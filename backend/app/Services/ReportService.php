<?php

namespace App\Services;

use App\Models\SavedReport;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Category;
use App\Models\SlaBreach;
use App\Models\TimeEntry;
use App\Models\SatisfactionSurvey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReportService
{
    /**
     * Execute a saved report and return data.
     */
    public function executeReport(SavedReport $report): array
    {
        return $this->executeConfig($report->config);
    }

    /**
     * Execute a report from raw config (for preview).
     */
    public function executeConfig(array $config): array
    {
        $startTime = microtime(true);

        $entity = $config['entity'] ?? 'tickets';
        $filters = $config['filters'] ?? [];
        $groupBy = $config['group_by'] ?? null;
        $metrics = $config['metrics'] ?? ['count'];
        $dateRange = $config['date_range'] ?? null;

        $data = match ($entity) {
            'tickets' => $this->queryTickets($filters, $groupBy, $metrics, $dateRange),
            'agents' => $this->queryAgents($filters, $metrics, $dateRange),
            'categories' => $this->queryCategories($filters, $metrics, $dateRange),
            default => $this->queryTickets($filters, $groupBy, $metrics, $dateRange),
        };

        $queryTime = round((microtime(true) - $startTime) * 1000);

        // Calculate summary totals
        $summary = $this->calculateSummary($data, $metrics);

        return [
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'query_time_ms' => $queryTime,
                'row_count' => count($data),
            ],
        ];
    }

    /**
     * Query tickets with filters, grouping, and metrics.
     */
    private function queryTickets(array $filters, ?string $groupBy, array $metrics, ?array $dateRange): array
    {
        $query = Ticket::query();

        // Apply date range
        $this->applyDateRange($query, $dateRange);

        // Apply filters
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }

        // If no group_by, return aggregated metrics as a single row
        if (!$groupBy) {
            return [$this->computeMetrics($query, $metrics, null, null)];
        }

        // Group by field
        $groupField = $this->resolveGroupField($groupBy);
        $labelResolver = $this->getLabelResolver($groupBy);

        // Get distinct group values
        $groups = (clone $query)->select($groupField)->distinct()->pluck($groupField);

        $rows = [];
        foreach ($groups as $groupValue) {
            $groupQuery = (clone $query)->where($groupField, $groupValue);
            $row = $this->computeMetrics($groupQuery, $metrics, $groupBy, $groupValue);
            $row['group_key'] = $groupBy;
            $row['group_value'] = $groupValue;
            $row['group_label'] = $labelResolver ? $labelResolver($groupValue) : ($groupValue ?? 'N/A');
            $rows[] = $row;
        }

        // Sort by count descending
        usort($rows, fn($a, $b) => ($b['count'] ?? 0) - ($a['count'] ?? 0));

        return $rows;
    }

    /**
     * Query agent performance.
     */
    private function queryAgents(array $filters, array $metrics, ?array $dateRange): array
    {
        $query = Ticket::query()->whereNotNull('assigned_to');

        $this->applyDateRange($query, $dateRange);
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }

        $agentIds = (clone $query)->select('assigned_to')->distinct()->pluck('assigned_to');
        $agents = User::withoutGlobalScopes()->whereIn('id', $agentIds)->pluck('name', 'id');

        $rows = [];
        foreach ($agentIds as $agentId) {
            $agentQuery = (clone $query)->where('assigned_to', $agentId);
            $row = $this->computeMetrics($agentQuery, $metrics, 'assigned_to', $agentId);
            $row['group_key'] = 'agent';
            $row['group_value'] = $agentId;
            $row['group_label'] = $agents[$agentId] ?? 'Desconocido';
            $rows[] = $row;
        }

        usort($rows, fn($a, $b) => ($b['count'] ?? 0) - ($a['count'] ?? 0));

        return $rows;
    }

    /**
     * Query by categories.
     */
    private function queryCategories(array $filters, array $metrics, ?array $dateRange): array
    {
        $query = Ticket::query();

        $this->applyDateRange($query, $dateRange);
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }

        $categoryIds = (clone $query)->select('category_id')->distinct()->pluck('category_id');
        $categories = Category::withoutGlobalScopes()->whereIn('id', $categoryIds)->pluck('name', 'id');

        $rows = [];
        foreach ($categoryIds as $catId) {
            $catQuery = (clone $query)->where('category_id', $catId);
            $row = $this->computeMetrics($catQuery, $metrics, 'category_id', $catId);
            $row['group_key'] = 'category';
            $row['group_value'] = $catId;
            $row['group_label'] = $catId ? ($categories[$catId] ?? 'Desconocida') : 'Sin categoría';
            $rows[] = $row;
        }

        usort($rows, fn($a, $b) => ($b['count'] ?? 0) - ($a['count'] ?? 0));

        return $rows;
    }

    /**
     * Compute requested metrics for a given query scope.
     */
    private function computeMetrics($query, array $metrics, ?string $groupBy, $groupValue): array
    {
        $row = [];

        foreach ($metrics as $metric) {
            $row[$metric] = match ($metric) {
                'count' => (clone $query)->count(),
                'avg_resolution_time' => $this->avgResolutionTime(clone $query),
                'avg_response_time' => $this->avgResponseTime(clone $query),
                'sla_compliance_rate' => $this->slaComplianceRate(clone $query),
                'avg_rating' => $this->avgRating(clone $query),
                'total_time_spent' => $this->totalTimeSpent(clone $query),
                default => null,
            };
        }

        return $row;
    }

    private function avgResolutionTime($query): ?float
    {
        $val = $query->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as val')
            ->value('val');

        return $val !== null ? round((float) $val, 1) : null;
    }

    private function avgResponseTime($query): ?float
    {
        $val = $query->whereNotNull('responded_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) as val')
            ->value('val');

        return $val !== null ? round((float) $val, 1) : null;
    }

    private function slaComplianceRate($query): ?float
    {
        $ticketIds = (clone $query)->pluck('id');
        if ($ticketIds->isEmpty()) {
            return 100.0;
        }

        $total = $ticketIds->count();
        $breached = SlaBreach::whereIn('ticket_id', $ticketIds)->distinct('ticket_id')->count('ticket_id');

        return round((($total - $breached) / $total) * 100, 1);
    }

    private function avgRating($query): ?float
    {
        $val = $query->whereNotNull('satisfaction_rating')
            ->where('satisfaction_rating', '>', 0)
            ->avg('satisfaction_rating');

        return $val !== null ? round((float) $val, 1) : null;
    }

    private function totalTimeSpent($query): float
    {
        $ticketIds = (clone $query)->pluck('id');
        if ($ticketIds->isEmpty()) {
            return 0;
        }

        return round((float) TimeEntry::whereIn('ticket_id', $ticketIds)->sum('hours'), 1);
    }

    /**
     * Apply a date range filter to a query.
     */
    private function applyDateRange($query, ?array $dateRange): void
    {
        if (!$dateRange) {
            return;
        }

        $type = $dateRange['type'] ?? null;
        $now = Carbon::now();

        match ($type) {
            'last_7_days' => $query->where('tickets.created_at', '>=', $now->copy()->subDays(7)),
            'last_30_days' => $query->where('tickets.created_at', '>=', $now->copy()->subDays(30)),
            'last_90_days' => $query->where('tickets.created_at', '>=', $now->copy()->subDays(90)),
            'this_month' => $query->where('tickets.created_at', '>=', $now->copy()->startOfMonth()),
            'last_month' => $query->whereBetween('tickets.created_at', [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ]),
            'this_year' => $query->where('tickets.created_at', '>=', $now->copy()->startOfYear()),
            'custom' => $this->applyCustomDateRange($query, $dateRange),
            default => null,
        };
    }

    private function applyCustomDateRange($query, array $dateRange): void
    {
        if (!empty($dateRange['start'])) {
            $query->where('tickets.created_at', '>=', Carbon::parse($dateRange['start'])->startOfDay());
        }
        if (!empty($dateRange['end'])) {
            $query->where('tickets.created_at', '<=', Carbon::parse($dateRange['end'])->endOfDay());
        }
    }

    /**
     * Apply a single filter condition.
     */
    private function applyFilter($query, array $filter): void
    {
        $field = $filter['field'] ?? null;
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        if (!$field || $value === null) {
            return;
        }

        // Map filter field to actual DB column
        $column = match ($field) {
            'status' => 'status',
            'priority' => 'priority',
            'type' => 'type',
            'category_id' => 'category_id',
            'department_id' => 'department_id',
            'assigned_to' => 'assigned_to',
            'requester_id' => 'requester_id',
            'source' => 'source',
            'agent_group_id' => 'agent_group_id',
            'is_spam' => 'is_spam',
            default => null,
        };

        if (!$column) {
            return;
        }

        match ($operator) {
            'in' => $query->whereIn($column, (array) $value),
            'not_in' => $query->whereNotIn($column, (array) $value),
            '=' => $query->where($column, $value),
            '!=' => $query->where($column, '!=', $value),
            'is_null' => $query->whereNull($column),
            'is_not_null' => $query->whereNotNull($column),
            default => $query->where($column, $operator, $value),
        };
    }

    /**
     * Resolve group_by value to DB column.
     */
    private function resolveGroupField(string $groupBy): string
    {
        return match ($groupBy) {
            'status' => 'status',
            'priority' => 'priority',
            'type' => 'type',
            'category_id' => 'category_id',
            'department_id' => 'department_id',
            'assigned_to' => 'assigned_to',
            'source' => 'source',
            'agent_group_id' => 'agent_group_id',
            default => $groupBy,
        };
    }

    /**
     * Get a callback to resolve human-readable labels for group values.
     */
    private function getLabelResolver(string $groupBy): ?\Closure
    {
        return match ($groupBy) {
            'category_id' => fn($v) => $v ? (Category::withoutGlobalScopes()->find($v)?->name ?? 'Desconocida') : 'Sin categoría',
            'assigned_to' => fn($v) => $v ? (User::withoutGlobalScopes()->find($v)?->name ?? 'Desconocido') : 'Sin asignar',
            'department_id' => fn($v) => $v ? (\App\Models\Department::withoutGlobalScopes()->find($v)?->name ?? 'Desconocido') : 'Sin departamento',
            'agent_group_id' => fn($v) => $v ? (\App\Models\AgentGroup::withoutGlobalScopes()->find($v)?->name ?? 'Desconocido') : 'Sin grupo',
            default => null,
        };
    }

    /**
     * Calculate summary totals from result data.
     */
    private function calculateSummary(array $data, array $metrics): array
    {
        $summary = [];

        foreach ($metrics as $metric) {
            $values = array_filter(array_column($data, $metric), fn($v) => $v !== null);

            if (empty($values)) {
                $summary[$metric] = null;
                continue;
            }

            $summary[$metric] = match ($metric) {
                'count', 'total_time_spent' => array_sum($values),
                'avg_resolution_time', 'avg_response_time', 'avg_rating' => round(array_sum($values) / count($values), 1),
                'sla_compliance_rate' => round(array_sum($values) / count($values), 1),
                default => null,
            };
        }

        return $summary;
    }

    /**
     * Return available filter fields for a given entity.
     */
    public function getAvailableFilters(string $entity): array
    {
        $ticketFilters = [
            ['field' => 'status', 'label' => 'Estado', 'type' => 'select', 'operators' => ['in', 'not_in'], 'options' => ['open', 'in_progress', 'pending', 'resolved', 'closed']],
            ['field' => 'priority', 'label' => 'Prioridad', 'type' => 'select', 'operators' => ['in', 'not_in'], 'options' => ['low', 'medium', 'high', 'urgent']],
            ['field' => 'type', 'label' => 'Tipo', 'type' => 'select', 'operators' => ['in', 'not_in'], 'options' => ['incident', 'request', 'problem', 'change']],
            ['field' => 'category_id', 'label' => 'Categoría', 'type' => 'relation', 'operators' => ['in', 'not_in', 'is_null', 'is_not_null']],
            ['field' => 'department_id', 'label' => 'Departamento', 'type' => 'relation', 'operators' => ['in', 'not_in', 'is_null', 'is_not_null']],
            ['field' => 'assigned_to', 'label' => 'Agente asignado', 'type' => 'relation', 'operators' => ['in', 'not_in', 'is_null', 'is_not_null']],
            ['field' => 'source', 'label' => 'Fuente', 'type' => 'select', 'operators' => ['in', 'not_in'], 'options' => ['portal', 'email', 'chatbot', 'catalog', 'api', 'phone']],
            ['field' => 'agent_group_id', 'label' => 'Grupo de agentes', 'type' => 'relation', 'operators' => ['in', 'not_in', 'is_null', 'is_not_null']],
        ];

        return match ($entity) {
            'tickets', 'categories', 'agents' => $ticketFilters,
            default => $ticketFilters,
        };
    }

    /**
     * Return available metrics for a given entity.
     */
    public function getAvailableMetrics(string $entity): array
    {
        return [
            ['key' => 'count', 'label' => 'Cantidad', 'description' => 'Número total de tickets'],
            ['key' => 'avg_resolution_time', 'label' => 'Tiempo promedio de resolución', 'description' => 'Horas promedio desde creación hasta resolución'],
            ['key' => 'avg_response_time', 'label' => 'Tiempo promedio de respuesta', 'description' => 'Horas promedio hasta primera respuesta'],
            ['key' => 'sla_compliance_rate', 'label' => 'Cumplimiento SLA', 'description' => 'Porcentaje de tickets dentro del SLA'],
            ['key' => 'avg_rating', 'label' => 'Calificación promedio', 'description' => 'Promedio de satisfacción del cliente'],
            ['key' => 'total_time_spent', 'label' => 'Tiempo total invertido', 'description' => 'Suma total de horas registradas'],
        ];
    }

    /**
     * Return available group-by options for a given entity.
     */
    public function getAvailableGroupings(string $entity): array
    {
        return match ($entity) {
            'tickets' => [
                ['key' => 'status', 'label' => 'Estado'],
                ['key' => 'priority', 'label' => 'Prioridad'],
                ['key' => 'type', 'label' => 'Tipo'],
                ['key' => 'category_id', 'label' => 'Categoría'],
                ['key' => 'department_id', 'label' => 'Departamento'],
                ['key' => 'assigned_to', 'label' => 'Agente asignado'],
                ['key' => 'source', 'label' => 'Fuente'],
                ['key' => 'agent_group_id', 'label' => 'Grupo de agentes'],
            ],
            'agents' => [
                ['key' => 'assigned_to', 'label' => 'Agente'],
            ],
            'categories' => [
                ['key' => 'category_id', 'label' => 'Categoría'],
            ],
            default => [],
        };
    }

    /**
     * Export data rows as CSV string.
     */
    public function exportToCsv(array $data, array $columns): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Header row
        $headers = [];
        foreach ($columns as $col) {
            $headers[] = $this->getColumnLabel($col);
        }
        fputcsv($output, $headers);

        // Data rows
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($columns as $col) {
                $csvRow[] = $this->resolveColumnValue($row, $col);
            }
            fputcsv($output, $csvRow);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Add BOM for Excel compatibility
        return "\xEF\xBB\xBF" . $csv;
    }

    private function getColumnLabel(string $col): string
    {
        return match ($col) {
            'group_label' => 'Grupo',
            'count' => 'Cantidad',
            'avg_resolution_time' => 'T. Resolución Prom. (h)',
            'avg_response_time' => 'T. Respuesta Prom. (h)',
            'sla_compliance_rate' => 'Cumplimiento SLA (%)',
            'avg_rating' => 'Calificación Prom.',
            'total_time_spent' => 'Tiempo Total (h)',
            default => $col,
        };
    }

    private function resolveColumnValue(array $row, string $col): mixed
    {
        return $row[$col] ?? '';
    }

    /**
     * Return pre-built report templates.
     */
    public function getPrebuiltReports(): array
    {
        return [
            [
                'name' => 'Resumen de tickets por estado',
                'description' => 'Distribución de todos los tickets agrupados por su estado actual',
                'report_type' => 'tickets',
                'config' => [
                    'entity' => 'tickets',
                    'filters' => [],
                    'group_by' => 'status',
                    'metrics' => ['count', 'avg_resolution_time'],
                    'date_range' => ['type' => 'last_30_days'],
                    'chart_type' => 'bar',
                    'columns' => ['group_label', 'count', 'avg_resolution_time'],
                ],
            ],
            [
                'name' => 'Rendimiento de agentes',
                'description' => 'Métricas de desempeño por cada agente asignado',
                'report_type' => 'agents',
                'config' => [
                    'entity' => 'agents',
                    'filters' => [],
                    'group_by' => null,
                    'metrics' => ['count', 'avg_resolution_time', 'avg_response_time', 'sla_compliance_rate'],
                    'date_range' => ['type' => 'last_30_days'],
                    'chart_type' => 'bar',
                    'columns' => ['group_label', 'count', 'avg_resolution_time', 'avg_response_time', 'sla_compliance_rate'],
                ],
            ],
            [
                'name' => 'Cumplimiento SLA por categoría',
                'description' => 'Porcentaje de cumplimiento de SLA desglosado por categoría',
                'report_type' => 'sla',
                'config' => [
                    'entity' => 'categories',
                    'filters' => [],
                    'group_by' => null,
                    'metrics' => ['count', 'sla_compliance_rate', 'avg_resolution_time'],
                    'date_range' => ['type' => 'last_30_days'],
                    'chart_type' => 'bar',
                    'columns' => ['group_label', 'count', 'sla_compliance_rate', 'avg_resolution_time'],
                ],
            ],
            [
                'name' => 'Tendencia de tickets (últimos 30 días)',
                'description' => 'Evolución diaria de tickets creados en los últimos 30 días',
                'report_type' => 'trends',
                'config' => [
                    'entity' => 'tickets',
                    'filters' => [],
                    'group_by' => 'status',
                    'metrics' => ['count'],
                    'date_range' => ['type' => 'last_30_days'],
                    'chart_type' => 'line',
                    'columns' => ['group_label', 'count'],
                ],
            ],
            [
                'name' => 'Tickets por canal/fuente',
                'description' => 'Distribución de tickets según su canal de origen',
                'report_type' => 'tickets',
                'config' => [
                    'entity' => 'tickets',
                    'filters' => [],
                    'group_by' => 'source',
                    'metrics' => ['count', 'avg_response_time'],
                    'date_range' => ['type' => 'last_30_days'],
                    'chart_type' => 'pie',
                    'columns' => ['group_label', 'count', 'avg_response_time'],
                ],
            ],
            [
                'name' => 'Tiempo promedio de resolución por prioridad',
                'description' => 'Comparación del tiempo de resolución según la prioridad del ticket',
                'report_type' => 'tickets',
                'config' => [
                    'entity' => 'tickets',
                    'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['resolved', 'closed']]],
                    'group_by' => 'priority',
                    'metrics' => ['count', 'avg_resolution_time', 'avg_response_time'],
                    'date_range' => ['type' => 'last_90_days'],
                    'chart_type' => 'bar',
                    'columns' => ['group_label', 'count', 'avg_resolution_time', 'avg_response_time'],
                ],
            ],
        ];
    }
}
