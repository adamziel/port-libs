<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq251 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like251 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull251 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between251 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$payload251 = static fn (array $row): array => [
    'rowid' => $row['rowid'],
    'expressionKey' => strtolower((string) $row['option_name']),
    'coveredValues' => [
        'option_name' => $row['option_name'],
        'option_value' => $row['option_value'],
        'updated_at' => $row['updated_at'],
        'blog_id' => $row['blog_id'],
        'autoload' => $row['autoload'],
    ],
];

$prepared251 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-covering-payload-next251',
    'schemaCookie' => 2510,
    'stat4Generation' => 251,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_stat4_covering_payload_partial_next251',
        'rootPage' => 25101,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 1, 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 1, 40]],
            ['neq' => '4 3', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 1, 20]],
            ['neq' => '4 1', 'nlt' => '2 5', 'ndlt' => '2 3', 'sample' => ['plugin_forms', 2, 80]],
            ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 4', 'sample' => ['plugin_mail', 1, 50]],
            ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 5', 'sample' => ['plugin_seo', 1, 30]],
            ['neq' => '1 1', 'nlt' => '8 8', 'ndlt' => '5 6', 'sample' => ['plugin_zulu', 1, 60]],
        ],
        'stat4ExpressionPayloads' => [],
    ]],
];

$current251 = static function (?callable $payloadMutator = null) use ($prepared251, $payload251): array {
    $source = $prepared251();
    $source['name'] = 'current-wp-options-stat4-covering-payload-next251';
    $source['schemaCookie'] = 2519;
    $source['stat4Generation'] = 751;
    $source['indexes'][0]['rootPage'] = 25188;
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ];
    $payloads = array_map(
        $payload251,
        array_values(array_filter($source['rows'], static fn (array $row): bool => $row['autoload'] === 'yes')),
    );
    if ($payloadMutator !== null) {
        $payloads = $payloadMutator($payloads);
    }
    $source['indexes'][0]['stat4ExpressionPayloads'] = $payloads;

    return $source;
};

$terms251 = static fn (): array => [
    $between251('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq251('autoload', 'yes'),
    $notNull251('option_name'),
    $eq251('blog_id', 1),
    $like251('option_name', 'plugin_%'),
];

$plan251 = static fn (int $limit = 5, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentCoveringPayloadFence(
    $prepared ?? $prepared251(),
    $current ?? $current251(),
    $terms ?? $terms251(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$staleValue251 = static function () use ($current251, $plan251): array {
    $current = $current251(static function (array $payloads): array {
        foreach ($payloads as &$payload) {
            if ($payload['rowid'] === 21) {
                $payload['coveredValues']['option_value'] = 'forms-stale-cache';
            }
        }
        unset($payload);

        return $payloads;
    });

    return $plan251(5, 1, null, $current);
};

$missingPayload251 = static function () use ($current251, $plan251): array {
    $current = $current251(static fn (array $payloads): array => array_values(array_filter(
        $payloads,
        static fn (array $payload): bool => $payload['rowid'] !== 22,
    )));

    return $plan251(5, 1, null, $current);
};

$missingColumn251 = static function () use ($current251, $plan251): array {
    $current = $current251(static function (array $payloads): array {
        foreach ($payloads as &$payload) {
            if ($payload['rowid'] === 50) {
                unset($payload['coveredValues']['updated_at']);
            }
        }
        unset($payload);

        return $payloads;
    });

    return $plan251(5, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next251 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next251-ready', $plan251()['status']),
    'planner stat4 expression partial current source next251 inherits stat4BoundaryPeer' => static fn (TestRunner $t) => $t->same(true, $plan251()['selectedPlan']['stat4BoundaryPeerReady']),
    'planner stat4 expression partial current source next251 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan251()['selectedPlan']['next251Ready']),
    'planner stat4 expression partial current source next251 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_stat4_covering_payload_partial_next251', $plan251()['selectedPlan']['name']),
    'planner stat4 expression partial current source next251 root page' => static fn (TestRunner $t) => $t->same(25188, $plan251()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next251 checked rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan251()['stat4CoveringPayloadFence']['checkedRowids']),
    'planner stat4 expression partial current source next251 needed columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan251()['stat4CoveringPayloadFence']['neededColumns']),
    'planner stat4 expression partial current source next251 all payloads match' => static fn (TestRunner $t) => $t->same(true, $plan251()['stat4CoveringPayloadFence']['allCoveringPayloadsMatchCurrentSource']),
    'planner stat4 expression partial current source next251 no stale payloads' => static fn (TestRunner $t) => $t->same([], $plan251()['stat4CoveringPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next251 no missing payloads' => static fn (TestRunner $t) => $t->same([], $plan251()['stat4CoveringPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next251 no missing columns' => static fn (TestRunner $t) => $t->same([], $plan251()['stat4CoveringPayloadFence']['missingCoveredColumnProofs']),
    'planner stat4 expression partial current source next251 proof count' => static fn (TestRunner $t) => $t->same(5, count($plan251()['stat4CoveringPayloadFence']['payloadProofs'])),
    'planner stat4 expression partial current source next251 first proof rowid' => static fn (TestRunner $t) => $t->same(30, $plan251()['stat4CoveringPayloadFence']['payloadProofs'][0]['rowid']),
    'planner stat4 expression partial current source next251 first proof fresh' => static fn (TestRunner $t) => $t->same(true, $plan251()['stat4CoveringPayloadFence']['payloadProofs'][0]['payloadFresh']),
    'planner stat4 expression partial current source next251 mail value proof' => static fn (TestRunner $t) => $t->same('mail', $plan251()['stat4CoveringPayloadFence']['payloadProofs'][1]['columnProofs'][1]['payloadValue']),
    'planner stat4 expression partial current source next251 mail value current' => static fn (TestRunner $t) => $t->same('mail', $plan251()['stat4CoveringPayloadFence']['payloadProofs'][1]['columnProofs'][1]['currentValue']),
    'planner stat4 expression partial current source next251 mail value matches' => static fn (TestRunner $t) => $t->same(true, $plan251()['stat4CoveringPayloadFence']['payloadProofs'][1]['columnProofs'][1]['matches']),
    'planner stat4 expression partial current source next251 selected checked rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan251()['selectedPlan']['next251CheckedRowids']),
    'planner stat4 expression partial current source next251 selected needed columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan251()['selectedPlan']['next251NeededColumns']),
    'planner stat4 expression partial current source next251 selected no stale' => static fn (TestRunner $t) => $t->same([], $plan251()['selectedPlan']['next251StalePayloadRowids']),
    'planner stat4 expression partial current source next251 signatures' => static fn (TestRunner $t) => $t->same([64, 64], [strlen($plan251()['stat4CoveringPayloadFence']['coveringPayloadSignature']), strlen($plan251()['stat4CoveringPayloadFence']['proofSignature'])]),
    'planner stat4 expression partial current source next251 selected signature' => static fn (TestRunner $t) => $t->same($plan251()['stat4CoveringPayloadFence']['proofSignature'], $plan251()['selectedPlan']['next251ProofSignature']),
    'planner stat4 expression partial current source next251 stat4 signature' => static fn (TestRunner $t) => $t->same($plan251()['stat4CoveringPayloadFence']['proofSignature'], $plan251()['stat4Fence']['next251ProofSignature']),
    'planner stat4 expression partial current source next251 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan251()['stat4Fence']['next251CoveringPayloadReady']),
    'planner stat4 expression partial current source next251 cursor appended' => static fn (TestRunner $t) => $t->same('VerifyCurrentStat4CoveringPayloads', $plan251()['cursorProgram'][array_key_last($plan251()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next251 cursor mode' => static fn (TestRunner $t) => $t->same('next251-current-source-stat4-expression-partial-covering-payload', $plan251()['cursorProgram'][array_key_last($plan251()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next251 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21, 22], $plan251()['cursorProgram'][array_key_last($plan251()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next251 cursor columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan251()['cursorProgram'][array_key_last($plan251()['cursorProgram'])]['neededColumns']),
    'planner stat4 expression partial current source next251 projected payload retained' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan251()['projectedRows'][4]['option_value']),
    'planner stat4 expression partial current source next251 boundary peers retained' => static fn (TestRunner $t) => $t->same([30, 20, 21, 22], $plan251()['stat4BoundaryPeerFence']['currentBoundaryPeerRowids']),
    'planner stat4 expression partial current source next251 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next251', $plan251()['dependencies'], true)),
    'planner stat4 expression partial current source next251 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan251()['dependency_closure']),
    'planner stat4 expression partial current source next251 non overlap' => static fn (TestRunner $t) => $t->contains('covering payload freshness validation', $plan251()['non_overlap']),
    'planner stat4 expression partial current source next251 detail' => static fn (TestRunner $t) => $t->contains('NEXT251 COVERING PAYLOAD FENCE', $plan251()['detail']),
    'planner stat4 expression partial current source next251 stale value status' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $staleValue251()['status']),
    'planner stat4 expression partial current source next251 stale value rowid' => static fn (TestRunner $t) => $t->same([21], $staleValue251()['stat4CoveringPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next251 stale selected rowid' => static fn (TestRunner $t) => $t->same([21], $staleValue251()['selectedPlan']['next251StalePayloadRowids']),
    'planner stat4 expression partial current source next251 stale value proof false' => static fn (TestRunner $t) => $t->same(false, $staleValue251()['stat4CoveringPayloadFence']['payloadProofs'][3]['payloadFresh']),
    'planner stat4 expression partial current source next251 stale value payload' => static fn (TestRunner $t) => $t->same('forms-stale-cache', $staleValue251()['stat4CoveringPayloadFence']['payloadProofs'][3]['columnProofs'][1]['payloadValue']),
    'planner stat4 expression partial current source next251 stale value current' => static fn (TestRunner $t) => $t->same('forms-copy-a', $staleValue251()['stat4CoveringPayloadFence']['payloadProofs'][3]['columnProofs'][1]['currentValue']),
    'planner stat4 expression partial current source next251 stale value no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('VerifyCurrentStat4CoveringPayloads', array_column($staleValue251()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next251 missing payload status' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $missingPayload251()['status']),
    'planner stat4 expression partial current source next251 missing payload rowid' => static fn (TestRunner $t) => $t->same([22], $missingPayload251()['stat4CoveringPayloadFence']['missingPayloadRowids']),
    'planner stat4 expression partial current source next251 missing payload stale rowid' => static fn (TestRunner $t) => $t->same([22], $missingPayload251()['stat4CoveringPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next251 missing column status' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-covering-payload-reprepare', $missingColumn251()['status']),
    'planner stat4 expression partial current source next251 missing column rowid' => static fn (TestRunner $t) => $t->same([50], $missingColumn251()['stat4CoveringPayloadFence']['stalePayloadRowids']),
    'planner stat4 expression partial current source next251 missing column name' => static fn (TestRunner $t) => $t->same('updated_at', $missingColumn251()['stat4CoveringPayloadFence']['missingCoveredColumnProofs'][0]['column']),
    'planner stat4 expression partial current source next251 narrow columns ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next251-ready', $plan251(5, 1, null, null, null, ['option_name'])['status']),
    'planner stat4 expression partial current source next251 narrow columns list' => static fn (TestRunner $t) => $t->same(['option_name'], $plan251(5, 1, null, null, null, ['option_name'])['stat4CoveringPayloadFence']['neededColumns']),
    'planner stat4 expression partial current source next251 dedup columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value'], $plan251(5, 1, null, null, null, ['option_name', 'option_value', 'option_name'])['stat4CoveringPayloadFence']['neededColumns']),
    'planner stat4 expression partial current source next251 zero limit ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next251-ready', $plan251(0, 0)['status']),
    'planner stat4 expression partial current source next251 zero limit checked rowids' => static fn (TestRunner $t) => $t->same([], $plan251(0, 0)['stat4CoveringPayloadFence']['checkedRowids']),
    'planner stat4 expression partial current source next251 tail rowid' => static fn (TestRunner $t) => $t->same([10], $plan251(5, 7)['stat4CoveringPayloadFence']['checkedRowids']),
    'planner stat4 expression partial current source next251 invalid empty needed' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan251(5, 1, null, null, null, [])),
    'planner stat4 expression partial current source next251 invalid blank needed' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan251(5, 1, null, null, null, ['option_name', ''])),
    'planner stat4 expression partial current source next251 invalid payload list' => static function (TestRunner $t) use ($current251, $plan251): void {
        $bad = $current251();
        $bad['indexes'][0]['stat4ExpressionPayloads'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan251(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next251 invalid payload rowid' => static function (TestRunner $t) use ($current251, $plan251): void {
        $bad = $current251();
        $bad['indexes'][0]['stat4ExpressionPayloads'][0]['rowid'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan251(5, 1, null, $bad));
    },
    'planner stat4 expression partial current source next251 invalid covered values' => static function (TestRunner $t) use ($current251, $plan251): void {
        $bad = $current251();
        $bad['indexes'][0]['stat4ExpressionPayloads'][0]['coveredValues'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan251(5, 1, null, $bad));
    },
];

foreach (range(1, 28) as $case) {
    $tests['planner stat4 expression partial current source next251 repeated covering proof ' . $case] = static function (TestRunner $t) use ($plan251, $case): void {
        $plan = $plan251(1 + ($case % 5), $case % 4);
        $t->same($plan['stat4CoveringPayloadFence']['proofSignature'], $plan['selectedPlan']['next251ProofSignature']);
    };
}

return $tests;
