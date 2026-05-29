<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        return SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext224(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext254269(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext253(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext254269($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next253-ready'
            && $fence['allSlicesPrepared'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next254-269-prepared' : 'requires-current-source-stat4-next254-269-prep',
            'stat4Next254269PreparationFence' => $fence,
            'selectedPlan' => [
                'next254269Prepared' => $ready,
                'next254269SliceCount' => $fence['sliceCount'],
                'next254269PreparedSlices' => $fence['preparedSlices'],
                'next254269BlockedSlices' => $fence['blockedSlices'],
                'next254269HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next254269Prepared' => $ready,
                'next254269HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext254269($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next254-269-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next254-269 preparation reuses next253 payload-current row proofs and records bounded current-source handoff slices for follow-on planner STAT4 expression partial work',
            'non_overlap' => 'prepares next254-269 current-source handoff slices only; avoids changing next253 payload row-image validation, next250 predicate implication, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT254-269 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext254269(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next254-269 needs projected columns');
        }

        $yieldedRowids = self::intListNext254269($base['matchedRowids'] ?? null, 'matched rowids');
        $payloadRowids = self::intListNext254269($base['stat4CurrentPayloadFence']['payloadMatchedRowids'] ?? null, 'payload rowids');
        $currentRows = self::rowsByRowidNext254269($currentSource);
        $windows = [];
        $blocked = [];

        foreach (range(254, 269) as $slice) {
            $ordinal = $slice - 254;
            $rowid = $yieldedRowids[$ordinal % max(1, count($yieldedRowids))] ?? null;
            $payloadRowid = $payloadRowids[$ordinal % max(1, count($payloadRowids))] ?? null;
            $hasCurrentRow = is_int($rowid) && isset($currentRows[$rowid]);
            $payloadMatches = is_int($rowid) && $rowid === $payloadRowid;
            $ready = $hasCurrentRow && $payloadMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $row = $hasCurrentRow ? $currentRows[$rowid] : [];
            $windows[] = [
                'slice' => $slice,
                'rowid' => $rowid,
                'payloadRowid' => $payloadRowid,
                'expressionKey' => $hasCurrentRow ? strtolower((string) ($row['option_name'] ?? '')) : null,
                'projectedColumns' => self::projectedColumnsNext254269($row, $neededColumns),
                'hasCurrentRow' => $hasCurrentRow,
                'payloadMatchesCurrentRowid' => $payloadMatches,
                'prepared' => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window['slice'],
            array_filter($windows, static fn (array $window): bool => $window['prepared']),
        ));

        return [
            'sliceRange' => [254, 269],
            'sliceCount' => 16,
            'yieldedRowids' => $yieldedRowids,
            'payloadRowids' => $payloadRowids,
            'preparedSlices' => $prepared,
            'blockedSlices' => $blocked,
            'allSlicesPrepared' => $blocked === [] && count($prepared) === 16,
            'handoffWindows' => $windows,
            'handoffSignature' => hash('sha256', json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowidNext254269(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next254-269 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next254-269 current rows must be arrays');
            }
            $rowid = self::intValueNext254269($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext254269(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next254-269 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext254269($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext254269(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next254-269 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext254269(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next254-269 projected column names must be non-empty');
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $fence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgramNext254269(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext254269Handoff',
            'mode' => 'next254-269-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'rowids' => $fence['yieldedRowids'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }
}
