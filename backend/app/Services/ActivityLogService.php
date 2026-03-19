<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public static function log(
        User $user,
        string $action,
        Model $subject,
        string $description,
        ?array $properties = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }

    /**
     * Log individual field changes for a ticket update.
     * Call AFTER $ticket->update() so $ticket has new values.
     */
    public static function logTicketFieldChanges(
        User $user,
        Ticket $ticket,
        array $oldValues,
        array $validated
    ): void {
        $fieldLabels = [
            'status' => 'el estado',
            'priority' => 'la prioridad',
            'planned_start_date' => 'la fecha de inicio planificada',
            'planned_end_date' => 'la fecha de finalización planificada',
        ];

        $statusLabels = [
            'open' => 'Abierto',
            'in_progress' => 'En Progreso',
            'pending' => 'Pendiente',
            'resolved' => 'Resuelto',
            'closed' => 'Cerrado',
        ];

        $priorityLabels = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];

        $baseProps = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'ticket_title' => $ticket->title,
        ];

        $logged = false;

        foreach ($fieldLabels as $field => $label) {
            if (!array_key_exists($field, $oldValues)) continue;

            $oldVal = $oldValues[$field];
            $newVal = $ticket->{$field};

            // Normalize for comparison (dates vs strings)
            $oldStr = $oldVal instanceof \DateTimeInterface ? $oldVal->format('Y-m-d H:i:s') : (string) $oldVal;
            $newStr = $newVal instanceof \DateTimeInterface ? $newVal->format('Y-m-d H:i:s') : (string) $newVal;

            if ($oldStr === $newStr) continue;

            $displayValue = match ($field) {
                'status' => $statusLabels[$newVal] ?? $newVal,
                'priority' => $priorityLabels[$newVal] ?? $newVal,
                'planned_start_date', 'planned_end_date' => $newVal?->format('D, d M, Y g:i A') ?? 'ninguna',
                default => (string) $newVal,
            };

            self::log(
                $user,
                'updated',
                $ticket,
                "cambió {$label} del ticket {$ticket->title} ({$ticket->ticket_number}) a {$displayValue}",
                array_merge($baseProps, [
                    'field' => $field,
                    'old_value' => $oldStr,
                    'new_value' => $newStr,
                    'display_value' => $displayValue,
                ])
            );

            $logged = true;
        }

        // If no significant field was logged but there were changes, log a generic update
        if (!$logged && !empty($validated)) {
            self::log(
                $user,
                'updated',
                $ticket,
                "actualizó el ticket {$ticket->title} ({$ticket->ticket_number})",
                $baseProps
            );
        }
    }
}
