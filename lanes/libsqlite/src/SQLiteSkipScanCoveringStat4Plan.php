<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSkipScanCoveringStat4Plan
{
    /**
     * @param list<array<string,mixed>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): ?array
    {
        $plans = self::rankedPlans($indexDefinitions, $predicate, $orderBy, $neededColumns);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): array
    {
        $basePlans = SQLiteMultiColumnRangePlan::rankedPlans($indexDefinitions, $predicate, $orderBy, $neededColumns);
        $indexesByName = [];
        foreach ($indexDefinitions as $index) {
            $sql = $index['sql'] ?? null;
            if (!is_string($sql) || $sql === '') {
                continue;
            }
            $name = isset($index['name']) && is_string($index['name']) && $index['name'] !== ''
                ? $index['name']
                : self::indexName($sql);
            $indexesByName[$name] = $index;
        }

        $plans = [];
        foreach ($basePlans as $plan) {
            if (($plan['usesSkipScan'] ?? false) !== true) {
                continue;
            }
            $index = $indexesByName[(string) $plan['name']] ?? null;
            if ($index === null) {
                continue;
            }
            $plans[] = self::enrich($plan, $index, $neededColumns);
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) $left['name']]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) $right['name']];
        });

        return $plans;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $index
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function enrich(array $plan, array $index, array $neededColumns): array
    {
        $samples = self::sampleRows($index['stat4Samples'] ?? []);
        $range = $plan['rangeConstraint'];
        $currentNext = self::currentNextSamples($samples);
        $loopEstimates = self::loopEstimates($samples, $plan['skippedColumns'], $range);
        $stat4Rows = array_sum(array_column($loopEstimates, 'estimatedRows'));
        $stat4Used = $samples !== [] && $stat4Rows > 0;
        $estimatedRows = $stat4Used ? $stat4Rows : (int) $plan['estimatedRows'];
        $covering = (bool) ($plan['covering'] ?? false);
        $missingColumns = self::missingColumns((array) $plan['columns'], $neededColumns);
        $cost = $estimatedRows + ((int) $plan['skipScanLoops'] * 8) + count($missingColumns) * 18;
        if ($covering) {
            $cost -= 24;
        }
        if (($plan['orderBySatisfied'] ?? false) === true) {
            $cost -= 8;
        }
        if ($stat4Used) {
            $cost -= min(20, count($samples));
        }

        return array_merge($plan, [
            'stat4Used' => $stat4Used,
            'stat4SamplesUsed' => count($samples),
            'stat4CurrentNext' => $currentNext,
            'stat4LoopEstimates' => $loopEstimates,
            'estimatedRows' => max(1, $estimatedRows),
            'estimatedCost' => max(1, $cost),
            'covering' => $covering,
            'coveringPayloadColumns' => $covering ? $neededColumns : [],
            'deferredTableLookup' => !$covering,
            'tableLookupColumns' => $missingColumns,
            'partialPredicateImplied' => ($plan['partial'] ?? false) === true,
            'detail' => self::detail($plan, $covering, $stat4Used),
        ]);
    }

    /**
     * @param mixed $samples
     * @return list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>
     */
    private static function sampleRows(mixed $samples): array
    {
        if ($samples === null || $samples === []) {
            return [];
        }
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite skip-scan STAT4 samples must be a list');
        }

        $rows = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite skip-scan STAT4 sample must be an array');
            }
            foreach (['nEq', 'nLt', 'nDLt'] as $field) {
                if (!isset($sample[$field]) || !is_int($sample[$field]) || $sample[$field] < 0) {
                    throw new \InvalidArgumentException('SQLite skip-scan STAT4 samples need non-negative integer counters');
                }
            }
            if (!array_key_exists('prefix', $sample) || !array_key_exists('suffix', $sample)) {
                throw new \InvalidArgumentException('SQLite skip-scan STAT4 samples need prefix and suffix values');
            }
            $rows[] = [
                'prefix' => $sample['prefix'],
                'suffix' => $sample['suffix'],
                'nEq' => $sample['nEq'],
                'nLt' => $sample['nLt'],
                'nDLt' => $sample['nDLt'],
            ];
        }
        usort($rows, static function (array $left, array $right): int {
            $prefix = self::compare($left['prefix'], $right['prefix']);
            if ($prefix !== 0) {
                return $prefix;
            }

            return self::compare($left['suffix'], $right['suffix']);
        });

        return $rows;
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @return list<array{current:array{prefix:mixed,suffix:mixed,key:string,nEq:int,nLt:int,nDLt:int},next:null|array{prefix:mixed,suffix:mixed,key:string,nEq:int,nLt:int,nDLt:int}}>
     */
    private static function currentNextSamples(array $samples): array
    {
        $pairs = [];
        foreach ($samples as $offset => $sample) {
            $pairs[] = [
                'current' => self::sampleSummary($sample),
                'next' => isset($samples[$offset + 1]) ? self::sampleSummary($samples[$offset + 1]) : null,
            ];
        }

        return $pairs;
    }

    /**
     * @param array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int} $sample
     * @return array{prefix:mixed,suffix:mixed,key:string,nEq:int,nLt:int,nDLt:int}
     */
    private static function sampleSummary(array $sample): array
    {
        return [
            'prefix' => $sample['prefix'],
            'suffix' => $sample['suffix'],
            'key' => self::valueLabel($sample['prefix']) . '|' . self::valueLabel($sample['suffix']),
            'nEq' => $sample['nEq'],
            'nLt' => $sample['nLt'],
            'nDLt' => $sample['nDLt'],
        ];
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @param list<string> $skippedColumns
     * @param array{column:string,operator:string,values:mixed} $range
     * @return list<array{prefix:mixed,sampleCount:int,estimatedRows:int,currentSuffix:mixed,nextSuffix:mixed|null}>
     */
    private static function loopEstimates(array $samples, array $skippedColumns, array $range): array
    {
        $byPrefix = [];
        foreach ($samples as $sample) {
            $byPrefix[self::key($sample['prefix'])][] = $sample;
        }

        $estimates = [];
        foreach ($byPrefix as $prefixSamples) {
            $inRange = array_values(array_filter(
                $prefixSamples,
                static fn (array $sample): bool => self::sampleWithinRange($sample['suffix'], $range)
            ));
            if ($inRange === []) {
                $inRange = [$prefixSamples[0]];
            }
            $estimates[] = [
                'prefix' => $prefixSamples[0]['prefix'],
                'sampleCount' => count($prefixSamples),
                'estimatedRows' => max(1, array_sum(array_column($inRange, 'nEq'))),
                'currentSuffix' => $inRange[0]['suffix'],
                'nextSuffix' => isset($inRange[1]) ? $inRange[1]['suffix'] : null,
            ];
        }

        return $estimates;
    }

    /**
     * @param array{column:string,operator:string,values:mixed} $range
     */
    private static function sampleWithinRange(mixed $value, array $range): bool
    {
        $operator = $range['operator'];
        $values = $range['values'];
        if ($operator === 'BETWEEN' && is_array($values)) {
            return self::compare($value, $values['lower'] ?? null) >= 0 && self::compare($value, $values['upper'] ?? null) <= 0;
        }
        if ($operator === 'range->=') {
            return self::compare($value, $values) >= 0;
        }
        if ($operator === 'range->') {
            return self::compare($value, $values) > 0;
        }
        if ($operator === 'range-<=') {
            return self::compare($value, $values) <= 0;
        }
        if ($operator === 'range-<') {
            return self::compare($value, $values) < 0;
        }

        return true;
    }

    /**
     * @param list<string> $indexColumns
     * @param list<string> $neededColumns
     * @return list<string>
     */
    private static function missingColumns(array $indexColumns, array $neededColumns): array
    {
        $available = array_fill_keys(array_map('strtolower', $indexColumns), true);
        $missing = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite skip-scan covering columns must be column names');
            }
            if (!isset($available[strtolower($column)])) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function detail(array $plan, bool $covering, bool $stat4Used): string
    {
        $detail = 'SEARCH ' . ($covering ? 'USING COVERING INDEX ' : 'USING INDEX ') . (string) $plan['name'];
        $detail .= ' SKIP-SCAN ANY(' . implode(',', (array) $plan['skippedColumns']) . ')';
        $detail .= ' CURRENT ' . (string) $plan['rangeColumn'];
        if (($plan['partial'] ?? false) === true) {
            $detail .= ' PARTIAL';
        }
        if ($stat4Used) {
            $detail .= ' USING STAT4';
        }
        if (($plan['orderBySatisfied'] ?? false) === true) {
            $detail .= ' ORDER BY SATISFIED';
        }

        return $detail;
    }

    private static function indexName(string $sql): string
    {
        if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $match) === 1) {
            return (string) ($match[1] ?: $match[2] ?: $match[3] ?: $match[4]);
        }

        return 'unknown';
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_array($left) || is_array($right)) {
            $leftTuple = is_array($left) ? array_values($left) : [$left];
            $rightTuple = is_array($right) ? array_values($right) : [$right];
            $count = min(count($leftTuple), count($rightTuple));
            for ($i = 0; $i < $count; $i++) {
                $comparison = self::compare($leftTuple[$i], $rightTuple[$i]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return count($leftTuple) <=> count($rightTuple);
        }
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }

        return strcmp((string) $left, (string) $right) <=> 0;
    }

    private static function key(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }

    private static function valueLabel(mixed $value): string
    {
        if (is_array($value)) {
            return '(' . implode(',', array_map(static fn (mixed $item): string => self::valueLabel($item), array_values($value))) . ')';
        }
        if ($value === null) {
            return 'NULL';
        }
        if ($value === true) {
            return '1';
        }
        if ($value === false) {
            return '0';
        }

        return (string) $value;
    }
}
