<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan;

$point140 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range140 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between140 = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and140 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows140 = static fn (): array => [
    ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache-a'],
    ['rowid' => 11, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-a'],
    ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-b'],
    ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'forms-lazy'],
    ['rowid' => 14, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'mail'],
    ['rowid' => 15, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_mail', 'option_value' => 'network'],
    ['rowid' => 16, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-a'],
    ['rowid' => 17, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-b'],
    ['rowid' => 18, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme'],
];

$source140 = static function (array $rows, array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-covering-range-next140',
        'schemaCookie' => 1400,
        'stat4Generation' => 90,
        'rows' => $rows,
        'indexes' => [[
            'name' => 'idx_wp_options_blog_autoload_name_cover_stat4_next140',
            'rootPage' => 14001,
            'estimatedRows' => 300,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
                ['neq' => '1 1 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
                ['neq' => '1 1 1', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_mail']],
                ['neq' => '1 1 2', 'nlt' => '4 4 4', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_seo']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_cover_stat4_next140 ON wp_options(blog_id, autoload, option_name, option_value, rowid)',
        ]],
    ], $overrides);
};

$prepared140 = static fn (array $overrides = []): array => $source140(array_slice($rows140(), 0, 4), $overrides);
$current140 = static fn (array $overrides = []): array => $source140($rows140(), [
    'name' => 'current-wp-options-stat4-covering-range-next140',
    'schemaCookie' => 1401,
    'stat4Generation' => 91,
    'indexes' => [[
        'rootPage' => 14041,
        'estimatedRows' => 80,
        'stat4Samples' => [
            ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
            ['neq' => '1 1 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
            ['neq' => '1 1 1', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_mail']],
            ['neq' => '1 1 2', 'nlt' => '4 4 4', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_seo']],
        ],
    ]],
] + $overrides);

$preparedWithStaleBoundary140 = static function () use ($prepared140): array {
    $source = $prepared140();
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_legacy']],
        ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
        ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
    ];

    return $source;
};

$predicate140 = static fn (): array => $and140(
    $point140('blog_id', 1),
    $point140('autoload', 'yes'),
    $range140('option_name', '>=', 'plugin_cache'),
    $range140('option_name', '<=', 'plugin_seo'),
);
$order140 = [['column' => 'option_name']];
$needed140 = ['option_name', 'option_value', 'rowid'];
$plan140 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan::materializeNext140(
    $prepared ?? $preparedWithStaleBoundary140(),
    $current ?? $current140(),
    $predicate ?? $predicate140(),
    $order140,
    $needed ?? $needed140,
);

$betweenPlan140 = static fn (): array => $plan140(null, null, $and140(
    $point140('blog_id', 1),
    $point140('autoload', 'yes'),
    $between140('option_name', 'plugin_forms', 'plugin_seo'),
));
$freshPlan140 = static function () use ($current140, $plan140): array {
    $source = $current140(['schemaCookie' => 1401, 'stat4Generation' => 91]);

    return $plan140($source, $source);
};
$uncoveredPlan140 = static function () use ($current140, $plan140): array {
    $source = $current140();
    $source['indexes'] = [[
        'name' => 'idx_wp_options_blog_autoload_name_uncovered_next140',
        'rootPage' => 14071,
        'estimatedRows' => 80,
        'stat4Samples' => $current140()['indexes'][0]['stat4Samples'],
        'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_uncovered_next140 ON wp_options(blog_id, autoload, option_name)',
    ]];

    return $plan140(null, $source);
};

$tests = [
    'planner stat4 covering range current source next140 status ready' => static fn (TestRunner $t) => $t->same('stat4-covering-range-current-source-next140-ready', $plan140()['status']),
    'planner stat4 covering range current source next140 selects current' => static fn (TestRunner $t) => $t->same('current', $plan140()['selectedSource']),
    'planner stat4 covering range current source next140 stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan140()['stalePreparedStatement']),
    'planner stat4 covering range current source next140 selected root' => static fn (TestRunner $t) => $t->same(14041, $plan140()['selectedPlan']['rootPage']),
    'planner stat4 covering range current source next140 range column' => static fn (TestRunner $t) => $t->same('option_name', $plan140()['currentSourceNextCursor']['rangeColumn']),
    'planner stat4 covering range current source next140 rowids' => static fn (TestRunner $t) => $t->same([10, 11, 12, 14, 16, 17], $plan140()['currentSourceNextCursor']['rowids']),
    'planner stat4 covering range current source next140 keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_seo'], $plan140()['currentSourceNextCursor']['rangeKeys']),
    'planner stat4 covering range current source next140 excludes wrong autoload' => static fn (TestRunner $t) => $t->same(false, in_array(13, $plan140()['currentSourceNextCursor']['rowids'], true)),
    'planner stat4 covering range current source next140 excludes wrong blog' => static fn (TestRunner $t) => $t->same(false, in_array(15, $plan140()['currentSourceNextCursor']['rowids'], true)),
    'planner stat4 covering range current source next140 excludes outside range' => static fn (TestRunner $t) => $t->same(false, in_array(18, $plan140()['currentSourceNextCursor']['rowids'], true)),
    'planner stat4 covering range current source next140 duplicate group count' => static fn (TestRunner $t) => $t->same(2, $plan140()['currentSourceNextCursor']['duplicateRangeGroupCount']),
    'planner stat4 covering range current source next140 duplicate rowids' => static fn (TestRunner $t) => $t->same([11, 12, 16, 17], $plan140()['rangeDuplicateRowids']),
    'planner stat4 covering range current source next140 duplicate first key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan140()['currentSourceNextCursor']['duplicateRangeGroups'][0]['rangeKey']),
    'planner stat4 covering range current source next140 duplicate second key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan140()['currentSourceNextCursor']['duplicateRangeGroups'][1]['rangeKey']),
    'planner stat4 covering range current source next140 duplicate forms rowids' => static fn (TestRunner $t) => $t->same([11, 12], $plan140()['currentSourceNextCursor']['duplicateRangeGroups'][0]['rowids']),
    'planner stat4 covering range current source next140 duplicate seo count' => static fn (TestRunner $t) => $t->same(2, $plan140()['currentSourceNextCursor']['duplicateRangeGroups'][1]['count']),
    'planner stat4 covering range current source next140 stable tie break' => static fn (TestRunner $t) => $t->same(true, $plan140()['currentSourceNextCursor']['stableTieBreak']),
    'planner stat4 covering range current source next140 selected stable flag' => static fn (TestRunner $t) => $t->same(true, $plan140()['selectedPlan']['currentNextCursorStable']),
    'planner stat4 covering range current source next140 step count' => static fn (TestRunner $t) => $t->same(6, $plan140()['currentSourceNextCursor']['stepCount']),
    'planner stat4 covering range current source next140 first step seeks' => static fn (TestRunner $t) => $t->same('SeekGE', $plan140()['currentSourceNextCursor']['steps'][0]['opcode']),
    'planner stat4 covering range current source next140 second step next' => static fn (TestRunner $t) => $t->same('Next', $plan140()['currentSourceNextCursor']['steps'][1]['opcode']),
    'planner stat4 covering range current source next140 duplicate step previous flag' => static fn (TestRunner $t) => $t->same(true, $plan140()['currentSourceNextCursor']['steps'][2]['sameRangeAsPrevious']),
    'planner stat4 covering range current source next140 duplicate step next flag' => static fn (TestRunner $t) => $t->same(true, $plan140()['currentSourceNextCursor']['steps'][1]['sameRangeAsNext']),
    'planner stat4 covering range current source next140 final step eof' => static fn (TestRunner $t) => $t->same(null, $plan140()['currentSourceNextCursor']['steps'][5]['nextRowid']),
    'planner stat4 covering range current source next140 step covering columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'rowid'], $plan140()['currentSourceNextCursor']['steps'][3]['coveringColumns']),
    'planner stat4 covering range current source next140 uses current buckets' => static fn (TestRunner $t) => $t->same(true, $plan140()['currentSourceNextCursor']['usesCurrentStat4Buckets']),
    'planner stat4 covering range current source next140 current boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan140()['stat4CurrentBoundaryKeys']),
    'planner stat4 covering range current source next140 prepared boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_legacy', 'plugin_cache', 'plugin_forms'], $plan140()['stat4PreparedBoundaryKeys']),
    'planner stat4 covering range current source next140 stale prepared rejected' => static fn (TestRunner $t) => $t->same(['plugin_legacy'], $plan140()['currentSourceNextCursor']['stalePreparedBucketsRejected']),
    'planner stat4 covering range current source next140 selected stale rejected' => static fn (TestRunner $t) => $t->same(['plugin_legacy'], $plan140()['selectedPlan']['stalePreparedBucketsRejected']),
    'planner stat4 covering range current source next140 boundary source current' => static fn (TestRunner $t) => $t->same('current', $plan140()['stat4BoundarySource']),
    'planner stat4 covering range current source next140 next source label' => static fn (TestRunner $t) => $t->same('stat4-covering-range-current-source-next140', $plan140()['selectedPlan']['nextSource']),
    'planner stat4 covering range current source next140 table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan140()['currentSourceNextCursor']['tableLookupElided']),
    'planner stat4 covering range current source next140 no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan140()['currentSourceNextCursor']['deferredSeekOpcode']),
    'planner stat4 covering range current source next140 next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan140()['currentSourceNextCursor']['nextOpcode']),
    'planner stat4 covering range current source next140 cursor program boundary' => static fn (TestRunner $t) => $t->same('Stat4CurrentSourceBoundary', $plan140()['selectedPlan']['cursorProgram'][count($plan140()['selectedPlan']['cursorProgram']) - 2]['opcode']),
    'planner stat4 covering range current source next140 cursor program tiebreak' => static fn (TestRunner $t) => $t->same('NextDuplicateRangeTieBreak', $plan140()['selectedPlan']['cursorProgram'][count($plan140()['selectedPlan']['cursorProgram']) - 1]['opcode']),
    'planner stat4 covering range current source next140 cursor signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan140()['currentSourceFence']['next140CursorSignature'])),
    'planner stat4 covering range current source next140 boundary signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan140()['currentSourceFence']['next140BoundarySignature'])),
    'planner stat4 covering range current source next140 prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan140()['currentSourceFence']['preparedBoundarySignature'])),
    'planner stat4 covering range current source next140 payload remains covering' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_forms', 'option_value' => 'forms-b', 'rowid' => 12], $plan140()['coveredRows'][2]['covering']),
    'planner stat4 covering range current source next140 bucket count from current' => static fn (TestRunner $t) => $t->same(4, $plan140()['stat4BucketCount']),
    'planner stat4 covering range current source next140 selected duplicate count' => static fn (TestRunner $t) => $t->same(2, $plan140()['selectedPlan']['duplicateRangeGroupCount']),
    'planner stat4 covering range current source next140 detail' => static fn (TestRunner $t) => $t->contains('STAT4 COVERING RANGE CURRENT-SOURCE NEXT140', $plan140()['detail']),
    'planner stat4 covering range current source next140 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-covering-range-current-source-next140', $plan140()['dependencies'], true)),
    'planner stat4 covering range current source next140 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan140()['dependency_closure']),
    'planner stat4 covering range current source next140 non overlap' => static fn (TestRunner $t) => $t->contains('stable Next advancement', $plan140()['non_overlap']),
    'planner stat4 covering range current source next140 between rowids' => static fn (TestRunner $t) => $t->same([11, 12, 14, 16, 17], $betweenPlan140()['currentSourceNextCursor']['rowids']),
    'planner stat4 covering range current source next140 between duplicate rowids' => static fn (TestRunner $t) => $t->same([11, 12, 16, 17], $betweenPlan140()['rangeDuplicateRowids']),
    'planner stat4 covering range current source next140 between boundaries' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo'], $betweenPlan140()['stat4CurrentBoundaryKeys']),
    'planner stat4 covering range current source next140 fresh reuses prepared' => static fn (TestRunner $t) => $t->same('requires-next-stage', $freshPlan140()['status']),
    'planner stat4 covering range current source next140 uncovered requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncoveredPlan140()['status']),
    'planner stat4 covering range current source next140 uncovered keeps table lookup fallback' => static fn (TestRunner $t) => $t->same(false, $uncoveredPlan140()['currentSourceNextCursor']['usesCurrentStat4Buckets']),
    'planner stat4 covering range current source next140 validates source rows list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan140(null, $current140(['rows' => ['bad' => []]]))),
    'planner stat4 covering range current source next140 validates source row arrays' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan140(null, $current140(['rows' => ['bad']]))),
];

return $tests;
