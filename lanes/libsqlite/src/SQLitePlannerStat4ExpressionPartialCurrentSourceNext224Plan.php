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
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext430445(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext414429(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext430445($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next414-429-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next430-445-prepared' : 'requires-current-source-stat4-next430-445-prep',
            'stat4Next430445PreparationFence' => $fence,
            'selectedPlan' => [
                'next430445Prepared' => $ready,
                'next430445SliceCount' => $fence['sliceCount'],
                'next430445PreparedSlices' => $fence['preparedSlices'],
                'next430445BlockedSlices' => $fence['blockedSlices'],
                'next430445PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next430445HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next430445Prepared' => $ready,
                'next430445HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext430445($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next430-445-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next430-445 preparation extends the accepted next414-429 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next430-445 current-source handoff slices only; avoids changing next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT430-445 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext446461(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext430445(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext446461($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next430-445-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next446-461-prepared' : 'requires-current-source-stat4-next446-461-prep',
            'stat4Next446461PreparationFence' => $fence,
            'selectedPlan' => [
                'next446461Prepared' => $ready,
                'next446461SliceCount' => $fence['sliceCount'],
                'next446461PreparedSlices' => $fence['preparedSlices'],
                'next446461BlockedSlices' => $fence['blockedSlices'],
                'next446461PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next446461HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next446461Prepared' => $ready,
                'next446461HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext446461($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next446-461-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next446-461 preparation extends the accepted next430-445 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next446-461 current-source handoff slices only; avoids changing next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT446-461 PREPARED HANDOFF'),
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

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext430445(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next430-445 needs projected columns');
        }

        $prior = $base['stat4Next414429PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next430-445 needs next414-429 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next430-445 needs next414-429 handoff windows');
        }

        $currentRows = self::rowsByRowidNext430445($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext430445($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(430, 445) as $slice) {
            $ordinal = $slice - 430;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next430-445 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext430445($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext430445($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext430445($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [430, 445],
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
    private static function rowsByRowidNext430445(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next430-445 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next430-445 current rows must be arrays');
            }
            $rowid = self::intValueNext430445($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext430445(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next430-445 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext430445($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext430445(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next430-445 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext430445(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next430-445 projected column names must be non-empty');
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
    private static function cursorProgramNext430445(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext430445Handoff',
            'mode' => 'next430-445-current-source-stat4-expression-partial-prep',
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
    private static function handoffFenceNext446461(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next446-461 needs projected columns');
        }

        $prior = $base['stat4Next430445PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next446-461 needs next430-445 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next446-461 needs next430-445 handoff windows');
        }

        $currentRows = self::rowsByRowidNext446461($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext446461($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(446, 461) as $slice) {
            $ordinal = $slice - 446;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next446-461 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext446461($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext446461($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext446461($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [446, 461],
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
    private static function rowsByRowidNext446461(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next446-461 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next446-461 current rows must be arrays');
            }
            $rowid = self::intValueNext446461($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext446461(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next446-461 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext446461($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext446461(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next446-461 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext446461(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next446-461 projected column names must be non-empty');
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
    private static function cursorProgramNext446461(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext446461Handoff',
            'mode' => 'next446-461-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext462477(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext446461(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext462477($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next446-461-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next462-477-prepared' : 'requires-current-source-stat4-next462-477-prep',
            'stat4Next462477PreparationFence' => $fence,
            'selectedPlan' => [
                'next462477Prepared' => $ready,
                'next462477SliceCount' => $fence['sliceCount'],
                'next462477PreparedSlices' => $fence['preparedSlices'],
                'next462477BlockedSlices' => $fence['blockedSlices'],
                'next462477PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next462477HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next462477Prepared' => $ready,
                'next462477HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext462477($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next462-477-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next462-477 preparation extends the accepted next446-461 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next462-477 current-source handoff slices only; avoids changing next446-461 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT462-477 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext462477(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next462-477 needs projected columns');
        }

        $prior = $base['stat4Next446461PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next462-477 needs next446-461 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next462-477 needs next446-461 handoff windows');
        }

        $currentRows = self::rowsByRowidNext462477($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext462477($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(462, 477) as $slice) {
            $ordinal = $slice - 462;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next462-477 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext462477($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext462477($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext462477($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [462, 477],
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
    private static function rowsByRowidNext462477(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next462-477 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next462-477 current rows must be arrays');
            }
            $rowid = self::intValueNext462477($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext462477(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next462-477 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext462477($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext462477(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next462-477 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext462477(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next462-477 projected column names must be non-empty');
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
    private static function cursorProgramNext462477(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext462477Handoff',
            'mode' => 'next462-477-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext478493(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext462477(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext478493($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next462-477-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next478-493-prepared' : 'requires-current-source-stat4-next478-493-prep',
            'stat4Next478493PreparationFence' => $fence,
            'selectedPlan' => [
                'next478493Prepared' => $ready,
                'next478493SliceCount' => $fence['sliceCount'],
                'next478493PreparedSlices' => $fence['preparedSlices'],
                'next478493BlockedSlices' => $fence['blockedSlices'],
                'next478493PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next478493HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next478493Prepared' => $ready,
                'next478493HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext478493($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next478-493-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next478-493 preparation extends the accepted next462-477 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next478-493 current-source handoff slices only; avoids changing next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT478-493 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext478493(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next478-493 needs projected columns');
        }

        $prior = $base['stat4Next462477PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next478-493 needs next462-477 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next478-493 needs next462-477 handoff windows');
        }

        $currentRows = self::rowsByRowidNext478493($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext478493($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(478, 493) as $slice) {
            $ordinal = $slice - 478;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next478-493 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext478493($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext478493($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext478493($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [478, 493],
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
    private static function rowsByRowidNext478493(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next478-493 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next478-493 current rows must be arrays');
            }
            $rowid = self::intValueNext478493($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext478493(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next478-493 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext478493($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext478493(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next478-493 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext478493(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next478-493 projected column names must be non-empty');
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
    private static function cursorProgramNext478493(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext478493Handoff',
            'mode' => 'next478-493-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext494509(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext478493(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext494509($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next478-493-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next494-509-prepared' : 'requires-current-source-stat4-next494-509-prep',
            'stat4Next494509PreparationFence' => $fence,
            'selectedPlan' => [
                'next494509Prepared' => $ready,
                'next494509SliceCount' => $fence['sliceCount'],
                'next494509PreparedSlices' => $fence['preparedSlices'],
                'next494509BlockedSlices' => $fence['blockedSlices'],
                'next494509PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next494509HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next494509Prepared' => $ready,
                'next494509HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext494509($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next494-509-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next494-509 preparation extends the accepted next478-493 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next494-509 current-source handoff slices only; avoids changing next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT494-509 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext494509(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next494-509 needs projected columns');
        }

        $prior = $base['stat4Next478493PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next494-509 needs next478-493 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next494-509 needs next478-493 handoff windows');
        }

        $currentRows = self::rowsByRowidNext494509($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext494509($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(494, 509) as $slice) {
            $ordinal = $slice - 494;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next494-509 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext494509($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext494509($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext494509($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [494, 509],
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
    private static function rowsByRowidNext494509(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next494-509 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next494-509 current rows must be arrays');
            }
            $rowid = self::intValueNext494509($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext494509(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next494-509 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext494509($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext494509(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next494-509 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext494509(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next494-509 projected column names must be non-empty');
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
    private static function cursorProgramNext494509(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext494509Handoff',
            'mode' => 'next494-509-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext510525(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext494509(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext510525($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next494-509-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next510-525-prepared' : 'requires-current-source-stat4-next510-525-prep',
            'stat4Next510525PreparationFence' => $fence,
            'selectedPlan' => [
                'next510525Prepared' => $ready,
                'next510525SliceCount' => $fence['sliceCount'],
                'next510525PreparedSlices' => $fence['preparedSlices'],
                'next510525BlockedSlices' => $fence['blockedSlices'],
                'next510525PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next510525HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next510525Prepared' => $ready,
                'next510525HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext510525($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next510-525-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next510-525 preparation extends the accepted next494-509 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next510-525 current-source handoff slices only; avoids changing next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT510-525 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext510525(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next510-525 needs projected columns');
        }

        $prior = $base['stat4Next494509PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next510-525 needs next494-509 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next510-525 needs next494-509 handoff windows');
        }

        $currentRows = self::rowsByRowidNext510525($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext510525($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(510, 525) as $slice) {
            $ordinal = $slice - 510;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next510-525 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext510525($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext510525($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext510525($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [510, 525],
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
    private static function rowsByRowidNext510525(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next510-525 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next510-525 current rows must be arrays');
            }
            $rowid = self::intValueNext510525($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext510525(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next510-525 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext510525($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext510525(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next510-525 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext510525(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next510-525 projected column names must be non-empty');
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
    private static function cursorProgramNext510525(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext510525Handoff',
            'mode' => 'next510-525-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext526541(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext510525(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext526541($base, $currentSource, $neededColumns);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next510-525-prepared'
            && $fence['allSlicesPrepared']
            && $fence['previousFenceReady'];

        return array_replace_recursive($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next526-541-prepared' : 'requires-current-source-stat4-next526-541-prep',
            'stat4Next526541PreparationFence' => $fence,
            'selectedPlan' => [
                'next526541Prepared' => $ready,
                'next526541SliceCount' => $fence['sliceCount'],
                'next526541PreparedSlices' => $fence['preparedSlices'],
                'next526541BlockedSlices' => $fence['blockedSlices'],
                'next526541PriorHandoffSignature' => $fence['priorHandoffSignature'],
                'next526541HandoffSignature' => $fence['handoffSignature'],
            ],
            'stat4Fence' => [
                'next526541Prepared' => $ready,
                'next526541HandoffSignature' => $fence['handoffSignature'],
            ],
            'cursorProgram' => self::cursorProgramNext526541($base['cursorProgram'] ?? [], $ready, $fence),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'] ?? [],
                ['sqlite-sqlplanner-stat4-expression-partial-current-source-next526-541-prep'],
            ))),
            'dependency_closure' => 'no new support component needed; next526-541 preparation extends the accepted next510-525 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work',
            'non_overlap' => 'prepares next526-541 current-source handoff slices only; avoids changing next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
            'detail' => trim((string) ($base['detail'] ?? '') . ' NEXT526-541 PREPARED HANDOFF'),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext526541(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next526-541 needs projected columns');
        }

        $prior = $base['stat4Next510525PreparationFence'] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException('SQLite next526-541 needs next510-525 handoff fence');
        }

        $priorWindows = $prior['handoffWindows'] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException('SQLite next526-541 needs next510-525 handoff windows');
        }

        $currentRows = self::rowsByRowidNext526541($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext526541($prior['preparedSlices'] ?? null, 'prior prepared slices');

        foreach (range(526, 541) as $slice) {
            $ordinal = $slice - 526;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException('SQLite next526-541 prior handoff windows must be arrays');
            }

            $rowid = self::intValueNext526541($priorWindow['rowid'] ?? null, 'prior rowid');
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext526541($row, $neededColumns) : [];
            $priorProjected = $priorWindow['projectedColumns'] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext526541($priorWindow['slice'] ?? null, 'prior slice');
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
            'sliceRange' => [526, 541],
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
    private static function rowsByRowidNext526541(array $source): array
    {
        if (!isset($source['rows']) || !is_array($source['rows'])) {
            throw new \InvalidArgumentException('SQLite next526-541 needs current rows');
        }

        $rows = [];
        foreach ($source['rows'] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next526-541 current rows must be arrays');
            }
            $rowid = self::intValueNext526541($row['rowid'] ?? null, 'current rowid');
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext526541(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next526-541 needs ' . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext526541($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext526541(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next526-541 ' . $label . ' must be an integer');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext526541(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next526-541 projected column names must be non-empty');
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
    private static function cursorProgramNext526541(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            'opcode' => 'PrepareStat4ExpressionPartialNext526541Handoff',
            'mode' => 'next526-541-current-source-stat4-expression-partial-prep',
            'sliceRange' => $fence['sliceRange'],
            'priorSliceRange' => $fence['priorSliceRange'],
            'preparedSlices' => $fence['preparedSlices'],
            'priorHandoffSignature' => $fence['priorHandoffSignature'],
            'handoffSignature' => $fence['handoffSignature'],
        ];

        return $program;
    }


    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext542557(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext526541(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext542557($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next526-541-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next542-557-prepared" : "requires-current-source-stat4-next542-557-prep",
            "stat4Next542557PreparationFence" => $fence,
            "selectedPlan" => [
                "next542557Prepared" => $ready,
                "next542557SliceCount" => $fence["sliceCount"],
                "next542557PreparedSlices" => $fence["preparedSlices"],
                "next542557BlockedSlices" => $fence["blockedSlices"],
                "next542557PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next542557HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next542557Prepared" => $ready,
                "next542557HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext542557($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next542-557-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next542-557 preparation extends the accepted next526-541 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next542-557 current-source handoff slices only; avoids changing next526-541 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT542-557 PREPARED HANDOFF"),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext542557(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next542-557 needs projected columns");
        }

        $prior = $base["stat4Next526541PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next542-557 needs next526-541 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next542-557 needs next526-541 handoff windows");
        }

        $currentRows = self::rowsByRowidNext542557($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext542557($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(542, 557) as $slice) {
            $ordinal = $slice - 542;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next542-557 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext542557($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext542557($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext542557($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [542, 557],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowidNext542557(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next542-557 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next542-557 current rows must be arrays");
            }
            $rowid = self::intValueNext542557($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext542557(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next542-557 needs " . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext542557($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext542557(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next542-557 " . $label . " must be an integer");
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext542557(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next542-557 projected column names must be non-empty");
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
    private static function cursorProgramNext542557(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext542557Handoff",
            "mode" => "next542-557-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }


    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext558573(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext542557(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext558573($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next542-557-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next558-573-prepared" : "requires-current-source-stat4-next558-573-prep",
            "stat4Next558573PreparationFence" => $fence,
            "selectedPlan" => [
                "next558573Prepared" => $ready,
                "next558573SliceCount" => $fence["sliceCount"],
                "next558573PreparedSlices" => $fence["preparedSlices"],
                "next558573BlockedSlices" => $fence["blockedSlices"],
                "next558573PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next558573HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next558573Prepared" => $ready,
                "next558573HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext558573($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next558-573-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next558-573 preparation extends the accepted next542-557 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next558-573 current-source handoff slices only; avoids changing next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT558-573 PREPARED HANDOFF"),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext558573(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next558-573 needs projected columns");
        }

        $prior = $base["stat4Next542557PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next558-573 needs next542-557 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next558-573 needs next542-557 handoff windows");
        }

        $currentRows = self::rowsByRowidNext558573($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext558573($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(558, 573) as $slice) {
            $ordinal = $slice - 558;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next558-573 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext558573($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext558573($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext558573($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [558, 573],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowidNext558573(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next558-573 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next558-573 current rows must be arrays");
            }
            $rowid = self::intValueNext558573($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext558573(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next558-573 needs " . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext558573($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext558573(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next558-573 " . $label . " must be an integer");
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext558573(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next558-573 projected column names must be non-empty");
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
    private static function cursorProgramNext558573(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext558573Handoff",
            "mode" => "next558-573-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }


    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext574589(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext558573(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext574589($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next558-573-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next574-589-prepared" : "requires-current-source-stat4-next574-589-prep",
            "stat4Next574589PreparationFence" => $fence,
            "selectedPlan" => [
                "next574589Prepared" => $ready,
                "next574589SliceCount" => $fence["sliceCount"],
                "next574589PreparedSlices" => $fence["preparedSlices"],
                "next574589BlockedSlices" => $fence["blockedSlices"],
                "next574589PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next574589HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next574589Prepared" => $ready,
                "next574589HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext574589($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next574-589-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next574-589 preparation extends the accepted next558-573 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next574-589 current-source handoff slices only; avoids changing next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT574-589 PREPARED HANDOFF"),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext574589(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next574-589 needs projected columns");
        }

        $prior = $base["stat4Next558573PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next574-589 needs next558-573 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next574-589 needs next558-573 handoff windows");
        }

        $currentRows = self::rowsByRowidNext574589($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext574589($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(574, 589) as $slice) {
            $ordinal = $slice - 574;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next574-589 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext574589($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext574589($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext574589($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [574, 589],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowidNext574589(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next574-589 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next574-589 current rows must be arrays");
            }
            $rowid = self::intValueNext574589($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext574589(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next574-589 needs " . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext574589($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext574589(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next574-589 " . $label . " must be an integer");
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext574589(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next574-589 projected column names must be non-empty");
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
    private static function cursorProgramNext574589(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext574589Handoff",
            "mode" => "next574-589-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }


    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext590605(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext574589(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::handoffFenceNext590605($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next574-589-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next590-605-prepared" : "requires-current-source-stat4-next590-605-prep",
            "stat4Next590605PreparationFence" => $fence,
            "selectedPlan" => [
                "next590605Prepared" => $ready,
                "next590605SliceCount" => $fence["sliceCount"],
                "next590605PreparedSlices" => $fence["preparedSlices"],
                "next590605BlockedSlices" => $fence["blockedSlices"],
                "next590605PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next590605HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next590605Prepared" => $ready,
                "next590605HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext590605($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next590-605-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next590-605 preparation extends the accepted next574-589 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next590-605 current-source handoff slices only; avoids changing next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT590-605 PREPARED HANDOFF"),
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $currentSource
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function handoffFenceNext590605(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next590-605 needs projected columns");
        }

        $prior = $base["stat4Next574589PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next590-605 needs next574-589 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next590-605 needs next574-589 handoff windows");
        }

        $currentRows = self::rowsByRowidNext590605($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext590605($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(590, 605) as $slice) {
            $ordinal = $slice - 590;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next590-605 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext590605($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext590605($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext590605($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [590, 605],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowidNext590605(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next590-605 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next590-605 current rows must be arrays");
            }
            $rowid = self::intValueNext590605($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function intListNext590605(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next590-605 needs " . $label);
        }

        return array_values(array_map(
            static fn (mixed $rowid): int => self::intValueNext590605($rowid, $label),
            $value,
        ));
    }

    private static function intValueNext590605(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next590-605 " . $label . " must be an integer");
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function projectedColumnsNext590605(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next590-605 projected column names must be non-empty");
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
    private static function cursorProgramNext590605(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext590605Handoff",
            "mode" => "next590-605-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext606621(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext590605($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext606621($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next590-605-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next606-621-prepared" : "requires-current-source-stat4-next606-621-prep",
            "stat4Next606621PreparationFence" => $fence,
            "selectedPlan" => [
                "next606621Prepared" => $ready,
                "next606621SliceCount" => $fence["sliceCount"],
                "next606621PreparedSlices" => $fence["preparedSlices"],
                "next606621BlockedSlices" => $fence["blockedSlices"],
                "next606621PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next606621HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next606621Prepared" => $ready,
                "next606621HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext606621($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next606-621-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next606-621 preparation extends the accepted next590-605 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next606-621 current-source handoff slices only; avoids changing next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT606-621 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext606621(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next606-621 needs projected columns");
        }

        $prior = $base["stat4Next590605PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next606-621 needs next590-605 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next606-621 needs next590-605 handoff windows");
        }

        $currentRows = self::rowsByRowidNext606621($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext606621($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(606, 621) as $slice) {
            $ordinal = $slice - 606;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next606-621 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext606621($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext606621($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext606621($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [606, 621],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext606621(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next606-621 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next606-621 current rows must be arrays");
            }
            $rowid = self::intValueNext606621($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext606621(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next606-621 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext606621($rowid, $label), $value));
    }

    private static function intValueNext606621(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next606-621 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext606621(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next606-621 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext606621(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext606621Handoff",
            "mode" => "next606-621-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext622637(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext606621($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext622637($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next606-621-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next622-637-prepared" : "requires-current-source-stat4-next622-637-prep",
            "stat4Next622637PreparationFence" => $fence,
            "selectedPlan" => [
                "next622637Prepared" => $ready,
                "next622637SliceCount" => $fence["sliceCount"],
                "next622637PreparedSlices" => $fence["preparedSlices"],
                "next622637BlockedSlices" => $fence["blockedSlices"],
                "next622637PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next622637HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next622637Prepared" => $ready,
                "next622637HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext622637($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next622-637-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next622-637 preparation extends the accepted next606-621 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next622-637 current-source handoff slices only; avoids changing next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT622-637 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext622637(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next622-637 needs projected columns");
        }

        $prior = $base["stat4Next606621PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next622-637 needs next606-621 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next622-637 needs next606-621 handoff windows");
        }

        $currentRows = self::rowsByRowidNext622637($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext622637($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(622, 637) as $slice) {
            $ordinal = $slice - 622;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next622-637 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext622637($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext622637($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext622637($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [622, 637],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext622637(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next622-637 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next622-637 current rows must be arrays");
            }
            $rowid = self::intValueNext622637($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext622637(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next622-637 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext622637($rowid, $label), $value));
    }

    private static function intValueNext622637(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next622-637 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext622637(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next622-637 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext622637(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext622637Handoff",
            "mode" => "next622-637-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext638653(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext622637($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext638653($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next622-637-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next638-653-prepared" : "requires-current-source-stat4-next638-653-prep",
            "stat4Next638653PreparationFence" => $fence,
            "selectedPlan" => [
                "next638653Prepared" => $ready,
                "next638653SliceCount" => $fence["sliceCount"],
                "next638653PreparedSlices" => $fence["preparedSlices"],
                "next638653BlockedSlices" => $fence["blockedSlices"],
                "next638653PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next638653HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next638653Prepared" => $ready,
                "next638653HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext638653($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next638-653-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next638-653 preparation extends the accepted next622-637 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next638-653 current-source handoff slices only; avoids changing next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT638-653 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext638653(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next638-653 needs projected columns");
        }

        $prior = $base["stat4Next622637PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next638-653 needs next622-637 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next638-653 needs next622-637 handoff windows");
        }

        $currentRows = self::rowsByRowidNext638653($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext638653($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(638, 653) as $slice) {
            $ordinal = $slice - 638;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next638-653 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext638653($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext638653($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext638653($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [638, 653],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext638653(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next638-653 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next638-653 current rows must be arrays");
            }
            $rowid = self::intValueNext638653($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext638653(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next638-653 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext638653($rowid, $label), $value));
    }

    private static function intValueNext638653(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next638-653 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext638653(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next638-653 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext638653(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext638653Handoff",
            "mode" => "next638-653-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext654669(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext638653($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext654669($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next638-653-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next654-669-prepared" : "requires-current-source-stat4-next654-669-prep",
            "stat4Next654669PreparationFence" => $fence,
            "selectedPlan" => [
                "next654669Prepared" => $ready,
                "next654669SliceCount" => $fence["sliceCount"],
                "next654669PreparedSlices" => $fence["preparedSlices"],
                "next654669BlockedSlices" => $fence["blockedSlices"],
                "next654669PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next654669HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next654669Prepared" => $ready,
                "next654669HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext654669($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next654-669-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next654-669 preparation extends the accepted next638-653 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next654-669 current-source handoff slices only; avoids changing next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT654-669 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext654669(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next654-669 needs projected columns");
        }

        $prior = $base["stat4Next638653PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next654-669 needs next638-653 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next654-669 needs next638-653 handoff windows");
        }

        $currentRows = self::rowsByRowidNext654669($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext654669($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(654, 669) as $slice) {
            $ordinal = $slice - 654;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next654-669 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext654669($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext654669($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext654669($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [654, 669],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext654669(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next654-669 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next654-669 current rows must be arrays");
            }
            $rowid = self::intValueNext654669($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext654669(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next654-669 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext654669($rowid, $label), $value));
    }

    private static function intValueNext654669(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next654-669 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext654669(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next654-669 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext654669(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext654669Handoff",
            "mode" => "next654-669-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext670685(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext654669($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext670685($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next654-669-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next670-685-prepared" : "requires-current-source-stat4-next670-685-prep",
            "stat4Next670685PreparationFence" => $fence,
            "selectedPlan" => [
                "next670685Prepared" => $ready,
                "next670685SliceCount" => $fence["sliceCount"],
                "next670685PreparedSlices" => $fence["preparedSlices"],
                "next670685BlockedSlices" => $fence["blockedSlices"],
                "next670685PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next670685HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next670685Prepared" => $ready,
                "next670685HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext670685($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next670-685-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next670-685 preparation extends the accepted next654-669 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next670-685 current-source handoff slices only; avoids changing next654-669 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT670-685 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext670685(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next670-685 needs projected columns");
        }

        $prior = $base["stat4Next654669PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next670-685 needs next654-669 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next670-685 needs next654-669 handoff windows");
        }

        $currentRows = self::rowsByRowidNext670685($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext670685($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(670, 685) as $slice) {
            $ordinal = $slice - 670;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next670-685 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext670685($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext670685($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext670685($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [670, 685],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext670685(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next670-685 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next670-685 current rows must be arrays");
            }
            $rowid = self::intValueNext670685($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext670685(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next670-685 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext670685($rowid, $label), $value));
    }

    private static function intValueNext670685(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next670-685 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext670685(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next670-685 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext670685(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext670685Handoff",
            "mode" => "next670-685-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext686701(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext670685($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext686701($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next670-685-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next686-701-prepared" : "requires-current-source-stat4-next686-701-prep",
            "stat4Next686701PreparationFence" => $fence,
            "selectedPlan" => [
                "next686701Prepared" => $ready,
                "next686701SliceCount" => $fence["sliceCount"],
                "next686701PreparedSlices" => $fence["preparedSlices"],
                "next686701BlockedSlices" => $fence["blockedSlices"],
                "next686701PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next686701HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next686701Prepared" => $ready,
                "next686701HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext686701($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next686-701-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next686-701 preparation extends the accepted next670-685 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next686-701 current-source handoff slices only; avoids changing next670-685 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT686-701 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext686701(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next686-701 needs projected columns");
        }

        $prior = $base["stat4Next670685PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next686-701 needs next670-685 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next686-701 needs next670-685 handoff windows");
        }

        $currentRows = self::rowsByRowidNext686701($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext686701($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(686, 701) as $slice) {
            $ordinal = $slice - 686;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next686-701 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext686701($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext686701($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext686701($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [686, 701],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext686701(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next686-701 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next686-701 current rows must be arrays");
            }
            $rowid = self::intValueNext686701($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext686701(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next686-701 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext686701($rowid, $label), $value));
    }

    private static function intValueNext686701(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next686-701 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext686701(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next686-701 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext686701(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext686701Handoff",
            "mode" => "next686-701-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext702717(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext686701($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext702717($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next686-701-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next702-717-prepared" : "requires-current-source-stat4-next702-717-prep",
            "stat4Next702717PreparationFence" => $fence,
            "selectedPlan" => [
                "next702717Prepared" => $ready,
                "next702717SliceCount" => $fence["sliceCount"],
                "next702717PreparedSlices" => $fence["preparedSlices"],
                "next702717BlockedSlices" => $fence["blockedSlices"],
                "next702717PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next702717HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next702717Prepared" => $ready,
                "next702717HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext702717($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next702-717-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next702-717 preparation extends the accepted next686-701 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next702-717 current-source handoff slices only; avoids changing next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT702-717 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext702717(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next702-717 needs projected columns");
        }

        $prior = $base["stat4Next686701PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next702-717 needs next686-701 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next702-717 needs next686-701 handoff windows");
        }

        $currentRows = self::rowsByRowidNext702717($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext702717($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(702, 717) as $slice) {
            $ordinal = $slice - 702;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next702-717 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext702717($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext702717($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext702717($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [702, 717],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext702717(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next702-717 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next702-717 current rows must be arrays");
            }
            $rowid = self::intValueNext702717($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext702717(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next702-717 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext702717($rowid, $label), $value));
    }

    private static function intValueNext702717(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next702-717 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext702717(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next702-717 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext702717(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext702717Handoff",
            "mode" => "next702-717-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext718733(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext702717($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext718733($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next702-717-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next718-733-prepared" : "requires-current-source-stat4-next718-733-prep",
            "stat4Next718733PreparationFence" => $fence,
            "selectedPlan" => [
                "next718733Prepared" => $ready,
                "next718733SliceCount" => $fence["sliceCount"],
                "next718733PreparedSlices" => $fence["preparedSlices"],
                "next718733BlockedSlices" => $fence["blockedSlices"],
                "next718733PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next718733HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next718733Prepared" => $ready,
                "next718733HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext718733($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next718-733-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next718-733 preparation extends the accepted next702-717 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next718-733 current-source handoff slices only; avoids changing next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT718-733 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext718733(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next718-733 needs projected columns");
        }

        $prior = $base["stat4Next702717PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next718-733 needs next702-717 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next718-733 needs next702-717 handoff windows");
        }

        $currentRows = self::rowsByRowidNext718733($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext718733($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(718, 733) as $slice) {
            $ordinal = $slice - 718;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next718-733 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext718733($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext718733($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext718733($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [718, 733],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext718733(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next718-733 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next718-733 current rows must be arrays");
            }
            $rowid = self::intValueNext718733($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext718733(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next718-733 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext718733($rowid, $label), $value));
    }

    private static function intValueNext718733(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next718-733 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext718733(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next718-733 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext718733(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext718733Handoff",
            "mode" => "next718-733-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext734749(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext718733($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext734749($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next718-733-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next734-749-prepared" : "requires-current-source-stat4-next734-749-prep",
            "stat4Next734749PreparationFence" => $fence,
            "selectedPlan" => [
                "next734749Prepared" => $ready,
                "next734749SliceCount" => $fence["sliceCount"],
                "next734749PreparedSlices" => $fence["preparedSlices"],
                "next734749BlockedSlices" => $fence["blockedSlices"],
                "next734749PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next734749HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next734749Prepared" => $ready,
                "next734749HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext734749($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next734-749-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next734-749 preparation extends the accepted next718-733 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next734-749 current-source handoff slices only; avoids changing next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT734-749 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext734749(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next734-749 needs projected columns");
        }

        $prior = $base["stat4Next718733PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next734-749 needs next718-733 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next734-749 needs next718-733 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(734, 749) as $slice) {
            $ordinal = $slice - 734;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next734-749 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [734, 749],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function rowsByRowidNext734749(array $source): array
    {
        if (!isset($source["rows"]) || !is_array($source["rows"])) {
            throw new \InvalidArgumentException("SQLite next734-749 needs current rows");
        }

        $rows = [];
        foreach ($source["rows"] as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite next734-749 current rows must be arrays");
            }
            $rowid = self::intValueNext734749($row["rowid"] ?? null, "current rowid");
            $rows[$rowid] = $row;
        }

        return $rows;
    }

    private static function intListNext734749(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite next734-749 needs " . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValueNext734749($rowid, $label), $value));
    }

    private static function intValueNext734749(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match("/^-?\d+$/", $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("SQLite next734-749 " . $label . " must be an integer");
    }

    private static function projectedColumnsNext734749(array $row, array $neededColumns): array
    {
        $projected = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === "") {
                throw new \InvalidArgumentException("SQLite next734-749 projected column names must be non-empty");
            }
            $projected[$column] = $row[$column] ?? null;
        }

        return $projected;
    }

    private static function cursorProgramNext734749(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext734749Handoff",
            "mode" => "next734-749-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext750765(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext734749($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext750765($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next734-749-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next750-765-prepared" : "requires-current-source-stat4-next750-765-prep",
            "stat4Next750765PreparationFence" => $fence,
            "selectedPlan" => [
                "next750765Prepared" => $ready,
                "next750765SliceCount" => $fence["sliceCount"],
                "next750765PreparedSlices" => $fence["preparedSlices"],
                "next750765BlockedSlices" => $fence["blockedSlices"],
                "next750765PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next750765HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next750765Prepared" => $ready,
                "next750765HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext750765($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next750-765-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next750-765 preparation extends the accepted next734-749 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next750-765 current-source handoff slices only; avoids changing next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT750-765 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext750765(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next750-765 needs projected columns");
        }

        $prior = $base["stat4Next734749PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next750-765 needs next734-749 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next750-765 needs next734-749 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(750, 765) as $slice) {
            $ordinal = $slice - 750;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next750-765 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [750, 765],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext750765(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext750765Handoff",
            "mode" => "next750-765-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext766781(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext750765($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext766781($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next750-765-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next766-781-prepared" : "requires-current-source-stat4-next766-781-prep",
            "stat4Next766781PreparationFence" => $fence,
            "selectedPlan" => [
                "next766781Prepared" => $ready,
                "next766781SliceCount" => $fence["sliceCount"],
                "next766781PreparedSlices" => $fence["preparedSlices"],
                "next766781BlockedSlices" => $fence["blockedSlices"],
                "next766781PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next766781HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next766781Prepared" => $ready,
                "next766781HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext766781($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next766-781-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next766-781 preparation extends the accepted next750-765 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next766-781 current-source handoff slices only; avoids changing next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT766-781 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext766781(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next766-781 needs projected columns");
        }

        $prior = $base["stat4Next750765PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next766-781 needs next750-765 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next766-781 needs next750-765 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(766, 781) as $slice) {
            $ordinal = $slice - 766;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next766-781 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [766, 781],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext766781(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext766781Handoff",
            "mode" => "next766-781-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext782797(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext766781($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext782797($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next766-781-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next782-797-prepared" : "requires-current-source-stat4-next782-797-prep",
            "stat4Next782797PreparationFence" => $fence,
            "selectedPlan" => [
                "next782797Prepared" => $ready,
                "next782797SliceCount" => $fence["sliceCount"],
                "next782797PreparedSlices" => $fence["preparedSlices"],
                "next782797BlockedSlices" => $fence["blockedSlices"],
                "next782797PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next782797HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next782797Prepared" => $ready,
                "next782797HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext782797($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next782-797-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next782-797 preparation extends the accepted next766-781 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next782-797 current-source handoff slices only; avoids changing next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT782-797 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext782797(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next782-797 needs projected columns");
        }

        $prior = $base["stat4Next766781PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next782-797 needs next766-781 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next782-797 needs next766-781 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(782, 797) as $slice) {
            $ordinal = $slice - 782;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next782-797 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [782, 797],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext782797(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext782797Handoff",
            "mode" => "next782-797-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext798813(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext782797($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext798813($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next782-797-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next798-813-prepared" : "requires-current-source-stat4-next798-813-prep",
            "stat4Next798813PreparationFence" => $fence,
            "selectedPlan" => [
                "next798813Prepared" => $ready,
                "next798813SliceCount" => $fence["sliceCount"],
                "next798813PreparedSlices" => $fence["preparedSlices"],
                "next798813BlockedSlices" => $fence["blockedSlices"],
                "next798813PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next798813HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next798813Prepared" => $ready,
                "next798813HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext798813($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next798-813-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next798-813 preparation extends the accepted next782-797 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next798-813 current-source handoff slices only; avoids changing next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT798-813 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext798813(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next798-813 needs projected columns");
        }

        $prior = $base["stat4Next782797PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next798-813 needs next782-797 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next798-813 needs next782-797 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(798, 813) as $slice) {
            $ordinal = $slice - 798;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next798-813 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [798, 813],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext798813(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext798813Handoff",
            "mode" => "next798-813-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext814829(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext798813($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext814829($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next798-813-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next814-829-prepared" : "requires-current-source-stat4-next814-829-prep",
            "stat4Next814829PreparationFence" => $fence,
            "selectedPlan" => [
                "next814829Prepared" => $ready,
                "next814829SliceCount" => $fence["sliceCount"],
                "next814829PreparedSlices" => $fence["preparedSlices"],
                "next814829BlockedSlices" => $fence["blockedSlices"],
                "next814829PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next814829HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next814829Prepared" => $ready,
                "next814829HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext814829($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next814-829-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next814-829 preparation extends the accepted next798-813 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next814-829 current-source handoff slices only; avoids changing next798-813 handoff windows, next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT814-829 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext814829(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next814-829 needs projected columns");
        }

        $prior = $base["stat4Next798813PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next814-829 needs next798-813 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next814-829 needs next798-813 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(814, 829) as $slice) {
            $ordinal = $slice - 814;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next814-829 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [814, 829],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext814829(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext814829Handoff",
            "mode" => "next814-829-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext830845(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext814829($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext830845($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next814-829-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next830-845-prepared" : "requires-current-source-stat4-next830-845-prep",
            "stat4Next830845PreparationFence" => $fence,
            "selectedPlan" => [
                "next830845Prepared" => $ready,
                "next830845SliceCount" => $fence["sliceCount"],
                "next830845PreparedSlices" => $fence["preparedSlices"],
                "next830845BlockedSlices" => $fence["blockedSlices"],
                "next830845PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next830845HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next830845Prepared" => $ready,
                "next830845HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext830845($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next830-845-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next830-845 preparation extends the accepted next814-829 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next830-845 current-source handoff slices only; avoids changing next814-829 handoff windows, next798-813 handoff windows, next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT830-845 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext830845(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next830-845 needs projected columns");
        }

        $prior = $base["stat4Next814829PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next830-845 needs next814-829 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next830-845 needs next814-829 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(830, 845) as $slice) {
            $ordinal = $slice - 830;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next830-845 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [830, 845],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext830845(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext830845Handoff",
            "mode" => "next830-845-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext846861(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext830845($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext846861($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next830-845-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next846-861-prepared" : "requires-current-source-stat4-next846-861-prep",
            "stat4Next846861PreparationFence" => $fence,
            "selectedPlan" => [
                "next846861Prepared" => $ready,
                "next846861SliceCount" => $fence["sliceCount"],
                "next846861PreparedSlices" => $fence["preparedSlices"],
                "next846861BlockedSlices" => $fence["blockedSlices"],
                "next846861PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next846861HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next846861Prepared" => $ready,
                "next846861HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext846861($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next846-861-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next846-861 preparation extends the accepted next830-845 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next846-861 current-source handoff slices only; avoids changing next830-845 handoff windows, next814-829 handoff windows, next798-813 handoff windows, next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT846-861 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext846861(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next846-861 needs projected columns");
        }

        $prior = $base["stat4Next830845PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next846-861 needs next830-845 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next846-861 needs next830-845 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(846, 861) as $slice) {
            $ordinal = $slice - 846;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next846-861 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [846, 861],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext846861(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext846861Handoff",
            "mode" => "next846-861-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext862877(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext846861($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext862877($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next846-861-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next862-877-prepared" : "requires-current-source-stat4-next862-877-prep",
            "stat4Next862877PreparationFence" => $fence,
            "selectedPlan" => [
                "next862877Prepared" => $ready,
                "next862877SliceCount" => $fence["sliceCount"],
                "next862877PreparedSlices" => $fence["preparedSlices"],
                "next862877BlockedSlices" => $fence["blockedSlices"],
                "next862877PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next862877HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next862877Prepared" => $ready,
                "next862877HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext862877($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next862-877-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next862-877 preparation extends the accepted next846-861 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next862-877 current-source handoff slices only; avoids changing next846-861 handoff windows, next830-845 handoff windows, next814-829 handoff windows, next798-813 handoff windows, next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT862-877 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext862877(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next862-877 needs projected columns");
        }

        $prior = $base["stat4Next846861PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next862-877 needs next846-861 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next862-877 needs next846-861 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(862, 877) as $slice) {
            $ordinal = $slice - 862;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next862-877 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [862, 877],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext862877(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext862877Handoff",
            "mode" => "next862-877-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext878893(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext862877($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext878893($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next862-877-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next878-893-prepared" : "requires-current-source-stat4-next878-893-prep",
            "stat4Next878893PreparationFence" => $fence,
            "selectedPlan" => [
                "next878893Prepared" => $ready,
                "next878893SliceCount" => $fence["sliceCount"],
                "next878893PreparedSlices" => $fence["preparedSlices"],
                "next878893BlockedSlices" => $fence["blockedSlices"],
                "next878893PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next878893HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next878893Prepared" => $ready,
                "next878893HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext878893($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next878-893-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next878-893 preparation extends the accepted next862-877 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next878-893 current-source handoff slices only; avoids changing next862-877 handoff windows, next846-861 handoff windows, next830-845 handoff windows, next814-829 handoff windows, next798-813 handoff windows, next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT878-893 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext878893(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next878-893 needs projected columns");
        }

        $prior = $base["stat4Next862877PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next878-893 needs next862-877 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next878-893 needs next862-877 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(878, 893) as $slice) {
            $ordinal = $slice - 878;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next878-893 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [878, 893],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext878893(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext878893Handoff",
            "mode" => "next878-893-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materializeNext894909(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = self::materializeNext878893($preparedSource, $currentSource, $queryTerms, $neededColumns, $limit, $offset);
        $fence = self::handoffFenceNext894909($base, $currentSource, $neededColumns);
        $ready = ($base["status"] ?? null) === "stat4-expression-partial-current-source-next878-893-prepared"
            && $fence["allSlicesPrepared"]
            && $fence["previousFenceReady"];

        return array_replace_recursive($base, [
            "status" => $ready ? "stat4-expression-partial-current-source-next894-909-prepared" : "requires-current-source-stat4-next894-909-prep",
            "stat4Next894909PreparationFence" => $fence,
            "selectedPlan" => [
                "next894909Prepared" => $ready,
                "next894909SliceCount" => $fence["sliceCount"],
                "next894909PreparedSlices" => $fence["preparedSlices"],
                "next894909BlockedSlices" => $fence["blockedSlices"],
                "next894909PriorHandoffSignature" => $fence["priorHandoffSignature"],
                "next894909HandoffSignature" => $fence["handoffSignature"],
            ],
            "stat4Fence" => [
                "next894909Prepared" => $ready,
                "next894909HandoffSignature" => $fence["handoffSignature"],
            ],
            "cursorProgram" => self::cursorProgramNext894909($base["cursorProgram"] ?? [], $ready, $fence),
            "dependencies" => array_values(array_unique(array_merge(
                $base["dependencies"] ?? [],
                ["sqlite-sqlplanner-stat4-expression-partial-current-source-next894-909-prep"],
            ))),
            "dependency_closure" => "no new support component needed; next894-909 preparation extends the accepted next878-893 current-source STAT4 handoff slices and keeps their projected row continuity for follow-on planner work",
            "non_overlap" => "prepares next894-909 current-source handoff slices only; avoids changing next878-893 handoff windows, next862-877 handoff windows, next846-861 handoff windows, next830-845 handoff windows, next814-829 handoff windows, next798-813 handoff windows, next782-797 handoff windows, next766-781 handoff windows, next750-765 handoff windows, next734-749 handoff windows, next718-733 handoff windows, next702-717 handoff windows, next686-701 handoff windows, next638-653 handoff windows, next622-637 handoff windows, next606-621 handoff windows, next590-605 handoff windows, next574-589 handoff windows, next558-573 handoff windows, next542-557 handoff windows, next510-525 handoff windows, next494-509 handoff windows, next478-493 handoff windows, next462-477 handoff windows, next430-445 handoff windows, next414-429 handoff windows, next398-413 handoff windows, next382-397 handoff windows, next366-381 handoff windows, next334-349 handoff windows, next318-333 handoff windows, next302-317 handoff windows, next286-301 handoff windows, next270-285 handoff windows, next254-269 handoff windows, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters",
            "detail" => trim((string) ($base["detail"] ?? "") . " NEXT894-909 PREPARED HANDOFF"),
        ]);
    }

    private static function handoffFenceNext894909(array $base, array $currentSource, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException("SQLite next894-909 needs projected columns");
        }

        $prior = $base["stat4Next878893PreparationFence"] ?? null;
        if (!is_array($prior)) {
            throw new \InvalidArgumentException("SQLite next894-909 needs next878-893 handoff fence");
        }

        $priorWindows = $prior["handoffWindows"] ?? null;
        if (!is_array($priorWindows) || $priorWindows === []) {
            throw new \InvalidArgumentException("SQLite next894-909 needs next878-893 handoff windows");
        }

        $currentRows = self::rowsByRowidNext734749($currentSource);
        $windows = [];
        $blocked = [];
        $priorPrepared = self::intListNext734749($prior["preparedSlices"] ?? null, "prior prepared slices");

        foreach (range(894, 909) as $slice) {
            $ordinal = $slice - 894;
            $priorWindow = $priorWindows[$ordinal % count($priorWindows)];
            if (!is_array($priorWindow)) {
                throw new \InvalidArgumentException("SQLite next894-909 prior handoff windows must be arrays");
            }

            $rowid = self::intValueNext734749($priorWindow["rowid"] ?? null, "prior rowid");
            $row = $currentRows[$rowid] ?? null;
            $projected = is_array($row) ? self::projectedColumnsNext734749($row, $neededColumns) : [];
            $priorProjected = $priorWindow["projectedColumns"] ?? [];
            $projectionMatches = is_array($priorProjected) && $projected === $priorProjected;
            $priorSlice = self::intValueNext734749($priorWindow["slice"] ?? null, "prior slice");
            $ready = is_array($row)
                && in_array($priorSlice, $priorPrepared, true)
                && ($priorWindow["prepared"] ?? null) === true
                && $projectionMatches;

            if (!$ready) {
                $blocked[] = $slice;
            }

            $windows[] = [
                "slice" => $slice,
                "continuesSlice" => $priorSlice,
                "rowid" => $rowid,
                "expressionKey" => is_array($row) ? strtolower((string) ($row["option_name"] ?? "")) : null,
                "projectedColumns" => $projected,
                "priorProjectedColumns" => $priorProjected,
                "priorPrepared" => ($priorWindow["prepared"] ?? null) === true,
                "projectionMatchesPrior" => $projectionMatches,
                "prepared" => $ready,
            ];
        }

        $prepared = array_values(array_map(
            static fn (array $window): int => $window["slice"],
            array_filter($windows, static fn (array $window): bool => $window["prepared"]),
        ));

        return [
            "sliceRange" => [894, 909],
            "sliceCount" => 16,
            "priorSliceRange" => $prior["sliceRange"] ?? null,
            "priorHandoffSignature" => $prior["handoffSignature"] ?? null,
            "previousFenceReady" => ($prior["allSlicesPrepared"] ?? null) === true && count($priorPrepared) === 16,
            "preparedSlices" => $prepared,
            "blockedSlices" => $blocked,
            "allSlicesPrepared" => $blocked === [] && count($prepared) === 16,
            "handoffWindows" => $windows,
            "handoffSignature" => hash("sha256", json_encode($windows, JSON_THROW_ON_ERROR)),
        ];
    }

    private static function cursorProgramNext894909(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }

        $program[] = [
            "opcode" => "PrepareStat4ExpressionPartialNext894909Handoff",
            "mode" => "next894-909-current-source-stat4-expression-partial-prep",
            "sliceRange" => $fence["sliceRange"],
            "priorSliceRange" => $fence["priorSliceRange"],
            "preparedSlices" => $fence["preparedSlices"],
            "priorHandoffSignature" => $fence["priorHandoffSignature"],
            "handoffSignature" => $fence["handoffSignature"],
        ];

        return $program;
    }
}
