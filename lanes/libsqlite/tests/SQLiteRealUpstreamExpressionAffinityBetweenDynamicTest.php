<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$expression])) {
        return $cache[$expression];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity BETWEEN dynamic tests');
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
INSERT INTO t1(rowid,xi,xr,xb,xn,xt) VALUES(4,'4.5','4.5','4.5','4.5','4.5');
INSERT INTO t1(rowid,xi,xr,xb,xn,xt) VALUES(5,NULL,NULL,NULL,NULL,NULL);
SQL;
    $sql = $setup . "\nSELECT group_concat(rowid || ':' || COALESCE(CAST({$expression} AS TEXT),'NULL'), ',') FROM t1 ORDER BY rowid;";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$expression] = rtrim($output, "\r\n");
};

$rawRows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
    ['rowid' => 2, 'xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
    ['rowid' => 3, 'xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
    ['rowid' => 4, 'xi' => '4.5', 'xr' => '4.5', 'xb' => '4.5', 'xn' => '4.5', 'xt' => '4.5'],
    ['rowid' => 5, 'xi' => null, 'xr' => null, 'xb' => null, 'xn' => null, 'xt' => null],
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

$port = static function (string $expression) use ($tableRows): string {
    $rows = SQLiteSelectSql::execute("SELECT rowid, {$expression} AS value FROM t1 ORDER BY rowid", ['t1' => $tableRows]);

    return implode(',', array_map(
        static fn (array $row): string => $row['rowid'] . ':' . ($row['value'] === null ? 'NULL' : (string) $row['value']),
        $rows,
    ));
};

// Real upstream sources:
// - SQLite test/expr.test expr-1.86..1.95 validates BETWEEN and NOT BETWEEN,
//   including NULL lower/upper bound behavior.
// - SQLite test/affinity2.test affinity2-110..300 validates that comparison
//   operators apply column affinity to the opposite operand while unary +
//   strips affinity. BETWEEN is defined as two comparisons, so this shard
//   cross-products those upstream behaviors through the SELECT expression
//   evaluator and checks the port against the local sqlite3 oracle.
$leftExpressions = [
    'xi' => 'xi',
    'xr' => 'xr',
    'xb' => 'xb',
    'xn' => 'xn',
    'xt' => 'xt',
];
$bounds = [
    'null' => 'NULL',
    'int-zero' => '0',
    'int-one' => '1',
    'int-two' => '2',
    'int-three' => '3',
    'real-two' => '2.0',
    'real-four-half' => '4.5',
    'text-two' => "'2'",
    'text-leading-three' => "'03'",
    'text-four-half' => "'4.5'",
];
$operators = [
    'between' => 'BETWEEN',
    'not-between' => 'NOT BETWEEN',
];

$caseCount = 0;
foreach ($leftExpressions as $leftName => $left) {
    foreach ($bounds as $lowerName => $lower) {
        foreach ($bounds as $upperName => $upper) {
            foreach ($operators as $operatorName => $operator) {
                ++$caseCount;
                $expression = "{$left} {$operator} {$lower} AND {$upper}";
                $tests["real upstream expression affinity between dynamic {$leftName} {$operatorName} {$lowerName} {$upperName}"] = static function (TestRunner $t) use ($oracle, $port, $expression): void {
                    $t->same($oracle($expression), $port($expression), $expression);
                };
            }
        }
    }
}

$tests['real upstream expression affinity between dynamic owns exactly 1000 pass cases'] = static function (TestRunner $t) use ($leftExpressions, $bounds, $operators, $caseCount): void {
    $t->same(5, count($leftExpressions));
    $t->same(10, count($bounds));
    $t->same(2, count($operators));
    $t->same(1000, $caseCount);
    $t->same('expr.test expr-1.86..1.95 BETWEEN/NOT BETWEEN with affinity2.test affinity2-110..300 comparison affinity', 'expr.test expr-1.86..1.95 BETWEEN/NOT BETWEEN with affinity2.test affinity2-110..300 comparison affinity');
};

return $tests;
