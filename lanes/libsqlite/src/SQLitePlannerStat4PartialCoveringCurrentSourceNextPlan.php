<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext135(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns
        ): array {
            self::validateNeededColumnsNext135($neededColumns);

            $comparison = SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
                $preparedSource,
                $currentSource,
                $predicate,
                $orderBy,
                $neededColumns,
            );
            $selectedSource = (string) $comparison['selectedSource'];
            $source = $selectedSource === 'current' ? $currentSource : $preparedSource;
            $selectedPlan = is_array($comparison['selectedPlan'] ?? null) ? $comparison['selectedPlan'] : [];
            $rows = self::coveredRowsNext135($source, $selectedPlan, $predicate, $neededColumns);
            $ready = ($comparison['status'] ?? null) === 'usable'
                && ($selectedPlan['partialPredicateImplied'] ?? false) === true
                && ($selectedPlan['covering'] ?? false) === true
                && ($selectedPlan['stat4Used'] ?? false) === true
                && $rows !== [];

            return array_replace($comparison, [
                'status' => $ready ? 'stat4-partial-covering-current-source-next135-ready' : 'requires-next-stage',
                'selectedPlan' => $selectedPlan + [
                    'coveredRowCount' => count($rows),
                    'stat4AnchorKeys' => self::stat4AnchorKeysNext135($selectedPlan),
                    'rangeLower' => self::rangeBoundsNext135($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['lower'],
                    'rangeUpper' => self::rangeBoundsNext135($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['upper'],
                    'lowerInclusive' => self::rangeBoundsNext135($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['lowerInclusive'],
                    'upperInclusive' => self::rangeBoundsNext135($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['upperInclusive'],
                    'cursorProgram' => self::cursorProgramNext135($selectedPlan, $predicate, $neededColumns),
                ],
                'coveredRows' => $rows,
                'tableLookupElided' => $ready,
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
                'currentNextRows' => self::currentNextRowsNext135($rows),
                'currentSourceFence' => [
                    'schemaCookie' => self::nonNegativeIntNext135($currentSource, 'schemaCookie'),
                    'stat4Generation' => self::nonNegativeIntNext135($currentSource, 'stat4Generation'),
                    'indexSignature' => (string) ($comparison['currentSource']['indexSignature'] ?? ''),
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                    'orderSignature' => self::orderSignatureNext135($orderBy),
                    'coveringSignature' => implode(',', $neededColumns),
                    'rowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
                ],
                'detail' => ((bool) ($comparison['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' STAT4 PARTIAL COVERING CURRENT SOURCE ROW STREAM '
                    . self::stringValueNext135($source, 'name')
                    . ' ' . (string) ($selectedPlan['detail'] ?? 'NO PLAN'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($comparison['dependencies'] ?? null) ? $comparison['dependencies'] : [],
                    [
                        'SQLiteStat4PartialCoveringCurrentSourcePlan',
                        'sqlite-sqlplanner-stat4-partial-covering-current-source-next135',
                    ],
                ))),
                'dependency_closure' => 'no new support component needed; next135 composes native STAT4 partial-covering source fences with lane-local current-row stream materialization',
                'non_overlap' => 'avoids next131 ordinary partial range row streams, next124 partial range deltas, next125/next127 skip-scan covering, next129 partial expression skip-scan, and next132 expression covering skip-scan; this slice covers STAT4 partial-covering current-source row stream admission',
            ]);
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function coveredRowsNext135(array $source, array $plan, array $predicate, array $neededColumns): array
        {
            if (
                ($plan['partialPredicateImplied'] ?? false) !== true
                || ($plan['covering'] ?? false) !== true
                || ($plan['stat4Used'] ?? false) !== true
            ) {
                return [];
            }

            $rows = self::listValueNext135($source, 'rows');
            $rangeColumn = (string) ($plan['rangeColumn'] ?? '');
            if ($rangeColumn === '') {
                return [];
            }
            $bounds = self::rangeBoundsNext135($predicate, $rangeColumn);
            $anchors = array_fill_keys(array_map([self::class, 'keySignatureNext135'], self::stat4AnchorKeysNext135($plan)), true);
            $covered = [];

            foreach ($rows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source rows must be arrays');
                }
                if (!self::rowSatisfiesPointTermsNext135($row, $predicate) || !self::rowSatisfiesEqualitiesNext135($row, $plan)) {
                    continue;
                }
                if (!array_key_exists($rangeColumn, $row) || !self::valueInRangeNext135($row[$rangeColumn], $bounds)) {
                    continue;
                }

                $payload = [];
                foreach ($neededColumns as $column) {
                    $payload[$column] = $row[$column] ?? null;
                }
                $covered[] = [
                    'sourceOffset' => $offset,
                    'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                    'rangeKey' => $row[$rangeColumn],
                    'stat4Anchor' => isset($anchors[self::keySignatureNext135($row[$rangeColumn])]),
                    'covering' => $payload,
                ];
            }

            usort($covered, static function (array $left, array $right): int {
                $comparison = self::compareValuesNext135($left['rangeKey'], $right['rangeKey']);
                if ($comparison !== 0) {
                    return $comparison;
                }

                return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
            });

            return $covered;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
         */
        private static function currentNextRowsNext135(array $rows): array
        {
            $pairs = [];
            foreach ($rows as $offset => $row) {
                $pairs[] = [
                    'current' => $row,
                    'next' => $rows[$offset + 1] ?? null,
                ];
            }

            return $pairs;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<mixed>
         */
        private static function stat4AnchorKeysNext135(array $plan): array
        {
            $keys = [];
            foreach (($plan['stat4MatchedCurrentNext'] ?? []) as $pair) {
                if (!is_array($pair) || !isset($pair['current']) || !is_array($pair['current']) || !array_key_exists('key', $pair['current'])) {
                    continue;
                }
                $keys[] = $pair['current']['key'];
            }

            return $keys;
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $plan
         */
        private static function rowSatisfiesEqualitiesNext135(array $row, array $plan): bool
        {
            $constraints = $plan['equalityConstraints'] ?? [];
            if (!is_array($constraints)) {
                return false;
            }

            foreach ($constraints as $constraint) {
                if (!is_array($constraint)) {
                    return false;
                }
                $column = $constraint['column'] ?? null;
                if (!is_string($column) || !array_key_exists($column, $row)) {
                    return false;
                }
                $operator = strtoupper((string) ($constraint['operator'] ?? ''));
                $values = $constraint['values'] ?? null;
                if ($operator === 'POINT' && self::compareValuesNext135($row[$column], $values) !== 0) {
                    return false;
                }
                if ($operator === 'IN') {
                    if (!is_array($values)) {
                        return false;
                    }
                    $matched = false;
                    foreach ($values as $value) {
                        if (self::compareValuesNext135($row[$column], $value) === 0) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        return false;
                    }
                }
            }

            return true;
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $predicate
         */
        private static function rowSatisfiesPointTermsNext135(array $row, array $predicate): bool
        {
            foreach (self::flattenAndTermsNext135($predicate) as $term) {
                $operator = strtoupper((string) ($term['operator'] ?? ''));
                if (!in_array($operator, ['=', '==', 'IS'], true)) {
                    continue;
                }
                $left = $term['left'] ?? null;
                if (!is_array($left)) {
                    continue;
                }
                $column = $left['column'] ?? null;
                if (!is_string($column) || !array_key_exists($column, $row)) {
                    return false;
                }
                if (self::compareValuesNext135($row[$column], $term['right'] ?? null) !== 0) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param array<string,mixed> $predicate
         * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}
         */
        private static function rangeBoundsNext135(array $predicate, string $column): array
        {
            $lower = null;
            $upper = null;
            $lowerInclusive = false;
            $upperInclusive = false;

            foreach (self::flattenAndTermsNext135($predicate) as $term) {
                $left = $term['left'] ?? null;
                if (!is_array($left) || strcasecmp((string) ($left['column'] ?? ''), $column) !== 0) {
                    continue;
                }
                $operator = strtoupper((string) ($term['operator'] ?? ''));
                if ($operator === 'BETWEEN') {
                    return [
                        'lower' => $term['lower'] ?? null,
                        'upper' => $term['upper'] ?? null,
                        'lowerInclusive' => true,
                        'upperInclusive' => true,
                    ];
                }
                if ($operator === '>' || $operator === '>=') {
                    $candidate = $term['right'] ?? null;
                    if ($lower === null || self::compareValuesNext135($candidate, $lower) > 0) {
                        $lower = $candidate;
                        $lowerInclusive = $operator === '>=';
                    }
                    continue;
                }
                if ($operator === '<' || $operator === '<=') {
                    $candidate = $term['right'] ?? null;
                    if ($upper === null || self::compareValuesNext135($candidate, $upper) < 0) {
                        $upper = $candidate;
                        $upperInclusive = $operator === '<=';
                    }
                }
            }

            return [
                'lower' => $lower,
                'upper' => $upper,
                'lowerInclusive' => $lowerInclusive,
                'upperInclusive' => $upperInclusive,
            ];
        }

        /**
         * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $bounds
         */
        private static function valueInRangeNext135(mixed $value, array $bounds): bool
        {
            if ($bounds['lower'] !== null) {
                $comparison = self::compareValuesNext135($value, $bounds['lower']);
                if ($comparison < 0 || ($comparison === 0 && !$bounds['lowerInclusive'])) {
                    return false;
                }
            }
            if ($bounds['upper'] !== null) {
                $comparison = self::compareValuesNext135($value, $bounds['upper']);
                if ($comparison > 0 || ($comparison === 0 && !$bounds['upperInclusive'])) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function cursorProgramNext135(array $plan, array $predicate, array $neededColumns): array
        {
            if (($plan['usable'] ?? false) !== true) {
                return [['opcode' => 'Rewind', 'source' => 'table']];
            }

            $rangeColumn = (string) ($plan['rangeColumn'] ?? '');
            $bounds = self::rangeBoundsNext135($predicate, $rangeColumn);
            $program = [[
                'opcode' => 'OpenRead',
                'target' => 'index',
                'index' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
            ]];
            $program[] = [
                'opcode' => $bounds['lowerInclusive'] ? 'SeekGE' : 'SeekGT',
                'column' => $rangeColumn,
                'value' => $bounds['lower'],
            ];
            $program[] = [
                'opcode' => $bounds['upperInclusive'] ? 'IdxGT' : 'IdxGE',
                'column' => $rangeColumn,
                'value' => $bounds['upper'],
            ];
            foreach ($neededColumns as $column) {
                $program[] = [
                    'opcode' => 'Column',
                    'source' => 'index',
                    'column' => $column,
                ];
            }
            $program[] = ['opcode' => 'Next', 'target' => 'index'];

            return $program;
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function flattenAndTermsNext135(array $predicate): array
        {
            if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
                return [$predicate];
            }

            $terms = $predicate['terms'] ?? null;
            if (!is_array($terms) || !array_is_list($terms)) {
                return [$predicate];
            }

            $flat = [];
            foreach ($terms as $term) {
                if (is_array($term)) {
                    array_push($flat, ...self::flattenAndTermsNext135($term));
                }
            }

            return $flat;
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderSignatureNext135(array $orderBy): string
        {
            if ($orderBy === []) {
                return 'rowid ASC';
            }

            return implode(', ', array_map(static function (array $term): string {
                $column = $term['column'] ?? null;
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 ORDER BY terms need columns');
                }
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 ORDER BY direction must be ASC or DESC');
                }

                return $column . ' ' . $direction;
            }, $orderBy));
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function listValueNext135(array $source, string $key): array
        {
            $value = $source[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs list ' . $key);
            }

            return $value;
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function validateNeededColumnsNext135(array $neededColumns): void
        {
            if ($neededColumns === []) {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs at least one covering column');
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 covering columns must be names');
                }
            }
        }

        private static function nonNegativeIntNext135(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs non-negative integer ' . $key);
            }

            return $value;
        }

        private static function stringValueNext135(array $source, string $key): string
        {
            $value = $source[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs string ' . $key);
            }

            return $value;
        }

        private static function compareValuesNext135(mixed $left, mixed $right): int
        {
            if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
                return ((float) $left) <=> ((float) $right);
            }

            return strcmp((string) $left, (string) $right);
        }

        private static function keySignatureNext135(mixed $value): string
        {
            return get_debug_type($value) . ':' . serialize($value);
        }

    /* Variant formerly implemented by SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext142(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns
        ): array {
            self::validateOrderByNext142($orderBy);

            $base = SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan::materializeNext135(
                $preparedSource,
                $currentSource,
                $predicate,
                $orderBy,
                $neededColumns,
            );
            $rows = self::orderedRowsNext142(
                is_array($base['coveredRows'] ?? null) ? $base['coveredRows'] : [],
                $orderBy,
            );
            $ready = ($base['status'] ?? null) === 'stat4-partial-covering-current-source-next135-ready'
                && $rows !== []
                && self::selectedPlanArrayNext142($base)['usesSkipScan'] !== true;
            $blockSort = $ready && self::requiresRightPartSortNext142(self::selectedPlanArrayNext142($base), $orderBy);
            $blocks = self::stat4BlocksNext142($rows, $orderBy);
            $selectedPlan = array_replace(self::selectedPlanArrayNext142($base), [
                'next142Ready' => $ready,
                'next142OrderedRowCount' => count($rows),
                'next142OrderedRowids' => array_column($rows, 'rowid'),
                'next142OrderByMode' => self::orderModeNext142(self::selectedPlanArrayNext142($base), $orderBy, $blockSort),
                'next142BlockSortRequired' => $blockSort,
                'next142Stat4BlockCount' => count($blocks),
                'next142Stat4BlockKeys' => array_column($blocks, 'key'),
                'next142CursorProgram' => self::cursorProgramNext142(self::selectedPlanArrayNext142($base), $neededColumns, $blockSort),
                'nextSource' => $ready ? 'partial-covering-stat4-current-next142' : 'table-rowid-lookup',
            ]);

            return array_replace($base, [
                'status' => $ready ? 'stat4-partial-covering-current-source-next142-ready' : 'requires-next-stage',
                'coveredRows' => $rows,
                'currentNextRows' => self::currentNextRowsNext142($rows),
                'stat4AnchorBlocks' => $blocks,
                'stat4AnchorBlockCount' => count($blocks),
                'tempBtreeForRightPartOrderBy' => $blockSort,
                'tableLookupElided' => $ready,
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
                'selectedPlan' => $selectedPlan,
                'currentSourceFence' => array_replace(
                    is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                    [
                        'next142RowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
                        'next142OrderSignature' => self::orderSignatureNext142($orderBy),
                        'next142Stat4BlockSignature' => hash('sha256', json_encode(array_column($blocks, 'key'), JSON_THROW_ON_ERROR)),
                    ],
                ),
                'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' STAT4 PARTIAL COVERING CURRENT SOURCE NEXT142 '
                    . (string) ($selectedPlan['name'] ?? 'NO INDEX')
                    . ($blockSort ? ' USE TEMP B-TREE FOR RIGHT PART OF ORDER BY' : ' ORDERED BY COVERING INDEX'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                    [
                        'SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan',
                        'sqlite-sqlplanner-stat4-partial-covering-current-source-next142',
                    ],
                ))),
                'dependency_closure' => 'no new support component needed; next142 reuses native partial-covering STAT4 current-source row streams and adds lane-local ORDER/current-next block materialization',
                'non_overlap' => 'avoids accepted next135 row-stream admission, next138 non-partial STAT4 ranges, expression ORDER BY, expression-index range costs, skip-scan, JSON table, WAL, VFS, and B-tree clusters; this slice covers partial-covering STAT4 current/next ORDER block materialization',
            ]);
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @param list<array{column:string,direction?:string}> $orderBy
         * @return list<array<string,mixed>>
         */
        private static function orderedRowsNext142(array $rows, array $orderBy): array
        {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next142 rows must be arrays');
                }
            }

            if ($orderBy === []) {
                return $rows;
            }

            usort($rows, static function (array $left, array $right) use ($orderBy): int {
                foreach ($orderBy as $term) {
                    $column = $term['column'];
                    $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                    $comparison = self::rowColumnCompareNext142($left, $right, $column);
                    if ($comparison === 0) {
                        continue;
                    }

                    return $direction === 'DESC' ? -$comparison : $comparison;
                }

                return ((int) ($left['rowid'] ?? $left['sourceOffset'] ?? 0)) <=> ((int) ($right['rowid'] ?? $right['sourceOffset'] ?? 0));
            });

            return $rows;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
         */
        private static function currentNextRowsNext142(array $rows): array
        {
            $pairs = [];
            foreach ($rows as $offset => $row) {
                $pairs[] = [
                    'current' => $row,
                    'next' => $rows[$offset + 1] ?? null,
                ];
            }

            return $pairs;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @param list<array{column:string,direction?:string}> $orderBy
         * @return list<array{key:mixed,rowids:list<mixed>,anchorCount:int,firstRowid:mixed,lastRowid:mixed,nextKey:mixed}>
         */
        private static function stat4BlocksNext142(array $rows, array $orderBy): array
        {
            $column = $orderBy[0]['column'] ?? 'rangeKey';
            $blocks = [];
            foreach ($rows as $row) {
                $key = self::rowColumnValueNext142($row, $column);
                $signature = self::keySignatureNext142($key);
                if (!isset($blocks[$signature])) {
                    $blocks[$signature] = [
                        'key' => $key,
                        'rowids' => [],
                        'anchorCount' => 0,
                        'firstRowid' => $row['rowid'] ?? null,
                        'lastRowid' => $row['rowid'] ?? null,
                        'nextKey' => null,
                    ];
                }
                $blocks[$signature]['rowids'][] = $row['rowid'] ?? null;
                $blocks[$signature]['lastRowid'] = $row['rowid'] ?? null;
                if (($row['stat4Anchor'] ?? false) === true) {
                    ++$blocks[$signature]['anchorCount'];
                }
            }

            $ordered = array_values($blocks);
            foreach ($ordered as $offset => $block) {
                $ordered[$offset]['nextKey'] = $ordered[$offset + 1]['key'] ?? null;
            }

            return $ordered;
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function requiresRightPartSortNext142(array $plan, array $orderBy): bool
        {
            if ($orderBy === []) {
                return false;
            }
            if (($plan['orderBySatisfied'] ?? false) === true) {
                return false;
            }

            return ($plan['partialPredicateImplied'] ?? false) === true
                && ($plan['covering'] ?? false) === true;
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderModeNext142(array $plan, array $orderBy, bool $blockSort): string
        {
            if ($orderBy === []) {
                return 'rowid-current-next';
            }
            if (($plan['orderBySatisfied'] ?? false) === true) {
                return 'covering-index-order';
            }
            if ($blockSort) {
                return 'partial-covering-right-part-sort';
            }

            return 'external-sort';
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function cursorProgramNext142(array $plan, array $neededColumns, bool $blockSort): array
        {
            if (($plan['usable'] ?? false) !== true) {
                return [['opcode' => 'Rewind', 'source' => 'table']];
            }

            $program = [[
                'opcode' => 'OpenRead',
                'target' => 'index',
                'index' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
            ]];
            $program[] = [
                'opcode' => 'SeekStat4',
                'column' => $plan['rangeColumn'] ?? null,
                'anchors' => array_values(array_filter($plan['stat4AnchorKeys'] ?? [], static fn (mixed $key): bool => $key !== null)),
            ];
            foreach ($neededColumns as $column) {
                $program[] = [
                    'opcode' => 'Column',
                    'source' => 'covering-index',
                    'column' => $column,
                ];
            }
            if ($blockSort) {
                $program[] = ['opcode' => 'SorterInsert', 'source' => 'covering-index'];
                $program[] = ['opcode' => 'SorterNext', 'target' => 'right-part-order'];
            }
            $program[] = ['opcode' => 'Next', 'target' => 'index'];

            return $program;
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function validateOrderByNext142(array $orderBy): void
        {
            foreach ($orderBy as $term) {
                if (!isset($term['column']) || !is_string($term['column']) || $term['column'] === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next142 ORDER BY terms need columns');
                }
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next142 ORDER BY direction must be ASC or DESC');
                }
            }
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderSignatureNext142(array $orderBy): string
        {
            if ($orderBy === []) {
                return 'rowid ASC';
            }

            return implode(', ', array_map(static fn (array $term): string => $term['column'] . ' ' . strtoupper((string) ($term['direction'] ?? 'ASC')), $orderBy));
        }

        /**
         * @param array<string,mixed> $base
         * @return array<string,mixed>
         */
        private static function selectedPlanArrayNext142(array $base): array
        {
            return is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
        }

        /**
         * @param array<string,mixed> $left
         * @param array<string,mixed> $right
         */
        private static function rowColumnCompareNext142(array $left, array $right, string $column): int
        {
            return self::compareValuesNext142(self::rowColumnValueNext142($left, $column), self::rowColumnValueNext142($right, $column));
        }

        /**
         * @param array<string,mixed> $row
         */
        private static function rowColumnValueNext142(array $row, string $column): mixed
        {
            if ($column === 'rangeKey') {
                return $row['rangeKey'] ?? null;
            }
            if (array_key_exists($column, $row)) {
                return $row[$column];
            }
            $covering = $row['covering'] ?? [];
            if (is_array($covering) && array_key_exists($column, $covering)) {
                return $covering[$column];
            }
            if (array_key_exists('rangeKey', $row)) {
                return $row['rangeKey'];
            }

            return null;
        }

        private static function compareValuesNext142(mixed $left, mixed $right): int
        {
            if ($left === null && $right === null) {
                return 0;
            }
            if ($left === null) {
                return -1;
            }
            if ($right === null) {
                return 1;
            }
            if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
                return ((float) $left) <=> ((float) $right);
            }

            return strcmp((string) $left, (string) $right);
        }

        private static function keySignatureNext142(mixed $value): string
        {
            return get_debug_type($value) . ':' . serialize($value);
        }

}
