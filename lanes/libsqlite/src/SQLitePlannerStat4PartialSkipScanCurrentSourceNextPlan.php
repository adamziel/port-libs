<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array<string,mixed>> $queryTerms
         * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
         * @param list<string> $neededColumns
         * @param array<string,mixed>|null $nextSource
         * @return array<string,mixed>
         */
        public static function materialize(
            array $preparedSource,
            array $currentSource,
            SQLiteIndexPredicate $partialPredicate,
            array $queryTerms,
            array $orderByExpressions,
            array $neededColumns,
            ?array $nextSource = null,
        ): array {
            $base = SQLiteSkipScanStat4PartialOrderPlan::expressionPartialSkipScan(
                $preparedSource,
                $currentSource,
                $partialPredicate,
                $queryTerms,
                $orderByExpressions,
                $neededColumns,
                $nextSource,
            );

            $selectedPlan = self::arrayValue($base, 'selectedPlan');
            $selectedSource = ($base['selectedSource'] ?? null) === 'current' ? $currentSource : $preparedSource;
            $status = ($base['status'] ?? null) === 'expression-partial-skipscan-current-source-ready'
                ? 'stat4-partial-skipscan-current-source-ready'
                : 'requires-current-source-reprepare';
            $loopProgram = $selectedPlan === null ? [] : self::loopProgram($selectedPlan, $selectedSource);
            $payloadRows = $selectedPlan === null ? [] : self::payloadRows($selectedPlan, $neededColumns);
            $stat4Pairs = $selectedPlan === null ? [] : self::stat4Pairs($selectedPlan);

            return array_replace($base, [
                'status' => $status,
                'selectedPlan' => $selectedPlan === null ? null : array_replace($selectedPlan, [
                    'currentSourcePrefixProgram' => $loopProgram,
                    'currentSourcePayloadRows' => $payloadRows,
                    'stat4CurrentSourceNextPairs' => $stat4Pairs,
                    'skipScanLoopCount' => count($loopProgram),
                    'payloadRowCount' => count($payloadRows),
                    'stat4PairCount' => count($stat4Pairs),
                    'detail' => ($selectedPlan['detail'] ?? 'SEARCH USING SKIP-SCAN')
                        . ' CURRENT-SOURCE STAT4 PREFIX PROGRAM current-source',
                ]),
                'prefixProgram' => $loopProgram,
                'payloadRows' => $payloadRows,
                'stat4CurrentSourceNextPairs' => $stat4Pairs,
                'currentSourceFence' => array_replace(
                    self::arrayValue($base, 'currentSourceFence') ?? [],
                    [
                        'loopProgramSignature' => self::signature($loopProgram),
                        'payloadSignature' => self::signature($payloadRows),
                        'stat4PairSignature' => self::signature($stat4Pairs),
                        'partialPredicateFence' => $base['partialPredicateFence'] ?? null,
                    ],
                ),
                'detail' => ($base['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                    . ' STAT4 CURRENT-SOURCE PREFIX PROGRAM current-source',
                'dependencies' => ['sqlite-sqlplanner-stat4-partial-skipscan-current-source'],
                'dependency_closure' => 'no new support component needed; current-source reuses native partial-index proof, STAT4 skip-scan estimates, expression-key materialization, and current-source reprepare fences',
                'non_overlap' => 'avoids accepted expression ORDER BY, range-cost, JSON, VFS, WAL, B-tree, and current-source source-fence-only surfaces by adding per-prefix STAT4 skip-scan cursor programs and covering payload evidence for the current source',
            ]);
        }

        /**
         * @return array<string,mixed>|null
         */
        private static function arrayValue(array $source, string $key): ?array
        {
            $value = $source[$key] ?? null;
            if ($value === null) {
                return null;
            }
            if (!is_array($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected {$key} to be an array");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $source
         * @return list<array<string,mixed>>
         */
        private static function loopProgram(array $plan, array $source): array
        {
            $loops = self::listValue($plan, 'loops');
            $rangeColumn = self::stringValue($source, 'rangeExpressionColumn');
            $upperInclusive = self::boolValue($source, 'upperInclusive', true);
            $program = [];
            foreach ($loops as $loop) {
                $prefix = $loop['prefix'] ?? null;
                $program[] = [
                    'prefix' => $prefix,
                    'opcodes' => [
                        ['opcode' => 'SeekPrefix', 'column' => self::stringValue($source, 'skippedColumn'), 'value' => $prefix],
                        ['opcode' => 'SeekGE', 'column' => $rangeColumn, 'value' => $source['lowerInclusive'] ?? null],
                        ['opcode' => $upperInclusive ? 'IdxGT' : 'IdxGE', 'column' => $rangeColumn, 'value' => $source['upperBound'] ?? null],
                        ['opcode' => 'Column', 'columns' => self::stringList($source, 'coveringColumns')],
                        ['opcode' => 'NextPrefix', 'column' => self::stringValue($source, 'skippedColumn')],
                    ],
                    'matched' => (int) ($loop['matched'] ?? 0),
                    'rowids' => self::intList($loop['rowids'] ?? []),
                ];
            }

            return $program;
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function payloadRows(array $plan, array $neededColumns): array
        {
            $pairs = self::listValue($plan, 'currentNextCoveringRows');
            $rows = [];
            foreach ($pairs as $pair) {
                $current = self::arrayFrom($pair['current'] ?? null, 'current covering row');
                $covering = self::arrayFrom($current['covering'] ?? null, 'current covering payload');
                $payload = [];
                foreach ($neededColumns as $column) {
                    if (!is_string($column) || $column === '') {
                        throw new \InvalidArgumentException('SQLite STAT4 partial skip-scan current-source needed columns must be strings');
                    }
                    $payload[$column] = $covering[$column] ?? null;
                }
                $rows[] = [
                    'rowid' => (int) ($current['rowid'] ?? 0),
                    'sourceOffset' => $current['sourceOffset'] ?? null,
                    'payload' => $payload,
                    'nextRowid' => is_array($pair['next'] ?? null) ? (int) ($pair['next']['rowid'] ?? 0) : null,
                ];
            }

            return $rows;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function stat4Pairs(array $plan): array
        {
            $pairs = [];
            foreach (self::listValue($plan, 'stat4CurrentNextByPrefix') as $pair) {
                $pairs[] = [
                    'prefix' => $pair['prefix'] ?? null,
                    'currentSuffix' => is_array($pair['current'] ?? null) ? ($pair['current']['suffix'] ?? null) : null,
                    'nextSuffix' => is_array($pair['next'] ?? null) ? ($pair['next']['suffix'] ?? null) : null,
                    'rangeSamples' => (int) ($pair['rangeSamples'] ?? 0),
                ];
            }

            return $pairs;
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function listValue(array $source, string $key): array
        {
            $value = $source[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected list {$key}");
            }
            foreach ($value as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected array items in {$key}");
                }
            }

            return $value;
        }

        /**
         * @return array<string,mixed>
         */
        private static function arrayFrom(mixed $value, string $context): array
        {
            if (!is_array($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected {$context}");
            }

            return $value;
        }

        /**
         * @return list<int>
         */
        private static function intList(mixed $value): array
        {
            if (!is_array($value) || !array_is_list($value)) {
                return [];
            }

            return array_map(static fn (mixed $item): int => (int) $item, $value);
        }

        private static function stringValue(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected string {$key}");
            }

            return $value;
        }

        /**
         * @return list<string>
         */
        private static function stringList(array $source, string $key): array
        {
            $value = $source[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected list {$key}");
            }
            foreach ($value as $item) {
                if (!is_string($item) || $item === '') {
                    throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected string values in {$key}");
                }
            }

            return array_values($value);
        }

        private static function boolValue(array $source, string $key, bool $default): bool
        {
            $value = $source[$key] ?? $default;
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 partial skip-scan current-source expected boolean {$key}");
            }

            return $value;
        }

        private static function signature(array $payload): string
        {
            return hash('sha256', serialize($payload));
        }

}
