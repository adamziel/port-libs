<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan;

$point138 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range138 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between138 = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and138 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows138 = static fn (): array => [
    ['rowid' => 1, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'core_home', 'option_value' => 'home'],
    ['rowid' => 2, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha'],
    ['rowid' => 3, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache'],
    ['rowid' => 4, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms'],
    ['rowid' => 5, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_lazy', 'option_value' => 'lazy'],
    ['rowid' => 6, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_network', 'option_value' => 'network'],
    ['rowid' => 7, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_security', 'option_value' => 'shield'],
    ['rowid' => 8, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'search'],
    ['rowid' => 9, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme'],
];

$source138 = static function (array $rows, array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-covering-stat4-range-next138',
        'schemaCookie' => 1380,
        'stat4Generation' => 80,
        'rows' => $rows,
        'indexes' => [[
            'name' => 'idx_wp_options_blog_autoload_name_cover_stat4_next138',
            'rootPage' => 13801,
            'estimatedRows' => 240,
            'stat4Samples' => [
                ['neq' => '1 1 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_alpha']],
                ['neq' => '1 1 3', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
                ['neq' => '1 1 4', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
                ['neq' => '1 1 2', 'nlt' => '10 10 10', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_security']],
                ['neq' => '1 1 2', 'nlt' => '12 12 12', 'ndlt' => '4 4 4', 'sample' => [1, 'yes', 'plugin_seo']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_cover_stat4_next138 ON wp_options(blog_id, autoload, option_name, option_value, rowid)',
        ], [
            'name' => 'idx_wp_options_name_plain_next138',
            'rootPage' => 13802,
            'estimatedRows' => 900,
            'sql' => 'CREATE INDEX idx_wp_options_name_plain_next138 ON wp_options(option_name)',
        ]],
    ], $overrides);
};

$preparedSource138 = static fn (array $overrides = []) => $source138(array_slice($rows138(), 0, 5), $overrides);
$currentSource138 = static fn (array $overrides = []) => $source138($rows138(), [
    'name' => 'current-wp-options-covering-stat4-range-next138',
    'schemaCookie' => 1381,
    'stat4Generation' => 81,
    'indexes' => [[
        'rootPage' => 13811,
        'estimatedRows' => 90,
    ]],
] + $overrides);
$predicate138 = static fn (): array => $and138(
    $point138('blog_id', 1),
    $point138('autoload', 'yes'),
    $range138('option_name', '>=', 'plugin_cache'),
    $range138('option_name', '<=', 'plugin_seo'),
);
$order138 = [['column' => 'option_name']];
$needed138 = ['option_name', 'option_value', 'rowid'];
$plan138 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan::materializeNext138(
    $prepared ?? $preparedSource138(),
    $current ?? $currentSource138(),
    $predicate ?? $predicate138(),
    $order138,
    $needed ?? $needed138,
);

$fresh138 = static function () use ($preparedSource138, $plan138): array {
    $source = $preparedSource138();

    return $plan138($source, $source);
};
$between138Plan = static fn (): array => $plan138(null, null, $and138($point138('blog_id', 1), $point138('autoload', 'yes'), $between138('option_name', 'plugin_forms', 'plugin_security')));
$uncovered138 = static function () use ($currentSource138, $plan138): array {
    $source = $currentSource138();
    $source['indexes'] = [[
        'name' => 'idx_wp_options_blog_autoload_name_uncovered_next138',
        'rootPage' => 13821,
        'estimatedRows' => 90,
        'stat4Samples' => $currentSource138()['indexes'][0]['stat4Samples'],
        'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_uncovered_next138 ON wp_options(blog_id, autoload, option_name)',
    ]];

    return $plan138(null, $source);
};
$noStat138 = static function () use ($currentSource138, $plan138): array {
    $source = $currentSource138();
    $source['indexes'] = [[
        'name' => 'idx_wp_options_blog_autoload_name_no_stat_next138',
        'rootPage' => 13831,
        'estimatedRows' => 90,
        'stat4Samples' => [],
        'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_no_stat_next138 ON wp_options(blog_id, autoload, option_name, option_value, rowid)',
    ]];

    return $plan138(null, $source);
};

$tests = [
    'planner covering stat4 range current source next138 status ready' => static fn (TestRunner $t) => $t->same('covering-stat4-range-current-source-next138-ready', $plan138()['status']),
    'planner covering stat4 range current source next138 selects current' => static fn (TestRunner $t) => $t->same('current', $plan138()['selectedSource']),
    'planner covering stat4 range current source next138 stale statement' => static fn (TestRunner $t) => $t->same(true, $plan138()['stalePreparedStatement']),
    'planner covering stat4 range current source next138 reprepare required' => static fn (TestRunner $t) => $t->same(true, $plan138()['reprepareRequired']),
    'planner covering stat4 range current source next138 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan138()['schemaCookieChanged']),
    'planner covering stat4 range current source next138 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan138()['stat4GenerationChanged']),
    'planner covering stat4 range current source next138 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_blog_autoload_name_cover_stat4_next138', $plan138()['selectedPlan']['name']),
    'planner covering stat4 range current source next138 selected root' => static fn (TestRunner $t) => $t->same(13811, $plan138()['selectedPlan']['rootPage']),
    'planner covering stat4 range current source next138 non partial' => static fn (TestRunner $t) => $t->same(false, $plan138()['selectedPlan']['partial']),
    'planner covering stat4 range current source next138 covering true' => static fn (TestRunner $t) => $t->same(true, $plan138()['selectedPlan']['covering']),
    'planner covering stat4 range current source next138 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan138()['selectedPlan']['stat4Used']),
    'planner covering stat4 range current source next138 order satisfied' => static fn (TestRunner $t) => $t->same(true, $plan138()['selectedPlan']['orderBySatisfied']),
    'planner covering stat4 range current source next138 no table lookup' => static fn (TestRunner $t) => $t->same(true, $plan138()['tableLookupElided']),
    'planner covering stat4 range current source next138 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan138()['deferredSeekOpcode']),
    'planner covering stat4 range current source next138 range column' => static fn (TestRunner $t) => $t->same('option_name', $plan138()['selectedPlan']['rangeColumn']),
    'planner covering stat4 range current source next138 lower bound' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan138()['selectedPlan']['rangeLower']),
    'planner covering stat4 range current source next138 upper bound' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan138()['selectedPlan']['rangeUpper']),
    'planner covering stat4 range current source next138 upper inclusive' => static fn (TestRunner $t) => $t->same(true, $plan138()['selectedPlan']['upperInclusive']),
    'planner covering stat4 range current source next138 equality prefix' => static fn (TestRunner $t) => $t->same(2, $plan138()['selectedPlan']['equalityPrefix']),
    'planner covering stat4 range current source next138 used columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'autoload', 'option_name'], $plan138()['selectedPlan']['usedColumns']),
    'planner covering stat4 range current source next138 covered count' => static fn (TestRunner $t) => $t->same(4, $plan138()['selectedPlan']['coveredRowCount']),
    'planner covering stat4 range current source next138 covered rowids' => static fn (TestRunner $t) => $t->same([3, 4, 7, 8], array_values(array_intersect(array_column($plan138()['coveredRows'], 'rowid'), [3, 4, 7, 8]))),
    'planner covering stat4 range current source next138 covered ordered rowids' => static fn (TestRunner $t) => $t->same([3, 4, 7, 8], array_column($plan138()['coveredRows'], 'rowid')),
    'planner covering stat4 range current source next138 covered keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_seo'], array_column($plan138()['coveredRows'], 'rangeKey')),
    'planner covering stat4 range current source next138 payload from index' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_security', 'option_value' => 'shield', 'rowid' => 7], $plan138()['coveredRows'][2]['covering']),
    'planner covering stat4 range current source next138 excludes wrong autoload' => static fn (TestRunner $t) => $t->same(false, in_array(5, array_column($plan138()['coveredRows'], 'rowid'), true)),
    'planner covering stat4 range current source next138 excludes outside range' => static fn (TestRunner $t) => $t->same(false, in_array(9, array_column($plan138()['coveredRows'], 'rowid'), true)),
    'planner covering stat4 range current source next138 excludes wrong blog' => static fn (TestRunner $t) => $t->same(false, in_array(6, array_column($plan138()['coveredRows'], 'rowid'), true)),
    'planner covering stat4 range current source next138 current next rowids' => static fn (TestRunner $t) => $t->same([4, 7, 8, null], array_map(static fn (array $pair): mixed => $pair['next']['rowid'] ?? null, $plan138()['currentNextRows'])),
    'planner covering stat4 range current source next138 bucket count' => static fn (TestRunner $t) => $t->same(4, $plan138()['stat4BucketCount']),
    'planner covering stat4 range current source next138 bucket keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_seo'], array_column($plan138()['stat4RangeBuckets'], 'key')),
    'planner covering stat4 range current source next138 bucket next keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_security', 'plugin_seo', null], array_column($plan138()['stat4RangeBuckets'], 'nextKey')),
    'planner covering stat4 range current source next138 bucket stat neq' => static fn (TestRunner $t) => $t->same([3, 4, 2, 2], array_column($plan138()['stat4RangeBuckets'], 'neq')),
    'planner covering stat4 range current source next138 plan stat4 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_security', 'plugin_seo'], $plan138()['selectedPlan']['stat4RangeKeys']),
    'planner covering stat4 range current source next138 plan admitted rowids' => static fn (TestRunner $t) => $t->same([3, 4, 7, 8], $plan138()['selectedPlan']['rangeRowsAdmitted']),
    'planner covering stat4 range current source next138 next source label' => static fn (TestRunner $t) => $t->same('covering-stat4-range-current-source', $plan138()['selectedPlan']['nextSource']),
    'planner covering stat4 range current source next138 current fence cookie' => static fn (TestRunner $t) => $t->same(1381, $plan138()['currentSourceFence']['schemaCookie']),
    'planner covering stat4 range current source next138 current fence stat4' => static fn (TestRunner $t) => $t->same(81, $plan138()['currentSourceFence']['stat4Generation']),
    'planner covering stat4 range current source next138 covering signature' => static fn (TestRunner $t) => $t->same('option_name,option_value,rowid', $plan138()['currentSourceFence']['coveringSignature']),
    'planner covering stat4 range current source next138 row stream signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan138()['stat4RowStreamSignature'])),
    'planner covering stat4 range current source next138 bucket signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan138()['currentSourceFence']['stat4BucketSignature'])),
    'planner covering stat4 range current source next138 cursor recheck first' => static fn (TestRunner $t) => $t->same('Stat4RangeRecheck', $plan138()['selectedPlan']['cursorProgram'][0]['opcode']),
    'planner covering stat4 range current source next138 cursor opens read' => static fn (TestRunner $t) => $t->same('OpenRead', $plan138()['selectedPlan']['cursorProgram'][1]['opcode']),
    'planner covering stat4 range current source next138 cursor seek ge' => static fn (TestRunner $t) => $t->same('SeekGE', $plan138()['selectedPlan']['cursorProgram'][2]['opcode']),
    'planner covering stat4 range current source next138 cursor stop gt' => static fn (TestRunner $t) => $t->same('IdxGT', $plan138()['selectedPlan']['cursorProgram'][3]['opcode']),
    'planner covering stat4 range current source next138 detail' => static fn (TestRunner $t) => $t->contains('COVERING STAT4 RANGE CURRENT SOURCE', $plan138()['detail']),
    'planner covering stat4 range current source next138 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-covering-stat4-range-current-source-next138', $plan138()['dependencies'], true)),
    'planner covering stat4 range current source next138 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan138()['dependency_closure']),
    'planner covering stat4 range current source next138 non overlap' => static fn (TestRunner $t) => $t->contains('non-partial covering STAT4 range row admission', $plan138()['non_overlap']),
    'planner covering stat4 range current source next138 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('prepared', $fresh138()['selectedSource']),
    'planner covering stat4 range current source next138 fresh rowids' => static fn (TestRunner $t) => $t->same([3, 4], array_column($fresh138()['coveredRows'], 'rowid')),
    'planner covering stat4 range current source next138 between rowids' => static fn (TestRunner $t) => $t->same([4, 7], array_column($between138Plan()['coveredRows'], 'rowid')),
    'planner covering stat4 range current source next138 between lower inclusive' => static fn (TestRunner $t) => $t->same(true, $between138Plan()['selectedPlan']['lowerInclusive']),
    'planner covering stat4 range current source next138 between bucket keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_security'], array_column($between138Plan()['stat4RangeBuckets'], 'key')),
    'planner covering stat4 range current source next138 uncovered requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncovered138()['status']),
    'planner covering stat4 range current source next138 uncovered defers seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $uncovered138()['deferredSeekOpcode']),
    'planner covering stat4 range current source next138 no stat requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $noStat138()['status']),
    'planner covering stat4 range current source next138 validates rows list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan138(null, $currentSource138(['rows' => ['bad' => []]]))),
    'planner covering stat4 range current source next138 validates row arrays' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan138(null, $currentSource138(['rows' => ['bad']]))),
];

return $tests;
