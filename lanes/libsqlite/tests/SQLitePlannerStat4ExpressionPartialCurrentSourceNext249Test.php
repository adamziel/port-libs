<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq249 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like249 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull249 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between249 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows249 = static fn (): array => [
    ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_current', 'option_value' => 'theme', 'updated_at' => 90],
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples249 = static fn (?array $override = null): array => $override ?? [
    ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
];

$prepared249 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-peers-next249',
    'schemaCookie' => 2490,
    'stat4Generation' => 249,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_peers_next249',
        'rootPage' => 24901,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_forms'],
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
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_seo', 30, 1]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_zulu', 60, 1]],
        ],
    ]],
];

$current249 = static function (?array $samples = null, ?array $rows = null) use ($prepared249, $rows249, $samples249): array {
    $source = $prepared249();
    $source['name'] = 'current-wp-options-stat4-peers-next249';
    $source['schemaCookie'] = 2498;
    $source['stat4Generation'] = 948;
    $source['rows'] = $rows ?? $rows249();
    $source['indexes'][0]['rootPage'] = 24988;
    $source['indexes'][0]['stat1'] = ['rows' => '6 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples249($samples);

    return $source;
};

$terms249 = static fn (): array => [
    $between249('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq249('autoload', 'yes'),
    $notNull249('option_name'),
    $eq249('blog_id', 1),
    $like249('option_name', 'plugin_%'),
];

$plan249 = static fn (?array $samples = null, ?array $rows = null, int $limit = 6, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext249(
    $prepared249(),
    $current249($samples, $rows),
    $terms249(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$staleFormsCount249 = static fn (): array => $plan249([
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '4 4', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60, 1]],
]);
$deletedPeer249 = static function () use ($plan249, $rows249): array {
    $rows = array_values(array_filter($rows249(), static fn (array $row): bool => $row['rowid'] !== 21));

    return $plan249(null, $rows);
};
$newPeer249 = static function () use ($plan249, $rows249): array {
    $rows = $rows249();
    $rows[] = ['rowid' => 23, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-copy-c', 'updated_at' => 23];

    return $plan249(null, $rows);
};

$tests = [
    'planner stat4 expression partial current source next249 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next249-ready', $plan249()['status']),
    'planner stat4 expression partial current source next249 inherits stat4SampleAnchor' => static fn (TestRunner $t) => $t->same(true, $plan249()['selectedPlan']['stat4SampleAnchorReady']),
    'planner stat4 expression partial current source next249 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan249()['selectedPlan']['next249Ready']),
    'planner stat4 expression partial current source next249 selected current' => static fn (TestRunner $t) => $t->same('current', $plan249()['selectedSource']),
    'planner stat4 expression partial current source next249 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_peers_next249', $plan249()['selectedPlan']['name']),
    'planner stat4 expression partial current source next249 root page' => static fn (TestRunner $t) => $t->same(24988, $plan249()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next249 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan249()['matchedRowids']),
    'planner stat4 expression partial current source next249 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan249()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next249 sample count' => static fn (TestRunner $t) => $t->same(4, $plan249()['stat4DuplicatePeerFence']['sampleCount']),
    'planner stat4 expression partial current source next249 current peer counts' => static fn (TestRunner $t) => $t->same([
        'plugin_forms#1' => 3,
        'plugin_mail#1' => 1,
        'plugin_seo#1' => 1,
        'plugin_zulu#1' => 1,
    ], $plan249()['stat4DuplicatePeerFence']['currentPeerCounts']),
    'planner stat4 expression partial current source next249 forms peer rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan249()['stat4DuplicatePeerFence']['currentPeerRows'][0]['rowids']),
    'planner stat4 expression partial current source next249 no rejected peers' => static fn (TestRunner $t) => $t->same([], $plan249()['stat4DuplicatePeerFence']['rejectedPeerKeys']),
    'planner stat4 expression partial current source next249 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan249()['stat4DuplicatePeerFence']['rejectedReason']),
    'planner stat4 expression partial current source next249 first proof sample neq' => static fn (TestRunner $t) => $t->same(3, $plan249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['sampleNeq']),
    'planner stat4 expression partial current source next249 first proof current count' => static fn (TestRunner $t) => $t->same(3, $plan249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['currentPeerCount']),
    'planner stat4 expression partial current source next249 first proof rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['currentPeerRowids']),
    'planner stat4 expression partial current source next249 first proof matches' => static fn (TestRunner $t) => $t->same(true, $plan249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['matchesCurrentPeers']),
    'planner stat4 expression partial current source next249 mail proof count' => static fn (TestRunner $t) => $t->same(1, $plan249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][1]['currentPeerCount']),
    'planner stat4 expression partial current source next249 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan249()['stat4DuplicatePeerFence']['proofSignature'])),
    'planner stat4 expression partial current source next249 selected signature' => static fn (TestRunner $t) => $t->same($plan249()['stat4DuplicatePeerFence']['proofSignature'], $plan249()['selectedPlan']['next249PeerSignature']),
    'planner stat4 expression partial current source next249 selected rejected' => static fn (TestRunner $t) => $t->same([], $plan249()['selectedPlan']['next249RejectedPeerKeys']),
    'planner stat4 expression partial current source next249 selected counts' => static fn (TestRunner $t) => $t->same($plan249()['stat4DuplicatePeerFence']['currentPeerCounts'], $plan249()['selectedPlan']['next249CurrentPeerCounts']),
    'planner stat4 expression partial current source next249 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan249()['stat4Fence']['next249DuplicatePeerReady']),
    'planner stat4 expression partial current source next249 stat4 signature' => static fn (TestRunner $t) => $t->same($plan249()['stat4DuplicatePeerFence']['proofSignature'], $plan249()['stat4Fence']['next249DuplicatePeerSignature']),
    'planner stat4 expression partial current source next249 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4DuplicatePeers', $plan249()['cursorProgram'][array_key_last($plan249()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next249 cursor mode' => static fn (TestRunner $t) => $t->same('next249-current-source-stat4-expression-partial-duplicate-peers', $plan249()['cursorProgram'][array_key_last($plan249()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next249 cursor sample count' => static fn (TestRunner $t) => $t->same(4, $plan249()['cursorProgram'][array_key_last($plan249()['cursorProgram'])]['sampleCount']),
    'planner stat4 expression partial current source next249 cursor counts' => static fn (TestRunner $t) => $t->same($plan249()['stat4DuplicatePeerFence']['currentPeerCounts'], $plan249()['cursorProgram'][array_key_last($plan249()['cursorProgram'])]['currentPeerCounts']),
    'planner stat4 expression partial current source next249 detail' => static fn (TestRunner $t) => $t->contains('NEXT249 DUPLICATE PEER FENCE', $plan249()['detail']),
    'planner stat4 expression partial current source next249 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next249', $plan249()['dependencies'], true)),
    'planner stat4 expression partial current source next249 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan249()['dependency_closure']),
    'planner stat4 expression partial current source next249 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate peer-count validation', $plan249()['non_overlap']),
    'planner stat4 expression partial current source next249 stale count blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-peer-reprepare', $staleFormsCount249()['status']),
    'planner stat4 expression partial current source next249 stale count rejected' => static fn (TestRunner $t) => $t->same(['plugin_forms#1'], $staleFormsCount249()['stat4DuplicatePeerFence']['rejectedPeerKeys']),
    'planner stat4 expression partial current source next249 stale count current remains three' => static fn (TestRunner $t) => $t->same(3, $staleFormsCount249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['currentPeerCount']),
    'planner stat4 expression partial current source next249 deleted peer blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-peer-reprepare', $deletedPeer249()['status']),
    'planner stat4 expression partial current source next249 deleted peer count' => static fn (TestRunner $t) => $t->same(2, $deletedPeer249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['currentPeerCount']),
    'planner stat4 expression partial current source next249 deleted peer rejected' => static fn (TestRunner $t) => $t->same(['plugin_forms#1'], $deletedPeer249()['stat4DuplicatePeerFence']['rejectedPeerKeys']),
    'planner stat4 expression partial current source next249 new peer blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-peer-reprepare', $newPeer249()['status']),
    'planner stat4 expression partial current source next249 new peer count' => static fn (TestRunner $t) => $t->same(4, $newPeer249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['currentPeerCount']),
    'planner stat4 expression partial current source next249 new peer rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22, 23], $newPeer249()['stat4DuplicatePeerFence']['duplicatePeerProofs'][0]['currentPeerRowids']),
    'planner stat4 expression partial current source next249 malformed neq' => static function (TestRunner $t) use ($plan249): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan249([
            ['neq' => 'bad', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20, 1]],
        ]));
    },
    'planner stat4 expression partial current source next249 malformed rowid' => static function (TestRunner $t) use ($plan249): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan249([
            ['neq' => '3 3', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 'bad', 1]],
        ]));
    },
];

foreach (range(1, 24) as $case) {
    $tests['planner stat4 expression partial current source next249 repeated duplicate peer proof ' . $case] = static function (TestRunner $t) use ($plan249, $case): void {
        $plan = $plan249(null, null, 3 + ($case % 4), $case % 2);
        $t->same($plan['stat4DuplicatePeerFence']['proofSignature'], $plan['selectedPlan']['next249PeerSignature']);
    };
}

return $tests;
