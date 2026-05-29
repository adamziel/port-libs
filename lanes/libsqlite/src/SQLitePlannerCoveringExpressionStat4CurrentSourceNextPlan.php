<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan. */

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
        public static function materializeNext122(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            self::validateNeededColumnsNext122($neededColumns);

            $preparedPlan = self::sourcePlanNext122($preparedSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
            $currentPlan = self::sourcePlanNext122($currentSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
            $preparedCookie = self::nonNegativeIntNext122($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext122($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeIntNext122($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeIntNext122($currentSource, 'stat4Generation');
            $preparedSignature = self::signatureNext122($preparedSource);
            $currentSignature = self::signatureNext122($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $currentPlan : $preparedPlan;
            $source = $stale ? $currentSource : $preparedSource;
            $rows = self::coveringRowsNext122($selected, $predicate, $currentRows, $neededColumns, $neededExpressions);
            $ready = ($selected['usable'] ?? false) === true
                && ($selected['covering'] ?? false) === true
                && ($selected['stat4Used'] ?? false) === true
                && ($selected['operator'] ?? null) === 'range-bounded'
                && $rows !== [];

            return [
                'status' => $ready ? 'covering-expression-stat4-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::summaryNext122($preparedSource, $preparedPlan, $preparedSignature),
                'currentSource' => self::summaryNext122($currentSource, $currentPlan, $currentSignature),
                'selectedPlan' => $selected + ['coveredRowCount' => count($rows)],
                'coveringPayloadColumns' => array_values($neededColumns),
                'coveringExpressionCount' => count($neededExpressions),
                'tableLookupElided' => $ready,
                'deferredTableSeekOpcode' => $ready ? null : 'DeferredSeek',
                'tempSorterElided' => $ready && ($selected['orderBySatisfied'] ?? false) === true,
                'currentNextRows' => self::currentNextNext122($rows),
                'cursorTape' => self::cursorTapeNext122($selected, $rows, $neededColumns, $ready, $stale ? 'current' : 'prepared'),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                    'coveringSignature' => implode(',', array_map('strtolower', $neededColumns)),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' COVERING EXPRESSION STAT4 '
                    . (string) ($selected['name'] ?? self::stringValueNext122($source, 'name')),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan bounded range STAT4 planner',
                    'sqlite-planner-covering-expression-stat4-current-source-next122',
                ],
                'dependency_closure' => 'no new support component needed; next122 reuses native expression-index parsing, STAT4 samples, and covering cursor diagnostics',
                'non_overlap' => 'avoids accepted next118 partial expression covering, next119 ordinary covering range order, expression ORDER BY, and range-cost ranking by materializing stale current-source rows for a bounded covering expression STAT4 range cursor',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        private static function sourcePlanNext122(array $source, array $predicate, array $orderBy, array $neededColumns, array $neededExpressions): array
        {
            $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
                self::indexesNext122($source),
                $predicate,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );

            return $plan ?? [
                'usable' => false,
                'covering' => false,
                'stat4Used' => false,
                'operator' => null,
                'estimatedRows' => 0,
                'stat4MatchedSamples' => 0,
                'detail' => 'SCAN TABLE; NO COVERING EXPRESSION STAT4 RANGE',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @return list<array<string,mixed>>
         */
        private static function indexesNext122(array $source): array
        {
            $indexes = $source['indexes'] ?? null;
            if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
                throw new \InvalidArgumentException('SQLite covering expression STAT4 source needs index definitions');
            }
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite covering expression STAT4 indexes must be arrays');
                }
            }

            return $indexes;
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function validateNeededColumnsNext122(array $neededColumns): void
        {
            if ($neededColumns === []) {
                throw new \InvalidArgumentException('SQLite covering expression STAT4 plan needs at least one output column');
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite covering expression STAT4 output columns must be names');
                }
            }
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function nonNegativeIntNext122(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite covering expression STAT4 source ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function signatureNext122(array $source): string
        {
            $parts = [];
            foreach (self::indexesNext122($source) as $index) {
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
        private static function summaryNext122(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValueNext122($source, 'name'),
                'schemaCookie' => self::nonNegativeIntNext122($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext122($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => (bool) ($plan['usable'] ?? false),
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'stat4MatchedSamples' => (int) ($plan['stat4MatchedSamples'] ?? 0),
                'covering' => (bool) ($plan['covering'] ?? false),
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return list<array<string,mixed>>
         */
        private static function coveringRowsNext122(array $plan, array $predicate, array $currentRows, array $neededColumns, array $neededExpressions): array
        {
            if (($plan['usable'] ?? false) !== true || ($plan['covering'] ?? false) !== true) {
                return [];
            }

            $rows = [];
            foreach ($currentRows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite covering expression STAT4 current rows must be arrays');
                }
                if (!self::rowSatisfiesPredicateNext122($row, $predicate)) {
                    continue;
                }

                $key = self::expressionValueNext122($plan, $row);
                $rows[] = [
                    'sourceOffset' => $offset,
                    'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                    'key' => $key,
                    'covering' => self::payloadNext122($row, $neededColumns),
                    'coveringExpressions' => self::expressionPayloadNext122($plan, $row, $neededExpressions),
                ];
            }

            usort($rows, static function (array $left, array $right): int {
                $comparison = self::compareKeysNext122($left['key'], $right['key']);
                if ($comparison !== 0) {
                    return $comparison;
                }

                return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
            });

            return $rows;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
         */
        private static function currentNextNext122(array $rows): array
        {
            $pairs = [];
            foreach ($rows as $offset => $row) {
                $pairs[] = ['current' => $row, 'next' => $rows[$offset + 1] ?? null];
            }

            return $pairs;
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<array<string,mixed>> $rows
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function cursorTapeNext122(array $plan, array $rows, array $neededColumns, bool $ready, string $source): array
        {
            $values = is_array($plan['values'] ?? null) ? $plan['values'] : [];
            $lower = $values['lower'] ?? null;
            $upper = $values['upper'] ?? null;
            $lowerInclusive = (bool) ($values['lowerInclusive'] ?? false);
            $upperInclusive = (bool) ($values['upperInclusive'] ?? false);
            $program = [
                ['opcode' => $lowerInclusive ? 'SeekGE' : 'SeekGT', 'source' => 'index', 'key' => $lower],
                ['opcode' => $upperInclusive ? 'IdxGT' : 'IdxGE', 'source' => 'index', 'key' => $upper],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => 'ResultRow', 'source' => $ready ? 'covering-index' : 'table'];
            $program[] = ['opcode' => 'Next', 'source' => $ready ? 'index' : 'table'];

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'expressionType' => $plan['type'] ?? null,
                'expressionColumn' => $plan['column'] ?? null,
                'rangeLower' => $lower,
                'rangeUpper' => $upper,
                'seekOpcode' => $lowerInclusive ? 'SeekGE' : 'SeekGT',
                'stopOpcode' => $upperInclusive ? 'IdxGT' : 'IdxGE',
                'matchedKeys' => array_map(static fn (array $row): mixed => $row['key'], $rows),
                'currentNextRows' => self::currentNextNext122($rows),
                'outputColumns' => array_map(static fn (string $column): array => ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column], $neededColumns),
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
                'sorterOpen' => !$ready || ($plan['orderBySatisfied'] ?? false) !== true,
                'program' => $program,
            ];
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $predicate
         */
        private static function rowSatisfiesPredicateNext122(array $row, array $predicate): bool
        {
            $operator = strtoupper((string) ($predicate['operator'] ?? ''));
            if ($operator === 'AND') {
                $terms = $predicate['terms'] ?? null;
                if (!is_array($terms) || !array_is_list($terms)) {
                    return false;
                }
                foreach ($terms as $term) {
                    if (!is_array($term) || !self::rowSatisfiesPredicateNext122($row, $term)) {
                        return false;
                    }
                }

                return true;
            }

            $left = self::operandValueNext122($predicate['left'] ?? null, $row);
            return match ($operator) {
                '=', '==' => $left == ($predicate['right'] ?? null),
                '>' => self::compareKeysNext122($left, $predicate['right'] ?? null) > 0,
                '>=' => self::compareKeysNext122($left, $predicate['right'] ?? null) >= 0,
                '<' => self::compareKeysNext122($left, $predicate['right'] ?? null) < 0,
                '<=' => self::compareKeysNext122($left, $predicate['right'] ?? null) <= 0,
                'BETWEEN' => self::compareKeysNext122($left, $predicate['lower'] ?? null) >= 0
                    && self::compareKeysNext122($left, $predicate['upper'] ?? null) <= 0,
                default => true,
            };
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $row
         */
        private static function expressionValueNext122(array $plan, array $row): mixed
        {
            $column = (string) ($plan['column'] ?? '');
            $value = $row[$column] ?? null;

            return match ((string) ($plan['type'] ?? '')) {
                'lower' => strtolower((string) $value),
                'upper' => strtoupper((string) $value),
                'length' => strlen((string) $value),
                'integer-cast' => (int) $value,
                default => $value,
            };
        }

        /**
         * @param array<string,mixed>|mixed $operand
         * @param array<string,mixed> $row
         */
        private static function operandValueNext122(mixed $operand, array $row): mixed
        {
            if (!is_array($operand)) {
                return $operand;
            }
            if (isset($operand['column']) && !isset($operand['function'])) {
                return $row[(string) $operand['column']] ?? null;
            }
            $plan = ['type' => strtolower((string) ($operand['function'] ?? '')), 'column' => (string) ($operand['column'] ?? '')];
            if ($plan['type'] === 'cast_integer') {
                $plan['type'] = 'integer-cast';
            }

            return self::expressionValueNext122($plan, $row);
        }

        /**
         * @param array<string,mixed> $row
         * @param list<string> $columns
         * @return array<string,mixed>
         */
        private static function payloadNext122(array $row, array $columns): array
        {
            $payload = [];
            foreach ($columns as $column) {
                $payload[$column] = $row[$column] ?? null;
            }

            return $payload;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $row
         * @param list<array<string,string>> $expressions
         * @return array<string,mixed>
         */
        private static function expressionPayloadNext122(array $plan, array $row, array $expressions): array
        {
            $payload = [];
            foreach ($expressions as $expression) {
                $function = strtolower((string) ($expression['function'] ?? ''));
                $column = (string) ($expression['column'] ?? '');
                if ($function === '' || $column === '') {
                    continue;
                }
                $payload[$function . '(' . $column . ')'] = self::operandValueNext122(['function' => $function, 'column' => $column], $row);
            }
            if ($payload === [] && isset($plan['type'], $plan['column'])) {
                $payload[(string) $plan['type'] . '(' . (string) $plan['column'] . ')'] = self::expressionValueNext122($plan, $row);
            }

            return $payload;
        }

        private static function compareKeysNext122(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return ((float) $left) <=> ((float) $right);
            }

            return strcmp((string) $left, (string) $right);
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function stringValueNext122(array $source, string $key): string
        {
            $value = $source[$key] ?? '';

            return is_string($value) ? $value : '';
        }

}
