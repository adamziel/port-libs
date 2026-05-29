<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan;

$point131 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range131 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between131 = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and131 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows131 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'core_home', 'option_value' => 'https://example.test'],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1'],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'a:2'],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'a:3'],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'a:4'],
    ['rowid' => 6, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'a:5'],
    ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme'],
];

$currentRows131 = static function () use ($rows131): array {
    $rows = $rows131();
    $rows[] = ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'a:6'];
    $rows[] = ['rowid' => 9, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zeta', 'option_value' => 'a:7'];

    return $rows;
};

$preparedSource131 = static function (array $overrides = []) use ($rows131): array {
    return $overrides + [
    'name' => 'prepared-main.wp_options@cookie1310',
    'schemaCookie' => 1310,
    'stat4Generation' => 41,
    'rows' => $rows131(),
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_plugin_covering_next131',
        'rootPage' => 13101,
        'estimatedRows' => 60,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
            ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_plugin_covering_next131 ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_full_name_next131',
        'rootPage' => 13102,
        'estimatedRows' => 200,
        'sql' => 'CREATE INDEX idx_wp_options_full_name_next131 ON wp_options(option_name)',
    ]],
    ];
};

$currentSource131 = static function (array $overrides = []) use ($currentRows131): array {
    return $overrides + [
    'name' => 'current-main.wp_options@cookie1311',
    'schemaCookie' => 1311,
    'stat4Generation' => 42,
    'rows' => $currentRows131(),
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_plugin_covering_next131',
        'rootPage' => 13111,
        'estimatedRows' => 40,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
            ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
            ['neq' => '1 1 1', 'nlt' => '3 3 3', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_security']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_plugin_covering_next131 ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_full_name_next131',
        'rootPage' => 13112,
        'estimatedRows' => 200,
        'sql' => 'CREATE INDEX idx_wp_options_full_name_next131 ON wp_options(option_name)',
    ]],
    ];
};

$predicate131 = static fn (): array => $and131(
    $point131('blog_id', 1),
    $point131('autoload', 'yes'),
    $range131('option_name', '>=', 'plugin_'),
    $range131('option_name', '<', 'plugin_z'),
);
$needed131 = ['option_name', 'option_value', 'rowid'];
$plan131 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan::materializeNext131(
    $prepared ?? $preparedSource131(),
    $current ?? $currentSource131(),
    $predicate ?? $predicate131(),
    $needed ?? $needed131,
);

$fresh131 = static function () use ($preparedSource131, $plan131): array {
    $source = $preparedSource131();

    return $plan131($source, $source);
};

$missingCovering131 = static fn (): array => $plan131(null, $currentSource131([
    'indexes' => [[
        'name' => 'idx_wp_options_blog_autoload_plugin_missing_payload_next131',
        'rootPage' => 13121,
        'estimatedRows' => 40,
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_plugin_missing_payload_next131 ON wp_options(blog_id, autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ]],
]));
$unprovedPartial131 = static fn (): array => $plan131(null, $currentSource131([
    'indexes' => [[
        'name' => 'idx_wp_options_strict_plugin_covering_next131',
        'rootPage' => 13131,
        'estimatedRows' => 20,
        'sql' => "CREATE INDEX idx_wp_options_strict_plugin_covering_next131 ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_mu'",
    ]],
]));
$betweenPlan131 = static fn (): array => $plan131(null, null, $and131($point131('blog_id', 1), $point131('autoload', 'yes'), $between131('option_name', 'plugin_cache', 'plugin_security')));
$openLower131 = static fn (): array => $plan131(null, null, $and131($point131('blog_id', 1), $point131('autoload', 'yes'), $range131('option_name', '>', 'plugin_cache'), $range131('option_name', '<', 'plugin_security')));

$tests = [
    'planner covering partial range current source next131 status ready' => static fn (TestRunner $t) => $t->same('covering-partial-range-current-source-ready', $plan131()['status']),
    'planner covering partial range current source next131 selects current' => static fn (TestRunner $t) => $t->same('current', $plan131()['selectedSource']),
    'planner covering partial range current source next131 marks stale' => static fn (TestRunner $t) => $t->same(true, $plan131()['stalePreparedStatement']),
    'planner covering partial range current source next131 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan131()['reprepareRequired']),
    'planner covering partial range current source next131 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan131()['schemaCookieChanged']),
    'planner covering partial range current source next131 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan131()['stat4GenerationChanged']),
    'planner covering partial range current source next131 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan131()['indexSignatureChanged']),
    'planner covering partial range current source next131 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_plugin_covering_next131', $plan131()['selectedPlan']['name']),
    'planner covering partial range current source next131 selected root' => static fn (TestRunner $t) => $t->same(13111, $plan131()['selectedPlan']['rootPage']),
    'planner covering partial range current source next131 prepared root' => static fn (TestRunner $t) => $t->same(13101, $plan131()['preparedSource']['rootPage']),
    'planner covering partial range current source next131 current root' => static fn (TestRunner $t) => $t->same(13111, $plan131()['currentSource']['rootPage']),
    'planner covering partial range current source next131 partial true' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['partial']),
    'planner covering partial range current source next131 covering true' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['covering']),
    'planner covering partial range current source next131 no skip scan' => static fn (TestRunner $t) => $t->same(false, $plan131()['selectedPlan']['usesSkipScan']),
    'planner covering partial range current source next131 range column' => static fn (TestRunner $t) => $t->same('option_name', $plan131()['selectedPlan']['rangeColumn']),
    'planner covering partial range current source next131 range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan131()['selectedPlan']['rangeLower']),
    'planner covering partial range current source next131 range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan131()['selectedPlan']['rangeUpper']),
    'planner covering partial range current source next131 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['lowerInclusive']),
    'planner covering partial range current source next131 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan131()['selectedPlan']['upperInclusive']),
    'planner covering partial range current source next131 used columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name'], $plan131()['selectedPlan']['usedColumns']),
    'planner covering partial range current source next131 equality prefix' => static fn (TestRunner $t) => $t->same(2, $plan131()['selectedPlan']['equalityPrefix']),
    'planner covering partial range current source next131 no residual columns' => static fn (TestRunner $t) => $t->same([], $plan131()['selectedPlan']['residualRangeColumns']),
    'planner covering partial range current source next131 table lookup false' => static fn (TestRunner $t) => $t->same(false, $plan131()['selectedPlan']['tableLookupRequired']),
    'planner covering partial range current source next131 estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan131()['selectedPlan']['estimatedRows']),
    'planner covering partial range current source next131 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['stat4Used']),
    'planner covering partial range current source next131 stat4 matched count' => static fn (TestRunner $t) => $t->same(4, $plan131()['selectedPlan']['stat4MatchedSamples']),
    'planner covering partial range current source next131 covered rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 8], array_column($plan131()['coveredRows'], 'rowid')),
    'planner covering partial range current source next131 covered keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security'], array_column($plan131()['coveredRows'], 'rangeKey')),
    'planner covering partial range current source next131 next rowid chain' => static fn (TestRunner $t) => $t->same([3, 4, 8, null], array_column($plan131()['coveredRows'], 'nextRowid')),
    'planner covering partial range current source next131 payload from index' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_security', 'option_value' => 'a:6', 'rowid' => 8], $plan131()['coveredRows'][3]['covering']),
    'planner covering partial range current source next131 current fence cookie' => static fn (TestRunner $t) => $t->same(1311, $plan131()['currentSourceFence']['schemaCookie']),
    'planner covering partial range current source next131 current fence stat4' => static fn (TestRunner $t) => $t->same(42, $plan131()['currentSourceFence']['stat4Generation']),
    'planner covering partial range current source next131 needed signature' => static fn (TestRunner $t) => $t->same('option_name,option_value,rowid', $plan131()['currentSourceFence']['neededColumnSignature']),
    'planner covering partial range current source next131 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan131()['currentSourceFence']['predicateSignature'])),
    'planner covering partial range current source next131 index signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan131()['currentSourceFence']['indexSignature'])),
    'planner covering partial range current source next131 cursor opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan131()['selectedPlan']['cursorProgram'][0]['opcode']),
    'planner covering partial range current source next131 cursor seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan131()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner covering partial range current source next131 cursor stop ge' => static fn (TestRunner $t) => $t->same('IdxGE', $plan131()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner covering partial range current source next131 cursor reads payload' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'rowid'], array_column(array_slice($plan131()['selectedPlan']['cursorProgram'], 3, 3), 'column')),
    'planner covering partial range current source next131 cursor advances' => static fn (TestRunner $t) => $t->same('Next', $plan131()['selectedPlan']['cursorProgram'][6]['opcode']),
    'planner covering partial range current source next131 detail says reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING PARTIAL RANGE USING current-main.wp_options@cookie1311', $plan131()['detail']),
    'planner covering partial range current source next131 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-covering-partial-range-current-source-next131', $plan131()['dependencies'], true)),
    'planner covering partial range current source next131 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan131()['dependency_closure']),
    'planner covering partial range current source next131 non overlap' => static fn (TestRunner $t) => $t->contains('ordinary covering partial range current-source materialization without skip-scan', $plan131()['non_overlap']),
    'planner covering partial range current source next131 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh131()['selectedSource']),
    'planner covering partial range current source next131 fresh not stale' => static fn (TestRunner $t) => $t->same(false, $fresh131()['stalePreparedStatement']),
    'planner covering partial range current source next131 fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], array_column($fresh131()['coveredRows'], 'rowid')),
    'planner covering partial range current source next131 missing covering requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingCovering131()['status']),
    'planner covering partial range current source next131 missing covering table lookup' => static fn (TestRunner $t) => $t->same(true, $missingCovering131()['selectedPlan']['tableLookupRequired']),
    'planner covering partial range current source next131 unproved partial requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unprovedPartial131()['status']),
    'planner covering partial range current source next131 unproved partial has no selected rows' => static fn (TestRunner $t) => $t->same([], $unprovedPartial131()['coveredRows']),
    'planner covering partial range current source next131 between stop gt' => static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan131()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner covering partial range current source next131 between includes upper boundary' => static fn (TestRunner $t) => $t->same([3, 4, 8], array_column($betweenPlan131()['coveredRows'], 'rowid')),
    'planner covering partial range current source next131 open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLower131()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner covering partial range current source next131 open lower excludes boundary' => static fn (TestRunner $t) => $t->same([4], array_column($openLower131()['coveredRows'], 'rowid')),
    'planner covering partial range current source next131 validates needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(null, null, null, [''])),
    'planner covering partial range current source next131 validates source indexes list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => 1, 'indexes' => ['bad']])),
    'planner covering partial range current source next131 validates schema cookie' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(['name' => 'bad', 'schemaCookie' => -1, 'stat4Generation' => 1, 'indexes' => []])),
    'planner covering partial range current source next131 validates stat4 generation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => -1, 'indexes' => []])),
];

return $tests;
