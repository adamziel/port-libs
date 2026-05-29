<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq192 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull192 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between192 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared192 = static fn (): array => [
    'name' => 'prepared-wp-options-covering-partial-stat4-expression-next192',
    'schemaCookie' => 1920,
    'stat4Generation' => 151,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_covering_partial_stat4_next192',
        'rootPage' => 19201,
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
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current192 = static function () use ($prepared192): array {
    $source = $prepared192();
    $source['name'] = 'current-wp-options-covering-partial-stat4-expression-next192';
    $source['schemaCookie'] = 1929;
    $source['stat4Generation'] = 177;
    $source['indexes'][0]['rootPage'] = 19288;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return $source;
};

$terms192 = static fn (): array => [
    $between192('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq192('autoload', 'yes'),
    $notNull192('option_name'),
];
$plan192 = static fn (array $needed = ['option_name', 'option_value', 'updated_at', 'blog_id'], int $limit = 4, int $offset = 1, ?array $prepared = null, ?array $current = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext192(
    $prepared ?? $prepared192(),
    $current ?? $current192(),
    $terms192(),
    $needed,
    $limit,
    $offset,
);
$nonCovering192 = static fn (): array => $plan192(['option_name', 'option_value', 'autoload', 'option_extra']);

$tests = [
    'planner stat4 expression partial current source next192 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next192-ready', $plan192()['status']),
    'planner stat4 expression partial current source next192 selected source' => static fn (TestRunner $t) => $t->same('current', $plan192()['selectedSource']),
    'planner stat4 expression partial current source next192 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_covering_partial_stat4_next192', $plan192()['selectedPlan']['name']),
    'planner stat4 expression partial current source next192 root page' => static fn (TestRunner $t) => $t->same(19288, $plan192()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next192 inherited next189 ready' => static fn (TestRunner $t) => $t->same(true, $plan192()['selectedPlan']['next189Ready']),
    'planner stat4 expression partial current source next192 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan192()['selectedPlan']['next192Ready']),
    'planner stat4 expression partial current source next192 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan192()['matchedRowids']),
    'planner stat4 expression partial current source next192 covering fence ready' => static fn (TestRunner $t) => $t->same(true, $plan192()['coveringColumnFence']['ready']),
    'planner stat4 expression partial current source next192 needed columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan192()['coveringColumnFence']['neededColumns']),
    'planner stat4 expression partial current source next192 covering columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'], $plan192()['coveringColumnFence']['coveringColumns']),
    'planner stat4 expression partial current source next192 expression column' => static fn (TestRunner $t) => $t->same('__expr_lower_option_name', $plan192()['coveringColumnFence']['expressionColumn']),
    'planner stat4 expression partial current source next192 available columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id', '__expr_lower_option_name'], $plan192()['coveringColumnFence']['availableColumns']),
    'planner stat4 expression partial current source next192 no missing columns' => static fn (TestRunner $t) => $t->same([], $plan192()['coveringColumnFence']['missingColumns']),
    'planner stat4 expression partial current source next192 no row missing columns' => static fn (TestRunner $t) => $t->same([], $plan192()['coveringColumnFence']['rowMissingColumns']),
    'planner stat4 expression partial current source next192 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan192()['tableLookupElided']),
    'planner stat4 expression partial current source next192 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan192()['deferredSeekOpcode']),
    'planner stat4 expression partial current source next192 selected no table lookup' => static fn (TestRunner $t) => $t->same(false, $plan192()['selectedPlan']['next192TableLookupRequired']),
    'planner stat4 expression partial current source next192 row check count' => static fn (TestRunner $t) => $t->same(4, count($plan192()['coveringColumnFence']['rowChecks'])),
    'planner stat4 expression partial current source next192 row check rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], array_column($plan192()['coveringColumnFence']['rowChecks'], 'rowid')),
    'planner stat4 expression partial current source next192 row check ready' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan192()['coveringColumnFence']['rowChecks'], 'ready')),
    'planner stat4 expression partial current source next192 row present columns' => static fn (TestRunner $t) => $t->same([['option_name', 'option_value', 'updated_at', 'blog_id'], ['option_name', 'option_value', 'updated_at', 'blog_id'], ['option_name', 'option_value', 'updated_at', 'blog_id'], ['option_name', 'option_value', 'updated_at', 'blog_id']], array_column($plan192()['coveringColumnFence']['rowChecks'], 'presentColumns')),
    'planner stat4 expression partial current source next192 row missing lists empty' => static fn (TestRunner $t) => $t->same([[], [], [], []], array_column($plan192()['coveringColumnFence']['rowChecks'], 'missingColumns')),
    'planner stat4 expression partial current source next192 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan192()['coveringColumnFence']['signature'])),
    'planner stat4 expression partial current source next192 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan192()['stat4Fence']['next192CoveringSignature'])),
    'planner stat4 expression partial current source next192 row payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan192()['coveringColumnFence']['rowChecks'][0]['payloadSignature'])),
    'planner stat4 expression partial current source next192 cursor opcode' => static fn (TestRunner $t) => $t->same('CoveringColumnFence', $plan192()['cursorProgram'][array_key_last($plan192()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next192 cursor ready' => static fn (TestRunner $t) => $t->same(true, $plan192()['cursorProgram'][array_key_last($plan192()['cursorProgram'])]['ready']),
    'planner stat4 expression partial current source next192 cursor needed columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'updated_at', 'blog_id'], $plan192()['cursorProgram'][array_key_last($plan192()['cursorProgram'])]['neededColumns']),
    'planner stat4 expression partial current source next192 cursor missing empty' => static fn (TestRunner $t) => $t->same([], $plan192()['cursorProgram'][array_key_last($plan192()['cursorProgram'])]['missingColumns']),
    'planner stat4 expression partial current source next192 projected payload retained' => static fn (TestRunner $t) => $t->same('mail', $plan192()['projectedRows'][1]['option_value']),
    'planner stat4 expression partial current source next192 inherited payload fence ready' => static fn (TestRunner $t) => $t->same(true, $plan192()['currentPayloadPartialFence']['ready']),
    'planner stat4 expression partial current source next192 detail' => static fn (TestRunner $t) => $t->contains('NEXT192 COVERING COLUMN FENCE', $plan192()['detail']),
    'planner stat4 expression partial current source next192 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next192', $plan192()['dependencies'], true)),
    'planner stat4 expression partial current source next192 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan192()['dependency_closure']),
    'planner stat4 expression partial current source next192 non overlap' => static fn (TestRunner $t) => $t->contains('requested payload columns are still covered', $plan192()['non_overlap']),
    'planner stat4 expression partial current source next192 non covering status' => static fn (TestRunner $t) => $t->same('requires-current-source-covering-reprepare', $nonCovering192()['status']),
    'planner stat4 expression partial current source next192 non covering missing' => static fn (TestRunner $t) => $t->same(['option_extra'], $nonCovering192()['coveringColumnFence']['missingColumns']),
    'planner stat4 expression partial current source next192 non covering selected missing' => static fn (TestRunner $t) => $t->same(['option_extra'], $nonCovering192()['selectedPlan']['next192MissingColumns']),
    'planner stat4 expression partial current source next192 non covering deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering192()['deferredSeekOpcode']),
    'planner stat4 expression partial current source next192 non covering cursor opcode' => static fn (TestRunner $t) => $t->same('DeferredSeek', $nonCovering192()['cursorProgram'][array_key_last($nonCovering192()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next192 zero window blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-covering-reprepare', $plan192(['option_name'], 0, 0)['status']),
    'planner stat4 expression partial current source next192 zero window row checks empty' => static fn (TestRunner $t) => $t->same([], $plan192(['option_name'], 0, 0)['coveringColumnFence']['rowChecks']),
    'planner stat4 expression partial current source next192 empty needed blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-covering-reprepare', $plan192([], 2, 0)['status']),
    'planner stat4 expression partial current source next192 invalid covering list' => static function (TestRunner $t) use ($current192, $plan192): void {
        $bad = $current192();
        $bad['indexes'][0]['coveringColumns'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan192(['option_name'], 1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next192 invalid covering entry' => static function (TestRunner $t) use ($current192, $plan192): void {
        $bad = $current192();
        $bad['indexes'][0]['coveringColumns'][] = '';
        $t->throws(InvalidArgumentException::class, static fn () => $plan192(['option_name'], 1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next192 invalid indexes' => static function (TestRunner $t) use ($current192, $plan192): void {
        $bad = $current192();
        $bad['indexes'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan192(['option_name'], 1, 0, null, $bad));
    },
];

foreach (range(1, 12) as $case) {
    $tests['planner stat4 expression partial current source next192 repeated covering fence ' . $case] = static function (TestRunner $t) use ($plan192, $case): void {
        $plan = $plan192(['option_name', 'option_value'], 1 + ($case % 4), $case % 5);
        $t->same(count($plan['coveringColumnFence']['rowChecks']), count($plan['coveringColumnFence']['rowChecks']));
    };
}

return $tests;
