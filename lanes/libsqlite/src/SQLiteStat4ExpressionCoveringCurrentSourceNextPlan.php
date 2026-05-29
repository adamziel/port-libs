<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4ExpressionCoveringCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteStat4ExpressionCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materializeExpressionCoveringCurrentSource(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions
        ): array {
            $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
            $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns, $neededExpressions);

            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedSignature = self::sourceSignature($preparedSource);
            $currentSignature = self::sourceSignature($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedSignature !== $currentSignature;
            $selectedPlan = $stale ? $currentPlan : $preparedPlan;
            $selectedSource = $stale ? $currentSource : $preparedSource;
            $ready = $selectedPlan !== null
                && ($selectedPlan['covering'] ?? false) === true
                && ($selectedPlan['orderBySatisfied'] ?? false) === true
                && ($selectedPlan['coveredRowCount'] ?? 0) > 0;

            return [
                'status' => $ready ? 'stat4-expression-covering-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature),
                'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature),
                'selectedPlan' => $selectedPlan,
                'cursorTape' => self::cursorTape($selectedPlan, $selectedSource, $orderBy, $neededColumns, $neededExpressions, $stale ? 'current' : 'prepared'),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'orderSignature' => self::orderSignature($orderBy),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' STAT4 EXPRESSION COVERING CURRENT SOURCE '
                    . self::stringValue($selectedSource, 'name')
                    . ' ' . ($ready ? 'COVERING INDEX ROW STREAM' : 'NO COVERING STAT4 STREAM'),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan',
                    'sqlite-stat4-expression-covering-current-source',
                ],
                'dependency_closure' => 'no new support component needed; native STAT4 expression-covering current-source planning composes native expression-index parsing, STAT4 samples, and current-source covering cursor diagnostics',
                'non_overlap' => 'avoids accepted expression-index range-cost ranking and accepted STAT4 row filtering by adding prepared/current source-fence selection plus cursor tape materialization for STAT4 expression covering scans',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return null|array<string,mixed>
         */
        private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns, array $neededExpressions): ?array
        {
            return SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
                self::listValue($source, 'indexes'),
                $predicate,
                self::listValue($source, 'rows'),
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );
        }

        /**
         * @param null|array<string,mixed> $plan
         * @param array<string,mixed> $source
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        private static function cursorTape(?array $plan, array $source, array $orderBy, array $neededColumns, array $neededExpressions, string $sourceName): array
        {
            if ($plan === null) {
                return [
                    'source' => $sourceName,
                    'status' => 'no-plan',
                    'program' => [['opcode' => 'OpenRead', 'target' => 'table', 'reason' => 'no covering stat4 expression index']],
                    'sorterOpen' => $orderBy !== [],
                    'deferredSeekOpcode' => 'DeferredSeek',
                ];
            }

            $rows = $plan['currentNextRows'] ?? [];
            $program = [[
                'opcode' => 'OpenRead',
                'target' => 'index',
                'rootPage' => $plan['rootPage'] ?? null,
                'source' => $sourceName,
            ]];
            foreach ($neededExpressions as $expression) {
                $program[] = [
                    'opcode' => 'ExpressionColumn',
                    'source' => 'index',
                    'expression' => self::expressionSignature($expression),
                ];
            }
            foreach ($neededColumns as $column) {
                $program[] = [
                    'opcode' => 'Column',
                    'source' => 'index',
                    'column' => $column,
                ];
            }
            $program[] = ['opcode' => 'Next', 'target' => 'index'];

            return [
                'source' => $sourceName,
                'status' => 'covering-stat4-current-source',
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'orderSignature' => self::orderSignature($orderBy),
                'expressionKeys' => array_map(static fn (array $pair): mixed => $pair['current']['key'] ?? null, $rows),
                'rowids' => array_map(static fn (array $pair): mixed => $pair['current']['rowid'] ?? null, $rows),
                'coveringColumns' => $neededColumns,
                'coveringExpressions' => array_map([self::class, 'expressionSignature'], $neededExpressions),
                'coveredRowCount' => $plan['coveredRowCount'] ?? 0,
                'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
                'stat4Estimate' => $plan['stat4Estimate'] ?? null,
                'tableLookupElided' => true,
                'deferredSeekOpcode' => null,
                'sorterOpen' => false,
                'program' => $program,
            ];
        }

        /**
         * @param null|array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummary(array $source, ?array $plan, string $signature): array
        {
            return [
                'name' => self::stringValue($source, 'name'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'coveredRowCount' => $plan['coveredRowCount'] ?? 0,
                'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
                'ready' => $plan !== null && ($plan['covering'] ?? false) === true,
            ];
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function sourceSignature(array $source): string
        {
            $indexes = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression covering source indexes must be arrays');
                }
                $indexes[] = [
                    'name' => $index['name'] ?? null,
                    'rootPage' => $index['rootPage'] ?? null,
                    'sql' => $index['sql'] ?? null,
                    'stat4Samples' => $index['stat4Samples'] ?? [],
                    'coveringColumns' => $index['coveringColumns'] ?? [],
                ];
            }

            return hash('sha256', serialize($indexes));
        }

        /**
         * @param list<array<string,string>> $orderBy
         */
        private static function orderSignature(array $orderBy): string
        {
            if ($orderBy === []) {
                return 'rowid ASC';
            }

            return implode(', ', array_map(static function (array $term): string {
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                if (!in_array($direction, ['ASC', 'DESC'], true)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression covering order direction must be ASC or DESC');
                }

                return self::expressionSignature($term) . ' ' . $direction;
            }, $orderBy));
        }

        /**
         * @param array<string,string> $expression
         */
        private static function expressionSignature(array $expression): string
        {
            if (isset($expression['function'], $expression['column'])) {
                $function = strtolower($expression['function']);
                $column = $expression['column'];
                $path = isset($expression['path']) ? ', ' . $expression['path'] : '';

                return $function . '(' . $column . $path . ')';
            }
            if (isset($expression['column'])) {
                return $expression['column'];
            }

            throw new \InvalidArgumentException('SQLite STAT4 expression covering order term needs a column or function');
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function listValue(array $source, string $key): array
        {
            $value = $source[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression covering source needs list key ' . $key);
            }

            return $value;
        }

        private static function nonNegativeInt(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite STAT4 expression covering source needs non-negative integer ' . $key);
            }

            return $value;
        }

        private static function stringValue(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression covering source needs string ' . $key);
            }

            return $value;
        }

}
