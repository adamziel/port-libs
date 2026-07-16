<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePartialIndexOrderCurrentSourcePlan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$inList = static fn (string $column, array $values): array => ['operator' => 'IN', 'left' => ['column' => $column], 'values' => $values];

$indexes = static fn (): array => [
    [
        'name' => 'idx_plugin_blog_name_cover',
        'rootPage' => 8501,
        'estimatedRows' => 2400,
        'sql' => "CREATE INDEX idx_plugin_blog_name_cover ON wp_options(blog_id, option_name, autoload, option_value) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_plugin_blog_name_desc',
        'rootPage' => 8502,
        'estimatedRows' => 2400,
        'sql' => "CREATE INDEX idx_plugin_blog_name_desc ON wp_options(blog_id, option_name DESC, autoload) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'idx_plain_blog_name',
        'rootPage' => 8503,
        'estimatedRows' => 1600,
        'sql' => 'CREATE INDEX idx_plain_blog_name ON wp_options(blog_id, option_name, autoload)',
    ],
    [
        'name' => 'idx_plugin_autoload_name',
        'rootPage' => 8504,
        'estimatedRows' => 2200,
        'sql' => "CREATE INDEX idx_plugin_autoload_name ON wp_options(autoload, option_name, option_id) WHERE kind = 'plugin' AND option_name >= 'plugin_'",
    ],
];

$predicate = static fn (): array => $and(
    $point('kind', 'plugin'),
    $point('blog_id', 1),
    $range('option_name', '>=', 'plugin_'),
    $range('autoload', '<', 'z'),
);

$covered = static fn (array $orderBy = [['column' => 'option_name']], array $needed = ['autoload', 'option_value']): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    $indexes(),
    $predicate(),
    $orderBy,
    $needed,
);

$tests = [
    'planner partial index order current source next85 uses partial index' => static fn (TestRunner $t) => $t->same('idx_plugin_blog_name_cover', $covered()['name']),
    'planner partial index order current source next85 is usable' => static fn (TestRunner $t) => $t->same('usable', $covered()['status']),
    'planner partial index order current source next85 marks usable bool' => static fn (TestRunner $t) => $t->same(true, $covered()['usable']),
    'planner partial index order current source next85 proves partial predicate' => static fn (TestRunner $t) => $t->same(true, $covered()['partialPredicateImplied']),
    'planner partial index order current source next85 marks partial order usable' => static fn (TestRunner $t) => $t->same(true, $covered()['partialIndexOrderUsable']),
    'planner partial index order current source next85 satisfies order' => static fn (TestRunner $t) => $t->same(true, $covered()['orderBySatisfied']),
    'planner partial index order current source next85 avoids block sort' => static fn (TestRunner $t) => $t->same(false, $covered()['blockSortRequired']),
    'planner partial index order current source next85 reports current source' => static fn (TestRunner $t) => $t->same('partial-index-order', $covered()['currentSource']),
    'planner partial index order current source next85 reports covering next source' => static fn (TestRunner $t) => $t->same('covering-index', $covered()['nextSource']),
    'planner partial index order current source next85 avoids deferred table lookup' => static fn (TestRunner $t) => $t->same(false, $covered()['deferredTableLookup']),
    'planner partial index order current source next85 keeps residual predicate' => static fn (TestRunner $t) => $t->same(true, $covered()['nextResidualPredicateRequired']),
    'planner partial index order current source next85 reports mode' => static fn (TestRunner $t) => $t->same('partial-current-source', $covered()['orderByMode']),
    'planner partial index order current source next85 keeps root page' => static fn (TestRunner $t) => $t->same(8501, $covered()['rootPage']),
    'planner partial index order current source next85 keeps current columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name'], $covered()['currentSourceColumns']),
    'planner partial index order current source next85 keeps order columns' => static fn (TestRunner $t) => $t->same(['option_name'], $covered()['currentSourceOrderColumns']),
    'planner partial index order current source next85 keeps range column' => static fn (TestRunner $t) => $t->same('option_name', $covered()['currentSourceRangeColumn']),
    'planner partial index order current source next85 keeps current range constraint' => static fn (TestRunner $t) => $t->same('range->=', $covered()['rangeConstraint']['operator']),
    'planner partial index order current source next85 keeps next residual column' => static fn (TestRunner $t) => $t->same(['autoload'], $covered()['residualRangeColumns']),
    'planner partial index order current source next85 keeps next residual operator' => static fn (TestRunner $t) => $t->same('range-<', $covered()['residualConstraints'][0]['operator']),
    'planner partial index order current source next85 reports one current loop' => static fn (TestRunner $t) => $t->same(1, $covered()['currentSourceLoops']),
    'planner partial index order current source next85 considers ranked candidates' => static fn (TestRunner $t) => $t->same(4, $covered()['candidateCount']),
    'planner partial index order current source next85 marks covering' => static fn (TestRunner $t) => $t->same(true, $covered()['covering']),
    'planner partial index order current source next85 detail names order source' => static fn (TestRunner $t) => $t->same('SEARCH idx_plugin_blog_name_cover USING CURRENT option_name RANGE PARTIAL-PREDICATE IMPLIED ORDER BY FROM PARTIAL INDEX COVERING NEXT RESIDUAL', $covered()['detail']),
];

$tests += [
    'planner partial index order current source next85 desc chooses desc partial' => static fn (TestRunner $t) => $t->same('idx_plugin_blog_name_desc', $covered([['column' => 'option_name', 'direction' => 'DESC']], ['autoload'])['name']),
    'planner partial index order current source next85 desc mode remains current source' => static fn (TestRunner $t) => $t->same('partial-current-source', $covered([['column' => 'option_name', 'direction' => 'DESC']], ['autoload'])['orderByMode']),
    'planner partial index order current source next85 desc avoids sort' => static fn (TestRunner $t) => $t->same(false, $covered([['column' => 'option_name', 'direction' => 'DESC']], ['autoload'])['blockSortRequired']),
    'planner partial index order current source next85 asc rejects desc index when asc cover exists' => static fn (TestRunner $t) => $t->same('idx_plugin_blog_name_cover', $covered([['column' => 'option_name']], ['autoload'])['name']),
    'planner partial index order current source next85 order by residual needs sort' => static fn (TestRunner $t) => $t->same('next-temp-sort', $covered([['column' => 'option_value']], ['autoload'])['orderByMode']),
    'planner partial index order current source next85 residual order blocks partial order' => static fn (TestRunner $t) => $t->same(true, $covered([['column' => 'option_value']], ['autoload'])['blockSortRequired']),
    'planner partial index order current source next85 residual order keeps index range source' => static fn (TestRunner $t) => $t->same('index-range', $covered([['column' => 'option_value']], ['autoload'])['currentSource']),
    'planner partial index order current source next85 uncovered projection defers lookup' => static fn (TestRunner $t) => $t->same(true, $covered([['column' => 'option_name']], ['missing_column'])['deferredTableLookup']),
    'planner partial index order current source next85 uncovered projection names table lookup' => static fn (TestRunner $t) => $t->same('table-rowid-lookup', $covered([['column' => 'option_name']], ['missing_column'])['nextSource']),
    'planner partial index order current source next85 uncovered detail keeps defer marker' => static fn (TestRunner $t) => $t->same('SEARCH idx_plugin_blog_name_cover USING CURRENT option_name RANGE PARTIAL-PREDICATE IMPLIED ORDER BY FROM PARTIAL INDEX DEFER TABLE LOOKUP NEXT RESIDUAL', $covered([['column' => 'option_name']], ['missing_column'])['detail']),
    'planner partial index order current source next85 no order has none mode' => static fn (TestRunner $t) => $t->same('none', $covered([], ['autoload'])['orderByMode']),
    'planner partial index order current source next85 no order does not claim partial order' => static fn (TestRunner $t) => $t->same(false, $covered([], ['autoload'])['partialIndexOrderUsable']),
    'planner partial index order current source next85 no order avoids sort' => static fn (TestRunner $t) => $t->same(false, $covered([], ['autoload'])['blockSortRequired']),
    'planner partial index order current source next85 validates order column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $covered([['column' => '']], ['autoload'])),
    'planner partial index order current source next85 validates order direction' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $covered([['column' => 'option_name', 'direction' => 'SIDEWAYS']], ['autoload'])),
];

$inPlan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    $indexes(),
    $and(
        $point('kind', 'plugin'),
        $inList('blog_id', [1, 2, null, 2]),
        $between('option_name', 'plugin_', 'plugin_zzzz'),
    ),
    [['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner partial index order current source next85 in-list keeps partial order' => static fn (TestRunner $t) => $t->same(true, $inPlan()['partialIndexOrderUsable']),
    'planner partial index order current source next85 in-list loops unique nonnull values' => static fn (TestRunner $t) => $t->same(2, $inPlan()['currentSourceLoops']),
    'planner partial index order current source next85 in-list uses between range' => static fn (TestRunner $t) => $t->same('BETWEEN', $inPlan()['rangeConstraint']['operator']),
    'planner partial index order current source next85 in-list keeps lower bound' => static fn (TestRunner $t) => $t->same('plugin_', $inPlan()['rangeConstraint']['values']['lower']),
    'planner partial index order current source next85 in-list keeps upper bound' => static fn (TestRunner $t) => $t->same('plugin_zzzz', $inPlan()['rangeConstraint']['values']['upper']),
    'planner partial index order current source next85 in-list current columns' => static fn (TestRunner $t) => $t->same(['blog_id', 'option_name'], $inPlan()['currentSourceColumns']),
];

$plainPlan = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    $indexes(),
    $and($point('blog_id', 1), $range('option_name', '>=', 'plugin_')),
    [['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner partial index order current source next85 unproved partial falls back to plain index' => static fn (TestRunner $t) => $t->same('idx_plain_blog_name', $plainPlan()['name']),
    'planner partial index order current source next85 plain plan does not prove partial' => static fn (TestRunner $t) => $t->same(false, $plainPlan()['partialPredicateImplied']),
    'planner partial index order current source next85 plain order is index source' => static fn (TestRunner $t) => $t->same('index-current-source', $plainPlan()['orderByMode']),
    'planner partial index order current source next85 plain source not partial order' => static fn (TestRunner $t) => $t->same(false, $plainPlan()['partialIndexOrderUsable']),
    'planner partial index order current source next85 plain detail omits partial marker' => static fn (TestRunner $t) => $t->same('SEARCH idx_plain_blog_name USING CURRENT option_name RANGE COVERING', $plainPlan()['detail']),
];

$unusable = static fn (): array => SQLitePartialIndexOrderCurrentSourcePlan::plan(
    [$indexes()[0]],
    $and($point('kind', 'plugin'), $range('option_name', '>=', 'plugin_')),
    [['column' => 'option_name']],
    ['autoload'],
);

$tests += [
    'planner partial index order current source next85 unusable without leading constraint' => static fn (TestRunner $t) => $t->same('unusable', $unusable()['status']),
    'planner partial index order current source next85 unusable marks false' => static fn (TestRunner $t) => $t->same(false, $unusable()['usable']),
    'planner partial index order current source next85 unusable blocks sort' => static fn (TestRunner $t) => $t->same(true, $unusable()['blockSortRequired']),
    'planner partial index order current source next85 unusable detail' => static fn (TestRunner $t) => $t->same('SCAN TABLE; NO USABLE PARTIAL INDEX ORDER', $unusable()['detail']),
];

$orderCases = [
    'option name asc' => [[['column' => 'option_name']], 'partial-current-source', false],
    'option name desc' => [[['column' => 'option_name', 'direction' => 'DESC']], 'partial-current-source', false],
    'autoload asc' => [[['column' => 'autoload']], 'partial-current-source', false],
    'option value asc' => [[['column' => 'option_value']], 'next-temp-sort', true],
    'option name plus autoload' => [[['column' => 'option_name'], ['column' => 'autoload']], 'partial-current-source', false],
];

foreach ($orderCases as $label => [$orderBy, $expectedMode, $expectedSort]) {
    $tests["planner partial index order current source next85 {$label} mode"] = static function (TestRunner $t) use ($covered, $orderBy, $expectedMode): void {
        $t->same($expectedMode, $covered($orderBy, ['autoload'])['orderByMode']);
    };
    $tests["planner partial index order current source next85 {$label} sort flag"] = static function (TestRunner $t) use ($covered, $orderBy, $expectedSort): void {
        $t->same($expectedSort, $covered($orderBy, ['autoload'])['blockSortRequired']);
    };
}

return $tests;
