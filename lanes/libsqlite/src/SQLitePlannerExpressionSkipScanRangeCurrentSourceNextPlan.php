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
        public static function materializeNext143(
            array $preparedSource,
            array $currentSource,
            SQLiteIndexPredicate $partialPredicate,
            array $queryTerms,
            array $orderByExpressions,
            array $neededColumns,
        ): array {
            $preparedView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScanCurrentSourceNext129(
                $preparedSource,
                $preparedSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );
            $currentView = SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScanCurrentSourceNext129(
                $preparedSource,
                $currentSource,
                $partialPredicate,
                self::rangeTermsForSourceNext143($queryTerms, $currentSource),
                $orderByExpressions,
                $neededColumns,
            );

            $preparedPlan = self::arrayValueNext143($preparedView, 'selectedPlan');
            $currentPlan = self::arrayValueNext143($currentView, 'selectedPlan');
            $preparedRowids = self::intListNext143($preparedPlan['rowids'] ?? []);
            $currentRowids = self::intListNext143($currentPlan['rowids'] ?? []);
            $preparedRangeSignature = self::rangeSignatureNext143($preparedSource);
            $currentRangeSignature = self::rangeSignatureNext143($currentSource);
            $rangeChanged = $preparedRangeSignature !== $currentRangeSignature;
            $ready = ($currentView['status'] ?? null) === 'usable'
                && ($currentPlan['expressionSkipScan'] ?? false) === true
                && ($currentPlan['usesSkipScan'] ?? false) === true
                && $rangeChanged;

            return array_replace($currentView, [
                'status' => $ready ? 'expression-skipscan-range-current-source-next143-ready' : 'requires-next-stage',
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
                'rangeLoopDelta' => self::loopDeltaNext143(self::loopsByPrefixNext143($preparedPlan), self::loopsByPrefixNext143($currentPlan)),
                'rangeFence' => self::rangeFenceNext143($currentSource, $currentPlan, $currentRangeSignature),
                'cursorTape' => self::cursorTapeNext143($currentSource, $currentPlan, $currentRangeSignature),
                'detail' => ($currentView['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' current-range-fence=' . ($rangeChanged ? 'changed' : 'stable'),
                'dependencies' => [
                    'SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScanCurrentSourceNext129',
                    'sqlite-sqlplanner-expression-skipscan-range-current-source-next143',
                ],
                'dependency_closure' => 'no new support component needed; next143 reuses native PHP expression skip-scan materialization, current-source fences, and bounded range cursor evidence',
                'non_overlap' => 'avoids partial expression skip-scan next129, expression covering next132, STAT4 stale-source next137, partial predicate changes next139, covering partial range next131, and expression-index range-cost ranking; this slice only fences stale expression skip-scan lower/upper/collation range bounds on the current source',
            ]);
        }

        /** @param list<array<string,mixed>> $terms @return list<array<string,mixed>> */
        private static function rangeTermsForSourceNext143(array $terms, array $source): array
        {
            $rangeExpression = self::stringValueNext143($source, 'rangeExpression');
            $lower = $source['lowerInclusive'] ?? null;
            $upper = $source['upperBound'] ?? null;
            $rewritten = [];
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite expression skip-scan range next143 query terms must be arrays');
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
        private static function rangeSignatureNext143(array $source): string
        {
            return hash('sha256', serialize([
                'rangeExpression' => self::stringValueNext143($source, 'rangeExpression'),
                'rangeExpressionColumn' => self::stringValueNext143($source, 'rangeExpressionColumn'),
                'lowerInclusive' => $source['lowerInclusive'] ?? null,
                'upperBound' => $source['upperBound'] ?? null,
                'upperInclusive' => (bool) ($source['upperInclusive'] ?? true),
                'collation' => strtoupper((string) ($source['collation'] ?? 'BINARY')),
            ]));
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return array<string,mixed> */
        private static function rangeFenceNext143(array $source, array $plan, string $signature): array
        {
            return [
                'source' => self::stringValueNext143($source, 'name'),
                'schemaCookie' => self::nonNegativeIntNext143($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext143($source, 'stat4Generation'),
                'rangeSignature' => $signature,
                'rangeExpression' => self::stringValueNext143($source, 'rangeExpression'),
                'rangeExpressionColumn' => self::stringValueNext143($source, 'rangeExpressionColumn'),
                'lowerInclusive' => $source['lowerInclusive'] ?? null,
                'upperBound' => $source['upperBound'] ?? null,
                'upperInclusive' => (bool) ($source['upperInclusive'] ?? true),
                'collation' => strtoupper((string) ($source['collation'] ?? 'BINARY')),
                'seekOpcode' => 'SeekScan',
                'lowerOpcode' => 'SeekGE',
                'upperOpcode' => ((bool) ($plan['upperInclusive'] ?? true)) ? 'IdxGT' : 'IdxGE',
                'loopCount' => count(self::arrayListNext143($plan['loops'] ?? [])),
                'rowCount' => count(self::intListNext143($plan['rowids'] ?? [])),
            ];
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return array<string,mixed> */
        private static function cursorTapeNext143(array $source, array $plan, string $signature): array
        {
            return [
                'source' => 'current',
                'program' => [
                    [
                        'opcode' => 'ReprepareIfRangeFenceStale',
                        'source' => self::stringValueNext143($source, 'name'),
                        'rangeSignature' => $signature,
                    ],
                    [
                        'opcode' => 'SeekScan',
                        'index' => (string) ($plan['indexName'] ?? ''),
                        'skippedColumn' => (string) ($plan['skippedColumn'] ?? ''),
                        'rangeExpression' => (string) ($plan['rangeExpression'] ?? ''),
                        'loopCount' => count(self::arrayListNext143($plan['loops'] ?? [])),
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
                        'columns' => self::stringListNext143($plan['neededColumns'] ?? []),
                    ],
                    [
                        'opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next',
                        'target' => 'index',
                    ],
                ],
                'rowids' => self::intListNext143($plan['rowids'] ?? []),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
            ];
        }

        /** @param array<string,mixed> $plan @return array<string,array<string,mixed>> */
        private static function loopsByPrefixNext143(array $plan): array
        {
            $map = [];
            foreach (self::arrayListNext143($plan['loops'] ?? []) as $loop) {
                $map[self::keyNext143($loop['prefix'] ?? null)] = $loop;
            }

            return $map;
        }

        /** @param array<string,array<string,mixed>> $prepared @param array<string,array<string,mixed>> $current @return list<array<string,mixed>> */
        private static function loopDeltaNext143(array $prepared, array $current): array
        {
            $keys = array_values(array_unique(array_merge(array_keys($prepared), array_keys($current))));
            usort($keys, static fn (string $left, string $right): int => (string) (($current[$left] ?? $prepared[$left] ?? [])['prefix'] ?? '') <=> (string) (($current[$right] ?? $prepared[$right] ?? [])['prefix'] ?? ''));
            $delta = [];
            foreach ($keys as $key) {
                $before = $prepared[$key] ?? [];
                $after = $current[$key] ?? [];
                $beforeRowids = self::intListNext143($before['rowids'] ?? []);
                $afterRowids = self::intListNext143($after['rowids'] ?? []);
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
        private static function arrayValueNext143(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite expression skip-scan range next143 metadata must be arrays');
            }

            return $value;
        }

        /** @return list<array<string,mixed>> */
        private static function arrayListNext143(mixed $value): array
        {
            return is_array($value) && array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [];
        }

        /** @return list<int> */
        private static function intListNext143(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): int => (int) $item, $value)) : [];
        }

        /** @return list<string> */
        private static function stringListNext143(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): string => (string) $item, $value)) : [];
        }

        /** @param array<string,mixed> $source */
        private static function stringValueNext143(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite expression skip-scan range next143 needs {$key}");
            }

            return $value;
        }

        /** @param array<string,mixed> $source */
        private static function nonNegativeIntNext143(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite expression skip-scan range next143 needs non-negative {$key}");
            }

            return $value;
        }

        private static function keyNext143(mixed $value): string
        {
            return get_debug_type($value) . ':' . serialize($value);
        }

}
