<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteStat4PartialCoveringCurrentSourcePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$stat4Prepared = static fn (): array => [
    ['neq' => '1 3 3', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
    ['neq' => '1 8 8', 'nlt' => '3 3 3', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 13 13', 'nlt' => '11 11 11', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 11 11', 'nlt' => '24 24 24', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 4 4', 'nlt' => '35 35 35', 'ndlt' => '4 4 4', 'sample' => [1, 'theme_mods', 'yes']],
];

$stat4Current = static fn (): array => [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 4 4', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_editor', 'yes']],
    ['neq' => '1 8 8', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '15 15 15', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '18 18 18', 'ndlt' => '5 5 5', 'sample' => [1, 'plugin_seo', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '23 23 23', 'ndlt' => '6 6 6', 'sample' => [1, 'theme_mods', 'yes']],
    ['neq' => '1 1 1', 'nlt' => '25 25 25', 'ndlt' => '7 7 7', 'sample' => [2, 'plugin_cache', 'yes']],
];

$index = static fn (string $name, int $rootPage, array $samples, array $columns = ['blog_id', 'option_name', 'autoload', 'option_value']): array => [
    'name' => $name,
    'rootPage' => $rootPage,
    'estimatedRows' => 120,
    'stat4Samples' => $samples,
    'sql' => sprintf(
        "CREATE INDEX %s ON wp_options(%s) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
        $name,
        implode(', ', $columns),
    ),
];

$preparedSource = static fn (): array => [
    'name' => 'prepared-before-plugin-import',
    'schemaCookie' => 14,
    'stat4Generation' => 40,
    'coveringColumns' => ['autoload', 'option_value'],
    'indexes' => [
        $index('idx_blog_plugin_name_stat4_cover_next90', 9001, $stat4Prepared()),
        $index('idx_blog_plugin_name_plain_next90', 9002, [], ['blog_id', 'option_name', 'autoload']),
    ],
];

$currentSource = static fn (): array => [
    'name' => 'current-after-plugin-import',
    'schemaCookie' => 14,
    'stat4Generation' => 41,
    'coveringColumns' => ['autoload', 'option_value'],
    'indexes' => [
        $index('idx_blog_plugin_name_stat4_cover_next90', 9001, $stat4Current()),
        $index('idx_blog_plugin_name_plain_next90', 9002, [], ['blog_id', 'option_name', 'autoload']),
    ],
];

$predicate = static fn (): array => $and(
    $point('kind', 'plugin'),
    $point('blog_id', 1),
    $range('option_name', '>=', 'plugin_cache'),
    $range('option_name', '<', 'plugin_seo'),
);

$plan = static fn (array $prepared = null, array $current = null, array $query = null, array $needed = ['autoload', 'option_value']): array => SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
    $prepared ?? $preparedSource(),
    $current ?? $currentSource(),
    $query ?? $predicate(),
    [['column' => 'option_name']],
    $needed,
);

$tests = [
    'planner stat4 partial covering current source next90 selects current source' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 partial covering current source next90 marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 partial covering current source next90 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 partial covering current source next90 detects stat4 generation change' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 partial covering current source next90 keeps schema cookie stable' => static fn (TestRunner $t) => $t->same(false, $plan()['schemaCookieChanged']),
    'planner stat4 partial covering current source next90 keeps projection stable' => static fn (TestRunner $t) => $t->same(false, $plan()['projectionChanged']),
    'planner stat4 partial covering current source next90 status is usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 partial covering current source next90 selected plan uses index' => static fn (TestRunner $t) => $t->same('idx_blog_plugin_name_stat4_cover_next90', $plan()['selectedPlan']['name']),
    'planner stat4 partial covering current source next90 selected plan is covering' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['covering']),
    'planner stat4 partial covering current source next90 selected plan proves partial' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 partial covering current source next90 selected plan uses partial order' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialIndexOrderUsable']),
    'planner stat4 partial covering current source next90 selected next source is covering index' => static fn (TestRunner $t) => $t->same('covering-index', $plan()['selectedPlan']['nextSource']),
    'planner stat4 partial covering current source next90 selected order mode' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan()['selectedPlan']['orderByMode']),
    'planner stat4 partial covering current source next90 uses stat4' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['stat4Used']),
    'planner stat4 partial covering current source next90 selected estimate tightened' => static fn (TestRunner $t) => $t->same(17, $plan()['selectedPlan']['estimatedRows']),
    'planner stat4 partial covering current source next90 selected stat4 estimate' => static fn (TestRunner $t) => $t->same(17, $plan()['selectedPlan']['stat4Estimate']),
    'planner stat4 partial covering current source next90 prepared stale estimate was narrow' => static fn (TestRunner $t) => $t->same(3, $plan()['preparedSource']['estimatedRows']),
    'planner stat4 partial covering current source next90 current estimate is selected' => static fn (TestRunner $t) => $t->same(17, $plan()['currentSource']['estimatedRows']),
    'planner stat4 partial covering current source next90 estimate delta uses current samples' => static fn (TestRunner $t) => $t->same(14, $plan()['estimatedRowsDelta']),
    'planner stat4 partial covering current source next90 stat4 estimate delta uses current samples' => static fn (TestRunner $t) => $t->same(17, $plan()['stat4EstimateDelta']),
    'planner stat4 partial covering current source next90 current matched samples' => static fn (TestRunner $t) => $t->same(4, $plan()['currentSource']['stat4MatchedSamples']),
    'planner stat4 partial covering current source next90 prepared matched samples are absent' => static fn (TestRunner $t) => $t->same(0, $plan()['preparedSource']['stat4MatchedSamples']),
    'planner stat4 partial covering current source next90 current source column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['currentSource']['stat4CurrentSourceColumn']),
    'planner stat4 partial covering current source next90 current source offset' => static fn (TestRunner $t) => $t->same(1, $plan()['currentSource']['stat4CurrentSourceOffset']),
    'planner stat4 partial covering current source next90 range lower boundary current' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']['key']),
    'planner stat4 partial covering current source next90 range lower boundary next' => static fn (TestRunner $t) => $t->same('plugin_editor', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['next']['key']),
    'planner stat4 partial covering current source next90 range upper boundary current' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 partial covering current source next90 current source summary has boundary' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan()['currentSource']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 partial covering current source next90 detail names reprepare' => static fn (TestRunner $t) => $t->same(true, str_starts_with($plan()['detail'], 'REPREPARE STAT4 PARTIAL COVERING USING CURRENT SOURCE current-after-plugin-import')),
    'planner stat4 partial covering current source next90 detail preserves covering marker' => static fn (TestRunner $t) => $t->same(true, str_contains($plan()['detail'], 'COVERING')),
    'planner stat4 partial covering current source next90 dependency includes partial order planner' => static fn (TestRunner $t) => $t->same(true, in_array('SQLitePartialIndexOrderCurrentSourcePlan', $plan()['dependencies'], true)),
    'planner stat4 partial covering current source next90 dependency includes multicolumn planner' => static fn (TestRunner $t) => $t->same(true, in_array('SQLiteMultiColumnRangePlan', $plan()['dependencies'], true)),
];

$reusePlan = static fn (): array => $plan($preparedSource(), $preparedSource());
$schemaPlan = static function () use ($plan, $preparedSource, $currentSource): array {
    $current = $currentSource();
    $current['schemaCookie'] = 15;

    return $plan($preparedSource(), $current);
};
$projectionPlan = static function () use ($plan, $preparedSource, $currentSource): array {
    $current = $currentSource();
    $current['stat4Generation'] = 40;
    $current['coveringColumns'] = ['autoload'];

    return $plan($preparedSource(), $current, null, ['autoload', 'option_value']);
};
$uncoveredPlan = static function () use ($plan, $preparedSource, $currentSource): array {
    $current = $currentSource();
    $current['indexes'][0] = [
        'name' => 'idx_blog_plugin_name_stat4_uncover_next90',
        'rootPage' => 9003,
        'estimatedRows' => 120,
        'stat4Samples' => $current['indexes'][0]['stat4Samples'],
        'sql' => "CREATE INDEX idx_blog_plugin_name_stat4_uncover_next90 ON wp_options(blog_id, option_name, autoload) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ];

    return $plan($preparedSource(), $current);
};

$tests += [
    'planner stat4 partial covering current source next90 reuses prepared when unchanged' => static fn (TestRunner $t) => $t->same('prepared', $reusePlan()['selectedSource']),
    'planner stat4 partial covering current source next90 unchanged does not reprepare' => static fn (TestRunner $t) => $t->same(false, $reusePlan()['reprepareRequired']),
    'planner stat4 partial covering current source next90 unchanged has no generation change' => static fn (TestRunner $t) => $t->same(false, $reusePlan()['stat4GenerationChanged']),
    'planner stat4 partial covering current source next90 unchanged keeps prepared estimate' => static fn (TestRunner $t) => $t->same(3, $reusePlan()['selectedPlan']['estimatedRows']),
    'planner stat4 partial covering current source next90 detects schema cookie change' => static fn (TestRunner $t) => $t->same(true, $schemaPlan()['schemaCookieChanged']),
    'planner stat4 partial covering current source next90 schema change still selects current' => static fn (TestRunner $t) => $t->same('current', $schemaPlan()['selectedSource']),
    'planner stat4 partial covering current source next90 projection change detected' => static fn (TestRunner $t) => $t->same(true, $projectionPlan()['projectionChanged']),
    'planner stat4 partial covering current source next90 projection change forces reprepare' => static fn (TestRunner $t) => $t->same(true, $projectionPlan()['reprepareRequired']),
    'planner stat4 partial covering current source next90 uncovered current summary is not covering' => static fn (TestRunner $t) => $t->same(false, $uncoveredPlan()['currentSource']['covering']),
    'planner stat4 partial covering current source next90 uncovered current defers lookup' => static fn (TestRunner $t) => $t->same('table-rowid-lookup', $uncoveredPlan()['selectedPlan']['nextSource']),
    'planner stat4 partial covering current source next90 uncovered detail defers table lookup' => static fn (TestRunner $t) => $t->same(true, str_contains($uncoveredPlan()['detail'], 'DEFER TABLE LOOKUP')),
];

$betweenPlan = static fn (): array => $plan(null, null, $and(
    $point('kind', 'plugin'),
    $point('blog_id', 1),
    $between('option_name', 'plugin_cache', 'plugin_security'),
));

$tests += [
    'planner stat4 partial covering current source next90 between selected estimate' => static fn (TestRunner $t) => $t->same(17, $betweenPlan()['selectedPlan']['estimatedRows']),
    'planner stat4 partial covering current source next90 between current matched samples' => static fn (TestRunner $t) => $t->same(4, $betweenPlan()['currentSource']['stat4MatchedSamples']),
    'planner stat4 partial covering current source next90 between lower inclusive' => static fn (TestRunner $t) => $t->same(true, $betweenPlan()['selectedPlan']['stat4RangeCurrentNext']['lowerInclusive']),
    'planner stat4 partial covering current source next90 between upper inclusive' => static fn (TestRunner $t) => $t->same(true, $betweenPlan()['selectedPlan']['stat4RangeCurrentNext']['upperInclusive']),
    'planner stat4 partial covering current source next90 between upper boundary' => static fn (TestRunner $t) => $t->same('plugin_security', $betweenPlan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
];

$invalidPrepared = static function (array $override) use ($preparedSource, $currentSource, $predicate): array {
    return SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
        array_replace($preparedSource(), $override),
        $currentSource(),
        $predicate(),
        [['column' => 'option_name']],
        ['autoload', 'option_value'],
    );
};

$tests += [
    'planner stat4 partial covering current source next90 validates prepared schema cookie' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidPrepared(['schemaCookie' => -1])),
    'planner stat4 partial covering current source next90 validates prepared stat4 generation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidPrepared(['stat4Generation' => '40'])),
    'planner stat4 partial covering current source next90 validates index list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidPrepared(['indexes' => ['bad' => []]])),
    'planner stat4 partial covering current source next90 validates covering list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidPrepared(['coveringColumns' => ['autoload', 3]])),
];

return $tests;
