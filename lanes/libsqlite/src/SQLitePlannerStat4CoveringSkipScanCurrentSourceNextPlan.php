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
        public static function materializeNext147(
            array $preparedSource,
            array $currentSource,
            SQLiteIndexPredicate $partialPredicate,
            array $queryTerms,
            array $orderByExpressions,
            array $neededColumns,
        ): array {
            $preparedView = SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materializeNext143(
                $preparedSource,
                $preparedSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );
            $currentView = SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materializeNext143(
                $preparedSource,
                $currentSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
            );

            $preparedPlan = self::arrayValueNext147($preparedView, 'selectedPlan');
            $currentPlan = self::arrayValueNext147($currentView, 'selectedPlan');
            $preparedSamples = self::stat4SamplesNext147($preparedSource);
            $currentSamples = self::stat4SamplesNext147($currentSource);
            $preparedSignature = self::stat4SignatureNext147($preparedSamples);
            $currentSignature = self::stat4SignatureNext147($currentSamples);
            $stat4Changed = $preparedSignature !== $currentSignature;
            $coveringChanged = self::coveringSignatureNext147($preparedSource) !== self::coveringSignatureNext147($currentSource);
            $ready = ($currentView['status'] ?? null) === 'expression-skipscan-range-current-source-next143-ready'
                && ($currentPlan['covering'] ?? false) === true
                && ($currentPlan['usesSkipScan'] ?? false) === true
                && $stat4Changed;

            $preparedByPrefix = self::samplesByPrefixNext147($preparedSamples);
            $currentByPrefix = self::samplesByPrefixNext147($currentSamples);

            return array_replace($currentView, [
                'status' => $ready ? 'stat4-covering-skipscan-current-source-next147-ready' : 'requires-next-stage',
                'preparedStat4Signature' => $preparedSignature,
                'currentStat4Signature' => $currentSignature,
                'stat4SignatureChanged' => $stat4Changed,
                'stat4SampleCountChanged' => count($preparedSamples) !== count($currentSamples),
                'stat4PrefixOrderChanged' => self::prefixOrderNext147($preparedSamples) !== self::prefixOrderNext147($currentSamples),
                'coveringSignatureChanged' => $coveringChanged,
                'preparedStat4Samples' => self::sampleSummariesNext147($preparedSamples),
                'currentStat4Samples' => self::sampleSummariesNext147($currentSamples),
                'stat4SampleDelta' => self::sampleDeltaNext147($preparedSamples, $currentSamples),
                'stat4PrefixDelta' => self::prefixDeltaNext147($preparedByPrefix, $currentByPrefix),
                'stat4Fence' => self::stat4FenceNext147($currentSource, $currentPlan, $currentSignature, $currentSamples),
                'coveringCursorTape' => self::coveringCursorTapeNext147($currentSource, $currentPlan, $currentSignature),
                'coveringPayloadPreview' => self::coveringPayloadPreviewNext147($currentSource, $currentPlan),
                'detail' => ($currentView['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' stat4-covering-fence=' . ($stat4Changed ? 'changed' : 'stable'),
                'dependencies' => [
                    'SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materialize',
                    'sqlite-sqlplanner-stat4-covering-skipscan-current-source-next147',
                ],
                'dependency_closure' => 'no new support component needed; next147 reuses native PHP STAT4 samples, covering skip-scan current-source fences, and cursor tape evidence',
                'non_overlap' => 'avoids accepted expression skip-scan range next143, partial expression skip-scan next129, partial covering skip-scan next125/127, STAT4 expression covering/range clusters, and range-cost ranking; this slice only fences stale STAT4 covering skip-scan sample payload/order on the current source',
            ]);
        }

        /** @return array<string,mixed> */
        private static function arrayValueNext147(array $source, string $key): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan next147 metadata must be arrays');
            }

            return $value;
        }

        /** @param array<string,mixed> $source @return list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> */
        private static function stat4SamplesNext147(array $source): array
        {
            $samples = $source['stat4Samples'] ?? [];
            if (!is_array($samples) || !array_is_list($samples)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan next147 stat4Samples must be a list');
            }
            $normalized = [];
            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan next147 sample rows must be arrays');
                }
                $normalized[] = [
                    'prefix' => $sample['prefix'] ?? null,
                    'suffix' => $sample['suffix'] ?? null,
                    'nEq' => self::nonNegativeSampleIntNext147($sample, 'nEq'),
                    'nLt' => self::nonNegativeSampleIntNext147($sample, 'nLt'),
                    'nDLt' => self::nonNegativeSampleIntNext147($sample, 'nDLt'),
                ];
            }

            return $normalized;
        }

        /** @param array<string,mixed> $sample */
        private static function nonNegativeSampleIntNext147(array $sample, string $key): int
        {
            $value = $sample[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 covering skip-scan next147 {$key} must be a non-negative integer");
            }

            return $value;
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples */
        private static function stat4SignatureNext147(array $samples): string
        {
            return hash('sha256', serialize(self::sampleSummariesNext147($samples)));
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return list<array<string,mixed>> */
        private static function sampleSummariesNext147(array $samples): array
        {
            return array_map(static fn (array $sample): array => [
                'prefix' => $sample['prefix'],
                'suffix' => $sample['suffix'],
                'nEq' => $sample['nEq'],
                'nLt' => $sample['nLt'],
                'nDLt' => $sample['nDLt'],
                'key' => self::sampleKeyNext147($sample),
            ], $samples);
        }

        /** @param array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int} $sample */
        private static function sampleKeyNext147(array $sample): string
        {
            return self::keyNext147($sample['prefix']) . "\0" . self::keyNext147($sample['suffix']);
        }

        private static function keyNext147(mixed $value): string
        {
            return is_scalar($value) || $value === null ? serialize($value) : serialize((string) json_encode($value));
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return list<mixed> */
        private static function prefixOrderNext147(array $samples): array
        {
            $seen = [];
            $order = [];
            foreach ($samples as $sample) {
                $key = self::keyNext147($sample['prefix']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $order[] = $sample['prefix'];
            }

            return $order;
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>> */
        private static function samplesByPrefixNext147(array $samples): array
        {
            $map = [];
            foreach ($samples as $sample) {
                $key = self::keyNext147($sample['prefix']);
                $map[$key] ??= [];
                $map[$key][] = $sample;
            }

            return $map;
        }

        /** @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $prepared @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $current @return array<string,list<array<string,mixed>>> */
        private static function sampleDeltaNext147(array $prepared, array $current): array
        {
            $preparedMap = [];
            foreach ($prepared as $sample) {
                $preparedMap[self::sampleKeyNext147($sample)] = $sample;
            }
            $currentMap = [];
            foreach ($current as $sample) {
                $currentMap[self::sampleKeyNext147($sample)] = $sample;
            }

            $added = [];
            $removed = [];
            $changed = [];
            foreach ($currentMap as $key => $sample) {
                if (!isset($preparedMap[$key])) {
                    $added[] = self::sampleSummariesNext147([$sample])[0];
                    continue;
                }
                if ($preparedMap[$key] !== $sample) {
                    $changed[] = [
                        'prepared' => self::sampleSummariesNext147([$preparedMap[$key]])[0],
                        'current' => self::sampleSummariesNext147([$sample])[0],
                    ];
                }
            }
            foreach ($preparedMap as $key => $sample) {
                if (!isset($currentMap[$key])) {
                    $removed[] = self::sampleSummariesNext147([$sample])[0];
                }
            }

            return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
        }

        /** @param array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>> $prepared @param array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>> $current @return list<array<string,mixed>> */
        private static function prefixDeltaNext147(array $prepared, array $current): array
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
        private static function coveringSignatureNext147(array $source): string
        {
            $columns = $source['coveringColumns'] ?? [];
            if (!is_array($columns) || !array_is_list($columns)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan next147 coveringColumns must be a list');
            }

            return hash('sha256', serialize(array_values(array_map('strval', $columns))));
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples @return array<string,mixed> */
        private static function stat4FenceNext147(array $source, array $plan, string $signature, array $samples): array
        {
            return [
                'source' => self::sourceNameNext147($source),
                'schemaCookie' => self::sourceNonNegativeIntNext147($source, 'schemaCookie'),
                'stat4Generation' => self::sourceNonNegativeIntNext147($source, 'stat4Generation'),
                'stat4Signature' => $signature,
                'sampleCount' => count($samples),
                'prefixOrder' => self::prefixOrderNext147($samples),
                'coveringSignature' => self::coveringSignatureNext147($source),
                'rowCount' => count(self::intListNext147($plan['rowids'] ?? [])),
                'coveringRowCount' => (int) ($plan['coveredRowCount'] ?? 0),
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
            ];
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return array<string,mixed> */
        private static function coveringCursorTapeNext147(array $source, array $plan, string $signature): array
        {
            return [
                'source' => 'current',
                'program' => [
                    ['opcode' => 'ReprepareIfStat4FenceStale', 'source' => self::sourceNameNext147($source), 'stat4Signature' => $signature],
                    ['opcode' => 'SeekScan', 'index' => (string) ($plan['indexName'] ?? ''), 'skippedColumn' => (string) ($plan['skippedColumn'] ?? '')],
                    ['opcode' => 'Stat4SampleGate', 'samples' => (int) (($plan['stat4SamplesUsed'] ?? 0))],
                    ['opcode' => 'Column', 'source' => 'covering-index', 'columns' => self::stringListNext147($plan['neededColumns'] ?? [])],
                    ['opcode' => ($plan['reverseScan'] ?? false) === true ? 'Prev' : 'Next', 'target' => 'covering-index'],
                ],
                'rowids' => self::intListNext147($plan['rowids'] ?? []),
                'covering' => ($plan['covering'] ?? false) === true,
                'tableSeekRequired' => ($plan['tableSeekRequired'] ?? true) === true,
            ];
        }

        /** @param array<string,mixed> $source @param array<string,mixed> $plan @return list<array<string,mixed>> */
        private static function coveringPayloadPreviewNext147(array $source, array $plan): array
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
            $rowids = self::intListNext147($plan['rowids'] ?? []);
            $columns = self::stringListNext147($plan['neededColumns'] ?? []);
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
        private static function sourceNameNext147(array $source): string
        {
            $name = $source['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite STAT4 covering skip-scan next147 needs source name');
            }

            return $name;
        }

        /** @param array<string,mixed> $source */
        private static function sourceNonNegativeIntNext147(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 covering skip-scan next147 needs non-negative {$key}");
            }

            return $value;
        }

        /** @return list<int> */
        private static function intListNext147(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): int => (int) $item, $value)) : [];
        }

        /** @return list<string> */
        private static function stringListNext147(mixed $value): array
        {
            return is_array($value) ? array_values(array_map(static fn (mixed $item): string => (string) $item, $value)) : [];
        }

}
