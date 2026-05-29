<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext123(array $preparedSource, array $currentSource, array $predicate, array $neededColumns = []): array
        {
            $normalized = self::normalizePredicateNext123($predicate);
            $prepared = self::sourcePlanNext123($preparedSource, $normalized, $neededColumns);
            $current = self::sourcePlanNext123($currentSource, $normalized, $neededColumns);

            $preparedCookie = self::nonNegativeIntNext123($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext123($currentSource, 'schemaCookie');
            $preparedGeneration = self::nonNegativeIntNext123($preparedSource, 'stat4Generation');
            $currentGeneration = self::nonNegativeIntNext123($currentSource, 'stat4Generation');
            $preparedSignature = self::sourceSignatureNext123($preparedSource);
            $currentSignature = self::sourceSignatureNext123($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedGeneration !== $currentGeneration
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $current : $prepared;
            $usable = ($selected['usable'] ?? false) === true
                && ($selected['expressionMatched'] ?? false) === true
                && ($selected['subqueryNullBlocked'] ?? true) === false
                && ($selected['collationMatched'] ?? false) === true
                && ($selected['affinityMatched'] ?? false) === true;

            return [
                'status' => $usable ? 'subquery-expression-index-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedGeneration !== $currentGeneration,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::sourceSummaryNext123($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::sourceSummaryNext123($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'subquery' => [
                    'source' => $normalized['subquery']['sourceName'],
                    'keyColumn' => $normalized['subquery']['keyColumn'],
                    'rowCount' => count($normalized['subquery']['rows']),
                    'values' => $normalized['values'],
                    'typedValues' => $normalized['typedValues'],
                    'nullSeen' => $normalized['subquery']['nullSeen'],
                    'duplicatesRemoved' => $normalized['subquery']['duplicatesRemoved'],
                    'collation' => $normalized['subquery']['collation'],
                    'affinity' => $normalized['subquery']['affinity'],
                    'correlatedOuterColumns' => $normalized['subquery']['correlatedOuterColumns'],
                ],
                'cursorTape' => self::cursorTapeNext123($selected, $normalized, $stale ? 'current' : 'prepared', $usable, $neededColumns),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentGeneration,
                    'indexSignature' => $currentSignature,
                    'subquerySignature' => self::subquerySignatureNext123($normalized),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' SUBQUERY EXPRESSION INDEX USING '
                    . ($stale ? self::stringValueNext123($currentSource, 'name') : self::stringValueNext123($preparedSource, 'name'))
                    . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependencies' => [
                    'SQLiteCreateIndex expression metadata',
                    'SQLiteSelectExpressionIndexPlan expression matching',
                    'sqlite-subquery-expression-index-current-source-next123',
                ],
                'dependency_closure' => 'no new support component needed; next123 composes native expression-index metadata with bounded IN-subquery key materialization',
                'non_overlap' => 'avoids accepted next115 subquery-covering partial indexes and accepted range-cost/expression ORDER BY work by routing subquery-produced expression keys through the current-source expression index fence',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlanNext123(array $source, array $predicate, array $neededColumns): array
        {
            $indexes = self::listValueNext123($source, 'indexes');
            $candidates = [];
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite subquery expression-index planner indexes must be arrays');
                }
                $expression = self::indexExpressionNext123($index);
                if ($expression === null) {
                    continue;
                }
                $expressionMatched = self::expressionSignatureNext123($expression) === self::expressionSignatureNext123($predicate['left']);
                if (!$expressionMatched) {
                    continue;
                }

                $collationMatched = strcasecmp(self::stringValueNext123($expression, 'collation', 'BINARY'), $predicate['subquery']['collation']) === 0;
                $affinityMatched = strtoupper(self::stringValueNext123($expression, 'affinity', 'TEXT')) === $predicate['subquery']['affinity'];
                $partialImplied = self::partialPredicateImpliedNext123($index, $predicate);
                if (!$partialImplied) {
                    continue;
                }

                $coveringColumns = self::stringListNext123($index, 'coveringColumns');
                $covering = array_diff($neededColumns, $coveringColumns) === [];
                $estimatedRows = max(1, min(self::nonNegativeIntNext123($index, 'estimatedRows', count($predicate['values']) * 4), count($predicate['values'])));
                $cost = $estimatedRows + ($covering ? 0 : 10) + ($collationMatched ? 0 : 100) + ($affinityMatched ? 0 : 100);
                $candidates[] = [
                    'usable' => true,
                    'name' => self::stringValueNext123($index, 'name', 'expression-index'),
                    'rootPage' => self::nullableIntNext123($index, 'rootPage'),
                    'expressionMatched' => true,
                    'expression' => $expression,
                    'operator' => 'IN',
                    'subqueryValues' => $predicate['values'],
                    'subqueryValueCount' => count($predicate['values']),
                    'typedValues' => $predicate['typedValues'],
                    'subqueryNullBlocked' => (bool) ($predicate['subquery']['nullSeen'] ?? false),
                    'collationMatched' => $collationMatched,
                    'affinityMatched' => $affinityMatched,
                    'partialPredicateImplied' => true,
                    'covering' => $covering,
                    'coveringColumns' => $coveringColumns,
                    'missingCoveringColumns' => array_values(array_diff($neededColumns, $coveringColumns)),
                    'estimatedRows' => $estimatedRows,
                    'estimatedCost' => $cost,
                    'rangeFence' => self::rangeFenceNext123($predicate['typedValues'], (bool) ($expression['descending'] ?? false)),
                    'nextSource' => $covering ? 'expression-index-covering' : 'table-rowid-lookup',
                    'deferredTableLookup' => !$covering,
                    'detail' => 'SEARCH ' . self::stringValueNext123($index, 'name', 'expression-index')
                        . ' USING CURRENT IN-SUBQUERY EXPRESSION KEYS'
                        . ($covering ? ' COVERING' : ' DEFER TABLE LOOKUP'),
                ];
            }

            usort($candidates, static fn (array $left, array $right): int => [
                $left['estimatedCost'],
                (string) $left['name'],
            ] <=> [
                $right['estimatedCost'],
                (string) $right['name'],
            ]);

            return $candidates[0] ?? [
                'usable' => false,
                'expressionMatched' => false,
                'subqueryNullBlocked' => (bool) ($predicate['subquery']['nullSeen'] ?? false),
                'collationMatched' => false,
                'affinityMatched' => false,
                'partialPredicateImplied' => false,
                'detail' => 'SCAN TABLE; NO USABLE SUBQUERY EXPRESSION INDEX',
            ];
        }

        /**
         * @param array<string,mixed> $predicate
         * @return array<string,mixed>
         */
        private static function normalizePredicateNext123(array $predicate): array
        {
            if (strtoupper(self::stringValueNext123($predicate, 'operator')) !== 'IN_SUBQUERY') {
                throw new \InvalidArgumentException('SQLite subquery expression-index planner needs IN_SUBQUERY predicate');
            }
            $left = $predicate['left'] ?? null;
            if (!is_array($left)) {
                throw new \InvalidArgumentException('SQLite subquery expression-index planner needs expression left operand');
            }
            $subquery = $predicate['subquery'] ?? null;
            if (!is_array($subquery)) {
                throw new \InvalidArgumentException('SQLite subquery expression-index planner needs subquery metadata');
            }

            $keyColumn = self::stringValueNext123($subquery, 'keyColumn', 'key');
            $collation = strtoupper(self::stringValueNext123($subquery, 'collation', 'BINARY'));
            $affinity = strtoupper(self::stringValueNext123($subquery, 'affinity', 'TEXT'));
            $rows = self::listValueNext123($subquery, 'rows');
            $seen = [];
            $values = [];
            $typedValues = [];
            $nullSeen = false;
            $duplicates = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite subquery expression-index rows must be arrays');
                }
                if (!array_key_exists($keyColumn, $row)) {
                    throw new \InvalidArgumentException('SQLite subquery expression-index row is missing key column');
                }
                $value = $row[$keyColumn];
                if ($value === null) {
                    $nullSeen = true;
                    continue;
                }
                $typed = self::applyAffinityNext123($value, $affinity, $collation);
                $signature = serialize($typed);
                if (isset($seen[$signature])) {
                    $duplicates++;
                    continue;
                }
                $seen[$signature] = true;
                $values[] = $typed['value'];
                $typedValues[] = $typed;
            }
            if ($values === []) {
                throw new \InvalidArgumentException('SQLite subquery expression-index planner needs at least one non-NULL subquery key');
            }

            return [
                'operator' => 'IN',
                'left' => $left,
                'values' => $values,
                'typedValues' => $typedValues,
                'subquery' => [
                    'sourceName' => self::stringValueNext123($subquery, 'sourceName', 'subquery'),
                    'keyColumn' => $keyColumn,
                    'rows' => $rows,
                    'nullSeen' => $nullSeen,
                    'duplicatesRemoved' => $duplicates,
                    'collation' => $collation,
                    'affinity' => $affinity,
                    'correlatedOuterColumns' => self::stringListNext123($subquery, 'correlatedOuterColumns'),
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function cursorTapeNext123(array $plan, array $predicate, string $source, bool $usable, array $neededColumns): array
        {
            $program = [
                ['opcode' => 'OpenRead', 'source' => 'expression-index', 'name' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => 'OpenEphemeral', 'source' => 'subquery-expression-keys', 'rows' => count($predicate['typedValues'])],
                ['opcode' => 'Rewind', 'source' => 'subquery-expression-keys'],
                ['opcode' => 'SeekGE', 'source' => 'expression-index', 'keyFrom' => 'subquery-expression-keys'],
                ['opcode' => 'IdxGT', 'source' => 'expression-index', 'keyFrom' => 'subquery-expression-keys'],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => ($plan['covering'] ?? false) === true ? 'expression-index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => 'Next', 'source' => 'subquery-expression-keys'];

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'seekOpcode' => 'SeekGE',
                'stopOpcode' => 'IdxGT',
                'nextOpcode' => 'Next',
                'subqueryValues' => $predicate['values'],
                'typedValues' => $predicate['typedValues'],
                'rangeFence' => $plan['rangeFence'] ?? null,
                'dedupeSubqueryValues' => true,
                'nullFilteredBeforeIndexSeek' => true,
                'collationMatched' => ($plan['collationMatched'] ?? false) === true,
                'affinityMatched' => ($plan['affinityMatched'] ?? false) === true,
                'expressionMatched' => ($plan['expressionMatched'] ?? false) === true,
                'tableLookupElided' => $usable && ($plan['covering'] ?? false) === true,
                'deferredSeekOpcode' => $usable && ($plan['covering'] ?? false) === true ? null : 'DeferredSeek',
                'program' => $program,
            ];
        }

        /** @param array<string,mixed> $index */
        private static function indexExpressionNext123(array $index): ?array
        {
            $expressions = $index['expressions'] ?? null;
            if (is_array($expressions) && isset($expressions[0]) && is_array($expressions[0])) {
                return $expressions[0];
            }
            $sql = $index['sql'] ?? null;
            if (!is_string($sql)) {
                return null;
            }
            if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+\S+\s+ON\s+\S+\s*\(\s*(lower|upper|length)\s*\(\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\)(?:\s+COLLATE\s+([a-zA-Z_][a-zA-Z0-9_]*))?(?:\s+(DESC|ASC))?/i', $sql, $match) !== 1) {
                return null;
            }

            return [
                'function' => strtolower($match[1]),
                'column' => $match[2],
                'collation' => strtoupper($match[3] ?? 'BINARY'),
                'affinity' => strtolower($match[1]) === 'length' ? 'INTEGER' : 'TEXT',
                'descending' => isset($match[4]) && strcasecmp($match[4], 'DESC') === 0,
            ];
        }

        /** @param array<string,mixed> $expression */
        private static function expressionSignatureNext123(array $expression): string
        {
            return strtolower(self::stringValueNext123($expression, 'function'))
                . '(' . strtolower(self::stringValueNext123($expression, 'column')) . ')';
        }

        /**
         * @param array<string,mixed> $index
         * @param array<string,mixed> $predicate
         */
        private static function partialPredicateImpliedNext123(array $index, array $predicate): bool
        {
            $partial = $index['partialPredicate'] ?? null;
            if ($partial === null || $partial === '') {
                return true;
            }
            if (!is_string($partial)) {
                throw new \InvalidArgumentException('SQLite subquery expression-index partial predicate must be SQL text');
            }
            if (preg_match("/lower\s*\(\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\)\s*>=\s*'([^']*)'/i", $partial, $match) === 1) {
                if (self::expressionSignatureNext123($predicate['left']) !== 'lower(' . strtolower($match[1]) . ')') {
                    return false;
                }
                foreach ($predicate['values'] as $value) {
                    if (strcmp((string) $value, strtolower($match[2])) < 0) {
                        return false;
                    }
                }

                return true;
            }
            if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)\s+IS\s+NOT\s+NULL/i', $partial) === 1) {
                return $predicate['subquery']['nullSeen'] === false;
            }

            return false;
        }

        /** @return array{first:mixed,last:mixed,descending:bool} */
        private static function rangeFenceNext123(array $typedValues, bool $descending): array
        {
            $values = array_column($typedValues, 'value');
            usort($values, static fn (mixed $left, mixed $right): int => $left <=> $right);
            if ($descending) {
                $values = array_reverse($values);
            }

            return ['first' => $values[0], 'last' => $values[count($values) - 1], 'descending' => $descending];
        }

        /** @return array{value:mixed,type:string,collation:string,affinity:string} */
        private static function applyAffinityNext123(mixed $value, string $affinity, string $collation): array
        {
            $type = 'text';
            if ($affinity === 'INTEGER') {
                if (is_numeric($value)) {
                    $value = (int) $value;
                    $type = 'integer';
                }
            } elseif (is_string($value)) {
                $value = $collation === 'NOCASE' ? strtolower($value) : $value;
            }

            return ['value' => $value, 'type' => $type, 'collation' => $collation, 'affinity' => $affinity];
        }

        /** @param array<string,mixed> $source */
        private static function sourceSignatureNext123(array $source): string
        {
            return hash('sha256', json_encode($source['indexes'] ?? [], JSON_THROW_ON_ERROR));
        }

        /** @param array<string,mixed> $normalized */
        private static function subquerySignatureNext123(array $normalized): string
        {
            return hash('sha256', json_encode([
                $normalized['left'],
                $normalized['typedValues'],
                $normalized['subquery']['collation'],
                $normalized['subquery']['affinity'],
            ], JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummaryNext123(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValueNext123($source, 'name'),
                'schemaCookie' => self::nonNegativeIntNext123($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext123($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => ($plan['usable'] ?? false) === true,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
            ];
        }

        /** @param array<string,mixed> $value */
        private static function stringValueNext123(array $value, string $key, string $default = ''): string
        {
            $candidate = $value[$key] ?? $default;
            if (!is_string($candidate)) {
                throw new \InvalidArgumentException("SQLite subquery expression-index planner expected string {$key}");
            }

            return $candidate;
        }

        /** @param array<string,mixed> $value */
        private static function nullableIntNext123(array $value, string $key): ?int
        {
            if (!array_key_exists($key, $value) || $value[$key] === null) {
                return null;
            }
            if (!is_int($value[$key])) {
                throw new \InvalidArgumentException("SQLite subquery expression-index planner expected int {$key}");
            }

            return $value[$key];
        }

        /** @param array<string,mixed> $value */
        private static function nonNegativeIntNext123(array $value, string $key, int $default = 0): int
        {
            $candidate = $value[$key] ?? $default;
            if (!is_int($candidate) || $candidate < 0) {
                throw new \InvalidArgumentException("SQLite subquery expression-index planner expected non-negative int {$key}");
            }

            return $candidate;
        }

        /**
         * @param array<string,mixed> $value
         * @return list<mixed>
         */
        private static function listValueNext123(array $value, string $key): array
        {
            $candidate = $value[$key] ?? [];
            if (!is_array($candidate) || array_keys($candidate) !== range(0, count($candidate) - 1)) {
                throw new \InvalidArgumentException("SQLite subquery expression-index planner expected list {$key}");
            }

            return $candidate;
        }

        /**
         * @param array<string,mixed> $value
         * @return list<string>
         */
        private static function stringListNext123(array $value, string $key): array
        {
            $candidate = self::listValueNext123($value, $key);
            foreach ($candidate as $item) {
                if (!is_string($item)) {
                    throw new \InvalidArgumentException("SQLite subquery expression-index planner expected string list {$key}");
                }
            }

            return $candidate;
        }

}
