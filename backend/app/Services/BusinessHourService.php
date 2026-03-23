<?php

namespace App\Services;

use App\Models\BusinessHour;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BusinessHourService
{
    /**
     * Calculate an SLA deadline by adding $minutes of working time to $start.
     *
     * If no business hour schedule is provided, or the schedule is 24x7,
     * simply adds the minutes to $start and returns.
     *
     * Otherwise, iterates forward through calendar time, only counting
     * minutes that fall within working slots and skipping holidays.
     */
    public function calculateDeadline(Carbon $start, int $minutes, ?BusinessHour $businessHour): Carbon
    {
        if ($minutes <= 0) {
            return $start->copy();
        }

        // No business hours or 24x7 -- simple addition
        if (!$businessHour || $businessHour->is_24x7) {
            return $start->copy()->addMinutes($minutes);
        }

        $businessHour->loadMissing(['slots', 'holidays']);
        $tz = $businessHour->timezone ?? 'America/Lima';

        // Work in the business-hour timezone
        $cursor = $start->copy()->setTimezone($tz);
        $remainingMinutes = $minutes;

        // Index slots by day_of_week for quick lookup
        $slotsByDay = $businessHour->slots->where('is_working_day', true)->groupBy('day_of_week');

        // Pre-load holidays (dates as strings for fast lookup)
        $holidays = $this->getHolidayDates($businessHour, $cursor, $remainingMinutes);

        // Safety: cap iterations to prevent infinite loops (max ~2 years of days)
        $maxIterations = 730;
        $iterations = 0;

        while ($remainingMinutes > 0 && $iterations < $maxIterations) {
            $iterations++;
            $dayOfWeek = (int) $cursor->dayOfWeek; // 0=Sun ... 6=Sat

            // Skip if this day is a holiday
            if ($this->isHoliday($cursor, $holidays)) {
                $cursor->startOfDay()->addDay();
                continue;
            }

            // Get working slots for this day of week
            $daySlots = $slotsByDay->get($dayOfWeek, collect());

            if ($daySlots->isEmpty()) {
                // Non-working day, jump to next day
                $cursor->startOfDay()->addDay();
                continue;
            }

            // Sort slots by start_time
            $daySlots = $daySlots->sortBy('start_time');

            foreach ($daySlots as $slot) {
                if ($remainingMinutes <= 0) {
                    break;
                }

                $slotStart = $cursor->copy()->setTimeFromTimeString($slot->start_time);
                $slotEnd = $cursor->copy()->setTimeFromTimeString($slot->end_time);

                // If cursor is already past this slot's end, skip
                if ($cursor->gte($slotEnd)) {
                    continue;
                }

                // Effective start of counting is the later of cursor or slot start
                $effectiveStart = $cursor->gte($slotStart) ? $cursor->copy() : $slotStart->copy();

                // Minutes available in this slot from the effective start
                $availableMinutes = (int) $effectiveStart->diffInMinutes($slotEnd);

                if ($availableMinutes <= 0) {
                    continue;
                }

                if ($remainingMinutes <= $availableMinutes) {
                    // We finish within this slot
                    $cursor = $effectiveStart->addMinutes($remainingMinutes);
                    $remainingMinutes = 0;
                    break;
                }

                // Consume the entire remaining slot and continue
                $remainingMinutes -= $availableMinutes;
                $cursor = $slotEnd->copy();
            }

            // If we still have remaining minutes, advance to next day start
            if ($remainingMinutes > 0) {
                $cursor->startOfDay()->addDay();
            }
        }

        return $cursor;
    }

    /**
     * Count working minutes between two timestamps under a given schedule.
     */
    public function getWorkingMinutesBetween(Carbon $start, Carbon $end, ?BusinessHour $businessHour): int
    {
        if ($start->gte($end)) {
            return 0;
        }

        if (!$businessHour || $businessHour->is_24x7) {
            return (int) $start->diffInMinutes($end);
        }

        $businessHour->loadMissing(['slots', 'holidays']);
        $tz = $businessHour->timezone ?? 'America/Lima';

        $cursor = $start->copy()->setTimezone($tz);
        $endTz = $end->copy()->setTimezone($tz);
        $totalMinutes = 0;

        $slotsByDay = $businessHour->slots->where('is_working_day', true)->groupBy('day_of_week');
        $holidays = $this->getHolidayDates($businessHour, $cursor, 0, $endTz);

        $maxIterations = 730;
        $iterations = 0;

        while ($cursor->lt($endTz) && $iterations < $maxIterations) {
            $iterations++;
            $dayOfWeek = (int) $cursor->dayOfWeek;

            if ($this->isHoliday($cursor, $holidays)) {
                $cursor->startOfDay()->addDay();
                continue;
            }

            $daySlots = $slotsByDay->get($dayOfWeek, collect());

            if ($daySlots->isEmpty()) {
                $cursor->startOfDay()->addDay();
                continue;
            }

            $daySlots = $daySlots->sortBy('start_time');

            foreach ($daySlots as $slot) {
                $slotStart = $cursor->copy()->startOfDay()->setTimeFromTimeString($slot->start_time);
                $slotEnd = $cursor->copy()->startOfDay()->setTimeFromTimeString($slot->end_time);

                // Clamp to our range
                $effectiveStart = $cursor->gt($slotStart) ? $cursor->copy() : $slotStart->copy();
                $effectiveEnd = $endTz->lt($slotEnd) ? $endTz->copy() : $slotEnd->copy();

                if ($effectiveStart->lt($effectiveEnd)) {
                    $totalMinutes += (int) $effectiveStart->diffInMinutes($effectiveEnd);
                }
            }

            $cursor->startOfDay()->addDay();
        }

        return $totalMinutes;
    }

    /**
     * Check if a given moment falls within working hours.
     */
    public function isWorkingTime(Carbon $time, ?BusinessHour $businessHour): bool
    {
        if (!$businessHour || $businessHour->is_24x7) {
            return true;
        }

        $businessHour->loadMissing(['slots', 'holidays']);
        $tz = $businessHour->timezone ?? 'America/Lima';
        $timeTz = $time->copy()->setTimezone($tz);

        // Check holidays
        $holidays = $this->getHolidayDatesForDate($businessHour, $timeTz);
        if ($this->isHoliday($timeTz, $holidays)) {
            return false;
        }

        $dayOfWeek = (int) $timeTz->dayOfWeek;
        $daySlots = $businessHour->slots
            ->where('is_working_day', true)
            ->where('day_of_week', $dayOfWeek);

        foreach ($daySlots as $slot) {
            $slotStart = $timeTz->copy()->setTimeFromTimeString($slot->start_time);
            $slotEnd = $timeTz->copy()->setTimeFromTimeString($slot->end_time);

            if ($timeTz->gte($slotStart) && $timeTz->lt($slotEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a collection of holiday date strings for fast lookup.
     * Covers the date range that the cursor might traverse.
     */
    private function getHolidayDates(BusinessHour $bh, Carbon $from, int $minutesAhead, ?Carbon $to = null): Collection
    {
        // Estimate the date range we might need.
        // Rough upper bound: if 8-hr days, minutes/480 days, plus buffer.
        $daysEstimate = $to
            ? (int) $from->diffInDays($to) + 2
            : max((int) ceil($minutesAhead / 480) * 2, 30);

        $startDate = $from->copy()->startOfDay();
        $endDate = $from->copy()->addDays($daysEstimate)->endOfDay();

        return $this->buildHolidaySet($bh, $startDate, $endDate);
    }

    private function getHolidayDatesForDate(BusinessHour $bh, Carbon $date): Collection
    {
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();

        return $this->buildHolidaySet($bh, $startDate, $endDate);
    }

    private function buildHolidaySet(BusinessHour $bh, Carbon $startDate, Carbon $endDate): Collection
    {
        $dates = collect();

        // Holidays directly associated with this business hour schedule
        $holidays = $bh->holidays;

        // Also include tenant-wide holidays (business_hour_id IS NULL)
        $tenantHolidays = Holiday::withoutGlobalScopes()
            ->where('tenant_id', $bh->tenant_id)
            ->whereNull('business_hour_id')
            ->get();

        $allHolidays = $holidays->merge($tenantHolidays);

        foreach ($allHolidays as $holiday) {
            if ($holiday->recurring) {
                // For recurring holidays, match month-day across all years in range
                $year = $startDate->year;
                $endYear = $endDate->year;
                for ($y = $year; $y <= $endYear; $y++) {
                    $recurDate = $holiday->date->copy()->year($y)->format('Y-m-d');
                    $dates->push($recurDate);
                }
            } else {
                $dates->push($holiday->date->format('Y-m-d'));
            }
        }

        return $dates->unique();
    }

    private function isHoliday(Carbon $date, Collection $holidayDates): bool
    {
        return $holidayDates->contains($date->format('Y-m-d'));
    }
}
