<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan;

$point135 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range135 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between135 = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and135 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows135 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'kind' => 'core', 'autoload' => 'yes', 'option_name' => 'home', 'option_value' => 'https://example.test'],
    ['rowid' => 2, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_akismet', 'option_value' => 'active'],
    ['rowid' => 3, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'warm'],
    ['rowid' => 4, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_editor', 'option_value' => 'blocks'],
    ['rowid' => 5, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
    ['rowid' => 6, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy'],
    ['rowid' => 7, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'shield'],
    ['rowid' => 8, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'search'],
    ['rowid' => 9, 'blog_id' => 2, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network'],
    ['rowid' => 10, 'blog_id' => 1, 'kind' => 'theme', 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme'],
];

$preparedSource135 = static function (array $overrides = []) use ($rows135): array {
    return array_replace_recursive([
        'name' => 'prepared-before-current-source-next135',
        'schemaCookie' => 1350,
        'stat4Generation' => 70,
        'coveringColumns' => ['autoload', 'option_value', 'rowid'],
        'rows' => array_slice($rows135(), 0, 6),
        'indexes' => [[
            'name' => 'idx_wp_options_blog_plugin_stat4_cover_next135',
            'rootPage' => 13501,
            'estimatedRows' => 140,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
                ['neq' => '1 3 3', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_plugin_stat4_cover_next135 ON wp_options(blog_id, option_name, autoload, option_value, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
        ], [
            'name' => 'idx_wp_options_name_plain_next135',
            'rootPage' => 13502,
            'estimatedRows' => 1000,
            'sql' => 'CREATE INDEX idx_wp_options_name_plain_next135 ON wp_options(option_name)',
        ]],
    ], $overrides);
};

$currentSource135 = static function (array $overrides = []) use ($rows135): array {
    return array_replace_recursive([
        'name' => 'current-after-plugin-import-next135',
        'schemaCookie' => 1351,
        'stat4Generation' => 71,
        'coveringColumns' => ['autoload', 'option_value', 'rowid'],
        'rows' => $rows135(),
        'indexes' => [[
            'name' => 'idx_wp_options_blog_plugin_stat4_cover_next135',
            'rootPage' => 13511,
            'estimatedRows' => 120,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
                ['neq' => '1 3 3', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_editor', 'yes']],
                ['neq' => '1 4 4', 'nlt' => '6 6 6', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_forms', 'yes']],
                ['neq' => '1 3 3', 'nlt' => '10 10 10', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_security', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '13 13 13', 'ndlt' => '5 5 5', 'sample' => [1, 'plugin_seo', 'yes']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_plugin_stat4_cover_next135 ON wp_options(blog_id, option_name, autoload, option_value, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
        ], [
            'name' => 'idx_wp_options_name_plain_next135',
            'rootPage' => 13512,
            'estimatedRows' => 1000,
            'sql' => 'CREATE INDEX idx_wp_options_name_plain_next135 ON wp_options(option_name)',
        ]],
    ], $overrides);
};

$predicate135 = static fn (): array => $and135(
    $point135('kind', 'plugin'),
    $point135('blog_id', 1),
    $point135('autoload', 'yes'),
    $range135('option_name', '>=', 'plugin_cache'),
    $range135('option_name', '<=', 'plugin_seo'),
);
$order135 = [['column' => 'option_name']];
$needed135 = ['autoload', 'option_value', 'rowid'];
$plan135 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan::materializeNext135(
    $prepared ?? $preparedSource135(),
    $current ?? $currentSource135(),
    $predicate ?? $predicate135(),
    $order135,
    $needed ?? $needed135,
);

$fresh135 = static function () use ($preparedSource135, $plan135): array {
    $source = $preparedSource135();

    return $plan135($source, $source);
};
$uncovered135 = static fn (): array => $plan135(null, $currentSource135([
    'coveringColumns' => ['autoload'],
    'indexes' => [[
        'name' => 'idx_wp_options_blog_plugin_uncovered_next135',
        'rootPage' => 13521,
        'estimatedRows' => 120,
        'stat4Samples' => $currentSource135()['indexes'][0]['stat4Samples'],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_uncovered_next135 ON wp_options(blog_id, option_name, autoload) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ]],
]));
$unproved135 = static fn (): array => $plan135(null, $currentSource135([
    'indexes' => [[
        'name' => 'idx_wp_options_blog_plugin_unproved_next135',
        'rootPage' => 13531,
        'estimatedRows' => 120,
        'stat4Samples' => $currentSource135()['indexes'][0]['stat4Samples'],
        'sql' => "CREATE INDEX idx_wp_options_blog_plugin_unproved_next135 ON wp_options(blog_id, option_name, autoload, option_value, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_mu'",
    ]],
]));
$between135Plan = static fn (): array => $plan135(null, null, $and135($point135('kind', 'plugin'), $point135('blog_id', 1), $point135('autoload', 'yes'), $between135('option_name', 'plugin_editor', 'plugin_security')));
$openLower135Plan = static fn (): array => $plan135(null, null, $and135($point135('kind', 'plugin'), $point135('blog_id', 1), $point135('autoload', 'yes'), $range135('option_name', '>', 'plugin_cache'), $range135('option_name', '<', 'plugin_security')));

$tests = [
    'planner stat4 partial covering current source next135 status ready' => static fn (TestRunner $t) => $t->same('stat4-partial-covering-current-source-next135-ready', $plan135()['status']),
    'planner stat4 partial covering current source next135 selects current' => static fn (TestRunner $t) => $t->same('current', $plan135()['selectedSource']),
    'planner stat4 partial covering current source next135 marks stale' => static fn (TestRunner $t) => $t->same(true, $plan135()['stalePreparedStatement']),
    'planner stat4 partial covering current source next135 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan135()['reprepareRequired']),
    'planner stat4 partial covering current source next135 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan135()['schemaCookieChanged']),
    'planner stat4 partial covering current source next135 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan135()['stat4GenerationChanged']),
    'planner stat4 partial covering current source next135 index signature changed' => static fn (TestRunner $t) => $t->same(true, $plan135()['indexSignatureChanged']),
    'planner stat4 partial covering current source next135 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_plugin_stat4_cover_next135', $plan135()['selectedPlan']['name']),
    'planner stat4 partial covering current source next135 selected root' => static fn (TestRunner $t) => $t->same(13511, $plan135()['selectedPlan']['rootPage']),
    'planner stat4 partial covering current source next135 prepared root' => static fn (TestRunner $t) => $t->same(13501, $plan135()['preparedSource']['rootPage']),
    'planner stat4 partial covering current source next135 current root' => static fn (TestRunner $t) => $t->same(13511, $plan135()['currentSource']['rootPage']),
    'planner stat4 partial covering current source next135 selected covering' => static fn (TestRunner $t) => $t->same(true, $plan135()['selectedPlan']['covering']),
    'planner stat4 partial covering current source next135 selected partial' => static fn (TestRunner $t) => $t->same(true, $plan135()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 partial covering current source next135 uses stat4' => static fn (TestRunner $t) => $t->same(true, $plan135()['selectedPlan']['stat4Used']),
    'planner stat4 partial covering current source next135 order from partial' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan135()['selectedPlan']['orderByMode']),
    'planner stat4 partial covering current source next135 no table lookup' => static fn (TestRunner $t) => $t->same(true, $plan135()['tableLookupElided']),
    'planner stat4 partial covering current source next135 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan135()['deferredSeekOpcode']),
    'planner stat4 partial covering current source next135 covered row count' => static fn (TestRunner $t) => $t->same(5, $plan135()['selectedPlan']['coveredRowCount']),
    'planner stat4 partial covering current source next135 covered rowids' => static fn (TestRunner $t) => $t->same([3, 4, 5, 7, 8], array_column($plan135()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next135 covered keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_editor', 'plugin_forms', 'plugin_security', 'plugin_seo'], array_column($plan135()['coveredRows'], 'rangeKey')),
    'planner stat4 partial covering current source next135 anchor flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($plan135()['coveredRows'], 'stat4Anchor')),
    'planner stat4 partial covering current source next135 payload from index' => static fn (TestRunner $t) => $t->same(['autoload' => 'yes', 'option_value' => 'shield', 'rowid' => 7], $plan135()['coveredRows'][3]['covering']),
    'planner stat4 partial covering current source next135 excludes wrong autoload' => static fn (TestRunner $t) => $t->same(false, in_array(6, array_column($plan135()['coveredRows'], 'rowid'), true)),
    'planner stat4 partial covering current source next135 excludes wrong blog' => static fn (TestRunner $t) => $t->same(false, in_array(9, array_column($plan135()['coveredRows'], 'rowid'), true)),
    'planner stat4 partial covering current source next135 current next rowids' => static fn (TestRunner $t) => $t->same([4, 5, 7, 8, null], array_map(static fn (array $pair): mixed => $pair['next']['rowid'] ?? null, $plan135()['currentNextRows'])),
    'planner stat4 partial covering current source next135 current first rowid' => static fn (TestRunner $t) => $t->same(3, $plan135()['currentNextRows'][0]['current']['rowid']),
    'planner stat4 partial covering current source next135 current fence cookie' => static fn (TestRunner $t) => $t->same(1351, $plan135()['currentSourceFence']['schemaCookie']),
    'planner stat4 partial covering current source next135 current fence stat4' => static fn (TestRunner $t) => $t->same(71, $plan135()['currentSourceFence']['stat4Generation']),
    'planner stat4 partial covering current source next135 order signature' => static fn (TestRunner $t) => $t->same('option_name ASC', $plan135()['currentSourceFence']['orderSignature']),
    'planner stat4 partial covering current source next135 covering signature' => static fn (TestRunner $t) => $t->same('autoload,option_value,rowid', $plan135()['currentSourceFence']['coveringSignature']),
    'planner stat4 partial covering current source next135 predicate signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan135()['currentSourceFence']['predicateSignature'])),
    'planner stat4 partial covering current source next135 row stream signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan135()['currentSourceFence']['rowStreamSignature'])),
    'planner stat4 partial covering current source next135 range lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan135()['selectedPlan']['rangeLower']),
    'planner stat4 partial covering current source next135 range upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan135()['selectedPlan']['rangeUpper']),
    'planner stat4 partial covering current source next135 upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan135()['selectedPlan']['upperInclusive']),
    'planner stat4 partial covering current source next135 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan135()['selectedPlan']['cursorProgram'][0]['opcode']),
    'planner stat4 partial covering current source next135 cursor seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan135()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner stat4 partial covering current source next135 cursor stop gt inclusive' => static fn (TestRunner $t) => $t->same('IdxGT', $plan135()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner stat4 partial covering current source next135 cursor reads payload columns' => static fn (TestRunner $t) => $t->same(['autoload', 'option_value', 'rowid'], array_column(array_slice($plan135()['selectedPlan']['cursorProgram'], 3, 3), 'column')),
    'planner stat4 partial covering current source next135 cursor advances' => static fn (TestRunner $t) => $t->same('Next', $plan135()['selectedPlan']['cursorProgram'][6]['opcode']),
    'planner stat4 partial covering current source next135 detail names current row stream' => static fn (TestRunner $t) => $t->contains('STAT4 PARTIAL COVERING CURRENT SOURCE ROW STREAM current-after-plugin-import-next135', $plan135()['detail']),
    'planner stat4 partial covering current source next135 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-partial-covering-current-source-next135', $plan135()['dependencies'], true)),
    'planner stat4 partial covering current source next135 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan135()['dependency_closure']),
    'planner stat4 partial covering current source next135 non overlap' => static fn (TestRunner $t) => $t->contains('row stream admission', $plan135()['non_overlap']),
    'planner stat4 partial covering current source next135 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh135()['selectedSource']),
    'planner stat4 partial covering current source next135 fresh not stale' => static fn (TestRunner $t) => $t->same(false, $fresh135()['stalePreparedStatement']),
    'planner stat4 partial covering current source next135 fresh rowids' => static fn (TestRunner $t) => $t->same([3, 4, 5], array_column($fresh135()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next135 uncovered requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncovered135()['status']),
    'planner stat4 partial covering current source next135 uncovered defers seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $uncovered135()['deferredSeekOpcode']),
    'planner stat4 partial covering current source next135 unproved partial requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved135()['status']),
    'planner stat4 partial covering current source next135 unproved has no rows' => static fn (TestRunner $t) => $t->same([], $unproved135()['coveredRows']),
    'planner stat4 partial covering current source next135 between rowids' => static fn (TestRunner $t) => $t->same([4, 5, 7], array_column($between135Plan()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next135 between lower inclusive' => static fn (TestRunner $t) => $t->same(true, $between135Plan()['selectedPlan']['lowerInclusive']),
    'planner stat4 partial covering current source next135 between upper inclusive' => static fn (TestRunner $t) => $t->same(true, $between135Plan()['selectedPlan']['upperInclusive']),
    'planner stat4 partial covering current source next135 open lower rowids' => static fn (TestRunner $t) => $t->same([4, 5], array_column($openLower135Plan()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next135 open lower seek gt' => static fn (TestRunner $t) => $t->same('SeekGT', $openLower135Plan()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner stat4 partial covering current source next135 open upper stop ge' => static fn (TestRunner $t) => $t->same('IdxGE', $openLower135Plan()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner stat4 partial covering current source next135 validates empty needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan135(null, null, null, [])),
    'planner stat4 partial covering current source next135 validates needed column names' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan135(null, null, null, ['autoload', ''])),
    'planner stat4 partial covering current source next135 validates rows list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan135(null, $currentSource135(['rows' => ['bad' => []]]))),
    'planner stat4 partial covering current source next135 validates current cookie' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan135(null, $currentSource135(['schemaCookie' => -1]))),
    'planner stat4 partial covering current source next135 validates current stat4 generation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan135(null, $currentSource135(['stat4Generation' => -1]))),
];

return $tests;
