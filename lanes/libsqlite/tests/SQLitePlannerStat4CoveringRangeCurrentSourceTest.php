<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows = static fn (): array => [
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

$source = static function (array $rows, array $overrides = []): array {
    return array_replace_recursive([
        'name' => 'prepared-wp-options-stat4-covering-range-currentSourceCursor',
        'schemaCookie' => 1400,
        'stat4Generation' => 90,
        'rows' => $rows,
        'indexes' => [[
            'name' => 'idx_wp_options_blog_autoload_name_cover_stat4_currentSourceCursor',
            'rootPage' => 14001,
            'estimatedRows' => 300,
            'stat4Samples' => [
                ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_cache']],
                ['neq' => '1 1 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_forms']],
                ['neq' => '1 1 1', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_mail']],
                ['neq' => '1 1 2', 'nlt' => '4 4 4', 'ndlt' => '3 3 3', 'sample' => [1, 'yes', 'plugin_seo']],
            ],
            'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_cover_stat4_currentSourceCursor ON wp_options(blog_id, autoload, option_name, option_value, rowid)',
        ]],
    ], $overrides);
};

$prepared = static fn (array $overrides = []): array => $source(array_slice($rows(), 0, 4), $overrides);
$current = static fn (array $overrides = []): array => $source($rows(), [
    'name' => 'current-wp-options-stat4-covering-range-currentSourceCursor',
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

$preparedWithStaleBoundary = static function () use ($prepared): array {
    $source = $prepared();
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'yes', 'plugin_legacy']],
        ['neq' => '1 1 1', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'yes', 'plugin_cache']],
        ['neq' => '1 1 1', 'nlt' => '2 2 2', 'ndlt' => '2 2 2', 'sample' => [1, 'yes', 'plugin_forms']],
    ];

    return $source;
};

$predicate = static fn (): array => $and(
    $point('blog_id', 1),
    $point('autoload', 'yes'),
    $range('option_name', '>=', 'plugin_cache'),
    $range('option_name', '<=', 'plugin_seo'),
);
$order = [['column' => 'option_name']];
$needed = ['option_name', 'option_value', 'rowid'];
$plan = static fn (?array $preparedSource = null, ?array $currentSource = null, ?array $predicateSource = null, ?array $neededColumns = null): array => SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan::materializeCurrentSourceCursor(
    $preparedSource ?? $preparedWithStaleBoundary(),
    $currentSource ?? $current(),
    $predicateSource ?? $predicate(),
    $order,
    $neededColumns ?? $needed,
);

$betweenPlan = static fn (): array => $plan(null, null, $and(
    $point('blog_id', 1),
    $point('autoload', 'yes'),
    $between('option_name', 'plugin_forms', 'plugin_seo'),
));
$freshPlan = static function () use ($current, $plan): array {
    $source = $current(['schemaCookie' => 1401, 'stat4Generation' => 91]);

    return $plan($source, $source);
};
$uncoveredPlan = static function () use ($current, $plan): array {
    $source = $current();
    $source['indexes'] = [[
        'name' => 'idx_wp_options_blog_autoload_name_uncovered_currentSourceCursor',
        'rootPage' => 14071,
        'estimatedRows' => 80,
        'stat4Samples' => $current()['indexes'][0]['stat4Samples'],
        'sql' => 'CREATE INDEX idx_wp_options_blog_autoload_name_uncovered_currentSourceCursor ON wp_options(blog_id, autoload, option_name)',
    ]];

    return $plan(null, $source);
};

$tests = [
    'planner stat4 covering range current source currentSourceCursor status ready' => static fn (TestRunner $t) => $t->same('stat4-covering-range-current-source-cursor-ready', $plan()['status']),
    'planner stat4 covering range current source currentSourceCursor selects current' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 covering range current source currentSourceCursor stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 covering range current source currentSourceCursor selected root' => static fn (TestRunner $t) => $t->same(14041, $plan()['selectedPlan']['rootPage']),
    'planner stat4 covering range current source currentSourceCursor range column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['currentSourceNextCursor']['rangeColumn']),
    'planner stat4 covering range current source currentSourceCursor rowids' => static fn (TestRunner $t) => $t->same([10, 11, 12, 14, 16, 17], $plan()['currentSourceNextCursor']['rowids']),
    'planner stat4 covering range current source currentSourceCursor keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_seo'], $plan()['currentSourceNextCursor']['rangeKeys']),
    'planner stat4 covering range current source currentSourceCursor excludes wrong autoload' => static fn (TestRunner $t) => $t->same(false, in_array(13, $plan()['currentSourceNextCursor']['rowids'], true)),
    'planner stat4 covering range current source currentSourceCursor excludes wrong blog' => static fn (TestRunner $t) => $t->same(false, in_array(15, $plan()['currentSourceNextCursor']['rowids'], true)),
    'planner stat4 covering range current source currentSourceCursor excludes outside range' => static fn (TestRunner $t) => $t->same(false, in_array(18, $plan()['currentSourceNextCursor']['rowids'], true)),
    'planner stat4 covering range current source currentSourceCursor duplicate group count' => static fn (TestRunner $t) => $t->same(2, $plan()['currentSourceNextCursor']['duplicateRangeGroupCount']),
    'planner stat4 covering range current source currentSourceCursor duplicate rowids' => static fn (TestRunner $t) => $t->same([11, 12, 16, 17], $plan()['rangeDuplicateRowids']),
    'planner stat4 covering range current source currentSourceCursor duplicate first key' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan()['currentSourceNextCursor']['duplicateRangeGroups'][0]['rangeKey']),
    'planner stat4 covering range current source currentSourceCursor duplicate second key' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan()['currentSourceNextCursor']['duplicateRangeGroups'][1]['rangeKey']),
    'planner stat4 covering range current source currentSourceCursor duplicate forms rowids' => static fn (TestRunner $t) => $t->same([11, 12], $plan()['currentSourceNextCursor']['duplicateRangeGroups'][0]['rowids']),
    'planner stat4 covering range current source currentSourceCursor duplicate seo count' => static fn (TestRunner $t) => $t->same(2, $plan()['currentSourceNextCursor']['duplicateRangeGroups'][1]['count']),
    'planner stat4 covering range current source currentSourceCursor stable tie break' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSourceNextCursor']['stableTieBreak']),
    'planner stat4 covering range current source currentSourceCursor selected stable flag' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['currentNextCursorStable']),
    'planner stat4 covering range current source currentSourceCursor step count' => static fn (TestRunner $t) => $t->same(6, $plan()['currentSourceNextCursor']['stepCount']),
    'planner stat4 covering range current source currentSourceCursor first step seeks' => static fn (TestRunner $t) => $t->same('SeekGE', $plan()['currentSourceNextCursor']['steps'][0]['opcode']),
    'planner stat4 covering range current source currentSourceCursor second step next' => static fn (TestRunner $t) => $t->same('Next', $plan()['currentSourceNextCursor']['steps'][1]['opcode']),
    'planner stat4 covering range current source currentSourceCursor duplicate step previous flag' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSourceNextCursor']['steps'][2]['sameRangeAsPrevious']),
    'planner stat4 covering range current source currentSourceCursor duplicate step next flag' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSourceNextCursor']['steps'][1]['sameRangeAsNext']),
    'planner stat4 covering range current source currentSourceCursor final step eof' => static fn (TestRunner $t) => $t->same(null, $plan()['currentSourceNextCursor']['steps'][5]['nextRowid']),
    'planner stat4 covering range current source currentSourceCursor step covering columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'rowid'], $plan()['currentSourceNextCursor']['steps'][3]['coveringColumns']),
    'planner stat4 covering range current source currentSourceCursor uses current buckets' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSourceNextCursor']['usesCurrentStat4Buckets']),
    'planner stat4 covering range current source currentSourceCursor current boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_cache', 'plugin_forms', 'plugin_mail', 'plugin_seo'], $plan()['stat4CurrentBoundaryKeys']),
    'planner stat4 covering range current source currentSourceCursor prepared boundary keys' => static fn (TestRunner $t) => $t->same(['plugin_legacy', 'plugin_cache', 'plugin_forms'], $plan()['stat4PreparedBoundaryKeys']),
    'planner stat4 covering range current source currentSourceCursor stale prepared rejected' => static fn (TestRunner $t) => $t->same(['plugin_legacy'], $plan()['currentSourceNextCursor']['stalePreparedBucketsRejected']),
    'planner stat4 covering range current source currentSourceCursor selected stale rejected' => static fn (TestRunner $t) => $t->same(['plugin_legacy'], $plan()['selectedPlan']['stalePreparedBucketsRejected']),
    'planner stat4 covering range current source currentSourceCursor boundary source current' => static fn (TestRunner $t) => $t->same('current', $plan()['stat4BoundarySource']),
    'planner stat4 covering range current source currentSourceCursor next source label' => static fn (TestRunner $t) => $t->same('stat4-covering-range-current-source-cursor', $plan()['selectedPlan']['nextSource']),
    'planner stat4 covering range current source currentSourceCursor table lookup elided' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSourceNextCursor']['tableLookupElided']),
    'planner stat4 covering range current source currentSourceCursor no deferred seek' => static fn (TestRunner $t) => $t->same(null, $plan()['currentSourceNextCursor']['deferredSeekOpcode']),
    'planner stat4 covering range current source currentSourceCursor next opcode' => static fn (TestRunner $t) => $t->same('Next', $plan()['currentSourceNextCursor']['nextOpcode']),
    'planner stat4 covering range current source currentSourceCursor cursor program boundary' => static fn (TestRunner $t) => $t->same('Stat4CurrentSourceBoundary', $plan()['selectedPlan']['cursorProgram'][count($plan()['selectedPlan']['cursorProgram']) - 2]['opcode']),
    'planner stat4 covering range current source currentSourceCursor cursor program tiebreak' => static fn (TestRunner $t) => $t->same('NextDuplicateRangeTieBreak', $plan()['selectedPlan']['cursorProgram'][count($plan()['selectedPlan']['cursorProgram']) - 1]['opcode']),
    'planner stat4 covering range current source currentSourceCursor cursor signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['currentSourceCursorSignature'])),
    'planner stat4 covering range current source currentSourceCursor boundary signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['currentSourceBoundarySignature'])),
    'planner stat4 covering range current source currentSourceCursor prepared signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSourceFence']['preparedBoundarySignature'])),
    'planner stat4 covering range current source currentSourceCursor payload remains covering' => static fn (TestRunner $t) => $t->same(['option_name' => 'plugin_forms', 'option_value' => 'forms-b', 'rowid' => 12], $plan()['coveredRows'][2]['covering']),
    'planner stat4 covering range current source currentSourceCursor bucket count from current' => static fn (TestRunner $t) => $t->same(4, $plan()['stat4BucketCount']),
    'planner stat4 covering range current source currentSourceCursor selected duplicate count' => static fn (TestRunner $t) => $t->same(2, $plan()['selectedPlan']['duplicateRangeGroupCount']),
    'planner stat4 covering range current source currentSourceCursor detail' => static fn (TestRunner $t) => $t->contains('STAT4 COVERING RANGE CURRENT-SOURCE CURSOR', $plan()['detail']),
    'planner stat4 covering range current source currentSourceCursor dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-covering-range-current-source-cursor', $plan()['dependencies'], true)),
    'planner stat4 covering range current source currentSourceCursor dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan()['dependency_closure']),
    'planner stat4 covering range current source currentSourceCursor non overlap' => static fn (TestRunner $t) => $t->contains('stable Next advancement', $plan()['non_overlap']),
    'planner stat4 covering range current source currentSourceCursor between rowids' => static fn (TestRunner $t) => $t->same([11, 12, 14, 16, 17], $betweenPlan()['currentSourceNextCursor']['rowids']),
    'planner stat4 covering range current source currentSourceCursor between duplicate rowids' => static fn (TestRunner $t) => $t->same([11, 12, 16, 17], $betweenPlan()['rangeDuplicateRowids']),
    'planner stat4 covering range current source currentSourceCursor between boundaries' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo'], $betweenPlan()['stat4CurrentBoundaryKeys']),
    'planner stat4 covering range current source currentSourceCursor fresh reuses prepared' => static fn (TestRunner $t) => $t->same('requires-next-stage', $freshPlan()['status']),
    'planner stat4 covering range current source currentSourceCursor uncovered requires next stage' => static fn (TestRunner $t) => $t->same('requires-next-stage', $uncoveredPlan()['status']),
    'planner stat4 covering range current source currentSourceCursor uncovered keeps table lookup fallback' => static fn (TestRunner $t) => $t->same(false, $uncoveredPlan()['currentSourceNextCursor']['usesCurrentStat4Buckets']),
    'planner stat4 covering range current source currentSourceCursor validates source rows list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $current(['rows' => ['bad' => []]]))),
    'planner stat4 covering range current source currentSourceCursor validates source row arrays' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $current(['rows' => ['bad']]))),
];

return $tests;
