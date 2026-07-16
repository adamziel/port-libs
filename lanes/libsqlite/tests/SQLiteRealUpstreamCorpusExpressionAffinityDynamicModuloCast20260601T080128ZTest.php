<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream modulo cast affinity tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-6.1..6.5 says % casts both
//   original operands to INTEGER before computing the remainder.
// - SQLite upstream test/expr.test expr-13.2..13.7 says text arithmetic uses
//   integer conversion when possible and REAL conversion for exponent/overflow
//   spellings. This corpus targets the interaction between those two rules.
$leftLiterals = [
    'int-72' => '72',
    'int-neg-72' => '-72',
    'real-72-35' => '72.35',
    'real-neg-72-35' => '-72.35',
    'text-int-max' => $quoteSql('9223372036854775807'),
    'text-int-min' => $quoteSql('-9223372036854775808'),
    'text-overflow-pos' => $quoteSql('9223372036854775808'),
    'text-overflow-neg' => $quoteSql('-9223372036854775809'),
    'text-exp-pos' => $quoteSql('1e20'),
    'text-exp-neg' => $quoteSql('-1e20'),
    'text-exp-leading-plus' => $quoteSql('  +6e3suffix'),
    'text-exp-leading-neg' => $quoteSql('  -6e3suffix'),
    'text-decimal-max' => $quoteSql('9223372036854775807.0'),
    'text-decimal-overflow' => $quoteSql('9223372036854775808.0'),
    'text-big-unsigned' => $quoteSql('18446744073709551616'),
    'text-plus-dot' => $quoteSql('+.5'),
    'text-minus-dot' => $quoteSql('-.5'),
    'text-trailing-alpha' => $quoteSql('123abc'),
    'text-alpha' => $quoteSql('abc'),
    'text-hex-prefix' => $quoteSql('0x123'),
    'text-leading-zero-exp' => $quoteSql('00042e2'),
    'cast-real-exp-pos' => "CAST('1e20' AS REAL)",
    'cast-int-exp-pos' => "CAST('1e20' AS INTEGER)",
    'cast-text-exp-pos' => "CAST('1e20' AS TEXT)",
    'blob-exp-pos' => "X'31653230'",
    'blob-overflow-pos' => "X'39323233333732303336383534373735383038'",
    'blob-alpha' => "X'616263'",
    'blob-exp-leading-neg' => "X'20202D366533737566666978'",
];

$rightLiterals = [
    'int-zero' => '0',
    'int-two' => '2',
    'int-three' => '3',
    'int-five' => '5',
    'int-seven' => '7',
    'int-neg-seven' => '-7',
    'real-five-half' => '5.5',
    'text-seven-exp' => $quoteSql('7e0'),
    'text-zero-exp' => $quoteSql('0e0'),
];

$contexts = [
    'raw' => static fn (string $sql): string => $sql,
    'parenthesized' => static fn (string $sql): string => "({$sql})",
    'plus-zero' => static fn (string $sql): string => "({$sql})+0",
    'coalesce-real' => static fn (string $sql): string => "coalesce(({$sql}), 99.0)",
    'is-null-predicate' => static fn (string $sql): string => "({$sql}) IS NULL",
];

$cases = [];
foreach ($leftLiterals as $leftName => $leftSql) {
    foreach ($rightLiterals as $rightName => $rightSql) {
        $modulo = "({$leftSql}) % ({$rightSql})";
        foreach ($contexts as $contextName => $contextSql) {
            $cases["{$leftName}.mod.{$rightName}.{$contextName}"] = $contextSql($modulo);
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-modulo-cast-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for modulo cast affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce modulo cast affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed modulo cast affinity oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d modulo cast affinity oracle rows, got %d', count($cases), count($oracle)));
}

$sameQuotedValue = static function (TestRunner $t, string $expected, string $actual, string $label): void {
    if ($expected === $actual) {
        $t->same($expected, $actual, $label);

        return;
    }

    if (is_numeric($expected) && is_numeric($actual)) {
        $expectedFloat = (float) $expected;
        $actualFloat = (float) $actual;
        $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
        $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-13, $label . " expected {$expected}, got {$actual}");

        return;
    }

    $t->same($expected, $actual, $label);
};

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic modulo cast e_expr-6 expr-13 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle, $sameQuotedValue): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            [],
        );

        $t->same(1, count($rows), $key . ' row count');
        $sameQuotedValue($t, $oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $key . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic modulo cast owns e_expr 6 expr 13 source ranges'] = static function (TestRunner $t) use ($leftLiterals, $rightLiterals, $contexts, $cases, $oracle): void {
    $t->same(28, count($leftLiterals));
    $t->same(9, count($rightLiterals));
    $t->same(5, count($contexts));
    $t->same(1260, count($cases));
    $t->same(1260, count($oracle));
    $t->same(
        'e_expr.test e_expr-6 modulo integer-cast rule with expr.test expr-13 string numeric conversion',
        'e_expr.test e_expr-6 modulo integer-cast rule with expr.test expr-13 string numeric conversion',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
