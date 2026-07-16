<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4PartialCoveringCurrentSourcePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$preparedSamples = static fn (): array => [
    ['neq' => '1 4 4', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 8 8', 'nlt' => '4 4 4', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 6 6', 'nlt' => '12 12 12', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_security', 'yes']],
];

$currentSamples = static fn (): array => [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_editor', 'yes']],
    ['neq' => '1 4 4', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '10 10 10', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_seo', 'yes']],
];

$makeIndex = static fn (string $name, int $rootPage, array $samples, string $tail = 'autoload, option_value'): array => [
    'name' => $name,
    'rootPage' => $rootPage,
    'estimatedRows' => 100,
    'stat4Samples' => $samples,
    'sql' => "CREATE INDEX {$name} ON wp_options(blog_id, option_name, {$tail}) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
];

$preparedSource = static fn (): array => [
    'name' => 'prepared-plugin-plan-next100',
    'schemaCookie' => 77,
    'stat4Generation' => 14,
    'coveringColumns' => ['autoload', 'option_value'],
    'indexes' => [
        $makeIndex('idx_wp_options_plugin_cover_next100', 410, $preparedSamples()),
    ],
];

$currentSource = static fn (): array => [
    'name' => 'current-plugin-plan-next100',
    'schemaCookie' => 77,
    'stat4Generation' => 14,
    'coveringColumns' => ['autoload', 'option_value'],
    'indexes' => [
        $makeIndex('idx_wp_options_plugin_cover_next100', 411, $currentSamples()),
    ],
];

$predicate = static fn (): array => $and(
    $point('kind', 'plugin'),
    $point('blog_id', 1),
    $range('option_name', '>=', 'plugin_cache'),
    $range('option_name', '<', 'plugin_seo'),
);

$compare = static fn (?array $prepared = null, ?array $current = null, ?array $query = null, array $needed = ['autoload', 'option_value']): array => SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
    $prepared ?? $preparedSource(),
    $current ?? $currentSource(),
    $query ?? $predicate(),
    [['column' => 'option_name']],
    $needed,
);

$plan = static fn (): array => $compare();
$unchangedPlan = static fn (): array => $compare($preparedSource(), $preparedSource());
$rootOnlyPlan = static function () use ($preparedSource, $currentSource, $compare): array {
    $current = $currentSource();
    $current['indexes'][0]['stat4Samples'] = $preparedSource()['indexes'][0]['stat4Samples'];
    $current['indexes'][0]['rootPage'] = 412;

    return $compare($preparedSource(), $current);
};
$sqlOnlyPlan = static function () use ($preparedSource, $currentSource, $compare): array {
    $current = $currentSource();
    $current['indexes'][0]['stat4Samples'] = $preparedSource()['indexes'][0]['stat4Samples'];
    $current['indexes'][0]['rootPage'] = 410;
    $current['indexes'][0]['sql'] = "CREATE INDEX idx_wp_options_plugin_cover_next100 ON wp_options(blog_id, option_name, option_value, autoload) WHERE kind = 'plugin' AND option_name >= 'plugin_'";

    return $compare($preparedSource(), $current);
};
$uncoveredPlan = static function () use ($preparedSource, $currentSource, $compare): array {
    $current = $currentSource();
    $current['indexes'][0] = [
        'name' => 'idx_wp_options_plugin_uncovered_next100',
        'rootPage' => 413,
        'estimatedRows' => 100,
        'stat4Samples' => $current['indexes'][0]['stat4Samples'],
        'sql' => "CREATE INDEX idx_wp_options_plugin_uncovered_next100 ON wp_options(blog_id, option_name, autoload) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ];

    return $compare($preparedSource(), $current);
};
$betweenPlan = static fn (): array => $compare(null, null, $and(
    $point('kind', 'plugin'),
    $point('blog_id', 1),
    $between('option_name', 'plugin_editor', 'plugin_security'),
));
$invalid = static fn (array $override): array => SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
    array_replace($preparedSource(), $override),
    $currentSource(),
    $predicate(),
    [['column' => 'option_name']],
    ['autoload', 'option_value'],
);

$tests = [
    'sqlplanner expression partial covering current source next100 detects index signature change' => static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']),
    'sqlplanner expression partial covering current source next100 selects current source for index change' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'sqlplanner expression partial covering current source next100 marks stale prepared statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'sqlplanner expression partial covering current source next100 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'sqlplanner expression partial covering current source next100 keeps schema cookie unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['schemaCookieChanged']),
    'sqlplanner expression partial covering current source next100 keeps stat4 generation unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['stat4GenerationChanged']),
    'sqlplanner expression partial covering current source next100 keeps projection unchanged' => static fn (TestRunner $t) => $t->same(false, $plan()['projectionChanged']),
    'sqlplanner expression partial covering current source next100 exposes prepared index signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['preparedSource']['indexSignature'])),
    'sqlplanner expression partial covering current source next100 exposes current index signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan()['currentSource']['indexSignature'])),
    'sqlplanner expression partial covering current source next100 signatures differ' => static fn (TestRunner $t) => $t->same(false, $plan()['preparedSource']['indexSignature'] === $plan()['currentSource']['indexSignature']),
    'sqlplanner expression partial covering current source next100 selected status usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'sqlplanner expression partial covering current source next100 selected plan index' => static fn (TestRunner $t) => $t->same('idx_wp_options_plugin_cover_next100', $plan()['selectedPlan']['name']),
    'sqlplanner expression partial covering current source next100 selected root page from current' => static fn (TestRunner $t) => $t->same(411, $plan()['selectedPlan']['rootPage']),
    'sqlplanner expression partial covering current source next100 selected plan is partial' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partial']),
    'sqlplanner expression partial covering current source next100 partial predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']),
    'sqlplanner expression partial covering current source next100 selected plan is covering' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'sqlplanner expression partial covering current source next100 selected next source covering index' => static fn (TestRunner $t) => $t->same('covering-index', $plan()['selectedPlan']['nextSource']),
    'sqlplanner expression partial covering current source next100 selected order by mode' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan()['selectedPlan']['orderByMode']),
    'sqlplanner expression partial covering current source next100 partial order usable' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialIndexOrderUsable']),
    'sqlplanner expression partial covering current source next100 order by satisfied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['orderBySatisfied']),
    'sqlplanner expression partial covering current source next100 no block sort' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['blockSortRequired']),
    'sqlplanner expression partial covering current source next100 no deferred table lookup' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['deferredTableLookup']),
    'sqlplanner expression partial covering current source next100 stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4Used']),
    'sqlplanner expression partial covering current source next100 current estimate selected' => static fn (TestRunner $t) => $t->same(10, $plan()['selectedPlan']['estimatedRows']),
    'sqlplanner expression partial covering current source next100 current stat4 estimate selected' => static fn (TestRunner $t) => $t->same(10, $plan()['selectedPlan']['stat4Estimate']),
    'sqlplanner expression partial covering current source next100 current matched samples' => static fn (TestRunner $t) => $t->same(4, $plan()['currentSource']['stat4MatchedSamples']),
    'sqlplanner expression partial covering current source next100 prepared matched samples' => static fn (TestRunner $t) => $t->same(3, $plan()['preparedSource']['stat4MatchedSamples']),
    'sqlplanner expression partial covering current source next100 current source column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['currentSource']['stat4CurrentSourceColumn']),
    'sqlplanner expression partial covering current source next100 current source offset' => static fn (TestRunner $t) => $t->same(1, $plan()['currentSource']['stat4CurrentSourceOffset']),
    'sqlplanner expression partial covering current source next100 lower boundary current' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']['key']),
    'sqlplanner expression partial covering current source next100 lower boundary next' => static fn (TestRunner $t) => $t->same('plugin_editor', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['next']['key']),
    'sqlplanner expression partial covering current source next100 upper boundary current' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'sqlplanner expression partial covering current source next100 upper boundary has no next' => static fn (TestRunner $t) => $t->same(null, $plan()['selectedPlan']['stat4RangeCurrentNext']['upper']['next']),
    'sqlplanner expression partial covering current source next100 lower inclusive' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4RangeCurrentNext']['lowerInclusive']),
    'sqlplanner expression partial covering current source next100 upper exclusive' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['stat4RangeCurrentNext']['upperInclusive']),
    'sqlplanner expression partial covering current source next100 estimate delta from current index payload' => static fn (TestRunner $t) => $t->same(-8, $plan()['estimatedRowsDelta']),
    'sqlplanner expression partial covering current source next100 stat4 estimate delta from current index payload' => static fn (TestRunner $t) => $t->same(-8, $plan()['stat4EstimateDelta']),
    'sqlplanner expression partial covering current source next100 detail names current source' => static fn (TestRunner $t) => $t->same(true, str_starts_with($plan()['detail'], 'REPREPARE STAT4 PARTIAL COVERING USING CURRENT SOURCE current-plugin-plan-next100')),
    'sqlplanner expression partial covering current source next100 detail preserves covering plan' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['detail'], 'COVERING')),
    'sqlplanner expression partial covering current source next100 dependencies include partial order planner' => static fn (TestRunner $t) => $t->same(true, in_array('SQLitePartialIndexOrderCurrentSourcePlan', $plan()['dependencies'], true)),
    'sqlplanner expression partial covering current source next100 dependencies include multicolumn planner' => static fn (TestRunner $t) => $t->same(true, in_array('SQLiteMultiColumnRangePlan', $plan()['dependencies'], true)),
    'sqlplanner expression partial covering current source next100 unchanged reuses prepared source' => static fn (TestRunner $t) => $t->same('prepared', $unchangedPlan()['selectedSource']),
    'sqlplanner expression partial covering current source next100 unchanged index signature stable' => static fn (TestRunner $t) => $t->same(false, $unchangedPlan()['indexSignatureChanged']),
    'sqlplanner expression partial covering current source next100 unchanged does not reprepare' => static fn (TestRunner $t) => $t->same(false, $unchangedPlan()['reprepareRequired']),
    'sqlplanner expression partial covering current source next100 root-only change detected' => static fn (TestRunner $t) => $t->same(true, $rootOnlyPlan()['indexSignatureChanged']),
    'sqlplanner expression partial covering current source next100 root-only change selects current root' => static fn (TestRunner $t) => $t->same(412, $rootOnlyPlan()['selectedPlan']['rootPage']),
    'sqlplanner expression partial covering current source next100 sql-only change detected' => static fn (TestRunner $t) => $t->same(true, $sqlOnlyPlan()['indexSignatureChanged']),
    'sqlplanner expression partial covering current source next100 sql-only change reparses current source' => static fn (TestRunner $t) => $t->same('current', $sqlOnlyPlan()['selectedSource']),
    'sqlplanner expression partial covering current source next100 uncovered current is not covering' => static fn (TestRunner $t) => $t->same(false, $uncoveredPlan()['currentSource']['covering']),
    'sqlplanner expression partial covering current source next100 uncovered current defers table lookup' => static fn (TestRunner $t) => $t->same('table-rowid-lookup', $uncoveredPlan()['selectedPlan']['nextSource']),
    'sqlplanner expression partial covering current source next100 between estimate uses current source' => static fn (TestRunner $t) => $t->same(9, $betweenPlan()['selectedPlan']['estimatedRows']),
    'sqlplanner expression partial covering current source next100 between lower boundary' => static fn (TestRunner $t) => $t->same('plugin_editor', $betweenPlan()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']['key']),
    'sqlplanner expression partial covering current source next100 between upper boundary' => static fn (TestRunner $t) => $t->same('plugin_security', $betweenPlan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'sqlplanner expression partial covering current source next100 validates index list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['indexes' => ['bad' => []]])),
    'sqlplanner expression partial covering current source next100 validates covering columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalid(['coveringColumns' => ['autoload', 7]])),
];

return $tests;
