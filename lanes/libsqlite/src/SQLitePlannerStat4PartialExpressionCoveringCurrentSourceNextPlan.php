<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materializeNext118(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            self::validateNeededColumnsNext118($neededColumns);
            $preparedPlan = self::sourcePlanNext118($preparedSource, $predicate, $currentRows, $orderBy, $neededColumns, $neededExpressions);
            $currentPlan = self::sourcePlanNext118($currentSource, $predicate, $currentRows, $orderBy, $neededColumns, $neededExpressions);

            $preparedCookie = self::nonNegativeIntNext118($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext118($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeIntNext118($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeIntNext118($currentSource, 'stat4Generation');
            $preparedSignature = self::signatureNext118($preparedSource);
            $currentSignature = self::signatureNext118($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $currentPlan : $preparedPlan;
            $ready = ($selected['usable'] ?? false) === true
                && ($selected['partial'] ?? false) === true
                && ($selected['covering'] ?? false) === true
                && ($selected['stat4Used'] ?? false) === true
                && ($selected['coveredRowCount'] ?? 0) > 0;

            return [
                'status' => $ready ? 'stat4-partial-expression-covering-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::summaryNext118($preparedSource, $preparedPlan, $preparedSignature),
                'currentSource' => self::summaryNext118($currentSource, $currentPlan, $currentSignature),
                'selectedPlan' => $selected,
                'coveringPayloadColumns' => array_values($neededColumns),
                'coveringExpressionCount' => count($neededExpressions),
                'deferredTableSeek' => $ready,
                'tempBtreeForCoveringPayload' => false,
                'residualPredicateRequired' => (bool) ($selected['residualPredicateRequired'] ?? true),
                'cursorTape' => [
                    'source' => $stale ? 'current' : 'prepared',
                    'indexName' => $selected['name'] ?? null,
                    'rootPage' => $selected['rootPage'] ?? null,
                    'operator' => $selected['operator'] ?? null,
                    'values' => $selected['values'] ?? null,
                    'stat4MatchedCurrentNext' => $selected['stat4MatchedCurrentNext'] ?? [],
                    'currentNextRows' => $selected['currentNextRows'] ?? [],
                    'opcodes' => self::opcodesNext118($ready, $selected, $neededColumns),
                ],
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'coveringSignature' => implode(',', array_map('strtolower', $neededColumns)),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' STAT4 PARTIAL EXPRESSION COVERING INDEX '
                    . (string) ($selected['name'] ?? 'NO INDEX'),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan STAT4 covering row stream',
                    'SQLiteCreateIndex partial expression parser',
                    'sqlite-stat4-partial-expression-covering-current-source-next118',
                ],
                'dependency_closure' => 'no new support component needed; next118 reuses native STAT4 expression-index, partial-predicate proof, and covering payload diagnostics',
                'non_overlap' => 'avoids accepted expression ORDER BY, range-cost, subquery partial-covering, and next114 partial-collation STAT4 surfaces by adding current-source covering row payload selection for stale partial expression STAT4 plans',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        private static function sourcePlanNext118(array $source, array $predicate, array $currentRows, array $orderBy, array $neededColumns, array $neededExpressions): array
        {
            $plan = SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
                self::indexesNext118($source),
                $predicate,
                $currentRows,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );
            if ($plan === null) {
                return [
                    'usable' => false,
                    'partial' => false,
                    'covering' => false,
                    'stat4Used' => false,
                    'coveredRowCount' => 0,
                    'detail' => 'SCAN TABLE; NO STAT4 PARTIAL EXPRESSION COVERING INDEX',
                ];
            }

            return $plan + [
                'partial' => true,
                'stat4Used' => ((int) ($plan['stat4MatchedSamples'] ?? 0)) > 0,
                'residualPredicateRequired' => true,
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @return list<array<string,mixed>>
         */
        private static function indexesNext118(array $source): array
        {
            $indexes = $source['indexes'] ?? null;
            if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
                throw new \InvalidArgumentException('SQLite STAT4 partial expression covering source needs index definitions');
            }
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite STAT4 partial expression covering indexes must be arrays');
                }
            }

            return $indexes;
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function validateNeededColumnsNext118(array $neededColumns): void
        {
            if ($neededColumns === []) {
                throw new \InvalidArgumentException('SQLite STAT4 partial expression covering plan needs at least one covering column');
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 partial expression covering columns must be names');
                }
            }
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function nonNegativeIntNext118(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite STAT4 partial expression covering source ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function signatureNext118(array $source): string
        {
            $parts = [];
            foreach (self::indexesNext118($source) as $index) {
                $parts[] = implode('|', [
                    (string) ($index['name'] ?? ''),
                    (string) ($index['rootPage'] ?? ''),
                    (string) ($index['sql'] ?? ''),
                    implode(',', array_map('strtolower', is_array($index['coveringColumns'] ?? null) ? $index['coveringColumns'] : [])),
                    hash('sha256', serialize($index['stat4Samples'] ?? [])),
                ]);
            }

            return hash('sha256', implode("\n", $parts));
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function summaryNext118(array $source, array $plan, string $signature): array
        {
            return [
                'name' => (string) ($source['name'] ?? ''),
                'schemaCookie' => self::nonNegativeIntNext118($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext118($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => (bool) ($plan['usable'] ?? false),
                'indexName' => $plan['name'] ?? null,
                'coveredRowCount' => (int) ($plan['coveredRowCount'] ?? 0),
                'stat4MatchedSamples' => (int) ($plan['stat4MatchedSamples'] ?? 0),
            ];
        }

        /**
         * @param array<string,mixed> $selected
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function opcodesNext118(bool $ready, array $selected, array $neededColumns): array
        {
            if (!$ready) {
                return [
                    ['opcode' => 'OpenRead', 'target' => 'table'],
                    ['opcode' => 'Rewind', 'target' => 'table'],
                    ['opcode' => 'DeferredSeek', 'target' => 'table'],
                ];
            }

            $program = [
                ['opcode' => 'OpenRead', 'target' => 'index', 'p2' => $selected['rootPage'] ?? null],
                ['opcode' => 'SeekStat4Matched', 'operator' => $selected['operator'] ?? null, 'values' => $selected['values'] ?? null],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'target' => 'index', 'column' => $column];
            }
            $program[] = ['opcode' => 'ResultRow', 'source' => 'covering-index'];
            $program[] = ['opcode' => 'Next', 'target' => 'index'];

            return $program;
        }

}
