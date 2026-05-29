<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeSubqueryPartialIndexPlan(array $preparedSource, array $currentSource, array $predicate, array $neededColumns): array
        {
            $normalizedPredicate = self::normalizeSubqueryPredicate($predicate);
            $prepared = self::sourcePlan($preparedSource, $normalizedPredicate, $neededColumns);
            $current = self::sourcePlan($currentSource, $normalizedPredicate, $neededColumns);

            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedGeneration = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentGeneration = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedSignature = self::indexSignature($preparedSource);
            $currentSignature = self::indexSignature($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedGeneration !== $currentGeneration
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $current : $prepared;
            $usable = ($selected['usable'] ?? false) === true
                && ($selected['partialPredicateImplied'] ?? false) === true
                && ($selected['subqueryNullBlocked'] ?? true) === false;

            return [
                'status' => $usable ? 'subquery-partial-index-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedGeneration !== $currentGeneration,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::sourceSummary($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'subquery' => [
                    'source' => $normalizedPredicate['subquery']['sourceName'],
                    'column' => $normalizedPredicate['subquery']['column'],
                    'rowCount' => count($normalizedPredicate['subquery']['rows']),
                    'values' => $normalizedPredicate['values'],
                    'nullSeen' => $normalizedPredicate['subquery']['nullSeen'],
                    'duplicatesRemoved' => $normalizedPredicate['subquery']['duplicatesRemoved'],
                    'correlatedOuterColumns' => $normalizedPredicate['subquery']['correlatedOuterColumns'],
                ],
                'cursorTape' => self::cursorTape($selected, $normalizedPredicate['values'], $stale ? 'current' : 'prepared', $usable, $neededColumns),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentGeneration,
                    'indexSignature' => $currentSignature,
                    'subquerySignature' => self::subquerySignature($normalizedPredicate),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' SUBQUERY PARTIAL INDEX USING '
                    . ($stale ? self::stringValue($currentSource, 'name') : self::stringValue($preparedSource, 'name'))
                    . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan',
                    'SQLiteCreateIndex partial predicate parsing',
                    'sqlite-subquery-partial-index-current-source',
                ],
                'dependency_closure' => 'no new support component needed; canonical planner composes native expression-index planning with bounded IN-subquery result materialization',
                'non_overlap' => 'avoids accepted scalar subquery execution, expression ORDER BY, STAT4 range-cost, and covering ORDER slices by proving partial-index eligibility from current-source IN-subquery values',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlan(array $source, array $predicate, array $neededColumns): array
        {
            $indexes = self::listValue($source, 'indexes');
            $plans = SQLiteSelectExpressionIndexPlan::rankedPlans($indexes, $predicate, [], $neededColumns);
            $plan = $plans[0] ?? null;
            if ($plan === null) {
                return [
                    'usable' => false,
                    'partialPredicateImplied' => false,
                    'subqueryNullBlocked' => (bool) ($predicate['subquery']['nullSeen'] ?? false),
                    'detail' => 'SCAN TABLE; NO USABLE SUBQUERY PARTIAL INDEX',
                ];
            }

            $subqueryValues = $predicate['values'];
            $plan['subqueryValues'] = $subqueryValues;
            $plan['subqueryValueCount'] = count($subqueryValues);
            $plan['subqueryNullBlocked'] = (bool) ($predicate['subquery']['nullSeen'] ?? false);
            $plan['partialPredicateImplied'] = ($plan['partial'] ?? false) === true && $plan['subqueryNullBlocked'] === false;
            $plan['coveringColumnsRequested'] = $neededColumns;
            $plan['deferredTableLookup'] = ($plan['covering'] ?? false) !== true;
            $plan['nextSource'] = ($plan['covering'] ?? false) === true ? 'covering-index' : 'table-rowid-lookup';
            $plan['detail'] = 'SEARCH ' . (string) ($plan['name'] ?? 'partial-index')
                . ' USING CURRENT IN-SUBQUERY VALUES'
                . (($plan['partialPredicateImplied'] ?? false) === true ? ' PARTIAL-PREDICATE IMPLIED' : ' PARTIAL-PREDICATE BLOCKED')
                . (($plan['covering'] ?? false) === true ? ' COVERING' : ' DEFER TABLE LOOKUP');

            return $plan;
        }

        /**
         * @param array<string,mixed> $predicate
         * @return array<string,mixed>
         */
        private static function normalizeSubqueryPredicate(array $predicate): array
        {
            $operator = strtoupper(self::stringValue($predicate, 'operator'));
            if ($operator !== 'IN_SUBQUERY') {
                throw new \InvalidArgumentException('SQLite subquery partial-index planner needs IN_SUBQUERY predicate');
            }

            $left = $predicate['left'] ?? null;
            if (!is_array($left)) {
                throw new \InvalidArgumentException('SQLite subquery partial-index planner needs expression left operand');
            }

            $subquery = $predicate['subquery'] ?? null;
            if (!is_array($subquery)) {
                throw new \InvalidArgumentException('SQLite subquery partial-index planner needs subquery metadata');
            }

            $rows = self::listValue($subquery, 'rows');
            $column = self::stringValue($subquery, 'column', 'value');
            $seen = [];
            $values = [];
            $nullSeen = false;
            $duplicates = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite subquery partial-index rows must be arrays');
                }
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException('SQLite subquery partial-index row is missing projected column');
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
            }

            if ($values === []) {
                throw new \InvalidArgumentException('SQLite subquery partial-index planner needs at least one non-NULL subquery value');
            }

            return [
                'operator' => 'IN',
                'left' => $left,
                'values' => $values,
                'subquery' => [
                    'sourceName' => self::stringValue($subquery, 'sourceName', 'subquery'),
                    'column' => $column,
                    'rows' => $rows,
                    'nullSeen' => $nullSeen,
                    'duplicatesRemoved' => $duplicates,
                    'correlatedOuterColumns' => self::stringList($subquery, 'correlatedOuterColumns'),
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<mixed> $values
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function cursorTape(array $plan, array $values, string $source, bool $usable, array $neededColumns): array
        {
            $program = [
                ['opcode' => 'OpenRead', 'source' => 'index', 'name' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => 'OpenEphemeral', 'source' => 'subquery-values', 'rows' => count($values)],
                ['opcode' => 'Rewind', 'source' => 'subquery-values'],
                ['opcode' => 'SeekGE', 'source' => 'index', 'keyFrom' => 'subquery-values'],
                ['opcode' => 'IdxGT', 'source' => 'index', 'keyFrom' => 'subquery-values'],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => ($plan['covering'] ?? false) === true ? 'index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => 'Next', 'source' => 'subquery-values'];

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'seekOpcode' => 'SeekGE',
                'stopOpcode' => 'IdxGT',
                'nextOpcode' => 'Next',
                'subqueryValues' => $values,
                'subqueryValueCount' => count($values),
                'dedupeSubqueryValues' => true,
                'nullFilteredBeforeIndexSeek' => true,
                'partialPredicateImplied' => ($plan['partialPredicateImplied'] ?? false) === true,
                'tableLookupElided' => $usable && ($plan['covering'] ?? false) === true,
                'deferredSeekOpcode' => $usable && ($plan['covering'] ?? false) === true ? null : 'DeferredSeek',
                'program' => $program,
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummary(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValue($source, 'name'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => ($plan['usable'] ?? false) === true,
                'nameSelected' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'partialPredicateImplied' => ($plan['partialPredicateImplied'] ?? false) === true,
                'covering' => ($plan['covering'] ?? false) === true,
                'estimatedRows' => $plan['estimatedRows'] ?? null,
                'estimatedCost' => $plan['estimatedCost'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignature(array $source): string
        {
            $parts = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite subquery partial-index source indexes must be arrays');
                }
                $parts[] = implode("\n", [
                    self::stringValue($index, 'name', ''),
                    (string) self::nonNegativeInt($index, 'rootPage'),
                    self::stringValue($index, 'sql'),
                    json_encode($index['stat4Samples'] ?? [], JSON_THROW_ON_ERROR),
                ]);
            }

            return hash('sha256', implode("\n---\n", $parts));
        }

        /**
         * @param array<string,mixed> $predicate
         */
        private static function subquerySignature(array $predicate): string
        {
            return hash('sha256', json_encode($predicate['subquery'], JSON_THROW_ON_ERROR) . "\n" . json_encode($predicate['values'], JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $array
         */
        private static function stringValue(array $array, string $key, ?string $default = null): string
        {
            $value = $array[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite subquery partial-index planner needs string {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $array
         */
        private static function nonNegativeInt(array $array, string $key): int
        {
            $value = $array[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite subquery partial-index planner needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $array
         * @return list<mixed>
         */
        private static function listValue(array $array, string $key): array
        {
            $value = $array[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite subquery partial-index planner needs list {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $array
         * @return list<string>
         */
        private static function stringList(array $array, string $key): array
        {
            $value = $array[$key] ?? [];
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite subquery partial-index planner needs string list {$key}");
            }
            foreach ($value as $item) {
                if (!is_string($item) || $item === '') {
                    throw new \InvalidArgumentException("SQLite subquery partial-index planner needs string list {$key}");
                }
            }

            return $value;
        }

}
