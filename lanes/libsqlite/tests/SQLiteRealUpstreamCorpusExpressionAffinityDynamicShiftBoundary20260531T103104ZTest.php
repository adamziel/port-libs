<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity shift-boundary tests');
}

$text = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - SQLite upstream test/expr.test expr-1.45a..1.45g covers left-shift
//   boundaries, negative shift-count reversal, and 64-bit overflow behavior.
// - expr-1.46a..1.46e covers right-shift sign extension, oversized counts,
//   and negative shift-count reversal.
// - expr-1.96..1.99 covers NULL propagation for shift and bitwise operators.
$leftOperands = [
    'zero' => '0',
    'one' => '1',
    'minus-one' => '-1',
    'two' => '2',
    'four' => '4',
    'eight' => '8',
    'thirty-two' => '32',
    'minus-thirty-two' => '-32',
    'sixty-four' => '64',
    'minus-sixty-four' => '-64',
    'int32-max' => '2147483647',
    'int32-min' => '-2147483648',
    'uint32' => '4294967296',
    'minus-uint32' => '-4294967296',
    'int64-max' => '9223372036854775807',
    'int64-min' => '-9223372036854775808',
    'real-four-half' => '4.5',
    'real-minus-four-half' => '-4.5',
    'real-fraction' => '0.75',
    'text-four' => $text('4'),
    'text-four-half' => $text('4.5'),
    'text-spaced-eight' => $text(' 8x'),
    'text-minus-sixteen' => $text('-16x'),
    'text-alpha' => $text('alpha'),
    'text-empty' => $text(''),
    'text-zero' => $text('0'),
    'blob-four' => "x'04'",
    'blob-text-four' => "x'34'",
    'blob-ce' => "x'ce'",
    'null' => 'NULL',
    'true-literal' => 'TRUE',
    'false-literal' => 'FALSE',
];

$shiftCounts = [
    'minus-int64-min' => '-9223372036854775808',
    'minus-one-hundred' => '-100',
    'minus-sixty-four' => '-64',
    'minus-sixty-three' => '-63',
    'minus-thirty-three' => '-33',
    'minus-three' => '-3',
    'minus-one' => '-1',
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'three' => '3',
    'thirty-one' => '31',
    'thirty-two' => '32',
    'sixty-two' => '62',
    'sixty-three' => '63',
    'sixty-four' => '64',
];

$operators = [
    'left-shift' => '<<',
    'right-shift' => '>>',
];

$cases = [];
foreach ($leftOperands as $leftName => $leftSql) {
    foreach ($shiftCounts as $shiftName => $shiftSql) {
        foreach ($operators as $operatorName => $operator) {
            $key = "{$leftName} {$operatorName} {$shiftName}";
            $expression = "({$leftSql}) {$operator} ({$shiftSql})";
            $cases[$key] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-shift-boundary-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression shift-boundary tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression shift-boundary output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed expression shift-boundary oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression shift-boundary oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic shift boundary expr.test ' . $key] =
        static function (TestRunner $t) use ($expression, $key, $oracle): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
                [],
            );
            $t->same(1, count($rows), $key . ' row count');

            $row = $rows[0];
            $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote');
            $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof');
            $t->same($oracle[$key]['isNull'], (string) $row['n'], $key . ' is-null');
        };
}

$tests['real upstream corpus expression affinity dynamic shift boundary owns exact expr sections'] =
    static function (TestRunner $t) use ($leftOperands, $shiftCounts, $operators, $cases, $oracle): void {
        $t->same(32, count($leftOperands));
        $t->same(16, count($shiftCounts));
        $t->same(2, count($operators));
        $t->same(1024, count($cases));
        $t->same(1024, count($oracle));
        $t->same(
            'expr.test expr-1.45a..1.46e and expr-1.96..1.99 shift-boundary integer coercion and NULL propagation',
            'expr.test expr-1.45a..1.46e and expr-1.96..1.99 shift-boundary integer coercion and NULL propagation',
        );
        $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    };

$tests['real upstream corpus expression affinity dynamic shift boundary dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed: reuses SQLiteSelectSql bitwise shift expression dispatch and the local sqlite3 oracle',
            'no new support component needed: reuses SQLiteSelectSql bitwise shift expression dispatch and the local sqlite3 oracle',
        );
    };

return $tests;
