<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan. */

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
            $preparedView = SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materialize(
                $preparedSource,
                $preparedSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );
            $currentView = SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materialize(
                $preparedSource,
                $currentSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );

            $preparedPlan = self::arrayValue($preparedView, 'selectedPlan');
            $currentPlan = self::arrayValue($currentView, 'selectedPlan');
            $preparedSamples = self::stat4Samples($preparedSource);
            $currentSamples = self::stat4Samples($currentSource);
            $preparedSignature = self::stat4Signature($preparedSamples);
            $currentSignature = self::stat4Signature($currentSamples);
            $stat4Changed = $preparedSignature !== $currentSignature;
            $coveringChanged = self::coveringSignature($preparedSource) !== self::coveringSignature($currentSource);
            $ready = ($currentView['status'] ?? null) === 'expression-skipscan-range-current-source-ready'
                && ($currentPlan['covering'] ?? false) === true
                && ($currentPlan['usesSkipScan'] ?? false) === true
                && $stat4Changed;

            $preparedByPrefix = self::samplesByPrefix($preparedSamples);
            $currentByPrefix = self::samplesByPrefix($currentSamples);

            return array_replace($currentView, [
                'status' => $ready ? 'stat4-covering-skipscan-current-source-ready' : 'requires-next-stage',
                'preparedStat4Signature' => $preparedSignature,
                'currentStat4Signature' => $currentSignature,
                'stat4SignatureChanged' => $stat4Changed,
                'stat4SampleCountChanged' => count($preparedSamples) !== count($currentSamples),
                'stat4PrefixOrderChanged' => self::prefixOrder($preparedSamples) !== self::prefixOrder($currentSamples),
                'coveringSignatureChanged' => $coveringChanged,
                'preparedStat4Samples' => self::sampleSummaries($preparedSamples),
                'currentStat4Samples' => self::sampleSummaries($currentSamples),
                'stat4SampleDelta' => self::sampleDelta($preparedSamples, $currentSamples),
                'stat4PrefixDelta' => self::prefixDelta($preparedByPrefix, $currentByPrefix),
                'stat4Fence' => self::stat4Fence($currentSource, $currentPlan, $currentSignature, $currentSamples),
                'coveringCursorTape' => self::coveringCursorTape($currentSource, $currentPlan, $currentSignature),
                'coveringPayloadPreview' => self::coveringPayloadPreview($currentSource, $currentPlan),
                'detail' => ($currentView['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' stat4-covering-fence=' . ($stat4Changed ? 'changed' : 'stable'),
                'dependencies' => [
                    'SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materialize',
                    'sqlite-sqlplanner-stat4-covering-skipscan-current-source',
                ],
                'dependency_closure' => 'no new support component needed; current-source reuses native PHP STAT4 samples, covering skip-scan current-source fences, and cursor tape evidence',
                'non_overlap' => 'avoids accepted expression skip-scan range next143, partial expression skip-scan next129, partial covering skip-scan next125/127, STAT4 expression covering/range clusters, and range-cost ranking; this slice only fences stale STAT4 covering skip-scan sample payload/order on the current source',
            ]);
        }

        /** @return array<string,mixed> */
        private static function arrayValue(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan current-source metadata must be arrays');
            }

            return $value;
        }

        /** @param array<string,mixed> $source @return list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> */
        private static function stat4Samples(array $source): array
        {
            $samples = $source['stat4Samples'] ?? [];
            if (!is_array($samples) || !array_is_list($samples)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan current-source stat4Samples must be a list');
            }
            $normalized = [];
            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan current-source sample rows must be arrays');
                }
                $normalized[] = [
                    'prefix' => $sample['prefix'] ?? null,
                    'suffix' => $sample['suffix'] ?? null,
                    'nEq' => self::nonNegativeSampleInt($sample, 'nEq'),
                    'nLt' => self::nonNegativeSampleInt($sample, 'nLt'),
                    'nDLt' => self::nonNegativeSampleInt($sample, 'nDLt'),
                ];
            }

            return $normalized;
        }

        /** @param array<string,mixed> $sample */
        private static function nonNegativeSampleInt(array $sample, string $key): int
        {
            $value = $sample[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 covering skip-scan current-source {$key} must be a non-negative integer");
            }

            return $value;
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples */
        private static function stat4Signature(array $samples): string
        {
            return hash('sha256', serialize(self::sampleSummaries($samples)));
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return list<array<string,mixed>> */
        private static function sampleSummaries(array $samples): array
        {
            return array_map(static fn (array $sample): array => [
                'prefix' => $sample['prefix'],
                'suffix' => $sample['suffix'],
                'nEq' => $sample['nEq'],
                'nLt' => $sample['nLt'],
                'nDLt' => $sample['nDLt'],
                'key' => self::sampleKey($sample),
            ], $samples);
        }

        /** @param array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int} $sample */
        private static function sampleKey(array $sample): string
        {
            return self::valueKey($sample['prefix']) . "\0" . self::valueKey($sample['suffix']);
        }

        private static function valueKey(mixed $value): string
        {
            return is_scalar($value) || $value === null ? serialize($value) : serialize((string) json_encode($value));
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return list<mixed> */
        private static function prefixOrder(array $samples): array
        {
            $seen = [];
            $order = [];
            foreach ($samples as $sample) {
                $key = self::valueKey($sample['prefix']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $order[] = $sample['prefix'];
            }

            return $order;
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>> */
        private static function samplesByPrefix(array $samples): array
        {
            $map = [];
            foreach ($samples as $sample) {
                $key = self::valueKey($sample['prefix']);
                $map[$key] ??= [];
                $map[$key][] = $sample;
            }

            return $map;
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $prepared @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $current @return array<string,list<array<string,mixed>>> */
        private static function sampleDelta(array $prepared, array $current): array
        {
            $preparedMap = [];
            foreach ($prepared as $sample) {
                $preparedMap[self::sampleKey($sample)] = $sample;
            }
            $currentMap = [];
            foreach ($current as $sample) {
                $currentMap[self::sampleKey($sample)] = $sample;
            }

            $added = [];
            $removed = [];
            $changed = [];
            foreach ($currentMap as $key => $sample) {
                if (!isset($preparedMap[$key])) {
                    $added[] = self::sampleSummaries([$sample])[0];
                    continue;
                }
                if ($preparedMap[$key] !== $sample) {
                    $changed[] = [
                        'prepared' => self::sampleSummaries([$preparedMap[$key]])[0],
                        'current' => self::sampleSummaries([$sample])[0],
                    ];
                }
            }
            foreach ($preparedMap as $key => $sample) {
                if (!isset($currentMap[$key])) {
                    $removed[] = self::sampleSummaries([$sample])[0];
                }
            }

            return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
        }

        /** @param array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>> $prepared @param array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>> $current @return list<array<string,mixed>> */
        private static function prefixDelta(array $prepared, array $current): array
        {
            $keys = array_values(array_unique(array_merge(array_keys($prepared), array_keys($current))));
            usort($keys, static fn (string $left, string $right): int => (string) (($current[$left][0] ?? $prepared[$left][0] ?? [])['prefix'] ?? '') <=> (string) (($current[$right][0] ?? $prepared[$right][0] ?? [])['prefix'] ?? ''));
            $delta = [];
            foreach ($keys as $key) {
                $before = $prepared[$key] ?? [];
                $after = $current[$key] ?? [];
                $delta[] = [
                    'prefix' => ($after[0] ?? $before[0] ?? [])['prefix'] ?? null,
                    'preparedSamples' => count($before),
                    'currentSamples' => count($after),
                    'sampleDelta' => count($after) - count($before),
                    'preparedNEq' => array_sum(array_column($before, 'nEq')),
                    'currentNEq' => array_sum(array_column($after, 'nEq')),
                    'suffixes' => array_values(array_unique(array_merge(array_column($before, 'suffix'), array_column($after, 'suffix')))),
                ];
            }

            return $delta;
        }

        /** @param array<string,mixed> $source */
        private static function coveringSignature(array $source): string
        {
            $columns = $source['coveringColumns'] ?? [];
            if (!is_array($columns) || !array_is_list($columns)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan current-source coveringColumns must be a list');
            }

            return hash('sha256', serialize(array_values(array_map('strval', $columns))));
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return array<string,mixed> */
        private static function stat4Fence(array $source, array $plan, string $signature, array $samples): array
        {
            return [
                'source' => self::sourceName($source),
                'schemaCookie' => self::sourceNonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::sourceNonNegativeInt($source, 'stat4Generation'),
                'stat4Signature' => $signature,
                'sampleCount' => count($samples),
                'prefixOrder' => self::prefixOrder($samples),
                'coveringSignature' => self::coveringSignature($source),
                'rowCount' => count(self::intList($plan['rowids'] ?? [])),
                'coveringRowCount' => (int) ($plan['coveredRowCount'] ?? 0),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
            ];
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return array<string,mixed> */
        private static function coveringCursorTape(array $source, array $plan, string $signature): array
        {
            return [
                'source' => 'current',
                'program' => [
                    ['opcode' => 'ReprepareIfStat4FenceStale', 'source' => self::sourceName($source), 'stat4Signature' => $signature],
                    ['opcode' => 'SeekScan', 'index' => (string) ($plan['indexName'] ?? ''), 'skippedColumn' => (string) ($plan['skippedColumn'] ?? '')],
                    ['opcode' => 'Stat4SampleGate', 'samples' => (int) (($plan['stat4SamplesUsed'] ?? 0))],
                    ['opcode' => 'Column', 'source' => 'covering-index', 'columns' => self::stringList($plan['neededColumns'] ?? [])],
                    ['opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next', 'target' => 'covering-index'],
                ],
                'rowids' => self::intList($plan['rowids'] ?? []),
                'covering' => ($plan['covering'] ?? false) === true,
                'tableSeekRequired' => ($plan['tableSeekRequired'] ?? true) === true,
            ];
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return list<array<string,mixed>> */
        private static function coveringPayloadPreview(array $source, array $plan): array
        {
            $sourceRows = $source['rows'] ?? [];
            $byRowid = [];
            if (is_array($sourceRows) && array_is_list($sourceRows)) {
                foreach ($sourceRows as $row) {
                    if (is_array($row) && isset($row['rowid'])) {
                        $byRowid[(int) $row['rowid']] = $row;
                    }
                }
            }
            $rowids = self::intList($plan['rowids'] ?? []);
            $columns = self::stringList($plan['neededColumns'] ?? []);
            $preview = [];
            foreach ($rowids as $rowid) {
                if (!isset($byRowid[$rowid])) {
                    continue;
                }
                $row = $byRowid[$rowid];
                $covering = [];
                foreach ($columns as $column) {
                    if (array_key_exists($column, $row)) {
                        $covering[$column] = $row[$column];
                    }
                }
                $preview[] = [
                    'rowid' => $rowid,
                    'prefix' => $row[(string) ($plan['skippedColumn'] ?? '')] ?? null,
                    'key' => $row[(string) ($plan['rangeColumn'] ?? '')] ?? null,
                    'covering' => $covering,
                ];
            }

            return $preview;
        }

        /** @param array<string,mixed> $source */
        private static function sourceName(array $source): string
        {
            $name = $source['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan current-source needs source name');
            }

            return $name;
        }

        /** @param array<string,mixed> $source */
        private static function sourceNonNegativeInt(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 covering skip-scan current-source needs non-negative {$key}");
            }

            return $value;
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

}
