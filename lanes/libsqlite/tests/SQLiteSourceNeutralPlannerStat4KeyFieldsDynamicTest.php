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
    'planner stat4 duplicate cardinality expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_cache', $callPrivate('expressionKeyCurrentSourceDuplicateCardinalityValidation', ['key_name' => 'Module_Cache'], 'key_name')),
    'planner stat4 boundary peer expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_auth', $callPrivate('expressionKeyStat4BoundaryPeer', ['key_name' => 'Module_Auth'], 'key_name')),
    'planner stat4 duplicate run expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_forms', $callPrivate('rowExpressionKeyCurrentSourceDuplicateRunValidation', ['key_name' => 'Module_Forms'], 'key_name')),
    'planner stat4 current partial predicate uses generic key expression' => static fn (TestRunner $t) => $t->same('module_alpha', $callPrivate('currentPartialPredicateLeftValue', ['expression' => 'lower(key_name)'], ['key_name' => 'Module_Alpha'])),
    'planner stat4 current partial predicate expression key uses generic key field' => static fn (TestRunner $t) => $t->same('module_beta', $callPrivate('currentPartialPredicateExpressionKey', ['key_name' => 'Module_Beta'], 'key_name')),
];
