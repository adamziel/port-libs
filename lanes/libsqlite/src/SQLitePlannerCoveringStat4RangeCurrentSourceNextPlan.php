<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeNext138(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns
        ): array {
            $base = SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan::materializeNext119(
                $preparedSource,
                $currentSource,
                $predicate,
                $orderBy,
                $neededColumns,
            );
            $selectedSource = (string) ($base['selectedSource'] ?? 'prepared');
            $source = $selectedSource === 'current' ? $currentSource : $preparedSource;
            $plan = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
            $rows = self::coveredRowsNext138($source, $plan, $predicate, $neededColumns);
            $bucketed = self::stat4BucketsNext138($plan, $rows);
            $ready = ($base['status'] ?? null) === 'covering-range-order-current-source-ready'
                && ($plan['covering'] ?? false) === true
                && ($plan['stat4Used'] ?? false) === true
                && ($plan['partial'] ?? false) === false
                && $rows !== [];

            return array_replace($base, [
                'status' => $ready ? 'covering-stat4-range-current-source-next138-ready' : 'requires-next-stage',
                'coveredRows' => $rows,
                'currentNextRows' => self::currentNextRowsNext138($rows),
                'stat4RangeBuckets' => $bucketed,
                'stat4BucketCount' => count($bucketed),
                'stat4RowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
                'tableLookupElided' => $ready,
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
                'selectedPlan' => array_replace($plan, [
                    'coveredRowCount' => count($rows),
                    'stat4BucketCount' => count($bucketed),
                    'stat4RangeKeys' => array_column($bucketed, 'key'),
                    'rangeRowsAdmitted' => array_column($rows, 'rowid'),
                    'nextSource' => $ready ? 'covering-stat4-range-current-source' : 'table-rowid-lookup',
                    'cursorProgram' => self::cursorProgramNext138($base, $ready),
                ]),
                'currentSourceFence' => array_replace(
                    is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                    [
                        'rowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
                        'stat4BucketSignature' => hash('sha256', json_encode(array_column($bucketed, 'key'), JSON_THROW_ON_ERROR)),
                        'coveringSignature' => implode(',', $neededColumns),
                    ],
                ),
                'detail' => (($base['stalePreparedStatement'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' COVERING STAT4 RANGE CURRENT SOURCE '
                    . (string) ($plan['name'] ?? 'NO INDEX'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                    [
                        'SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan',
                        'sqlite-sqlplanner-covering-stat4-range-current-source-next138',
                    ],
                ))),
                'dependency_closure' => 'no new support component needed; next138 reuses native covering range-order planning and adds non-partial STAT4 row-stream materialization',
                'non_overlap' => 'avoids accepted next131 partial range, next135 STAT4 partial covering, next136 partial residual recheck, expression-index range cost, expression ORDER BY, skip-scan, JSON, VFS, WAL, and B-tree clusters; this slice covers non-partial covering STAT4 range row admission',
            ]);
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @return list<array<string,mixed>>
         */
        private static function coveredRowsNext138(array $source, array $plan, array $predicate, array $neededColumns): array
        {
            $rangeColumn = (string) ($plan['rangeColumn'] ?? '');
            if ($rangeColumn === '' || ($plan['covering'] ?? false) !== true) {
                return [];
            }

            $rows = $source['rows'] ?? [];
            if (!is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite covering STAT4 range current-source rows must be a list');
            }

            $covered = [];
            foreach ($rows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite covering STAT4 range current-source rows must be arrays');
                }
                if (!self::rowSatisfiesPointTermsNext138($row, $predicate)) {
                    continue;
                }
                if (!array_key_exists($rangeColumn, $row) || !self::withinRangeNext138($row[$rangeColumn], $plan)) {
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
                $comparison = self::compareNext138($left['rangeKey'] ?? null, $right['rangeKey'] ?? null);
                if ($comparison !== 0) {
                    return $comparison;
                }

                return ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0));
            });

            return $covered;
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $plan
         */
        private static function rowSatisfiesPointTermsNext138(array $row, array $predicate): bool
        {
            foreach (self::flattenAndTermsNext138($predicate) as $term) {
                $operator = strtoupper((string) ($term['operator'] ?? ''));
                if (!in_array($operator, ['=', '==', 'IS'], true)) {
                    continue;
                }
                $left = $term['left'] ?? null;
                if (!is_array($left)) {
                    continue;
                }
                $column = $left['column'] ?? null;
                if (!is_string($column) || !array_key_exists($column, $row)) {
                    return false;
                }
                if (self::compareNext138($row[$column], $term['right'] ?? null) !== 0) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param array<string,mixed> $predicate
         * @return list<array<string,mixed>>
         */
        private static function flattenAndTermsNext138(array $predicate): array
        {
            if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
                return [$predicate];
            }
            $terms = $predicate['terms'] ?? [];
            if (!is_array($terms) || !array_is_list($terms)) {
                throw new \InvalidArgumentException('SQLite covering STAT4 range current-source predicate terms must be a list');
            }

            $flat = [];
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite covering STAT4 range current-source predicate terms must be arrays');
                }
                array_push($flat, ...self::flattenAndTermsNext138($term));
            }

            return $flat;
        }

        /**
         * @param array<string,mixed> $plan
         */
        private static function withinRangeNext138(mixed $value, array $plan): bool
        {
            $lower = $plan['rangeLower'] ?? null;
            if ($lower !== null) {
                $comparison = self::compareNext138($value, $lower);
                if ($comparison < 0 || ($comparison === 0 && ($plan['lowerInclusive'] ?? false) !== true)) {
                    return false;
                }
            }
            $upper = $plan['rangeUpper'] ?? null;
            if ($upper !== null) {
                $comparison = self::compareNext138($value, $upper);
                if ($comparison > 0 || ($comparison === 0 && ($plan['upperInclusive'] ?? false) !== true)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param array<string,mixed> $plan
         * @param list<array<string,mixed>> $rows
         * @return list<array<string,mixed>>
         */
        private static function stat4BucketsNext138(array $plan, array $rows): array
        {
            $rowKeys = array_fill_keys(array_map(static fn (array $row): string => self::keySignatureNext138($row['rangeKey'] ?? null), $rows), true);
            $buckets = [];
            foreach (($plan['stat4CurrentNext'] ?? []) as $pair) {
                if (!is_array($pair) || !isset($pair['current']) || !is_array($pair['current'])) {
                    continue;
                }
                $key = $pair['current']['key'] ?? null;
                if (!isset($rowKeys[self::keySignatureNext138($key)])) {
                    continue;
                }
                $buckets[] = [
                    'key' => $key,
                    'nextKey' => is_array($pair['next'] ?? null) ? ($pair['next']['key'] ?? null) : null,
                    'neq' => (int) ($pair['neq'] ?? 0),
                    'nlt' => (int) ($pair['nlt'] ?? 0),
                    'ndlt' => (int) ($pair['ndlt'] ?? 0),
                    'advance' => (string) ($pair['advance'] ?? 'next'),
                ];
            }

            return $buckets;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
         */
        private static function currentNextRowsNext138(array $rows): array
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
         * @param array<string,mixed> $base
         * @return list<array<string,mixed>>
         */
        private static function cursorProgramNext138(array $base, bool $ready): array
        {
            $tape = is_array($base['cursorTape'] ?? null) ? $base['cursorTape'] : [];
            $program = is_array($tape['program'] ?? null) ? $tape['program'] : [];
            array_unshift($program, ['opcode' => 'Stat4RangeRecheck', 'source' => 'current-source']);
            if (!$ready) {
                $program[] = ['opcode' => 'DeferredSeek', 'source' => 'table'];
            }

            return $program;
        }

        private static function compareNext138(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return (float) $left <=> (float) $right;
            }

            return strcmp((string) $left, (string) $right) <=> 0;
        }

        private static function keySignatureNext138(mixed $value): string
        {
            return get_debug_type($value) . ':' . (string) $value;
        }

}
