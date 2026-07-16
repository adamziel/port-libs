<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4ExpressionRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteStat4ExpressionRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function compareExpressionRange(array $preparedSource, array $currentSource, array $predicate, array $neededColumns = []): array
        {
            $prepared = self::sourcePlan($preparedSource, $predicate, $neededColumns);
            $current = self::sourcePlan($currentSource, $predicate, $neededColumns);

            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedIndexes = self::indexSignature($preparedSource);
            $currentIndexes = self::indexSignature($currentSource);
            $preparedProjection = self::projectionSignature($preparedSource, $neededColumns);
            $currentProjection = self::projectionSignature($currentSource, $neededColumns);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedIndexes !== $currentIndexes
                || $preparedProjection !== $currentProjection;
            $selected = $stale ? $current : $prepared;

            return [
                'status' => $selected['status'],
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => $preparedIndexes !== $currentIndexes,
                'projectionChanged' => $preparedProjection !== $currentProjection,
                'estimateDelta' => (int) ($current['estimatedRows'] ?? 0) - (int) ($prepared['estimatedRows'] ?? 0),
                'costDelta' => (int) ($current['estimatedCost'] ?? 0) - (int) ($prepared['estimatedCost'] ?? 0),
                'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedProjection),
                'currentSource' => self::sourceSummary($currentSource, $current, $currentProjection),
                'selectedPlan' => $selected,
                'expressionRangePlan' => ($selected['status'] ?? null) === 'usable' && ($selected['expressionRangeUsable'] ?? false) === true,
                'detail' => self::detail($stale, $selected, $currentSource),
                'dependency_closure' => 'no new support component needed; current-source STAT4 expression range planning composes native PHP expression-index metadata and sqlite_stat4 sample diagnostics',
                'dependencies' => [
                    'sqlite-stat4-expression-range-current-source-next104',
                    'SQLiteCreateIndex',
                    'sqlite_stat4',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlan(array $source, array $predicate, array $neededColumns): array
        {
            $plans = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                $plan = self::indexPlan($index, $predicate, $neededColumns);
                if ($plan !== null) {
                    $plans[] = $plan;
                }
            }
            usort($plans, static fn (array $left, array $right): int => [$left['estimatedCost'], $left['estimatedRows'], $left['name']] <=> [$right['estimatedCost'], $right['estimatedRows'], $right['name']]);

            if ($plans === []) {
                return [
                    'status' => 'unusable',
                    'usable' => false,
                    'expressionRangeUsable' => false,
                    'rankedPlanCount' => 0,
                    'nextSource' => 'table-scan',
                    'detail' => 'SCAN TABLE; NO USABLE STAT4 EXPRESSION RANGE',
                ];
            }

            $selected = $plans[0];

            return $selected + [
                'status' => 'usable',
                'usable' => true,
                'rankedPlanCount' => count($plans),
                'rankedPlanNames' => array_map(static fn (array $plan): string => (string) $plan['name'], $plans),
                'nextSource' => ($selected['covering'] ?? false) === true ? 'covering-expression-index' : 'table-rowid-lookup',
            ];
        }

        /**
         * @param array<string,mixed> $index
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return null|array<string,mixed>
         */
        private static function indexPlan(array $index, array $predicate, array $neededColumns): ?array
        {
            $expression = self::expressionValue($index, 'expression');
            $constraints = self::expressionConstraints($predicate, $expression);
            if ($constraints['lower'] === null && $constraints['upper'] === null) {
                return null;
            }

            $samples = self::samples($index['stat4Samples'] ?? []);
            $range = self::sampleRange($samples, $constraints['lower'], $constraints['upper']);
            $matched = $range['matched'];
            $stat4Estimate = $matched === [] ? 1 : max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $matched)));
            $estimatedRows = min(self::positiveInt($index, 'estimatedRows', 1000), $stat4Estimate);
            $covering = self::coversNeededColumns($index, $neededColumns);
            $cost = $estimatedRows + 32 - ($covering ? 14 : 0) - ($matched !== [] ? 6 : 0);

            return [
                'name' => self::stringValue($index, 'name', self::indexName((string) ($index['sql'] ?? 'expression-index'))),
                'rootPage' => isset($index['rootPage']) && is_int($index['rootPage']) ? $index['rootPage'] : null,
                'expression' => $expression,
                'expressionRangeUsable' => true,
                'lowerBound' => $constraints['lower'],
                'upperBound' => $constraints['upper'],
                'lowerInclusive' => $constraints['lowerInclusive'],
                'upperInclusive' => $constraints['upperInclusive'],
                'covering' => $covering,
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => max(1, $cost),
                'stat4Used' => $samples !== [],
                'stat4Estimate' => $stat4Estimate,
                'stat4MatchedSamples' => count($matched),
                'stat4MatchedCurrentNext' => self::currentNextPairs($matched),
                'stat4RangeCurrentNext' => [
                    'lower' => self::boundaryPair($range['beforeLower'], $range['firstInRange']),
                    'upper' => self::boundaryPair($range['lastInRange'], $range['afterUpper']),
                ],
                'detail' => 'SEARCH ' . self::stringValue($index, 'name', self::indexName((string) ($index['sql'] ?? 'expression-index'))) . ' USING STAT4 EXPRESSION RANGE ' . $expression,
            ];
        }

        /**
         * @param array<string,mixed> $predicate
         * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}
         */
        private static function expressionConstraints(array $predicate, string $expression): array
        {
            $terms = self::flattenAndTerms($predicate);
            $lower = null;
            $upper = null;
            $lowerInclusive = false;
            $upperInclusive = false;
            foreach ($terms as $term) {
                $operator = strtoupper(self::stringValue($term, 'operator'));
                if (!self::sameExpression($term['left'] ?? null, $expression)) {
                    continue;
                }
                if (in_array($operator, ['>', '>='], true)) {
                    $lower = $term['right'] ?? null;
                    $lowerInclusive = $operator === '>=';
                } elseif (in_array($operator, ['<', '<='], true)) {
                    $upper = $term['right'] ?? null;
                    $upperInclusive = $operator === '<=';
                } elseif ($operator === 'BETWEEN') {
                    $values = $term['right'] ?? null;
                    if (!is_array($values) || count($values) !== 2) {
                        throw new \InvalidArgumentException('SQLite STAT4 expression range BETWEEN needs two values');
                    }
                    $lower = $values[0];
                    $upper = $values[1];
                    $lowerInclusive = true;
                    $upperInclusive = true;
                }
            }

            return ['lower' => $lower, 'upper' => $upper, 'lowerInclusive' => $lowerInclusive, 'upperInclusive' => $upperInclusive];
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function flattenAndTerms(array $predicate): array
        {
            $operator = strtoupper(self::stringValue($predicate, 'operator'));
            if ($operator !== 'AND') {
                return [$predicate];
            }
            $terms = $predicate['terms'] ?? null;
            if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
                throw new \InvalidArgumentException('SQLite STAT4 expression range AND predicate needs terms');
            }
            $flattened = [];
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression range terms must be predicates');
                }
                array_push($flattened, ...self::flattenAndTerms($term));
            }

            return $flattened;
        }

        private static function sameExpression(mixed $operand, string $expression): bool
        {
            if (is_array($operand) && isset($operand['expression']) && is_string($operand['expression'])) {
                return self::normalizeExpression($operand['expression']) === self::normalizeExpression($expression);
            }

            return false;
        }

        /**
         * @param list<array<string,mixed>> $samples
         * @return array{beforeLower:?array<string,mixed>,firstInRange:?array<string,mixed>,lastInRange:?array<string,mixed>,afterUpper:?array<string,mixed>,matched:list<array<string,mixed>>}
         */
        private static function sampleRange(array $samples, mixed $lower, mixed $upper): array
        {
            $beforeLower = null;
            $first = null;
            $last = null;
            $afterUpper = null;
            $matched = [];
            foreach ($samples as $sample) {
                $key = $sample['key'];
                if ($lower !== null && self::compareValue($key, $lower) < 0) {
                    $beforeLower = $sample;
                    continue;
                }
                if ($upper !== null && self::compareValue($key, $upper) >= 0) {
                    $afterUpper ??= $sample;
                    continue;
                }
                $first ??= $sample;
                $last = $sample;
                $matched[] = $sample;
            }

            return ['beforeLower' => $beforeLower, 'firstInRange' => $first, 'lastInRange' => $last, 'afterUpper' => $afterUpper, 'matched' => $matched];
        }

        private static function compareValue(mixed $left, mixed $right): int
        {
            if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
                return ((float) $left) <=> ((float) $right);
            }

            return strcmp((string) $left, (string) $right) <=> 0;
        }

        /**
         * @param mixed $samples
         * @return list<array{key:mixed,rowid:int,neq:int,nlt:int}>
         */
        private static function samples(mixed $samples): array
        {
            if (!is_array($samples) || !array_is_list($samples)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression range needs stat4Samples list');
            }
            $normalized = [];
            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression range samples must be arrays');
                }
                $value = $sample['sample'][0] ?? $sample['key'] ?? null;
                $rowid = $sample['rowid'] ?? $sample['sample'][1] ?? null;
                if (!is_int($rowid)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression range sample rowid must be an integer');
                }
                $normalized[] = [
                    'key' => $value,
                    'rowid' => $rowid,
                    'neq' => self::firstStatInt($sample['neq'] ?? 1),
                    'nlt' => self::firstStatInt($sample['nlt'] ?? 0),
                ];
            }
            usort($normalized, static fn (array $left, array $right): int => self::compareValue($left['key'], $right['key']) ?: ($left['rowid'] <=> $right['rowid']));

            return $normalized;
        }

        private static function firstStatInt(mixed $value): int
        {
            if (is_int($value)) {
                return max(1, $value);
            }
            if (is_string($value)) {
                $parts = preg_split('/\s+/', trim($value)) ?: [];
                return max(1, (int) ($parts[0] ?? 1));
            }
            if (is_array($value)) {
                return max(1, (int) ($value[0] ?? 1));
            }

            return 1;
        }

        /**
         * @param list<array<string,mixed>> $samples
         * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
         */
        private static function currentNextPairs(array $samples): array
        {
            $pairs = [];
            foreach ($samples as $offset => $sample) {
                $pairs[] = ['current' => $sample, 'next' => $samples[$offset + 1] ?? null];
            }

            return $pairs;
        }

        /**
         * @param null|array<string,mixed> $current
         * @param null|array<string,mixed> $next
         * @return array{current:?array<string,mixed>,next:?array<string,mixed>}
         */
        private static function boundaryPair(?array $current, ?array $next): array
        {
            return ['current' => $current, 'next' => $next];
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
                'expression' => $plan['expression'] ?? null,
                'lowerBound' => $plan['lowerBound'] ?? null,
                'upperBound' => $plan['upperBound'] ?? null,
                'estimatedRows' => $plan['estimatedRows'] ?? 0,
                'estimatedCost' => $plan['estimatedCost'] ?? 0,
                'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
                'stat4Estimate' => $plan['stat4Estimate'] ?? null,
                'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
                'nextSource' => $plan['nextSource'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $currentSource
         */
        private static function detail(bool $stale, array $plan, array $currentSource): string
        {
            $action = $stale ? 'REPREPARE STAT4 EXPRESSION RANGE USING CURRENT SOURCE ' : 'REUSE PREPARED STAT4 EXPRESSION RANGE ';

            return $action . self::stringValue($currentSource, 'name', 'current') . ' ' . (string) ($plan['detail'] ?? 'NO PLAN');
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignature(array $source): string
        {
            $parts = [];
            foreach (self::listValue($source, 'indexes') as $index) {
                $parts[] = implode('|', [
                    isset($index['name']) && is_string($index['name']) ? $index['name'] : '',
                    isset($index['rootPage']) && is_int($index['rootPage']) ? (string) $index['rootPage'] : '',
                    isset($index['expression']) && is_string($index['expression']) ? self::normalizeExpression($index['expression']) : '',
                    isset($index['sql']) && is_string($index['sql']) ? preg_replace('/\s+/', ' ', trim($index['sql'])) : '',
                    hash('sha256', serialize($index['stat4Samples'] ?? [])),
                ]);
            }
            sort($parts, SORT_STRING);

            return hash('sha256', implode("\n", $parts));
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
         * @param array<string,mixed> $index
         * @param list<string> $neededColumns
         */
        private static function coversNeededColumns(array $index, array $neededColumns): bool
        {
            if ($neededColumns === []) {
                return false;
            }
            $covering = self::stringList($index['coveringColumns'] ?? [], 'coveringColumns');
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 expression range needed columns must be strings');
                }
                if (!in_array($column, $covering, true)) {
                    return false;
                }
            }

            return true;
        }

        private static function normalizeExpression(string $expression): string
        {
            return strtolower((string) preg_replace('/\s+/', '', $expression));
        }

        private static function indexName(string $sql): string
        {
            if (preg_match('/CREATE\s+INDEX\s+([^\s(]+)/i', $sql, $matches) === 1) {
                return trim($matches[1], '`"[]');
            }

            return 'expression-index';
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function expressionValue(array $data, string $key): string
        {
            $value = self::stringValue($data, $key);
            if (!str_contains($value, '(') || !str_contains($value, ')')) {
                throw new \InvalidArgumentException('SQLite STAT4 expression range needs an indexed expression');
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValue(array $data, string $key, ?string $default = null): string
        {
            $value = $data[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite STAT4 expression range current-source planner needs {$key}");
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
                throw new \InvalidArgumentException("SQLite STAT4 expression range current-source planner needs non-negative integer {$key}");
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function positiveInt(array $data, string $key, int $default): int
        {
            $value = $data[$key] ?? $default;
            if (!is_int($value) || $value < 1) {
                throw new \InvalidArgumentException("SQLite STAT4 expression range current-source planner needs positive integer {$key}");
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
                throw new \InvalidArgumentException("SQLite STAT4 expression range current-source planner needs list {$key}");
            }

            return $value;
        }

        /**
         * @return list<string>
         */
        private static function stringList(mixed $value, string $key): array
        {
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite STAT4 expression range current-source planner needs list {$key}");
            }
            $strings = [];
            foreach ($value as $item) {
                if (!is_string($item) || $item === '') {
                    throw new \InvalidArgumentException("SQLite STAT4 expression range current-source planner needs string {$key} values");
                }
                $strings[] = $item;
            }

            return $strings;
        }

}
