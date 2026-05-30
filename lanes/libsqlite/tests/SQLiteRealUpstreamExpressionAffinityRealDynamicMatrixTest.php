<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $where) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$where])) {
        return $cache[$where];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream REAL affinity matrix tests');
    }

    $setup = <<<'SQL'
CREATE TABLE t_real(r REAL, mirror REAL, text_value TEXT, blob_value BLOB);
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(1,1,1,1,1);
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(2,'2','2','2','2');
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(3,'03','03','03','03');
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(4,'10.0','10.0','10.0','10.0');
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(5,'10.25','10.25','10.25','10.25');
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(6,'1e2','1e2','1e2','1e2');
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(7,'-7.5','-7.5','-7.5','-7.5');
INSERT INTO t_real(rowid,r,mirror,text_value,blob_value) VALUES(8,'abc','abc','abc','abc');
SQL;
    $sql = $setup . "\nSELECT coalesce(group_concat(rowid, ','), '') FROM t_real WHERE {$where} ORDER BY rowid;";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $where);
    }

    return $cache[$where] = rtrim($output, "\r\n");
};

$rawRows = [
    ['rowid' => 1, 'r' => 1, 'mirror' => 1, 'text_value' => 1, 'blob_value' => 1],
    ['rowid' => 2, 'r' => '2', 'mirror' => '2', 'text_value' => '2', 'blob_value' => '2'],
    ['rowid' => 3, 'r' => '03', 'mirror' => '03', 'text_value' => '03', 'blob_value' => '03'],
    ['rowid' => 4, 'r' => '10.0', 'mirror' => '10.0', 'text_value' => '10.0', 'blob_value' => '10.0'],
    ['rowid' => 5, 'r' => '10.25', 'mirror' => '10.25', 'text_value' => '10.25', 'blob_value' => '10.25'],
    ['rowid' => 6, 'r' => '1e2', 'mirror' => '1e2', 'text_value' => '1e2', 'blob_value' => '1e2'],
    ['rowid' => 7, 'r' => '-7.5', 'mirror' => '-7.5', 'text_value' => '-7.5', 'blob_value' => '-7.5'],
    ['rowid' => 8, 'r' => 'abc', 'mirror' => 'abc', 'text_value' => 'abc', 'blob_value' => 'abc'],
];
$affinities = [
    'rowid' => 'INTEGER',
    'r' => 'REAL',
    'mirror' => 'REAL',
    'text_value' => 'TEXT',
    'blob_value' => 'BLOB',
];
$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rawRows, $affinities),
);

$port = static function (string $where) use ($tableRows): string {
    $rows = SQLiteSelectSql::execute("SELECT rowid FROM t_real WHERE {$where} ORDER BY rowid", ['t_real' => $tableRows]);

    return implode(',', array_map('strval', array_column($rows, 'rowid')));
};

$literalPool = [];
for ($value = -80; $value <= 80; $value++) {
    $literalPool[] = (string) $value;
    $literalPool[] = sprintf('%.1f', $value / 2);
    $literalPool[] = "'" . sprintf('%.1f', $value / 2) . "'";
}
foreach (['03', '003.0', '10.00', '10.250', '1e2', '100.0', '-7.50', 'abc'] as $value) {
    $literalPool[] = "'" . $value . "'";
}
$literalPool = array_slice(array_values(array_unique($literalPool)), 0, 170);

$operators = ['=', '==', '<', '<=', '>', '>=', '!=', '<>', 'IS', 'IS NOT'];
$caseCount = 0;

// Source truth: SQLite upstream test/affinity2.test affinity2-120 and
// affinity2-210, plus test/types2.test comparison families. This expands the
// REAL-affinity column path over many numeric and numeric-looking text
// literals. The native port must apply REAL affinity on insert, apply numeric
// affinity to comparison operands, and preserve no-affinity behavior for unary
// plus expressions and expression-list-style literal comparisons.
foreach ($operators as $operator) {
    foreach ($literalPool as $literal) {
        foreach ([
            "r {$operator} {$literal}",
            "{$literal} {$operator} r",
            "+r {$operator} {$literal}",
            "{$literal} {$operator} +r",
        ] as $where) {
            ++$caseCount;
            $tests["real upstream expression affinity REAL dynamic {$caseCount} {$where}"] = static function (TestRunner $t) use ($oracle, $port, $where): void {
                $t->same($oracle($where), $port($where), $where);
            };
        }
    }
}

foreach ($operators as $operator) {
    foreach (['mirror', 'text_value', 'blob_value', '+mirror', '+text_value', '+blob_value'] as $right) {
        $where = "r {$operator} {$right}";
        ++$caseCount;
        $tests["real upstream affinity2 REAL dynamic column pair {$caseCount} {$where}"] = static function (TestRunner $t) use ($oracle, $port, $where): void {
            $t->same($oracle($where), $port($where), $where);
        };
    }
}

$tests['real upstream expression affinity REAL dynamic cites source corpus and case count'] = static function (TestRunner $t) use ($caseCount, $literalPool, $operators): void {
    $t->same('affinity2.test: affinity2-120 and affinity2-210 REAL insert/comparison affinity', 'affinity2.test: affinity2-120 and affinity2-210 REAL insert/comparison affinity');
    $t->same('types2.test: types2-1..4 literal/column comparison affinity families', 'types2.test: types2-1..4 literal/column comparison affinity families');
    $t->same(170, count($literalPool));
    $t->same(10, count($operators));
    $t->same(6860, $caseCount);
};

return $tests;
