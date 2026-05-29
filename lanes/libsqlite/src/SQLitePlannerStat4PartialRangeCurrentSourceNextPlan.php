<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialRangeCurrentSourceNextPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function compare(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns = [],
    ): array {
        $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
        $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);

        $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
        $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
        $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
        $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
        $preparedRange = self::partialRangeSummary($preparedPlan);
        $currentRange = self::partialRangeSummary($currentPlan);
        $stale = $preparedCookie !== $currentCookie
            || $preparedStat4 !== $currentStat4
            || $preparedRange !== $currentRange
            || self::indexSignature($preparedSource) !== self::indexSignature($currentSource);
        $selected = $stale ? $currentPlan : $preparedPlan;

        return [
            'status' => (string) ($selected['status'] ?? 'unusable'),
            'selectedSource' => $stale ? 'current' : 'prepared',
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedRange),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentRange),
            'selectedPlan' => $selected,
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'indexSignatureChanged' => self::indexSignature($preparedSource) !== self::indexSignature($currentSource),
            'partialRangeChanged' => $preparedRange !== $currentRange,
            'partialRangeDelta' => self::partialRangeDelta($preparedRange, $currentRange),
            'preparedWouldUseStalePartialRange' => $stale
                && ($preparedPlan['usable'] ?? false) === true
                && ($currentPlan['usable'] ?? false) === true
                && $preparedRange !== $currentRange,
            'preparedRowEstimate' => (int) ($preparedPlan['estimatedRows'] ?? 0),
            'currentRowEstimate' => (int) ($currentPlan['estimatedRows'] ?? 0),
            'estimatedRowsDelta' => (int) ($currentPlan['estimatedRows'] ?? 0) - (int) ($preparedPlan['estimatedRows'] ?? 0),
            'stat4MatchedSamplesDelta' => (int) ($currentPlan['stat4MatchedSamples'] ?? 0) - (int) ($preparedPlan['stat4MatchedSamples'] ?? 0),
            'detail' => self::detail($stale, $selected, $currentSource, $currentRange),
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
            $indexes = $source['indexes'] ?? null;
            if (!is_array($indexes) || !array_is_list($indexes)) {
                throw new \InvalidArgumentException('SQLite STAT4 partial range current-source planner needs index list');
            }

            $plan = SQLitePartialIndexOrderCurrentSourcePlan::plan($indexes, $predicate, $orderBy, $neededColumns);
            $selectedName = $plan['name'] ?? null;
            if (is_string($selectedName)) {
                foreach ($indexes as $index) {
                    if (!is_array($index)) {
                        throw new \InvalidArgumentException('SQLite STAT4 partial range current-source indexes must be arrays');
                    }
                    $name = $index['name'] ?? null;
                    if ($name === $selectedName) {
                        $plan['sql'] = $index['sql'] ?? null;
                        break;
                    }
                }
            }

            return $plan;
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @param array<string,mixed>|null $partialRange
         * @return array<string,mixed>
         */
        private static function sourceSummary(array $source, array $plan, ?array $partialRange): array
        {
            return [
                'name' => self::stringValue($source, 'name', 'source'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'indexSignature' => self::indexSignature($source),
                'status' => (string) ($plan['status'] ?? 'unusable'),
                'usable' => (bool) ($plan['usable'] ?? false),
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'rangeConstraint' => $plan['rangeConstraint'] ?? null,
                'partialRange' => $partialRange,
                'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
                'stat4MatchedSamples' => (int) ($plan['stat4MatchedSamples'] ?? 0),
                'stat4RangeCurrentNext' => $plan['stat4RangeCurrentNext'] ?? null,
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'detail' => $plan['detail'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return array<string,mixed>|null
         */
        private static function partialRangeSummary(array $plan): ?array
        {
            $indexSql = (string) ($plan['sql'] ?? '');
            if ($indexSql === '') {
                return null;
            }

            $rangeColumn = $plan['rangeColumn'] ?? null;
            if (!is_string($rangeColumn) || $rangeColumn === '') {
                return null;
            }

            $bounds = [];
            $quoted = preg_quote($rangeColumn, '/');
            if (preg_match_all('/\b' . $quoted . '\s*(>=|>|<=|<)\s*\'([^\']*)\'/i', $indexSql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $operator = $match[1];
                    $value = $match[2];
                    if ($operator === '>=' || $operator === '>') {
                        $bounds['lower'] = $value;
                        $bounds['lowerInclusive'] = $operator === '>=';
                    } else {
                        $bounds['upper'] = $value;
                        $bounds['upperInclusive'] = $operator === '<=';
                    }
                }
            }
            if ($bounds === []) {
                return null;
            }

            return [
                'column' => $rangeColumn,
                'lower' => $bounds['lower'] ?? null,
                'lowerInclusive' => $bounds['lowerInclusive'] ?? null,
                'upper' => $bounds['upper'] ?? null,
                'upperInclusive' => $bounds['upperInclusive'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed>|null $prepared
         * @param array<string,mixed>|null $current
         * @return array<string,mixed>
         */
        private static function partialRangeDelta(?array $prepared, ?array $current): array
        {
            return [
                'prepared' => $prepared,
                'current' => $current,
                'lowerChanged' => ($prepared['lower'] ?? null) !== ($current['lower'] ?? null)
                    || ($prepared['lowerInclusive'] ?? null) !== ($current['lowerInclusive'] ?? null),
                'upperChanged' => ($prepared['upper'] ?? null) !== ($current['upper'] ?? null)
                    || ($prepared['upperInclusive'] ?? null) !== ($current['upperInclusive'] ?? null),
            ];
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignature(array $source): string
        {
            $indexes = $source['indexes'] ?? [];
            if (!is_array($indexes)) {
                throw new \InvalidArgumentException('SQLite STAT4 partial range current-source planner needs index list');
            }

            return hash('sha256', serialize($indexes));
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed>|null $currentRange
         */
        private static function detail(bool $stale, array $plan, array $currentSource, ?array $currentRange): string
        {
            $action = $stale ? 'REPREPARE USING CURRENT SOURCE ' : 'REUSE PREPARED SOURCE ';
            $sourceName = self::stringValue($currentSource, 'name', 'current');
            $range = $currentRange === null ? 'NO PARTIAL RANGE' : 'PARTIAL RANGE ' . ($currentRange['column'] ?? 'unknown');

            return $action . $sourceName . ' ' . $range . ' ' . (string) ($plan['detail'] ?? 'STAT4 PARTIAL RANGE');
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function nonNegativeInt(array $data, string $key): int
        {
            $value = $data[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite STAT4 partial range current-source planner needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValue(array $data, string $key, string $default): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 partial range current-source planner needs {$key}");
            }

            return $value;
        }

}
