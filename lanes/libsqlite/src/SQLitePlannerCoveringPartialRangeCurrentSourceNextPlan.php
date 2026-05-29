<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext131(array $preparedSource, array $currentSource, array $predicate, array $neededColumns): array
        {
            self::validateNeededColumnsNext131($neededColumns);

            $prepared = self::sourcePlanNext131($preparedSource, $predicate, $neededColumns);
            $current = self::sourcePlanNext131($currentSource, $predicate, $neededColumns);
            $preparedCookie = self::nonNegativeIntNext131($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeIntNext131($currentSource, 'schemaCookie');
            $preparedGeneration = self::nonNegativeIntNext131($preparedSource, 'stat4Generation');
            $currentGeneration = self::nonNegativeIntNext131($currentSource, 'stat4Generation');
            $preparedSignature = self::indexSignatureNext131($preparedSource);
            $currentSignature = self::indexSignatureNext131($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedGeneration !== $currentGeneration
                || $preparedSignature !== $currentSignature;
            $selectedSource = $stale ? 'current' : 'prepared';
            $selected = $stale ? $current : $prepared;
            $ready = self::isReadyNext131($selected);

            return [
                'status' => $ready ? 'covering-partial-range-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $selectedSource,
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedGeneration !== $currentGeneration,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::sourceSummaryNext131($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::sourceSummaryNext131($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'coveredRows' => self::coveredRowsNext131($selectedSource === 'current' ? $currentSource : $preparedSource, $selected, $neededColumns),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentGeneration,
                    'indexSignature' => $currentSignature,
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                    'neededColumnSignature' => implode(',', $neededColumns),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' COVERING PARTIAL RANGE USING '
                    . self::stringValueNext131($selectedSource === 'current' ? $currentSource : $preparedSource, 'name')
                    . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
                'dependencies' => [
                    'SQLiteCreateIndex partial predicate parsing',
                    'SQLiteMultiColumnRangePlan',
                    'sqlite-sqlplanner-covering-partial-range-current-source-next131',
                ],
                'dependency_closure' => 'no new support component needed; next131 reuses native CREATE INDEX parsing, multicolumn range planning, partial predicate implication, STAT4 current/next metadata, and lane-local row materialization',
                'non_overlap' => 'avoids accepted partial expression skip-scan next129, raw-column partial covering skip-scan next125/next127, expression ORDER BY, expression-index range-cost ranking, and parser-level JSON table/SELECT source clusters; this slice covers ordinary covering partial range current-source materialization without skip-scan',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function sourcePlanNext131(array $source, array $predicate, array $neededColumns): array
        {
            $plan = SQLiteMultiColumnRangePlan::choose(self::indexListNext131($source), $predicate, [], $neededColumns);
            if ($plan === null) {
                return [
                    'usable' => false,
                    'partial' => false,
                    'covering' => false,
                    'usesSkipScan' => false,
                    'rangeColumn' => null,
                    'detail' => 'SCAN TABLE; NO USABLE PARTIAL COVERING RANGE',
                ];
            }

            $range = self::combinedRangeForColumnNext131($predicate, (string) ($plan['rangeColumn'] ?? ''))
                ?? self::rangeSummaryNext131($plan['rangeConstraint'] ?? null);

            return $plan + [
                'rangeLower' => $range['lower'],
                'rangeUpper' => $range['upper'],
                'lowerInclusive' => $range['lowerInclusive'],
                'upperInclusive' => $range['upperInclusive'],
                'partialPredicateImplied' => (bool) ($plan['partial'] ?? false),
                'tableLookupRequired' => !((bool) ($plan['covering'] ?? false)),
                'cursorProgram' => self::cursorProgramNext131($plan, $range, $neededColumns),
                'detail' => 'SEARCH ' . (string) ($plan['name'] ?? 'index')
                    . ' USING COVERING PARTIAL RANGE '
                    . (string) ($plan['rangeColumn'] ?? 'unknown'),
            ];
        }

        /**
         * @param array<string,mixed> $selected
         */
        private static function isReadyNext131(array $selected): bool
        {
            return ($selected['usable'] ?? false) === true
                && ($selected['partial'] ?? false) === true
                && ($selected['covering'] ?? false) === true
                && ($selected['usesSkipScan'] ?? false) === false
                && is_string($selected['rangeColumn'] ?? null);
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function coveredRowsNext131(array $source, array $plan, array $neededColumns): array
        {
            if (!self::isReadyNext131($plan)) {
                return [];
            }

            $rows = self::listValueNext131($source, 'rows', false);
            $rangeColumn = (string) $plan['rangeColumn'];
            $lower = $plan['rangeLower'] ?? null;
            $upper = $plan['rangeUpper'] ?? null;
            $lowerInclusive = (bool) ($plan['lowerInclusive'] ?? false);
            $upperInclusive = (bool) ($plan['upperInclusive'] ?? false);
            $covered = [];
            foreach ($rows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite covering partial range current-source rows must be arrays');
                }
                if (!self::rowSatisfiesEqualityConstraintsNext131($row, $plan)) {
                    continue;
                }
                if (!array_key_exists($rangeColumn, $row) || !self::valueInRangeNext131($row[$rangeColumn], $lower, $upper, $lowerInclusive, $upperInclusive)) {
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
                    'covering' => $payload,
                ];
            }

            usort($covered, static function (array $left, array $right): int {
                $comparison = self::compareValuesNext131($left['rangeKey'], $right['rangeKey']);
                if ($comparison !== 0) {
                    return $comparison;
                }

                return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
            });

            foreach ($covered as $offset => $row) {
                $covered[$offset]['nextRowid'] = $covered[$offset + 1]['rowid'] ?? null;
            }

            return $covered;
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $plan
         */
        private static function rowSatisfiesEqualityConstraintsNext131(array $row, array $plan): bool
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
                $operator = $constraint['operator'] ?? null;
                $values = $constraint['values'] ?? null;
                if ($operator === 'point' && self::compareValuesNext131($row[$column], $values) !== 0) {
                    return false;
                }
                if ($operator === 'IN' && is_array($values)) {
                    $matched = false;
                    foreach ($values as $value) {
                        if (self::compareValuesNext131($row[$column], $value) === 0) {
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

        private static function valueInRangeNext131(mixed $value, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive): bool
        {
            if ($lower !== null) {
                $comparison = self::compareValuesNext131($value, $lower);
                if ($comparison < 0 || ($comparison === 0 && !$lowerInclusive)) {
                    return false;
                }
            }
            if ($upper !== null) {
                $comparison = self::compareValuesNext131($value, $upper);
                if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param mixed $constraint
         * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}
         */
        private static function rangeSummaryNext131(mixed $constraint): array
        {
            if (!is_array($constraint)) {
                return ['lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false];
            }
            $operator = (string) ($constraint['operator'] ?? '');
            if (strtoupper($operator) === 'BETWEEN' && is_array($constraint['values'] ?? null)) {
                return [
                    'lower' => $constraint['values']['lower'] ?? null,
                    'upper' => $constraint['values']['upper'] ?? null,
                    'lowerInclusive' => true,
                    'upperInclusive' => true,
                ];
            }
            $value = $constraint['values'] ?? null;

            return match ($operator) {
                'range->' => ['lower' => $value, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false],
                'range->=' => ['lower' => $value, 'upper' => null, 'lowerInclusive' => true, 'upperInclusive' => false],
                'range-<' => ['lower' => null, 'upper' => $value, 'lowerInclusive' => false, 'upperInclusive' => false],
                'range-<=' => ['lower' => null, 'upper' => $value, 'lowerInclusive' => false, 'upperInclusive' => true],
                default => ['lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false],
            };
        }

        /**
         * @return null|array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}
         */
        private static function combinedRangeForColumnNext131(array $predicate, string $column): ?array
        {
            if ($column === '') {
                return null;
            }

            $lower = null;
            $upper = null;
            $lowerInclusive = false;
            $upperInclusive = false;
            foreach (self::flattenAndTermsNext131($predicate) as $term) {
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
                    if ($lower === null || self::compareValuesNext131($candidate, $lower) > 0) {
                        $lower = $candidate;
                        $lowerInclusive = $operator === '>=';
                    }
                    continue;
                }
                if ($operator === '<' || $operator === '<=') {
                    $candidate = $term['right'] ?? null;
                    if ($upper === null || self::compareValuesNext131($candidate, $upper) < 0) {
                        $upper = $candidate;
                        $upperInclusive = $operator === '<=';
                    }
                }
            }

            if ($lower === null && $upper === null) {
                return null;
            }

            return [
                'lower' => $lower,
                'upper' => $upper,
                'lowerInclusive' => $lowerInclusive,
                'upperInclusive' => $upperInclusive,
            ];
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function flattenAndTermsNext131(array $predicate): array
        {
            if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
                return [$predicate];
            }

            $terms = $predicate['terms'] ?? null;
            if (!is_array($terms) || !array_is_list($terms)) {
                return [$predicate];
            }

            $flattened = [];
            foreach ($terms as $term) {
                if (is_array($term)) {
                    array_push($flattened, ...self::flattenAndTermsNext131($term));
                }
            }

            return $flattened;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $range
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function cursorProgramNext131(array $plan, array $range, array $neededColumns): array
        {
            if (($plan['usable'] ?? false) !== true) {
                return [['opcode' => 'Rewind', 'source' => 'table']];
            }

            $rangeColumn = (string) ($plan['rangeColumn'] ?? '');
            $program = [[
                'opcode' => 'OpenRead',
                'source' => 'index',
                'index' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
            ]];
            if ($range['lower'] !== null) {
                $program[] = [
                    'opcode' => $range['lowerInclusive'] ? 'SeekGE' : 'SeekGT',
                    'source' => 'index',
                    'column' => $rangeColumn,
                    'key' => $range['lower'],
                ];
            }
            if ($range['upper'] !== null) {
                $program[] = [
                    'opcode' => $range['upperInclusive'] ? 'IdxGT' : 'IdxGE',
                    'source' => 'index',
                    'column' => $rangeColumn,
                    'key' => $range['upper'],
                ];
            }
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => 'index', 'column' => $column];
            }
            $program[] = ['opcode' => 'Next', 'source' => 'index'];

            return $program;
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function sourceSummaryNext131(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValueNext131($source, 'name'),
                'schemaCookie' => self::nonNegativeIntNext131($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeIntNext131($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'status' => ($plan['usable'] ?? false) === true ? 'usable' : 'unusable',
                'partial' => (bool) ($plan['partial'] ?? false),
                'covering' => (bool) ($plan['covering'] ?? false),
                'rangeColumn' => $plan['rangeColumn'] ?? null,
                'rangeLower' => $plan['rangeLower'] ?? null,
                'rangeUpper' => $plan['rangeUpper'] ?? null,
                'estimatedRows' => $plan['estimatedRows'] ?? null,
                'estimatedCost' => $plan['estimatedCost'] ?? null,
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @return list<array<string,mixed>>
         */
        private static function indexListNext131(array $source): array
        {
            $indexes = self::listValueNext131($source, 'indexes');
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite covering partial range source indexes must be arrays');
                }
            }

            return $indexes;
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignatureNext131(array $source): string
        {
            $parts = [];
            foreach (self::indexListNext131($source) as $index) {
                $parts[] = [
                    'name' => $index['name'] ?? null,
                    'rootPage' => $index['rootPage'] ?? null,
                    'sql' => $index['sql'] ?? null,
                    'estimatedRows' => $index['estimatedRows'] ?? null,
                    'stat4Samples' => $index['stat4Samples'] ?? [],
                ];
            }

            return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
        }

        /**
         * @param list<string> $columns
         */
        private static function validateNeededColumnsNext131(array $columns): void
        {
            foreach ($columns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite covering partial range needed columns must be non-empty names');
                }
            }
        }

        /**
         * @return list<mixed>
         */
        private static function listValueNext131(array $row, string $key, bool $required = true): array
        {
            $value = $row[$key] ?? [];
            if (!$required && $value === []) {
                return [];
            }
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException("SQLite covering partial range {$key} must be a list");
            }

            return $value;
        }

        private static function stringValueNext131(array $row, string $key): string
        {
            $value = $row[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite covering partial range {$key} must be a non-empty string");
            }

            return $value;
        }

        private static function nonNegativeIntNext131(array $row, string $key): int
        {
            $value = $row[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite covering partial range {$key} must be a non-negative integer");
            }

            return $value;
        }

        private static function compareValuesNext131(mixed $left, mixed $right): int
        {
            if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
                return $left <=> $right;
            }

            return strcmp((string) $left, (string) $right);
        }

}
