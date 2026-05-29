<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4ExpressionCoveringCurrentSourceNextPlan;

$expr = static fn (string $function, string $column, ?string $path = null): array => array_filter(
    ['function' => $function, 'column' => $column, 'path' => $path],
    static fn (mixed $value): bool => $value !== null,
);
$column = static fn (string $name): array => ['column' => $name];
$point = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$in = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$range = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$jsonChannel = $expr('jsonb_extract', 'option_value', '$.plugin.channel');
$autoloadYes = $point($column('autoload'), 'yes');

$preparedSource = static fn (): array => [
    'name' => 'prepared-wp-options-next117',
    'schemaCookie' => 50,
    'stat4Generation' => 12,
    'indexes' => [[
        'name' => 'idx_wp_options_channel_covering_stat4_old_next117',
        'rootPage' => 1170,
        'estimatedRows' => 220,
        'stat4Samples' => [
            ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'yes']],
            ['neq' => '9 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['beta', 'yes']],
            ['neq' => '4 1', 'nlt' => '12 2', 'ndlt' => '2 2', 'sample' => ['legacy', 'yes']],
        ],
        'coveringColumns' => ['option_name', 'autoload', 'option_id', 'blog_id'],
        'sql' => "CREATE INDEX idx_wp_options_channel_covering_stat4_old_next117 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload, option_id) WHERE autoload = 'yes'",
    ]],
    'rows' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'plugin_alpha_old', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"alpha"}}'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'plugin_beta_old', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"beta"}}'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'plugin_legacy_old', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"legacy"}}'],
    ],
];

$currentSource = static fn (): array => [
    'name' => 'current-wp-options-next117',
    'schemaCookie' => 51,
    'stat4Generation' => 13,
    'indexes' => [[
        'name' => 'idx_wp_options_channel_covering_stat4_current_next117',
        'rootPage' => 1171,
        'estimatedRows' => 180,
        'stat4Samples' => [
            ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['alpha', 'yes']],
            ['neq' => '7 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['beta', 'yes']],
            ['neq' => '5 1', 'nlt' => '10 2', 'ndlt' => '2 2', 'sample' => ['stable', 'yes']],
            ['neq' => '2 1', 'nlt' => '15 3', 'ndlt' => '3 3', 'sample' => ['theta', 'no']],
        ],
        'coveringColumns' => ['option_name', 'autoload', 'option_id', 'blog_id'],
        'sql' => "CREATE INDEX idx_wp_options_channel_covering_stat4_current_next117 ON wp_options(jsonb_extract(option_value, '$.plugin.channel'), autoload, option_id) WHERE autoload = 'yes'",
    ]],
    'rows' => [
        ['rowid' => 10, 'option_id' => 10, 'option_name' => 'plugin_beta_a', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"beta"}}'],
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"alpha"}}'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'plugin_stable', 'autoload' => 'yes', 'blog_id' => 2, 'option_value' => '{"plugin":{"channel":"stable"}}'],
        ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_beta_b', 'autoload' => 'yes', 'blog_id' => 3, 'option_value' => '{"plugin":{"channel":"beta"}}'],
        ['rowid' => 14, 'option_id' => 14, 'option_name' => 'plugin_theta_no', 'autoload' => 'no', 'blog_id' => 1, 'option_value' => '{"plugin":{"channel":"theta"}}'],
        ['rowid' => 15, 'option_id' => 15, 'option_name' => 'plugin_missing', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => '{"plugin":{"enabled":true}}'],
    ],
];

$plan = static function (?array $predicate = null, ?array $prepared = null, ?array $current = null, array $neededColumns = ['option_name', 'autoload', 'option_id', 'blog_id'], array $neededExpressions = null) use ($preparedSource, $currentSource, $and, $in, $jsonChannel, $autoloadYes): array {
    return SQLiteStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext117(
        $prepared ?? $preparedSource(),
        $current ?? $currentSource(),
        $predicate ?? $and($in($jsonChannel, ['alpha', 'beta', 'stable']), $autoloadYes),
        [$jsonChannel, ['column' => 'autoload']],
        $neededColumns,
        $neededExpressions ?? [$jsonChannel],
    );
};

$tests = [];

$tests['planner expression index stat4 covering current source next117 status ready'] = static fn (TestRunner $t) => $t->same('stat4-expression-covering-current-source-ready', $plan()['status']);
$tests['planner expression index stat4 covering current source next117 selects current source'] = static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']);
$tests['planner expression index stat4 covering current source next117 marks stale prepared'] = static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']);
$tests['planner expression index stat4 covering current source next117 requires reprepare'] = static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']);
$tests['planner expression index stat4 covering current source next117 detects schema cookie'] = static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']);
$tests['planner expression index stat4 covering current source next117 detects stat4 generation'] = static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']);
$tests['planner expression index stat4 covering current source next117 detects index signature'] = static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']);
$tests['planner expression index stat4 covering current source next117 current summary ready'] = static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['ready']);
$tests['planner expression index stat4 covering current source next117 prepared summary ready'] = static fn (TestRunner $t) => $t->same(true, $plan()['preparedSource']['ready']);
$tests['planner expression index stat4 covering current source next117 prepared old root'] = static fn (TestRunner $t) => $t->same(1170, $plan()['preparedSource']['rootPage']);
$tests['planner expression index stat4 covering current source next117 current root'] = static fn (TestRunner $t) => $t->same(1171, $plan()['currentSource']['rootPage']);
$tests['planner expression index stat4 covering current source next117 current row count'] = static fn (TestRunner $t) => $t->same(4, $plan()['currentSource']['coveredRowCount']);
$tests['planner expression index stat4 covering current source next117 selected row count'] = static fn (TestRunner $t) => $t->same(4, $plan()['selectedPlan']['coveredRowCount']);
$tests['planner expression index stat4 covering current source next117 selected index'] = static fn (TestRunner $t) => $t->same('idx_wp_options_channel_covering_stat4_current_next117', $plan()['selectedPlan']['name']);
$tests['planner expression index stat4 covering current source next117 selected root'] = static fn (TestRunner $t) => $t->same(1171, $plan()['selectedPlan']['rootPage']);
$tests['planner expression index stat4 covering current source next117 selected covering'] = static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']);
$tests['planner expression index stat4 covering current source next117 selected order'] = static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['orderBySatisfied']);
$tests['planner expression index stat4 covering current source next117 selected stat4 samples'] = static fn (TestRunner $t) => $t->same(3, $plan()['selectedPlan']['stat4MatchedSamples']);
$tests['planner expression index stat4 covering current source next117 selected stat4 estimate'] = static fn (TestRunner $t) => $t->same(15, $plan()['selectedPlan']['stat4Estimate']);
$tests['planner expression index stat4 covering current source next117 sorted keys'] = static fn (TestRunner $t) => $t->same(['alpha', 'beta', 'beta', 'stable'], $plan()['cursorTape']['expressionKeys']);
$tests['planner expression index stat4 covering current source next117 sorted rowids'] = static fn (TestRunner $t) => $t->same([11, 10, 13, 12], $plan()['cursorTape']['rowids']);
$tests['planner expression index stat4 covering current source next117 cursor source'] = static fn (TestRunner $t) => $t->same('current', $plan()['cursorTape']['source']);
$tests['planner expression index stat4 covering current source next117 cursor status'] = static fn (TestRunner $t) => $t->same('covering-stat4-current-source', $plan()['cursorTape']['status']);
$tests['planner expression index stat4 covering current source next117 cursor root'] = static fn (TestRunner $t) => $t->same(1171, $plan()['cursorTape']['rootPage']);
$tests['planner expression index stat4 covering current source next117 cursor schema cookie'] = static fn (TestRunner $t) => $t->same(51, $plan()['cursorTape']['schemaCookie']);
$tests['planner expression index stat4 covering current source next117 cursor stat4 generation'] = static fn (TestRunner $t) => $t->same(13, $plan()['cursorTape']['stat4Generation']);
$tests['planner expression index stat4 covering current source next117 cursor order signature'] = static fn (TestRunner $t) => $t->same('jsonb_extract(option_value, $.plugin.channel) ASC, autoload ASC', $plan()['cursorTape']['orderSignature']);
$tests['planner expression index stat4 covering current source next117 cursor expression signature'] = static fn (TestRunner $t) => $t->same(['jsonb_extract(option_value, $.plugin.channel)'], $plan()['cursorTape']['coveringExpressions']);
$tests['planner expression index stat4 covering current source next117 cursor covering columns'] = static fn (TestRunner $t) => $t->same(['option_name', 'autoload', 'option_id', 'blog_id'], $plan()['cursorTape']['coveringColumns']);
$tests['planner expression index stat4 covering current source next117 elides table lookup'] = static fn (TestRunner $t) => $t->same(true, $plan()['cursorTape']['tableLookupElided']);
$tests['planner expression index stat4 covering current source next117 elides deferred seek'] = static fn (TestRunner $t) => $t->same(null, $plan()['cursorTape']['deferredSeekOpcode']);
$tests['planner expression index stat4 covering current source next117 elides sorter'] = static fn (TestRunner $t) => $t->same(false, $plan()['cursorTape']['sorterOpen']);
$tests['planner expression index stat4 covering current source next117 program opens current root'] = static fn (TestRunner $t) => $t->same(['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => 1171, 'source' => 'current'], $plan()['cursorTape']['program'][0]);
$tests['planner expression index stat4 covering current source next117 program reads expression'] = static fn (TestRunner $t) => $t->same(['opcode' => 'ExpressionColumn', 'source' => 'index', 'expression' => 'jsonb_extract(option_value, $.plugin.channel)'], $plan()['cursorTape']['program'][1]);
$tests['planner expression index stat4 covering current source next117 program reads option name'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'option_name'], $plan()['cursorTape']['program'][2]);
$tests['planner expression index stat4 covering current source next117 program reads autoload'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'autoload'], $plan()['cursorTape']['program'][3]);
$tests['planner expression index stat4 covering current source next117 program reads blog id'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'column' => 'blog_id'], $plan()['cursorTape']['program'][5]);
$tests['planner expression index stat4 covering current source next117 program advances'] = static fn (TestRunner $t) => $t->same(['opcode' => 'Next', 'target' => 'index'], $plan()['cursorTape']['program'][6]);
$tests['planner expression index stat4 covering current source next117 first row covering name'] = static fn (TestRunner $t) => $t->same('plugin_alpha', $plan()['selectedPlan']['currentNextRows'][0]['current']['covering']['option_name']);
$tests['planner expression index stat4 covering current source next117 second beta next row'] = static fn (TestRunner $t) => $t->same(13, $plan()['selectedPlan']['currentNextRows'][1]['next']['rowid']);
$tests['planner expression index stat4 covering current source next117 stable last next null'] = static fn (TestRunner $t) => $t->same(null, $plan()['selectedPlan']['currentNextRows'][3]['next']);
$tests['planner expression index stat4 covering current source next117 covers expression payload'] = static fn (TestRunner $t) => $t->same('alpha', $plan()['selectedPlan']['currentNextRows'][0]['current']['coveringExpressions']['jsonb_extract(option_value)']);
$tests['planner expression index stat4 covering current source next117 keeps current fence cookie'] = static fn (TestRunner $t) => $t->same(51, $plan()['currentSourceFence']['schemaCookie']);
$tests['planner expression index stat4 covering current source next117 keeps current fence stat4'] = static fn (TestRunner $t) => $t->same(13, $plan()['currentSourceFence']['stat4Generation']);
$tests['planner expression index stat4 covering current source next117 keeps fence order'] = static fn (TestRunner $t) => $t->same('jsonb_extract(option_value, $.plugin.channel) ASC, autoload ASC', $plan()['currentSourceFence']['orderSignature']);
$tests['planner expression index stat4 covering current source next117 records dependency'] = static fn (TestRunner $t) => $t->same(['SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan', 'sqlite-stat4-expression-covering-current-source-next117'], $plan()['dependencies']);
$tests['planner expression index stat4 covering current source next117 records detail'] = static fn (TestRunner $t) => $t->contains('REPREPARE STAT4 EXPRESSION COVERING CURRENT SOURCE current-wp-options-next117', $plan()['detail']);
$tests['planner expression index stat4 covering current source next117 records non overlap'] = static fn (TestRunner $t) => $t->contains('prepared/current source-fence selection', $plan()['non_overlap']);
$tests['planner expression index stat4 covering current source next117 records dependency closure'] = static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']);

$tests['planner expression index stat4 covering current source next117 reuses prepared when source unchanged'] = static function (TestRunner $t) use ($plan, $preparedSource): void {
    $candidate = $plan(null, $preparedSource(), $preparedSource(), ['option_name', 'autoload', 'option_id']);
    $t->same('prepared', $candidate['selectedSource']);
    $t->same(false, $candidate['reprepareRequired']);
    $t->same([1, 2], $candidate['cursorTape']['rowids']);
};

$tests['planner expression index stat4 covering current source next117 point predicate narrows stream'] = static function (TestRunner $t) use ($plan, $and, $point, $jsonChannel, $autoloadYes): void {
    $candidate = $plan($and($point($jsonChannel, 'stable'), $autoloadYes));
    $t->same(1, $candidate['selectedPlan']['coveredRowCount']);
    $t->same(['stable'], $candidate['cursorTape']['expressionKeys']);
    $t->same([12], $candidate['cursorTape']['rowids']);
};

$tests['planner expression index stat4 covering current source next117 lower range uses matched stat4 rows'] = static function (TestRunner $t) use ($plan, $and, $range, $jsonChannel, $autoloadYes): void {
    $candidate = $plan($and($range($jsonChannel, '>=', 'beta'), $autoloadYes));
    $t->same(['beta', 'beta', 'stable'], $candidate['cursorTape']['expressionKeys']);
    $t->same(3, $candidate['selectedPlan']['coveredRowCount']);
    $t->same(177, $candidate['selectedPlan']['stat4Estimate']);
};

$tests['planner expression index stat4 covering current source next117 missing covering column falls back'] = static function (TestRunner $t) use ($plan): void {
    $candidate = $plan(null, null, null, ['option_name', 'missing_column']);
    $t->same('requires-next-stage', $candidate['status']);
    $t->same('no-plan', $candidate['cursorTape']['status']);
    $t->same('DeferredSeek', $candidate['cursorTape']['deferredSeekOpcode']);
};

$tests['planner expression index stat4 covering current source next117 validates source row list'] = static function (TestRunner $t) use ($plan, $currentSource): void {
    $current = $currentSource();
    $current['rows'][] = 'bad';
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, null, $current));
};

$tests['planner expression index stat4 covering current source next117 validates source index list'] = static function (TestRunner $t) use ($plan, $currentSource): void {
    $current = $currentSource();
    $current['indexes'] = ['bad'];
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, null, $current));
};

$tests['planner expression index stat4 covering current source next117 validates order direction'] = static function (TestRunner $t) use ($preparedSource, $currentSource, $jsonChannel, $autoloadYes, $and, $in): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteStat4ExpressionCoveringCurrentSourceNextPlan::materializeNext117(
        $preparedSource(),
        $currentSource(),
        $and($in($jsonChannel, ['alpha']), $autoloadYes),
        [array_merge($jsonChannel, ['direction' => 'SIDEWAYS'])],
        ['option_name'],
        [$jsonChannel],
    ));
};

return $tests;
