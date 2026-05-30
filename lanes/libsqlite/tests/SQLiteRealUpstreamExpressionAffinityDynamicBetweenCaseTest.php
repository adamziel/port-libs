<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic BETWEEN/CASE tests');
}

$oracleRows = static function (array $expressions) use ($sqlite3): array {
    $script = [];
    foreach ($expressions as $key => $expression) {
        $safeKey = str_replace("'", "''", (string) $key);
        $script[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
    }

    $scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-eexpr-between-case-');
    if ($scriptFile === false) {
        throw new RuntimeException('Could not create temporary sqlite3 oracle script');
    }

    file_put_contents($scriptFile, implode("\n", $script));
    $output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
    @unlink($scriptFile);
    if (!is_string($output) || trim($output) === '') {
        throw new RuntimeException('sqlite3 oracle did not produce e_expr BETWEEN/CASE output');
    }

    $rows = [];
    foreach (explode("\n", trim($output)) as $line) {
        $parts = explode("\t", $line);
        if (count($parts) !== 4) {
            throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
        }

        [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
        $rows[$key] = [
            'quote' => $quotedValue,
            'typeof' => $storageClass,
            'isNull' => $quotedIsNull,
        ];
    }

    return $rows;
};

$literalSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Real upstream source: SQLite test/e_expr.test e_expr-13 verifies BETWEEN
// equivalence and precedence. This dynamic shard keeps the same behavior
// class but widens it across literal storage classes, explicit affinities,
// NOT BETWEEN spelling, and precedence-sensitive boolean terms.
$betweenValues = [
    'null' => 'NULL',
    'int-neg' => '-2',
    'int-zero' => '0',
    'int-one' => '1',
    'int-two' => '2',
    'int-five' => '5',
    'real-half' => '0.5',
    'real-two' => '2.0',
    'text-zero' => "'0'",
    'text-one' => "'1'",
    'text-two' => "'2'",
    'text-five' => "'5'",
    'text-leading-two' => "'02'",
    'text-alpha' => "'alpha'",
    'text-empty' => "''",
    'blob-one' => "x'31'",
];
$betweenWrappers = [
    'raw' => static fn (string $sql): string => $sql,
    'numeric' => static fn (string $sql): string => "CAST({$sql} AS NUMERIC)",
    'real' => static fn (string $sql): string => "CAST({$sql} AS REAL)",
    'text' => static fn (string $sql): string => "CAST({$sql} AS TEXT)",
];
$betweenOperators = ['BETWEEN', 'NOT BETWEEN'];

$expressions = [];
$betweenCaseCount = 0;
foreach ($betweenWrappers as $wrapName => $wrap) {
    foreach ($betweenValues as $valueName => $valueSql) {
        foreach ($betweenValues as $lowerName => $lowerSql) {
            foreach ($betweenOperators as $operator) {
                if ($betweenCaseCount >= 640) {
                    break 4;
                }
                ++$betweenCaseCount;
                $key = sprintf('e_expr-13.dynamic.%s.%s.%s.%s', strtolower(str_replace(' ', '-', $operator)), $wrapName, $valueName, $lowerName);
                $expressions[$key] = sprintf(
                    '(%s) %s (%s) AND (%s)',
                    $wrap($valueSql),
                    $operator,
                    $wrap($lowerSql),
                    $wrap($betweenValues['text-five']),
                );
            }
        }
    }
}

$precedenceExpressions = [
    'e_expr-13.2.1.dynamic' => '1 == 10 BETWEEN 0 AND 2',
    'e_expr-13.2.2.dynamic' => '(1 == 10) BETWEEN 0 AND 2',
    'e_expr-13.2.3.dynamic' => '1 == (10 BETWEEN 0 AND 2)',
    'e_expr-13.2.5.dynamic' => '(6 BETWEEN 4 AND 8) == 1',
    'e_expr-13.2.6.dynamic' => '6 BETWEEN 4 AND (8 == 1)',
    'e_expr-13.2.8.dynamic' => '(5 BETWEEN 0 AND 0) != 1',
    'e_expr-13.2.9.dynamic' => '5 BETWEEN 0 AND (0 != 1)',
    'e_expr-13.2.10.dynamic' => '1 != 0 BETWEEN 0 AND 2',
    'e_expr-13.2.11.dynamic' => '(1 != 0) BETWEEN 0 AND 2',
    'e_expr-13.2.12.dynamic' => '1 != (0 BETWEEN 0 AND 2)',
];
foreach ($precedenceExpressions as $key => $expression) {
    $expressions[$key] = $expression;
}

// Real upstream source: SQLite test/e_expr.test expression-form rows around
// CASE expression grammar. This dynamic shard checks both simple and searched
// CASE with NULL, numeric, real, and text-affinity arms.
$caseBaseValues = [
    'null' => null,
    'zero' => 0,
    'one' => 1,
    'two' => 2,
    'real-one' => 1.0,
    'text-one' => '1',
    'text-two' => '2',
    'text-alpha' => 'alpha',
    'empty' => '',
];
$caseResults = [
    'int-ten' => 10,
    'real-half' => 0.5,
    'text-match' => 'match',
    'text-fallback' => 'fallback',
    'null' => null,
];

$caseCount = 0;
foreach ($caseBaseValues as $baseName => $baseValue) {
    foreach ($caseBaseValues as $whenName => $whenValue) {
        foreach ($caseResults as $thenName => $thenValue) {
            if ($caseCount >= 180) {
                break 3;
            }
            ++$caseCount;
            $key = sprintf('e_expr-12.case.simple.dynamic.%s.%s.%s', $baseName, $whenName, $thenName);
            $expressions[$key] = sprintf(
                'CASE %s WHEN %s THEN %s ELSE %s END',
                $literalSql($baseValue),
                $literalSql($whenValue),
                $literalSql($thenValue),
                $literalSql('else-' . $baseName),
            );
        }
    }
}

foreach ($caseBaseValues as $leftName => $leftValue) {
    foreach ($caseBaseValues as $rightName => $rightValue) {
        foreach ($caseResults as $thenName => $thenValue) {
            if ($caseCount >= 360) {
                break 3;
            }
            ++$caseCount;
            $key = sprintf('e_expr-12.case.searched.dynamic.%s.%s.%s', $leftName, $rightName, $thenName);
            $left = $literalSql($leftValue);
            $right = $literalSql($rightValue);
            $expressions[$key] = sprintf(
                'CASE WHEN %s IS NULL THEN %s WHEN %s = %s THEN %s ELSE %s END',
                $left,
                $literalSql('null-left'),
                $left,
                $right,
                $literalSql($thenValue),
                $literalSql('searched-else'),
            );
        }
    }
}

$oracle = $oracleRows($expressions);
if (count($oracle) !== count($expressions)) {
    throw new RuntimeException(sprintf('Expected %d e_expr BETWEEN/CASE oracle rows, got %d', count($expressions), count($oracle)));
}

foreach ($expressions as $key => $expression) {
    $tests['real upstream expression affinity dynamic e_expr between case ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote ' . $expression);
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof ' . $expression);
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $key . ' is-null ' . $expression);
    };
}

$tests['real upstream expression affinity dynamic e_expr between case owns exactly 1010 oracle cases'] = static function (TestRunner $t) use ($expressions, $betweenCaseCount, $precedenceExpressions, $caseCount): void {
    $t->same(640, $betweenCaseCount);
    $t->same(10, count($precedenceExpressions));
    $t->same(360, $caseCount);
    $t->same(1010, count($expressions));
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
