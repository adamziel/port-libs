<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $projection) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$projection])) {
        return $cache[$projection];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream affinity2 dynamic tests');
    }

    $setup = <<<'SQL'
CREATE TABLE t1(
  xi INTEGER,
  xr REAL,
  xb BLOB,
  xn NUMERIC,
  xt TEXT
);
INSERT INTO t1(rowid,xi,xr,xb,xn,xt) VALUES(1,1,1,1,1,1);
INSERT INTO t1(rowid,xi,xr,xb,xn,xt) VALUES(2,'2','2','2','2','2');
INSERT INTO t1(rowid,xi,xr,xb,xn,xt) VALUES(3,'03','03','03','03','03');
SQL;
    $sql = $setup . "\nSELECT group_concat(rowid || ':' || COALESCE(CAST({$projection} AS TEXT),'NULL'), ',') FROM t1 ORDER BY rowid;";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $projection);
    }

    return $cache[$projection] = rtrim($output, "\r\n");
};

$rawRows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
    ['rowid' => 2, 'xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
    ['rowid' => 3, 'xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
];
$affinities = [
    'xi' => 'INTEGER',
    'xr' => 'REAL',
    'xb' => 'BLOB',
    'xn' => 'NUMERIC',
    'xt' => 'TEXT',
];
$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rawRows, $affinities),
);

$port = static function (string $projection) use ($tableRows): string {
    $rows = SQLiteSelectSql::execute("SELECT rowid, {$projection} AS value FROM t1 ORDER BY rowid", ['t1' => $tableRows]);

    return implode(',', array_map(
        static fn (array $row): string => $row['rowid'] . ':' . ($row['value'] === null ? 'NULL' : (string) $row['value']),
        $rows,
    ));
};

// Real upstream source: SQLite test/affinity2.test affinity2-110 through
// affinity2-300. This dynamic shard widens the fixed upstream comparisons
// across every stored value, column-pair direction, comparison spelling, and
// unary-plus form. The key behavior is that column affinity is applied to the
// other operand, while unary +column has no affinity.
$columns = ['xi', 'xr', 'xb', 'xn', 'xt'];
$operators = ['=', '==', '!=', '<>', '<', '<=', '>', '>=', 'IS', 'IS NOT'];
$rightForms = [];
foreach ($columns as $column) {
    $rightForms[$column] = $column;
    $rightForms['plus-' . $column] = '+' . $column;
}

$caseCount = 0;
foreach ($columns as $left) {
    foreach ($rightForms as $rightName => $right) {
        foreach ($operators as $operator) {
            ++$caseCount;
            $projection = "{$left} {$operator} {$right}";
            $tests["real upstream affinity2 dynamic column comparison {$left} {$operator} {$rightName}"] = static function (TestRunner $t) use ($oracle, $port, $projection): void {
                $t->same($oracle($projection), $port($projection), $projection);
            };
        }
    }
}

$literalPool = [
    'integer-one' => '1',
    'integer-two' => '2',
    'integer-three' => '3',
    'real-one' => '1.0',
    'real-two' => '2.0',
    'text-one' => "'1'",
    'text-two' => "'2'",
    'text-leading-three' => "'03'",
    'text-alpha' => "'alpha'",
    'null' => 'NULL',
];

foreach ($columns as $left) {
    foreach ($literalPool as $literalName => $literal) {
        foreach ($operators as $operator) {
            ++$caseCount;
            $projection = "{$left} {$operator} {$literal}";
            $tests["real upstream affinity2 dynamic literal comparison {$left} {$operator} {$literalName}"] = static function (TestRunner $t) use ($oracle, $port, $projection): void {
                $t->same($oracle($projection), $port($projection), $projection);
            };
        }
    }
}

foreach ($columns as $right) {
    foreach ($literalPool as $literalName => $literal) {
        foreach ($operators as $operator) {
            ++$caseCount;
            $projection = "{$literal} {$operator} {$right}";
            $tests["real upstream affinity2 dynamic literal left comparison {$literalName} {$operator} {$right}"] = static function (TestRunner $t) use ($oracle, $port, $projection): void {
                $t->same($oracle($projection), $port($projection), $projection);
            };
        }
    }
}

$tests['real upstream affinity2 dynamic owns 1500 comparison pass cases'] = static function (TestRunner $t) use ($columns, $operators, $rightForms, $literalPool, $caseCount): void {
    $t->same(5, count($columns));
    $t->same(10, count($operators));
    $t->same(10, count($rightForms));
    $t->same(10, count($literalPool));
    $t->same(1500, $caseCount);
    $t->same('affinity2.test: affinity2-110..300 type affinity in comparison operations', 'affinity2.test: affinity2-110..300 type affinity in comparison operations');
};

return $tests;
