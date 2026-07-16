<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeCoveringRangeOrderCurrentSource(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns): array
        {
            self::validateOrderBy($orderBy);
            self::validateNeededColumns($neededColumns);

            $prepared = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
            $current = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);

            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedSignature = self::indexSignature($preparedSource);
            $currentSignature = self::indexSignature($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $current : $prepared;
            $ready = ($selected['covering'] ?? false) === true
                && ($selected['orderBySatisfied'] ?? false) === true
                && ($selected['rangeColumn'] ?? null) !== null
                && ($selected['partialPredicateImplied'] ?? false) === true;

            return [
                'status' => $ready ? 'covering-range-order-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::sourceSummary($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'coveringRangeOrderPlan' => $ready,
                'tableLookupElided' => $ready,
                'tempSortElided' => $ready,
                'cursorTape' => self::cursorTape($selected, $orderBy, $neededColumns, $ready, $stale ? 'current' : 'prepared'),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'orderSignature' => self::orderSignature($orderBy),
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' COVERING RANGE ORDER USING '
                    . ($stale ? self::stringValue($currentSource, 'name') : self::stringValue($preparedSource, 'name'))
                    . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependencies' => [
                    'SQLiteCreateIndex column/partial parsing',
                    'sqlite-planner-covering-range-order-current-source',
                ],
                'dependency_closure' => 'no new support component needed; current-source composes native CREATE INDEX parsing, partial-predicate implication, STAT4 current/next samples, and covering range ORDER BY cursor diagnostics',
                'non_overlap' => 'avoids accepted expression ORDER BY, subquery covering partial, STAT4 expression covering, and parser-level SELECT text clusters by asserting ordinary multicolumn covering range ORDER BY current-source cursor materialization',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns): array
        {
            $terms = self::flattenAndTerms($predicate);
            $plans = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite covering range order source indexes must be arrays');
                }
                $sql = self::stringValue($index, 'sql');
                $columns = SQLiteCreateIndex::columns($sql);
                if ($columns === null || $columns === []) {
                    continue;
                }
                $partialImplied = self::partialPredicateImplied($columns[0]->partialPredicate, $terms);
                if (!$partialImplied) {
                    continue;
                }

                $prefix = self::usablePrefix($columns, $terms);
                if ($prefix['rangeColumn'] === null) {
                    continue;
                }

                $covering = self::covers($columns, $neededColumns);
                $orderSatisfied = self::orderBySatisfied($columns, $orderBy, $prefix['equalityPrefix'], $prefix['rangeColumn']);
                $samples = self::matchedSamples(self::listValue($index, 'stat4Samples', false), $prefix['rangeLower'], $prefix['rangeUpper'], $prefix['lowerInclusive'], $prefix['upperInclusive'], $prefix['rangeColumn']);
                $estimatedRows = $samples === []
                    ? max(1, (int) ($index['estimatedRows'] ?? 1))
                    : max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $samples)));
                $cost = $estimatedRows + 32 - ($prefix['equalityPrefix'] * 8) - ($covering ? 32 : 0) - ($orderSatisfied ? 12 : 0) - ($columns[0]->partial ? 4 : 0);

                $plans[] = [
                    'usable' => true,
                    'name' => self::stringValue($index, 'name', self::indexName($sql)),
                    'rootPage' => self::nonNegativeInt($index, 'rootPage'),
                    'columns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns),
                    'collations' => array_map(static fn (SQLiteIndexColumn $column): string => $column->collation, $columns),
                    'descending' => array_map(static fn (SQLiteIndexColumn $column): bool => $column->descending, $columns),
                    'usedColumns' => $prefix['usedColumns'],
                    'equalityPrefix' => $prefix['equalityPrefix'],
                    'rangeColumn' => $prefix['rangeColumn'],
                    'rangeLower' => $prefix['rangeLower'],
                    'rangeUpper' => $prefix['rangeUpper'],
                    'lowerInclusive' => $prefix['lowerInclusive'],
                    'upperInclusive' => $prefix['upperInclusive'],
                    'residualColumns' => $prefix['residualColumns'],
                    'partial' => $columns[0]->partial,
                    'partialPredicateImplied' => $partialImplied,
                    'covering' => $covering,
                    'orderBySatisfied' => $orderSatisfied,
                    'blockSortRequired' => $orderBy !== [] && !$orderSatisfied,
                    'tableLookupRequired' => !$covering,
                    'stat4Used' => $samples !== [],
                    'stat4MatchedSamples' => count($samples),
                    'stat4CurrentNext' => self::currentNext($samples),
                    'estimatedRows' => $estimatedRows,
                    'estimatedCost' => max(1, $cost),
                    'detail' => 'SEARCH ' . self::stringValue($index, 'name', self::indexName($sql))
                        . ' USING COVERING RANGE'
                        . ($orderSatisfied ? ' ORDER BY' : ' BLOCK SORT')
                        . ($columns[0]->partial ? ' PARTIAL' : ''),
                ];
            }

            usort($plans, static function (array $left, array $right): int {
                $leftReady = ($left['covering'] && $left['orderBySatisfied']) ? 0 : 1;
                $rightReady = ($right['covering'] && $right['orderBySatisfied']) ? 0 : 1;

                return [$leftReady, $left['estimatedCost'], $left['estimatedRows'], $left['name']]
                    <=> [$rightReady, $right['estimatedCost'], $right['estimatedRows'], $right['name']];
            });

            return $plans[0] ?? [
                'usable' => false,
                'covering' => false,
                'orderBySatisfied' => false,
                'partialPredicateImplied' => false,
                'rangeColumn' => null,
                'detail' => 'SCAN TABLE; NO USABLE COVERING RANGE ORDER',
            ];
        }

        /**
         * @param list<SQLiteIndexColumn> $columns
         * @param list<array<string,mixed>> $terms
         * @return array{usedColumns:list<string>,equalityPrefix:int,rangeColumn:?string,rangeLower:mixed,rangeUpper:mixed,lowerInclusive:bool,upperInclusive:bool,residualColumns:list<string>}
         */
        private static function usablePrefix(array $columns, array $terms): array
        {
            $used = [];
            $equalityPrefix = 0;
            $rangeColumn = null;
            $rangeLower = null;
            $rangeUpper = null;
            $lowerInclusive = false;
            $upperInclusive = false;

            foreach ($columns as $column) {
                $name = $column->columnName;
                if ($rangeColumn !== null) {
                    break;
                }
                $constraints = self::constraintsForColumn($terms, $name);
                $hasPoint = false;
                foreach ($constraints as $constraint) {
                    if ($constraint['operator'] === '=' || $constraint['operator'] === '==') {
                        $hasPoint = true;
                        break;
                    }
                }
                if ($hasPoint) {
                    $used[] = $name;
                    $equalityPrefix++;
                    continue;
                }
                foreach ($constraints as $constraint) {
                    $operator = strtoupper($constraint['operator']);
                    if ($operator === '>=') {
                        $rangeLower = $constraint['value'];
                        $lowerInclusive = true;
                    } elseif ($operator === '>') {
                        $rangeLower = $constraint['value'];
                        $lowerInclusive = false;
                    } elseif ($operator === '<=') {
                        $rangeUpper = $constraint['value'];
                        $upperInclusive = true;
                    } elseif ($operator === '<') {
                        $rangeUpper = $constraint['value'];
                        $upperInclusive = false;
                    } elseif ($operator === 'BETWEEN') {
                        $rangeLower = $constraint['lower'];
                        $rangeUpper = $constraint['upper'];
                        $lowerInclusive = true;
                        $upperInclusive = true;
                    }
                }
                if ($rangeLower !== null || $rangeUpper !== null) {
                    $rangeColumn = $name;
                    $used[] = $name;
                }
            }

            $residual = [];
            foreach ($terms as $term) {
                $column = self::columnOperand($term['left'] ?? null);
                if ($column !== null && !in_array($column, $used, true)) {
                    $residual[] = $column;
                }
            }

            return [
                'usedColumns' => $used,
                'equalityPrefix' => $equalityPrefix,
                'rangeColumn' => $rangeColumn,
                'rangeLower' => $rangeLower,
                'rangeUpper' => $rangeUpper,
                'lowerInclusive' => $lowerInclusive,
                'upperInclusive' => $upperInclusive,
                'residualColumns' => array_values(array_unique($residual)),
            ];
        }

        /**
         * @param list<SQLiteIndexColumn> $columns
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderBySatisfied(array $columns, array $orderBy, int $equalityPrefix, ?string $rangeColumn): bool
        {
            if ($orderBy === [] || $rangeColumn === null) {
                return false;
            }

            $orderedColumns = array_slice($columns, $equalityPrefix);
            if (count($orderBy) > count($orderedColumns)) {
                return false;
            }

            foreach ($orderBy as $offset => $term) {
                $column = $orderedColumns[$offset] ?? null;
                if (!$column instanceof SQLiteIndexColumn) {
                    return false;
                }
                if (strcasecmp($term['column'], $column->columnName) !== 0) {
                    return false;
                }
                $direction = strtoupper($term['direction'] ?? 'ASC');
                if ($direction !== ($column->descending ? 'DESC' : 'ASC')) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param list<array<string,mixed>> $terms
         * @return list<array<string,mixed>>
         */
        private static function constraintsForColumn(array $terms, string $column): array
        {
            $constraints = [];
            foreach ($terms as $term) {
                $left = self::columnOperand($term['left'] ?? null);
                if ($left === null || strcasecmp($left, $column) !== 0) {
                    continue;
                }
                $operator = strtoupper(self::stringValue($term, 'operator'));
                if (in_array($operator, ['=', '==', '<', '<=', '>', '>='], true)) {
                    $constraints[] = ['operator' => $operator, 'value' => $term['right'] ?? null];
                } elseif ($operator === 'BETWEEN') {
                    $constraints[] = ['operator' => $operator, 'lower' => $term['lower'] ?? null, 'upper' => $term['upper'] ?? null];
                }
            }

            return $constraints;
        }

        /**
         * @param list<SQLiteIndexColumn> $columns
         * @param list<string> $neededColumns
         */
        private static function covers(array $columns, array $neededColumns): bool
        {
            $available = array_map(static fn (SQLiteIndexColumn $column): string => strtolower($column->columnName), $columns);
            foreach ($neededColumns as $column) {
                if (!in_array(strtolower($column), $available, true)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param list<array<string,mixed>> $terms
         */
        private static function partialPredicateImplied(?SQLiteIndexPredicate $partial, array $terms): bool
        {
            if ($partial === null) {
                return true;
            }
            foreach ($terms as $term) {
                $column = self::columnOperand($term['left'] ?? null);
                if ($column === null) {
                    continue;
                }
                $operator = strtoupper(self::stringValue($term, 'operator'));
                if (($operator === '=' || $operator === '==') && $partial->isImpliedByPointLookup($column, $term['right'] ?? null)) {
                    return true;
                }
                if ($operator === 'BETWEEN' && $partial->isImpliedByRangeLookup($column, $term['lower'] ?? null, true, $term['upper'] ?? null, true)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @param list<array<string,mixed>> $samples
         * @return list<array{neq:int,nlt:int,ndlt:int,sample:list<mixed>}>
         */
        private static function matchedSamples(array $samples, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, string $rangeColumn): array
        {
            $matched = [];
            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    continue;
                }
                $values = $sample['sample'] ?? null;
                if (!is_array($values) || $values === []) {
                    continue;
                }
                $key = $values[count($values) - 1];
                if (!self::withinRange($key, $lower, $upper, $lowerInclusive, $upperInclusive)) {
                    continue;
                }
                $matched[] = [
                    'neq' => self::lastStatInt($sample['neq'] ?? 1),
                    'nlt' => self::lastStatInt($sample['nlt'] ?? 0),
                    'ndlt' => self::lastStatInt($sample['ndlt'] ?? 0),
                    'sample' => array_values($values),
                    'rangeColumn' => $rangeColumn,
                ];
            }

            return $matched;
        }

        private static function withinRange(mixed $value, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive): bool
        {
            if ($lower !== null) {
                $comparison = self::compare($value, $lower);
                if ($comparison < 0 || ($comparison === 0 && !$lowerInclusive)) {
                    return false;
                }
            }
            if ($upper !== null) {
                $comparison = self::compare($value, $upper);
                if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                    return false;
                }
            }

            return true;
        }

        private static function compare(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return (float) $left <=> (float) $right;
            }

            return strcmp((string) $left, (string) $right) <=> 0;
        }

        private static function lastStatInt(mixed $value): int
        {
            if (is_array($value)) {
                return max(0, (int) ($value[count($value) - 1] ?? 0));
            }
            if (is_string($value)) {
                $parts = preg_split('/\s+/', trim($value));

                return max(0, (int) ($parts === false ? $value : $parts[count($parts) - 1]));
            }

            return max(0, (int) $value);
        }

        /**
         * @param list<array{neq:int,nlt:int,ndlt:int,sample:list<mixed>,rangeColumn:string}> $samples
         * @return list<array<string,mixed>>
         */
        private static function currentNext(array $samples): array
        {
            $pairs = [];
            foreach ($samples as $offset => $sample) {
                $next = $samples[$offset + 1] ?? null;
                $pairs[] = [
                    'current' => ['key' => $sample['sample'][count($sample['sample']) - 1], 'sample' => $sample['sample']],
                    'next' => $next === null ? null : ['key' => $next['sample'][count($next['sample']) - 1], 'sample' => $next['sample']],
                    'neq' => $sample['neq'],
                    'nlt' => $sample['nlt'],
                    'ndlt' => $sample['ndlt'],
                    'advance' => $next === null ? 'eof' : 'next',
                ];
            }

            return $pairs;
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function cursorTape(array $plan, array $orderBy, array $neededColumns, bool $ready, string $source): array
        {
            $program = [
                ['opcode' => 'OpenRead', 'source' => 'index', 'name' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => self::seekOpcode($plan), 'source' => 'index', 'column' => $plan['rangeColumn'] ?? null, 'key' => $plan['rangeLower'] ?? null],
                ['opcode' => self::stopOpcode($plan), 'source' => 'index', 'column' => $plan['rangeColumn'] ?? null, 'key' => $plan['rangeUpper'] ?? null],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column];
            }
            if (!$ready && ($plan['orderBySatisfied'] ?? false) !== true && $orderBy !== []) {
                $program[] = ['opcode' => 'SorterOpen', 'source' => 'temp-btree'];
            }
            $program[] = ['opcode' => self::nextOpcode($orderBy), 'source' => 'index'];

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'seekOpcode' => self::seekOpcode($plan),
                'stopOpcode' => self::stopOpcode($plan),
                'nextOpcode' => self::nextOpcode($orderBy),
                'scanDirection' => self::scanDirection($orderBy),
                'rangeColumn' => $plan['rangeColumn'] ?? null,
                'rangeLower' => $plan['rangeLower'] ?? null,
                'rangeUpper' => $plan['rangeUpper'] ?? null,
                'rangeLowerExact' => ($plan['lowerInclusive'] ?? false) === true,
                'rangeUpperExact' => ($plan['upperInclusive'] ?? false) === true,
                'currentNextSegments' => $plan['stat4CurrentNext'] ?? [],
                'currentNextCount' => count($plan['stat4CurrentNext'] ?? []),
                'matchedKeys' => array_map(static fn (array $pair): mixed => $pair['current']['key'] ?? null, $plan['stat4CurrentNext'] ?? []),
                'outputColumns' => array_map(static fn (string $column): array => ['column' => $column, 'opcode' => 'Column', 'source' => $ready ? 'index' : 'table'], $neededColumns),
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
                'sorterOpen' => !$ready && $orderBy !== [],
                'tableLookupElided' => $ready,
                'tempSortElided' => $ready,
                'program' => $program,
            ];
        }

        private static function seekOpcode(array $plan): string
        {
            return ($plan['lowerInclusive'] ?? false) === true ? 'SeekGE' : 'SeekGT';
        }

        private static function stopOpcode(array $plan): string
        {
            return ($plan['upperInclusive'] ?? false) === true ? 'IdxGT' : 'IdxGE';
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function nextOpcode(array $orderBy): string
        {
            return strtoupper($orderBy[0]['direction'] ?? 'ASC') === 'DESC' ? 'Prev' : 'Next';
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function scanDirection(array $orderBy): string
        {
            return strtoupper($orderBy[0]['direction'] ?? 'ASC') === 'DESC' ? 'descending' : 'ascending';
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function flattenAndTerms(array $predicate): array
        {
            if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
                return [$predicate];
            }
            $terms = $predicate['terms'] ?? null;
            if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
                throw new \InvalidArgumentException('SQLite covering range order predicate needs non-empty AND terms');
            }
            $flattened = [];
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite covering range order predicate terms must be arrays');
                }
                array_push($flattened, ...self::flattenAndTerms($term));
            }

            return $flattened;
        }

        private static function columnOperand(mixed $operand): ?string
        {
            if (is_array($operand) && isset($operand['column']) && is_string($operand['column']) && $operand['column'] !== '') {
                return $operand['column'];
            }

            return null;
        }

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
                'covering' => ($plan['covering'] ?? false) === true,
                'orderBySatisfied' => ($plan['orderBySatisfied'] ?? false) === true,
                'rangeColumn' => $plan['rangeColumn'] ?? null,
                'estimatedRows' => $plan['estimatedRows'] ?? null,
                'estimatedCost' => $plan['estimatedCost'] ?? null,
            ];
        }

        private static function indexSignature(array $source): string
        {
            $parts = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite covering range order source indexes must be arrays');
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
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderSignature(array $orderBy): string
        {
            return implode(', ', array_map(static fn (array $term): string => $term['column'] . ' ' . strtoupper($term['direction'] ?? 'ASC'), $orderBy));
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function validateOrderBy(array $orderBy): void
        {
            foreach ($orderBy as $term) {
                if (!isset($term['column']) || !is_string($term['column']) || $term['column'] === '') {
                    throw new \InvalidArgumentException('SQLite covering range order ORDER BY terms need column names');
                }
                $direction = strtoupper($term['direction'] ?? 'ASC');
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite covering range order ORDER BY direction must be ASC or DESC');
                }
            }
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function validateNeededColumns(array $neededColumns): void
        {
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite covering range order output columns must be non-empty strings');
                }
            }
        }

        /**
         * @return list<mixed>
         */
        private static function listValue(array $data, string $key, bool $required = true): array
        {
            $value = $data[$key] ?? [];
            if (!is_array($value) || !array_is_list($value)) {
                if ($required) {
                    throw new \InvalidArgumentException('SQLite covering range order ' . $key . ' must be a list');
                }

                return [];
            }

            return $value;
        }

        private static function stringValue(array $data, string $key, ?string $default = null): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite covering range order ' . $key . ' must be a non-empty string');
            }

            return $value;
        }

        private static function nonNegativeInt(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite covering range order ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

        private static function indexName(string $sql): string
        {
            if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $matches) === 1) {
                return (string) ($matches[1] ?: ($matches[2] ?: ($matches[3] ?: $matches[4])));
            }

            return 'index';
        }

}
