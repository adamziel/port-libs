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
        public static function compareNext104(array $preparedSource, array $currentSource, array $predicate, array $neededColumns = []): array
        {
            $prepared = self::sourcePlanNext104($preparedSource, $predicate, $neededColumns);
            $current = self::sourcePlanNext104($currentSource, $predicate, $neededColumns);

            $preparedCookie = self::nonNegativeIntNext104($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext104($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeIntNext104($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeIntNext104($currentSource, 'stat4Generation');
            $preparedIndexes = self::indexSignatureNext104($preparedSource);
            $currentIndexes = self::indexSignatureNext104($currentSource);
            $preparedProjection = self::projectionSignatureNext104($preparedSource, $neededColumns);
            $currentProjection = self::projectionSignatureNext104($currentSource, $neededColumns);
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
                'preparedSource' => self::sourceSummaryNext104($preparedSource, $prepared, $preparedProjection),
                'currentSource' => self::sourceSummaryNext104($currentSource, $current, $currentProjection),
                'selectedPlan' => $selected,
                'expressionRangePlan' => ($selected['status'] ?? null) === 'usable' && ($selected['expressionRangeUsable'] ?? false) === true,
                'detail' => self::detailNext104($stale, $selected, $currentSource),
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
        private static function sourcePlanNext104(array $source, array $predicate, array $neededColumns): array
        {
            $plans = [];
            foreach (self::listValueNext104($source, 'indexes') as $index) {
                $plan = self::indexPlanNext104($index, $predicate, $neededColumns);
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
        private static function indexPlanNext104(array $index, array $predicate, array $neededColumns): ?array
        {
            $expression = self::expressionValueNext104($index, 'expression');
            $constraints = self::expressionConstraintsNext104($predicate, $expression);
            if ($constraints['lower'] === null && $constraints['upper'] === null) {
                return null;
            }

            $samples = self::samplesNext104($index['stat4Samples'] ?? []);
            $range = self::sampleRangeNext104($samples, $constraints['lower'], $constraints['upper']);
            $matched = $range['matched'];
            $stat4Estimate = $matched === [] ? 1 : max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $matched)));
            $estimatedRows = min(self::positiveIntNext104($index, 'estimatedRows', 1000), $stat4Estimate);
            $covering = self::coversNeededColumnsNext104($index, $neededColumns);
            $cost = $estimatedRows + 32 - ($covering ? 14 : 0) - ($matched !== [] ? 6 : 0);

            return [
                'name' => self::stringValueNext104($index, 'name', self::indexNameNext104((string) ($index['sql'] ?? 'expression-index'))),
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
                'stat4MatchedCurrentNext' => self::currentNextPairsNext104($matched),
                'stat4RangeCurrentNext' => [
                    'lower' => self::boundaryPairNext104($range['beforeLower'], $range['firstInRange']),
                    'upper' => self::boundaryPairNext104($range['lastInRange'], $range['afterUpper']),
                ],
                'detail' => 'SEARCH ' . self::stringValueNext104($index, 'name', self::indexNameNext104((string) ($index['sql'] ?? 'expression-index'))) . ' USING STAT4 EXPRESSION RANGE ' . $expression,
            ];
        }

        /**
         * @param array<string,mixed> $predicate
         * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}
         */
        private static function expressionConstraintsNext104(array $predicate, string $expression): array
        {
            $terms = self::flattenAndTermsNext104($predicate);
            $lower = null;
            $upper = null;
            $lowerInclusive = false;
            $upperInclusive = false;
            foreach ($terms as $term) {
                $operator = strtoupper(self::stringValueNext104($term, 'operator'));
                if (!self::sameExpressionNext104($term['left'] ?? null, $expression)) {
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
        private static function flattenAndTermsNext104(array $predicate): array
        {
            $operator = strtoupper(self::stringValueNext104($predicate, 'operator'));
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
                array_push($flattened, ...self::flattenAndTermsNext104($term));
            }

            return $flattened;
        }

        private static function sameExpressionNext104(mixed $operand, string $expression): bool
        {
            if (is_array($operand) && isset($operand['expression']) && is_string($operand['expression'])) {
                return self::normalizeExpressionNext104($operand['expression']) === self::normalizeExpressionNext104($expression);
            }

            return false;
        }

        /**
         * @param list<array<string,mixed>> $samples
         * @return array{beforeLower:?array<string,mixed>,firstInRange:?array<string,mixed>,lastInRange:?array<string,mixed>,afterUpper:?array<string,mixed>,matched:list<array<string,mixed>>}
         */
        private static function sampleRangeNext104(array $samples, mixed $lower, mixed $upper): array
        {
            $beforeLower = null;
            $first = null;
            $last = null;
            $afterUpper = null;
            $matched = [];
            foreach ($samples as $sample) {
                $key = $sample['key'];
                if ($lower !== null && self::compareValueNext104($key, $lower) < 0) {
                    $beforeLower = $sample;
                    continue;
                }
                if ($upper !== null && self::compareValueNext104($key, $upper) >= 0) {
                    $afterUpper ??= $sample;
                    continue;
                }
                $first ??= $sample;
                $last = $sample;
                $matched[] = $sample;
            }

            return ['beforeLower' => $beforeLower, 'firstInRange' => $first, 'lastInRange' => $last, 'afterUpper' => $afterUpper, 'matched' => $matched];
        }

        private static function compareValueNext104(mixed $left, mixed $right): int
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
        private static function samplesNext104(mixed $samples): array
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
                    'neq' => self::firstStatIntNext104($sample['neq'] ?? 1),
                    'nlt' => self::firstStatIntNext104($sample['nlt'] ?? 0),
                ];
            }
            usort($normalized, static fn (array $left, array $right): int => self::compareValueNext104($left['key'], $right['key']) ?: ($left['rowid'] <=> $right['rowid']));

            return $normalized;
        }

        private static function firstStatIntNext104(mixed $value): int
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
        private static function currentNextPairsNext104(array $samples): array
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
        private static function boundaryPairNext104(?array $current, ?array $next): array
        {
            return ['current' => $current, 'next' => $next];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummaryNext104(array $source, array $plan, string $projectionSignature): array
        {
            return [
                'name' => self::stringValueNext104($source, 'name', 'source'),
                'schemaCookie' => self::nonNegativeIntNext104($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext104($source, 'stat4Generation'),
                'projectionSignature' => $projectionSignature,
                'indexSignature' => self::indexSignatureNext104($source),
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
        private static function detailNext104(bool $stale, array $plan, array $currentSource): string
        {
            $action = $stale ? 'REPREPARE STAT4 EXPRESSION RANGE USING CURRENT SOURCE ' : 'REUSE PREPARED STAT4 EXPRESSION RANGE ';

            return $action . self::stringValueNext104($currentSource, 'name', 'current') . ' ' . (string) ($plan['detail'] ?? 'NO PLAN');
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignatureNext104(array $source): string
        {
            $parts = [];
            foreach (self::listValueNext104($source, 'indexes') as $index) {
                $parts[] = implode('|', [
                    isset($index['name']) && is_string($index['name']) ? $index['name'] : '',
                    isset($index['rootPage']) && is_int($index['rootPage']) ? (string) $index['rootPage'] : '',
                    isset($index['expression']) && is_string($index['expression']) ? self::normalizeExpressionNext104($index['expression']) : '',
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
        private static function projectionSignatureNext104(array $source, array $neededColumns): string
        {
            $columns = self::stringListNext104($source['coveringColumns'] ?? $neededColumns, 'coveringColumns');
            sort($columns, SORT_STRING);

            return implode("\0", $columns);
        }

        /**
         * @param array<string,mixed> $index
         * @param list<string> $neededColumns
         */
        private static function coversNeededColumnsNext104(array $index, array $neededColumns): bool
        {
            if ($neededColumns === []) {
                return false;
            }
            $covering = self::stringListNext104($index['coveringColumns'] ?? [], 'coveringColumns');
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

        private static function normalizeExpressionNext104(string $expression): string
        {
            return strtolower((string) preg_replace('/\s+/', '', $expression));
        }

        private static function indexNameNext104(string $sql): string
        {
            if (preg_match('/CREATE\s+INDEX\s+([^\s(]+)/i', $sql, $matches) === 1) {
                return trim($matches[1], '`"[]');
            }

            return 'expression-index';
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function expressionValueNext104(array $data, string $key): string
        {
            $value = self::stringValueNext104($data, $key);
            if (!str_contains($value, '(') || !str_contains($value, ')')) {
                throw new \InvalidArgumentException('SQLite STAT4 expression range needs an indexed expression');
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $data
         */
        private static function stringValueNext104(array $data, string $key, ?string $default = null): string
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
        private static function nonNegativeIntNext104(array $data, string $key): int
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
        private static function positiveIntNext104(array $data, string $key, int $default): int
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
        private static function listValueNext104(array $data, string $key): array
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
        private static function stringListNext104(mixed $value, string $key): array
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
