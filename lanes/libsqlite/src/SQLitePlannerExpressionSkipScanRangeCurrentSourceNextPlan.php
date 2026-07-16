<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array<string,mixed>> $queryTerms
         * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materialize(
            array $preparedSource,
            array $currentSource,
            SQLiteIndexPredicate $partialPredicate,
            array $queryTerms,
            array $orderByExpressions,
            array $neededColumns,
        ): array {
            $preparedView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan(
                $preparedSource,
                $preparedSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );
            $currentView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan(
                $preparedSource,
                $currentSource,
                $partialPredicate,
                self::rangeTermsForSource($queryTerms, $currentSource),
                $orderByExpressions,
                $neededColumns,
            );

            $preparedPlan = self::arrayValue($preparedView, 'selectedPlan');
            $currentPlan = self::arrayValue($currentView, 'selectedPlan');
            $preparedRowids = self::intList($preparedPlan['rowids'] ?? []);
            $currentRowids = self::intList($currentPlan['rowids'] ?? []);
            $preparedRangeSignature = self::rangeSignature($preparedSource);
            $currentRangeSignature = self::rangeSignature($currentSource);
            $rangeChanged = $preparedRangeSignature !== $currentRangeSignature;
            $ready = ($currentView['status'] ?? null) === 'usable'
                && ($currentPlan['expressionSkipScan'] ?? false) === true
                && ($currentPlan['usesSkipScan'] ?? false) === true
                && $rangeChanged;

            return array_replace($currentView, [
                'status' => $ready ? 'expression-skipscan-range-current-source-ready' : 'requires-next-stage',
                'preparedRangeSignature' => $preparedRangeSignature,
                'currentRangeSignature' => $currentRangeSignature,
                'rangeFenceChanged' => $rangeChanged,
                'lowerBoundChanged' => ($preparedSource['lowerInclusive'] ?? null) !== ($currentSource['lowerInclusive'] ?? null),
                'upperBoundChanged' => ($preparedSource['upperBound'] ?? null) !== ($currentSource['upperBound'] ?? null),
                'upperInclusiveChanged' => (bool) ($preparedSource['upperInclusive'] ?? true) !== (bool) ($currentSource['upperInclusive'] ?? true),
                'collationChanged' => strtoupper((string) ($preparedSource['collation'] ?? 'BINARY')) !== strtoupper((string) ($currentSource['collation'] ?? 'BINARY')),
                'preparedSkipScanRowids' => $preparedRowids,
                'currentSkipScanRowids' => $currentRowids,
                'rangeRejectedRowids' => array_values(array_diff($preparedRowids, $currentRowids)),
                'rangeAdmittedRowids' => array_values(array_diff($currentRowids, $preparedRowids)),
                'rangeStableRowids' => array_values(array_intersect($currentRowids, $preparedRowids)),
                'rangeLoopDelta' => self::loopDelta(self::loopsByPrefix($preparedPlan), self::loopsByPrefix($currentPlan)),
                'rangeFence' => self::rangeFence($currentSource, $currentPlan, $currentRangeSignature),
                'cursorTape' => self::cursorTape($currentSource, $currentPlan, $currentRangeSignature),
                'detail' => ($currentView['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' current-range-fence=' . ($rangeChanged ? 'changed' : 'stable'),
                'dependencies' => [
                    'SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan',
                    'sqlite-sqlplanner-expression-skipscan-range-current-source',
                ],
                'dependency_closure' => 'no new support component needed; the canonical planner reuses native PHP expression skip-scan materialization, current-source fences, and bounded range cursor evidence',
                'non_overlap' => 'avoids partial expression skip-scan current-source, expression covering current-source, STAT4 stale-source next137, partial predicate changes next139, covering partial range next131, and expression-index range-cost ranking; this slice only fences stale expression skip-scan lower/upper/collation range bounds on the current source',
            ]);
        }

        /** @param list<array<string,mixed>> $terms @return list<array<string,mixed>> */
        private static function rangeTermsForSource(array $terms, array $source): array
        {
            $rangeExpression = self::stringValue($source, 'rangeExpression');
            $lower = $source['lowerInclusive'] ?? null;
            $upper = $source['upperBound'] ?? null;
            $rewritten = [];
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite expression skip-scan range current query terms must be arrays');
                }
                $left = $term['left'] ?? null;
                if (is_array($left) && strcasecmp((string) ($left['expression'] ?? ''), $rangeExpression) === 0) {
                    continue;
                }
                $rewritten[] = $term;
            }
            if ($lower !== null) {
                $rewritten[] = ['operator' => '>=', 'left' => ['expression' => $rangeExpression], 'right' => $lower];
            }
            if ($upper !== null) {
                $rewritten[] = ['operator' => (bool) ($source['upperInclusive'] ?? true) ? '<=' : '<', 'left' => ['expression' => $rangeExpression], 'right' => $upper];
            }

            return $rewritten;
        }

        /** @param array<string,mixed> $source */
        private static function rangeSignature(array $source): string
        {
            return hash('sha256', serialize([
                'rangeExpression' => self::stringValue($source, 'rangeExpression'),
                'rangeExpressionColumn' => self::stringValue($source, 'rangeExpressionColumn'),
                'lowerInclusive' => $source['lowerInclusive'] ?? null,
                'upperBound' => $source['upperBound'] ?? null,
                'upperInclusive' => (bool) ($source['upperInclusive'] ?? true),
                'collation' => strtoupper((string) ($source['collation'] ?? 'BINARY')),
            ]));
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return array<string,mixed> */
        private static function rangeFence(array $source, array $plan, string $signature): array
        {
            return [
                'source' => self::stringValue($source, 'name'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'rangeSignature' => $signature,
                'rangeExpression' => self::stringValue($source, 'rangeExpression'),
                'rangeExpressionColumn' => self::stringValue($source, 'rangeExpressionColumn'),
                'lowerInclusive' => $source['lowerInclusive'] ?? null,
                'upperBound' => $source['upperBound'] ?? null,
                'upperInclusive' => (bool) ($source['upperInclusive'] ?? true),
                'collation' => strtoupper((string) ($source['collation'] ?? 'BINARY')),
                'seekOpcode' => 'SeekScan',
                'lowerOpcode' => 'SeekGE',
                'upperOpcode' => ((bool) ($plan['upperInclusive'] ?? true)) ? 'IdxGT' : 'IdxGE',
                'loopCount' => count(self::arrayList($plan['loops'] ?? [])),
                'rowCount' => count(self::intList($plan['rowids'] ?? [])),
            ];
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return array<string,mixed> */
        private static function cursorTape(array $source, array $plan, string $signature): array
        {
            return [
                'source' => 'current',
                'program' => [
                    [
                        'opcode' => 'ReprepareIfRangeFenceStale',
                        'source' => self::stringValue($source, 'name'),
                        'rangeSignature' => $signature,
                    ],
                    [
                        'opcode' => 'SeekScan',
                        'index' => (string) ($plan['indexName'] ?? ''),
                        'skippedColumn' => (string) ($plan['skippedColumn'] ?? ''),
                        'rangeExpression' => (string) ($plan['rangeExpression'] ?? ''),
                        'loopCount' => count(self::arrayList($plan['loops'] ?? [])),
                    ],
                    [
                        'opcode' => 'SeekGE',
                        'column' => (string) ($plan['rangeExpressionColumn'] ?? $plan['rangeColumn'] ?? ''),
                        'value' => $plan['lowerInclusive'] ?? null,
                    ],
                    [
                        'opcode' => ((bool) ($plan['upperInclusive'] ?? true)) ? 'IdxGT' : 'IdxGE',
                        'column' => (string) ($plan['rangeExpressionColumn'] ?? $plan['rangeColumn'] ?? ''),
                        'value' => $plan['upperBound'] ?? null,
                    ],
                    [
                        'opcode' => 'Column',
                        'source' => 'index',
                        'columns' => self::stringList($plan['neededColumns'] ?? []),
                    ],
                    [
                        'opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next',
                        'target' => 'index',
                    ],
                ],
                'rowids' => self::intList($plan['rowids'] ?? []),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
            ];
        }

        /** @param array<string,mixed> $plan @return array<string,array<string,mixed>> */
        private static function loopsByPrefix(array $plan): array
        {
            $map = [];
            foreach (self::arrayList($plan['loops'] ?? []) as $loop) {
                $map[self::key($loop['prefix'] ?? null)] = $loop;
            }

            return $map;
        }

        /** @param array<string,array<string,mixed>> $prepared @param array<string,array<string,mixed>> $current @return list<array<string,mixed>> */
        private static function loopDelta(array $prepared, array $current): array
        {
            $keys = array_values(array_unique(array_merge(array_keys($prepared), array_keys($current))));
            usort($keys, static fn (string $left, string $right): int => (string) (($current[$left] ?? $prepared[$left] ?? [])['prefix'] ?? '') <=> (string) (($current[$right] ?? $prepared[$right] ?? [])['prefix'] ?? ''));
            $delta = [];
            foreach ($keys as $key) {
                $before = $prepared[$key] ?? [];
                $after = $current[$key] ?? [];
                $beforeRowids = self::intList($before['rowids'] ?? []);
                $afterRowids = self::intList($after['rowids'] ?? []);
                $delta[] = [
                    'prefix' => $after['prefix'] ?? $before['prefix'] ?? null,
                    'preparedMatched' => (int) ($before['matched'] ?? 0),
                    'currentMatched' => (int) ($after['matched'] ?? 0),
                    'matchedDelta' => (int) ($after['matched'] ?? 0) - (int) ($before['matched'] ?? 0),
                    'rejectedRowids' => array_values(array_diff($beforeRowids, $afterRowids)),
                    'admittedRowids' => array_values(array_diff($afterRowids, $beforeRowids)),
                ];
            }

            return $delta;
        }

        /** @return array<string,mixed> */
        private static function arrayValue(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite expression skip-scan range current metadata must be arrays');
            }

            return $value;
        }

        /** @return list<array<string,mixed>> */
        private static function arrayList(mixed $value): array
        {
            return is_array($value) && array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [];
        }

        /** @return list<int> */
        private static function intList(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): int => (int) $item, $value)) : [];
        }

        /** @return list<string> */
        private static function stringList(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): string => (string) $item, $value)) : [];
        }

        /** @param array<string,mixed> $source */
        private static function stringValue(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite expression skip-scan range current needs {$key}");
            }

            return $value;
        }

        /** @param array<string,mixed> $source */
        private static function nonNegativeInt(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite expression skip-scan range current needs non-negative {$key}");
            }

            return $value;
        }

        private static function key(mixed $value): string
        {
            return get_debug_type($value) . ':' . serialize($value);
        }

}
