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
        public static function materializeNext119(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns): array
        {
            self::validateOrderByNext119($orderBy);
            self::validateNeededColumnsNext119($neededColumns);

            $prepared = self::sourcePlanNext119($preparedSource, $predicate, $orderBy, $neededColumns);
            $current = self::sourcePlanNext119($currentSource, $predicate, $orderBy, $neededColumns);

            $preparedCookie = self::nonNegativeIntNext119($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext119($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeIntNext119($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeIntNext119($currentSource, 'stat4Generation');
            $preparedSignature = self::indexSignatureNext119($preparedSource);
            $currentSignature = self::indexSignatureNext119($currentSource);
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
                'preparedSource' => self::sourceSummaryNext119($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::sourceSummaryNext119($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'coveringRangeOrderPlan' => $ready,
                'tableLookupElided' => $ready,
                'tempSortElided' => $ready,
                'cursorTape' => self::cursorTapeNext119($selected, $orderBy, $neededColumns, $ready, $stale ? 'current' : 'prepared'),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'orderSignature' => self::orderSignatureNext119($orderBy),
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' COVERING RANGE ORDER USING '
                    . ($stale ? self::stringValueNext119($currentSource, 'name') : self::stringValueNext119($preparedSource, 'name'))
                    . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependencies' => [
                    'SQLiteCreateIndex column/partial parsing',
                    'sqlite-planner-covering-range-order-current-source-next119',
                ],
                'dependency_closure' => 'no new support component needed; next119 composes native CREATE INDEX parsing, partial-predicate implication, STAT4 current/next samples, and covering range ORDER BY cursor diagnostics',
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
        private static function sourcePlanNext119(array $source, array $predicate, array $orderBy, array $neededColumns): array
        {
            $terms = self::flattenAndTermsNext119($predicate);
            $plans = [];
            foreach (self::listValueNext119($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite covering range order source indexes must be arrays');
                }
                $sql = self::stringValueNext119($index, 'sql');
                $columns = SQLiteCreateIndex::columns($sql);
                if ($columns === null || $columns === []) {
                    continue;
                }
                $partialImplied = self::partialPredicateImpliedNext119($columns[0]->partialPredicate, $terms);
                if (!$partialImplied) {
                    continue;
                }

                $prefix = self::usablePrefixNext119($columns, $terms);
                if ($prefix['rangeColumn'] === null) {
                    continue;
                }

                $covering = self::coversNext119($columns, $neededColumns);
                $orderSatisfied = self::orderBySatisfiedNext119($columns, $orderBy, $prefix['equalityPrefix'], $prefix['rangeColumn']);
                $samples = self::matchedSamplesNext119(self::listValueNext119($index, 'stat4Samples', false), $prefix['rangeLower'], $prefix['rangeUpper'], $prefix['lowerInclusive'], $prefix['upperInclusive'], $prefix['rangeColumn']);
                $estimatedRows = $samples === []
                    ? max(1, (int) ($index['estimatedRows'] ?? 1))
                    : max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $samples)));
                $cost = $estimatedRows + 32 - ($prefix['equalityPrefix'] * 8) - ($covering ? 32 : 0) - ($orderSatisfied ? 12 : 0) - ($columns[0]->partial ? 4 : 0);

                $plans[] = [
                    'usable' => true,
                    'name' => self::stringValueNext119($index, 'name', self::indexNameNext119($sql)),
                    'rootPage' => self::nonNegativeIntNext119($index, 'rootPage'),
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
                    'stat4CurrentNext' => self::currentNextNext119($samples),
                    'estimatedRows' => $estimatedRows,
                    'estimatedCost' => max(1, $cost),
                    'detail' => 'SEARCH ' . self::stringValueNext119($index, 'name', self::indexNameNext119($sql))
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
        private static function usablePrefixNext119(array $columns, array $terms): array
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
                $constraints = self::constraintsForColumnNext119($terms, $name);
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
                $column = self::columnOperandNext119($term['left'] ?? null);
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
        private static function orderBySatisfiedNext119(array $columns, array $orderBy, int $equalityPrefix, ?string $rangeColumn): bool
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
        private static function constraintsForColumnNext119(array $terms, string $column): array
        {
            $constraints = [];
            foreach ($terms as $term) {
                $left = self::columnOperandNext119($term['left'] ?? null);
                if ($left === null || strcasecmp($left, $column) !== 0) {
                    continue;
                }
                $operator = strtoupper(self::stringValueNext119($term, 'operator'));
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
        private static function coversNext119(array $columns, array $neededColumns): bool
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
        private static function partialPredicateImpliedNext119(?SQLiteIndexPredicate $partial, array $terms): bool
        {
            if ($partial === null) {
                return true;
            }
            foreach ($terms as $term) {
                $column = self::columnOperandNext119($term['left'] ?? null);
                if ($column === null) {
                    continue;
                }
                $operator = strtoupper(self::stringValueNext119($term, 'operator'));
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
        private static function matchedSamplesNext119(array $samples, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, string $rangeColumn): array
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
                if (!self::withinRangeNext119($key, $lower, $upper, $lowerInclusive, $upperInclusive)) {
                    continue;
                }
                $matched[] = [
                    'neq' => self::lastStatIntNext119($sample['neq'] ?? 1),
                    'nlt' => self::lastStatIntNext119($sample['nlt'] ?? 0),
                    'ndlt' => self::lastStatIntNext119($sample['ndlt'] ?? 0),
                    'sample' => array_values($values),
                    'rangeColumn' => $rangeColumn,
                ];
            }

            return $matched;
        }

        private static function withinRangeNext119(mixed $value, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive): bool
        {
            if ($lower !== null) {
                $comparison = self::compareNext119($value, $lower);
                if ($comparison < 0 || ($comparison === 0 && !$lowerInclusive)) {
                    return false;
                }
            }
            if ($upper !== null) {
                $comparison = self::compareNext119($value, $upper);
                if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                    return false;
                }
            }

            return true;
        }

        private static function compareNext119(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return (float) $left <=> (float) $right;
            }

            return strcmp((string) $left, (string) $right) <=> 0;
        }

        private static function lastStatIntNext119(mixed $value): int
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
        private static function currentNextNext119(array $samples): array
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
        private static function cursorTapeNext119(array $plan, array $orderBy, array $neededColumns, bool $ready, string $source): array
        {
            $program = [
                ['opcode' => 'OpenRead', 'source' => 'index', 'name' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => self::seekOpcodeNext119($plan), 'source' => 'index', 'column' => $plan['rangeColumn'] ?? null, 'key' => $plan['rangeLower'] ?? null],
                ['opcode' => self::stopOpcodeNext119($plan), 'source' => 'index', 'column' => $plan['rangeColumn'] ?? null, 'key' => $plan['rangeUpper'] ?? null],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column];
            }
            if (!$ready && ($plan['orderBySatisfied'] ?? false) !== true && $orderBy !== []) {
                $program[] = ['opcode' => 'SorterOpen', 'source' => 'temp-btree'];
            }
            $program[] = ['opcode' => self::nextOpcodeNext119($orderBy), 'source' => 'index'];

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'seekOpcode' => self::seekOpcodeNext119($plan),
                'stopOpcode' => self::stopOpcodeNext119($plan),
                'nextOpcode' => self::nextOpcodeNext119($orderBy),
                'scanDirection' => self::scanDirectionNext119($orderBy),
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

        private static function seekOpcodeNext119(array $plan): string
        {
            return ($plan['lowerInclusive'] ?? false) === true ? 'SeekGE' : 'SeekGT';
        }

        private static function stopOpcodeNext119(array $plan): string
        {
            return ($plan['upperInclusive'] ?? false) === true ? 'IdxGT' : 'IdxGE';
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function nextOpcodeNext119(array $orderBy): string
        {
            return strtoupper($orderBy[0]['direction'] ?? 'ASC') === 'DESC' ? 'Prev' : 'Next';
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function scanDirectionNext119(array $orderBy): string
        {
            return strtoupper($orderBy[0]['direction'] ?? 'ASC') === 'DESC' ? 'descending' : 'ascending';
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function flattenAndTermsNext119(array $predicate): array
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
                array_push($flattened, ...self::flattenAndTermsNext119($term));
            }

            return $flattened;
        }

        private static function columnOperandNext119(mixed $operand): ?string
        {
            if (is_array($operand) && isset($operand['column']) && is_string($operand['column']) && $operand['column'] !== '') {
                return $operand['column'];
            }

            return null;
        }

        private static function sourceSummaryNext119(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValueNext119($source, 'name'),
                'schemaCookie' => self::nonNegativeIntNext119($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext119($source, 'stat4Generation'),
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

        private static function indexSignatureNext119(array $source): string
        {
            $parts = [];
            foreach (self::listValueNext119($source, 'indexes') as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite covering range order source indexes must be arrays');
                }
                $parts[] = implode("\n", [
                    self::stringValueNext119($index, 'name', ''),
                    (string) self::nonNegativeIntNext119($index, 'rootPage'),
                    self::stringValueNext119($index, 'sql'),
                    json_encode($index['stat4Samples'] ?? [], JSON_THROW_ON_ERROR),
                ]);
            }

            return hash('sha256', implode("\n---\n", $parts));
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderSignatureNext119(array $orderBy): string
        {
            return implode(', ', array_map(static fn (array $term): string => $term['column'] . ' ' . strtoupper($term['direction'] ?? 'ASC'), $orderBy));
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function validateOrderByNext119(array $orderBy): void
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
        private static function validateNeededColumnsNext119(array $neededColumns): void
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
        private static function listValueNext119(array $data, string $key, bool $required = true): array
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

        private static function stringValueNext119(array $data, string $key, ?string $default = null): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite covering range order ' . $key . ' must be a non-empty string');
            }

            return $value;
        }

        private static function nonNegativeIntNext119(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite covering range order ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

        private static function indexNameNext119(string $sql): string
        {
            if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $matches) === 1) {
                return (string) ($matches[1] ?: ($matches[2] ?: ($matches[3] ?: $matches[4])));
            }

            return 'index';
        }

}
