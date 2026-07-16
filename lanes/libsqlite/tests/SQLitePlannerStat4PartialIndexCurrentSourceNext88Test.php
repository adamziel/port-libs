<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePartialIndexOrderCurrentSourcePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$inList = static fn (string $column, array $values): array => ['operator' => 'IN', 'left' => ['column' => $column], 'values' => $values];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$samples = static fn (): array => [
    ['neq' => '1 1 1', 'nlt' => '0 0 0', 'ndlt' => '0 0 0', 'sample' => [1, 'plugin_akismet', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '1 1 1', 'ndlt' => '1 1 1', 'sample' => [1, 'plugin_cache', 'yes']],
    ['neq' => '1 4 4', 'nlt' => '3 3 3', 'ndlt' => '2 2 2', 'sample' => [1, 'plugin_editor', 'yes']],
    ['neq' => '1 8 8', 'nlt' => '7 7 7', 'ndlt' => '3 3 3', 'sample' => [1, 'plugin_forms', 'yes']],
    ['neq' => '1 3 3', 'nlt' => '15 15 15', 'ndlt' => '4 4 4', 'sample' => [1, 'plugin_security', 'yes']],
    ['neq' => '1 5 5', 'nlt' => '18 18 18', 'ndlt' => '5 5 5', 'sample' => [1, 'plugin_seo', 'yes']],
    ['neq' => '1 2 2', 'nlt' => '23 23 23', 'ndlt' => '6 6 6', 'sample' => [1, 'theme_mods', 'yes']],
    ['neq' => '1 1 1', 'nlt' => '25 25 25', 'ndlt' => '7 7 7', 'sample' => [2, 'plugin_cache', 'yes']],
    ['neq' => '1 1 1', 'nlt' => '26 26 26', 'ndlt' => '8 8 8', 'sample' => [2, 'plugin_forms', 'yes']],
];

$indexes = static fn (): array => [
    [
        'name' => 'idx_blog_plugin_name_stat4_partial_next88',
        'rootPage' => 8801,
        'estimatedRows' => 80,
        'stat4Samples' => $samples(),
        'sql' => "CREATE INDEX idx_blog_plugin_name_stat4_partial_next88 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_blog_plugin_name_plain_partial_next88',
        'rootPage' => 8802,
        'estimatedRows' => 80,
        'sql' => "CREATE INDEX idx_blog_plugin_name_plain_partial_next88 ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_blog_name_unfiltered_next88',
        'rootPage' => 8803,
        'estimatedRows' => 60,
        'sql' => 'CREATE INDEX idx_blog_name_unfiltered_next88 ON wp_options(blog_id, option_name, autoload)',
    ],
];

$stat4Plan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    [$indexes()[0]],
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $range('option_name', '>=', 'plugin_cache'),
        $range('option_name', '<', 'plugin_seo')
    ),
    [['column' => 'option_name']],
    ['autoload', 'option_value'],
);

$tests = [
    'planner stat4 partial index current source next88 chooses stat4 partial index' => static fn (TestRunner $t) => $t->same('idx_blog_plugin_name_stat4_partial_next88', $stat4Plan()['name']),
    'planner stat4 partial index current source next88 marks usable' => static fn (TestRunner $t) => $t->same(true, $stat4Plan()['usable']),
    'planner stat4 partial index current source next88 proves partial predicate' => static fn (TestRunner $t) => $t->same(true, $stat4Plan()['partialPredicateImplied']),
    'planner stat4 partial index current source next88 keeps partial order usable' => static fn (TestRunner $t) => $t->same(true, $stat4Plan()['partialIndexOrderUsable']),
    'planner stat4 partial index current source next88 uses stat4 estimate' => static fn (TestRunner $t) => $t->same(true, $stat4Plan()['stat4Used']),
    'planner stat4 partial index current source next88 exposes stat4 estimate' => static fn (TestRunner $t) => $t->same(17, $stat4Plan()['stat4Estimate']),
    'planner stat4 partial index current source next88 applies stat4 rows' => static fn (TestRunner $t) => $t->same(17, $stat4Plan()['estimatedRows']),
    'planner stat4 partial index current source next88 counts matched samples' => static fn (TestRunner $t) => $t->same(4, $stat4Plan()['stat4MatchedSamples']),
    'planner stat4 partial index current source next88 records source column' => static fn (TestRunner $t) => $t->same('option_name', $stat4Plan()['stat4CurrentSourceColumn']),
    'planner stat4 partial index current source next88 records source offset' => static fn (TestRunner $t) => $t->same(1, $stat4Plan()['stat4CurrentSourceOffset']),
    'planner stat4 partial index current source next88 keeps current range lower' => static fn (TestRunner $t) => $t->same('plugin_cache', $stat4Plan()['rangeConstraint']['values']['lower']),
    'planner stat4 partial index current source next88 keeps current range upper' => static fn (TestRunner $t) => $t->same('plugin_seo', $stat4Plan()['rangeConstraint']['values']['upper']),
    'planner stat4 partial index current source next88 exposes lower boundary current' => static fn (TestRunner $t) => $t->same('plugin_cache', $stat4Plan()['stat4RangeCurrentNext']['lower']['current']['key']),
    'planner stat4 partial index current source next88 exposes lower boundary next' => static fn (TestRunner $t) => $t->same('plugin_editor', $stat4Plan()['stat4RangeCurrentNext']['lower']['next']['key']),
    'planner stat4 partial index current source next88 exposes upper boundary current' => static fn (TestRunner $t) => $t->same('plugin_seo', $stat4Plan()['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 partial index current source next88 exposes matched current next' => static fn (TestRunner $t) => $t->same('plugin_forms', $stat4Plan()['stat4MatchedCurrentNext'][2]['current']['key']),
    'planner stat4 partial index current source next88 matched current next excludes blog two samples' => static fn (TestRunner $t) => $t->same(4, count($stat4Plan()['stat4MatchedCurrentNext'])),
    'planner stat4 partial index current source next88 full current next keeps blog one first' => static fn (TestRunner $t) => $t->same('plugin_akismet', $stat4Plan()['stat4CurrentNext'][0]['current']['key']),
    'planner stat4 partial index current source next88 full current next filters equality prefix' => static fn (TestRunner $t) => $t->same(7, count($stat4Plan()['stat4CurrentNext'])),
    'planner stat4 partial index current source next88 covers projected option value' => static fn (TestRunner $t) => $t->same(true, $stat4Plan()['covering']),
    'planner stat4 partial index current source next88 detail includes partial order' => static fn (TestRunner $t) => $t->same('SEARCH idx_blog_plugin_name_stat4_partial_next88 USING CURRENT option_name RANGE PARTIAL-PREDICATE IMPLIED ORDER BY FROM PARTIAL INDEX COVERING', $stat4Plan()['detail']),
];

$betweenPlan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    [$indexes()[0]],
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $between('option_name', 'plugin_cache', 'plugin_security')
    ),
    [['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner stat4 partial index current source next88 between estimates inclusive range' => static fn (TestRunner $t) => $t->same(17, $betweenPlan()['estimatedRows']),
    'planner stat4 partial index current source next88 between matched samples' => static fn (TestRunner $t) => $t->same(4, $betweenPlan()['stat4MatchedSamples']),
    'planner stat4 partial index current source next88 between lower inclusive' => static fn (TestRunner $t) => $t->same(true, $betweenPlan()['stat4RangeCurrentNext']['lowerInclusive']),
    'planner stat4 partial index current source next88 between upper inclusive' => static fn (TestRunner $t) => $t->same(true, $betweenPlan()['stat4RangeCurrentNext']['upperInclusive']),
    'planner stat4 partial index current source next88 between upper boundary' => static fn (TestRunner $t) => $t->same('plugin_security', $betweenPlan()['stat4RangeCurrentNext']['upper']['current']['key']),
    'planner stat4 partial index current source next88 between upper next' => static fn (TestRunner $t) => $t->same('plugin_seo', $betweenPlan()['stat4RangeCurrentNext']['upper']['next']['key']),
];

$inPlan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    [$indexes()[0]],
    $and(
        $point('kind', 'plugin'),
        $inList('blog_id', [1, 2, null, 2]),
        $range('option_name', '>=', 'plugin_cache')
    ),
    [['column' => 'blog_id'], ['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner stat4 partial index current source next88 in prefix keeps loops' => static fn (TestRunner $t) => $t->same(2, $inPlan()['currentSourceLoops']),
    'planner stat4 partial index current source next88 in prefix uses stat4' => static fn (TestRunner $t) => $t->same(true, $inPlan()['stat4Used']),
    'planner stat4 partial index current source next88 in prefix keeps both blogs' => static fn (TestRunner $t) => $t->same(9, count($inPlan()['stat4CurrentNext'])),
    'planner stat4 partial index current source next88 in prefix estimate keeps single-ended tail' => static fn (TestRunner $t) => $t->same(79, $inPlan()['estimatedRows']),
    'planner stat4 partial index current source next88 in prefix offset remains option name' => static fn (TestRunner $t) => $t->same(1, $inPlan()['stat4CurrentSourceOffset']),
];

$fallbackPlan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    [$indexes()[1]],
    $and(
        $point('kind', 'plugin'),
        $point('blog_id', 1),
        $range('option_name', '>=', 'plugin_cache')
    ),
    [['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner stat4 partial index current source next88 fallback has no stat4' => static fn (TestRunner $t) => $t->same(false, $fallbackPlan()['stat4Used']),
    'planner stat4 partial index current source next88 fallback estimate remains generic' => static fn (TestRunner $t) => $t->same(2, $fallbackPlan()['estimatedRows']),
    'planner stat4 partial index current source next88 fallback boundary null' => static fn (TestRunner $t) => $t->same(null, $fallbackPlan()['stat4RangeCurrentNext']),
    'planner stat4 partial index current source next88 fallback still proves partial' => static fn (TestRunner $t) => $t->same(true, $fallbackPlan()['partialPredicateImplied']),
];

$plainPlan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    $indexes(),
    $and(
        $point('blog_id', 1),
        $range('option_name', '>=', 'plugin_cache')
    ),
    [['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner stat4 partial index current source next88 unproved partial chooses plain index' => static fn (TestRunner $t) => $t->same('idx_blog_name_unfiltered_next88', $plainPlan()['name']),
    'planner stat4 partial index current source next88 unproved partial omits stat4' => static fn (TestRunner $t) => $t->same(false, $plainPlan()['stat4Used']),
    'planner stat4 partial index current source next88 unproved partial flag false' => static fn (TestRunner $t) => $t->same(false, $plainPlan()['partialPredicateImplied']),
];

$invalidSamples = static function (array $override) use ($indexes, $and, $point, $range): array {
    $index = $indexes()[0];
    $index = array_replace($index, $override);

    return SQLitePartialIndexOrderCurrentSourcePlan::plan(
        [$index],
        $and($point('kind', 'plugin'), $point('blog_id', 1), $range('option_name', '>=', 'plugin_cache')),
        [['column' => 'option_name']],
        ['autoload'],
    );
};

$tests += [
    'planner stat4 partial index current source next88 validates stat4 list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidSamples(['stat4Samples' => ['bad' => []]])),
    'planner stat4 partial index current source next88 validates stat4 rows' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidSamples(['stat4Samples' => ['bad']])),
    'planner stat4 partial index current source next88 validates current source sample offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidSamples(['stat4Samples' => [['neq' => '1 1', 'nlt' => '0 0', 'sample' => [1]]]])),
    'planner stat4 partial index current source next88 validates neq offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidSamples(['stat4Samples' => [['neq' => [1, 0], 'nlt' => [0, 0], 'sample' => [1, 'plugin_cache']]]])),
    'planner stat4 partial index current source next88 validates nlt offset' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $invalidSamples(['stat4Samples' => [['neq' => [1, 1], 'nlt' => [0, -1], 'sample' => [1, 'plugin_cache']]]])),
];

return $tests;
