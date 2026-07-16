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
        'name' => 'idx_wp_options_blog_autoload_plugin_covering_current_source',
        'rootPage' => 13101,
        'estimatedRows' => 60,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
            ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_plugin_covering_current_source ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_full_name_current_source',
        'rootPage' => 13102,
        'estimatedRows' => 200,
        'sql' => 'CREATE INDEX idx_wp_options_full_name_current_source ON wp_options(option_name)',
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
        'name' => 'idx_wp_options_blog_autoload_plugin_covering_current_source',
        'rootPage' => 13111,
        'estimatedRows' => 40,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
            ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
            ['neq' => '1 1 1', 'nlt' => '3 3 3', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_security']],
        ],
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_plugin_covering_current_source ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ], [
        'name' => 'idx_wp_options_full_name_current_source',
        'rootPage' => 13112,
        'estimatedRows' => 200,
        'sql' => 'CREATE INDEX idx_wp_options_full_name_current_source ON wp_options(option_name)',
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
$plan131 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan::materialize(
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
        'name' => 'idx_wp_options_blog_autoload_plugin_missing_payload_current_source',
        'rootPage' => 13121,
        'estimatedRows' => 40,
        'sql' => "CREATE INDEX idx_wp_options_blog_autoload_plugin_missing_payload_current_source ON wp_options(blog_id, autoload, option_name) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ]],
]));
$unprovedPartial131 = static fn (): array => $plan131(null, $currentSource131([
    'indexes' => [[
        'name' => 'idx_wp_options_strict_plugin_covering_current_source',
        'rootPage' => 13131,
        'estimatedRows' => 20,
        'sql' => "CREATE INDEX idx_wp_options_strict_plugin_covering_current_source ON wp_options(blog_id, autoload, option_name, option_value, rowid) WHERE autoload = 'yes' AND option_name >= 'plugin_mu'",
    ]],
]));
$betweenPlan131 = static fn (): array => $plan131(null, null, $and131($point131('blog_id', 1), $point131('autoload', 'yes'), $between131('option_name', 'plugin_cache', 'plugin_security')));
$openLower131 = static fn (): array => $plan131(null, null, $and131($point131('blog_id', 1), $point131('autoload', 'yes'), $range131('option_name', '>', 'plugin_cache'), $range131('option_name', '<', 'plugin_security')));

$tests = [
    'planner covering partial range current source status ready' => static fn (TestRunner $t) => $t->same('covering-partial-range-current-source-ready', $plan131()['status']),
    'planner covering partial range current source selects current' => static fn (TestRunner $t) => $t->same('current', $plan131()['selectedSource']),
    'planner covering partial range current source marks stale' => static fn (TestRunner $t) => $t->same(true, $plan131()['stalePreparedStatement']),
    'planner covering partial range current source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan131()['reprepareRequired']),
    'planner covering partial range current source schema changed' => static fn (TestRunner $t) => $t->same(true, $plan131()['schemaCookieChanged']),
    'planner covering partial range current source stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan131()['stat4GenerationChanged']),
    'planner covering partial range current source signature changed' => static fn (TestRunner $t) => $t->same(true, $plan131()['indexSignatureChanged']),
    'planner covering partial range current source selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_plugin_covering_current_source', $plan131()['selectedPlan']['name']),
    'planner covering partial range current source selected root' => static fn (TestRunner $t) => $t->same(13111, $plan131()['selectedPlan']['rootPage']),
    'planner covering partial range current source prepared root' => static fn (TestRunner $t) => $t->same(13101, $plan131()['preparedSource']['rootPage']),
    'planner covering partial range current source current root' => static fn (TestRunner $t) => $t->same(13111, $plan131()['currentSource']['rootPage']),
    'planner covering partial range current source partial true' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['partial']),
    'planner covering partial range current source covering true' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['covering']),
    'planner covering partial range current source no skip scan' => static fn (TestRunner $t) => $t->same(false, $plan131()['selectedPlan']['usesSkipScan']),
    'planner covering partial range current source range column' => static fn (TestRunner $t) => $t->same('option_name', $plan131()['selectedPlan']['rangeColumn']),
    'planner covering partial range current source range lower' => static fn (TestRunner $t) => $t->same('plugin_', $plan131()['selectedPlan']['rangeLower']),
    'planner covering partial range current source range upper' => static fn (TestRunner $t) => $t->same('plugin_z', $plan131()['selectedPlan']['rangeUpper']),
    'planner covering partial range current source lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['lowerInclusive']),
    'planner covering partial range current source upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan131()['selectedPlan']['upperInclusive']),
    'planner covering partial range current source used columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name'], $plan131()['selectedPlan']['usedColumns']),
    'planner covering partial range current source equality prefix' => static fn (TestRunner $t) => $t->same(2, $plan131()['selectedPlan']['equalityPrefix']),
    'planner covering partial range current source no residual columns' => static fn (TestRunner $t) => $t->same([], $plan131()['selectedPlan']['residualRangeColumns']),
    'planner covering partial range current source table lookup false' => static fn (TestRunner $t) => $t->same(false, $plan131()['selectedPlan']['tableLookupRequired']),
    'planner covering partial range current source estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan131()['selectedPlan']['estimatedRows']),
    'planner covering partial range current source stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan131()['selectedPlan']['stat4Used']),
    'planner covering partial range current source stat4 matched count' => static fn (TestRunner $t) => $t->same(4, $plan131()['selectedPlan']['stat4MatchedSamples']),
    'planner covering partial range current source covered rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 8], array_column($plan131()['coveredRows'], 'rowid')),
    'planner covering partial range current source covered keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security'], array_column($plan131()['coveredRows'], 'rangeKey')),
    'planner covering partial range current source next rowid chain' => static fn (TestRunner $t) => $t->same([3, 4, 8, null], array_column($plan131()['coveredRows'], 'nextRowid')),
    'planner covering partial range current source payload from index' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_security', 'option_value' => 'a:6', 'rowid' => 8], $plan131()['coveredRows'][3]['covering']),
    'planner covering partial range current source current fence cookie' => static fn (TestRunner $t) => $t->same(1311, $plan131()['currentSourceFence']['schemaCookie']),
    'planner covering partial range current source current fence stat4' => static fn (TestRunner $t) => $t->same(42, $plan131()['currentSourceFence']['stat4Generation']),
    'planner covering partial range current source needed signature' => static fn (TestRunner $t) => $t->same('option_name,option_value,rowid', $plan131()['currentSourceFence']['neededColumnSignature']),
    'planner covering partial range current source predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan131()['currentSourceFence']['predicateSignature'])),
    'planner covering partial range current source index signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan131()['currentSourceFence']['indexSignature'])),
    'planner covering partial range current source cursor opens index' => static fn (TestRunner $t) => $t->same('OpenRead', $plan131()['selectedPlan']['cursorProgram'][0]['opcode']),
    'planner covering partial range current source cursor seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan131()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner covering partial range current source cursor stop ge' => static fn (TestRunner $t) => $t->same('IdxGE', $plan131()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner covering partial range current source cursor reads payload' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'rowid'], array_column(array_slice($plan131()['selectedPlan']['cursorProgram'], 3, 3), 'column')),
    'planner covering partial range current source cursor advances' => static fn (TestRunner $t) => $t->same('Next', $plan131()['selectedPlan']['cursorProgram'][6]['opcode']),
    'planner covering partial range current source detail says reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING PARTIAL RANGE USING current-main.wp_options@cookie1311', $plan131()['detail']),
    'planner covering partial range current source dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-covering-partial-range-current-source', $plan131()['dependencies'], true)),
    'planner covering partial range current source dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan131()['dependency_closure']),
    'planner covering partial range current source non overlap' => static fn (TestRunner $t) => $t->contains('ordinary covering partial range current-source materialization without skip-scan', $plan131()['non_overlap']),
    'planner covering partial range current source fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh131()['selectedSource']),
    'planner covering partial range current source fresh not stale' => static fn (TestRunner $t) => $t->same(false, $fresh131()['stalePreparedStatement']),
    'planner covering partial range current source fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4], array_column($fresh131()['coveredRows'], 'rowid')),
    'planner covering partial range current source missing covering requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $missingCovering131()['status']),
    'planner covering partial range current source missing covering table lookup' => static fn (TestRunner $t) => $t->same(true, $missingCovering131()['selectedPlan']['tableLookupRequired']),
    'planner covering partial range current source unproved partial requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unprovedPartial131()['status']),
    'planner covering partial range current source unproved partial has no selected rows' => static fn (TestRunner $t) => $t->same([], $unprovedPartial131()['coveredRows']),
    'planner covering partial range current source between stop gt' => static fn (TestRunner $t) => $t->same('IdxGT', $betweenPlan131()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner covering partial range current source between includes upper boundary' => static fn (TestRunner $t) => $t->same([3, 4, 8], array_column($betweenPlan131()['coveredRows'], 'rowid')),
    'planner covering partial range current source open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLower131()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner covering partial range current source open lower excludes boundary' => static fn (TestRunner $t) => $t->same([4], array_column($openLower131()['coveredRows'], 'rowid')),
    'planner covering partial range current source validates needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(null, null, null, [''])),
    'planner covering partial range current source validates source indexes list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => 1, 'indexes' => ['bad']])),
    'planner covering partial range current source validates schema cookie' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(['name' => 'bad', 'schemaCookie' => -1, 'stat4Generation' => 1, 'indexes' => []])),
    'planner covering partial range current source validates stat4 generation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan131(null, ['name' => 'bad', 'schemaCookie' => 1, 'stat4Generation' => -1, 'indexes' => []])),
];

return $tests;
