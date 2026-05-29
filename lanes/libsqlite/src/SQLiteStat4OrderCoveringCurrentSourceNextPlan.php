<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4OrderCoveringCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteStat4OrderCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function compareCurrentSourcePlan(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns): array
        {
            $prepared = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
            $current = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);

            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedProjection = self::projectionSignature($preparedSource, $neededColumns);
            $currentProjection = self::projectionSignature($currentSource, $neededColumns);
            $preparedIndexes = self::indexSignature($preparedSource);
            $currentIndexes = self::indexSignature($currentSource);
            $orderSignature = self::orderSignature($orderBy);

            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedProjection !== $currentProjection
                || $preparedIndexes !== $currentIndexes;
            $selected = $stale ? $current : $prepared;

            return [
                'status' => $selected['status'],
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'projectionChanged' => $preparedProjection !== $currentProjection,
                'indexSignatureChanged' => $preparedIndexes !== $currentIndexes,
                'orderSignature' => $orderSignature,
                'coveringChanged' => (bool) ($prepared['covering'] ?? false) !== (bool) ($current['covering'] ?? false),
                'orderByModeChanged' => ($prepared['orderByMode'] ?? null) !== ($current['orderByMode'] ?? null),
                'stat4EstimateDelta' => (int) ($current['stat4Estimate'] ?? 0) - (int) ($prepared['stat4Estimate'] ?? 0),
                'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
                'estimatedCostDelta' => (int) ($current['estimatedCost'] ?? 0) - (int) ($prepared['estimatedCost'] ?? 0),
                'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedProjection),
                'currentSource' => self::sourceSummary($currentSource, $current, $currentProjection),
                'selectedPlan' => $selected,
                'coveringOrderPlan' => self::isCoveringOrderPlan($selected),
                'tableLookupElided' => self::isCoveringOrderPlan($selected) && ($selected['deferredTableLookup'] ?? true) === false,
                'tempSortElided' => self::isCoveringOrderPlan($selected) && ($selected['blockSortRequired'] ?? true) === false,
                'detail' => self::detail($stale, $selected, $currentSource),
                'dependencies' => [
                    'SQLitePartialIndexOrderCurrentSourcePlan',
                    'SQLiteMultiColumnRangePlan',
                    'SQLiteIndexPredicate',
                    'sqlite-stat4-order-covering-current-source',
                ],
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
            return SQLitePartialIndexOrderCurrentSourcePlan::plan(
                self::listValue($source, 'indexes'),
                $predicate,
                $orderBy,
                $neededColumns,
            );
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummary(array $source, array $plan, string $projectionSignature): array
        {
            return [
                'name' => self::stringValue($source, 'name', 'source'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'projectionSignature' => $projectionSignature,
                'indexSignature' => self::indexSignature($source),
                'status' => $plan['status'] ?? 'unusable',
                'selectedIndex' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'covering' => (bool) ($plan['covering'] ?? false),
                'partialPredicateImplied' => (bool) ($plan['partialPredicateImplied'] ?? false),
                'orderBySatisfied' => (bool) ($plan['orderBySatisfied'] ?? false),
                'orderByMode' => $plan['orderByMode'] ?? 'none',
                'blockSortRequired' => (bool) ($plan['blockSortRequired'] ?? false),
                'deferredTableLookup' => (bool) ($plan['deferredTableLookup'] ?? true),
                'nextSource' => $plan['nextSource'] ?? null,
                'estimatedRows' => $plan['estimatedRows'] ?? 0,
                'estimatedCost' => $plan['estimatedCost'] ?? 0,
                'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
                'stat4Estimate' => $plan['stat4Estimate'] ?? null,
                'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
                'stat4RangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed> $plan
         */
        private static function isCoveringOrderPlan(array $plan): bool
        {
            return ($plan['status'] ?? null) === 'usable'
                && ($plan['covering'] ?? false) === true
                && ($plan['orderBySatisfied'] ?? false) === true;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $currentSource
         */
        private static function detail(bool $stale, array $plan, array $currentSource): string
        {
            $action = $stale ? 'REPREPARE STAT4 ORDER COVERING USING CURRENT SOURCE ' : 'REUSE PREPARED STAT4 ORDER COVERING ';
            $detail = $action . self::stringValue($currentSource, 'name', 'current') . ' ' . (string) ($plan['detail'] ?? 'NO PLAN');
            if (self::isCoveringOrderPlan($plan)) {
                $detail .= ' COVERING ORDER CURRENT SOURCE';
            }

            return $detail;
        }

        /**
         * @param array<string,mixed> $source
         * @param list<string> $neededColumns
         */
        private static function projectionSignature(array $source, array $neededColumns): string
        {
            $columns = self::stringList($source['coveringColumns'] ?? $neededColumns, 'coveringColumns');
            sort($columns, SORT_STRING);

            return implode("\0", $columns);
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignature(array $source): string
        {
            $parts = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                $name = isset($index['name']) && is_string($index['name']) ? $index['name'] : '';
                $rootPage = isset($index['rootPage']) && is_int($index['rootPage']) ? (string) $index['rootPage'] : '';
                $sql = isset($index['sql']) && is_string($index['sql']) ? preg_replace('/\s+/', ' ', trim($index['sql'])) : '';
                $stat4 = $index['stat4Samples'] ?? [];
                $parts[] = $name . '|' . $rootPage . '|' . $sql . '|' . hash('sha256', serialize($stat4));
            }
            sort($parts, SORT_STRING);

            return hash('sha256', implode("\n", $parts));
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderSignature(array $orderBy): string
        {
            $parts = [];
            foreach ($orderBy as $term) {
                $column = $term['column'] ?? null;
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 order-covering current-source planner needs ORDER BY columns');
                }
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite STAT4 order-covering current-source planner ORDER BY direction must be ASC or DESC');
                }
                $parts[] = strtolower($column) . ' ' . $direction;
            }

            return implode(',', $parts);
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValue(array $data, string $key, ?string $default = null): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 order-covering current-source planner needs {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function nonNegativeInt(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 order-covering current-source planner needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         * @return list<array<string,mixed>>
         */
        private static function listValue(array $data, string $key): array
        {
            $value = $data[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 order-covering current-source planner needs list {$key}");
            }

            return $value;
        }

        /**
         * @return list<string>
         */
        private static function stringList(mixed $value, string $key): array
        {
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 order-covering current-source planner needs list {$key}");
            }
            $strings = [];
            foreach ($value as $item) {
                if (!is_string($item) || $item === '') {
                    throw new \InvalidArgumentException("SQLite STAT4 order-covering current-source planner needs string {$key} values");
                }
                $strings[] = $item;
            }

            return $strings;
        }

    /* Variant formerly implemented by SQLiteStat4OrderCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeCoveringOrderCursorTape(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns): array
        {
            $comparison = SQLiteStat4OrderCoveringCurrentSourceNextPlan::compareCurrentSourcePlan(
                $preparedSource,
                $currentSource,
                $predicate,
                $orderBy,
                $neededColumns,
            );
            $plan = is_array($comparison['selectedPlan'] ?? null) ? $comparison['selectedPlan'] : [];
            $coveringOrder = ($comparison['coveringOrderPlan'] ?? false) === true;
            $range = is_array($plan['rangeConstraint'] ?? null) ? $plan['rangeConstraint'] : null;
            $rangeCurrentNext = is_array($plan['stat4RangeCurrentNext'] ?? null) ? $plan['stat4RangeCurrentNext'] : null;
            $segments = self::cursorSegments($plan);

            return array_merge($comparison, [
                'status' => $coveringOrder ? 'covering-order-current-source-ready' : 'requires-next-stage',
                'cursorTape' => [
                    'source' => $comparison['selectedSource'],
                    'indexName' => $plan['name'] ?? null,
                    'rootPage' => $plan['rootPage'] ?? null,
                    'seekOpcode' => self::seekOpcode($range),
                    'stopOpcode' => self::stopOpcode($range),
                    'nextOpcode' => self::advanceOpcode($orderBy),
                    'scanDirection' => self::scanDirection($orderBy),
                    'rangeColumn' => $plan['rangeColumn'] ?? null,
                    'rangeLower' => self::rangeBoundaryValue($rangeCurrentNext, 'lower'),
                    'rangeUpper' => self::rangeBoundaryValue($rangeCurrentNext, 'upper'),
                    'rangeLowerExact' => self::rangeBoundaryExact($rangeCurrentNext, 'lower'),
                    'rangeUpperExact' => self::rangeBoundaryExact($rangeCurrentNext, 'upper'),
                    'countedSegments' => $segments,
                    'countedCount' => count($segments),
                    'matchedKeys' => array_map(static fn (array $segment): mixed => $segment['currentKey'], $segments),
                    'outputColumns' => self::outputColumns($neededColumns),
                    'deferredSeekOpcode' => $coveringOrder ? null : 'DeferredSeek',
                    'sorterOpen' => !$coveringOrder && ($plan['blockSortRequired'] ?? false) === true,
                    'tableLookupElided' => $coveringOrder,
                    'tempSortElided' => $coveringOrder,
                    'program' => self::cursorProgram($coveringOrder, $plan, $range, $orderBy, $neededColumns),
                ],
                'currentSourceFence' => [
                    'schemaCookie' => $comparison['currentSource']['schemaCookie'] ?? null,
                    'stat4Generation' => $comparison['currentSource']['stat4Generation'] ?? null,
                    'indexSignature' => $comparison['currentSource']['indexSignature'] ?? null,
                    'projectionSignature' => $comparison['currentSource']['projectionSignature'] ?? null,
                    'orderSignature' => $comparison['orderSignature'] ?? '',
                ],
                'dependency_closure' => 'no new support component needed; cursor-tape composes accepted STAT4 current-source planning into native covering ORDER BY cursor tape diagnostics',
                'non_overlap' => 'avoids batch94 plan invalidation-only coverage by asserting current/next cursor tape materialization and VDBE-style sorter/table-lookup elision for the selected covering ordered index',
            ]);
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array{position:int,currentKey:mixed,nextKey:mixed,neq:int|null,nlt:int|null,ndlt:int|null,advance:string}>
         */
        private static function cursorSegments(array $plan): array
        {
            $pairs = $plan['stat4MatchedCurrentNext'] ?? [];
            if (!is_array($pairs) || !array_is_list($pairs)) {
                return [];
            }

            $segments = [];
            foreach ($pairs as $offset => $pair) {
                if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                    continue;
                }
                $current = $pair['current'];
                $next = is_array($pair['next'] ?? null) ? $pair['next'] : null;
                $segments[] = [
                    'position' => $offset,
                    'currentKey' => $current['key'] ?? null,
                    'nextKey' => $next['key'] ?? null,
                    'neq' => isset($current['neq']) && is_int($current['neq']) ? $current['neq'] : null,
                    'nlt' => isset($current['nlt']) && is_int($current['nlt']) ? $current['nlt'] : null,
                    'ndlt' => isset($current['ndlt']) && is_int($current['ndlt']) ? $current['ndlt'] : null,
                    'advance' => $next === null ? 'eof' : 'next',
                ];
            }

            return $segments;
        }

        /**
         * @param array<string,mixed>|null $range
         */
        private static function seekOpcode(?array $range): string
        {
            $operator = is_array($range) ? (string) ($range['operator'] ?? '') : '';
            if ($operator === 'range->') {
                return 'SeekGT';
            }

            return 'SeekGE';
        }

        /**
         * @param array<string,mixed>|null $range
         */
        private static function stopOpcode(?array $range): string
        {
            $operator = is_array($range) ? (string) ($range['operator'] ?? '') : '';
            if ($operator === 'range-<=' || $operator === 'BETWEEN') {
                return 'IdxGT';
            }
            if ($operator === 'range-bounded' && is_array($range['values'] ?? null) && (($range['values']['upperInclusive'] ?? false) === true)) {
                return 'IdxGT';
            }

            return 'IdxGE';
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function advanceOpcode(array $orderBy): string
        {
            return self::scanDirection($orderBy) === 'descending' ? 'Prev' : 'Next';
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function scanDirection(array $orderBy): string
        {
            if ($orderBy !== [] && strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC') {
                return 'descending';
            }

            return 'ascending';
        }

        /**
         * @param array<string,mixed>|null $rangeCurrentNext
         */
        private static function rangeBoundaryValue(?array $rangeCurrentNext, string $side): mixed
        {
            $boundary = is_array($rangeCurrentNext[$side] ?? null) ? $rangeCurrentNext[$side] : null;
            if ($boundary === null) {
                return null;
            }

            return $boundary['value'] ?? null;
        }

        /**
         * @param array<string,mixed>|null $rangeCurrentNext
         */
        private static function rangeBoundaryExact(?array $rangeCurrentNext, string $side): bool
        {
            $boundary = is_array($rangeCurrentNext[$side] ?? null) ? $rangeCurrentNext[$side] : null;

            return $boundary !== null && ($boundary['exact'] ?? false) === true;
        }

        /**
         * @param list<string> $neededColumns
         * @return list<array{column:string,opcode:string}>
         */
        private static function outputColumns(array $neededColumns): array
        {
            $columns = [];
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 order-covering current-source cursor-tape needs output column names');
                }
                $columns[] = ['column' => $column, 'opcode' => 'Column'];
            }

            return $columns;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed>|null $range
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function cursorProgram(bool $coveringOrder, array $plan, ?array $range, array $orderBy, array $neededColumns): array
        {
            $program = [
                ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => self::seekOpcode($range), 'column' => $plan['rangeColumn'] ?? null],
                ['opcode' => self::stopOpcode($range), 'column' => $plan['rangeColumn'] ?? null],
            ];
            if (!$coveringOrder) {
                $program[] = ['opcode' => 'DeferredSeek', 'target' => 'table'];
            }
            if (!$coveringOrder && ($plan['blockSortRequired'] ?? false) === true) {
                $program[] = ['opcode' => 'SorterOpen', 'orderBy' => $orderBy];
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 order-covering current-source cursor-tape needs output column names');
                }
                $program[] = ['opcode' => 'Column', 'source' => $coveringOrder ? 'index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => self::advanceOpcode($orderBy), 'target' => 'index'];

            return $program;
        }

}
