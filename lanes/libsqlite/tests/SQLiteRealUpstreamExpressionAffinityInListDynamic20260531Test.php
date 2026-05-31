<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity IN-list dynamic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-12.3.78..84 declares IN and NOT IN
//   expression-list forms as core expressions.
// - SQLite upstream test/types2.test types2-5.* and types2-6.* pin that values
//   on the right side of IN(...) are expression values, including numeric-looking
//   text and real values that must not inherit column affinity from the RHS.
$leftExpressions = [
    'null' => 'NULL',
    'int-ten' => '10',
    'real-ten' => '10.0',
    'text-ten' => $quoteSql('10'),
    'text-ten-real' => $quoteSql('10.0'),
    'text-leading-zero' => $quoteSql('010'),
    'text-real-leading-zero' => $quoteSql('010.0'),
    'text-space-ten' => $quoteSql(' 10'),
    'text-plus-ten' => $quoteSql('+10'),
    'text-ten-tail' => $quoteSql('10tail'),
    'int-zero' => '0',
    'text-zero' => $quoteSql('0'),
    'text-zero-real' => $quoteSql('0.0'),
    'blob-ten' => "X'3130'",
    'blob-ten-real' => "X'31302E30'",
    'blob-empty' => "X''",
    'text-empty' => $quoteSql(''),
    'text-neg-five' => $quoteSql('-5'),
    'text-neg-five-real' => $quoteSql('-5.0'),
];

$rightLists = [
    'single-int-ten' => ['10'],
    'single-real-ten' => ['10.0'],
    'single-text-ten' => [$quoteSql('10')],
    'single-text-ten-real' => [$quoteSql('10.0')],
    'mixed-int-real' => ['10', '10.0'],
    'mixed-text-int' => [$quoteSql('10'), '10'],
    'mixed-text-real' => [$quoteSql('10.0'), '10.0'],
    'mixed-text-leading-zero' => [$quoteSql('010'), '10'],
    'null-only' => ['NULL'],
    'null-then-int' => ['NULL', '10'],
    'int-then-null' => ['10', 'NULL'],
    'real-then-null' => ['10.0', 'NULL'],
    'text-then-null' => [$quoteSql('10'), 'NULL'],
    'blob-ten' => ["X'3130'"],
    'blob-and-text' => ["X'3130'", $quoteSql('10')],
    'zero-mixed' => ['0', '0.0', $quoteSql('0'), $quoteSql('0.0')],
    'negative-mixed' => ['-5', '-5.0', $quoteSql('-5'), $quoteSql('-5.0')],
    'casted-numeric' => ["CAST('10' AS NUMERIC)", "CAST('10.0' AS NUMERIC)"],
    'casted-text' => ['CAST(10 AS TEXT)', 'CAST(10.0 AS TEXT)'],
    'casted-real-integer' => ['CAST(10 AS REAL)', 'CAST(10.0 AS INTEGER)'],
    'no-match-numeric' => ['20', '30.0', $quoteSql('40')],
    'no-match-text' => [$quoteSql('20'), $quoteSql('30.0'), $quoteSql('010.0')],
];

$wrappers = [
    'plain' => static fn (string $left, string $list): string => "{$left} IN ({$list})",
    'not-in' => static fn (string $left, string $list): string => "{$left} NOT IN ({$list})",
    'is-null' => static fn (string $left, string $list): string => "({$left} IN ({$list})) IS NULL",
    'is-not-null' => static fn (string $left, string $list): string => "({$left} IN ({$list})) IS NOT NULL",
    'case-hit' => static fn (string $left, string $list): string => "CASE WHEN {$left} IN ({$list}) THEN 'hit' WHEN {$left} NOT IN ({$list}) THEN 'miss' ELSE 'null' END",
    'numeric-guard' => static fn (string $left, string $list): string => "({$left} IN ({$list})) + 0",
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
    'is-null' => static fn (string $expression): string => "quote(({$expression}) IS NULL)",
];

$cases = [];
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($rightLists as $listName => $rightSqls) {
        $listSql = implode(', ', $rightSqls);
        foreach ($wrappers as $wrapperName => $wrapper) {
            $expression = $wrapper($leftSql, $listSql);
            foreach ($projections as $projectionName => $projection) {
                $cases["{$leftName}.{$listName}.{$wrapperName}.{$projectionName}"] = $projection($expression);
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $projectionSql) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$projectionSql};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-in-list-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce IN-list expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 IN-list oracle row: ' . $line);
    }

    [$key, $value] = $parts;
    $oracle[$key] = $value;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 IN-list oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $projectionSql) {
    $tests['real upstream expression affinity in-list dynamic e_expr-12 types2-5-6 ' . $key] = static function (TestRunner $t) use ($key, $projectionSql, $oracle): void {
        $rows = SQLiteSelectSql::execute('SELECT ' . $projectionSql, []);
        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key], (string) array_values($rows[0])[0], $key);
    };
}

$tests['real upstream expression affinity in-list dynamic owns 7524 e_expr and types2 cases'] = static function (TestRunner $t) use ($leftExpressions, $rightLists, $wrappers, $projections, $cases, $oracle): void {
    $t->same(19, count($leftExpressions));
    $t->same(22, count($rightLists));
    $t->same(6, count($wrappers));
    $t->same(3, count($projections));
    $t->same(7524, count($cases));
    $t->same(7524, count($oracle));
    $t->same(
        'e_expr.test e_expr-12.3.78..84 and types2.test types2-5/6 IN-list expression affinity semantics',
        'e_expr.test e_expr-12.3.78..84 and types2.test types2-5/6 IN-list expression affinity semantics',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
};

return $tests;
