<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr153 = static fn (string $function, string $column): array => ['function' => $function, 'column' => $column];
$column153 = static fn (string $name): array => ['column' => $name];
$point153 = static fn (array $left, mixed $value): array => ['operator' => '=', 'left' => $left, 'right' => $value];
$range153 = static fn (array $left, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $left, 'right' => $value];
$and153 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$lower153 = $expr153('lower', 'option_name');
$predicate153 = $and153(
    $range153($lower153, '>=', 'plugin_'),
    $range153($lower153, '<', 'plugin_z'),
    $point153($column153('autoload'), 'yes'),
);
$order153 = [$lower153, ['column' => 'option_id']];
$needed153 = ['option_name', 'autoload', 'option_value', 'option_id'];
$neededExpressions153 = [$lower153];

$preparedSource153 = static function (array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-stat4-expression-partial-sample-fence',
        'schemaCookie' => 1530,
        'stat4Generation' => 15,
        'rowGeneration' => 5,
        'indexes' => [[
            'name' => 'idx_wp_options_lower_autoload_partial_sample_fence',
            'rootPage' => 15301,
            'estimatedRows' => 60,
            'coveringColumns' => ['option_name', 'autoload', 'option_value', 'option_id'],
            'coveringExpressions' => [['function' => 'lower', 'column' => 'option_name']],
            'stat4Samples' => [
                ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_cache', 11]],
                ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 12]],
                ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_mail', 13]],
            ],
            'sql' => "CREATE INDEX idx_wp_options_lower_autoload_partial_sample_fence ON wp_options(lower(option_name), option_id, option_value) WHERE autoload = 'yes'",
        ]],
        'rows' => [
            ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'old-cache'],
            ['rowid' => 12, 'option_id' => 12, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms'],
            ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_mail', 'autoload' => 'yes', 'option_value' => 'mail'],
        ],
    ], $overrides);
};

$currentSource153 = static function (array $overrides = []) use ($preparedSource153): array {
    $source = $preparedSource153([
        'name' => 'current-stat4-expression-partial-sample-fence',
        'schemaCookie' => 1534,
        'stat4Generation' => 22,
        'rowGeneration' => 9,
    ]);
    $source['indexes'][0]['rootPage'] = 15344;
    $source['indexes'][0]['estimatedRows'] = 18;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 21]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 11]],
        ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 12]],
        ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_seo', 22]],
    ];
    $source['rows'] = [
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'option_value' => 'new-cache'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'plugin_forms', 'autoload' => 'yes', 'option_value' => 'forms'],
        ['rowid' => 13, 'option_id' => 13, 'option_name' => 'plugin_mail', 'autoload' => 'no', 'option_value' => 'disabled'],
        ['rowid' => 21, 'option_id' => 21, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'option_value' => 'alpha'],
        ['rowid' => 22, 'option_id' => 22, 'option_name' => 'plugin_seo', 'autoload' => 'yes', 'option_value' => 'seo'],
    ];

    return array_replace_recursive($source, $overrides);
};

$plan153 = static fn (
    ?array $prepared = null,
    ?array $current = null,
    ?array $predicate = null,
    ?array $needed = null,
): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4SampleCurrentSourceFence(
    $prepared ?? $preparedSource153(),
    $current ?? $currentSource153(),
    $predicate ?? $predicate153,
    $order153,
    $needed ?? $needed153,
    $neededExpressions153,
);

$fresh153 = static fn (): array => $plan153($preparedSource153(), $preparedSource153(['name' => 'current-fresh-stat4-sample-fence']));
$noStat4153 = static function () use ($currentSource153, $plan153): array {
    $current = $currentSource153();
    $current['indexes'][0]['stat4Samples'] = [];

    return $plan153(null, $current);
};
$uncovered153 = static function () use ($currentSource153, $plan153): array {
    $current = $currentSource153();
    $current['indexes'][0]['coveringColumns'] = ['option_name'];

    return $plan153(null, $current);
};

return [
    'planner stat4 expression partial current source next153 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-sample-fence-ready', $plan153()['status']),
    'planner stat4 expression partial current source next153 selects current' => static fn (TestRunner $t) => $t->same('current', $plan153()['selectedSource']),
    'planner stat4 expression partial current source next153 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan153()['stalePreparedStatement']),
    'planner stat4 expression partial current source next153 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan153()['sampleFence']['schemaCookieChanged']),
    'planner stat4 expression partial current source next153 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan153()['sampleFence']['stat4GenerationChanged']),
    'planner stat4 expression partial current source next153 index changed' => static fn (TestRunner $t) => $t->same(true, $plan153()['sampleFence']['indexSignatureChanged']),
    'planner stat4 expression partial current source next153 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_autoload_partial_sample_fence', $plan153()['selectedPlan']['name']),
    'planner stat4 expression partial current source next153 selected root' => static fn (TestRunner $t) => $t->same(15344, $plan153()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next153 current rowids' => static fn (TestRunner $t) => $t->same([21, 11, 12, 22], $plan153()['sampleFence']['currentRowids']),
    'planner stat4 expression partial current source next153 prepared rowids' => static fn (TestRunner $t) => $t->same([11, 12, 13], $plan153()['sampleFence']['preparedRowids']),
    'planner stat4 expression partial current source next153 matched rowids' => static fn (TestRunner $t) => $t->same([21, 11, 12, 22], $plan153()['sampleFence']['matchedRowids']),
    'planner stat4 expression partial current source next153 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_seo'], $plan153()['sampleFence']['matchedKeys']),
    'planner stat4 expression partial current source next153 blocks stale rowid' => static fn (TestRunner $t) => $t->same([13], $plan153()['sampleFence']['stalePreparedRowidsBlocked']),
    'planner stat4 expression partial current source next153 sample pair count' => static fn (TestRunner $t) => $t->same(4, count($plan153()['sampleFence']['samplePairs'])),
    'planner stat4 expression partial current source next153 sample first next' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan153()['sampleFence']['samplePairs'][0]['next']['key']),
    'planner stat4 expression partial current source next153 sample final eof' => static fn (TestRunner $t) => $t->same(null, $plan153()['sampleFence']['samplePairs'][3]['next']),
    'planner stat4 expression partial current source next153 sample signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan153()['sampleFence']['sampleSignature'])),
    'planner stat4 expression partial current source next153 row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan153()['sampleFence']['rowStreamSignature'])),
    'planner stat4 expression partial current source next153 cursor filters stale' => static fn (TestRunner $t) => $t->same(['opcode' => 'FilterStalePartialRowids', 'rowids' => [13]], $plan153()['cursorTape']['program'][4]),
    'planner stat4 expression partial current source next153 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan153()['tableLookupElided']),
    'planner stat4 expression partial current source next153 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-sample-fence', $plan153()['dependencies'], true)),
    'planner stat4 expression partial current source next153 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan153()['dependency_closure']),
    'planner stat4 expression partial current source next153 non overlap' => static fn (TestRunner $t) => $t->contains('without adding a numbered production helper', $plan153()['non_overlap']),
    'planner stat4 expression partial current source next153 fresh selects prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh153()['selectedSource']),
    'planner stat4 expression partial current source next153 fresh rowids' => static fn (TestRunner $t) => $t->same([11, 12, 13], $fresh153()['sampleFence']['matchedRowids']),
    'planner stat4 expression partial current source next153 no stat4 falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat4153()['status']),
    'planner stat4 expression partial current source next153 uncovered falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncovered153()['status']),
    'planner stat4 expression partial current source next153 validates current rowid' => static function (TestRunner $t) use ($currentSource153, $plan153): void {
        $bad = $currentSource153();
        $bad['rows'][0]['rowid'] = -1;
        $t->throws(InvalidArgumentException::class, static fn () => $plan153(null, $bad));
    },
    'planner stat4 expression partial current source next153 validates needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan153(null, null, null, [])),
];
