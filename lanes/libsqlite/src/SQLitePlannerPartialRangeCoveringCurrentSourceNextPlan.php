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
        public static function materializeNext136(array $preparedSource, array $currentSource, array $predicate, array $neededColumns): array
        {
            $base = SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan::materializeNext131(
                $preparedSource,
                $currentSource,
                $predicate,
                $neededColumns,
            );
            $selectedSource = self::stringValueNext136($base, 'selectedSource');
            $source = $selectedSource === 'current' ? $currentSource : $preparedSource;
            $filteredRows = self::predicateCoveredRowsNext136($source, $base['coveredRows'] ?? [], $predicate, $neededColumns);
            $selectedPlan = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
            $ready = ($base['status'] ?? null) === 'covering-partial-range-current-source-ready'
                && ($selectedPlan['partial'] ?? false) === true
                && ($selectedPlan['covering'] ?? false) === true
                && ($selectedPlan['usesSkipScan'] ?? false) === false;

            $base['status'] = $ready ? 'partial-range-covering-current-source-ready' : 'requires-next-stage';
            $base['coveredRowsBeforePredicate'] = $base['coveredRows'] ?? [];
            $base['coveredRows'] = $filteredRows;
            $base['partialPredicateFilteredRowids'] = self::filteredRowidsNext136($base['coveredRowsBeforePredicate'], $filteredRows);
            $base['predicateFilteredRowCount'] = count($base['partialPredicateFilteredRowids']);
            $base['partialPredicateRechecked'] = true;
            $base['currentSourceNextRows'] = self::currentNextRowsNext136($filteredRows);
            $base['selectedPlan']['cursorProgram'] = self::cursorProgramNext136($selectedPlan, $neededColumns, $base['predicateFilteredRowCount']);
            $base['selectedPlan']['residualPredicateRequired'] = true;
            $base['selectedPlan']['partialPredicateRechecked'] = true;
            $base['selectedPlan']['nextSource'] = $ready ? 'covering-partial-index-current-source' : 'table-rowid-lookup';
            $base['detail'] = ($base['stalePreparedStatement'] ?? false)
                ? 'REPREPARE PARTIAL RANGE COVERING CURRENT SOURCE ' . (string) ($selectedPlan['name'] ?? 'NO INDEX')
                : 'REUSE PARTIAL RANGE COVERING CURRENT SOURCE ' . (string) ($selectedPlan['name'] ?? 'NO INDEX');
            $base['dependencies'] = [
                'SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan',
                'SQLiteMultiColumnRangePlan',
                'sqlite-sqlplanner-partial-range-covering-current-source-next136',
            ];
            $base['dependency_closure'] = 'no new support component needed; next136 reuses native partial range planning and adds predicate-exact covering stream materialization for current-source rows';
            $base['non_overlap'] = 'avoids accepted next131 ordinary partial range materialization, next124 STAT4 partial range reprepare, next133 STAT4 partial expression planning, expression ORDER BY, range-cost, skip-scan, and JSON/VFS/WAL clusters; this slice filters covering current-source rows by full partial predicate terms not present in the index key';

            return $base;
        }

        /**
         * @param array<string,mixed> $source
         * @param mixed $coveredRows
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function predicateCoveredRowsNext136(array $source, mixed $coveredRows, array $predicate, array $neededColumns): array
        {
            if (!is_array($coveredRows) || !array_is_list($coveredRows)) {
                return [];
            }
            $sourceRows = self::sourceRowsByOffsetNext136($source);
            $filtered = [];
            foreach ($coveredRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $offset = $row['sourceOffset'] ?? null;
                if (!is_int($offset) || !isset($sourceRows[$offset])) {
                    continue;
                }
                if (!self::rowSatisfiesPredicateNext136($sourceRows[$offset], $predicate)) {
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
        private static function sourceRowsByOffsetNext136(array $source): array
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

        private static function rowSatisfiesPredicateNext136(array $row, array $predicate): bool
        {
            $operator = strtoupper((string) ($predicate['operator'] ?? ''));
            if ($operator === 'AND') {
                foreach (self::termsNext136($predicate) as $term) {
                    if (!self::rowSatisfiesPredicateNext136($row, $term)) {
                        return false;
                    }
                }

                return true;
            }
            if ($operator === 'OR') {
                foreach (self::termsNext136($predicate) as $term) {
                    if (self::rowSatisfiesPredicateNext136($row, $term)) {
                        return true;
                    }
                }

                return false;
            }

            $column = self::columnOperandNext136($predicate['left'] ?? null);
            if ($column === null || !array_key_exists($column, $row)) {
                return true;
            }
            $value = $row[$column];

            return match ($operator) {
                '=', '==' => self::compareNext136($value, $predicate['right'] ?? null) === 0,
                '!=' , '<>' => self::compareNext136($value, $predicate['right'] ?? null) !== 0,
                '>' => self::compareNext136($value, $predicate['right'] ?? null) > 0,
                '>=' => self::compareNext136($value, $predicate['right'] ?? null) >= 0,
                '<' => self::compareNext136($value, $predicate['right'] ?? null) < 0,
                '<=' => self::compareNext136($value, $predicate['right'] ?? null) <= 0,
                'BETWEEN' => self::compareNext136($value, $predicate['lower'] ?? null) >= 0
                    && self::compareNext136($value, $predicate['upper'] ?? null) <= 0,
                'IN' => self::inListNext136($value, $predicate['values'] ?? null),
                'IS NOT NULL' => $value !== null,
                'IS NULL' => $value === null,
                default => true,
            };
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function termsNext136(array $predicate): array
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

        private static function columnOperandNext136(mixed $operand): ?string
        {
            if (!is_array($operand)) {
                return null;
            }
            $column = $operand['column'] ?? null;

            return is_string($column) && $column !== '' ? $column : null;
        }

        private static function inListNext136(mixed $value, mixed $values): bool
        {
            if (!is_array($values) || !array_is_list($values)) {
                return false;
            }
            foreach ($values as $candidate) {
                if (self::compareNext136($value, $candidate) === 0) {
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
        private static function filteredRowidsNext136(array $before, array $after): array
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
        private static function currentNextRowsNext136(array $rows): array
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
        private static function cursorProgramNext136(array $plan, array $neededColumns, int $filteredRowCount): array
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

        private static function stringValueNext136(array $row, string $key): string
        {
            $value = $row[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite partial range covering current-source {$key} must be a non-empty string");
            }

            return $value;
        }

        private static function compareNext136(mixed $left, mixed $right): int
        {
            if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
                return $left <=> $right;
            }

            return strcmp((string) $left, (string) $right);
        }

}
