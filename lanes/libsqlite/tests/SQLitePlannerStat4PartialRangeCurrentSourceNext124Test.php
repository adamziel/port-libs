<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialRangeCurrentSourceNextPlan;

$point124 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range124 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and124 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$samples124 = static fn (int $blog = 1): array => [
    ['neq' => '1 3 3', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [$blog, 'plugin_alpha', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '3 3 3', 'ndlt' => '1 1 1', 'sample' => [$blog, 'plugin_cache', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => [$blog, 'plugin_forms', 'yes']],
    ['neq' => '1 7 7', 'nlt' => '10 10 10', 'ndlt' => '3 3 3', 'sample' => [$blog, 'plugin_security', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '17 17 17', 'ndlt' => '4 4 4', 'sample' => [$blog, 'plugin_seo', 'yes']],
    ['neq' => '1 1 1', 'nlt' => '19 19 19', 'ndlt' => '5 5 5', 'sample' => [$blog, 'plugin_zeta', 'yes']],
];

$source124 = static function (array $overrides = []) use ($samples124): array {
    $lower = $overrides['partialLower'] ?? 'plugin_';
    $upper = $overrides['partialUpper'] ?? 'plugin_zzzz';
    unset($overrides['partialLower'], $overrides['partialUpper']);

    return $overrides + [
        'name' => 'prepared-plugin-range',
        'schemaCookie' => 123,
        'stat4Generation' => 44,
        'indexes' => [[
            'name' => 'idx_wp_options_blog_plugin_partial_stat4_next124',
            'rootPage' => 12401,
            'estimatedRows' => 120,
            'stat4Samples' => $samples124(),
            'sql' => "CREATE INDEX idx_wp_options_blog_plugin_partial_stat4_next124 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= '{$lower}' AND option_name < '{$upper}'",
        ]],
    ];
};

$predicate124 = static fn (string $lower = 'plugin_cache', string $upper = 'plugin_seo'): array => $and124(
    $point124('kind', 'plugin'),
    $point124('blog_id', 1),
    $range124('option_name', '>=', $lower),
    $range124('option_name', '<', $upper),
);

$plan124 = static function () use ($source124, $predicate124): array {
    return SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124(
        $source124(),
        $source124([
            'name' => 'current-plugin-range-after-analyze',
            'schemaCookie' => 124,
            'stat4Generation' => 45,
            'partialLower' => 'plugin_cache',
            'partialUpper' => 'plugin_seo',
            'indexes' => [[
                'name' => 'idx_wp_options_blog_plugin_partial_stat4_next124',
                'rootPage' => 12409,
                'estimatedRows' => 80,
                'stat4Samples' => [
                    ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
                    ['neq' => '1 4 4', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_forms', 'yes']],
                    ['neq' => '1 3 3', 'nlt' => '6 6 6', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_security', 'yes']],
                    ['neq' => '1 1 1', 'nlt' => '9 9 9', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_seo', 'yes']],
                ],
                'sql' => "CREATE INDEX idx_wp_options_blog_plugin_partial_stat4_next124 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_cache' AND option_name < 'plugin_seo'",
            ]],
        ]),
        $predicate124(),
        [['column' => 'option_name']],
        ['autoload', 'option_value'],
    );
};

$tests = [
    'planner stat4 partial range current source next124 selects current source' => static fn (TestRunner $t) => $t->same('current', $plan124()['selectedSource']),
    'planner stat4 partial range current source next124 marks stale statement' => static fn (TestRunner $t) => $t->same(true, $plan124()['stalePreparedStatement']),
    'planner stat4 partial range current source next124 requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan124()['reprepareRequired']),
    'planner stat4 partial range current source next124 detects cookie change' => static fn (TestRunner $t) => $t->same(true, $plan124()['schemaCookieChanged']),
    'planner stat4 partial range current source next124 detects stat4 change' => static fn (TestRunner $t) => $t->same(true, $plan124()['stat4GenerationChanged']),
    'planner stat4 partial range current source next124 detects index signature change' => static fn (TestRunner $t) => $t->same(true, $plan124()['indexSignatureChanged']),
    'planner stat4 partial range current source next124 detects partial range change' => static fn (TestRunner $t) => $t->same(true, $plan124()['partialRangeChanged']),
    'planner stat4 partial range current source next124 status usable' => static fn (TestRunner $t) => $t->same('usable', $plan124()['status']),
    'planner stat4 partial range current source next124 selected status usable' => static fn (TestRunner $t) => $t->same(true, $plan124()['selectedPlan']['usable']),
    'planner stat4 partial range current source next124 current name' => static fn (TestRunner $t) => $t->same('current-plugin-range-after-analyze', $plan124()['currentSource']['name']),
    'planner stat4 partial range current source next124 prepared name' => static fn (TestRunner $t) => $t->same('prepared-plugin-range', $plan124()['preparedSource']['name']),
    'planner stat4 partial range current source next124 current root page' => static fn (TestRunner $t) => $t->same(12409, $plan124()['currentSource']['rootPage']),
    'planner stat4 partial range current source next124 prepared root page' => static fn (TestRunner $t) => $t->same(12401, $plan124()['preparedSource']['rootPage']),
    'planner stat4 partial range current source next124 selected root page' => static fn (TestRunner $t) => $t->same(12409, $plan124()['selectedPlan']['rootPage']),
    'planner stat4 partial range current source next124 prepared lower range' => static fn (TestRunner $t) => $t->same('plugin_', $plan124()['preparedSource']['partialRange']['lower']),
    'planner stat4 partial range current source next124 current lower range' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan124()['currentSource']['partialRange']['lower']),
    'planner stat4 partial range current source next124 prepared upper range' => static fn (TestRunner $t) => $t->same('plugin_zzzz', $plan124()['preparedSource']['partialRange']['upper']),
    'planner stat4 partial range current source next124 current upper range' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan124()['currentSource']['partialRange']['upper']),
    'planner stat4 partial range current source next124 lower changed' => static fn (TestRunner $t) => $t->same(true, $plan124()['partialRangeDelta']['lowerChanged']),
    'planner stat4 partial range current source next124 upper changed' => static fn (TestRunner $t) => $t->same(true, $plan124()['partialRangeDelta']['upperChanged']),
    'planner stat4 partial range current source next124 flags stale range admission' => static fn (TestRunner $t) => $t->same(true, $plan124()['preparedWouldUseStalePartialRange']),
    'planner stat4 partial range current source next124 prepared stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan124()['preparedSource']['stat4Used']),
    'planner stat4 partial range current source next124 current stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan124()['currentSource']['stat4Used']),
    'planner stat4 partial range current source next124 prepared matched samples' => static fn (TestRunner $t) => $t->same(3, $plan124()['preparedSource']['stat4MatchedSamples']),
    'planner stat4 partial range current source next124 current matched samples' => static fn (TestRunner $t) => $t->same(3, $plan124()['currentSource']['stat4MatchedSamples']),
    'planner stat4 partial range current source next124 matched sample delta' => static fn (TestRunner $t) => $t->same(0, $plan124()['stat4MatchedSamplesDelta']),
    'planner stat4 partial range current source next124 prepared estimate' => static fn (TestRunner $t) => $t->same(14, $plan124()['preparedRowEstimate']),
    'planner stat4 partial range current source next124 current estimate' => static fn (TestRunner $t) => $t->same(9, $plan124()['currentRowEstimate']),
    'planner stat4 partial range current source next124 estimate delta' => static fn (TestRunner $t) => $t->same(-5, $plan124()['estimatedRowsDelta']),
    'planner stat4 partial range current source next124 selected estimate uses current' => static fn (TestRunner $t) => $t->same(9, $plan124()['selectedPlan']['estimatedRows']),
    'planner stat4 partial range current source next124 selected stat4 boundary lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan124()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']['key']),
    'planner stat4 partial range current source next124 selected stat4 boundary upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan124()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 partial range current source next124 selected matched first' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan124()['selectedPlan']['stat4MatchedCurrentNext'][0]['current']['key']),
    'planner stat4 partial range current source next124 selected matched second' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan124()['selectedPlan']['stat4MatchedCurrentNext'][1]['current']['key']),
    'planner stat4 partial range current source next124 current source column' => static fn (TestRunner $t) => $t->same('option_name', $plan124()['selectedPlan']['stat4CurrentSourceColumn']),
    'planner stat4 partial range current source next124 current source offset' => static fn (TestRunner $t) => $t->same(1, $plan124()['selectedPlan']['stat4CurrentSourceOffset']),
    'planner stat4 partial range current source next124 partial predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan124()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 partial range current source next124 order mode partial current source' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan124()['selectedPlan']['orderByMode']),
    'planner stat4 partial range current source next124 no deferred lookup covering' => static fn (TestRunner $t) => $t->same(false, $plan124()['selectedPlan']['deferredTableLookup']),
    'planner stat4 partial range current source next124 detail reparses current' => static fn (TestRunner $t) => $t->contains('REPREPARE USING CURRENT SOURCE current-plugin-range-after-analyze', $plan124()['detail']),
    'planner stat4 partial range current source next124 detail names range column' => static fn (TestRunner $t) => $t->contains('PARTIAL RANGE option_name', $plan124()['detail']),
    'planner stat4 partial range current source next124 detail keeps partial order' => static fn (TestRunner $t) => $t->contains('ORDER BY FROM PARTIAL INDEX', $plan124()['detail']),
    'planner stat4 partial range current source next124 dependency includes partial planner' => static fn (TestRunner $t) => $t->same(true, in_array('SQLitePartialIndexOrderCurrentSourcePlan', $plan124()['dependencies'], true)),
];

$tests += [
    'planner stat4 partial range current source next124 reuses prepared when identical' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $source = $source124();
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source, $source, $predicate124(), [['column' => 'option_name']], ['autoload']);
        $t->same('prepared', $plan['selectedSource']);
    },
    'planner stat4 partial range current source next124 identical source has no stale range' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $source = $source124();
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source, $source, $predicate124(), [['column' => 'option_name']], ['autoload']);
        $t->same(false, $plan['partialRangeChanged']);
    },
    'planner stat4 partial range current source next124 cookie alone reparses' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(), $source124(['schemaCookie' => 124]), $predicate124(), [['column' => 'option_name']], ['autoload']);
        $t->same(true, $plan['schemaCookieChanged']);
    },
    'planner stat4 partial range current source next124 stat4 alone reparses' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(), $source124(['stat4Generation' => 45]), $predicate124(), [['column' => 'option_name']], ['autoload']);
        $t->same(true, $plan['stat4GenerationChanged']);
    },
    'planner stat4 partial range current source next124 current unproved partial becomes unusable' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(), $source124(['schemaCookie' => 124, 'partialLower' => 'plugin_mu']), $predicate124(), [['column' => 'option_name']], ['autoload']);
        $t->same('unusable', $plan['status']);
    },
    'planner stat4 partial range current source next124 current unproved partial selects current' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(), $source124(['schemaCookie' => 124, 'partialLower' => 'plugin_mu']), $predicate124(), [['column' => 'option_name']], ['autoload']);
        $t->same('current', $plan['selectedSource']);
    },
    'planner stat4 partial range current source next124 wider predicate proves current lower' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(), $source124(['schemaCookie' => 124, 'partialLower' => 'plugin_mu']), $predicate124('plugin_mu', 'plugin_z'), [['column' => 'option_name']], ['autoload']);
        $t->same('usable', $plan['status']);
    },
    'planner stat4 partial range current source next124 validates prepared schema cookie' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(['schemaCookie' => -1]), $source124(), $predicate124(), [['column' => 'option_name']], ['autoload']));
    },
    'planner stat4 partial range current source next124 validates current stat4 generation' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($source124(), $source124(['stat4Generation' => -1]), $predicate124(), [['column' => 'option_name']], ['autoload']));
    },
    'planner stat4 partial range current source next124 validates index list' => static function (TestRunner $t) use ($source124, $predicate124): void {
        $bad = $source124();
        $bad['indexes'] = ['bad' => []];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compareNext124($bad, $source124(), $predicate124(), [['column' => 'option_name']], ['autoload']));
    },
];

return $tests;
