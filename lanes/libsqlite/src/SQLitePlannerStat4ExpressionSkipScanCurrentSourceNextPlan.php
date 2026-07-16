<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array<string,mixed>> $queryTerms
         * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeCurrentSource(
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
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );

            $selectedPlan = self::arrayValue($currentView, 'selectedPlan');
            $preparedPlan = self::arrayValue($preparedView, 'selectedPlan');
            $preparedRowids = self::intList($preparedPlan['rowids'] ?? []);
            $currentRowids = self::intList($selectedPlan['rowids'] ?? []);
            $stat4Changed = (bool) ($currentView['stat4GenerationChanged'] ?? false);
            $stale = (bool) ($currentView['stalePreparedStatement'] ?? false);
            $skipScanReady = ($currentView['status'] ?? null) === 'usable'
                && ($selectedPlan['expressionSkipScan'] ?? false) === true
                && ($selectedPlan['stat4SamplesUsed'] ?? 0) > 0
                && ($selectedPlan['usesSkipScan'] ?? false) === true
                && $stale
                && $stat4Changed;

            return array_replace($currentView, [
                'status' => $skipScanReady ? 'stat4-expression-skipscan-current-source-next137-ready' : 'requires-next-stage',
                'preparedStat4Signature' => self::stat4Signature($preparedSource),
                'currentStat4Signature' => self::stat4Signature($currentSource),
                'stat4SignatureChanged' => self::stat4Signature($preparedSource) !== self::stat4Signature($currentSource),
                'preparedSkipScanRowids' => $preparedRowids,
                'currentSkipScanRowids' => $currentRowids,
                'staleSkipScanRejectedRowids' => array_values(array_diff($preparedRowids, $currentRowids)),
                'currentSkipScanAdmittedRowids' => array_values(array_diff($currentRowids, $preparedRowids)),
                'stableSkipScanRowids' => array_values(array_intersect($currentRowids, $preparedRowids)),
                'stat4PrefixDelta' => self::prefixDelta(
                    self::stat4ByPrefix($preparedPlan['stat4CurrentNextByPrefix'] ?? []),
                    self::stat4ByPrefix($selectedPlan['stat4CurrentNextByPrefix'] ?? []),
                ),
                'skipScanCostDelta' => (int) ($selectedPlan['estimatedCost'] ?? 0) - (int) ($preparedPlan['estimatedCost'] ?? 0),
                'skipScanRowEstimateDelta' => (int) ($selectedPlan['estimatedRows'] ?? 0) - (int) ($preparedPlan['estimatedRows'] ?? 0),
                'currentSourceFence' => array_replace(
                    self::arrayValue($currentView, 'currentSourceFence'),
                    [
                        'stat4Signature' => self::stat4Signature($currentSource),
                        'skipScanOpcode' => 'SeekScan',
                        'rangeRecheckOpcode' => self::rangeRecheckOpcode($selectedPlan),
                        'skipScanLoopCount' => count(self::arrayList($selectedPlan['loops'] ?? [])),
                    ],
                ),
                'cursorTape' => self::cursorTape($selectedPlan, $preparedPlan, $currentSource),
                'dependencies' => [
                    'SQLiteSkipScanStat4PartialOrderPlan::partialExpressionSkipScan',
                    'SQLiteIndexSkipScanPlan STAT4 per-prefix current/next samples',
                    'sqlite-sqlplanner-stat4-expression-skipscan-current-source-next137',
                ],
                'dependency_closure' => 'no new support component needed; next137 reuses native PHP expression skip-scan, STAT4 per-prefix samples, and current-source fences',
                'non_overlap' => 'does not repeat partial expression skip-scan current-source, covering skip-scan current-source/127/132, STAT4 expression covering range next128, range-cost ranking, or SQL expression ORDER BY; this slice adds STAT4 stale-source selection and current/next deltas for expression skip-scan loops',
            ]);
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $preparedPlan
         * @param array<string,mixed> $currentSource
         * @return array<string,mixed>
         */
        private static function cursorTape(array $plan, array $preparedPlan, array $currentSource): array
        {
            $loops = self::arrayList($plan['loops'] ?? []);
            $program = [
                [
                    'opcode' => 'ReprepareIfStale',
                    'source' => self::stringValue($currentSource, 'name'),
                    'schemaCookie' => self::nonNegativeInt($currentSource, 'schemaCookie'),
                    'stat4Generation' => self::nonNegativeInt($currentSource, 'stat4Generation'),
                ],
                [
                    'opcode' => 'SeekScan',
                    'index' => (string) ($plan['indexName'] ?? ''),
                    'skippedColumn' => (string) ($plan['skippedColumn'] ?? ''),
                    'rangeExpression' => (string) ($plan['rangeExpression'] ?? ''),
                    'loopCount' => count($loops),
                ],
                [
                    'opcode' => self::rangeRecheckOpcode($plan),
                    'column' => (string) ($plan['rangeExpressionColumn'] ?? $plan['rangeColumn'] ?? ''),
                    'lower' => $plan['lowerInclusive'] ?? null,
                    'upper' => $plan['upperBound'] ?? null,
                    'upperInclusive' => (bool) ($plan['upperInclusive'] ?? true),
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
            ];

            return [
                'source' => 'current',
                'program' => $program,
                'preparedLoops' => self::loopSummary(self::arrayList($preparedPlan['loops'] ?? [])),
                'currentLoops' => self::loopSummary($loops),
                'stat4CurrentNextByPrefix' => self::arrayList($plan['stat4CurrentNextByPrefix'] ?? []),
                'rowids' => self::intList($plan['rowids'] ?? []),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
                'blockSortRequired' => (bool) ($plan['blockSortRequired'] ?? false),
            ];
        }

        /**
         * @param list<array<string,mixed>> $loops
         * @return list<array{prefix:mixed,matched:int,rowids:list<int>}>
         */
        private static function loopSummary(array $loops): array
        {
            $summary = [];
            foreach ($loops as $loop) {
                $summary[] = [
                    'prefix' => $loop['prefix'] ?? null,
                    'matched' => (int) ($loop['matched'] ?? 0),
                    'rowids' => self::intList($loop['rowids'] ?? []),
                ];
            }

            return $summary;
        }

        /**
         * @param array<string,array<string,mixed>> $prepared
         * @param array<string,array<string,mixed>> $current
         * @return list<array<string,mixed>>
         */
        private static function prefixDelta(array $prepared, array $current): array
        {
            $prefixes = array_values(array_unique(array_merge(array_keys($prepared), array_keys($current))));
            usort(
                $prefixes,
                static fn (string $left, string $right): int => (string) (($current[$left] ?? $prepared[$left] ?? [])['prefix'] ?? '')
                    <=> (string) (($current[$right] ?? $prepared[$right] ?? [])['prefix'] ?? ''),
            );
            $delta = [];
            foreach ($prefixes as $key) {
                $preparedEntry = $prepared[$key] ?? null;
                $currentEntry = $current[$key] ?? null;
                $delta[] = [
                    'prefix' => $currentEntry['prefix'] ?? $preparedEntry['prefix'] ?? null,
                    'preparedRangeSamples' => (int) ($preparedEntry['rangeSamples'] ?? 0),
                    'currentRangeSamples' => (int) ($currentEntry['rangeSamples'] ?? 0),
                    'rangeSamplesDelta' => (int) ($currentEntry['rangeSamples'] ?? 0) - (int) ($preparedEntry['rangeSamples'] ?? 0),
                    'preparedCurrent' => $preparedEntry['current'] ?? null,
                    'currentCurrent' => $currentEntry['current'] ?? null,
                    'preparedNext' => $preparedEntry['next'] ?? null,
                    'currentNext' => $currentEntry['next'] ?? null,
                ];
            }

            return $delta;
        }

        /**
         * @param mixed $entries
         * @return array<string,array<string,mixed>>
         */
        private static function stat4ByPrefix(mixed $entries): array
        {
            $map = [];
            foreach (self::arrayList($entries) as $entry) {
                $map[self::prefixMapKey($entry['prefix'] ?? null)] = $entry;
            }

            return $map;
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function stat4Signature(array $source): string
        {
            return hash('sha256', serialize($source['stat4Samples'] ?? []));
        }

        /**
         * @param array<string,mixed> $plan
         */
        private static function rangeRecheckOpcode(array $plan): string
        {
            return ($plan['upperInclusive'] ?? true) === true ? 'IdxGT' : 'IdxGE';
        }

        /**
         * @return array<string,mixed>
         */
        private static function arrayValue(array $data, string $key): array
        {
            $value = $data[$key] ?? [];
            if (!is_array($value)) {
                return [];
            }

            return $value;
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function arrayList(mixed $value): array
        {
            if (!is_array($value) || !array_is_list($value)) {
                return [];
            }
            foreach ($value as $entry) {
                if (!is_array($entry)) {
                    return [];
                }
            }

            return $value;
        }

        /**
         * @return list<int>
         */
        private static function intList(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_map(static fn (mixed $item): int => (int) $item, $value));
        }

        /**
         * @return list<string>
         */
        private static function stringList(mixed $value): array
        {
            if (!is_array($value)) {
                return [];
            }

            return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValue(array $data, string $key): string
        {
            $value = $data[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 expression skip-scan next137 needs {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function nonNegativeInt(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 expression skip-scan next137 needs non-negative {$key}");
            }

            return $value;
        }

        private static function prefixMapKey(mixed $value): string
        {
            return get_debug_type($value) . ':' . serialize($value);
        }

}
