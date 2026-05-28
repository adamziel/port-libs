<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext237Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
        $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
        self::assertSupported($sql, $currentPlan, $nextPlan);

        $preLimitSql = self::withoutFinalLimit($sql);
        $traceSql = self::recursiveTraceSql($sql);
        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
        $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
        $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
        $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);
        $currentWindows = self::windowTerms($currentPlan);
        $nextWindows = self::windowTerms($nextPlan);
        $currentToken = self::token($currentRows, $currentPreLimitRows, $currentRecursive['trace'], $currentWindows);
        $nextToken = self::token($nextRows, $nextPreLimitRows, $nextRecursive['trace'], $nextWindows);
        $dequeue = self::dequeueFence($currentRecursive['trace'], $currentRows, $nextRows, $currentToken);
        self::validateCursor($cursor, $currentToken, $dequeue);

        $offset = self::offset($currentPlan);
        $limit = self::limit($currentPlan);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next237-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $limit,
                'offset' => $offset,
                'hasUnionAllHead' => (self::operators($currentPlan)[0] ?? null) === 'UNION ALL',
                'hasIntersectMiddle' => in_array('INTERSECT', self::operators($currentPlan), true),
                'hasExceptTail' => in_array('EXCEPT', self::operators($currentPlan), true),
            ],
            'recursiveQueue' => [
                'name' => $currentRecursive['name'],
                'columns' => $currentRecursive['columns'],
                'operator' => $currentRecursive['operator'],
                'currentTraceCount' => count($currentRecursive['trace']),
                'nextTraceCount' => count($nextRecursive['trace']),
                'currentSkippedLabels' => self::traceLabels($currentRecursive['trace'], false),
                'nextSkippedLabels' => self::traceLabels($nextRecursive['trace'], false),
                'currentEmittedLabels' => self::traceLabels($currentRecursive['trace'], true),
                'nextEmittedLabels' => self::traceLabels($nextRecursive['trace'], true),
                'currentLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
            ],
            'windows' => [
                'current' => $currentWindows,
                'next' => $nextWindows,
                'functions' => array_values(array_unique(array_column($currentWindows, 'function'))),
                'currentMetrics' => self::columnValues($currentPreLimitRows, 'metric'),
                'nextMetrics' => self::columnValues($nextPreLimitRows, 'metric'),
            ],
            'sourceWindow' => [
                'currentToken' => $currentToken,
                'nextToken' => $nextToken,
                'currentAdmittedLabels' => self::labels($currentRows),
                'nextAdmittedLabels' => self::labels($nextRows),
                'currentSkippedLabels' => self::labels(array_slice($currentPreLimitRows, 0, $offset)),
                'nextSkippedLabels' => self::labels(array_slice($nextPreLimitRows, 0, $offset)),
                'currentTruncatedLabels' => self::labels(array_slice($currentPreLimitRows, $offset + $limit)),
                'nextTruncatedLabels' => self::labels(array_slice($nextPreLimitRows, $offset + $limit)),
                'nextOnlyAdmittedLabels' => self::changedLabels($currentRows, $nextRows, true),
                'currentOnlyAdmittedLabels' => self::changedLabels($currentRows, $nextRows, false),
                'intersectExceptBoundaryChanged' => $currentToken !== $nextToken,
            ],
            'currentSourceDequeueNext237' => $dequeue,
            'cursor' => [
                'currentToken' => $currentToken,
                'resumeOffset' => $offset + $limit,
                'limit' => $limit,
                'currentRowCount' => count($currentRows),
                'nextRowCount' => count($nextRows),
                'currentDequeueTokenNext237' => $dequeue['currentDequeueToken'],
                'requiredCurrentDequeueAcksNext237' => $dequeue['requiredCurrentDequeueAcks'],
                'nextExposureNext237' => $dequeue['nextExposure'],
            ],
            'replanReasons' => [
                'compound-union-all-intersect-except-rank-current-source-next237',
                'recursive-limit-offset-dequeue-before-window-compound-next237',
                'next-source-row-number-shift-held-by-current-dequeue-acks-next237',
                'wordpress-option-preview-stale-dequeue-cursor-fence-next237',
            ],
            'dependencies' => [
                'sqlite-select-sql-recursive-queue-limit-offset-next237',
                'sqlite-select-sql-rank-row-number-window-intersect-except-next237',
                'sqlite-compound-current-source-dequeue-token-fence-next237',
            ],
            'dependency_closure' => 'no new support component needed; next237 reuses native SELECT SQL compound execution, recursive queue LIMIT/OFFSET tracing, rank/row_number window output, INTERSECT/EXCEPT membership, and final LIMIT helpers',
            'non_overlap' => 'next237 adds a current-source recursive dequeue acknowledgement fence for a UNION ALL -> INTERSECT -> EXCEPT chain using rank and row_number window output; it avoids accepted next233 final-order ordinal resume, next229 UNION DISTINCT -> EXCEPT dense_rank, next226 aggregate windows through EXCEPT/INTERSECT, next196 ntile/first_value, JSON, WAL/VFS, B-tree, encoding, trigger, PRAGMA, and suite evidence clusters',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 needs compound SELECT SQL');
        }
        $operators = self::operators($currentPlan);
        foreach (['UNION ALL', 'INTERSECT', 'EXCEPT'] as $operator) {
            if (!in_array($operator, $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 needs UNION ALL, INTERSECT, and EXCEPT');
            }
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 needs final LIMIT/OFFSET');
        }
        if (preg_match('/\bLIMIT\s+\d+\s+OFFSET\s+\d+/is', self::recursiveBody($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 needs recursive LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['rank', 'row_number'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 needs rank and row_number window output');
            }
        }
    }

    private static function recursiveBody(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^WITH\s+RECURSIVE\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s+AS\s*\((.*)\)\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 cannot isolate recursive CTE body');
        }

        return $match[1];
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 cannot isolate final LIMIT');
        }

        return $without;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<string>
     */
    private static function operators(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return array_values(array_map('strtoupper', is_array($compound['operators'] ?? null) ? $compound['operators'] : []));
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<string>
     */
    private static function orderColumns(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        if (!is_array($compound['orderBy'] ?? null)) {
            return [];
        }

        return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function windowTerms(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $windows = [];
        foreach ((array) ($compound['arms'] ?? []) as $armIndex => $arm) {
            $select = is_array($arm) && is_array($arm['select'] ?? null) ? $arm['select'] : [];
            foreach ($select as $selectIndex => $term) {
                if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                    continue;
                }
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => strtolower((string) ($term['function'] ?? '')),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                ];
            }
        }

        return $windows;
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function dequeueFence(array $trace, array $currentRows, array $nextRows, string $currentToken): array
    {
        $emitted = self::traceLabels($trace, true);
        $required = [];
        foreach ($emitted as $index => $label) {
            $required[] = hash('sha256', json_encode([
                'token' => $currentToken,
                'dequeueOrdinal' => $index + 1,
                'label' => $label,
            ], JSON_THROW_ON_ERROR));
        }
        $dequeueToken = hash('sha256', json_encode([
            'token' => $currentToken,
            'emitted' => $emitted,
            'currentFinalPage' => self::labels($currentRows),
            'nextFinalPage' => self::labels($nextRows),
        ], JSON_THROW_ON_ERROR));

        return [
            'currentDequeueToken' => $dequeueToken,
            'requiredCurrentDequeueAcks' => $required,
            'requiredAckCount' => count($required),
            'currentEmittedLabels' => $emitted,
            'currentFinalPageLabels' => self::labels($currentRows),
            'nextFinalPageLabels' => self::labels($nextRows),
            'nextOnlyLabels' => self::changedLabels($currentRows, $nextRows, true),
            'currentOnlyLabels' => self::changedLabels($currentRows, $nextRows, false),
            'nextExposure' => 'held-until-current-recursive-dequeue-acks',
            'yieldBoundary' => 'compound-window-next237-current-recursive-dequeue-fences-next-source',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $dequeue
     */
    private static function validateCursor(?array $cursor, string $currentToken, array $dequeue): void
    {
        if ($cursor === null) {
            return;
        }
        if (($cursor['currentToken'] ?? null) !== $currentToken) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 cursor does not match current token');
        }
        if (isset($cursor['currentDequeueTokenNext237']) && $cursor['currentDequeueTokenNext237'] !== $dequeue['currentDequeueToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 cursor does not match current dequeue token');
        }
        if (!array_key_exists('acknowledgedCurrentDequeueAcksNext237', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCurrentDequeueAcksNext237'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 acknowledged dequeue acks must be a list');
        }
        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedCurrentDequeueAcksNext237']));
        $required = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $dequeue['requiredCurrentDequeueAcks']));
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next237 current dequeue acknowledgements do not match required set');
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function columnValues(array $rows, string $column): array
    {
        return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<string>
     */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $labels = [];
        foreach ($trace as $entry) {
            if (!is_array($entry) || (bool) ($entry['emitted'] ?? false) !== $emitted) {
                continue;
            }
            $row = is_array($entry['current'] ?? null) ? $entry['current'] : (is_array($entry['row'] ?? null) ? $entry['row'] : []);
            $labels[] = (string) ($row['label'] ?? $row['name'] ?? $row[1] ?? '');
        }

        return $labels;
    }

    /**
     * @param list<array<string,mixed>> $trace
     */
    private static function lastTraceValue(array $trace, string $key): int
    {
        $last = end($trace);
        if (!is_array($last)) {
            return 0;
        }

        return (int) ($last[$key] ?? 0);
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows, bool $nextOnly): array
    {
        $current = self::labels($currentRows);
        $next = self::labels($nextRows);

        return array_values(array_diff($nextOnly ? $next : $current, $nextOnly ? $current : $next));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $trace
     * @param list<array<string,mixed>> $windows
     */
    private static function token(array $rows, array $preLimitRows, array $trace, array $windows): string
    {
        return hash('sha256', json_encode([
            'rows' => $rows,
            'preLimitRows' => $preLimitRows,
            'trace' => $trace,
            'windows' => $windows,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function limit(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return (int) ($compound['limit'] ?? 0);
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function offset(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return (int) ($compound['offset'] ?? 0);
    }
}
