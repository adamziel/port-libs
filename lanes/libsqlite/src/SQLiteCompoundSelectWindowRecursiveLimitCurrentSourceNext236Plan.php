<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext236Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext233Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $fence = self::metricFence($base);
        self::validateCursor($cursor, $fence);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next236-ready';
        $base['windowMetricFenceNext236'] = $fence;
        $base['cursor']['currentMetricFenceTokenNext236'] = $fence['currentMetricFenceToken'];
        $base['cursor']['requiredMetricAcksNext236'] = $fence['requiredMetricAcks'];
        $base['cursor']['nextExposure'] = $fence['nextExposure'];
        $base['replanReasons'][] = 'compound-window-metric-ack-fence-next236';
        $base['replanReasons'][] = 'recursive-window-metric-drift-holds-next-source-next236';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-metric-fence-next236';
        $base['dependency_closure'] = 'no new support component needed; next236 reuses accepted next233 compound SELECT, recursive LIMIT/OFFSET, window evaluation, final-order ordinal acknowledgements, and adds a per-row window metric acknowledgement fence before next-source rows are exposed';
        $base['non_overlap'] = 'next236 extends accepted next233 final ordinal resume by requiring current-source window metric acknowledgements; it avoids accepted next226/next228/next230/next233 compound recursive/window LIMIT variants, suite236 evidence, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, and encoding surfaces';

        return $base;
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>|null
     */
    private static function baseCursor(?array $cursor): ?array
    {
        if ($cursor === null || !isset($cursor['currentResumeToken'])) {
            return null;
        }

        $base = ['currentResumeToken' => $cursor['currentResumeToken']];
        if (isset($cursor['acknowledgedCurrentOrdinalAcks'])) {
            $base['acknowledgedCurrentOrdinalAcks'] = $cursor['acknowledgedCurrentOrdinalAcks'];
        }

        return $base;
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function metricFence(array $plan): array
    {
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $resume = is_array($plan['currentSourceResumeNext233'] ?? null) ? $plan['currentSourceResumeNext233'] : [];
        $currentToken = (string) ($resume['currentToken'] ?? '');
        $currentMetrics = self::rowMetrics($currentRows, $currentToken);
        $nextMetrics = self::rowMetrics($nextRows, (string) ($resume['nextToken'] ?? ''));
        $required = array_column($currentMetrics, 'ack');
        $currentSignatures = array_column($currentMetrics, 'signature');
        $nextSignatures = array_column($nextMetrics, 'signature');
        $payload = [
            'resumeToken' => (string) ($resume['currentResumeToken'] ?? ''),
            'currentMetrics' => $currentSignatures,
            'nextMetrics' => $nextSignatures,
        ];
        $token = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'currentMetricFenceToken' => $token,
            'requiredMetricAcks' => $required,
            'requiredMetricAckCount' => count($required),
            'currentMetricSignatures' => $currentSignatures,
            'nextMetricSignatures' => $nextSignatures,
            'currentMetricLabels' => array_column($currentMetrics, 'label'),
            'nextMetricLabels' => array_column($nextMetrics, 'label'),
            'metricDriftLabels' => self::metricDriftLabels($currentMetrics, $nextMetrics),
            'nextOnlyMetricLabels' => self::nextOnlyLabels($currentMetrics, $nextMetrics),
            'nextExposure' => 'held-until-current-window-metric-acks-match',
            'yieldBoundary' => 'compound-window-next236-current-window-metric-fence',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $fence
     */
    private static function validateCursor(?array $cursor, array $fence): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($cursor['currentMetricFenceTokenNext236']) && $cursor['currentMetricFenceTokenNext236'] !== $fence['currentMetricFenceToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next236 cursor does not match current metric fence token');
        }
        if (!array_key_exists('acknowledgedMetricAcksNext236', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedMetricAcksNext236'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next236 acknowledged metrics must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedMetricAcksNext236']));
        $required = self::stringList($fence['requiredMetricAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next236 current window metric acknowledgements do not match required fence set');
        }
    }

    /**
     * @param mixed $value
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{label:string,metric:mixed,signature:string,ack:string}>
     */
    private static function rowMetrics(array $rows, string $token): array
    {
        $metrics = [];
        foreach ($rows as $index => $row) {
            $label = (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? '');
            $metric = $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? $row['window_value'] ?? null;
            $signature = hash('sha256', json_encode([
                'ordinal' => $index + 1,
                'label' => $label,
                'metric' => $metric,
            ], JSON_THROW_ON_ERROR));
            $metrics[] = [
                'label' => $label,
                'metric' => $metric,
                'signature' => $signature,
                'ack' => hash('sha256', json_encode([
                    'token' => $token,
                    'signature' => $signature,
                ], JSON_THROW_ON_ERROR)),
            ];
        }

        return $metrics;
    }

    /**
     * @param list<array{label:string,metric:mixed,signature:string,ack:string}> $current
     * @param list<array{label:string,metric:mixed,signature:string,ack:string}> $next
     * @return list<string>
     */
    private static function metricDriftLabels(array $current, array $next): array
    {
        $currentByLabel = [];
        foreach ($current as $metric) {
            $currentByLabel[$metric['label']] = $metric['signature'];
        }

        $drift = [];
        foreach ($next as $metric) {
            if (isset($currentByLabel[$metric['label']]) && $currentByLabel[$metric['label']] !== $metric['signature']) {
                $drift[] = $metric['label'];
            }
        }

        return array_values(array_unique($drift));
    }

    /**
     * @param list<array{label:string,metric:mixed,signature:string,ack:string}> $current
     * @param list<array{label:string,metric:mixed,signature:string,ack:string}> $next
     * @return list<string>
     */
    private static function nextOnlyLabels(array $current, array $next): array
    {
        $currentLabels = array_flip(array_column($current, 'label'));
        $labels = [];
        foreach ($next as $metric) {
            if (!isset($currentLabels[$metric['label']])) {
                $labels[] = $metric['label'];
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }
}
