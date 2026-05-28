<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext191Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
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

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next191-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'] ?? null,
                'offset' => $currentPlan['compound']['offset'] ?? 0,
                'hasUnionAllHead' => (self::operators($currentPlan)[0] ?? null) === 'UNION ALL',
                'hasDistinctTail' => in_array('UNION', self::operators($currentPlan), true),
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
                'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
                'valueOffsetFunctions' => self::valueOffsetFunctions($currentPlan),
                'ntileBuckets' => self::ntileBuckets($currentPreLimitRows),
            ],
            'recursive' => [
                'name' => $currentRecursive['name'],
                'columns' => $currentRecursive['columns'],
                'operator' => $currentRecursive['operator'],
                'currentRows' => $currentRecursive['rows'],
                'nextRows' => $nextRecursive['rows'],
                'currentTraceCount' => count($currentRecursive['trace']),
                'nextTraceCount' => count($nextRecursive['trace']),
                'currentSkippedLabels' => self::traceLabels($currentRecursive['trace'], false),
                'nextSkippedLabels' => self::traceLabels($nextRecursive['trace'], false),
                'currentEmittedLabels' => self::traceLabels($currentRecursive['trace'], true),
                'nextEmittedLabels' => self::traceLabels($nextRecursive['trace'], true),
                'currentFinalLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'nextFinalLimitRemaining' => self::lastTraceValue($nextRecursive['trace'], 'limit_remaining'),
                'currentFinalOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
                'nextFinalOffsetRemaining' => self::lastTraceValue($nextRecursive['trace'], 'offset_remaining'),
                'orderedQueue' => stripos($sql, 'ORDER BY 3 DESC') !== false,
            ],
            'valueOffsetTape' => [
                'current' => self::valueOffsetTape($currentPreLimitRows, $currentRows),
                'next' => self::valueOffsetTape($nextPreLimitRows, $nextRows),
                'changedPeerLabels' => self::changedPeerLabels($currentRows, $nextRows),
                'peerBoundary' => self::peerBoundary($currentRows, $nextRows),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'boundary' => self::boundaryDelta($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentRecursive, $currentPlan),
            'dependencies' => [
                'sqlite-select-sql-recursive-ordered-limit-offset-next191',
                'sqlite-select-sql-compound-nth-value-ntile-lead-next191',
                'sqlite-select-sql-union-distinct-value-offset-boundary-next191',
                'sqlite-current-source-next191',
            ],
            'dependency_closure' => 'no new support component needed; next191 reuses lane-local SELECT SQL recursive queue ORDER BY/LIMIT/OFFSET, compound UNION ALL/UNION distinct execution, nth_value/ntile/lead window evaluation, ORDER BY, and LIMIT/OFFSET helpers',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 needs compound SELECT SQL');
        }
        $operators = self::operators($currentPlan);
        if (!in_array('UNION ALL', $operators, true) || !in_array('UNION', $operators, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 needs UNION ALL and UNION distinct');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 needs final LIMIT/OFFSET');
        }
        if (preg_match('/WITH\s+RECURSIVE.*?\bORDER\s+BY\s+3\s+DESC\s+LIMIT\s+\d+\s+OFFSET\s+\d+/is', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 needs ordered recursive LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['nth_value', 'ntile', 'lead'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next191 needs {$function} window output");
            }
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next191 cannot isolate final LIMIT');
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
                    'function' => (string) ($term['function'] ?? ''),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'hasFrame' => is_array($term['frame'] ?? null),
                ];
            }
        }

        return $windows;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<string>
     */
    private static function valueOffsetFunctions(array $plan): array
    {
        return array_values(array_filter(
            array_map(static fn (array $term): string => strtolower((string) ($term['function'] ?? '')), self::windowTerms($plan)),
            static fn (string $function): bool => in_array($function, ['nth_value', 'ntile', 'lead'], true),
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function ntileBuckets(array $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $peer = $row['peer'] ?? null;
            if (is_int($peer)) {
                $buckets[] = $peer;
            }
        }

        return array_values(array_unique($buckets));
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<string>
     */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $labels = [];
        foreach ($trace as $step) {
            if (!is_array($step) || (bool) ($step['emitted'] ?? false) !== $emitted) {
                continue;
            }
            $current = $step['current'] ?? null;
            if (is_array($current) && isset($current['label'])) {
                $labels[] = (string) $current['label'];
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string,mixed>> $trace
     */
    private static function lastTraceValue(array $trace, string $key): ?int
    {
        $last = $trace === [] ? null : $trace[count($trace) - 1];
        $value = is_array($last) ? ($last[$key] ?? null) : null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @return list<array<string,mixed>>
     */
    private static function valueOffsetTape(array $preLimitRows, array $limitedRows): array
    {
        $limited = array_flip(self::rowSignatures($limitedRows));
        $tape = [];
        foreach ($preLimitRows as $index => $row) {
            $label = (string) ($row['label'] ?? '');
            $peer = $row['peer'] ?? null;
            $tape[] = [
                'index' => $index,
                'label' => $label,
                'peer' => $peer,
                'source' => str_starts_with($label, 'seed') ? 'recursive' : 'table',
                'peerType' => is_int($peer) ? 'ntile' : (str_starts_with((string) $peer, 'seed') ? 'recursive-value' : 'table-value'),
                'admittedByFinalLimit' => isset($limited[json_encode($row, JSON_THROW_ON_ERROR)]),
            ];
        }

        return $tape;
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function limitTrace(array $preLimitRows, array $limitedRows, array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;
        $limit = isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : count($limitedRows);

        return [
            'preLimitCount' => count($preLimitRows),
            'acceptedCount' => count($limitedRows),
            'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
            'admitted' => $limitedRows,
            'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
            'firstAdmitted' => $limitedRows[0] ?? null,
            'lastAdmitted' => $limitedRows === [] ? null : $limitedRows[count($limitedRows) - 1],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<mixed>
     */
    private static function changedPeerLabels(array $currentRows, array $nextRows): array
    {
        $current = array_values(array_unique(array_column($currentRows, 'peer'), SORT_REGULAR));
        $next = array_values(array_unique(array_column($nextRows, 'peer'), SORT_REGULAR));

        return array_values(array_unique(array_merge(array_diff($next, $current), array_diff($current, $next)), SORT_REGULAR));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function peerBoundary(array $currentRows, array $nextRows): array
    {
        return [
            'currentPeers' => array_values(array_column($currentRows, 'peer')),
            'nextPeers' => array_values(array_column($nextRows, 'peer')),
            'currentFirstPeer' => $currentRows[0]['peer'] ?? null,
            'nextFirstPeer' => $nextRows[0]['peer'] ?? null,
            'currentLastPeer' => $currentRows === [] ? null : ($currentRows[count($currentRows) - 1]['peer'] ?? null),
            'nextLastPeer' => $nextRows === [] ? null : ($nextRows[count($nextRows) - 1]['peer'] ?? null),
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function boundaryDelta(array $currentRows, array $nextRows): array
    {
        $currentSignatures = self::rowSignatures($currentRows);
        $nextSignatures = self::rowSignatures($nextRows);

        return [
            'currentFirst' => $currentRows[0] ?? null,
            'nextFirst' => $nextRows[0] ?? null,
            'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
            'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
            'gainedRows' => array_values(array_diff($nextSignatures, $currentSignatures)),
            'lostRows' => array_values(array_diff($currentSignatures, $nextSignatures)),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSignatures(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $currentPreLimitRows
     * @param list<array<string,mixed>> $nextPreLimitRows
     * @param array<string,mixed> $currentRecursive
     * @param array<string,mixed> $currentPlan
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentRecursive, array $currentPlan): array
    {
        $reasons = ['compound-nth-value-ntile-lead-window-offsets'];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-value-offset-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimitRows) !== self::rowSignatures($nextPreLimitRows)) {
            $reasons[] = 'prelimit-value-offset-rowset-changed';
        }
        if (self::changedPeerLabels($currentRows, $nextRows) !== []) {
            $reasons[] = 'value-offset-peer-boundary-changed';
        }
        if (($currentRecursive['rows'] ?? []) !== []) {
            $reasons[] = 'ordered-recursive-limit-offset-feeds-compound-arm';
        }
        if (self::valueOffsetFunctions($currentPlan) === ['nth_value', 'ntile', 'lead']) {
            $reasons[] = 'nth-value-ntile-lead-before-union-distinct';
        }
        if (($currentPlan['compound']['limit'] ?? null) !== null && (($currentPlan['compound']['offset'] ?? 0) > 0)) {
            $reasons[] = 'compound-tail-limit-offset-after-value-sort';
        }

        return $reasons;
    }
}
