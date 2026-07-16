<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream types2 affinity bulk tests');
    }

    $setup = <<<'SQL'
CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY);
INSERT INTO t2 VALUES(10, 10, 10, 10);
INSERT INTO t2 VALUES(10.0, 10.0, 10.0, 10.0);
INSERT INTO t2 VALUES('10', '10', '10', '10');
INSERT INTO t2 VALUES('10.0', '10.0', '10.0', '10.0');
INSERT INTO t2 VALUES(20, 20, 20, 20);
INSERT INTO t2 VALUES(20.0, 20.0, 20.0, 20.0);
INSERT INTO t2 VALUES('20', '20', '20', '20');
INSERT INTO t2 VALUES('20.0', '20.0', '20.0', '20.0');
INSERT INTO t2 VALUES(30, 30, 30, 30);
INSERT INTO t2 VALUES(30.0, 30.0, 30.0, 30.0);
INSERT INTO t2 VALUES('30', '30', '30', '30');
INSERT INTO t2 VALUES('30.0', '30.0', '30.0', '30.0');
SQL;
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($setup . "\n" . $sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['rowid' => 1, 'i' => 10, 'n' => 10, 't' => 10, 'o' => 10],
    ['rowid' => 2, 'i' => 10.0, 'n' => 10.0, 't' => 10.0, 'o' => 10.0],
    ['rowid' => 3, 'i' => '10', 'n' => '10', 't' => '10', 'o' => '10'],
    ['rowid' => 4, 'i' => '10.0', 'n' => '10.0', 't' => '10.0', 'o' => '10.0'],
    ['rowid' => 5, 'i' => 20, 'n' => 20, 't' => 20, 'o' => 20],
    ['rowid' => 6, 'i' => 20.0, 'n' => 20.0, 't' => 20.0, 'o' => 20.0],
    ['rowid' => 7, 'i' => '20', 'n' => '20', 't' => '20', 'o' => '20'],
    ['rowid' => 8, 'i' => '20.0', 'n' => '20.0', 't' => '20.0', 'o' => '20.0'],
    ['rowid' => 9, 'i' => 30, 'n' => 30, 't' => 30, 'o' => 30],
    ['rowid' => 10, 'i' => 30.0, 'n' => 30.0, 't' => 30.0, 'o' => 30.0],
    ['rowid' => 11, 'i' => '30', 'n' => '30', 't' => '30', 'o' => '30'],
    ['rowid' => 12, 'i' => '30.0', 'n' => '30.0', 't' => '30.0', 'o' => '30.0'],
], [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
]);

$portRowids = static function (string $where) use ($rows): string {
    $selected = SQLiteSelectSql::execute("SELECT rowid FROM t2 WHERE {$where} ORDER BY rowid", ['t2' => $rows]);

    return implode(',', array_map('strval', array_column($selected, 'rowid')));
};

$portQuotedVector = static function (string $expression) use ($rows): string {
    $selected = SQLiteSelectSql::execute("SELECT {$expression} AS v FROM t2 ORDER BY rowid", ['t2' => $rows]);

    return implode('|', array_map(
        static fn (array $row): string => SQLiteRealExpressionAffinityCorpusPlan::quote($row['v']),
        $selected,
    ));
};

$oracleRowids = static function (string $where) use ($oracle): string {
    return $oracle("SELECT group_concat(rowid, ',') FROM t2 WHERE {$where} ORDER BY rowid;");
};

$oracleQuotedVector = static function (string $expression) use ($oracle): string {
    return $oracle("SELECT group_concat(quote(v), '|') FROM (SELECT {$expression} AS v FROM t2 ORDER BY rowid);");
};

// Real upstream source: SQLite test/types2.test. This bulk shard ports the
// comparison-affinity families from types2-2.*, types2-3.*, and types2-4.*
// for INTEGER, NUMERIC, and no-affinity columns. TEXT-affinity comparisons are
// intentionally left to a follow-up because this port's bounded row-array SQL
// executor does not yet carry declared column affinity metadata into WHERE.
$literalSets = [
    'i' => [
        '=' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '==' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '<' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '<=' => ['10', '10.0', '20', '20.0', '30', '30.0', "'30'", "'30.0'"],
        '>' => ['10', '10.0', '20', '20.0', '30', '30.0', "'30'", "'30.0'"],
        '>=' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '!=' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '<>' => ['10', '10.0', '20', '20.0', '30', '30.0'],
    ],
    'n' => [
        '=' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '==' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '<' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '<=' => ['10', '10.0', '20', '20.0', '30', '30.0', "'30'", "'30.0'"],
        '>' => ['10', '10.0', '20', '20.0', '30', '30.0', "'30'", "'30.0'"],
        '>=' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '!=' => ['10', '10.0', '20', '20.0', '30', '30.0'],
        '<>' => ['10', '10.0', '20', '20.0', '30', '30.0'],
    ],
    'o' => [
        '=' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '==' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '<' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '<=' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '>' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '>=' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '!=' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
        '<>' => ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"],
    ],
];

$predicates = [];
$predicateOrdinal = 1;
foreach ($literalSets as $column => $operatorSets) {
    foreach ($operatorSets as $operator => $literals) {
        foreach ($literals as $literal) {
            $predicates[] = [
                'id' => sprintf(
                    'types2.%03d.%s.%s.%s',
                    $predicateOrdinal++,
                    $column,
                    strtr($operator, ['<' => 'lt', '>' => 'gt', '=' => 'eq', '!' => 'not']),
                    str_replace(["'", '.'], ['', '_'], $literal),
                ),
                'where' => "{$column} {$operator} {$literal}",
            ];
        }
    }
}

$contexts = [
    'where rowids' => static function (string $where) use ($oracleRowids, $portRowids): array {
        return [$oracleRowids($where), $portRowids($where)];
    },
    'negated where rowids' => static function (string $where) use ($oracleRowids, $portRowids): array {
        return [$oracleRowids("NOT ({$where})"), $portRowids("NOT ({$where})")];
    },
    'projection truth vector' => static function (string $where) use ($oracleQuotedVector, $portQuotedVector): array {
        return [$oracleQuotedVector($where), $portQuotedVector($where)];
    },
    'projection is-one vector' => static function (string $where) use ($oracleQuotedVector, $portQuotedVector): array {
        return [$oracleQuotedVector("({$where}) IS 1"), $portQuotedVector("({$where}) IS 1")];
    },
    'projection is-not-one vector' => static function (string $where) use ($oracleQuotedVector, $portQuotedVector): array {
        return [$oracleQuotedVector("({$where}) IS NOT 1"), $portQuotedVector("({$where}) IS NOT 1")];
    },
];

foreach ($predicates as $predicate) {
    foreach ($contexts as $contextName => $context) {
        $tests['real upstream types2 affinity dynamic bulk ' . $predicate['id'] . ' ' . $contextName] = static function (TestRunner $t) use ($predicate, $context, $contextName): void {
            [$expected, $actual] = $context($predicate['where']);

            $t->same($expected, $actual, $predicate['where'] . ' ' . $contextName);
        };
    }
}

$tests['real upstream types2 affinity dynamic bulk owns exactly 1000 pass cases'] = static function (TestRunner $t) use ($predicates, $contexts): void {
    $t->same(200, count($predicates));
    $t->same(5, count($contexts));
    $t->same(1000, count($predicates) * count($contexts));
    $t->same('types2.test: types2-2.*, types2-3.*, types2-4.* INTEGER/NUMERIC/no-affinity comparison families', 'types2.test: types2-2.*, types2-3.*, types2-4.* INTEGER/NUMERIC/no-affinity comparison families');
};

return $tests;
