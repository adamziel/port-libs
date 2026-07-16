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
        public static function materialize(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            self::validateNeededColumns($neededColumns);

            $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
            $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedSignature = self::signature($preparedSource);
            $currentSignature = self::signature($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $currentPlan : $preparedPlan;
            $source = $stale ? $currentSource : $preparedSource;
            $rows = self::coveringRows($selected, $predicate, $currentRows, $neededColumns, $neededExpressions);
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
                'preparedSource' => self::summary($preparedSource, $preparedPlan, $preparedSignature),
                'currentSource' => self::summary($currentSource, $currentPlan, $currentSignature),
                'selectedPlan' => $selected + ['coveredRowCount' => count($rows)],
                'coveringPayloadColumns' => array_values($neededColumns),
                'coveringExpressionCount' => count($neededExpressions),
                'tableLookupElided' => $ready,
                'deferredTableSeekOpcode' => $ready ? null : 'DeferredSeek',
                'tempSorterElided' => $ready && ($selected['orderBySatisfied'] ?? false) === true,
                'currentNextRows' => self::currentNextRows($rows),
                'cursorTape' => self::cursorTape($selected, $rows, $neededColumns, $ready, $stale ? 'current' : 'prepared'),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                    'coveringSignature' => implode(',', array_map('strtolower', $neededColumns)),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' COVERING EXPRESSION STAT4 '
                    . (string) ($selected['name'] ?? self::stringValue($source, 'name')),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan bounded range STAT4 planner',
                    'sqlite-planner-covering-expression-stat4-current-source',
                ],
                'dependency_closure' => 'no new support component needed; canonical covering expression STAT4 planner reuses native expression-index parsing, STAT4 samples, and covering cursor diagnostics',
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
        private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns, array $neededExpressions): array
        {
            $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost(
                self::indexes($source),
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
        private static function indexes(array $source): array
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
        private static function validateNeededColumns(array $neededColumns): void
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
        private static function nonNegativeInt(array $source, string $key): int
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
        private static function signature(array $source): string
        {
            $parts = [];
            foreach (self::indexes($source) as $index) {
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
        private static function summary(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValue($source, 'name'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
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
        private static function coveringRows(array $plan, array $predicate, array $currentRows, array $neededColumns, array $neededExpressions): array
        {
            if (($plan['usable'] ?? false) !== true || ($plan['covering'] ?? false) !== true) {
                return [];
            }

            $rows = [];
            foreach ($currentRows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite covering expression STAT4 current rows must be arrays');
                }
                if (!self::rowSatisfiesPredicate($row, $predicate)) {
                    continue;
                }

                $key = self::expressionValue($plan, $row);
                $rows[] = [
                    'sourceOffset' => $offset,
                    'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                    'key' => $key,
                    'covering' => self::payload($row, $neededColumns),
                    'coveringExpressions' => self::expressionPayload($plan, $row, $neededExpressions),
                ];
            }

            usort($rows, static function (array $left, array $right): int {
                $comparison = self::compareKeys($left['key'], $right['key']);
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
        private static function currentNextRows(array $rows): array
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
        private static function cursorTape(array $plan, array $rows, array $neededColumns, bool $ready, string $source): array
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
                'currentNextRows' => self::currentNextRows($rows),
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
        private static function rowSatisfiesPredicate(array $row, array $predicate): bool
        {
            $operator = strtoupper((string) ($predicate['operator'] ?? ''));
            if ($operator === 'AND') {
                $terms = $predicate['terms'] ?? null;
                if (!is_array($terms) || !array_is_list($terms)) {
                    return false;
                }
                foreach ($terms as $term) {
                    if (!is_array($term) || !self::rowSatisfiesPredicate($row, $term)) {
                        return false;
                    }
                }

                return true;
            }

            $left = self::operandValue($predicate['left'] ?? null, $row);
            return match ($operator) {
                '=', '==' => $left == ($predicate['right'] ?? null),
                '>' => self::compareKeys($left, $predicate['right'] ?? null) > 0,
                '>=' => self::compareKeys($left, $predicate['right'] ?? null) >= 0,
                '<' => self::compareKeys($left, $predicate['right'] ?? null) < 0,
                '<=' => self::compareKeys($left, $predicate['right'] ?? null) <= 0,
                'BETWEEN' => self::compareKeys($left, $predicate['lower'] ?? null) >= 0
                    && self::compareKeys($left, $predicate['upper'] ?? null) <= 0,
                default => true,
            };
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $row
         */
        private static function expressionValue(array $plan, array $row): mixed
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
        private static function operandValue(mixed $operand, array $row): mixed
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

            return self::expressionValue($plan, $row);
        }

        /**
         * @param array<string,mixed> $row
         * @param list<string> $columns
         * @return array<string,mixed>
         */
        private static function payload(array $row, array $columns): array
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
        private static function expressionPayload(array $plan, array $row, array $expressions): array
        {
            $payload = [];
            foreach ($expressions as $expression) {
                $function = strtolower((string) ($expression['function'] ?? ''));
                $column = (string) ($expression['column'] ?? '');
                if ($function === '' || $column === '') {
                    continue;
                }
                $payload[$function . '(' . $column . ')'] = self::operandValue(['function' => $function, 'column' => $column], $row);
            }
            if ($payload === [] && isset($plan['type'], $plan['column'])) {
                $payload[(string) $plan['type'] . '(' . (string) $plan['column'] . ')'] = self::expressionValue($plan, $row);
            }

            return $payload;
        }

        private static function compareKeys(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return ((float) $left) <=> ((float) $right);
            }

            return strcmp((string) $left, (string) $right);
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function stringValue(array $source, string $key): string
        {
            $value = $source[$key] ?? '';

            return is_string($value) ? $value : '';
        }

}
