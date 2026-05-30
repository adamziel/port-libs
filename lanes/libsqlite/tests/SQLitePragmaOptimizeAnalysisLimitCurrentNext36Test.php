<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaOptimizePlan;

$tables = [
    ['schema' => 'main', 'name' => 'wp_options', 'rowCount' => 12000, 'statRowCount' => 8000, 'touched' => true],
    ['schema' => 'main', 'name' => 'wp_postmeta', 'rowCount' => 240000, 'statRowCount' => 240000, 'touched' => false],
    ['schema' => 'main', 'name' => 'wp_posts', 'rowCount' => 20000, 'hasStat' => false, 'touched' => false],
    ['schema' => 'aux', 'name' => 'wp_options', 'rowCount' => 90, 'statRowCount' => 10, 'touched' => true],
    ['schema' => 'temp', 'name' => 'wp_tmp', 'rowCount' => 30, 'statRowCount' => 30, 'touched' => false],
];

$tests = [];

$parseCases = [
    'analysis_limit query' => ['PRAGMA analysis_limit', ['pragma' => 'analysis_limit', 'schema' => 'main', 'argument' => null]],
    'analysis_limit equals integer' => ['PRAGMA analysis_limit=400', ['pragma' => 'analysis_limit', 'schema' => 'main', 'argument' => '400']],
    'analysis_limit parenthesized integer' => ['PRAGMA analysis_limit(25)', ['pragma' => 'analysis_limit', 'schema' => 'main', 'argument' => '25']],
    'schema qualified analysis_limit' => ['PRAGMA aux.analysis_limit=7', ['pragma' => 'analysis_limit', 'schema' => 'aux', 'argument' => '7']],
    'quoted schema analysis_limit' => ['PRAGMA "aux".analysis_limit=9', ['pragma' => 'analysis_limit', 'schema' => 'aux', 'argument' => '9']],
    'optimize default' => ['PRAGMA optimize', ['pragma' => 'optimize', 'schema' => 'main', 'argument' => null]],
    'optimize numeric mask' => ['PRAGMA optimize=65536', ['pragma' => 'optimize', 'schema' => 'main', 'argument' => '65536']],
    'optimize hex mask' => ['PRAGMA optimize(0x10002)', ['pragma' => 'optimize', 'schema' => 'main', 'argument' => '0x10002']],
    'schema optimize semicolon' => ['PRAGMA aux.optimize;', ['pragma' => 'optimize', 'schema' => 'aux', 'argument' => null]],
    'bracket schema optimize' => ['PRAGMA [temp].optimize(2)', ['pragma' => 'optimize', 'schema' => 'temp', 'argument' => '2']],
];

foreach ($parseCases as $name => [$sql, $expected]) {
    $tests['pragma optimize parse ' . $name] = static fn (): mixed => SQLitePragmaOptimizePlan::parse($sql);
}

$analysisLimitCases = [
    'default query returns zero' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit')['effective'],
    'assignment changes current limit' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit=1000')['effective'],
    'negative assignment clamps to zero' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit=-12')['effective'],
    'parenthesized assignment changes current limit' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit(33)')['effective'],
    'schema assignment is isolated' => static function (): mixed {
        $plan = new SQLitePragmaOptimizePlan();
        $plan->execute('PRAGMA aux.analysis_limit=44');
        return [$plan->execute('PRAGMA aux.analysis_limit')['effective'], $plan->execute('PRAGMA main.analysis_limit')['effective']];
    },
    'constructor seeds schema limit' => static fn (): mixed => (new SQLitePragmaOptimizePlan(['aux' => 77]))->execute('PRAGMA aux.analysis_limit')['effective'],
    'changed false for repeated assignment' => static function (): mixed {
        $plan = new SQLitePragmaOptimizePlan(['main' => 15]);
        return $plan->execute('PRAGMA analysis_limit=15')['changed'];
    },
    'changed true for new assignment' => static fn (): mixed => (new SQLitePragmaOptimizePlan(['main' => 15]))->execute('PRAGMA analysis_limit=16')['changed'],
    'rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit')['rows'][0]),
    'dependency reports pragma state' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit')['dependencies'],
];

$analysisExpected = [
    'default query returns zero' => 0,
    'assignment changes current limit' => 1000,
    'negative assignment clamps to zero' => 0,
    'parenthesized assignment changes current limit' => 33,
    'schema assignment is isolated' => [44, 0],
    'constructor seeds schema limit' => 77,
    'changed false for repeated assignment' => false,
    'changed true for new assignment' => true,
    'rows use sqlite column name' => ['analysis_limit'],
    'dependency reports pragma state' => ['pragma-state'],
];

foreach ($analysisLimitCases as $name => $callback) {
    $tests['pragma analysis_limit current ' . $name] = static function (TestRunner $t) use ($callback, $analysisExpected, $name): void {
        $t->same($analysisExpected[$name], $callback());
    };
}

$optimizeCases = [
    'default mask analyzes touched and missing stats' => ['PRAGMA optimize', ['wp_options' => 'touched-table', 'wp_posts' => 'missing-stat1']],
    'touched mask analyzes touched and missing-stat tables' => ['PRAGMA optimize=2', ['wp_options' => 'touched-table', 'wp_posts' => 'missing-stat1']],
    'force mask analyzes all current schema tables' => ['PRAGMA optimize=0x10000', ['wp_options' => 'force-all', 'wp_postmeta' => 'force-all', 'wp_posts' => 'missing-stat1']],
    'debug mask analyzes all current schema tables' => ['PRAGMA optimize=1', ['wp_options' => 'debug-mask', 'wp_postmeta' => 'debug-mask', 'wp_posts' => 'debug-mask']],
    'aux schema optimize stays on aux tables' => ['PRAGMA aux.optimize', ['wp_options' => 'touched-table']],
    'temp schema optimize can skip up to date temp table' => ['PRAGMA temp.optimize', []],
];

foreach ($optimizeCases as $name => [$sql, $expectedReasons]) {
    $tests['pragma optimize current ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expectedReasons): void {
        $result = (new SQLitePragmaOptimizePlan())->execute($sql, $tables);
        $actual = [];
        foreach ($result['analyze'] as $row) {
            $actual[$row['table']] = $row['reason'];
        }
        $t->same($expectedReasons, $actual);
    };
}

$tests['pragma optimize temporarily raises zero analysis limit'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', $tables);
    $t->same(0, $result['previousAnalysisLimit']);
    $t->same(2000, $result['temporaryAnalysisLimit']);
    $t->same(0, $result['restoredAnalysisLimit']);
    $t->same(2000, $result['analyze'][0]['analysisLimit']);
};

$tests['pragma optimize preserves nonzero analysis limit after run'] = static function (TestRunner $t) use ($tables): void {
    $plan = new SQLitePragmaOptimizePlan(['main' => 64]);
    $result = $plan->execute('PRAGMA optimize', $tables);
    $t->same(64, $result['previousAnalysisLimit']);
    $t->same(64, $result['temporaryAnalysisLimit']);
    $t->same(64, $result['restoredAnalysisLimit']);
    $t->same(64, $plan->execute('PRAGMA analysis_limit')['effective']);
};

$tests['pragma optimize emits quoted analyze sql'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', $tables);
    $t->same('ANALYZE "main"."wp_options"', $result['analyze'][0]['sql']);
};

$tests['pragma optimize records skipped up to date tables'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', $tables);
    $t->same(['table' => 'wp_postmeta', 'reason' => 'up-to-date'], $result['skipped'][0]);
};

$tests['pragma optimize reports dependencies'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', $tables);
    $t->same(['analysis_limit', 'sqlite_stat1', 'schema-table-scan', 'current-source'], $result['dependencies']);
};

$tests['pragma optimize returns empty rows like sqlite pragma optimize'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', $tables);
    $t->same([], $result['rows']);
};

$tests['pragma optimize no table metadata produces empty worklist'] = static function (TestRunner $t): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', []);
    $t->same([], $result['analyze']);
    $t->same([], $result['skipped']);
};

$tests['pragma optimize drift below threshold skips table'] = static function (TestRunner $t): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', [
        ['schema' => 'main', 'name' => 'wp_options', 'rowCount' => 100, 'statRowCount' => 80, 'touched' => false],
    ]);
    $t->same([], $result['analyze']);
    $t->same([['table' => 'wp_options', 'reason' => 'up-to-date']], $result['skipped']);
};

$tests['pragma optimize drift at threshold schedules analyze'] = static function (TestRunner $t): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', [
        ['schema' => 'main', 'name' => 'wp_options', 'rowCount' => 100, 'statRowCount' => 75, 'touched' => false],
    ]);
    $t->same('row-count-drift', $result['analyze'][0]['reason']);
};

$tests['pragma optimize missing stat row count schedules analyze'] = static function (TestRunner $t): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', [
        ['schema' => 'main', 'name' => 'wp_options', 'rowCount' => 100, 'hasStat' => false],
    ]);
    $t->same('missing-stat1', $result['analyze'][0]['reason']);
};

$tests['pragma optimize schema names normalize case'] = static function (TestRunner $t): void {
    $result = (new SQLitePragmaOptimizePlan(['Aux' => 12]))->execute('PRAGMA AUX.optimize', [
        ['schema' => 'AUX', 'name' => 'wp_options', 'rowCount' => 100, 'statRowCount' => 50],
    ]);
    $t->same('aux', $result['schema']);
    $t->same(12, $result['temporaryAnalysisLimit']);
};

$tests['pragma optimize quoted numeric mask is accepted'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute("PRAGMA optimize='65536'", $tables);
    $t->same(65536, $result['mask']);
    $t->same('force-all', $result['analyze'][0]['reason']);
};

$tests['pragma optimize parenthesized quoted hex mask is accepted'] = static function (TestRunner $t) use ($tables): void {
    $result = (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize("0x10000")', $tables);
    $t->same(65536, $result['mask']);
};

$tests['pragma optimize rejects unsafe table identifier'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize', [['name' => 'bad-name']]));
};

$guardCases = [
    'unknown pragma rejected' => static fn (): mixed => SQLitePragmaOptimizePlan::parse('PRAGMA cache_size=10'),
    'select sql rejected' => static fn (): mixed => SQLitePragmaOptimizePlan::parse('SELECT PRAGMA optimize'),
    'invalid analysis limit rejected' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA analysis_limit=fast'),
    'invalid optimize mask rejected' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize=fast'),
    'negative optimize mask rejected' => static fn (): mixed => (new SQLitePragmaOptimizePlan())->execute('PRAGMA optimize=-1'),
];

foreach ($guardCases as $name => $callback) {
    $tests['pragma optimize guard ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, static fn () => $callback());
    };
}

$applicationCases = [
    'autoload option update touch schedules wp_options analyze' => ['PRAGMA optimize', 0, 'wp_options', 'touched-table'],
    'postmeta stable stats stay skipped' => ['PRAGMA optimize', 0, 'wp_postmeta', 'up-to-date'],
    'missing posts stats schedule analyze' => ['PRAGMA optimize', 0, 'wp_posts', 'missing-stat1'],
    'aux copied options schema schedules attached analyze' => ['PRAGMA aux.optimize', 0, 'wp_options', 'touched-table'],
    'application preflight bounded limit is restored' => ['PRAGMA optimize', 128, 'restoredAnalysisLimit', 128],
];

foreach ($applicationCases as $name => [$sql, $limit, $field, $expected]) {
    $tests['pragma optimize application ' . $name] = static function (TestRunner $t) use ($tables, $sql, $limit, $field, $expected): void {
        $result = (new SQLitePragmaOptimizePlan(['main' => $limit, 'aux' => $limit]))->execute($sql, $tables);
        if ($field === 'restoredAnalysisLimit') {
            $t->same($expected, $result[$field]);
            return;
        }
        foreach ($result['analyze'] as $row) {
            if ($row['table'] === $field) {
                $t->same($expected, $row['reason']);
                return;
            }
        }
        foreach ($result['skipped'] as $row) {
            if ($row['table'] === $field) {
                $t->same($expected, $row['reason']);
                return;
            }
        }
        $t->same($expected, null);
    };
}

return $tests;
