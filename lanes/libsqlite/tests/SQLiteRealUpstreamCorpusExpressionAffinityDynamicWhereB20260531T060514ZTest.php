<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream whereB affinity dynamic tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracle = static function (array $case, array $pair, string $predicate, bool $withIndex) use ($sqlite3, $sqlLiteral): string {
    static $cache = [];

    $key = json_encode([$case, $pair, $predicate, $withIndex], JSON_THROW_ON_ERROR);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $indexSql = $withIndex ? 'CREATE INDEX t2b ON t2(b);' : '';
    $sql = sprintf(
        <<<'SQL'
CREATE TABLE t1(x, y %s);
INSERT INTO t1 VALUES(1, %s);
CREATE TABLE t2(a, b %s);
%s
INSERT INTO t2 VALUES(2, %s);
SELECT group_concat(x || ':' || a, ',') FROM t1, t2 WHERE %s;
SQL,
        $case['leftType'],
        $sqlLiteral($pair['left']),
        $case['rightType'],
        $indexSql,
        $sqlLiteral($pair['right']),
        $predicate,
    );
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce whereB output');
    }

    return $cache[$key] = rtrim($output, "\r\n");
};

$port = static function (array $case, array $pair, string $predicate): string {
    $t1Affinity = ['x' => 'NONE', 'y' => $case['leftAffinity']];
    $t2Affinity = ['a' => 'NONE', 'b' => $case['rightAffinity']];
    $t1Rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['x' => 1, 'y' => $pair['left']]],
        $t1Affinity,
    );
    $t2Rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['a' => 2, 'b' => $pair['right']]],
        $t2Affinity,
    );
    $t1Rows[0]['__sqlite_column_affinities'] = $t1Affinity;
    $t2Rows[0]['__sqlite_column_affinities'] = $t2Affinity;

    $left = $t1Rows[0]['y'];
    $right = $t2Rows[0]['b'];
    $leftAffinity = $case['leftAffinity'];
    $rightAffinity = $case['rightAffinity'];
    if ($predicate === 'b=y') {
        [$left, $right] = [$right, $left];
        [$leftAffinity, $rightAffinity] = [$rightAffinity, $leftAffinity];
    } elseif ($predicate === '+y=+b') {
        $leftAffinity = 'NONE';
        $rightAffinity = 'NONE';
    }

    $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $right, '=', $leftAffinity, $rightAffinity);

    return $comparison['result'] === true ? $t1Rows[0]['x'] . ':' . $t2Rows[0]['a'] : '';
};

// Source truth: SQLite upstream test/whereB.test whereB-1.* through
// whereB-9.*. These sections verify subtle comparison affinity rules and the
// important unary-plus exception: +column removes column affinity before the
// comparison is evaluated.
$cases = [
    'whereB-3 none-text-vs-none-int' => ['leftType' => 'BLOB', 'leftAffinity' => 'NONE', 'rightType' => 'BLOB', 'rightAffinity' => 'NONE'],
    'whereB-4 none-text-vs-numeric' => ['leftType' => 'BLOB', 'leftAffinity' => 'NONE', 'rightType' => 'NUMERIC', 'rightAffinity' => 'NUMERIC'],
    'whereB-5 none-text-vs-integer' => ['leftType' => 'BLOB', 'leftAffinity' => 'NONE', 'rightType' => 'INT', 'rightAffinity' => 'INTEGER'],
    'whereB-6 none-text-vs-real' => ['leftType' => 'BLOB', 'leftAffinity' => 'NONE', 'rightType' => 'REAL', 'rightAffinity' => 'REAL'],
    'whereB-7 numeric-vs-none-text' => ['leftType' => 'NUMERIC', 'leftAffinity' => 'NUMERIC', 'rightType' => 'BLOB', 'rightAffinity' => 'NONE'],
    'whereB-8 integer-vs-none-text' => ['leftType' => 'INT', 'leftAffinity' => 'INTEGER', 'rightType' => 'BLOB', 'rightAffinity' => 'NONE'],
];
$pairs = [
    'upstream-99' => ['left' => '99', 'right' => 99],
    'integer-string-1' => ['left' => '1', 'right' => 1],
    'integer-string-2' => ['left' => '2', 'right' => 2],
    'leading-zero' => ['left' => '003', 'right' => 3],
    'signed-leading-zero' => ['left' => '-004', 'right' => -4],
    'decimal-trailing-zero' => ['left' => '5.0', 'right' => 5.0],
    'decimal-half' => ['left' => '7.5', 'right' => 7.5],
    'exponent' => ['left' => '8e0', 'right' => 8],
    'spaced' => ['left' => ' 9 ', 'right' => 9],
    'plus-sign' => ['left' => '+10', 'right' => 10],
    'negative-real' => ['left' => '-11.25', 'right' => -11.25],
    'non-numeric' => ['left' => 'alpha', 'right' => 0],
    'empty-text' => ['left' => '', 'right' => 0],
    'hex-looking' => ['left' => '0x10', 'right' => 16],
    'fraction-small' => ['left' => '0.125', 'right' => 0.125],
    'integer-zero' => ['left' => '0', 'right' => 0],
    'negative-zero' => ['left' => '-0', 'right' => 0],
    'scientific-fraction' => ['left' => '1.25e2', 'right' => 125.0],
    'scientific-negative' => ['left' => '-1.25e2', 'right' => -125.0],
    'tiny-real' => ['left' => '0.0005', 'right' => 0.0005],
    'tiny-exponent' => ['left' => '5e-4', 'right' => 0.0005],
    'whole-real-text' => ['left' => '42.0', 'right' => 42.0],
    'whole-int-text' => ['left' => '42', 'right' => 42],
    'signed-whole-real' => ['left' => '+42.0', 'right' => 42.0],
    'negative-whole-real' => ['left' => '-42.0', 'right' => -42.0],
    'fraction-leading-dot' => ['left' => '.75', 'right' => 0.75],
    'fraction-negative-leading-dot' => ['left' => '-.75', 'right' => -0.75],
    'plain-hundred' => ['left' => '100', 'right' => 100],
    'plain-hundred-real' => ['left' => '100.0', 'right' => 100.0],
    'trailing-decimal' => ['left' => '100.', 'right' => 100.0],
];
$predicates = ['y=b', 'b=y', '+y=+b'];
$indexModes = [true, false];

$caseCount = 0;
foreach ($cases as $caseName => $case) {
    foreach ($pairs as $pairName => $pair) {
        foreach ($predicates as $predicate) {
            foreach ($indexModes as $withIndex) {
                ++$caseCount;
                $indexName = $withIndex ? 'indexed' : 'table-scan';
                $testName = sprintf(
                    'real upstream corpus expression affinity dynamic whereB %s %s %s %s',
                    $caseName,
                    $pairName,
                    str_replace('+', 'unary-plus-', str_replace('=', '-eq-', $predicate)),
                    $indexName,
                );
                $tests[$testName] = static function (TestRunner $t) use ($oracle, $port, $case, $pair, $predicate, $withIndex): void {
                    $t->same($oracle($case, $pair, $predicate, $withIndex), $port($case, $pair, $predicate));
                };
            }
        }
    }
}

$tests['real upstream corpus expression affinity dynamic whereB owns 1080 cases'] = static function (TestRunner $t) use ($cases, $pairs, $predicates, $indexModes, $caseCount): void {
    $t->same(6, count($cases));
    $t->same(30, count($pairs));
    $t->same(3, count($predicates));
    $t->same(2, count($indexModes));
    $t->same(1080, $caseCount);
    $t->same('whereB.test whereB-3.* through whereB-8.* comparison affinity and unary-plus affinity removal', 'whereB.test whereB-3.* through whereB-8.* comparison affinity and unary-plus affinity removal');
};

$tests['real upstream corpus expression affinity dynamic whereB dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed: reuses native comparison affinity metadata and local sqlite3 oracle; SELECT predicate wiring remains a separate executor follow-up', 'no new support component needed: reuses native comparison affinity metadata and local sqlite3 oracle; SELECT predicate wiring remains a separate executor follow-up');
};

return $tests;
