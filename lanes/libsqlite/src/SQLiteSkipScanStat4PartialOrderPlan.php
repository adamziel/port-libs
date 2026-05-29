<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSkipScanStat4PartialOrderPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function coveringCurrentSource(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $orderBy,
        array $neededColumns,
    ): array {
        $prepared = self::sourceCoveringPlan($preparedSource, $partialPredicate, $queryTerms, $orderBy, $neededColumns);
        $current = self::sourceCoveringPlan($currentSource, $partialPredicate, $queryTerms, $orderBy, $neededColumns);

        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $selectedSource = $stale ? $currentSource : $preparedSource;

        return [
            'status' => $selected === null ? 'unusable' : 'usable',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::nonNegativeSourceInt($preparedSource, 'schemaCookie') !== self::nonNegativeSourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::nonNegativeSourceInt($preparedSource, 'stat4Generation') !== self::nonNegativeSourceInt($currentSource, 'stat4Generation'),
            'indexRootChanged' => self::sourceString($preparedSource, 'indexName') !== self::sourceString($currentSource, 'indexName')
                || self::optionalSourceInt($preparedSource, 'rootPage') !== self::optionalSourceInt($currentSource, 'rootPage'),
            'rowSignatureChanged' => self::rowSignature(self::sourceRows($preparedSource)) !== self::rowSignature(self::sourceRows($currentSource)),
            'stat4SignatureChanged' => self::stat4Signature(self::sourceList($preparedSource, 'stat4Samples')) !== self::stat4Signature(self::sourceList($currentSource, 'stat4Samples')),
            'coveringSignatureChanged' => self::columnSignature(self::sourceStringList($preparedSource, 'coveringColumns')) !== self::columnSignature(self::sourceStringList($currentSource, 'coveringColumns')),
            'preparedSource' => self::sourceFenceSummary($preparedSource, $prepared, $preparedSignature),
            'currentSource' => self::sourceFenceSummary($currentSource, $current, $currentSignature),
            'selectedPlan' => $selected,
            'currentSourceFence' => [
                'name' => self::sourceString($selectedSource, 'name'),
                'schemaCookie' => self::nonNegativeSourceInt($selectedSource, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeSourceInt($selectedSource, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'rootPage' => self::optionalSourceInt($selectedSource, 'rootPage'),
                'coveringSignature' => self::columnSignature(self::sourceStringList($selectedSource, 'coveringColumns')),
            ],
            'detail' => ($stale ? 'REPREPARE ' : 'REUSE PREPARED ')
                . 'COVERING PARTIAL SKIP-SCAN '
                . self::sourceString($selectedSource, 'name')
                . ' '
                . ($selected['detail'] ?? 'NO PLAN'),
            'dependencies' => ['sqlite-sqlplanner-covering-partial-skipscan-current-source'],
        ];
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function partialCoveringSkipScan(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $orderByExpressions,
        array $neededColumns,
    ): array {
        $preparedOrder = self::reducedOrderByExpressions($preparedSource, $queryTerms, $orderByExpressions);
        $currentOrder = self::reducedOrderByExpressions($currentSource, $queryTerms, $orderByExpressions);

        $prepared = self::coveringCurrentSource(
            $preparedSource,
            $preparedSource,
            $partialPredicate,
            $queryTerms,
            $preparedOrder['orderBy'],
            array_values(array_unique(array_merge($neededColumns, $preparedOrder['neededExpressionColumns']))),
        );
        $current = self::coveringCurrentSource(
            $currentSource,
            $currentSource,
            $partialPredicate,
            $queryTerms,
            $currentOrder['orderBy'],
            array_values(array_unique(array_merge($neededColumns, $currentOrder['neededExpressionColumns']))),
        );

        $preparedSignature = self::sourceSignature($preparedSource) . '|' . self::orderExpressionSignature($preparedOrder);
        $currentSignature = self::sourceSignature($currentSource) . '|' . self::orderExpressionSignature($currentOrder);
        $stale = $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $selectedOrder = $stale ? $currentOrder : $preparedOrder;
        $selectedSource = $stale ? $currentSource : $preparedSource;
        $selectedPlan = is_array($selected['selectedPlan'] ?? null) ? $selected['selectedPlan'] : null;

        if ($selectedPlan !== null && $selectedOrder['uncoveredExpressions'] !== []) {
            $selectedPlan = array_replace($selectedPlan, [
                'covering' => false,
                'tableSeekRequired' => true,
                'deferredSeekOpcode' => 'DeferredSeek',
                'coveringRejectedColumns' => array_values(array_unique(array_merge(
                    $selectedPlan['coveringRejectedColumns'] ?? [],
                    $selectedOrder['uncoveredExpressions'],
                ))),
                'coveringMode' => 'skipscan-expression-order-table-seek',
                'detail' => ($selectedPlan['detail'] ?? 'SEARCH USING SKIP-SCAN') . ' ORDER BY EXPRESSION NEEDS TABLE',
            ]);
        }

        return [
            'status' => $selected['status'] ?? 'unusable',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::nonNegativeSourceInt($preparedSource, 'schemaCookie') !== self::nonNegativeSourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::nonNegativeSourceInt($preparedSource, 'stat4Generation') !== self::nonNegativeSourceInt($currentSource, 'stat4Generation'),
            'orderExpressionSignatureChanged' => self::orderExpressionSignature($preparedOrder) !== self::orderExpressionSignature($currentOrder),
            'preparedOrder' => $preparedOrder,
            'currentOrder' => $currentOrder,
            'selectedOrder' => $selectedOrder,
            'preparedSource' => $prepared['preparedSource'] ?? null,
            'currentSource' => $current['preparedSource'] ?? null,
            'selectedPlan' => $selectedPlan,
            'currentSourceFence' => [
                'name' => self::sourceString($selectedSource, 'name'),
                'schemaCookie' => self::nonNegativeSourceInt($selectedSource, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeSourceInt($selectedSource, 'stat4Generation'),
                'orderExpressionSignature' => self::orderExpressionSignature($selectedOrder),
                'coveringSignature' => self::columnSignature(self::sourceStringList($selectedSource, 'coveringColumns')),
            ],
            'detail' => ($stale ? 'REPREPARE ' : 'REUSE PREPARED ')
                . 'PARTIAL COVERING SKIP-SCAN ORDER EXPRESSIONS '
                . self::sourceString($selectedSource, 'name')
                . ' constants=' . count($selectedOrder['constantExpressions'])
                . ' uncovered=' . count($selectedOrder['uncoveredExpressions']),
            'dependencies' => ['sqlite-sqlplanner-partial-covering-skipscan-current-source'],
        ];
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function partialExpressionSkipScan(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $orderByExpressions,
        array $neededColumns,
    ): array {
        $preparedExpression = self::sourceString($preparedSource, 'rangeExpression');
        $currentExpression = self::sourceString($currentSource, 'rangeExpression');
        $preparedRangeColumn = self::sourceString($preparedSource, 'rangeExpressionColumn', '__sqlite_expr_range');
        $currentRangeColumn = self::sourceString($currentSource, 'rangeExpressionColumn', '__sqlite_expr_range');

        $preparedMaterialized = self::materializedExpressionSource($preparedSource, $preparedExpression, $preparedRangeColumn);
        $currentMaterialized = self::materializedExpressionSource($currentSource, $currentExpression, $currentRangeColumn);
        $preparedQuery = self::materializedExpressionTerms($queryTerms, $preparedExpression, $preparedRangeColumn);
        $currentQuery = self::materializedExpressionTerms($queryTerms, $currentExpression, $currentRangeColumn);
        $preparedOrder = self::materializedExpressionOrder($orderByExpressions, $preparedExpression, $preparedRangeColumn);
        $currentOrder = self::materializedExpressionOrder($orderByExpressions, $currentExpression, $currentRangeColumn);

        $prepared = self::partialCoveringSkipScan(
            $preparedMaterialized,
            $preparedMaterialized,
            $partialPredicate,
            $preparedQuery,
            $preparedOrder,
            $neededColumns,
        );
        $current = self::partialCoveringSkipScan(
            $currentMaterialized,
            $currentMaterialized,
            $partialPredicate,
            $currentQuery,
            $currentOrder,
            $neededColumns,
        );

        $preparedSignature = self::sourceSignature($preparedMaterialized)
            . '|' . self::expressionSourceSignature($preparedExpression, $preparedRangeColumn, $preparedOrder, $preparedQuery);
        $currentSignature = self::sourceSignature($currentMaterialized)
            . '|' . self::expressionSourceSignature($currentExpression, $currentRangeColumn, $currentOrder, $currentQuery);
        $stale = $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $selectedSource = $stale ? $currentMaterialized : $preparedMaterialized;
        $selectedExpression = $stale ? $currentExpression : $preparedExpression;
        $selectedRangeColumn = $stale ? $currentRangeColumn : $preparedRangeColumn;
        $selectedPlan = is_array($selected['selectedPlan'] ?? null) ? $selected['selectedPlan'] : null;

        if ($selectedPlan !== null) {
            $selectedPlan = array_replace($selectedPlan, [
                'rangeExpression' => $selectedExpression,
                'rangeExpressionColumn' => $selectedRangeColumn,
                'expressionSkipScan' => true,
                'coveringMode' => ($selectedPlan['coveringMode'] ?? 'covering-skipscan') . '-expression',
                'detail' => ($selectedPlan['detail'] ?? 'SEARCH USING SKIP-SCAN') . ' EXPRESSION RANGE ' . $selectedExpression,
            ]);
        }

        return [
            'status' => $selected['status'] ?? 'unusable',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::nonNegativeSourceInt($preparedSource, 'schemaCookie') !== self::nonNegativeSourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::nonNegativeSourceInt($preparedSource, 'stat4Generation') !== self::nonNegativeSourceInt($currentSource, 'stat4Generation'),
            'rangeExpressionChanged' => self::normalizeExpression($preparedExpression) !== self::normalizeExpression($currentExpression),
            'expressionColumnChanged' => $preparedRangeColumn !== $currentRangeColumn,
            'expressionSignatureChanged' => self::expressionSourceSignature($preparedExpression, $preparedRangeColumn, $preparedOrder, $preparedQuery)
                !== self::expressionSourceSignature($currentExpression, $currentRangeColumn, $currentOrder, $currentQuery),
            'preparedExpression' => self::expressionSummary($preparedExpression, $preparedRangeColumn, $preparedMaterialized, $prepared),
            'currentExpression' => self::expressionSummary($currentExpression, $currentRangeColumn, $currentMaterialized, $current),
            'selectedPlan' => $selectedPlan,
            'currentSourceFence' => [
                'name' => self::sourceString($selectedSource, 'name'),
                'schemaCookie' => self::nonNegativeSourceInt($selectedSource, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeSourceInt($selectedSource, 'stat4Generation'),
                'rangeExpression' => $selectedExpression,
                'rangeExpressionColumn' => $selectedRangeColumn,
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
            ],
            'detail' => ($stale ? 'REPREPARE ' : 'REUSE PREPARED ')
                . 'PARTIAL EXPRESSION SKIP-SCAN '
                . self::sourceString($selectedSource, 'name')
                . ' expr=' . $selectedExpression,
            'dependencies' => ['sqlite-sqlplanner-partial-expression-skipscan-current-source'],
            'dependency_closure' => 'no new support component needed; current-source reuses native PHP skip-scan, partial predicate proof, current-source fences, and bounded SQL expression-key materialization',
        ];
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
     * @param list<string> $neededColumns
     * @param list<string> $neededExpressions
     * @return array<string,mixed>
     */
    public static function expressionCoveringSkipScan(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $orderByExpressions,
        array $neededColumns,
        array $neededExpressions,
    ): array {
        $neededExpressions = self::validatedExpressionList($neededExpressions, 'SQLite expression covering skip-scan projection');
        $base = self::partialExpressionSkipScan(
            $preparedSource,
            $currentSource,
            $partialPredicate,
            $queryTerms,
            $orderByExpressions,
            $neededColumns,
        );

        $selectedOriginalSource = ($base['selectedSource'] ?? null) === 'current' ? $currentSource : $preparedSource;
        $selectedPlan = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : null;
        if ($selectedPlan === null) {
            return array_replace($base, [
                'status' => 'unusable',
                'dependencies' => ['sqlite-sqlplanner-expression-covering-skipscan-current-source'],
                'dependency_closure' => 'no new support component needed; current-source reuses native PHP expression skip-scan materialization and covering-index cursor evidence',
            ]);
        }

        $coveredExpressions = self::sourceExpressionList($selectedOriginalSource, 'coveringExpressions');
        $rangeExpression = self::sourceString($selectedOriginalSource, 'rangeExpression');
        if (!in_array($rangeExpression, $coveredExpressions, true)) {
            $coveredExpressions[] = $rangeExpression;
        }

        $missing = [];
        foreach ($neededExpressions as $expression) {
            if (!self::expressionListContains($coveredExpressions, $expression)) {
                $missing[] = $expression;
            }
        }

        $expressionRows = self::expressionCoveringRowsForPlan($selectedPlan, $selectedOriginalSource, $neededExpressions);
        $expressionColumns = [];
        foreach ($neededExpressions as $expression) {
            $expressionColumns[$expression] = self::expressionCoveringColumnName($selectedOriginalSource, $expression);
        }

        $expressionCovering = $missing === [];
        $selectedPlan = array_replace($selectedPlan, [
            'expressionCovering' => $expressionCovering,
            'coveredExpressions' => $coveredExpressions,
            'neededExpressions' => $neededExpressions,
            'expressionCoveringColumns' => $expressionColumns,
            'expressionCoveringRows' => $expressionRows,
            'expressionCoveringRejected' => $missing,
            'covering' => (bool) ($selectedPlan['covering'] ?? false) && $expressionCovering,
            'tableSeekRequired' => (bool) ($selectedPlan['tableSeekRequired'] ?? false) || !$expressionCovering,
            'deferredSeekOpcode' => ((bool) ($selectedPlan['tableSeekRequired'] ?? false) || !$expressionCovering) ? 'DeferredSeek' : null,
            'coveringMode' => $expressionCovering
                ? ($selectedPlan['coveringMode'] ?? 'covering-skipscan-expression') . '-expr-covering'
                : 'skipscan-expression-covering-table-seek',
            'cursorProgram' => self::cursorProgramWithExpressionColumns($selectedPlan, $expressionColumns),
            'detail' => ($selectedPlan['detail'] ?? 'SEARCH USING SKIP-SCAN')
                . ($expressionCovering ? ' USING COVERING EXPRESSIONS' : ' EXPRESSION PROJECTION NEEDS TABLE'),
        ]);

        return array_replace($base, [
            'status' => $base['status'] ?? 'unusable',
            'selectedPlan' => $selectedPlan,
            'currentSourceFence' => array_replace(
                is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                [
                    'coveringExpressionSignature' => self::expressionListSignature($coveredExpressions),
                    'neededExpressionSignature' => self::expressionListSignature($neededExpressions),
                ],
            ),
            'detail' => ($base['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                . ' expression-covering=' . ($expressionCovering ? 'yes' : 'no'),
            'dependencies' => ['sqlite-sqlplanner-expression-covering-skipscan-current-source'],
            'dependency_closure' => 'no new support component needed; current-source reuses native PHP expression skip-scan materialization, current-source fences, and covering-index cursor evidence',
        ]);
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
     * @param list<string> $neededColumns
     * @param array<string,mixed>|null $nextSource
     * @return array<string,mixed>
     */
    public static function expressionPartialSkipScan(
        array $preparedSource,
        array $currentSource,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $orderByExpressions,
        array $neededColumns,
        ?array $nextSource = null,
    ): array {
        $base = self::partialExpressionSkipScan(
            $preparedSource,
            $currentSource,
            $partialPredicate,
            $queryTerms,
            $orderByExpressions,
            $neededColumns,
        );

        $selectedPlan = is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : null;
        $selectedSource = ($base['selectedSource'] ?? null) === 'current' ? $currentSource : $preparedSource;
        $selectedExpression = self::sourceString($selectedSource, 'rangeExpression');
        $selectedRangeColumn = self::sourceString($selectedSource, 'rangeExpressionColumn');
        $materializedSelected = self::materializedExpressionSource($selectedSource, $selectedExpression, $selectedRangeColumn);
        $selectedSignature = self::sourceSignature($materializedSelected);
        $nextSummary = null;
        $nextSourceAdmitted = true;
        if ($nextSource !== null) {
            $nextExpression = self::sourceString($nextSource, 'rangeExpression');
            $nextRangeColumn = self::sourceString($nextSource, 'rangeExpressionColumn');
            $materializedNext = self::materializedExpressionSource($nextSource, $nextExpression, $nextRangeColumn);
            $nextSignature = self::sourceSignature($materializedNext);
            $nextSourceAdmitted = $nextSignature === $selectedSignature;
            $nextSummary = [
                'name' => self::sourceString($nextSource, 'name'),
                'schemaCookie' => self::nonNegativeSourceInt($nextSource, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeSourceInt($nextSource, 'stat4Generation'),
                'rangeExpression' => $nextExpression,
                'rangeExpressionColumn' => $nextRangeColumn,
                'sourceSignature' => $nextSignature,
                'admitted' => $nextSourceAdmitted,
                'replanReasons' => self::nextSourceReplanReasons($selectedSource, $nextSource, $selectedSignature, $nextSignature),
            ];
        }

        if ($selectedPlan !== null) {
            $selectedPlan = array_replace($selectedPlan, [
                'expressionPartialSkipScan' => true,
                'partialPredicateFence' => self::predicateFence($partialPredicate),
                'sourceSignature' => $selectedSignature,
                'nextSourceAdmitted' => $nextSourceAdmitted,
                'detail' => ($selectedPlan['detail'] ?? 'SEARCH USING SKIP-SCAN')
                    . ' PARTIAL EXPRESSION CURRENT-SOURCE FENCE',
            ]);
        }

        return array_replace($base, [
            'status' => (($base['status'] ?? null) === 'usable' && $selectedPlan !== null && $nextSourceAdmitted)
                ? 'expression-partial-skipscan-current-source-ready'
                : 'requires-current-source-reprepare',
            'selectedPlan' => $selectedPlan,
            'currentSourceFence' => array_replace(
                is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                [
                    'partialPredicateSignature' => self::predicateSignature($partialPredicate),
                    'selectedSourceSignature' => $selectedSignature,
                    'rangeExpression' => $selectedExpression,
                    'rangeExpressionColumn' => $selectedRangeColumn,
                ],
            ),
            'nextSource' => $nextSummary,
            'nextSourceAdmitted' => $nextSourceAdmitted,
            'partialPredicateFence' => self::predicateFence($partialPredicate),
            'detail' => ($base['detail'] ?? 'PARTIAL EXPRESSION SKIP-SCAN')
                . ' CURRENT-SOURCE PARTIAL FENCE current-source',
            'dependencies' => ['sqlite-sqlplanner-expression-partial-skipscan-current-source'],
            'dependency_closure' => 'no new support component needed; current-source reuses native PHP expression skip-scan materialization, partial-index proof, STAT4 skip-scan estimates, and current-source fences',
            'non_overlap' => 'avoids accepted current-source expression skip-scan materialization and current-source expression-covering rows by adding current-source partial-predicate source fencing and explicit next-source rejection for stale expression keys',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $stat4Samples
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $coveringColumns
     * @param list<string> $neededColumns
     * @return array<string,mixed>|null
     */
    public static function coveringCurrentSourcePlan(
        array $rows,
        string $indexName,
        string $skippedColumn,
        string $rangeColumn,
        mixed $lowerInclusive,
        mixed $upperBound,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $stat4Samples,
        array $orderBy,
        array $coveringColumns,
        array $neededColumns,
        bool $upperInclusive = true,
        string $collation = 'BINARY',
    ): ?array {
        $coveringColumns = self::validatedColumnList($coveringColumns, 'SQLite STAT4 skip-scan covering index');
        $neededColumns = self::validatedColumnList($neededColumns, 'SQLite STAT4 skip-scan covering projection');

        $plan = self::plan(
            $rows,
            $indexName,
            $skippedColumn,
            $rangeColumn,
            $lowerInclusive,
            $upperBound,
            $partialPredicate,
            $queryTerms,
            $stat4Samples,
            $orderBy,
            $upperInclusive,
            $collation,
        );

        if (($plan['status'] ?? null) !== 'usable') {
            return null;
        }

        $coveringSet = array_fill_keys(array_map('strtolower', $coveringColumns), true);
        $missing = [];
        foreach ($neededColumns as $column) {
            if (!isset($coveringSet[strtolower($column)])) {
                $missing[] = $column;
            }
        }
        if ($missing !== []) {
            return $plan + [
                'covering' => false,
                'coveringRejectedColumns' => $missing,
                'tableSeekRequired' => true,
                'deferredSeekOpcode' => 'DeferredSeek',
                'dependencies' => ['sqlite-stat4-skipscan-covering-current-source-next120'],
            ];
        }

        $currentNextRows = [];
        $matchRows = array_values($plan['rows'] ?? []);
        foreach ($matchRows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 skip-scan covering rows must be row arrays');
            }
            $currentNextRows[] = [
                'current' => self::coveringRowEvidence($row, $neededColumns, $offset),
                'next' => isset($matchRows[$offset + 1]) ? self::coveringRowEvidence($matchRows[$offset + 1], $neededColumns, $offset + 1) : null,
            ];
        }

        $cursorProgram = [
            ['opcode' => $plan['reverseScan'] ? 'LastPrefix' : 'RewindPrefix', 'source' => 'index', 'index' => $indexName],
            ['opcode' => $plan['upperInclusive'] ? 'SeekGE' : 'SeekGT', 'column' => $rangeColumn, 'value' => $lowerInclusive],
            ['opcode' => $plan['upperInclusive'] ? 'IdxGT' : 'IdxGE', 'column' => $rangeColumn, 'value' => $upperBound],
            ['opcode' => 'Column', 'source' => 'index', 'columns' => $neededColumns],
            ['opcode' => $plan['reverseScan'] ? 'Prev' : 'Next', 'target' => 'index'],
        ];

        return array_replace($plan, [
            'covering' => true,
            'coveringColumns' => $coveringColumns,
            'neededColumns' => $neededColumns,
            'coveringRejectedColumns' => [],
            'tableSeekRequired' => false,
            'deferredSeekOpcode' => null,
            'currentNextCoveringRows' => $currentNextRows,
            'coveredRowCount' => count($currentNextRows),
            'cursorProgram' => $cursorProgram,
            'coveringMode' => ($plan['blockSortRequired'] ?? false) ? 'covering-skipscan-block-sort' : 'covering-skipscan',
            'dependencies' => ['sqlite-stat4-skipscan-covering-current-source-next120'],
            'detail' => $plan['detail'] . ' USING COVERING INDEX',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $stat4Samples
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function plan(
        array $rows,
        string $indexName,
        string $skippedColumn,
        string $rangeColumn,
        mixed $lowerInclusive,
        mixed $upperBound,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        array $stat4Samples,
        array $orderBy = [],
        bool $upperInclusive = true,
        string $collation = 'BINARY',
    ): array {
        if ($indexName === '' || $skippedColumn === '' || $rangeColumn === '') {
            throw new \InvalidArgumentException('SQLite STAT4 skip-scan planner needs index and column names');
        }
        if ($skippedColumn === $rangeColumn) {
            throw new \InvalidArgumentException('SQLite STAT4 skip-scan range column must differ from skipped column');
        }

        $scan = SQLiteIndexSkipScanPlan::betweenPartialRows(
            $rows,
            $indexName,
            $skippedColumn,
            $rangeColumn,
            $lowerInclusive,
            $upperBound,
            $partialPredicate,
            $queryTerms,
            $upperInclusive,
            null,
            0,
            $collation,
        );

        if ($scan['status'] !== 'usable') {
            return $scan + [
                'stat4SamplesUsed' => 0,
                'stat4LoopEstimates' => [],
                'estimatedRows' => 0,
                'estimatedCost' => 0,
                'orderByMode' => 'none',
                'orderBySatisfied' => false,
                'partialOrderBy' => false,
                'blockSortRequired' => false,
                'sortBreakColumns' => [],
                'detail' => 'UNUSABLE PARTIAL INDEX ' . $indexName,
            ];
        }

        $sampleMap = self::samplesByPrefix($stat4Samples);
        $loopEstimates = [];
        $estimatedRows = 0;
        foreach ($scan['loops'] as $loop) {
            $prefix = $loop['prefix'];
            $samples = $sampleMap[self::key($prefix)] ?? [];
            $estimate = self::estimateLoopRows($samples, $lowerInclusive, $upperBound, $upperInclusive, $collation, (int) $loop['matched']);
            $currentNext = self::currentNextForRange($samples, $lowerInclusive, $upperBound, $upperInclusive, $collation);
            $loopEstimates[] = [
                'prefix' => $prefix,
                'matched' => $loop['matched'],
                'estimatedRows' => $estimate,
                'sampleCount' => count($samples),
                'rowids' => $loop['rowids'],
                'current' => $currentNext['current'],
                'next' => $currentNext['next'],
                'rangeSamples' => $currentNext['rangeSamples'],
            ];
            $estimatedRows += $estimate;
        }

        $orderEvidence = self::orderEvidence($skippedColumn, $rangeColumn, $orderBy);
        $estimatedSeeks = (int) $scan['estimatedSeeks'];
        $blockSortPenalty = $orderEvidence['blockSortRequired'] ? max(1, $estimatedRows) : 0;
        $cost = $estimatedSeeks * 8 + $estimatedRows + $blockSortPenalty;

        return $scan + [
            'stat4SamplesUsed' => array_sum(array_column($loopEstimates, 'sampleCount')),
            'stat4LoopEstimates' => $loopEstimates,
            'stat4CurrentNextByPrefix' => self::currentNextByPrefix($loopEstimates),
            'estimatedRows' => $estimatedRows,
            'estimatedCost' => $cost,
            'orderByMode' => $orderEvidence['mode'],
            'orderBySatisfied' => $orderEvidence['satisfied'],
            'partialOrderBy' => $orderEvidence['partial'],
            'blockSortRequired' => $orderEvidence['blockSortRequired'],
            'sortBreakColumns' => $orderEvidence['breakColumns'],
            'orderByDirections' => $orderEvidence['directions'],
            'reverseScan' => $orderEvidence['reverseScan'],
            'sortBlockCount' => $orderEvidence['blockSortRequired'] ? $estimatedSeeks : 0,
            'detail' => self::detail($indexName, $skippedColumn, $rangeColumn, $orderEvidence),
        ];
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @return array<string,list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}>>
     */
    private static function samplesByPrefix(array $samples): array
    {
        $byPrefix = [];
        foreach ($samples as $sample) {
            foreach (['nEq', 'nLt', 'nDLt'] as $field) {
                if (!isset($sample[$field]) || !is_int($sample[$field]) || $sample[$field] < 0) {
                    throw new \InvalidArgumentException('SQLite STAT4 samples need non-negative integer counters');
                }
            }
            $byPrefix[self::key($sample['prefix'])][] = $sample;
        }

        return $byPrefix;
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     */
    private static function estimateLoopRows(array $samples, mixed $lower, mixed $upper, bool $upperInclusive, string $collation, int $fallback): int
    {
        if ($samples === []) {
            return max(1, $fallback);
        }

        $best = null;
        foreach ($samples as $sample) {
            if (!self::within($sample['suffix'], $lower, $upper, $upperInclusive, $collation)) {
                continue;
            }
            $estimate = max(1, $sample['nEq']);
            $best = $best === null ? $estimate : min($best, $estimate);
        }
        if ($best !== null) {
            return $best;
        }

        $span = 0;
        foreach ($samples as $sample) {
            $span = max($span, $sample['nEq']);
        }

        return max(1, min(max(1, $fallback), $span === 0 ? $fallback : $span));
    }

    /**
     * @param list<array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}> $samples
     * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,rangeSamples:int}
     */
    private static function currentNextForRange(array $samples, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): array
    {
        $inRange = [];
        foreach ($samples as $sample) {
            if (self::within($sample['suffix'], $lower, $upper, $upperInclusive, $collation)) {
                $inRange[] = $sample;
            }
        }

        usort($inRange, static fn (array $left, array $right): int => self::compare($left['suffix'], $right['suffix'], $collation));

        return [
            'current' => self::sampleEvidence($inRange[0] ?? null),
            'next' => self::sampleEvidence($inRange[1] ?? null),
            'rangeSamples' => count($inRange),
        ];
    }

    /**
     * @param array{prefix:mixed,suffix:mixed,nEq:int,nLt:int,nDLt:int}|null $sample
     * @return array<string,mixed>|null
     */
    private static function sampleEvidence(?array $sample): ?array
    {
        if ($sample === null) {
            return null;
        }

        return [
            'prefix' => $sample['prefix'],
            'suffix' => $sample['suffix'],
            'nEq' => $sample['nEq'],
            'nLt' => $sample['nLt'],
            'nDLt' => $sample['nDLt'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $loopEstimates
     * @return list<array{prefix:mixed,current:array<string,mixed>|null,next:array<string,mixed>|null,rangeSamples:int}>
     */
    private static function currentNextByPrefix(array $loopEstimates): array
    {
        return array_map(
            static fn (array $loop): array => [
                'prefix' => $loop['prefix'],
                'current' => $loop['current'] ?? null,
                'next' => $loop['next'] ?? null,
                'rangeSamples' => (int) ($loop['rangeSamples'] ?? 0),
            ],
            $loopEstimates,
        );
    }

    private static function within(mixed $value, mixed $lower, mixed $upper, bool $upperInclusive, string $collation): bool
    {
        if ($value === null) {
            return false;
        }
        if ($lower !== null && self::compare($value, $lower, $collation) < 0) {
            return false;
        }
        if ($upper !== null) {
            $comparison = self::compare($value, $upper, $collation);
            if ($comparison > 0 || ($comparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{mode:string,satisfied:bool,partial:bool,blockSortRequired:bool,breakColumns:list<string>,directions:list<string>,reverseScan:bool}
     */
    private static function orderEvidence(string $skippedColumn, string $rangeColumn, array $orderBy): array
    {
        if ($orderBy === []) {
            return ['mode' => 'none', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => false, 'breakColumns' => [], 'directions' => [], 'reverseScan' => false];
        }

        $columns = [];
        $directions = [];
        foreach ($orderBy as $term) {
            $column = strtolower((string) ($term['column'] ?? ''));
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException('SQLite STAT4 skip-scan ORDER BY direction must be ASC or DESC');
            }
            $columns[] = $column;
            $directions[] = $direction;
        }

        $allDesc = $directions !== [] && count(array_unique($directions)) === 1 && $directions[0] === 'DESC';
        if ($columns === [strtolower($skippedColumn), strtolower($rangeColumn)]) {
            if (count(array_unique($directions)) === 1) {
                return ['mode' => $allDesc ? 'full-reverse' : 'full', 'satisfied' => true, 'partial' => false, 'blockSortRequired' => false, 'breakColumns' => [], 'directions' => $directions, 'reverseScan' => $allDesc];
            }

            return ['mode' => 'mixed-direction-external-sort', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => true, 'breakColumns' => [$skippedColumn, $rangeColumn], 'directions' => $directions, 'reverseScan' => false];
        }
        if ($columns[0] === strtolower($rangeColumn)) {
            return ['mode' => 'partial-current-next', 'satisfied' => false, 'partial' => true, 'blockSortRequired' => true, 'breakColumns' => [$skippedColumn], 'directions' => $directions, 'reverseScan' => $directions[0] === 'DESC'];
        }
        if ($columns[0] === strtolower($skippedColumn)) {
            return ['mode' => $directions[0] === 'DESC' ? 'prefix-only-reverse' : 'prefix-only', 'satisfied' => count($columns) === 1, 'partial' => count($columns) > 1, 'blockSortRequired' => count($columns) > 1, 'breakColumns' => [$rangeColumn], 'directions' => $directions, 'reverseScan' => $directions[0] === 'DESC'];
        }

        return ['mode' => 'external-sort', 'satisfied' => false, 'partial' => false, 'blockSortRequired' => true, 'breakColumns' => $columns, 'directions' => $directions, 'reverseScan' => false];
    }

    /**
     * @param array{mode:string,satisfied:bool,partial:bool,blockSortRequired:bool,breakColumns:list<string>,directions:list<string>,reverseScan:bool} $orderEvidence
     */
    private static function detail(string $indexName, string $skippedColumn, string $rangeColumn, array $orderEvidence): string
    {
        $detail = 'SEARCH USING SKIP-SCAN ' . $indexName . ' (ANY(' . $skippedColumn . ') AND ' . $rangeColumn . ' RANGE) USING STAT4';
        if ($orderEvidence['partial']) {
            return $detail . ' USE TEMP B-TREE FOR RIGHT PART OF ORDER BY';
        }
        if ($orderEvidence['satisfied']) {
            return $detail . ' ORDER BY SATISFIED';
        }

        return $detail;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        $leftText = (string) $left;
        $rightText = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $leftText = strtolower($leftText);
            $rightText = strtolower($rightText);
        }

        return strcmp($leftText, $rightText) <=> 0;
    }

    private static function key(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function validatedColumnList(array $columns, string $context): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException($context . ' needs at least one column');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException($context . ' columns must be non-empty strings');
            }
        }

        return array_values($columns);
    }

    /**
     * @param list<string> $expressions
     * @return list<string>
     */
    private static function validatedExpressionList(array $expressions, string $context): array
    {
        if ($expressions === []) {
            throw new \InvalidArgumentException($context . ' needs at least one expression');
        }
        foreach ($expressions as $expression) {
            if (!is_string($expression) || trim($expression) === '') {
                throw new \InvalidArgumentException($context . ' expressions must be non-empty strings');
            }
        }

        return array_values($expressions);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function coveringRowEvidence(array $row, array $columns, int $sourceOffset): array
    {
        $covering = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite STAT4 skip-scan covering row is missing column {$column}");
            }
            $covering[$column] = $row[$column];
        }

        return [
            'rowid' => (int) ($row['rowid'] ?? 0),
            'sourceOffset' => $sourceOffset,
            'covering' => $covering,
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>|null
     */
    private static function sourceCoveringPlan(array $source, SQLiteIndexPredicate $partialPredicate, array $queryTerms, array $orderBy, array $neededColumns): ?array
    {
        return self::coveringCurrentSourcePlan(
            self::sourceRows($source),
            self::sourceString($source, 'indexName'),
            self::sourceString($source, 'skippedColumn'),
            self::sourceString($source, 'rangeColumn'),
            $source['lowerInclusive'] ?? null,
            $source['upperBound'] ?? null,
            $partialPredicate,
            $queryTerms,
            self::sourceList($source, 'stat4Samples'),
            $orderBy,
            self::sourceStringList($source, 'coveringColumns'),
            $neededColumns,
            self::sourceBool($source, 'upperInclusive', true),
            self::sourceString($source, 'collation', 'BINARY'),
        );
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function materializedExpressionSource(array $source, string $expression, string $rangeColumn): array
    {
        $rows = [];
        foreach (self::sourceRows($source) as $row) {
            $row[$rangeColumn] = self::evaluateExpression($expression, $row);
            $rows[] = $row;
        }

        $covering = self::sourceStringList($source, 'coveringColumns');
        foreach ([$rangeColumn, $expression] as $column) {
            if (!in_array($column, $covering, true)) {
                $covering[] = $column;
            }
        }

        return array_replace($source, [
            'rangeColumn' => $rangeColumn,
            'rows' => $rows,
            'coveringColumns' => $covering,
        ]);
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $source
     * @param list<string> $expressions
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function expressionCoveringRowsForPlan(array $plan, array $source, array $expressions): array
    {
        $rowsByRowid = [];
        foreach (self::sourceRows($source) as $row) {
            $rowsByRowid[(int) ($row['rowid'] ?? 0)] = $row;
        }

        $pairs = [];
        foreach (($plan['currentNextCoveringRows'] ?? []) as $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                continue;
            }
            $current = self::expressionCoveringRowEvidence($pair['current'], $rowsByRowid, $expressions);
            $next = is_array($pair['next'] ?? null)
                ? self::expressionCoveringRowEvidence($pair['next'], $rowsByRowid, $expressions)
                : null;
            $pairs[] = ['current' => $current, 'next' => $next];
        }

        return $pairs;
    }

    /**
     * @param array<string,mixed> $coveringRow
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param list<string> $expressions
     * @return array<string,mixed>
     */
    private static function expressionCoveringRowEvidence(array $coveringRow, array $rowsByRowid, array $expressions): array
    {
        $rowid = (int) ($coveringRow['rowid'] ?? 0);
        $row = $rowsByRowid[$rowid] ?? null;
        if ($row === null) {
            throw new \InvalidArgumentException('SQLite expression covering skip-scan rowid is not present in current source');
        }

        $payload = [];
        foreach ($expressions as $expression) {
            $payload[$expression] = self::evaluateExpression($expression, $row);
        }

        return [
            'rowid' => $rowid,
            'sourceOffset' => $coveringRow['sourceOffset'] ?? null,
            'covering' => $coveringRow['covering'] ?? [],
            'coveringExpressions' => $payload,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,string> $expressionColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgramWithExpressionColumns(array $plan, array $expressionColumns): array
    {
        $program = $plan['cursorProgram'] ?? [];
        if (!is_array($program)) {
            return [];
        }

        foreach ($program as $offset => $opcode) {
            if (!is_array($opcode) || ($opcode['opcode'] ?? null) !== 'Column') {
                continue;
            }
            $columns = $opcode['columns'] ?? [];
            if (!is_array($columns)) {
                $columns = [];
            }
            $program[$offset]['expressionColumns'] = $expressionColumns;
            $program[$offset]['columns'] = array_values(array_unique(array_merge($columns, array_values($expressionColumns))));
        }

        return $program;
    }

    private static function expressionCoveringColumnName(array $source, string $expression): string
    {
        if (self::normalizeExpression($expression) === self::normalizeExpression(self::sourceString($source, 'rangeExpression'))) {
            return self::sourceString($source, 'rangeExpressionColumn');
        }

        return '__expr_' . substr(sha1(self::normalizeExpression($expression)), 0, 12);
    }

    /**
     * @return list<string>
     */
    private static function sourceExpressionList(array $source, string $key): array
    {
        $values = $source[$key] ?? [];
        if (!is_array($values)) {
            throw new \InvalidArgumentException('SQLite expression covering source list must be an array');
        }
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException('SQLite expression covering source list needs non-empty expression strings');
            }
        }

        return array_values($values);
    }

    /**
     * @param list<string> $expressions
     */
    private static function expressionListContains(array $expressions, string $needle): bool
    {
        $normalizedNeedle = self::normalizeExpression($needle);
        foreach ($expressions as $expression) {
            if (self::normalizeExpression($expression) === $normalizedNeedle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $expressions
     */
    private static function expressionListSignature(array $expressions): string
    {
        $normalized = array_map(static fn (string $expression): string => self::normalizeExpression($expression), $expressions);
        sort($normalized);

        return sha1(implode('|', $normalized));
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array<string,mixed>>
     */
    private static function materializedExpressionTerms(array $terms, string $expression, string $rangeColumn): array
    {
        $normalized = self::normalizeExpression($expression);
        $mapped = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan query terms must be arrays');
            }
            $left = $term['left'] ?? null;
            if (is_array($left) && isset($left['expression']) && is_string($left['expression']) && self::normalizeExpression($left['expression']) === $normalized) {
                $term['left'] = ['column' => $rangeColumn];
            }
            $mapped[] = $term;
        }

        return $mapped;
    }

    /**
     * @param list<array{expression:string,column?:string,direction?:string}> $orderBy
     * @return list<array{expression:string,column?:string,direction?:string}>
     */
    private static function materializedExpressionOrder(array $orderBy, string $expression, string $rangeColumn): array
    {
        $normalized = self::normalizeExpression($expression);
        $mapped = [];
        foreach ($orderBy as $term) {
            $termExpression = $term['expression'] ?? null;
            if (!is_string($termExpression) || trim($termExpression) === '') {
                throw new \InvalidArgumentException('SQLite partial expression skip-scan ORDER BY expressions need SQL text');
            }
            if (self::normalizeExpression($termExpression) === $normalized) {
                $term['expression'] = $rangeColumn;
                $term['column'] = $rangeColumn;
            }
            $mapped[] = $term;
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluateExpression(string $expression, array $row): mixed
    {
        $normalized = self::normalizeExpression($expression);
        if (preg_match('/^lower\((.+)\)$/', $normalized, $matches) === 1) {
            $value = self::rowExpressionValue($matches[1], $row);
            return $value === null ? null : strtolower((string) $value);
        }
        if (preg_match('/^upper\((.+)\)$/', $normalized, $matches) === 1) {
            $value = self::rowExpressionValue($matches[1], $row);
            return $value === null ? null : strtoupper((string) $value);
        }
        if (preg_match('/^trim\((.+)\)$/', $normalized, $matches) === 1) {
            $value = self::rowExpressionValue($matches[1], $row);
            return $value === null ? null : trim((string) $value);
        }
        if (preg_match('/^length\((.+)\)$/', $normalized, $matches) === 1) {
            $value = self::rowExpressionValue($matches[1], $row);
            return $value === null ? null : strlen((string) $value);
        }
        if (preg_match('/^cast\((.+) as integer\)$/', $normalized, $matches) === 1) {
            $value = self::rowExpressionValue($matches[1], $row);
            return $value === null ? null : (int) $value;
        }
        $simple = self::simpleColumnExpression($expression);
        if ($simple !== null) {
            return $row[$simple] ?? null;
        }

        throw new \InvalidArgumentException('SQLite partial expression skip-scan supports bounded lower/upper/trim/length/cast integer expressions');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowExpressionValue(string $expression, array $row): mixed
    {
        $column = self::simpleColumnExpression($expression);
        if ($column === null) {
            throw new \InvalidArgumentException('SQLite partial expression skip-scan expression argument must be a column');
        }

        return $row[$column] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array<string,mixed>> $orderBy
     */
    private static function expressionSourceSignature(string $expression, string $rangeColumn, array $orderBy, array $queryTerms): string
    {
        return hash('sha256', serialize([
            self::normalizeExpression($expression),
            $rangeColumn,
            $orderBy,
            $queryTerms,
        ]));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function expressionSummary(string $expression, string $rangeColumn, array $source, array $plan): array
    {
        $selectedPlan = is_array($plan['selectedPlan'] ?? null) ? $plan['selectedPlan'] : [];

        return [
            'name' => self::sourceString($source, 'name'),
            'rangeExpression' => $expression,
            'rangeExpressionColumn' => $rangeColumn,
            'schemaCookie' => self::nonNegativeSourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeSourceInt($source, 'stat4Generation'),
            'rowids' => $selectedPlan['rowids'] ?? [],
            'estimatedRows' => (int) ($selectedPlan['estimatedRows'] ?? 0),
            'estimatedCost' => (int) ($selectedPlan['estimatedCost'] ?? 0),
            'coveredRowCount' => (int) ($selectedPlan['coveredRowCount'] ?? 0),
            'expressionKeys' => self::expressionKeys($source, $rangeColumn),
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return list<mixed>
     */
    private static function expressionKeys(array $source, string $rangeColumn): array
    {
        $keys = [];
        foreach (self::sourceRows($source) as $row) {
            $keys[] = $row[$rangeColumn] ?? null;
        }

        return $keys;
    }

    private static function normalizeExpression(string $expression): string
    {
        $trimmed = trim($expression);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite partial expression skip-scan expression must be non-empty');
        }
        $trimmed = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return strtolower($trimmed);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @param list<array{expression:string,column?:string,direction?:string}> $orderByExpressions
     * @return array{orderBy:list<array{column:string,direction?:string}>,constantExpressions:list<string>,uncoveredExpressions:list<string>,coveredExpressions:list<string>,neededExpressionColumns:list<string>,directions:list<string>}
     */
    private static function reducedOrderByExpressions(array $source, array $queryTerms, array $orderByExpressions): array
    {
        $covering = array_fill_keys(array_map('strtolower', self::sourceStringList($source, 'coveringColumns')), true);
        $constants = self::constantColumnsFromTerms($queryTerms);
        $orderBy = [];
        $constantExpressions = [];
        $uncoveredExpressions = [];
        $coveredExpressions = [];
        $neededExpressionColumns = [];
        $directions = [];

        foreach ($orderByExpressions as $term) {
            $expression = $term['expression'] ?? null;
            if (!is_string($expression) || trim($expression) === '') {
                throw new \InvalidArgumentException('SQLite partial covering skip-scan ORDER BY expressions need SQL text');
            }
            $expression = trim($expression);
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException('SQLite partial covering skip-scan ORDER BY direction must be ASC or DESC');
            }
            $directions[] = $direction;
            $column = isset($term['column']) && is_string($term['column']) ? $term['column'] : self::simpleColumnExpression($expression);
            if ($column !== null && isset($constants[strtolower($column)])) {
                $constantExpressions[] = $expression;
                continue;
            }
            if ($column !== null && isset($covering[strtolower($column)])) {
                $orderBy[] = ['column' => $column, 'direction' => $direction];
                $coveredExpressions[] = $expression;
                $neededExpressionColumns[] = $column;
                continue;
            }
            if (isset($covering[strtolower($expression)])) {
                $coveredExpressions[] = $expression;
                continue;
            }
            $uncoveredExpressions[] = $expression;
        }

        return [
            'orderBy' => $orderBy,
            'constantExpressions' => $constantExpressions,
            'uncoveredExpressions' => $uncoveredExpressions,
            'coveredExpressions' => $coveredExpressions,
            'neededExpressionColumns' => array_values(array_unique($neededExpressionColumns)),
            'directions' => $directions,
        ];
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @return array<string,mixed>
     */
    private static function constantColumnsFromTerms(array $queryTerms): array
    {
        $constants = [];
        foreach ($queryTerms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator !== '=') {
                continue;
            }
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column']) || !is_string($left['column']) || $left['column'] === '') {
                continue;
            }
            $constants[strtolower($left['column'])] = $term['right'] ?? null;
        }

        return $constants;
    }

    private static function simpleColumnExpression(string $expression): ?string
    {
        $trimmed = trim($expression);
        if (preg_match('/^(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))$/', $trimmed, $matches) !== 1) {
            return null;
        }

        $doubleQuoted = $matches[1] ?? '';
        $backtickQuoted = $matches[2] ?? '';
        $bracketQuoted = $matches[3] ?? '';
        $bare = $matches[4] ?? '';

        return $doubleQuoted !== '' ? $doubleQuoted : ($backtickQuoted !== '' ? $backtickQuoted : ($bracketQuoted !== '' ? $bracketQuoted : $bare));
    }

    /**
     * @param array<string,mixed> $order
     */
    private static function orderExpressionSignature(array $order): string
    {
        return hash('sha256', serialize([
            $order['orderBy'] ?? [],
            $order['constantExpressions'] ?? [],
            $order['uncoveredExpressions'] ?? [],
            $order['coveredExpressions'] ?? [],
            $order['neededExpressionColumns'] ?? [],
            $order['directions'] ?? [],
        ]));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed>|null $plan
     * @return array<string,mixed>
     */
    private static function sourceFenceSummary(array $source, ?array $plan, string $signature): array
    {
        return [
            'name' => self::sourceString($source, 'name'),
            'schemaCookie' => self::nonNegativeSourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeSourceInt($source, 'stat4Generation'),
            'rootPage' => self::optionalSourceInt($source, 'rootPage'),
            'sourceSignature' => $signature,
            'indexName' => self::sourceString($source, 'indexName'),
            'status' => $plan === null ? 'unusable' : ($plan['status'] ?? 'usable'),
            'covering' => (bool) ($plan['covering'] ?? false),
            'tableSeekRequired' => (bool) ($plan['tableSeekRequired'] ?? true),
            'coveredRowCount' => (int) ($plan['coveredRowCount'] ?? 0),
            'rowids' => $plan['rowids'] ?? [],
            'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
            'estimatedCost' => (int) ($plan['estimatedCost'] ?? 0),
            'orderByMode' => $plan['orderByMode'] ?? 'none',
            'blockSortRequired' => (bool) ($plan['blockSortRequired'] ?? false),
            'coveringSignature' => self::columnSignature(self::sourceStringList($source, 'coveringColumns')),
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function sourceRows(array $source): array
    {
        return self::sourceList($source, 'rows');
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function sourceList(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs list {$key}");
        }
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs row arrays in {$key}");
            }
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceString(array $source, string $key, ?string $default = null): string
    {
        $value = $source[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceBool(array $source, string $key, bool $default): bool
    {
        $value = $source[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs boolean {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function nonNegativeSourceInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs non-negative integer {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function optionalSourceInt(array $source, string $key): ?int
    {
        $value = $source[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs non-negative integer {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     * @return list<string>
     */
    private static function sourceStringList(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 skip-scan current source needs list {$key}");
        }

        return self::validatedColumnList($value, "SQLite STAT4 skip-scan current source {$key}");
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return hash('sha256', implode("\n", [
            self::sourceString($source, 'name'),
            (string) self::nonNegativeSourceInt($source, 'schemaCookie'),
            (string) self::nonNegativeSourceInt($source, 'stat4Generation'),
            self::sourceString($source, 'indexName'),
            (string) (self::optionalSourceInt($source, 'rootPage') ?? -1),
            self::sourceString($source, 'skippedColumn'),
            self::sourceString($source, 'rangeColumn'),
            serialize($source['lowerInclusive'] ?? null),
            serialize($source['upperBound'] ?? null),
            self::sourceBool($source, 'upperInclusive', true) ? '1' : '0',
            self::sourceString($source, 'collation', 'BINARY'),
            self::columnSignature(self::sourceStringList($source, 'coveringColumns')),
            self::rowSignature(self::sourceRows($source)),
            self::stat4Signature(self::sourceList($source, 'stat4Samples')),
        ]));
    }

    /**
     * @return list<string>
     */
    private static function nextSourceReplanReasons(array $selectedSource, array $nextSource, string $selectedSignature, string $nextSignature): array
    {
        $reasons = [];
        if (self::nonNegativeSourceInt($selectedSource, 'schemaCookie') !== self::nonNegativeSourceInt($nextSource, 'schemaCookie')) {
            $reasons[] = 'schema-cookie';
        }
        if (self::nonNegativeSourceInt($selectedSource, 'stat4Generation') !== self::nonNegativeSourceInt($nextSource, 'stat4Generation')) {
            $reasons[] = 'stat4-generation';
        }
        if (self::sourceString($selectedSource, 'rangeExpression') !== self::sourceString($nextSource, 'rangeExpression')) {
            $reasons[] = 'range-expression';
        }
        if (self::sourceString($selectedSource, 'rangeExpressionColumn') !== self::sourceString($nextSource, 'rangeExpressionColumn')) {
            $reasons[] = 'range-expression-column';
        }
        if (self::rowSignature(self::sourceRows($selectedSource)) !== self::rowSignature(self::sourceRows($nextSource))) {
            $reasons[] = 'row-signature';
        }
        if (self::stat4Signature(self::sourceList($selectedSource, 'stat4Samples')) !== self::stat4Signature(self::sourceList($nextSource, 'stat4Samples'))) {
            $reasons[] = 'stat4-signature';
        }
        if ($selectedSignature !== $nextSignature && $reasons === []) {
            $reasons[] = 'source-signature';
        }

        return $reasons;
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicateFence(SQLiteIndexPredicate $predicate): array
    {
        if (is_array($predicate->value)) {
            $children = [];
            foreach ($predicate->value as $child) {
                if ($child instanceof SQLiteIndexPredicate) {
                    $children[] = self::predicateFence($child);
                }
            }

            return [
                'column' => $predicate->columnName,
                'operator' => $predicate->operator,
                'children' => $children,
                'signature' => self::predicateSignature($predicate),
            ];
        }

        return [
            'column' => $predicate->columnName,
            'operator' => $predicate->operator,
            'value' => $predicate->value,
            'signature' => self::predicateSignature($predicate),
        ];
    }

    private static function predicateSignature(SQLiteIndexPredicate $predicate): string
    {
        return hash('sha256', serialize(self::predicateSignaturePayload($predicate)));
    }

    /**
     * @return array<string,mixed>
     */
    private static function predicateSignaturePayload(SQLiteIndexPredicate $predicate): array
    {
        if (is_array($predicate->value)) {
            $children = [];
            foreach ($predicate->value as $child) {
                if ($child instanceof SQLiteIndexPredicate) {
                    $children[] = self::predicateSignaturePayload($child);
                }
            }

            return [
                'column' => strtolower($predicate->columnName),
                'operator' => $predicate->operator,
                'children' => $children,
            ];
        }

        return [
            'column' => strtolower($predicate->columnName),
            'operator' => $predicate->operator,
            'value' => $predicate->value,
        ];
    }

    /**
     * @param list<string> $columns
     */
    private static function columnSignature(array $columns): string
    {
        $normalized = array_map('strtolower', $columns);
        sort($normalized, SORT_STRING);

        return implode(',', $normalized);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowSignature(array $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            ksort($row);
            $parts[] = serialize($row);
        }

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param list<array<string,mixed>> $samples
     */
    private static function stat4Signature(array $samples): string
    {
        $parts = [];
        foreach ($samples as $sample) {
            ksort($sample);
            $parts[] = serialize($sample);
        }

        return hash('sha256', implode("\n", $parts));
    }
}
