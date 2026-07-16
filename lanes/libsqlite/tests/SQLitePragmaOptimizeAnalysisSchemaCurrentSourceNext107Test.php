<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaOptimizePlan;

$tables = [
    [
        'schema' => 'main',
        'name' => 'wp_options',
        'rowCount' => 12000,
        'statRowCount' => 8000,
        'touched' => true,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v41',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_postmeta',
        'rowCount' => 240000,
        'statRowCount' => 240000,
        'touched' => false,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v41',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_posts',
        'rowCount' => 20000,
        'hasStat' => false,
        'touched' => false,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v41',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_usermeta',
        'rowCount' => 50000,
        'statRowCount' => 10000,
        'touched' => true,
        'schemaCookie' => 42,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v42',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_termmeta',
        'rowCount' => 2000,
        'statRowCount' => 100,
        'touched' => true,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 8,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v41',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_commentmeta',
        'rowCount' => 2000,
        'statRowCount' => 100,
        'touched' => true,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-schema-v41b',
        'expectedSourceId' => 'main-schema-v41',
    ],
    [
        'schema' => 'network',
        'name' => 'wp_sitemeta',
        'rowCount' => 1000,
        'statRowCount' => 10,
        'touched' => true,
        'schemaCookie' => 9,
        'expectedSchemaCookie' => 9,
        'statCookie' => 3,
        'expectedStatCookie' => 3,
        'sourceId' => 'network-schema-v9',
        'expectedSourceId' => 'network-schema-v9',
    ],
    [
        'schema' => 'network',
        'name' => 'wp_blogmeta',
        'rowCount' => 1000,
        'statRowCount' => 10,
        'touched' => true,
        'schemaCookie' => 10,
        'expectedSchemaCookie' => 9,
        'statCookie' => 3,
        'expectedStatCookie' => 3,
        'sourceId' => 'network-schema-v10',
        'expectedSourceId' => 'network-schema-v9',
    ],
];

$tests = [];

$mainResult = static fn (): array => (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', $tables);
$networkResult = static fn (): array => (new SQLitePragmaOptimizePlan())->execute('PRAGMA network.optimize', $tables);
$forceResult = static fn (): array => (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize=0x10000', $tables);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$mainCases = [
    'main result exposes current source block' => [$mainResult, 'currentSource.schema', 'main'],
    'main source is marked stale when any table changed' => [$mainResult, 'currentSource.stable', false],
    'main records all current schema tables' => [$mainResult, 'currentSource.tables.count', 6],
    'main analyze keeps stable touched table' => [$mainResult, 'analyze.0.table', 'wp_options'],
    'main analyze records touched-table reason' => [$mainResult, 'analyze.0.reason', 'touched-table'],
    'main analyze carries source token' => [$mainResult, 'analyze.0.currentSource', 'main:wp_options:schema=41:stat=7:source=main-schema-v41'],
    'main stable missing stat table still schedules analyze' => [$mainResult, 'analyze.1.table', 'wp_posts'],
    'main missing stat reason survives source checks' => [$mainResult, 'analyze.1.reason', 'missing-stat1'],
    'main up to date stable table is skipped' => [$mainResult, 'skipped.0.table', 'wp_postmeta'],
    'main up to date skip reason remains distinct' => [$mainResult, 'skipped.0.reason', 'up-to-date'],
    'main stale schema-cookie table is skipped' => [$mainResult, 'skipped.1.table', 'wp_usermeta'],
    'main stale schema-cookie skip reason' => [$mainResult, 'skipped.1.reason', 'stale-current-source'],
    'main stale stat-cookie table is skipped' => [$mainResult, 'skipped.2.table', 'wp_termmeta'],
    'main stale stat-cookie skip reason' => [$mainResult, 'skipped.2.reason', 'stale-current-source'],
    'main stale source-id table is skipped' => [$mainResult, 'skipped.3.table', 'wp_commentmeta'],
    'main stale source-id skip reason' => [$mainResult, 'skipped.3.reason', 'stale-current-source'],
    'main stale list records schema-cookie table' => [$mainResult, 'currentSource.stale.0.table', 'wp_usermeta'],
    'main stale list records schema-cookie reason' => [$mainResult, 'currentSource.stale.0.reason', 'schema-cookie'],
    'main stale list records stat-cookie table' => [$mainResult, 'currentSource.stale.1.table', 'wp_termmeta'],
    'main stale list records stat-cookie reason' => [$mainResult, 'currentSource.stale.1.reason', 'sqlite-stat1-cookie'],
    'main stale list records source-id table' => [$mainResult, 'currentSource.stale.2.table', 'wp_commentmeta'],
    'main stale list records source-id reason' => [$mainResult, 'currentSource.stale.2.reason', 'source-id'],
    'main table source stores schema cookie' => [$mainResult, 'currentSource.tables.wp_options.schemaCookie', 41],
    'main table source stores expected schema cookie' => [$mainResult, 'currentSource.tables.wp_options.expectedSchemaCookie', 41],
    'main table source stores stat cookie' => [$mainResult, 'currentSource.tables.wp_options.statCookie', 7],
    'main table source stores expected stat cookie' => [$mainResult, 'currentSource.tables.wp_options.expectedStatCookie', 7],
    'main table source stores source id' => [$mainResult, 'currentSource.tables.wp_options.sourceId', 'main-schema-v41'],
    'main table source stores expected source id' => [$mainResult, 'currentSource.tables.wp_options.expectedSourceId', 'main-schema-v41'],
    'main stale table source unstable' => [$mainResult, 'currentSource.tables.wp_usermeta.stable', false],
    'main stale table source reason' => [$mainResult, 'currentSource.tables.wp_usermeta.staleReason', 'schema-cookie'],
    'main stable table source stable' => [$mainResult, 'currentSource.tables.wp_options.stable', true],
    'main stable table source has null stale reason' => [$mainResult, 'currentSource.tables.wp_options.staleReason', null],
    'main dependencies include current source' => [$mainResult, 'dependencies.3', 'current-source'],
    'main optimize still returns no pragma rows' => [$mainResult, 'rows', []],
];

foreach ($mainCases as $name => [$factory, $path, $expected]) {
    $tests['pragma optimize current source ' . $name] = static function (TestRunner $t) use ($factory, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$networkCases = [
    'network schema is isolated' => [$networkResult, 'currentSource.schema', 'network'],
    'network sees only attached schema tables' => [$networkResult, 'currentSource.tables.count', 2],
    'network stable table analyzes' => [$networkResult, 'analyze.0.table', 'wp_sitemeta'],
    'network stable table reason is touched' => [$networkResult, 'analyze.0.reason', 'touched-table'],
    'network stale table skipped' => [$networkResult, 'skipped.0.table', 'wp_blogmeta'],
    'network stale table skip reason' => [$networkResult, 'skipped.0.reason', 'stale-current-source'],
    'network stale reason is schema cookie' => [$networkResult, 'currentSource.stale.0.reason', 'schema-cookie'],
    'network source token uses attached schema' => [$networkResult, 'analyze.0.currentSource', 'network:wp_sitemeta:schema=9:stat=3:source=network-schema-v9'],
];

foreach ($networkCases as $name => [$factory, $path, $expected]) {
    $tests['pragma optimize attached current source ' . $name] = static function (TestRunner $t) use ($factory, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$forceCases = [
    'force mask does not override schema-cookie stale guard' => [$forceResult, 'skipped.0.table', 'wp_usermeta'],
    'force mask does not override stat-cookie stale guard' => [$forceResult, 'skipped.1.table', 'wp_termmeta'],
    'force mask does not override source-id stale guard' => [$forceResult, 'skipped.2.table', 'wp_commentmeta'],
    'force mask still analyzes stable postmeta' => [$forceResult, 'analyze.1.table', 'wp_postmeta'],
    'force mask stable postmeta reason' => [$forceResult, 'analyze.1.reason', 'force-all'],
    'force mask keeps stale aggregate false' => [$forceResult, 'currentSource.stable', false],
];

foreach ($forceCases as $name => [$factory, $path, $expected]) {
    $tests['pragma optimize force current source ' . $name] = static function (TestRunner $t) use ($factory, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$guardCases = [
    'schema cookie rejects non integer text' => ['schemaCookie' => 'forty-one'],
    'expected schema cookie rejects non integer text' => ['expectedSchemaCookie' => 'forty-one'],
    'stat cookie rejects non integer text' => ['statCookie' => 'seven'],
    'expected stat cookie rejects non integer text' => ['expectedStatCookie' => 'seven'],
    'source id rejects empty text' => ['sourceId' => ''],
    'expected source id rejects empty text' => ['expectedSourceId' => ''],
    'source id rejects non string' => ['sourceId' => 123],
    'expected source id rejects non string' => ['expectedSourceId' => 123],
];

foreach ($guardCases as $name => $override) {
    $tests['pragma optimize current source guard ' . $name] = static function (TestRunner $t) use ($override): void {
        $row = [
            'schema' => 'main',
            'name' => 'wp_options',
            'rowCount' => 1,
            'statRowCount' => 1,
        ] + $override;
        $t->throws(InvalidArgumentException::class, static fn () => (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', [$row]));
    };
}

return $tests;
