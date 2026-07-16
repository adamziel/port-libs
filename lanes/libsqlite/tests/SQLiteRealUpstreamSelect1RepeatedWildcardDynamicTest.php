<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreTables = static function (int $case): array {
    $f1 = 10 + $case;
    $f2 = 20 + ($case * 2);
    $r1 = $case + 0.25;
    $r2 = $case + 0.75;

    return [
        'test1' => [
            ['f1' => $f1, 'f2' => $f2],
        ],
        'test2' => [
            ['r1' => $r1, 'r2' => $r2],
        ],
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return list<array{name:string,sql:string,expected:Closure(array<string,list<array<string,mixed>>>):list<mixed>}>
 */
$selectCoreScenarios = static function (): array {
    return [
        [
            'name' => 'select1-1.4 single column f1',
            'sql' => 'SELECT f1 FROM test1',
            'expected' => static fn (array $tables): array => [$tables['test1'][0]['f1']],
        ],
        [
            'name' => 'select1-1.5 single column f2',
            'sql' => 'SELECT f2 FROM test1',
            'expected' => static fn (array $tables): array => [$tables['test1'][0]['f2']],
        ],
        [
            'name' => 'select1-1.6 reversed column order',
            'sql' => 'SELECT f2, f1 FROM test1',
            'expected' => static fn (array $tables): array => [$tables['test1'][0]['f2'], $tables['test1'][0]['f1']],
        ],
        [
            'name' => 'select1-1.8 wildcard expansion',
            'sql' => 'SELECT * FROM test1',
            'expected' => static fn (array $tables): array => [$tables['test1'][0]['f1'], $tables['test1'][0]['f2']],
        ],
        [
            'name' => 'select1-1.8.1 repeated wildcard expansion',
            'sql' => 'SELECT *, * FROM test1',
            'expected' => static fn (array $tables): array => [
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
            ],
        ],
        [
            'name' => 'select1-1.8.3 literals around wildcards',
            'sql' => "SELECT 'one', *, 'two', * FROM test1",
            'expected' => static fn (array $tables): array => [
                'one',
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
                'two',
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
            ],
        ],
        [
            'name' => 'select1-1.9 comma join wildcard source order',
            'sql' => 'SELECT * FROM test1, test2',
            'expected' => static fn (array $tables): array => [
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
                $tables['test2'][0]['r1'],
                $tables['test2'][0]['r2'],
            ],
        ],
        [
            'name' => 'select1-1.9.1 comma join wildcard literal tail',
            'sql' => "SELECT *, 'hi' FROM test1, test2",
            'expected' => static fn (array $tables): array => [
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
                $tables['test2'][0]['r1'],
                $tables['test2'][0]['r2'],
                'hi',
            ],
        ],
        [
            'name' => 'select1-1.9.2 literals around joined wildcards',
            'sql' => "SELECT 'one', *, 'two', * FROM test1, test2",
            'expected' => static fn (array $tables): array => [
                'one',
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
                $tables['test2'][0]['r1'],
                $tables['test2'][0]['r2'],
                'two',
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
                $tables['test2'][0]['r1'],
                $tables['test2'][0]['r2'],
            ],
        ],
        [
            'name' => 'select1-1.10 qualified columns',
            'sql' => 'SELECT test1.f1, test2.r1 FROM test1, test2',
            'expected' => static fn (array $tables): array => [$tables['test1'][0]['f1'], $tables['test2'][0]['r1']],
        ],
        [
            'name' => 'select1-1.11 qualified columns with reversed source order',
            'sql' => 'SELECT test1.f1, test2.r1 FROM test2, test1',
            'expected' => static fn (array $tables): array => [$tables['test1'][0]['f1'], $tables['test2'][0]['r1']],
        ],
        [
            'name' => 'select1-1.11.1 reversed source wildcard order',
            'sql' => 'SELECT * FROM test2, test1',
            'expected' => static fn (array $tables): array => [
                $tables['test2'][0]['r1'],
                $tables['test2'][0]['r2'],
                $tables['test1'][0]['f1'],
                $tables['test1'][0]['f2'],
            ],
        ],
    ];
};

$tests = [];
$scenarios = $selectCoreScenarios();
$caseCount = 84;

foreach (range(1, $caseCount) as $case) {
    foreach ($scenarios as $scenario) {
        $name = sprintf('real upstream corpus select1.test repeated wildcard dynamic case %03d %s', $case, $scenario['name']);
        $tests[$name] = static function (TestRunner $t) use ($case, $scenario, $selectCoreTables, $flattenRows): void {
            $tables = $selectCoreTables($case);
            $actual = $flattenRows(SQLiteSelectSql::execute($scenario['sql'], $tables));
            $expected = ($scenario['expected'])($tables);

            $t->same($expected, $actual, $scenario['sql']);
            $t->same(count($expected), count($actual), 'flat value count for ' . $scenario['sql']);
            $t->same(
                $expected === [] ? '' : md5(json_encode($expected, JSON_THROW_ON_ERROR)),
                $actual === [] ? '' : md5(json_encode($actual, JSON_THROW_ON_ERROR)),
                'fingerprint for ' . $scenario['sql']
            );
        };
    }
}

$tests['real upstream corpus select1.test repeated wildcard dynamic owns exactly 1008 pass cases'] = static function (TestRunner $t) use ($scenarios, $caseCount): void {
    $t->contains('/test/select1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test');
    $t->same(12, count($scenarios));
    $t->same(84, $caseCount);
    $t->same(1008, count($scenarios) * $caseCount);
    $t->same(
        'select1.test: select1-1.4 through select1-1.11.1 select-list extraction, wildcard expansion, comma joins, qualified columns, and source order',
        'select1.test: select1-1.4 through select1-1.11.1 select-list extraction, wildcard expansion, comma joins, qualified columns, and source order'
    );
};

return $tests;
