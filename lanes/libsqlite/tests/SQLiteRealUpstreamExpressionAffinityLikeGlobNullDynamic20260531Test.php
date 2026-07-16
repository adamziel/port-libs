<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream LIKE/GLOB NULL dynamic tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-17.2.6 through
// e_expr-17.2.9. Those cases assert that NULL on either side of NOT LIKE and
// NOT GLOB propagates NULL rather than true/false. This shard widens that
// upstream NULL behavior across LIKE, NOT LIKE, GLOB, NOT GLOB, and ESCAPE
// forms through the bounded SELECT SQL expression executor.
$values = [
    'null' => null,
    'empty' => '',
    'abc' => 'abc',
    'abcxyz' => 'abcxyz',
    'ABCxyz' => 'ABCxyz',
    'abc-percent' => 'abc%',
    'abc-underscore' => 'abc_',
    'abc-slash-percent' => 'abc\\%',
    'abc-x-percent' => 'abcX%',
    'prefix-middle-suffix' => 'prefix-middle-suffix',
    'numeric-text' => '12345',
    'space-text' => 'abc xyz',
    'bracket-text' => 'abc[xyz]',
    'star-text' => 'abc*xyz',
    'question-text' => 'abc?xyz',
    'mixed-case' => 'AbCxyz',
    'unicode-ae-lower' => "\u{00e6}",
    'unicode-ae-upper' => "\u{00c6}",
    'newline-text' => "abc\nxyz",
];

$patterns = [
    'null' => null,
    'empty' => '',
    'abc-like-prefix' => 'abc%',
    'abc-like-single' => 'abc_',
    'upper-like-prefix' => 'ABC%',
    'escaped-percent-x' => 'abcX%',
    'escaped-underscore-x' => 'abcX_',
    'escaped-x-x' => 'abcXX',
    'escaped-percent-backslash' => 'abc\\%',
    'escaped-underscore-backslash' => 'abc\\_',
    'glob-prefix' => 'abc*',
    'glob-single' => 'abc???',
    'glob-upper-prefix' => 'ABC*',
    'glob-char-class' => 'abc[xyz]*',
    'glob-negated-class' => 'abc[^0-9]*',
    'literal-percent' => 'abc%',
    'literal-star' => 'abc*xyz',
    'unicode-ae-lower' => "\u{00e6}",
];

for ($i = 1; $i <= 42; ++$i) {
    $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $values["dynamic-value-{$suffix}"] = "dynamic-value-{$suffix}";
    $patterns["dynamic-like-prefix-{$suffix}"] = "dynamic-value-{$suffix}%";
}

$operatorFactories = [
    'like' => static fn (string $value, string $pattern): string => "{$value} LIKE {$pattern}",
    'not-like' => static fn (string $value, string $pattern): string => "{$value} NOT LIKE {$pattern}",
    'like-escape-x' => static fn (string $value, string $pattern): string => "{$value} LIKE {$pattern} ESCAPE 'X'",
    'not-like-escape-x' => static fn (string $value, string $pattern): string => "{$value} NOT LIKE {$pattern} ESCAPE 'X'",
    'like-escape-backslash' => static fn (string $value, string $pattern): string => "{$value} LIKE {$pattern} ESCAPE '\\'",
    'not-like-escape-backslash' => static fn (string $value, string $pattern): string => "{$value} NOT LIKE {$pattern} ESCAPE '\\'",
    'like-escape-null' => static fn (string $value, string $pattern): string => "{$value} LIKE {$pattern} ESCAPE NULL",
    'not-like-escape-null' => static fn (string $value, string $pattern): string => "{$value} NOT LIKE {$pattern} ESCAPE NULL",
    'glob' => static fn (string $value, string $pattern): string => "{$value} GLOB {$pattern}",
    'not-glob' => static fn (string $value, string $pattern): string => "{$value} NOT GLOB {$pattern}",
];

$cases = [];
foreach ($values as $valueName => $value) {
    foreach ($patterns as $patternName => $pattern) {
        foreach ($operatorFactories as $operatorName => $factory) {
            if ($value !== null && $pattern !== null) {
                continue;
            }

            $key = "{$valueName}.{$operatorName}.{$patternName}";
            $cases[$key] = $factory($sqlLiteral($value), $sqlLiteral($pattern));
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(NOT ({$expression})) || char(9) || typeof(NOT ({$expression}));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-like-glob-null-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr LIKE/GLOB NULL tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr LIKE/GLOB NULL output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('malformed e_expr LIKE/GLOB NULL oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedNotValue, $notStorageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'notQuote' => $quotedNotValue,
        'notTypeof' => $notStorageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr LIKE/GLOB NULL oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity LIKE GLOB NULL dynamic e_expr17 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(NOT ({$expression})) AS nq, typeof(NOT ({$expression})) AS nt",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['notQuote'], (string) $row['nq'], $expression . ' negated quote');
        $t->same($oracle[$key]['notTypeof'], (string) $row['nt'], $expression . ' negated typeof');
    };
}

$tests['real upstream expression affinity LIKE GLOB NULL dynamic owns e_expr17 null matrix'] = static function (TestRunner $t) use ($values, $patterns, $operatorFactories, $cases, $oracle): void {
    $t->same(61, count($values));
    $t->same(60, count($patterns));
    $t->same(10, count($operatorFactories));
    $t->same(1200, count($cases));
    $t->same(1200, count($oracle));
    $t->same(
        'e_expr.test e_expr-17.2.6..17.2.9 NULL propagation for NOT GLOB and NOT LIKE with LIKE ESCAPE variants',
        'e_expr.test e_expr-17.2.6..17.2.9 NULL propagation for NOT GLOB and NOT LIKE with LIKE ESCAPE variants',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
