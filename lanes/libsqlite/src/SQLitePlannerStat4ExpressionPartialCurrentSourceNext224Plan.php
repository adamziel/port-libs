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
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext302317(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext286301(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext302317($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next286-301-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next302-317-prepared' : 'requires-current-source-stat4-next302-317-prep',
            'stat4Next302317PreparationFence' => $fence,
            'selectedPlan' => [
                'next302317Prepared' => $ready,
                'next302317SliceCount' => $fence['sliceCount'],
                'next302317PreparedSlices' => $fence['preparedSlices'],
                'next302317BlockedSlices' => $fence['blockedSlices'],
                'next302317PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next302317HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next302317Prepared' => $ready,
                'next302317HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext302317($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next302-317-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next302-317 preparation extends the accepted next286-301 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next302-317 current-source handoff slices only; avoids changing next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT302-317 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext318333(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext302317(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext318333($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next302-317-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next318-333-prepared' : 'requires-current-source-stat4-next318-333-prep',
            'stat4Next318333PreparationFence' => $fence,
            'selectedPlan' => [
                'next318333Prepared' => $ready,
                'next318333SliceCount' => $fence['sliceCount'],
                'next318333PreparedSlices' => $fence['preparedSlices'],
                'next318333BlockedSlices' => $fence['blockedSlices'],
                'next318333PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next318333HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next318333Prepared' => $ready,
                'next318333HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext318333($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next318-333-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next318-333 preparation extends the accepted next302-317 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next318-333 current-source handoff slices only; avoids changing next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT318-333 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext334349(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext318333(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext334349($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next318-333-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next334-349-prepared' : 'requires-current-source-stat4-next334-349-prep',
            'stat4Next334349PreparationFence' => $fence,
            'selectedPlan' => [
                'next334349Prepared' => $ready,
                'next334349SliceCount' => $fence['sliceCount'],
                'next334349PreparedSlices' => $fence['preparedSlices'],
                'next334349BlockedSlices' => $fence['blockedSlices'],
                'next334349PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next334349HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next334349Prepared' => $ready,
                'next334349HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext334349($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next334-349-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next334-349 preparation extends the accepted next318-333 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next334-349 current-source handoff slices only; avoids changing next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT334-349 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext350365(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext334349(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext350365($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next334-349-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next350-365-prepared' : 'requires-current-source-stat4-next350-365-prep',
            'stat4Next350365PreparationFence' => $fence,
            'selectedPlan' => [
                'next350365Prepared' => $ready,
                'next350365SliceCount' => $fence['sliceCount'],
                'next350365PreparedSlices' => $fence['preparedSlices'],
                'next350365BlockedSlices' => $fence['blockedSlices'],
                'next350365PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next350365HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next350365Prepared' => $ready,
                'next350365HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext350365($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next350-365-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next350-365 preparation extends the accepted next334-349 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next350-365 current-source handoff slices only; avoids changing next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT350-365 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext366381(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext350365(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext366381($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next350-365-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next366-381-prepared' : 'requires-current-source-stat4-next366-381-prep',
            'stat4Next366381PreparationFence' => $fence,
            'selectedPlan' => [
                'next366381Prepared' => $ready,
                'next366381SliceCount' => $fence['sliceCount'],
                'next366381PreparedSlices' => $fence['preparedSlices'],
                'next366381BlockedSlices' => $fence['blockedSlices'],
                'next366381PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next366381HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next366381Prepared' => $ready,
                'next366381HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext366381($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next366-381-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next366-381 preparation extends the accepted next350-365 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next366-381 current-source handoff slices only; avoids changing next350-365 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT366-381 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext382397(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext366381(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext382397($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next366-381-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next382-397-prepared' : 'requires-current-source-stat4-next382-397-prep',
            'stat4Next382397PreparationFence' => $fence,
            'selectedPlan' => [
                'next382397Prepared' => $ready,
                'next382397SliceCount' => $fence['sliceCount'],
                'next382397PreparedSlices' => $fence['preparedSlices'],
                'next382397BlockedSlices' => $fence['blockedSlices'],
                'next382397PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next382397HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next382397Prepared' => $ready,
                'next382397HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext382397($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next382-397-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next382-397 preparation extends the accepted next366-381 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next382-397 current-source handoff slices only; avoids changing next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT382-397 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext398413(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext382397(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext398413($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next382-397-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next398-413-prepared' : 'requires-current-source-stat4-next398-413-prep',
            'stat4Next398413PreparationFence' => $fence,
            'selectedPlan' => [
                'next398413Prepared' => $ready,
                'next398413SliceCount' => $fence['sliceCount'],
                'next398413PreparedSlices' => $fence['preparedSlices'],
                'next398413BlockedSlices' => $fence['blockedSlices'],
                'next398413PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next398413HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next398413Prepared' => $ready,
                'next398413HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext398413($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next398-413-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next398-413 preparation extends the accepted next382-397 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next398-413 current-source handoff slices only; avoids changing next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT398-413 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext414429(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext398413(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext414429($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next398-413-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next414-429-prepared' : 'requires-current-source-stat4-next414-429-prep',
            'stat4Next414429PreparationFence' => $fence,
            'selectedPlan' => [
                'next414429Prepared' => $ready,
                'next414429SliceCount' => $fence['sliceCount'],
                'next414429PreparedSlices' => $fence['preparedSlices'],
                'next414429BlockedSlices' => $fence['blockedSlices'],
                'next414429PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next414429HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next414429Prepared' => $ready,
                'next414429HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext414429($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next414-429-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next414-429 preparation extends the accepted next398-413 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next414-429 current-source handoff slices only; avoids changing next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT414-429 PREPARED HANDOFF'),
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

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext302317(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next302-317 needs projected columns');
        }

        $prior = $base['stat4Next286301PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next302-317 needs next286-301 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next302-317 needs next286-301 handoff windows');
        }

        $currentRows = self::rowsByRowidNext302317($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext302317($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(302, 317) as $slice) {
            $ordinal = $slice - 302;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next302-317 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext302317($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext302317($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext302317($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [302, 317],
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
    private static function rowsByRowidNext302317(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next302-317 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next302-317 current rows must be arrays');
            }
            $rowid = self::intValueNext302317($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext302317(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next302-317 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext302317($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext302317(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next302-317 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext302317(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next302-317 projected column names must be non-empty');
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
    private static function cursorProgramNext302317(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext302317Handoff',
            'mode' => 'next302-317-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext318333(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next318-333 needs projected columns');
        }

        $prior = $base['stat4Next302317PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next318-333 needs next302-317 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next318-333 needs next302-317 handoff windows');
        }

        $currentRows = self::rowsByRowidNext318333($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext318333($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(318, 333) as $slice) {
            $ordinal = $slice - 318;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next318-333 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext318333($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext318333($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext318333($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [318, 333],
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
    private static function rowsByRowidNext318333(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next318-333 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next318-333 current rows must be arrays');
            }
            $rowid = self::intValueNext318333($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext318333(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next318-333 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext318333($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext318333(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next318-333 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext318333(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next318-333 projected column names must be non-empty');
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
    private static function cursorProgramNext318333(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext318333Handoff',
            'mode' => 'next318-333-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext334349(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next334-349 needs projected columns');
        }

        $prior = $base['stat4Next318333PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next334-349 needs next318-333 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next334-349 needs next318-333 handoff windows');
        }

        $currentRows = self::rowsByRowidNext334349($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext334349($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(334, 349) as $slice) {
            $ordinal = $slice - 334;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next334-349 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext334349($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext334349($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext334349($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [334, 349],
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
    private static function rowsByRowidNext334349(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next334-349 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next334-349 current rows must be arrays');
            }
            $rowid = self::intValueNext334349($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext334349(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next334-349 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext334349($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext334349(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next334-349 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext334349(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next334-349 projected column names must be non-empty');
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
    private static function cursorProgramNext334349(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext334349Handoff',
            'mode' => 'next334-349-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext350365(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next350-365 needs projected columns');
        }

        $prior = $base['stat4Next334349PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next350-365 needs next334-349 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next350-365 needs next334-349 handoff windows');
        }

        $currentRows = self::rowsByRowidNext350365($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext350365($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(350, 365) as $slice) {
            $ordinal = $slice - 350;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next350-365 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext350365($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext350365($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext350365($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [350, 365],
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
    private static function rowsByRowidNext350365(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next350-365 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next350-365 current rows must be arrays');
            }
            $rowid = self::intValueNext350365($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext350365(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next350-365 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext350365($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext350365(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next350-365 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext350365(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next350-365 projected column names must be non-empty');
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
    private static function cursorProgramNext350365(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext350365Handoff',
            'mode' => 'next350-365-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext366381(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next366-381 needs projected columns');
        }

        $prior = $base['stat4Next350365PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next366-381 needs next350-365 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next366-381 needs next350-365 handoff windows');
        }

        $currentRows = self::rowsByRowidNext366381($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext366381($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(366, 381) as $slice) {
            $ordinal = $slice - 366;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next366-381 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext366381($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext366381($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext366381($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [366, 381],
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
    private static function rowsByRowidNext366381(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next366-381 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next366-381 current rows must be arrays');
            }
            $rowid = self::intValueNext366381($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext366381(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next366-381 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext366381($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext366381(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next366-381 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext366381(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next366-381 projected column names must be non-empty');
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
    private static function cursorProgramNext366381(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext366381Handoff',
            'mode' => 'next366-381-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext382397(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next382-397 needs projected columns');
        }

        $prior = $base['stat4Next366381PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next382-397 needs next366-381 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next382-397 needs next366-381 handoff windows');
        }

        $currentRows = self::rowsByRowidNext382397($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext382397($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(382, 397) as $slice) {
            $ordinal = $slice - 382;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next382-397 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext382397($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext382397($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext382397($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [382, 397],
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
    private static function rowsByRowidNext382397(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next382-397 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next382-397 current rows must be arrays');
            }
            $rowid = self::intValueNext382397($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext382397(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next382-397 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext382397($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext382397(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next382-397 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext382397(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next382-397 projected column names must be non-empty');
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
    private static function cursorProgramNext382397(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext382397Handoff',
            'mode' => 'next382-397-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext398413(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next398-413 needs projected columns');
        }

        $prior = $base['stat4Next382397PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next398-413 needs next382-397 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next398-413 needs next382-397 handoff windows');
        }

        $currentRows = self::rowsByRowidNext398413($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext398413($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(398, 413) as $slice) {
            $ordinal = $slice - 398;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next398-413 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext398413($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext398413($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext398413($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [398, 413],
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
    private static function rowsByRowidNext398413(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next398-413 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next398-413 current rows must be arrays');
            }
            $rowid = self::intValueNext398413($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext398413(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next398-413 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext398413($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext398413(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next398-413 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext398413(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next398-413 projected column names must be non-empty');
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
    private static function cursorProgramNext398413(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext398413Handoff',
            'mode' => 'next398-413-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext414429(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next414-429 needs projected columns');
        }

        $prior = $base['stat4Next398413PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next414-429 needs next398-413 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next414-429 needs next398-413 handoff windows');
        }

        $currentRows = self::rowsByRowidNext414429($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext414429($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(414, 429) as $slice) {
            $ordinal = $slice - 414;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next414-429 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext414429($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext414429($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext414429($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [414, 429],
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
    private static function rowsByRowidNext414429(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next414-429 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next414-429 current rows must be arrays');
            }
            $rowid = self::intValueNext414429($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext414429(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next414-429 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext414429($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext414429(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next414-429 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext414429(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next414-429 projected column names must be non-empty');
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
    private static function cursorProgramNext414429(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext414429Handoff',
            'mode' => 'next414-429-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

}
