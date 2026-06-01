<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$methodSource = static function (string $method): string {
    $reflection = new ReflectionMethod(SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::class, $method);
    $file = $reflection->getFileName();
    if (!is_string($file)) {
        throw new RuntimeException('Unable to locate STAT4 planner source file');
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException('Unable to read STAT4 planner source file');
    }

    return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
};

$domainMatches = static function (array $methods) use ($methodSource): array {
    $matches = [];
    $pattern = '/wp_|wp_options|blog_id|blogId|option_name|optionName|option_value|optionValue|autoload|Autoload/';

    foreach ($methods as $method) {
        $source = $methodSource($method);
        if (preg_match_all($pattern, $source, $methodMatches) > 0) {
            foreach ($methodMatches[0] as $match) {
                $matches[] = $method . ': ' . $match;
            }
        }
    }

    return $matches;
};

$callPrivate = static function (string $method, mixed ...$args): mixed {
    $reflection = new ReflectionMethod(SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(null, ...$args);
};

$genericIndex = [
    'name' => 'idx_app_settings_lower_name',
    'expression' => 'lower(key_name)',
    'stat4KeyFields' => ['keyColumn' => 'key_name', 'tenantColumn' => 'tenant_id'],
    'partialPredicateTerms' => [
        ['left' => ['expression' => 'lower(key_name)'], 'operator' => '>=', 'right' => 'module_forms'],
        ['left' => ['expression' => 'lower(key_name)'], 'operator' => '<=', 'right' => 'module_zulu'],
        ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
    ],
];

$lateCurrentSourceFenceMethods = [
    'materializeCurrentSourceCoveringPayloadValidation',
    'coveringPayloadFenceCurrentSourceCoveringPayloadValidation',
    'mismatchedColumnsCurrentSourceCoveringPayloadValidation',
    'payloadKeyFromRowCurrentSourceCoveringPayloadValidation',
    'leftValueCurrentSourceCoveringPayloadValidation',
    'materializeCurrentSourcePartialEstimateFence',
    'partialEstimateFenceCurrentSourcePartialEstimateFence',
    'rowSatisfiesTermsCurrentSourcePartialEstimateFence',
    'likePrefixCurrentSourcePartialEstimateFence',
    'rowExpressionKeyCurrentSourcePartialEstimateFence',
    'materializeCurrentSourceResidualWhereValidation',
    'residualWhereFenceCurrentSourceResidualWhereValidation',
    'leftValueCurrentSourceResidualWhereValidation',
    'materializeCurrentSourceSampleTapeValidation',
    'sampleTapeFenceCurrentSourceSampleTapeValidation',
    'matchedRowsByExpressionCurrentSourceSampleTapeValidation',
    'rowExpressionKeyCurrentSourceSampleTapeValidation',
    'materializeCurrentSourcePayloadWindowFence',
    'stat4WindowProvenance',
    'materializeCurrentSourceDuplicateCardinalityValidation',
    'duplicateCardinalityFenceCurrentSourceDuplicateCardinalityValidation',
    'expressionKeyCurrentSourceDuplicateCardinalityValidation',
    'materializeCurrentSourceLimitOffsetWindowValidation',
    'windowFenceCurrentSourceLimitOffsetWindowValidation',
    'leftValueCurrentSourceLimitOffsetWindowValidation',
    'expressionKeyCurrentSourceLimitOffsetWindowValidation',
    'materializeStat4BoundaryPeerFence',
    'boundaryPeerFenceStat4BoundaryPeer',
    'leftValueStat4BoundaryPeer',
    'expressionKeyStat4BoundaryPeer',
    'materializeCurrentSourceDuplicateRunValidation',
    'duplicateRunFenceCurrentSourceDuplicateRunValidation',
    'rowSatisfiesTermsCurrentSourceDuplicateRunValidation',
    'likePrefixCurrentSourceDuplicateRunValidation',
    'rowExpressionKeyCurrentSourceDuplicateRunValidation',
	    'materializeCurrentPartialPredicateFence',
	    'currentPartialPredicateFence',
	    'currentPartialPredicateLeftValue',
	    'currentPartialPredicateExpressionKey',
	    'residualValueCurrentSourceNotLikeResidualFence',
	    'evaluateExpressionCurrentSourceDuplicateRunFence',
	    'residualValueCurrentSourceTrailingPayloadFence',
	    'expressionForPayloadExpressionFence',
	    'payloadExpressionFence',
	    'residualValueDistinctResidual',
	    'valueForLeftKeyCurrentSourcePartialPredicateFence',
	    'expressionCurrentSourceResidualWhereFence',
	    'peerOrderFenceCurrentSourceResidualWhereFence',
	    'residualValueCurrentSourceHistogramFence',
	    'boundaryFenceCurrentSourceScanDirectionFence',
	    'peerFenceCurrentSourceStat4PayloadFence',
	    'partialPredicateTermsCurrentSourceStat4PayloadFence',
	    'valueForLeftKeyCurrentSourcePartialOrPayloadFence',
	    'sampleFenceCurrentSourcePartialOrSelectivityFence',
	    'valueForLeftKeyCurrentSourcePartialOrSelectivityFence',
	    'valueForLeftKeyCurrentSourceRepeatedSeekWindowFence',
	    'expressionKeyPeerRowidWindow',
	    'expressionKeyCurrentSourceRepeatedSeekResumeFence',
	    'valueForLeftKeyCurrentSourceGroupedLikeFence',
	    'valueForLeftKeyLikeCaseFence',
	    'expressionPayloadCoveringFence',
	    'expressionKeyForCurrentNextYieldFence',
	    'expressionKeyForStat4PeerRunYieldFence',
	    'expressionKeyForStat4SampleWindow',
	    'expressionKeyForSampleOrder',
	    'stat4SamplePartialPredicateFence',
	    'leftValueForStat4SamplePartialPredicate',
	    'gapDensityRowExpressionKey',
	    'gapDensityBounds',
	    'gapDensityExpressionKey',
	    'leftValueForPageMembershipFence',
	    'expressionKeyForPageMembershipFence',
	    'currentPartialLeftValue',
	    'currentPartialExpressionKey',
	    'rowExpressionKeyForSampleRowGuardFence',
	    'leftValueForStat4HistogramFence',
	    'expressionKeyForStat4HistogramFence',
	    'rowExpressionKeyCurrentSourceStat4DensityVectorValidation',
	    'stat4ValueForLeftKey',
	    'stat4ExpressionFromIndex',
	    'stat4ExpressionKeyForExpression',
	    'stat4IndexByName',
	];

$preparedHandoffWindowMethods = [
    'materializeCurrentStat4PayloadFence',
    'currentStat4PayloadFence',
    'currentStat4PayloadExpressionKey',
    'handoffFenceForStat4PayloadHandoffSeed',
    'handoffFenceForStat4PayloadHandoffContinuation',
    'handoffFenceForStat4PayloadHandoffMiddle',
    'handoffFenceForStat4PayloadHandoffValidation',
    'handoffFenceForStat4ExpressionPartialCurrentHandoffTail',
    'handoffFencePreparedHandoffBridgeSeed',
    'handoffFencePreparedHandoffBridgeMiddle',
    'handoffFencePreparedHandoffBridgeLate',
    'handoffFencePreparedHandoffBridgeValidation',
    'handoffFencePreparedHandoffBridgeFollowup',
    'handoffFencePreparedHandoffBridgePenultimate',
    'handoffFencePreparedHandoffBridgeFinal',
    'handoffFenceForStat4ExpressionPartialPreparedBridgeCurrentSourceHandoff',
    'handoffFenceForStat4ExpressionPartialPreparedBridgeFirstContinuation',
    'handoffFenceForStat4ExpressionPartialPreparedBridgeSecondContinuation',
    'handoffFenceForStat4ExpressionPartialPreparedBridgeThirdContinuation',
    'handoffFenceForStat4ExpressionPartialPreparedBridgeFourthContinuation',
    'handoffFenceForStat4ExpressionPartialPreparedBridgeFifthContinuation',
    'handoffFenceForStat4ExpressionPartialPreparedBridge',
    'handoffFenceForStat4ExpressionPartialPreparedHandoff',
    'handoffFencePreparedHandoffPenultimateSeed',
    'handoffFencePreparedHandoffFinalSeed',
    'stat4ExpressionPartialPreparedContinuationFence',
    'stat4ExpressionPartialHandoffFenceForRange',
    'keyColumnForStat4ExpressionPartialHandoff',
    'indexForStat4ExpressionPartialKeyFields',
    'expressionKeyForStat4ExpressionPartialHandoff',
];

$genericCurrentSource = [
    'indexes' => [$genericIndex + ['selected' => true]],
    'rows' => [
        ['rowid' => 1, 'key_name' => 'Module_Alpha', 'tenant_id' => 1, 'key_value' => 'alpha'],
        ['rowid' => 2, 'key_name' => 'Module_Beta', 'tenant_id' => 1, 'key_value' => 'beta'],
    ],
];

return [
    'planner stat4 prepared handoff key fields are source neutral' => static fn (TestRunner $t) => $t->same([], $domainMatches([
        'handoffFenceForStat4ExpressionPartialPreparedBridge',
        'materializeRangeRows',
        'selectedRangeRowsExpressionIndex',
        'expressionRangeRowsRange',
        'rangeRowsMatchingRows',
        'expressionValueStat4CurrentRange',
        'handoffFenceForPreparedHandoff',
        'preparedHandoffFenceForRange',
        'keyColumnForPreparedHandoff',
        'indexForPreparedHandoffKeyFields',
        'expressionKeyForPreparedHandoff',
    ])),
    'planner stat4 expression key field helpers are source neutral' => static fn (TestRunner $t) => $t->same([], $domainMatches([
        'materializeCurrentSourceCoveringReprepare',
        'expressionValueCurrentOrSplitPartialExpression',
        'expressionValueForStat4ExpressionPartialCurrentSource',
        'stat4LikePrefixPartialExpressionValue',
        'competingExpressionIndexesForStat4PartialCostFence',
        'canonicalRelevantChurnRow',
        'canonicalRelevantChurnColumns',
        'canonicalRelevantChurnExpressions',
        'rowMatchesRelevantChurnTerms',
        'expressionValueForUnsampledBracket',
        'materializeDuplicateSampleFanout',
        'selectedExpressionForDuplicateFanout',
        'expressionValueLikePrefixWindow',
        'normalizeExpressionForStat4BetweenRangeFence',
        'materializeStat4OrderFence',
        'expressionValueForStat4OrderFence',
        'stat4SourceNeutralExpressionValue',
        'stat4SourceNeutralExpressionKey',
        'firstExpressionFromIndexes',
    ])),
    'planner stat4 late current source fences are source neutral' => static fn (TestRunner $t) => $t->same([], $domainMatches($lateCurrentSourceFenceMethods)),
    'planner stat4 prepared handoff windows are source neutral' => static fn (TestRunner $t) => $t->same([], $domainMatches($preparedHandoffWindowMethods)),
    'planner stat4 prepared handoff key column uses generic metadata' => static fn (TestRunner $t) => $t->same('key_name', $callPrivate('keyColumnForPreparedHandoff', ['indexes' => [$genericIndex]])),
    'planner stat4 prepared handoff expression key uses generic row field' => static fn (TestRunner $t) => $t->same('module_zulu', $callPrivate('expressionKeyForPreparedHandoff', ['key_name' => 'Module_Zulu', 'tenant_id' => 1], 'key_name')),
    'planner stat4 current range expression uses generic lower key field' => static fn (TestRunner $t) => $t->same('module_zulu', $callPrivate('expressionValueStat4CurrentRange', ['key_name' => 'Module_Zulu'], 'lower(key_name)')),
    'planner stat4 current range json expression uses generic value field' => static fn (TestRunner $t) => $t->same('search', $callPrivate('expressionValueStat4CurrentRange', ['key_value' => '{"module":"search"}'], 'json_extract(key_value,$.module)')),
    'planner stat4 unsampled bracket uses generic substring key field' => static fn (TestRunner $t) => $t->same('module_cache', $callPrivate('expressionValueForUnsampledBracket', ['key_name' => 'module_cache_v2'], 'substr(key_name,1,12)')),
    'planner stat4 like window uses generic value length field' => static fn (TestRunner $t) => $t->same(17, $callPrivate('expressionValueLikePrefixWindow', ['key_value' => 'scheduled_refresh'], 'length(key_value)')),
    'planner stat4 order fence uses generic lower key field' => static fn (TestRunner $t) => $t->same('module_alpha', $callPrivate('expressionValueForStat4OrderFence', ['key_name' => 'Module_Alpha'], 'lower(key_name)')),
    'planner stat4 covering payload key uses generic tenant field' => static fn (TestRunner $t) => $t->same(['module_forms', 2, 15], $callPrivate('payloadKeyFromRowCurrentSourceCoveringPayloadValidation', ['rowid' => 15, 'key_name' => 'Module_Forms', 'tenant_id' => 2], 'key_name', 'tenant_id')),
    'planner stat4 partial estimate expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_search', $callPrivate('rowExpressionKeyCurrentSourcePartialEstimateFence', ['key_name' => 'Module_Search'], 'key_name')),
    'planner stat4 residual where uses generic lower key field' => static fn (TestRunner $t) => $t->same('module_search', $callPrivate('leftValueCurrentSourceResidualWhereValidation', ['expression' => 'lower(key_name)'], ['key_name' => 'Module_Search'])),
    'planner stat4 sample tape expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_sync', $callPrivate('rowExpressionKeyCurrentSourceSampleTapeValidation', ['key_name' => 'Module_Sync'], 'key_name')),
    'planner stat4 sample provenance uses generic key column' => static fn (TestRunner $t) => $t->same(
        [['rowid' => 7, 'source' => 'current', 'keyColumn' => 'key_name', 'keyValue' => 'Module_Sync', 'sampleKey' => 'module_sync', 'stat4Anchor' => true]],
        $callPrivate('stat4WindowProvenance', [7], [7 => ['rowid' => 7, 'key_name' => 'Module_Sync']], [['key' => 'module_sync', 'rowid' => 7, 'neq' => 1, 'nlt' => 0, 'ndlt' => 0]], 'key_name')
    ),
    'planner stat4 duplicate cardinality expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_cache', $callPrivate('expressionKeyCurrentSourceDuplicateCardinalityValidation', ['key_name' => 'Module_Cache'], 'key_name')),
    'planner stat4 boundary peer expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_auth', $callPrivate('expressionKeyStat4BoundaryPeer', ['key_name' => 'Module_Auth'], 'key_name')),
    'planner stat4 duplicate run expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_forms', $callPrivate('rowExpressionKeyCurrentSourceDuplicateRunValidation', ['key_name' => 'Module_Forms'], 'key_name')),
	    'planner stat4 current partial predicate uses generic key expression' => static fn (TestRunner $t) => $t->same('module_alpha', $callPrivate('currentPartialPredicateLeftValue', ['expression' => 'lower(key_name)'], ['key_name' => 'Module_Alpha'])),
	    'planner stat4 current partial predicate expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_beta', $callPrivate('currentPartialPredicateExpressionKey', ['key_name' => 'Module_Beta'], 'key_name')),
	    'planner stat4 dynamic left-key helper uses generic expression payload' => static fn (TestRunner $t) => $t->same('module_forms', $callPrivate('stat4ValueForLeftKey', ['key_name' => 'Module_Forms'], ['rowid' => 7], 'expression:lower(key_name)', 'source-neutral-test')),
	    'planner stat4 payload expression fence accepts generic index expression' => static fn (TestRunner $t) => $t->same('lower(key_name)', $callPrivate('expressionForPayloadExpressionFence', $genericIndex)),
	    'planner stat4 source-neutral expression key supports generic length expression' => static fn (TestRunner $t) => $t->same('17', $callPrivate('stat4ExpressionKeyForExpression', ['key_value' => 'scheduled_refresh'], 'length(key_value)', 'source-neutral-test')),
	    'planner stat4 payload fence compares generic key column' => static function (TestRunner $t) use ($callPrivate): void {
        $fence = $callPrivate(
            'currentStat4PayloadFence',
            [1 => ['rowid' => 1, 'key_name' => 'Module_Alpha', 'key_value' => 'alpha']],
            [1 => ['rowid' => 1, 'expressionKey' => 'module_alpha', 'coveredValues' => ['key_value' => 'alpha']]],
            [1],
            ['key_value'],
            'key_name'
        );

        $t->same('module_alpha', $fence['rowProofs'][0]['currentExpressionKey']);
        $t->same(true, $fence['allYieldedRowsHaveCurrentPayloads']);
    },
    'planner stat4 payload handoff seed uses generic key metadata' => static function (TestRunner $t) use ($callPrivate, $genericCurrentSource): void {
        $fence = $callPrivate(
            'handoffFenceForStat4PayloadHandoffSeed',
            [
                'selectedPlan' => ['name' => 'idx_app_settings_lower_name'],
                'matchedRowids' => [1],
                'stat4CurrentPayloadFence' => ['payloadMatchedRowids' => [1]],
            ],
            $genericCurrentSource,
            ['key_value']
        );

        $t->same('module_alpha', $fence['handoffWindows'][0]['expressionKey']);
        $t->same(true, $fence['allSlicesPrepared']);
    },
    'planner stat4 generic handoff range uses generic key metadata' => static function (TestRunner $t) use ($callPrivate, $genericCurrentSource): void {
        $fence = $callPrivate(
            'stat4ExpressionPartialHandoffFenceForRange',
            [
                'selectedPlan' => ['name' => 'idx_app_settings_lower_name'],
                'stat4PriorFence' => [
                    'handoffWindows' => [
                        ['slice' => 1, 'rowid' => 2, 'projectedColumns' => ['key_value' => 'beta'], 'prepared' => true],
                    ],
                    'preparedSlices' => [1],
                    'allSlicesPrepared' => true,
                    'sliceRange' => [1, 1],
                    'handoffSignature' => 'prior',
                ],
            ],
            $genericCurrentSource,
            ['key_value'],
            'stat4PriorFence',
            638,
            653,
            'prior'
        );

        $t->same('module_beta', $fence['handoffWindows'][0]['expressionKey']);
        $t->same([], $fence['blockedSlices']);
    },
];
