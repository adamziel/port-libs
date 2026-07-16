<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4PartialRangeCurrentSourceNextPlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$samples = static fn (int $blog = 1): array => [
    ['neq' => '1 3 3', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [$blog, 'plugin_alpha', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '3 3 3', 'ndlt' => '1 1 1', 'sample' => [$blog, 'plugin_cache', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '5 5 5', 'ndlt' => '2 2 2', 'sample' => [$blog, 'plugin_forms', 'yes']],
    ['neq' => '1 7 7', 'nlt' => '10 10 10', 'ndlt' => '3 3 3', 'sample' => [$blog, 'plugin_security', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '17 17 17', 'ndlt' => '4 4 4', 'sample' => [$blog, 'plugin_seo', 'yes']],
    ['neq' => '1 1 1', 'nlt' => '19 19 19', 'ndlt' => '5 5 5', 'sample' => [$blog, 'plugin_zeta', 'yes']],
];

$source = static function (array $overrides = []) use ($samples): array {
    $lower = $overrides['partialLower'] ?? 'plugin_';
    $upper = $overrides['partialUpper'] ?? 'plugin_zzzz';
    unset($overrides['partialLower'], $overrides['partialUpper']);

    return $overrides + [
        'name' => 'prepared-plugin-range',
        'schemaCookie' => 123,
        'stat4Generation' => 44,
        'indexes' => [[
            'name' => 'idx_wp_options_blog_plugin_partial_stat4_stable',
            'rootPage' => 12501,
            'estimatedRows' => 120,
            'stat4Samples' => $samples(),
            'sql' => "CREATE INDEX idx_wp_options_blog_plugin_partial_stat4_stable ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= '{$lower}' AND option_name < '{$upper}'",
        ]],
    ];
};

$predicate = static fn (string $lower = 'plugin_cache', string $upper = 'plugin_seo'): array => $and(
    $point('kind', 'plugin'),
    $point('blog_id', 1),
    $range('option_name', '>=', $lower),
    $range('option_name', '<', $upper),
);

$plan = static function () use ($source, $predicate): array {
    return SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare(
        $source(),
        $source([
            'name' => 'current-plugin-range-after-analyze',
            'schemaCookie' => 125,
            'stat4Generation' => 45,
            'partialLower' => 'plugin_cache',
            'partialUpper' => 'plugin_seo',
            'indexes' => [[
                'name' => 'idx_wp_options_blog_plugin_partial_stat4_stable',
                'rootPage' => 12509,
                'estimatedRows' => 80,
                'stat4Samples' => [
                    ['neq' => '1 2 2', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_cache', 'yes']],
                    ['neq' => '1 4 4', 'nlt' => '2 2 2', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_forms', 'yes']],
                    ['neq' => '1 3 3', 'nlt' => '6 6 6', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_security', 'yes']],
                    ['neq' => '1 1 1', 'nlt' => '9 9 9', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_seo', 'yes']],
                ],
                'sql' => "CREATE INDEX idx_wp_options_blog_plugin_partial_stat4_stable ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_cache' AND option_name < 'plugin_seo'",
            ]],
        ]),
        $predicate(),
        [['column' => 'option_name']],
        ['autoload', 'option_value'],
    );
};

$tests = [
    'planner stat4 partial range current source stable selects current source' => static fn (TestRunner $t) => $t->same('current', $plan()['selectedSource']),
    'planner stat4 partial range current source stable marks stale statement' => static fn (TestRunner $t) => $t->same(true, $plan()['stalePreparedStatement']),
    'planner stat4 partial range current source stable requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'planner stat4 partial range current source stable detects cookie change' => static fn (TestRunner $t) => $t->same(true, $plan()['schemaCookieChanged']),
    'planner stat4 partial range current source stable detects stat4 change' => static fn (TestRunner $t) => $t->same(true, $plan()['stat4GenerationChanged']),
    'planner stat4 partial range current source stable detects index signature change' => static fn (TestRunner $t) => $t->same(true, $plan()['indexSignatureChanged']),
    'planner stat4 partial range current source stable detects partial range change' => static fn (TestRunner $t) => $t->same(true, $plan()['partialRangeChanged']),
    'planner stat4 partial range current source stable status usable' => static fn (TestRunner $t) => $t->same('usable', $plan()['status']),
    'planner stat4 partial range current source stable selected status usable' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['usable']),
    'planner stat4 partial range current source stable current name' => static fn (TestRunner $t) => $t->same('current-plugin-range-after-analyze', $plan()['currentSource']['name']),
    'planner stat4 partial range current source stable prepared name' => static fn (TestRunner $t) => $t->same('prepared-plugin-range', $plan()['preparedSource']['name']),
    'planner stat4 partial range current source stable current root page' => static fn (TestRunner $t) => $t->same(12509, $plan()['currentSource']['rootPage']),
    'planner stat4 partial range current source stable prepared root page' => static fn (TestRunner $t) => $t->same(12501, $plan()['preparedSource']['rootPage']),
    'planner stat4 partial range current source stable selected root page' => static fn (TestRunner $t) => $t->same(12509, $plan()['selectedPlan']['rootPage']),
    'planner stat4 partial range current source stable prepared lower range' => static fn (TestRunner $t) => $t->same('plugin_', $plan()['preparedSource']['partialRange']['lower']),
    'planner stat4 partial range current source stable current lower range' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan()['currentSource']['partialRange']['lower']),
    'planner stat4 partial range current source stable prepared upper range' => static fn (TestRunner $t) => $t->same('plugin_zzzz', $plan()['preparedSource']['partialRange']['upper']),
    'planner stat4 partial range current source stable current upper range' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan()['currentSource']['partialRange']['upper']),
    'planner stat4 partial range current source stable lower changed' => static fn (TestRunner $t) => $t->same(true, $plan()['partialRangeDelta']['lowerChanged']),
    'planner stat4 partial range current source stable upper changed' => static fn (TestRunner $t) => $t->same(true, $plan()['partialRangeDelta']['upperChanged']),
    'planner stat4 partial range current source stable flags stale range admission' => static fn (TestRunner $t) => $t->same(true, $plan()['preparedWouldUseStalePartialRange']),
    'planner stat4 partial range current source stable prepared stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['preparedSource']['stat4Used']),
    'planner stat4 partial range current source stable current stat4 used' => static fn (TestRunner $t) => $t->same(true, $plan()['currentSource']['stat4Used']),
    'planner stat4 partial range current source stable prepared matched samples' => static fn (TestRunner $t) => $t->same(3, $plan()['preparedSource']['stat4MatchedSamples']),
    'planner stat4 partial range current source stable current matched samples' => static fn (TestRunner $t) => $t->same(3, $plan()['currentSource']['stat4MatchedSamples']),
    'planner stat4 partial range current source stable matched sample delta' => static fn (TestRunner $t) => $t->same(0, $plan()['stat4MatchedSamplesDelta']),
    'planner stat4 partial range current source stable prepared estimate' => static fn (TestRunner $t) => $t->same(14, $plan()['preparedRowEstimate']),
    'planner stat4 partial range current source stable current estimate' => static fn (TestRunner $t) => $t->same(9, $plan()['currentRowEstimate']),
    'planner stat4 partial range current source stable estimate delta' => static fn (TestRunner $t) => $t->same(-5, $plan()['estimatedRowsDelta']),
    'planner stat4 partial range current source stable selected estimate uses current' => static fn (TestRunner $t) => $t->same(9, $plan()['selectedPlan']['estimatedRows']),
    'planner stat4 partial range current source stable selected stat4 boundary lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan()['selectedPlan']['stat4RangeCurrentNext']['lower']['current']['key']),
    'planner stat4 partial range current source stable selected stat4 boundary upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $plan()['selectedPlan']['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 partial range current source stable selected matched first' => static fn (TestRunner $t) => $t->same('plugin_cache', $plan()['selectedPlan']['stat4MatchedCurrentNext'][0]['current']['key']),
    'planner stat4 partial range current source stable selected matched second' => static fn (TestRunner $t) => $t->same('plugin_forms', $plan()['selectedPlan']['stat4MatchedCurrentNext'][1]['current']['key']),
    'planner stat4 partial range current source stable current source column' => static fn (TestRunner $t) => $t->same('option_name', $plan()['selectedPlan']['stat4CurrentSourceColumn']),
    'planner stat4 partial range current source stable current source offset' => static fn (TestRunner $t) => $t->same(1, $plan()['selectedPlan']['stat4CurrentSourceOffset']),
    'planner stat4 partial range current source stable partial predicate implied' => static fn (TestRunner $t) => $t->same(true, $plan()['selectedPlan']['partialPredicateImplied']),
    'planner stat4 partial range current source stable order mode partial current source' => static fn (TestRunner $t) => $t->same('partial-current-source', $plan()['selectedPlan']['orderByMode']),
    'planner stat4 partial range current source stable no deferred lookup covering' => static fn (TestRunner $t) => $t->same(false, $plan()['selectedPlan']['deferredTableLookup']),
    'planner stat4 partial range current source stable detail reparses current' => static fn (TestRunner $t) => $t->contains('REPREPARE USING CURRENT SOURCE current-plugin-range-after-analyze', $plan()['detail']),
    'planner stat4 partial range current source stable detail names range column' => static fn (TestRunner $t) => $t->contains('PARTIAL RANGE option_name', $plan()['detail']),
    'planner stat4 partial range current source stable detail keeps partial order' => static fn (TestRunner $t) => $t->contains('ORDER BY FROM PARTIAL INDEX', $plan()['detail']),
    'planner stat4 partial range current source stable dependency includes partial planner' => static fn (TestRunner $t) => $t->same(true, in_array('SQLitePartialIndexOrderCurrentSourcePlan', $plan()['dependencies'], true)),
];

$tests += [
    'planner stat4 partial range current source stable reuses prepared when identical' => static function (TestRunner $t) use ($source, $predicate): void {
        $source = $source();
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source, $source, $predicate(), [['column' => 'option_name']], ['autoload']);
        $t->same('prepared', $plan['selectedSource']);
    },
    'planner stat4 partial range current source stable identical source has no stale range' => static function (TestRunner $t) use ($source, $predicate): void {
        $source = $source();
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source, $source, $predicate(), [['column' => 'option_name']], ['autoload']);
        $t->same(false, $plan['partialRangeChanged']);
    },
    'planner stat4 partial range current source stable cookie alone reparses' => static function (TestRunner $t) use ($source, $predicate): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(), $source(['schemaCookie' => 125]), $predicate(), [['column' => 'option_name']], ['autoload']);
        $t->same(true, $plan['schemaCookieChanged']);
    },
    'planner stat4 partial range current source stable stat4 alone reparses' => static function (TestRunner $t) use ($source, $predicate): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(), $source(['stat4Generation' => 45]), $predicate(), [['column' => 'option_name']], ['autoload']);
        $t->same(true, $plan['stat4GenerationChanged']);
    },
    'planner stat4 partial range current source stable current unproved partial becomes unusable' => static function (TestRunner $t) use ($source, $predicate): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(), $source(['schemaCookie' => 125, 'partialLower' => 'plugin_mu']), $predicate(), [['column' => 'option_name']], ['autoload']);
        $t->same('unusable', $plan['status']);
    },
    'planner stat4 partial range current source stable current unproved partial selects current' => static function (TestRunner $t) use ($source, $predicate): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(), $source(['schemaCookie' => 125, 'partialLower' => 'plugin_mu']), $predicate(), [['column' => 'option_name']], ['autoload']);
        $t->same('current', $plan['selectedSource']);
    },
    'planner stat4 partial range current source stable wider predicate proves current lower' => static function (TestRunner $t) use ($source, $predicate): void {
        $plan = SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(), $source(['schemaCookie' => 125, 'partialLower' => 'plugin_mu']), $predicate('plugin_mu', 'plugin_z'), [['column' => 'option_name']], ['autoload']);
        $t->same('usable', $plan['status']);
    },
    'planner stat4 partial range current source stable validates prepared schema cookie' => static function (TestRunner $t) use ($source, $predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(['schemaCookie' => -1]), $source(), $predicate(), [['column' => 'option_name']], ['autoload']));
    },
    'planner stat4 partial range current source stable validates current stat4 generation' => static function (TestRunner $t) use ($source, $predicate): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($source(), $source(['stat4Generation' => -1]), $predicate(), [['column' => 'option_name']], ['autoload']));
    },
    'planner stat4 partial range current source stable validates index list' => static function (TestRunner $t) use ($source, $predicate): void {
        $bad = $source();
        $bad['indexes'] = ['bad' => []];
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePlannerStat4PartialRangeCurrentSourceNextPlan::compare($bad, $source(), $predicate(), [['column' => 'option_name']], ['autoload']));
    },
];

return $tests;
