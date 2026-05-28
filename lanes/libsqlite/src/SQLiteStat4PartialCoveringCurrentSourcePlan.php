<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4PartialCoveringCurrentSourcePlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function compare(array $preparedSource, array $currentSource, array $predicate, array $orderBy = [], array $neededColumns = []): array
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
            'coveringChanged' => (bool) ($prepared['covering'] ?? false) !== (bool) ($current['covering'] ?? false),
            'orderByModeChanged' => ($prepared['orderByMode'] ?? null) !== ($current['orderByMode'] ?? null),
            'stat4EstimateDelta' => (int) ($current['stat4Estimate'] ?? 0) - (int) ($prepared['stat4Estimate'] ?? 0),
            'estimatedRowsDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
            'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedProjection),
            'currentSource' => self::sourceSummary($currentSource, $current, $currentProjection),
            'selectedPlan' => $selected,
            'detail' => self::detail($stale, $selected, $currentSource),
            'dependencies' => [
                'SQLitePartialIndexOrderCurrentSourcePlan',
                'SQLiteMultiColumnRangePlan',
                'SQLiteIndexPredicate',
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
            'partialPredicateImplied' => (bool) ($plan['partialPredicateImplied'] ?? false),
            'partialIndexOrderUsable' => (bool) ($plan['partialIndexOrderUsable'] ?? false),
            'covering' => (bool) ($plan['covering'] ?? false),
            'orderByMode' => $plan['orderByMode'] ?? 'none',
            'estimatedRows' => $plan['estimatedRows'] ?? 0,
            'estimatedCost' => $plan['estimatedCost'] ?? 0,
            'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
            'stat4Estimate' => $plan['stat4Estimate'] ?? null,
            'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
            'stat4CurrentSourceColumn' => $plan['stat4CurrentSourceColumn'] ?? null,
            'stat4CurrentSourceOffset' => $plan['stat4CurrentSourceOffset'] ?? null,
            'stat4RangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
            'nextSource' => $plan['nextSource'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $currentSource
     */
    private static function detail(bool $stale, array $plan, array $currentSource): string
    {
        $action = $stale ? 'REPREPARE STAT4 PARTIAL COVERING USING CURRENT SOURCE ' : 'REUSE PREPARED STAT4 PARTIAL COVERING ';

        return $action . self::stringValue($currentSource, 'name', 'current') . ' ' . (string) ($plan['detail'] ?? 'NO PLAN');
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
     * @param array<string,mixed> $data
     */
    private static function stringValue(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 partial-covering current-source planner needs {$key}");
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
            throw new \InvalidArgumentException("SQLite STAT4 partial-covering current-source planner needs non-negative integer {$key}");
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
            throw new \InvalidArgumentException("SQLite STAT4 partial-covering current-source planner needs list {$key}");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $key): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 partial-covering current-source planner needs list {$key}");
        }
        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("SQLite STAT4 partial-covering current-source planner needs string {$key} values");
            }
            $strings[] = $item;
        }

        return $strings;
    }
}
