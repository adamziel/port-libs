<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array{column:string,direction?:string}> $orderBy
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        public static function materializeCurrentSourceCursor(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $orderBy,
            array $neededColumns
        ): array {
            $base = SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan::materializeCoveringStat4Range(
                $preparedSource,
                $currentSource,
                $predicate,
                $orderBy,
                $neededColumns,
            );

            $rows = self::listOfArrays($base, 'coveredRows', false);
            $ready = ($base['status'] ?? null) === 'covering-stat4-range-current-source-next138-ready'
                && ($base['selectedSource'] ?? null) === 'current'
                && ($base['tableLookupElided'] ?? false) === true
                && $rows !== [];
            $duplicateGroups = self::duplicateRangeGroups($rows);
            $steps = self::cursorSteps($rows, $base);
            $currentBuckets = self::currentBoundaryKeys($base);
            $preparedBuckets = self::preparedBoundaryKeys($preparedSource, $base);

            return array_replace($base, [
                'status' => $ready ? 'stat4-covering-range-current-source-cursor-ready' : 'requires-next-stage',
                'currentSourceNextCursor' => [
                    'source' => $base['selectedSource'] ?? null,
                    'rangeColumn' => $base['selectedPlan']['rangeColumn'] ?? null,
                    'rowids' => array_column($rows, 'rowid'),
                    'rangeKeys' => array_column($rows, 'rangeKey'),
                    'steps' => $steps,
                    'stepCount' => count($steps),
                    'duplicateRangeGroups' => $duplicateGroups,
                    'duplicateRangeGroupCount' => count($duplicateGroups),
                    'stableTieBreak' => self::stableTieBreak($rows),
                    'usesCurrentStat4Buckets' => $currentBuckets !== [],
                    'stalePreparedBucketsRejected' => array_values(array_diff($preparedBuckets, $currentBuckets)),
                    'nextOpcode' => $base['cursorTape']['nextOpcode'] ?? 'Next',
                    'deferredSeekOpcode' => null,
                    'tableLookupElided' => true,
                ],
                'stat4CurrentBoundaryKeys' => $currentBuckets,
                'stat4PreparedBoundaryKeys' => $preparedBuckets,
                'stat4BoundarySource' => 'current',
                'rangeDuplicateRowids' => self::rangeDuplicateRowids($duplicateGroups),
                'selectedPlan' => array_replace(
                    is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [],
                    [
                        'nextSource' => $ready ? 'stat4-covering-range-current-source-cursor' : 'table-rowid-lookup',
                        'currentNextCursorStable' => $ready && self::stableTieBreak($rows),
                        'duplicateRangeGroupCount' => count($duplicateGroups),
                        'stalePreparedBucketsRejected' => array_values(array_diff($preparedBuckets, $currentBuckets)),
                        'cursorProgram' => self::cursorProgram($base, $steps, $ready),
                    ],
                ),
                'currentSourceFence' => array_replace(
                    is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                    [
                        'currentSourceCursorSignature' => hash('sha256', json_encode(array_column($steps, 'signature'), JSON_THROW_ON_ERROR)),
                        'currentSourceBoundarySignature' => hash('sha256', json_encode($currentBuckets, JSON_THROW_ON_ERROR)),
                        'preparedBoundarySignature' => hash('sha256', json_encode($preparedBuckets, JSON_THROW_ON_ERROR)),
                    ],
                ),
                'detail' => (($base['stalePreparedStatement'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' STAT4 COVERING RANGE CURRENT-SOURCE CURSOR '
                    . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                    [
                        'SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan',
                        'sqlite-sqlplanner-stat4-covering-range-current-source-cursor',
                    ],
                ))),
                'dependency_closure' => 'no new support component needed; currentSourceCursor reuses native covering STAT4 range planning and adds current-source next cursor continuity for duplicate range keys',
                'non_overlap' => 'avoids accepted next138 row admission, expression-index range cost, expression ORDER BY, partial STAT4 covering, skip-scan, JSON, VFS, WAL, and B-tree clusters; this slice covers current-source STAT4 boundary selection plus stable Next advancement across duplicate covering range keys',
            ]);
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $base
         * @return list<string>
         */
        private static function preparedBoundaryKeys(array $source, array $base): array
        {
            $plan = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [];
            $lower = $plan['rangeLower'] ?? null;
            $upper = $plan['rangeUpper'] ?? null;
            $lowerInclusive = (bool) ($plan['lowerInclusive'] ?? false);
            $upperInclusive = (bool) ($plan['upperInclusive'] ?? false);
            $keys = [];
            foreach (self::sourceStat4Samples($source, (string) ($plan['name'] ?? '')) as $sample) {
                $key = $sample[count($sample) - 1] ?? null;
                if ($key !== null && self::withinRange($key, $lower, $upper, $lowerInclusive, $upperInclusive)) {
                    $keys[] = (string) $key;
                }
            }

            return array_values(array_unique($keys));
        }

        /**
         * @param array<string,mixed> $source
         * @return list<list<mixed>>
         */
        private static function sourceStat4Samples(array $source, string $selectedName): array
        {
            $indexes = $source['indexes'] ?? [];
            if (!is_array($indexes)) {
                return [];
            }
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    continue;
                }
                if ($selectedName !== '' && ($index['name'] ?? null) !== $selectedName) {
                    continue;
                }
                $samples = $index['stat4Samples'] ?? [];
                if (!is_array($samples)) {
                    return [];
                }

                return array_values(array_filter(array_map(
                    static fn (mixed $sample): ?array => is_array($sample) && is_array($sample['sample'] ?? null) ? array_values($sample['sample']) : null,
                    $samples,
                )));
            }

            return [];
        }

        /**
         * @param array<string,mixed> $base
         * @return list<string>
         */
        private static function currentBoundaryKeys(array $base): array
        {
            return array_map(static fn (mixed $key): string => (string) $key, array_column(self::listOfArrays($base, 'stat4RangeBuckets', false), 'key'));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{rangeKey:mixed,rowids:list<mixed>,count:int}>
         */
        private static function duplicateRangeGroups(array $rows): array
        {
            $groups = [];
            foreach ($rows as $row) {
                $key = (string) ($row['rangeKey'] ?? '');
                $groups[$key]['rangeKey'] = $row['rangeKey'] ?? null;
                $groups[$key]['rowids'][] = $row['rowid'] ?? null;
            }

            $duplicates = [];
            foreach ($groups as $group) {
                if (count($group['rowids']) > 1) {
                    $duplicates[] = [
                        'rangeKey' => $group['rangeKey'],
                        'rowids' => $group['rowids'],
                        'count' => count($group['rowids']),
                    ];
                }
            }

            return $duplicates;
        }

        /**
         * @param list<array{rangeKey:mixed,rowids:list<mixed>,count:int}> $groups
         * @return list<mixed>
         */
        private static function rangeDuplicateRowids(array $groups): array
        {
            $rowids = [];
            foreach ($groups as $group) {
                array_push($rowids, ...$group['rowids']);
            }

            return $rowids;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @param array<string,mixed> $base
         * @return list<array<string,mixed>>
         */
        private static function cursorSteps(array $rows, array $base): array
        {
            $steps = [];
            $nextOpcode = (string) ($base['cursorTape']['nextOpcode'] ?? 'Next');
            foreach ($rows as $offset => $row) {
                $previous = $rows[$offset - 1] ?? null;
                $next = $rows[$offset + 1] ?? null;
                $sameAsPrevious = is_array($previous) && self::compareValues($previous['rangeKey'] ?? null, $row['rangeKey'] ?? null) === 0;
                $sameAsNext = is_array($next) && self::compareValues($next['rangeKey'] ?? null, $row['rangeKey'] ?? null) === 0;
                $opcode = $offset === 0 ? (string) ($base['cursorTape']['seekOpcode'] ?? 'SeekGE') : $nextOpcode;
                $steps[] = [
                    'ordinal' => $offset,
                    'opcode' => $opcode,
                    'rowid' => $row['rowid'] ?? null,
                    'rangeKey' => $row['rangeKey'] ?? null,
                    'sameRangeAsPrevious' => $sameAsPrevious,
                    'sameRangeAsNext' => $sameAsNext,
                    'nextRowid' => $next['rowid'] ?? null,
                    'coveringColumns' => array_keys(is_array($row['covering'] ?? null) ? $row['covering'] : []),
                    'signature' => implode(':', [$opcode, (string) ($row['rangeKey'] ?? ''), (string) ($row['rowid'] ?? '')]),
                ];
            }

            return $steps;
        }

        /**
         * @param list<array<string,mixed>> $rows
         */
        private static function stableTieBreak(array $rows): bool
        {
            $previousKey = null;
            $previousRowid = null;
            foreach ($rows as $row) {
                $key = $row['rangeKey'] ?? null;
                $rowid = $row['rowid'] ?? null;
                if ($previousKey !== null && self::compareValues($previousKey, $key) === 0 && (int) $previousRowid > (int) $rowid) {
                    return false;
                }
                $previousKey = $key;
                $previousRowid = $rowid;
            }

            return true;
        }

        /**
         * @param array<string,mixed> $base
         * @param list<array<string,mixed>> $steps
         * @return list<array<string,mixed>>
         */
        private static function cursorProgram(array $base, array $steps, bool $ready): array
        {
            $program = is_array($base['selectedPlan']['cursorProgram'] ?? null) ? $base['selectedPlan']['cursorProgram'] : [];
            $program[] = ['opcode' => 'Stat4CurrentSourceBoundary', 'source' => 'current'];
            if ($ready) {
                $program[] = ['opcode' => 'NextDuplicateRangeTieBreak', 'source' => 'index', 'steps' => count($steps)];
            }

            return $program;
        }

        /**
         * @param array<string,mixed> $source
         * @return list<array<string,mixed>>
         */
        private static function listOfArrays(array $source, string $key, bool $required = true): array
        {
            $value = $source[$key] ?? [];
            if (!is_array($value) || !array_is_list($value)) {
                if ($required) {
                    throw new \InvalidArgumentException("SQLite planner STAT4 covering range currentSourceCursor {$key} must be a list");
                }

                return [];
            }
            foreach ($value as $entry) {
                if (!is_array($entry)) {
                    throw new \InvalidArgumentException("SQLite planner STAT4 covering range currentSourceCursor {$key} entries must be arrays");
                }
            }

            return $value;
        }

        private static function withinRange(mixed $value, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive): bool
        {
            if ($lower !== null) {
                $comparison = self::compareValues($value, $lower);
                if ($comparison < 0 || ($comparison === 0 && !$lowerInclusive)) {
                    return false;
                }
            }
            if ($upper !== null) {
                $comparison = self::compareValues($value, $upper);
                if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                    return false;
                }
            }

            return true;
        }

        private static function compareValues(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return (float) $left <=> (float) $right;
            }

            return strcmp((string) $left, (string) $right) <=> 0;
        }

}
