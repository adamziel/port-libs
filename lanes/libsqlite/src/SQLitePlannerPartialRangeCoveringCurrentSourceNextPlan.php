<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializePartialRangeCovering(array $preparedSource, array $currentSource, array $predicate, array $neededColumns): array
        {
            $base = SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan::materialize(
                $preparedSource,
                $currentSource,
                $predicate,
                $neededColumns,
            );
            $selectedSource = self::stringValue($base, 'selectedSource');
            $source = $selectedSource === 'current' ? $currentSource : $preparedSource;
            $filteredRows = self::predicateCoveredRows($source, $base['coveredRows'] ?? [], $predicate, $neededColumns);
            $selectedPlan = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
            $ready = ($base['status'] ?? null) === 'covering-partial-range-current-source-ready'
                && ($selectedPlan['partial'] ?? false) === true
                && ($selectedPlan['covering'] ?? false) === true
                && ($selectedPlan['usesSkipScan'] ?? false) === false;

            $base['status'] = $ready ? 'partial-range-covering-current-source-ready' : 'requires-next-stage';
            $base['coveredRowsBeforePredicate'] = $base['coveredRows'] ?? [];
            $base['coveredRows'] = $filteredRows;
            $base['partialPredicateFilteredRowids'] = self::filteredRowids($base['coveredRowsBeforePredicate'], $filteredRows);
            $base['predicateFilteredRowCount'] = count($base['partialPredicateFilteredRowids']);
            $base['partialPredicateRechecked'] = true;
            $base['currentSourceNextRows'] = self::currentRowsWithNext($filteredRows);
            $base['selectedPlan']['cursorProgram'] = self::cursorProgram($selectedPlan, $neededColumns, $base['predicateFilteredRowCount']);
            $base['selectedPlan']['residualPredicateRequired'] = true;
            $base['selectedPlan']['partialPredicateRechecked'] = true;
            $base['selectedPlan']['nextSource'] = $ready ? 'covering-partial-index-current-source' : 'table-rowid-lookup';
            $base['detail'] = ($base['stalePreparedStatement'] ?? false)
                ? 'REPREPARE PARTIAL RANGE COVERING CURRENT SOURCE ' . (string) ($selectedPlan['name'] ?? 'NO INDEX')
                : 'REUSE PARTIAL RANGE COVERING CURRENT SOURCE ' . (string) ($selectedPlan['name'] ?? 'NO INDEX');
            $base['dependencies'] = [
                'SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan',
                'SQLiteMultiColumnRangePlan',
                'sqlite-sqlplanner-partial-range-covering-current-source',
            ];
            $base['dependency_closure'] = 'no new support component needed; native partial range covering reuses native partial range planning and adds predicate-exact covering stream materialization for current-source rows';
            $base['non_overlap'] = 'avoids accepted ordinary partial range materialization, STAT4 partial range reprepare, STAT4 partial expression planning, expression ORDER BY, range-cost, skip-scan, and JSON/VFS/WAL clusters; this slice filters covering current-source rows by full partial predicate terms not present in the index key';

            return $base;
        }

        /**
         * @param array<string,mixed> $source
         * @param mixed $coveredRows
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function predicateCoveredRows(array $source, mixed $coveredRows, array $predicate, array $neededColumns): array
        {
            if (!is_array($coveredRows) || !array_is_list($coveredRows)) {
                return [];
            }
            $sourceRows = self::sourceRowsByOffset($source);
            $filtered = [];
            foreach ($coveredRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $offset = $row['sourceOffset'] ?? null;
                if (!is_int($offset) || !isset($sourceRows[$offset])) {
                    continue;
                }
                if (!self::rowSatisfiesPredicate($sourceRows[$offset], $predicate)) {
                    continue;
                }
                $payload = [];
                foreach ($neededColumns as $column) {
                    $payload[$column] = $sourceRows[$offset][$column] ?? null;
                }
                $row['covering'] = $payload;
                $filtered[] = $row;
            }

            foreach ($filtered as $offset => $row) {
                $filtered[$offset]['nextRowid'] = $filtered[$offset + 1]['rowid'] ?? null;
            }

            return $filtered;
        }

        /**
         * @param array<string,mixed> $source
         * @return array<int,array<string,mixed>>
         */
        private static function sourceRowsByOffset(array $source): array
        {
            $rows = $source['rows'] ?? [];
            if (!is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite partial range covering current-source rows must be a list');
            }

            $indexed = [];
            foreach ($rows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite partial range covering current-source rows must be arrays');
                }
                $indexed[$offset] = $row;
            }

            return $indexed;
        }

        private static function rowSatisfiesPredicate(array $row, array $predicate): bool
        {
            $operator = strtoupper((string) ($predicate['operator'] ?? ''));
            if ($operator === 'AND') {
                foreach (self::predicateTerms($predicate) as $term) {
                    if (!self::rowSatisfiesPredicate($row, $term)) {
                        return false;
                    }
                }

                return true;
            }
            if ($operator === 'OR') {
                foreach (self::predicateTerms($predicate) as $term) {
                    if (self::rowSatisfiesPredicate($row, $term)) {
                        return true;
                    }
                }

                return false;
            }

            $column = self::columnOperand($predicate['left'] ?? null);
            if ($column === null || !array_key_exists($column, $row)) {
                return true;
            }
            $value = $row[$column];

            return match ($operator) {
                '=', '==' => self::compareValues($value, $predicate['right'] ?? null) === 0,
                '!=' , '<>' => self::compareValues($value, $predicate['right'] ?? null) !== 0,
                '>' => self::compareValues($value, $predicate['right'] ?? null) > 0,
                '>=' => self::compareValues($value, $predicate['right'] ?? null) >= 0,
                '<' => self::compareValues($value, $predicate['right'] ?? null) < 0,
                '<=' => self::compareValues($value, $predicate['right'] ?? null) <= 0,
                'BETWEEN' => self::compareValues($value, $predicate['lower'] ?? null) >= 0
                    && self::compareValues($value, $predicate['upper'] ?? null) <= 0,
                'IN' => self::inList($value, $predicate['values'] ?? null),
                'IS NOT NULL' => $value !== null,
                'IS NULL' => $value === null,
                default => true,
            };
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function predicateTerms(array $predicate): array
        {
            $terms = $predicate['terms'] ?? [];
            if (!is_array($terms) || !array_is_list($terms)) {
                throw new \InvalidArgumentException('SQLite partial range covering current-source predicate terms must be a list');
            }
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite partial range covering current-source predicate terms must be arrays');
                }
            }

            return $terms;
        }

        private static function columnOperand(mixed $operand): ?string
        {
            if (!is_array($operand)) {
                return null;
            }
            $column = $operand['column'] ?? null;

            return is_string($column) && $column !== '' ? $column : null;
        }

        private static function inList(mixed $value, mixed $values): bool
        {
            if (!is_array($values) || !array_is_list($values)) {
                return false;
            }
            foreach ($values as $candidate) {
                if (self::compareValues($value, $candidate) === 0) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @param list<array<string,mixed>> $before
         * @param list<array<string,mixed>> $after
         * @return list<mixed>
         */
        private static function filteredRowids(array $before, array $after): array
        {
            $afterRowids = [];
            foreach ($after as $row) {
                $afterRowids[] = $row['rowid'] ?? null;
            }

            $filtered = [];
            foreach ($before as $row) {
                $rowid = $row['rowid'] ?? null;
                if (!in_array($rowid, $afterRowids, true)) {
                    $filtered[] = $rowid;
                }
            }

            return $filtered;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array<string,mixed>>
         */
        private static function currentRowsWithNext(array $rows): array
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
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function cursorProgram(array $plan, array $neededColumns, int $filteredRowCount): array
        {
            $program = $plan['cursorProgram'] ?? [];
            if (!is_array($program) || !array_is_list($program)) {
                $program = [];
            }
            $program[] = [
                'opcode' => 'IfNot',
                'source' => 'partial-predicate',
                'filteredRows' => $filteredRowCount,
            ];
            foreach ($neededColumns as $column) {
                $program[] = [
                    'opcode' => 'Column',
                    'source' => 'covering-partial-index-current-source',
                    'column' => $column,
                ];
            }

            return $program;
        }

        private static function stringValue(array $row, string $key): string
        {
            $value = $row[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite partial range covering current-source {$key} must be a non-empty string");
            }

            return $value;
        }

        private static function compareValues(mixed $left, mixed $right): int
        {
            if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
                return $left <=> $right;
            }

            return strcmp((string) $left, (string) $right);
        }

}
