<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext115(array $preparedSource, array $currentSource, array $predicate, array $neededColumns): array
        {
            $normalizedPredicate = self::normalizeSubqueryPredicateNext115($predicate);
            $prepared = self::sourcePlanNext115($preparedSource, $normalizedPredicate, $neededColumns);
            $current = self::sourcePlanNext115($currentSource, $normalizedPredicate, $neededColumns);

            $preparedCookie = self::nonNegativeIntNext115($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext115($currentSource, 'schemaCookie');
            $preparedGeneration = self::nonNegativeIntNext115($preparedSource, 'stat4Generation');
            $currentGeneration = self::nonNegativeIntNext115($currentSource, 'stat4Generation');
            $preparedSignature = self::indexSignatureNext115($preparedSource);
            $currentSignature = self::indexSignatureNext115($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedGeneration !== $currentGeneration
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $current : $prepared;
            $usable = ($selected['usable'] ?? false) === true
                && ($selected['partialPredicateImplied'] ?? false) === true
                && ($selected['subqueryNullBlocked'] ?? true) === false
                && ($selected['subqueryCovering'] ?? false) === true;

            return [
                'status' => $usable ? 'subquery-covering-partial-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedGeneration !== $currentGeneration,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::sourceSummaryNext115($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::sourceSummaryNext115($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'subquery' => [
                    'source' => $normalizedPredicate['subquery']['sourceName'],
                    'keyColumn' => $normalizedPredicate['subquery']['column'],
                    'projectedColumns' => $normalizedPredicate['subquery']['projectedColumns'],
                    'rowCount' => count($normalizedPredicate['subquery']['rows']),
                    'values' => $normalizedPredicate['values'],
                    'coveringRows' => $normalizedPredicate['coveringRows'],
                    'nullSeen' => $normalizedPredicate['subquery']['nullSeen'],
                    'duplicatesRemoved' => $normalizedPredicate['subquery']['duplicatesRemoved'],
                    'correlatedOuterColumns' => $normalizedPredicate['subquery']['correlatedOuterColumns'],
                ],
                'cursorTape' => self::cursorTapeNext115($selected, $normalizedPredicate, $stale ? 'current' : 'prepared', $usable, $neededColumns),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentGeneration,
                    'indexSignature' => $currentSignature,
                    'subquerySignature' => self::subquerySignatureNext115($normalizedPredicate),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' SUBQUERY COVERING PARTIAL INDEX USING '
                    . ($stale ? self::stringValueNext115($currentSource, 'name') : self::stringValueNext115($preparedSource, 'name'))
                    . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan',
                    'SQLiteCreateIndex partial predicate parsing',
                    'sqlite-subquery-covering-partial-current-source-next115',
                ],
                'dependency_closure' => 'no new support component needed; next115 composes native expression-index planning with bounded IN-subquery covering projection materialization',
                'non_overlap' => 'avoids accepted next106 IN-subquery partial-index cursor work by adding subquery-projection covering payload columns that eliminate deferred table lookup on the current source',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlanNext115(array $source, array $predicate, array $neededColumns): array
        {
            $indexes = self::listValueNext115($source, 'indexes');
            $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes, $predicate, [], [$predicate['indexColumn']]);
            $plan = null;
            foreach ($plans as $candidate) {
                if (($candidate['partial'] ?? false) === true) {
                    $plan = $candidate;
                    break;
                }
            }
            $plan ??= $plans[0] ?? null;
            if ($plan === null) {
                return [
                    'usable' => false,
                    'partialPredicateImplied' => false,
                    'subqueryNullBlocked' => (bool) ($predicate['subquery']['nullSeen'] ?? false),
                    'subqueryCovering' => false,
                    'detail' => 'SCAN TABLE; NO USABLE SUBQUERY COVERING PARTIAL INDEX',
                ];
            }

            $missing = array_values(array_diff($neededColumns, $predicate['subquery']['projectedColumns']));
            $subqueryCovering = $missing === [];
            $plan['subqueryValues'] = $predicate['values'];
            $plan['subqueryValueCount'] = count($predicate['values']);
            $plan['subqueryNullBlocked'] = (bool) ($predicate['subquery']['nullSeen'] ?? false);
            $plan['partialPredicateImplied'] = ($plan['partial'] ?? false) === true && $plan['subqueryNullBlocked'] === false;
            $coveringColumns = self::stringListNext115($plan, 'coveringColumns');
            $indexCoversRequested = array_diff($neededColumns, $coveringColumns) === [];
            $plan['coveringColumnsRequested'] = $neededColumns;
            $plan['subqueryProjectedColumns'] = $predicate['subquery']['projectedColumns'];
            $plan['indexProbeCovering'] = ($plan['covering'] ?? false) === true;
            $plan['covering'] = $indexCoversRequested;
            $plan['subqueryCovering'] = $subqueryCovering;
            $plan['missingSubqueryCoveringColumns'] = $missing;
            $plan['indexProbeColumn'] = $predicate['indexColumn'];
            $plan['deferredTableLookup'] = !$subqueryCovering;
            $plan['nextSource'] = $subqueryCovering ? 'subquery-covering-payload' : 'table-rowid-lookup';
            $plan['detail'] = 'SEARCH ' . (string) ($plan['name'] ?? 'partial-index')
                . ' USING CURRENT IN-SUBQUERY VALUES'
                . (($plan['partialPredicateImplied'] ?? false) === true ? ' PARTIAL-PREDICATE IMPLIED' : ' PARTIAL-PREDICATE BLOCKED')
                . ($subqueryCovering ? ' SUBQUERY-COVERING' : ' DEFER TABLE LOOKUP');

            return $plan;
        }

        /**
         * @param array<string,mixed> $predicate
         * @return array<string,mixed>
         */
        private static function normalizeSubqueryPredicateNext115(array $predicate): array
        {
            $operator = strtoupper(self::stringValueNext115($predicate, 'operator'));
            if ($operator !== 'IN_SUBQUERY') {
                throw new \InvalidArgumentException('SQLite subquery covering partial-index planner needs IN_SUBQUERY predicate');
            }

            $left = $predicate['left'] ?? null;
            if (!is_array($left)) {
                throw new \InvalidArgumentException('SQLite subquery covering partial-index planner needs expression left operand');
            }
            $indexColumn = self::expressionColumnNext115($left);

            $subquery = $predicate['subquery'] ?? null;
            if (!is_array($subquery)) {
                throw new \InvalidArgumentException('SQLite subquery covering partial-index planner needs subquery metadata');
            }

            $rows = self::listValueNext115($subquery, 'rows');
            $column = self::stringValueNext115($subquery, 'column', 'value');
            $projectedColumns = self::stringListNext115($subquery, 'projectedColumns');
            if (!in_array($column, $projectedColumns, true)) {
                throw new \InvalidArgumentException('SQLite subquery covering partial-index projected columns must include the key column');
            }

            $seen = [];
            $values = [];
            $coveringRows = [];
            $nullSeen = false;
            $duplicates = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite subquery covering partial-index rows must be arrays');
                }
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException('SQLite subquery covering partial-index row is missing projected key column');
                }
                foreach ($projectedColumns as $projectedColumn) {
                    if (!array_key_exists($projectedColumn, $row)) {
                        throw new \InvalidArgumentException('SQLite subquery covering partial-index row is missing projected covering column');
                    }
                }

                $value = $row[$column];
                if ($value === null) {
                    $nullSeen = true;
                    continue;
                }
                $key = serialize($value);
                if (isset($seen[$key])) {
                    $duplicates++;
                    continue;
                }

                $seen[$key] = true;
                $values[] = $value;
                $coveringRows[] = array_intersect_key($row, array_flip($projectedColumns));
            }

            if ($values === []) {
                throw new \InvalidArgumentException('SQLite subquery covering partial-index planner needs at least one non-NULL subquery value');
            }

            return [
                'operator' => 'IN',
                'left' => $left,
                'indexColumn' => $indexColumn,
                'values' => $values,
                'coveringRows' => $coveringRows,
                'subquery' => [
                    'sourceName' => self::stringValueNext115($subquery, 'sourceName', 'subquery'),
                    'column' => $column,
                    'projectedColumns' => $projectedColumns,
                    'rows' => $rows,
                    'nullSeen' => $nullSeen,
                    'duplicatesRemoved' => $duplicates,
                    'correlatedOuterColumns' => self::stringListNext115($subquery, 'correlatedOuterColumns'),
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function cursorTapeNext115(array $plan, array $predicate, string $source, bool $usable, array $neededColumns): array
        {
            $program = [
                ['opcode' => 'OpenRead', 'source' => 'index', 'name' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => 'OpenEphemeral', 'source' => 'subquery-covering-rows', 'rows' => count($predicate['coveringRows'])],
                ['opcode' => 'Rewind', 'source' => 'subquery-covering-rows'],
                ['opcode' => 'SeekGE', 'source' => 'index', 'keyFrom' => 'subquery-covering-rows'],
                ['opcode' => 'IdxGT', 'source' => 'index', 'keyFrom' => 'subquery-covering-rows'],
            ];
            foreach ($neededColumns as $column) {
                $program[] = [
                    'opcode' => 'Column',
                    'source' => $usable ? 'subquery-covering-row' : 'table',
                    'column' => $column,
                ];
            }
            $program[] = ['opcode' => 'Next', 'source' => 'subquery-covering-rows'];

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'seekOpcode' => 'SeekGE',
                'stopOpcode' => 'IdxGT',
                'nextOpcode' => 'Next',
                'subqueryValues' => $predicate['values'],
                'subqueryValueCount' => count($predicate['values']),
                'coveringRows' => $predicate['coveringRows'],
                'dedupeSubqueryValues' => true,
                'nullFilteredBeforeIndexSeek' => true,
                'partialPredicateImplied' => ($plan['partialPredicateImplied'] ?? false) === true,
                'subqueryCovering' => ($plan['subqueryCovering'] ?? false) === true,
                'tableLookupElided' => $usable,
                'deferredSeekOpcode' => $usable ? null : 'DeferredSeek',
                'program' => $program,
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummaryNext115(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValueNext115($source, 'name'),
                'schemaCookie' => self::nonNegativeIntNext115($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext115($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => ($plan['usable'] ?? false) === true,
                'nameSelected' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'partialPredicateImplied' => ($plan['partialPredicateImplied'] ?? false) === true,
                'subqueryCovering' => ($plan['subqueryCovering'] ?? false) === true,
                'missingSubqueryCoveringColumns' => $plan['missingSubqueryCoveringColumns'] ?? [],
                'estimatedRows' => $plan['estimatedRows'] ?? null,
                'estimatedCost' => $plan['estimatedCost'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignatureNext115(array $source): string
        {
            $parts = [];
            foreach (self::listValueNext115($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite subquery covering partial-index source indexes must be arrays');
                }
                $parts[] = implode("\n", [
                    self::stringValueNext115($index, 'name', ''),
                    (string) self::nonNegativeIntNext115($index, 'rootPage'),
                    self::stringValueNext115($index, 'sql'),
                    json_encode($index['stat4Samples'] ?? [], JSON_THROW_ON_ERROR),
                ]);
            }

            return hash('sha256', implode("\n---\n", $parts));
        }

        /**
         * @param array<string,mixed> $predicate
         */
        private static function subquerySignatureNext115(array $predicate): string
        {
            return hash('sha256', json_encode($predicate['subquery'], JSON_THROW_ON_ERROR) . "\n" . json_encode($predicate['coveringRows'], JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $expression
         */
        private static function expressionColumnNext115(array $expression): string
        {
            $column = $expression['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite subquery covering partial-index expression needs a column');
            }

            return $column;
        }

        /**
         * @param array<string,mixed> $array
         */
        private static function stringValueNext115(array $array, string $key, ?string $default = null): string
        {
            $value = $array[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite subquery covering partial-index planner needs string {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $array
         */
        private static function nonNegativeIntNext115(array $array, string $key): int
        {
            $value = $array[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite subquery covering partial-index planner needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $array
         * @return list<mixed>
         */
        private static function listValueNext115(array $array, string $key): array
        {
            $value = $array[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite subquery covering partial-index planner needs list {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $array
         * @return list<string>
         */
        private static function stringListNext115(array $array, string $key): array
        {
            $value = $array[$key] ?? [];
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite subquery covering partial-index planner needs string list {$key}");
            }
            foreach ($value as $item) {
                if (!is_string($item) || $item === '') {
                    throw new \InvalidArgumentException("SQLite subquery covering partial-index planner needs string list {$key}");
                }
            }

            return $value;
        }

}
