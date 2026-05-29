<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan;

$point142 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range142 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and142 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows142 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'kind' => 'core', 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'updated_at' => 10],
    ['rowid' => 2, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'active', 'updated_at' => 40],
    ['rowid' => 3, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'warm', 'updated_at' => 60],
    ['rowid' => 4, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'fresh', 'updated_at' => 70],
    ['rowid' => 5, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 30],
    ['rowid' => 6, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy', 'updated_at' => 50],
    ['rowid' => 7, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'shield', 'updated_at' => 80],
    ['rowid' => 8, 'blog_id' => 1, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'search', 'updated_at' => 20],
    ['rowid' => 9, 'blog_id' => 2, 'kind' => 'plugin', 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'network', 'updated_at' => 90],
    ['rowid' => 10, 'blog_id' => 1, 'kind' => 'theme', 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme', 'updated_at' => 100],
];

$preparedSource142 = static function (array $overrides = []) use ($rows142): array {
    return array_replace_recursive([
        'name' => 'prepared-before-current-source-next142',
        'schemaCookie' => 1420,
        'stat4Generation' => 80,
        'coveringColumns' => ['autoload', 'option_value', 'updated_at', 'rowid'],
        'rows' => array_slice($rows142(), 0, 6),
        'indexes' => [[
            'name' => 'idx_wp_options_blog_plugin_stat4_cover_next142',
            'rootPage' => 14201,
            'estimatedRows' => 180,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_alpha', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '3 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_plugin_stat4_cover_next142 ON wp_options(blog_id, option_name, autoload, option_value, updated_at, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
        ], [
            'name' => 'idx_wp_options_plain_next142',
            'rootPage' => 14202,
            'estimatedRows' => 2000,
            'sql' => 'CREATE INDEX idx_wp_options_plain_next142 ON wp_options(option_name)',
        ]],
    ], $overrides);
};

$currentSource142 = static function (array $overrides = []) use ($rows142): array {
    return array_replace_recursive([
        'name' => 'current-after-plugin-import-next142',
        'schemaCookie' => 1422,
        'stat4Generation' => 84,
        'coveringColumns' => ['autoload', 'option_value', 'updated_at', 'rowid'],
        'rows' => $rows142(),
        'indexes' => [[
            'name' => 'idx_wp_options_blog_plugin_stat4_cover_next142',
            'rootPage' => 14222,
            'estimatedRows' => 130,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_alpha', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
                ['neq' => '1 3 3', 'nlt' => '3 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '5 3 3', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
                ['neq' => '1 2 2', 'nlt' => '8 4 4', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_seo', 'yes']],
            ],
            'sql' => "CREATE INDEX idx_wp_options_blog_plugin_stat4_cover_next142 ON wp_options(blog_id, option_name, autoload, option_value, updated_at, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
        ], [
            'name' => 'idx_wp_options_plain_next142',
            'rootPage' => 14212,
            'estimatedRows' => 2000,
            'sql' => 'CREATE INDEX idx_wp_options_plain_next142 ON wp_options(option_name)',
        ]],
    ], $overrides);
};

$predicate142 = static fn (): array => $and142(
    $point142('kind', 'plugin'),
    $point142('blog_id', 1),
    $point142('autoload', 'yes'),
    $range142('option_name', '>=', 'plugin_alpha'),
    $range142('option_name', '<=', 'plugin_seo'),
);
$needed142 = ['autoload', 'option_value', 'updated_at', 'rowid'];
$order142 = [['column' => 'option_name']];
$plan142 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $order = null, ?array $needed = null): array => SQLitePlannerStat4PartialCoveringCurrentSourceNextPlan::materializePartialCoveringOrder(
    $prepared ?? $preparedSource142(),
    $current ?? $currentSource142(),
    $predicate ?? $predicate142(),
    $order ?? $order142,
    $needed ?? $needed142,
);

$updatedOrder142 = static fn (): array => $plan142(null, null, null, [['column' => 'updated_at', 'direction' => 'DESC']]);
$fresh142 = static function () use ($preparedSource142, $plan142): array {
    $source = $preparedSource142();

    return $plan142($source, $source);
};
$uncovered142 = static function () use ($currentSource142, $plan142): array {
    $current = $currentSource142([
        'coveringColumns' => ['autoload'],
        'indexes' => [[
            'name' => 'idx_wp_options_uncovered_next142',
            'rootPage' => 14231,
            'estimatedRows' => 130,
            'stat4Samples' => $currentSource142()['indexes'][0]['stat4Samples'],
            'sql' => "CREATE INDEX idx_wp_options_uncovered_next142 ON wp_options(blog_id, option_name, autoload) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
        ]],
    ]);

    return $plan142(null, $current);
};
$unproved142 = static function () use ($currentSource142, $plan142): array {
    $current = $currentSource142([
        'indexes' => [[
            'name' => 'idx_wp_options_unproved_next142',
            'rootPage' => 14241,
            'estimatedRows' => 130,
            'stat4Samples' => $currentSource142()['indexes'][0]['stat4Samples'],
            'sql' => "CREATE INDEX idx_wp_options_unproved_next142 ON wp_options(blog_id, option_name, autoload, option_value, updated_at, rowid) WHERE kind = 'plugin' AND option_name >= 'plugin_z'",
        ]],
    ]);

    return $plan142(null, $current);
};

$tests = [
    'planner stat4 partial covering current source next142 status ready' => static fn (TestRunner $t) => $t->same('stat4-partial-covering-current-source-next142-ready', $plan142()['status']),
    'planner stat4 partial covering current source next142 selects current' => static fn (TestRunner $t) => $t->same('current', $plan142()['selectedSource']),
    'planner stat4 partial covering current source next142 marks stale' => static fn (TestRunner $t) => $t->same(true, $plan142()['stalePreparedStatement']),
    'planner stat4 partial covering current source next142 reparses' => static fn (TestRunner $t) => $t->same(true, $plan142()['reprepareRequired']),
    'planner stat4 partial covering current source next142 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan142()['schemaCookieChanged']),
    'planner stat4 partial covering current source next142 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan142()['stat4GenerationChanged']),
    'planner stat4 partial covering current source next142 index changed' => static fn (TestRunner $t) => $t->same(true, $plan142()['indexSignatureChanged']),
    'planner stat4 partial covering current source next142 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_plugin_stat4_cover_next142', $plan142()['selectedPlan']['name']),
    'planner stat4 partial covering current source next142 selected root page' => static fn (TestRunner $t) => $t->same(14222, $plan142()['selectedPlan']['rootPage']),
    'planner stat4 partial covering current source next142 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan142()['selectedPlan']['next142Ready']),
    'planner stat4 partial covering current source next142 partial proof' => static fn (TestRunner $t) => $t->same(true, $plan142()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 partial covering current source next142 covering true' => static fn (TestRunner $t) => $t->same(true, $plan142()['selectedPlan']['covering']),
    'planner stat4 partial covering current source next142 uses stat4' => static fn (TestRunner $t) => $t->same(true, $plan142()['selectedPlan']['stat4Used']),
    'planner stat4 partial covering current source next142 no skip scan' => static fn (TestRunner $t) => $t->same(false, $plan142()['selectedPlan']['usesSkipScan']),
    'planner stat4 partial covering current source next142 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan142()['tableLookupElided']),
    'planner stat4 partial covering current source next142 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan142()['deferredSeekOpcode']),
    'planner stat4 partial covering current source next142 ordered row count' => static fn (TestRunner $t) => $t->same(6, $plan142()['selectedPlan']['next142OrderedRowCount']),
    'planner stat4 partial covering current source next142 ordered rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5, 7, 8], $plan142()['selectedPlan']['next142OrderedRowids']),
    'planner stat4 partial covering current source next142 covered rows rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5, 7, 8], array_column($plan142()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next142 covered rows keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_seo'], array_column($plan142()['coveredRows'], 'rangeKey')),
    'planner stat4 partial covering current source next142 duplicate key keeps rowid order' => static fn (TestRunner $t) => $t->same([3, 4], $plan142()['stat4AnchorBlocks'][1]['rowids']),
    'planner stat4 partial covering current source next142 duplicate key anchor count' => static fn (TestRunner $t) => $t->same(2, $plan142()['stat4AnchorBlocks'][1]['anchorCount']),
    'planner stat4 partial covering current source next142 first block key' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan142()['stat4AnchorBlocks'][0]['key']),
    'planner stat4 partial covering current source next142 first block next key' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan142()['stat4AnchorBlocks'][0]['nextKey']),
    'planner stat4 partial covering current source next142 last block eof' => static fn (TestRunner $t) => $t->same(null, $plan142()['stat4AnchorBlocks'][4]['nextKey']),
    'planner stat4 partial covering current source next142 block count' => static fn (TestRunner $t) => $t->same(5, $plan142()['stat4AnchorBlockCount']),
    'planner stat4 partial covering current source next142 selected block count' => static fn (TestRunner $t) => $t->same(5, $plan142()['selectedPlan']['next142Stat4BlockCount']),
    'planner stat4 partial covering current source next142 selected block keys' => static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_seo'], $plan142()['selectedPlan']['next142Stat4BlockKeys']),
    'planner stat4 partial covering current source next142 current next rowids' => static fn (TestRunner $t) => $t->same([3, 4, 5, 7, 8, null], array_map(static fn (array $pair): mixed => $pair['next']['rowid'] ?? null, $plan142()['currentNextRows'])),
    'planner stat4 partial covering current source next142 current first payload' => static fn (TestRunner $t) => $t->same('active', $plan142()['currentNextRows'][0]['current']['covering']['option_value']),
    'planner stat4 partial covering current source next142 duplicate next payload' => static fn (TestRunner $t) => $t->same('fresh', $plan142()['currentNextRows'][1]['next']['covering']['option_value']),
    'planner stat4 partial covering current source next142 excludes core' => static fn (TestRunner $t) => $t->same(false, in_array(1, array_column($plan142()['coveredRows'], 'rowid'), true)),
    'planner stat4 partial covering current source next142 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(6, array_column($plan142()['coveredRows'], 'rowid'), true)),
    'planner stat4 partial covering current source next142 excludes blog two' => static fn (TestRunner $t) => $t->same(false, in_array(9, array_column($plan142()['coveredRows'], 'rowid'), true)),
    'planner stat4 partial covering current source next142 excludes theme' => static fn (TestRunner $t) => $t->same(false, in_array(10, array_column($plan142()['coveredRows'], 'rowid'), true)),
    'planner stat4 partial covering current source next142 order mode covering' => static fn (TestRunner $t) => $t->same('covering-index-order', $plan142()['selectedPlan']['next142OrderByMode']),
    'planner stat4 partial covering current source next142 no right part sort' => static fn (TestRunner $t) => $t->same(false, $plan142()['tempBtreeForRightPartOrderBy']),
    'planner stat4 partial covering current source next142 selected no right sort' => static fn (TestRunner $t) => $t->same(false, $plan142()['selectedPlan']['next142BlockSortRequired']),
    'planner stat4 partial covering current source next142 next source covering' => static fn (TestRunner $t) => $t->same('partial-covering-stat4-current-next142', $plan142()['selectedPlan']['nextSource']),
    'planner stat4 partial covering current source next142 cursor open read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan142()['selectedPlan']['next142CursorProgram'][0]['opcode']),
    'planner stat4 partial covering current source next142 cursor seek stat4' => static fn (TestRunner $t) => $t->same('SeekStat4', $plan142()['selectedPlan']['next142CursorProgram'][1]['opcode']),
    'planner stat4 partial covering current source next142 cursor reads covering columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed142'], array_column(array_slice($plan142()['selectedPlan']['next142CursorProgram'], 2, 4), 'column')),
    'planner stat4 partial covering current source next142 cursor advances' => static fn (TestRunner $t) => $t->same('Next', $plan142()['selectedPlan']['next142CursorProgram'][6]['opcode']),
    'planner stat4 partial covering current source next142 fence cookie' => static fn (TestRunner $t) => $t->same(1422, $plan142()['currentSourceFence']['schemaCookie']),
    'planner stat4 partial covering current source next142 fence order' => static fn (TestRunner $t) => $t->same('option_name ASC', $plan142()['currentSourceFence']['next142OrderSignature']),
    'planner stat4 partial covering current source next142 fence row signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan142()['currentSourceFence']['next142RowStreamSignature'])),
    'planner stat4 partial covering current source next142 fence block signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan142()['currentSourceFence']['next142Stat4BlockSignature'])),
    'planner stat4 partial covering current source next142 detail ordered' => static fn (TestRunner $t) => $t->contains('ORDERED BY COVERING INDEX', $plan142()['detail']),
    'planner stat4 partial covering current source next142 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-partial-covering-current-source-next142', $plan142()['dependencies'], true)),
    'planner stat4 partial covering current source next142 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan142()['dependency_closure']),
    'planner stat4 partial covering current source next142 non overlap' => static fn (TestRunner $t) => $t->contains('ORDER block materialization', $plan142()['non_overlap']),
    'planner stat4 partial covering current source next142 updated order rowids' => static fn (TestRunner $t) => $t->same([7, 4, 3, 2, 5, 8], array_column($updatedOrder142()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next142 updated order mode' => static fn (TestRunner $t) => $t->same('partial-covering-right-part-sort', $updatedOrder142()['selectedPlan']['next142OrderByMode']),
    'planner stat4 partial covering current source next142 updated order needs temp btree' => static fn (TestRunner $t) => $t->same(true, $updatedOrder142()['tempBtreeForRightPartOrderBy']),
    'planner stat4 partial covering current source next142 updated order cursor sorter insert' => static fn (TestRunner $t) => $t->same('SorterInsert', $updatedOrder142()['selectedPlan']['next142CursorProgram'][6]['opcode']),
    'planner stat4 partial covering current source next142 updated order cursor sorter next' => static fn (TestRunner $t) => $t->same('SorterNext', $updatedOrder142()['selectedPlan']['next142CursorProgram'][7]['opcode']),
    'planner stat4 partial covering current source next142 updated order block keys' => static fn (TestRunner $t) => $t->same([80, 70, 60, 40, 30, 20], $updatedOrder142()['selectedPlan']['next142Stat4BlockKeys']),
    'planner stat4 partial covering current source next142 updated order fence' => static fn (TestRunner $t) => $t->same('updated_at DESC', $updatedOrder142()['currentSourceFence']['next142OrderSignature']),
    'planner stat4 partial covering current source next142 updated order detail sort' => static fn (TestRunner $t) => $t->contains('USE TEMP B-TREE FOR RIGHT PART OF ORDER BY', $updatedOrder142()['detail']),
    'planner stat4 partial covering current source next142 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh142()['selectedSource']),
    'planner stat4 partial covering current source next142 fresh rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5], array_column($fresh142()['coveredRows'], 'rowid')),
    'planner stat4 partial covering current source next142 uncovered falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncovered142()['status']),
    'planner stat4 partial covering current source next142 uncovered deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $uncovered142()['deferredSeekOpcode']),
    'planner stat4 partial covering current source next142 unproved falls back' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved142()['status']),
    'planner stat4 partial covering current source next142 unproved row stream empty' => static fn (TestRunner $t) => $t->same([], $unproved142()['coveredRows']),
    'planner stat4 partial covering current source next142 validates order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan142(null, null, null, [['column' => 'option_name', 'direction' => 'SIDEWAYS']])),
    'planner stat4 partial covering current source next142 validates order column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan142(null, null, null, [['column' => '']])),
    'planner stat4 partial covering current source next142 validates source rows' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan142(null, $currentSource142(['rows' => ['bad' => []]]))),
];

$GLOBALS['needed142'] = $needed142;

return $tests;
