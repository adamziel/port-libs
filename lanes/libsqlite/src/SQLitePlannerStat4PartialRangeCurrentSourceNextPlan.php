<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4PartialRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function compareNext124(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns = [],
        ): array {
            $preparedPlan = self::sourcePlanNext124($preparedSource, $predicate, $orderBy, $neededColumns);
            $currentPlan = self::sourcePlanNext124($currentSource, $predicate, $orderBy, $neededColumns);

            $preparedCookie = self::nonNegativeIntNext124($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext124($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeIntNext124($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeIntNext124($currentSource, 'stat4Generation');
            $preparedRange = self::partialRangeSummaryNext124($preparedPlan);
            $currentRange = self::partialRangeSummaryNext124($currentPlan);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedRange !== $currentRange
                || self::indexSignatureNext124($preparedSource) !== self::indexSignatureNext124($currentSource);
            $selected = $stale ? $currentPlan : $preparedPlan;

            return [
                'status' => (string) ($selected['status'] ?? 'unusable'),
                'selectedSource' => $stale ? 'current' : 'prepared',
                'preparedSource' => self::sourceSummaryNext124($preparedSource, $preparedPlan, $preparedRange),
                'currentSource' => self::sourceSummaryNext124($currentSource, $currentPlan, $currentRange),
                'selectedPlan' => $selected,
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => self::indexSignatureNext124($preparedSource) !== self::indexSignatureNext124($currentSource),
                'partialRangeChanged' => $preparedRange !== $currentRange,
                'partialRangeDelta' => self::partialRangeDeltaNext124($preparedRange, $currentRange),
                'preparedWouldUseStalePartialRange' => $stale
                    && ($preparedPlan['usable'] ?? false) === true
                    && ($currentPlan['usable'] ?? false) === true
                    && $preparedRange !== $currentRange,
                'preparedRowEstimate' => (int) ($preparedPlan['estimatedRows'] ?? 0),
                'currentRowEstimate' => (int) ($currentPlan['estimatedRows'] ?? 0),
                'estimatedRowsDelta' => (int) ($currentPlan['estimatedRows'] ?? 0) - (int) ($preparedPlan['estimatedRows'] ?? 0),
                'stat4MatchedSamplesDelta' => (int) ($currentPlan['stat4MatchedSamples'] ?? 0) - (int) ($preparedPlan['stat4MatchedSamples'] ?? 0),
                'detail' => self::detailNext124($stale, $selected, $currentSource, $currentRange),
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
        private static function sourcePlanNext124(array $source, array $predicate, array $orderBy, array $neededColumns): array
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
        private static function sourceSummaryNext124(array $source, array $plan, ?array $partialRange): array
        {
            return [
                'name' => self::stringValueNext124($source, 'name', 'source'),
                'schemaCookie' => self::nonNegativeIntNext124($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext124($source, 'stat4Generation'),
                'indexSignature' => self::indexSignatureNext124($source),
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
        private static function partialRangeSummaryNext124(array $plan): ?array
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
        private static function partialRangeDeltaNext124(?array $prepared, ?array $current): array
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
        private static function indexSignatureNext124(array $source): string
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
        private static function detailNext124(bool $stale, array $plan, array $currentSource, ?array $currentRange): string
        {
            $action = $stale ? 'REPREPARE USING CURRENT SOURCE ' : 'REUSE PREPARED SOURCE ';
            $sourceName = self::stringValueNext124($currentSource, 'name', 'current');
            $range = $currentRange === null ? 'NO PARTIAL RANGE' : 'PARTIAL RANGE ' . ($currentRange['column'] ?? 'unknown');

            return $action . $sourceName . ' ' . $range . ' ' . (string) ($plan['detail'] ?? 'STAT4 PARTIAL RANGE');
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function nonNegativeIntNext124(array $data, string $key): int
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
        private static function stringValueNext124(array $data, string $key, string $default): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 partial range current-source planner needs {$key}");
            }

            return $value;
        }

}
