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
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext270285(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext254269(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext270285($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next254-269-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next270-285-prepared' : 'requires-current-source-stat4-next270-285-prep',
            'stat4Next270285PreparationFence' => $fence,
            'selectedPlan' => [
                'next270285Prepared' => $ready,
                'next270285SliceCount' => $fence['sliceCount'],
                'next270285PreparedSlices' => $fence['preparedSlices'],
                'next270285BlockedSlices' => $fence['blockedSlices'],
                'next270285PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next270285HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next270285Prepared' => $ready,
                'next270285HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext270285($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next270-285-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next270-285 preparation extends the accepted next254-269 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next270-285 current-source handoff slices only; avoids changing next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT270-285 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext286301(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext270285(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext286301($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next270-285-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next286-301-prepared' : 'requires-current-source-stat4-next286-301-prep',
            'stat4Next286301PreparationFence' => $fence,
            'selectedPlan' => [
                'next286301Prepared' => $ready,
                'next286301SliceCount' => $fence['sliceCount'],
                'next286301PreparedSlices' => $fence['preparedSlices'],
                'next286301BlockedSlices' => $fence['blockedSlices'],
                'next286301PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next286301HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next286301Prepared' => $ready,
                'next286301HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext286301($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next286-301-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next286-301 preparation extends the accepted next270-285 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next286-301 current-source handoff slices only; avoids changing next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT286-301 PREPARED HANDOFF'),
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

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext270285(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next270-285 needs projected columns');
        }

        $prior = $base['stat4Next254269PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next270-285 needs next254-269 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next270-285 needs next254-269 handoff windows');
        }

        $currentRows = self::rowsByRowidNext270285($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext270285($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(270, 285) as $slice) {
            $ordinal = $slice - 270;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next270-285 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext270285($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext270285($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext270285($priorWindow['slice'] ?? null, 'prior slice');
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow['prepared'] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                'slice' => $slice,
                'continuesSlice' => $priorSlice,
                'rowid' => $rowid,
                'expressionKey' => is_array($row) ? strtolower((string) ($row['option_name'] ?? '')) : null,
                'projectedColumns' => $projected,
                'priorProjectedColumns' => $priorProjected,
                'priorPrepared' => ($priorWindow['prepared'] ?? null) === true,
                'projectionMatchesPrior' => $projectionMatches,
                'prepared' => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window['slice'],
            array_filter($windows, static fn (array $window): bool => $window['prepared']),
        ));

        return [
            'sliceRange' => [270, 285],
            'sliceCount' => 16,
            'priorSliceRange' => $prior['sliceRange'] ?? null,
            'priorHandoffSignature' => $prior['handoffSignature'] ?? null,
            'previousFenceReady' => ($prior['allSlicesPrepared'] ?? null) === true && count($priorPrepared) === 16,
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
    private static function rowsByRowidNext270285(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next270-285 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next270-285 current rows must be arrays');
            }
            $rowid = self::intValueNext270285($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext270285(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next270-285 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext270285($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext270285(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next270-285 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext270285(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next270-285 projected column names must be non-empty');
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
    private static function cursorProgramNext270285(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext270285Handoff',
            'mode' => 'next270-285-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext286301(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next286-301 needs projected columns');
        }

        $prior = $base['stat4Next270285PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next286-301 needs next270-285 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next286-301 needs next270-285 handoff windows');
        }

        $currentRows = self::rowsByRowidNext286301($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext286301($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(286, 301) as $slice) {
            $ordinal = $slice - 286;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next286-301 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext286301($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext286301($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext286301($priorWindow['slice'] ?? null, 'prior slice');
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow['prepared'] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                'slice' => $slice,
                'continuesSlice' => $priorSlice,
                'rowid' => $rowid,
                'expressionKey' => is_array($row) ? strtolower((string) ($row['option_name'] ?? '')) : null,
                'projectedColumns' => $projected,
                'priorProjectedColumns' => $priorProjected,
                'priorPrepared' => ($priorWindow['prepared'] ?? null) === true,
                'projectionMatchesPrior' => $projectionMatches,
                'prepared' => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window['slice'],
            array_filter($windows, static fn (array $window): bool => $window['prepared']),
        ));

        return [
            'sliceRange' => [286, 301],
            'sliceCount' => 16,
            'priorSliceRange' => $prior['sliceRange'] ?? null,
            'priorHandoffSignature' => $prior['handoffSignature'] ?? null,
            'previousFenceReady' => ($prior['allSlicesPrepared'] ?? null) === true && count($priorPrepared) === 16,
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
    private static function rowsByRowidNext286301(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next286-301 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next286-301 current rows must be arrays');
            }
            $rowid = self::intValueNext286301($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext286301(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next286-301 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext286301($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext286301(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next286-301 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext286301(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next286-301 projected column names must be non-empty');
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
    private static function cursorProgramNext286301(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext286301Handoff',
            'mode' => 'next286-301-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }
}
