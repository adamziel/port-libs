<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq248 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like248 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull248 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between248 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$rows248 = static fn (): array => [
    ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
    ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
    ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
    ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
    ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
    ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
    ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-anchor', 'updated_at' => 40],
    ['rowid' => 41, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Cache', 'option_value' => 'cache-copy', 'updated_at' => 41],
    ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
    ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
];

$samples248 = static fn (?array $override = null): array => $override ?? [
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 40, 1]],
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 41, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 21, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 4', 'sample' => ['plugin_zulu', 60, 1]],
];

$prepared248 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-duplicate-run-next248',
    'schemaCookie' => 2480,
    'stat4Generation' => 248,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_duplicate_run_next248',
        'rootPage' => 24801,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_cache'],
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

$current248 = static function (?array $samples = null, ?array $rows = null) use ($prepared248, $rows248, $samples248): array {
    $source = $prepared248();
    $source['name'] = 'current-wp-options-stat4-duplicate-run-next248';
    $source['schemaCookie'] = 2488;
    $source['stat4Generation'] = 648;
    $source['rows'] = $rows ?? $rows248();
    $source['indexes'][0]['rootPage'] = 24888;
    $source['indexes'][0]['stat1'] = ['rows' => '8 2 1'];
    $source['indexes'][0]['stat4Samples'] = $samples248($samples);

    return $source;
};

$terms248 = static fn (): array => [
    $between248('LOWER(option_name)', 'plugin_cache', 'plugin_zulu'),
    $eq248('autoload', 'yes'),
    $notNull248('option_name'),
    $eq248('blog_id', 1),
    $like248('option_name', 'plugin_%'),
];

$plan248 = static fn (?array $samples = null, ?array $rows = null, int $limit = 8, int $offset = 0): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDuplicateRunValidation(
    $prepared248(),
    $current248($samples, $rows),
    $terms248(),
    ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$missingDuplicate248 = static fn (): array => $plan248([
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 40, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 21, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 4', 'sample' => ['plugin_zulu', 60, 1]],
]);

$wrongRunOrder248 = static fn (): array => $plan248([
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 41, 1]],
    ['neq' => '2 2', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 40, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 21, 1]],
    ['neq' => '3 3', 'nlt' => '2 2', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 22, 1]],
    ['neq' => '1 1', 'nlt' => '5 5', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 50, 1]],
    ['neq' => '1 1', 'nlt' => '6 6', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 30, 1]],
    ['neq' => '1 1', 'nlt' => '7 7', 'ndlt' => '4 4', 'sample' => ['plugin_zulu', 60, 1]],
]);

$tests = [
    'planner stat4 expression partial current source next248 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next248-ready', $plan248()['status']),
    'planner stat4 expression partial current source next248 inherits stat4SampleAnchor' => static fn (TestRunner $t) => $t->same(true, $plan248()['selectedPlan']['stat4SampleAnchorReady']),
    'planner stat4 expression partial current source next248 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan248()['selectedPlan']['next248Ready']),
    'planner stat4 expression partial current source next248 selected current' => static fn (TestRunner $t) => $t->same('current', $plan248()['selectedSource']),
    'planner stat4 expression partial current source next248 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_duplicate_run_next248', $plan248()['selectedPlan']['name']),
    'planner stat4 expression partial current source next248 root page' => static fn (TestRunner $t) => $t->same(24888, $plan248()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next248 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22, 40, 41], $plan248()['matchedRowids']),
    'planner stat4 expression partial current source next248 projected duplicate payload' => static fn (TestRunner $t) => $t->same('cache-copy', $plan248()['projectedRows'][7]['option_value']),
    'planner stat4 expression partial current source next248 partial count' => static fn (TestRunner $t) => $t->same(8, $plan248()['stat4DuplicateRunFence']['partialRowCount']),
    'planner stat4 expression partial current source next248 index order rowids' => static fn (TestRunner $t) => $t->same([40, 41, 20, 21, 22, 50, 30, 60], $plan248()['stat4DuplicateRunFence']['partialRowidsInIndexOrder']),
    'planner stat4 expression partial current source next248 duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms'], $plan248()['stat4DuplicateRunFence']['duplicateKeys']),
    'planner stat4 expression partial current source next248 duplicate count' => static fn (TestRunner $t) => $t->same(2, $plan248()['stat4DuplicateRunFence']['duplicateRunCount']),
    'planner stat4 expression partial current source next248 rejected empty' => static fn (TestRunner $t) => $t->same([], $plan248()['stat4DuplicateRunFence']['rejectedKeys']),
    'planner stat4 expression partial current source next248 rejected reason null' => static fn (TestRunner $t) => $t->same(null, $plan248()['stat4DuplicateRunFence']['rejectedReason']),
    'planner stat4 expression partial current source next248 first proof key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan248()['stat4DuplicateRunFence']['duplicateRunProofs'][0]['expressionKey']),
    'planner stat4 expression partial current source next248 first proof current rowids' => static fn (TestRunner $t) => $t->same([40, 41], $plan248()['stat4DuplicateRunFence']['duplicateRunProofs'][0]['currentRowids']),
    'planner stat4 expression partial current source next248 first proof sample rowids' => static fn (TestRunner $t) => $t->same([40, 41], $plan248()['stat4DuplicateRunFence']['duplicateRunProofs'][0]['sampleRowids']),
    'planner stat4 expression partial current source next248 forms proof rowids' => static fn (TestRunner $t) => $t->same([20, 21, 22], $plan248()['stat4DuplicateRunFence']['duplicateRunProofs'][1]['currentRowids']),
    'planner stat4 expression partial current source next248 singleton proof' => static fn (TestRunner $t) => $t->same(false, $plan248()['stat4DuplicateRunFence']['duplicateRunProofs'][2]['duplicateRun']),
    'planner stat4 expression partial current source next248 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan248()['stat4DuplicateRunFence']['proofSignature'])),
    'planner stat4 expression partial current source next248 run signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan248()['stat4DuplicateRunFence']['runSignature'])),
    'planner stat4 expression partial current source next248 selected duplicate keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms'], $plan248()['selectedPlan']['next248DuplicateKeys']),
    'planner stat4 expression partial current source next248 selected rejected' => static fn (TestRunner $t) => $t->same([], $plan248()['selectedPlan']['next248RejectedKeys']),
    'planner stat4 expression partial current source next248 selected signature' => static fn (TestRunner $t) => $t->same($plan248()['stat4DuplicateRunFence']['proofSignature'], $plan248()['selectedPlan']['next248ProofSignature']),
    'planner stat4 expression partial current source next248 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan248()['stat4Fence']['next248DuplicateRunReady']),
    'planner stat4 expression partial current source next248 stat4 rejected' => static fn (TestRunner $t) => $t->same([], $plan248()['stat4Fence']['next248RejectedKeys']),
    'planner stat4 expression partial current source next248 stat4 signature' => static fn (TestRunner $t) => $t->same($plan248()['stat4DuplicateRunFence']['proofSignature'], $plan248()['stat4Fence']['next248DuplicateRunSignature']),
    'planner stat4 expression partial current source next248 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4DuplicateRuns', $plan248()['cursorProgram'][array_key_last($plan248()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next248 cursor mode' => static fn (TestRunner $t) => $t->same('next248-current-source-stat4-expression-partial-duplicate-runs', $plan248()['cursorProgram'][array_key_last($plan248()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next248 cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms'], $plan248()['cursorProgram'][array_key_last($plan248()['cursorProgram'])]['duplicateKeys']),
    'planner stat4 expression partial current source next248 cursor rowids' => static fn (TestRunner $t) => $t->same([40, 41, 20, 21, 22, 50, 30, 60], $plan248()['cursorProgram'][array_key_last($plan248()['cursorProgram'])]['partialRowidsInIndexOrder']),
    'planner stat4 expression partial current source next248 cursor signature' => static fn (TestRunner $t) => $t->same($plan248()['stat4DuplicateRunFence']['proofSignature'], $plan248()['cursorProgram'][array_key_last($plan248()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next248 detail' => static fn (TestRunner $t) => $t->contains('NEXT248 DUPLICATE RUN FENCE', $plan248()['detail']),
    'planner stat4 expression partial current source next248 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next248', $plan248()['dependencies'], true)),
    'planner stat4 expression partial current source next248 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan248()['dependency_closure']),
    'planner stat4 expression partial current source next248 non overlap' => static fn (TestRunner $t) => $t->contains('duplicate expression-key run validation', $plan248()['non_overlap']),
    'planner stat4 expression partial current source next248 missing duplicate blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-run-reprepare', $missingDuplicate248()['status']),
    'planner stat4 expression partial current source next248 missing duplicate rejected' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms'], $missingDuplicate248()['stat4DuplicateRunFence']['rejectedKeys']),
    'planner stat4 expression partial current source next248 missing duplicate reason' => static fn (TestRunner $t) => $t->same('stale-stat4-duplicate-expression-key-run', $missingDuplicate248()['stat4DuplicateRunFence']['rejectedReason']),
    'planner stat4 expression partial current source next248 missing duplicate no cursor' => static fn (TestRunner $t) => $t->same(false, in_array('ValidateCurrentSourceStat4DuplicateRuns', array_column($missingDuplicate248()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next248 wrong run order blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-duplicate-run-reprepare', $wrongRunOrder248()['status']),
    'planner stat4 expression partial current source next248 wrong run order rejected' => static fn (TestRunner $t) => $t->same(['plugin_cache'], $wrongRunOrder248()['stat4DuplicateRunFence']['rejectedKeys']),
    'planner stat4 expression partial current source next248 malformed rowid' => static function (TestRunner $t) use ($plan248): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan248([
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 'bad', 1]],
        ]));
    },
];

foreach (range(1, 30) as $case) {
    $tests['planner stat4 expression partial current source next248 repeated duplicate run proof ' . $case] = static function (TestRunner $t) use ($plan248, $case): void {
        $plan = $plan248(null, null, 5 + ($case % 4), $case % 3);
        $t->same($plan['stat4DuplicateRunFence']['proofSignature'], $plan['selectedPlan']['next248ProofSignature']);
    };
}

return $tests;
