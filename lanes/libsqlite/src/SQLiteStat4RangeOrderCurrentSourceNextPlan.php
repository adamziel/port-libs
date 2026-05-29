<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4RangeOrderCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteStat4RangeOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array{column:string,direction?:string}> $orderBy
         * @return array<string,mixed>
         */
        public static function compareNext97(array $preparedSource, array $currentSource, array $orderBy = []): array
        {
            $prepared = self::sourcePlanNext97($preparedSource, $orderBy);
            $current = self::sourcePlanNext97($currentSource, $orderBy);

            $preparedCookie = self::nonNegativeIntNext97($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext97($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeIntNext97($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeIntNext97($currentSource, 'stat4Generation');
            $preparedRange = self::rangeSignatureNext97($preparedSource);
            $currentRange = self::rangeSignatureNext97($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedRange !== $currentRange;
            $selected = $stale ? $current : $prepared;

            return [
                'status' => $selected['status'],
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'rangeChanged' => $preparedRange !== $currentRange,
                'orderByModeChanged' => ($prepared['orderByMode'] ?? null) !== ($current['orderByMode'] ?? null),
                'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
                'preparedSource' => self::sourceSummaryNext97($preparedSource, $prepared),
                'currentSource' => self::sourceSummaryNext97($currentSource, $current),
                'selectedPlan' => $selected,
                'detail' => self::detailNext97($stale, $selected, $currentSource),
                'dependencies' => [
                    'SQLiteStat4RangeOrderCurrentSourceNextPlan',
                    'SQLiteIndexPredicate',
                    'sqlite_stat4',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param list<array{column:string,direction?:string}> $orderBy
         * @return array<string,mixed>
         */
        private static function sourcePlanNext97(array $source, array $orderBy): array
        {
            $indexName = self::stringValueNext97($source, 'indexName');
            $rangeColumn = self::stringValueNext97($source, 'rangeColumn');
            $collation = strtoupper(self::stringValueNext97($source, 'collation', 'BINARY'));
            if (!in_array($collation, ['BINARY', 'NOCASE'], true)) {
                throw new \InvalidArgumentException('SQLite STAT4 range-order planner supports BINARY and NOCASE collations');
            }

            $rows = self::listValueNext97($source, 'rows');
            $lower = $source['lower'] ?? null;
            $upper = $source['upper'] ?? null;
            $upperInclusive = (bool) ($source['upperInclusive'] ?? true);
            $matchingRows = [];
            $omittedNullRangeRows = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order rows must be arrays');
                }
                if (!array_key_exists($rangeColumn, $row)) {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order rows need range column values');
                }
                if ($row[$rangeColumn] === null) {
                    ++$omittedNullRangeRows;
                    continue;
                }
                if (self::withinNext97($row[$rangeColumn], $lower, $upper, $upperInclusive, $collation)) {
                    $matchingRows[] = $row;
                }
            }

            $orderEvidence = self::orderEvidenceNext97($rangeColumn, $orderBy);
            usort(
                $matchingRows,
                static function (array $left, array $right) use ($rangeColumn, $collation, $orderEvidence): int {
                    $comparison = self::compareValuesNext97($left[$rangeColumn] ?? null, $right[$rangeColumn] ?? null, $collation);
                    if ($comparison === 0) {
                        $comparison = ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0));
                    }

                    return $orderEvidence['reverseScan'] ? -$comparison : $comparison;
                }
            );

            $samples = self::stat4SamplesNext97(self::listValueNext97($source, 'stat4Samples'));
            $currentNext = self::currentNextForRangeNext97($samples, $lower, $upper, $upperInclusive, $collation);
            $estimate = self::estimateRowsNext97($samples, $currentNext['rangeSamples'], count($matchingRows));
            $seekCount = $matchingRows === [] ? 0 : 1;
            $sortPenalty = $orderEvidence['blockSortRequired'] ? max(1, count($matchingRows)) : 0;

            return [
                'status' => 'usable',
                'usable' => true,
                'indexName' => $indexName,
                'rangeColumn' => $rangeColumn,
                'collation' => $collation,
                'rowids' => array_values(array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $matchingRows)),
                'omittedNullRangeRows' => $omittedNullRangeRows,
                'stat4SamplesUsed' => count($samples),
                'stat4Current' => $currentNext['current'],
                'stat4Next' => $currentNext['next'],
                'stat4RangeSamples' => $currentNext['rangeSamples'],
                'stat4RangeNltSpan' => $currentNext['nLtSpan'],
                'estimatedRows' => $estimate,
                'estimatedCost' => $seekCount * 8 + $estimate + $sortPenalty,
                'orderByMode' => $orderEvidence['mode'],
                'orderBySatisfied' => $orderEvidence['satisfied'],
                'blockSortRequired' => $orderEvidence['blockSortRequired'],
                'reverseScan' => $orderEvidence['reverseScan'],
                'sortBlockCount' => $orderEvidence['blockSortRequired'] ? 1 : 0,
                'detail' => self::planDetailNext97($indexName, $rangeColumn, $orderEvidence),
            ];
        }

        /**
         * @param list<array<string,mixed>> $samples
         * @return list<array{value:mixed,nEq:int,nLt:int,nDLt:int}>
         */
        private static function stat4SamplesNext97(array $samples): array
        {
            $normalized = [];
            foreach ($samples as $sample) {
                if (!is_array($sample) || !array_key_exists('value', $sample)) {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order samples need value');
                }
                foreach (['nEq', 'nLt', 'nDLt'] as $field) {
                    if (!isset($sample[$field]) || !is_int($sample[$field]) || $sample[$field] < 0) {
                        throw new \InvalidArgumentException('SQLite STAT4 range-order samples need non-negative counters');
                    }
                }
                $normalized[] = [
                    'value' => $sample['value'],
                    'nEq' => $sample['nEq'],
                    'nLt' => $sample['nLt'],
                    'nDLt' => $sample['nDLt'],
                ];
            }

            return $normalized;
        }

        /**
         * @param list<array{value:mixed,nEq:int,nLt:int,nDLt:int}> $samples
         * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,rangeSamples:int,nLtSpan:int}
         */
        private static function currentNextForRangeNext97(array $samples, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): array
        {
            $inRange = [];
            foreach ($samples as $sample) {
                if (self::withinNext97($sample['value'], $lower, $upper, $upperInclusive, $collation)) {
                    $inRange[] = $sample;
                }
            }
            usort($inRange, static fn (array $left, array $right): int => self::compareValuesNext97($left['value'], $right['value'], $collation));

            $first = $inRange[0] ?? null;
            $last = $inRange === [] ? null : $inRange[array_key_last($inRange)];
            $span = $first === null || $last === null ? 0 : max(0, $last['nLt'] - $first['nLt'] + $last['nEq']);

            return [
                'current' => self::sampleEvidenceNext97($first),
                'next' => self::sampleEvidenceNext97($inRange[1] ?? null),
                'rangeSamples' => count($inRange),
                'nLtSpan' => $span,
            ];
        }

        /**
         * @param array{value:mixed,nEq:int,nLt:int,nDLt:int}|null $sample
         * @return array<string,mixed>|null
         */
        private static function sampleEvidenceNext97(?array $sample): ?array
        {
            if ($sample === null) {
                return null;
            }

            return [
                'value' => $sample['value'],
                'nEq' => $sample['nEq'],
                'nLt' => $sample['nLt'],
                'nDLt' => $sample['nDLt'],
            ];
        }

        /**
         * @return array{mode:string,satisfied:bool,blockSortRequired:bool,reverseScan:bool}
         */
        private static function orderEvidenceNext97(string $rangeColumn, array $orderBy): array
        {
            if ($orderBy === []) {
                return ['mode' => 'none', 'satisfied' => false, 'blockSortRequired' => false, 'reverseScan' => false];
            }
            if (count($orderBy) !== 1) {
                return ['mode' => 'external-sort', 'satisfied' => false, 'blockSortRequired' => true, 'reverseScan' => false];
            }
            $term = $orderBy[0];
            $column = strtolower((string) ($term['column'] ?? ''));
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($column === '' || !in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException('SQLite STAT4 range-order ORDER BY needs column and ASC/DESC direction');
            }
            if ($column !== strtolower($rangeColumn)) {
                return ['mode' => 'external-sort', 'satisfied' => false, 'blockSortRequired' => true, 'reverseScan' => false];
            }

            return ['mode' => $direction === 'DESC' ? 'range-reverse' : 'range', 'satisfied' => true, 'blockSortRequired' => false, 'reverseScan' => $direction === 'DESC'];
        }

        private static function estimateRowsNext97(array $samples, int $rangeSamples, int $fallback): int
        {
            if ($rangeSamples === 0) {
                return max(1, $fallback);
            }

            $estimate = 0;
            foreach ($samples as $sample) {
                $estimate += min(max(1, $sample['nEq']), 4);
            }

            return max(1, min(max(1, $fallback), $estimate));
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummaryNext97(array $source, array $plan): array
        {
            return [
                'name' => self::stringValueNext97($source, 'name', 'source'),
                'schemaCookie' => self::nonNegativeIntNext97($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext97($source, 'stat4Generation'),
                'rangeSignature' => self::rangeSignatureNext97($source),
                'rowids' => $plan['rowids'] ?? [],
                'estimatedRows' => $plan['estimatedRows'] ?? 0,
                'estimatedCost' => $plan['estimatedCost'] ?? 0,
                'orderByMode' => $plan['orderByMode'] ?? 'none',
                'orderBySatisfied' => $plan['orderBySatisfied'] ?? false,
                'stat4Current' => $plan['stat4Current'] ?? null,
                'stat4Next' => $plan['stat4Next'] ?? null,
                'stat4RangeSamples' => $plan['stat4RangeSamples'] ?? 0,
            ];
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function rangeSignatureNext97(array $source): string
        {
            return serialize([$source['lower'] ?? null, $source['upper'] ?? null, (bool) ($source['upperInclusive'] ?? true), self::stringValueNext97($source, 'collation', 'BINARY')]);
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $currentSource
         */
        private static function detailNext97(bool $stale, array $plan, array $currentSource): string
        {
            $action = $stale ? 'REPREPARE STAT4 RANGE ORDER USING CURRENT SOURCE ' : 'REUSE PREPARED STAT4 RANGE ORDER ';

            return $action . self::stringValueNext97($currentSource, 'name', 'current') . ' ' . (string) ($plan['detail'] ?? 'NO PLAN');
        }

        /**
         * @param array{mode:string,satisfied:bool,blockSortRequired:bool,reverseScan:bool} $orderEvidence
         */
        private static function planDetailNext97(string $indexName, string $rangeColumn, array $orderEvidence): string
        {
            $detail = 'SEARCH ' . $indexName . ' USING STAT4 ' . $rangeColumn . ' RANGE';
            if ($orderEvidence['satisfied']) {
                $detail .= $orderEvidence['reverseScan'] ? ' ORDER BY RANGE REVERSE' : ' ORDER BY RANGE';
            } elseif ($orderEvidence['blockSortRequired']) {
                $detail .= ' USE TEMP B-TREE FOR ORDER BY';
            }

            return $detail;
        }

        private static function withinNext97(mixed $value, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): bool
        {
            if ($value === null) {
                return false;
            }
            if ($lower !== null && self::compareValuesNext97($value, $lower, $collation) < 0) {
                return false;
            }
            if ($upper !== null) {
                $comparison = self::compareValuesNext97($value, $upper, $collation);
                if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                    return false;
                }
            }

            return true;
        }

        private static function compareValuesNext97(mixed $left, mixed $right, string $collation): int
        {
            if ($left === null || $right === null) {
                return $left === $right ? 0 : ($left === null ? -1 : 1);
            }
            $leftText = (string) $left;
            $rightText = (string) $right;
            if ($collation === 'NOCASE') {
                $leftText = strtolower($leftText);
                $rightText = strtolower($rightText);
            }

            return strcmp($leftText, $rightText) <=> 0;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValueNext97(array $data, string $key, ?string $default = null): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 range-order current-source planner needs {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function nonNegativeIntNext97(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 range-order current-source planner needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         * @return list<array<string,mixed>>
         */
        private static function listValueNext97(array $data, string $key): array
        {
            $value = $data[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 range-order current-source planner needs list {$key}");
            }

            return $value;
        }

    /* Variant formerly implemented by SQLiteStat4RangeOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext102(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns = []): array
        {
            $prepared = self::sourcePlanNext102($preparedSource, $predicate, $orderBy, $neededColumns);
            $current = self::sourcePlanNext102($currentSource, $predicate, $orderBy, $neededColumns);
            $preparedFence = self::fenceNext102($preparedSource, $predicate, $orderBy, $neededColumns);
            $currentFence = self::fenceNext102($currentSource, $predicate, $orderBy, $neededColumns);
            $stale = $preparedFence !== $currentFence;
            $selected = $stale ? $current : $prepared;
            $source = $stale ? $currentSource : $preparedSource;
            $range = is_array($selected['rangeConstraint'] ?? null) ? $selected['rangeConstraint'] : null;
            $stat4 = is_array($selected['stat4RangeCurrentNext'] ?? null) ? $selected['stat4RangeCurrentNext'] : null;
            $reverse = self::reverseScanNext102($orderBy);
            $covering = ($selected['covering'] ?? false) === true;

            return [
                'status' => ($selected['status'] ?? null) === 'usable' ? 'range-order-current-source-ready' : 'no-usable-plan',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => self::nonNegativeIntNext102($preparedSource, 'schemaCookie') !== self::nonNegativeIntNext102($currentSource, 'schemaCookie'),
                'stat4GenerationChanged' => self::nonNegativeIntNext102($preparedSource, 'stat4Generation') !== self::nonNegativeIntNext102($currentSource, 'stat4Generation'),
                'indexSignatureChanged' => self::indexSignatureNext102($preparedSource) !== self::indexSignatureNext102($currentSource),
                'predicateSignatureChanged' => self::predicateSignatureNext102($predicate) !== ($preparedSource['preparedPredicateSignature'] ?? self::predicateSignatureNext102($predicate)),
                'orderSignature' => self::orderSignatureNext102($orderBy),
                'preparedPlan' => $prepared,
                'currentPlan' => $current,
                'selectedPlan' => $selected,
                'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
                'estimatedCostDelta' => (int) ($current['estimatedCost'] ?? 0) - (int) ($prepared['estimatedCost'] ?? 0),
                'cursorTape' => [
                    'source' => $stale ? 'current' : 'prepared',
                    'sourceName' => self::stringValueNext102($source, 'name', 'source'),
                    'indexName' => $selected['name'] ?? $selected['selected'] ?? null,
                    'rootPage' => $selected['rootPage'] ?? null,
                    'rangeColumn' => $selected['rangeColumn'] ?? null,
                    'seekOpcode' => self::seekOpcodeNext102($range, $reverse),
                    'stopOpcode' => self::stopOpcodeNext102($range, $reverse),
                    'nextOpcode' => $reverse ? 'Prev' : 'Next',
                    'scanDirection' => $reverse ? 'descending' : 'ascending',
                    'lowerValue' => self::rangeValueNext102($range, 'lower'),
                    'upperValue' => self::rangeValueNext102($range, 'upper'),
                    'lowerInclusive' => self::rangeInclusiveNext102($range, 'lower'),
                    'upperInclusive' => self::rangeInclusiveNext102($range, 'upper'),
                    'stat4LowerCurrent' => self::boundaryKeyNext102($stat4, 'lower', 'current'),
                    'stat4LowerNext' => self::boundaryKeyNext102($stat4, 'lower', 'next'),
                    'stat4UpperCurrent' => self::boundaryKeyNext102($stat4, 'upper', 'current'),
                    'stat4UpperNext' => self::boundaryKeyNext102($stat4, 'upper', 'next'),
                    'stat4LowerExact' => self::boundaryExactNext102($stat4, 'lower'),
                    'stat4UpperExact' => self::boundaryExactNext102($stat4, 'upper'),
                    'stat4EmptyGap' => (bool) ($stat4['emptyGap'] ?? false),
                    'stat4MatchedSamples' => $selected['stat4MatchedSamples'] ?? 0,
                    'covering' => $covering,
                    'deferredSeekOpcode' => $covering ? null : 'DeferredSeek',
                    'sorterOpen' => ($selected['blockSortRequired'] ?? false) === true,
                    'program' => self::programNext102($selected, $range, $orderBy, $neededColumns, $covering),
                ],
                'currentSourceFence' => $currentFence,
                'detail' => ($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 RANGE ORDER CURRENT SOURCE ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependency_closure' => 'no new support component needed; next102 composes existing STAT4 multicolumn range planning into current-source cursor tape diagnostics',
                'non_overlap' => 'avoids accepted expression-index range-cost and expression ORDER BY work by asserting STAT4 range boundary seek/stop opcodes, current-source fences, and covering/deferred cursor behavior for plain indexed option-name ranges',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlanNext102(array $source, array $predicate, array $orderBy, array $neededColumns): array
        {
            $plan = SQLiteMultiColumnRangePlan::stat4RangeOrderCurrentSourceNext92(
                self::listValueNext102($source, 'indexes'),
                $predicate,
                $orderBy,
                $neededColumns,
            );
            if (($plan['status'] ?? null) !== 'usable') {
                return $plan;
            }

            return $plan + ['status' => 'usable'];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function fenceNext102(array $source, array $predicate, array $orderBy, array $neededColumns): array
        {
            return [
                'schemaCookie' => self::nonNegativeIntNext102($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext102($source, 'stat4Generation'),
                'indexSignature' => self::indexSignatureNext102($source),
                'predicateSignature' => self::predicateSignatureNext102($predicate),
                'orderSignature' => self::orderSignatureNext102($orderBy),
                'projectionSignature' => self::projectionSignatureNext102($neededColumns),
            ];
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignatureNext102(array $source): string
        {
            $parts = [];
            foreach (self::listValueNext102($source, 'indexes') as $index) {
                $parts[] = hash('sha256', serialize([
                    $index['name'] ?? null,
                    $index['rootPage'] ?? null,
                    isset($index['sql']) && is_string($index['sql']) ? preg_replace('/\s+/', ' ', trim($index['sql'])) : null,
                    $index['estimatedRows'] ?? null,
                    $index['stat4Samples'] ?? [],
                ]));
            }
            sort($parts, SORT_STRING);

            return hash('sha256', implode("\n", $parts));
        }

        /**
         * @param array<string,mixed> $predicate
         */
        private static function predicateSignatureNext102(array $predicate): string
        {
            return hash('sha256', serialize($predicate));
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function orderSignatureNext102(array $orderBy): string
        {
            $parts = [];
            foreach ($orderBy as $term) {
                $column = $term['column'] ?? null;
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order current-source next102 needs ORDER BY columns');
                }
                $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order current-source next102 ORDER BY direction must be ASC or DESC');
                }
                $parts[] = strtolower($column) . ' ' . $direction;
            }

            return implode(',', $parts);
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function projectionSignatureNext102(array $neededColumns): string
        {
            $columns = $neededColumns;
            sort($columns, SORT_STRING);

            return implode("\0", $columns);
        }

        /**
         * @param array<string,mixed>|null $range
         */
        private static function seekOpcodeNext102(?array $range, bool $reverse): string
        {
            if ($reverse) {
                return self::rangeInclusiveNext102($range, 'upper') ? 'SeekLE' : 'SeekLT';
            }

            return self::rangeInclusiveNext102($range, 'lower') ? 'SeekGE' : 'SeekGT';
        }

        /**
         * @param array<string,mixed>|null $range
         */
        private static function stopOpcodeNext102(?array $range, bool $reverse): string
        {
            if ($reverse) {
                return self::rangeInclusiveNext102($range, 'lower') ? 'IdxLT' : 'IdxLE';
            }

            return self::rangeInclusiveNext102($range, 'upper') ? 'IdxGT' : 'IdxGE';
        }

        /**
         * @param array<string,mixed>|null $range
         */
        private static function rangeValueNext102(?array $range, string $side): mixed
        {
            if ($range === null) {
                return null;
            }
            $operator = (string) ($range['operator'] ?? '');
            $values = $range['values'] ?? null;
            if ($operator === 'BETWEEN' || $operator === 'range-bounded') {
                return is_array($values) ? ($values[$side] ?? null) : null;
            }
            if ($side === 'lower' && ($operator === 'range->' || $operator === 'range->=')) {
                return $values;
            }
            if ($side === 'upper' && ($operator === 'range-<' || $operator === 'range-<=')) {
                return $values;
            }

            return null;
        }

        /**
         * @param array<string,mixed>|null $range
         */
        private static function rangeInclusiveNext102(?array $range, string $side): bool
        {
            if ($range === null) {
                return true;
            }
            $operator = (string) ($range['operator'] ?? '');
            $values = $range['values'] ?? null;
            if ($operator === 'BETWEEN') {
                return true;
            }
            if ($operator === 'range-bounded' && is_array($values)) {
                return (bool) ($values[$side . 'Inclusive'] ?? false);
            }
            if ($side === 'lower') {
                return $operator !== 'range->';
            }

            return $operator !== 'range-<';
        }

        /**
         * @param array<string,mixed>|null $stat4
         */
        private static function boundaryKeyNext102(?array $stat4, string $side, string $which): mixed
        {
            $boundary = is_array($stat4[$side] ?? null) ? $stat4[$side] : null;
            $sample = is_array($boundary[$which] ?? null) ? $boundary[$which] : null;

            return $sample['key'] ?? null;
        }

        /**
         * @param array<string,mixed>|null $stat4
         */
        private static function boundaryExactNext102(?array $stat4, string $side): bool
        {
            $boundary = is_array($stat4[$side] ?? null) ? $stat4[$side] : null;

            return $boundary !== null && ($boundary['exact'] ?? false) === true;
        }

        /**
         * @param list<array{column:string,direction?:string}> $orderBy
         */
        private static function reverseScanNext102(array $orderBy): bool
        {
            return $orderBy !== [] && strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC';
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed>|null $range
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function programNext102(array $plan, ?array $range, array $orderBy, array $neededColumns, bool $covering): array
        {
            $reverse = self::reverseScanNext102($orderBy);
            $program = [
                ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null],
                ['opcode' => self::seekOpcodeNext102($range, $reverse), 'column' => $plan['rangeColumn'] ?? null, 'value' => $reverse ? self::rangeValueNext102($range, 'upper') : self::rangeValueNext102($range, 'lower')],
                ['opcode' => self::stopOpcodeNext102($range, $reverse), 'column' => $plan['rangeColumn'] ?? null, 'value' => $reverse ? self::rangeValueNext102($range, 'lower') : self::rangeValueNext102($range, 'upper')],
            ];
            if (!$covering) {
                $program[] = ['opcode' => 'DeferredSeek', 'target' => 'table'];
            }
            if (($plan['blockSortRequired'] ?? false) === true) {
                $program[] = ['opcode' => 'SorterOpen', 'orderBy' => $orderBy];
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 range-order current-source next102 needs output column names');
                }
                $program[] = ['opcode' => 'Column', 'source' => $covering ? 'index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => $reverse ? 'Prev' : 'Next', 'target' => 'index'];

            return $program;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValueNext102(array $data, string $key, ?string $default = null): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 range-order current-source next102 needs {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function nonNegativeIntNext102(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 range-order current-source next102 needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         * @return list<array<string,mixed>>
         */
        private static function listValueNext102(array $data, string $key): array
        {
            $value = $data[$key] ?? null;
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 range-order current-source next102 needs list {$key}");
            }

            return $value;
        }

}
