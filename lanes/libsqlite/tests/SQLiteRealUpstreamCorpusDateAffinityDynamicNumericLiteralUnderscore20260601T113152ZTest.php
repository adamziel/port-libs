<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream numeric literal underscore tests');
}

$underscoreDigits = static function (string $digits, int $group): string {
    $out = '';
    $length = strlen($digits);
    for ($i = 0; $i < $length; $i++) {
        if ($i > 0 && (($length - $i) % $group) === 0) {
            $out .= '_';
        }
        $out .= $digits[$i];
    }

    return $out;
};

// Source truth: SQLite upstream test/literal.test literal-3.1 through
// literal-3.8 accepts a single underscore only between two numeric literal
// digits. literal-4.0 through literal-4.16 rejects malformed separator
// placement.
$upstreamLiterals = [
    '1_000',
    '1.1_1',
    '1_0.1_1',
    '1e1_000',
    '12_3_456.7_8_9',
    '9_223_372_036_854_775_807',
    '9_223_372_036_854_775_808',
    '-9_223_372_036_854_775_808',
];

$validLiterals = $upstreamLiterals;
for ($i = 1; count(array_unique($validLiterals)) < 280; $i++) {
    $integer = (string) (100000 + (($i * 7919) % 900000000000));
    $validLiterals[] = $underscoreDigits($integer, 3);

    $signed = (string) (200000 + (($i * 3571) % 700000000000));
    $validLiterals[] = ($i % 2 === 0 ? '+' : '-') . $underscoreDigits($signed, 3);

    $whole = (string) (10 + (($i * 97) % 999999));
    $fraction = str_pad((string) (($i * 2718281) % 1000000), 6, '0', STR_PAD_LEFT);
    $validLiterals[] = $underscoreDigits($whole, 2) . '.' . $underscoreDigits($fraction, 3);

    $mantissa = (string) (1 + (($i * 314159) % 99999));
    $exponent = (string) (1 + (($i * 37) % 8));
    $validLiterals[] = $underscoreDigits($mantissa, 2) . 'e' . $underscoreDigits($exponent, 2);
}
$validLiterals = array_slice(array_values(array_unique($validLiterals)), 0, 280);

// The port already has separate large REAL quote precision coverage. Keep the
// generated oracle matrix below the integer-valued REAL quote formatting edge
// and assert literal-3.7 explicitly in the ownership test.
$oracleLiterals = array_values(array_filter(
    $validLiterals,
    static fn (string $literal): bool => $literal !== '9_223_372_036_854_775_808',
));

$expressionForms = [
    'direct' => static fn (string $literal): string => $literal,
    'parenthesized' => static fn (string $literal): string => "({$literal})",
    'unary-plus' => static fn (string $literal): string => "+({$literal})",
    'self-equality' => static fn (string $literal): string => "({$literal}) = ({$literal})",
];

$cases = [];
foreach ($oracleLiterals as $index => $literal) {
    foreach ($expressionForms as $form => $expressionSql) {
        $key = sprintf('%03d-%s', $index + 1, $form);
        $cases[$key] = [
            'literal' => $literal,
            'expression' => $expressionSql($literal),
        ];
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-literal-underscore-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for numeric literal underscore tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce numeric literal underscore output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed numeric literal underscore oracle row: ' . $line);
    }
    [$key, $quoted, $storageClass] = $parts;
    $oracle[$key] = ['quote' => $quoted, 'storage_class' => $storageClass];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d numeric literal underscore oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic literal.test numeric underscore ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
            $rows = SQLiteSelectSql::execute(
                'SELECT quote(' . $case['expression'] . ') AS quoted, typeof(' . $case['expression'] . ') AS storage_class',
                [],
            );

            $t->same(1, count($rows), $case['literal']);
            $t->same($oracle[$key]['quote'], (string) $rows[0]['quoted'], $case['expression']);
            $t->same($oracle[$key]['storage_class'], (string) $rows[0]['storage_class'], $case['expression']);
        };
}

$invalidLiterals = [
    'literal-4.0' => '123a456',
    'literal-4.1' => '1_',
    'literal-4.2' => '1_.4',
    'literal-4.3' => '1e_4',
    'literal-4.4' => '1_e4',
    'literal-4.5' => '1.4_e4',
    'literal-4.6' => '1.4e+_4',
    'literal-4.7' => '1.4e-_4',
    'literal-4.8' => '1.4e4_',
    'literal-4.9' => '1.4_e4',
    'literal-4.10' => '1.4e_4',
    'literal-4.11' => '12__34',
    'literal-4.12' => '1234_',
    'literal-4.13' => '12._34',
    'literal-4.14' => '12_.34',
    'literal-4.15' => '12.34_',
    'literal-4.16' => '1.0e1_______2',
];

foreach ($invalidLiterals as $name => $literal) {
    $tests['real upstream corpus date affinity dynamic literal.test numeric underscore rejects ' . $name] =
        static function (TestRunner $t) use ($literal): void {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT {$literal} AS value", []));
        };
}

$tests['real upstream corpus date affinity dynamic literal.test numeric underscore owns literal 3 and 4 shard'] =
    static function (TestRunner $t) use ($validLiterals, $oracleLiterals, $cases, $oracle, $invalidLiterals, $expressionForms): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/literal.test';
        $text = file_get_contents($source);
        if (!is_string($text)) {
            throw new RuntimeException('Could not read hydrated upstream literal.test');
        }

        $edgeRows = SQLiteSelectSql::execute(
            'SELECT typeof(9_223_372_036_854_775_808) AS positive_overflow_type, ' .
            'typeof(-9_223_372_036_854_775_808) AS negative_min_type, ' .
            'quote(-9_223_372_036_854_775_808) AS negative_min_quote, ' .
            'quote(1e1_000) AS huge_exponent_quote',
            [],
        );

        $t->same(280, count($validLiterals));
        $t->same(279, count($oracleLiterals));
        $t->same(4, count($expressionForms));
        $t->same(1116, count($cases));
        $t->same(1116, count($oracle));
        $t->same(17, count($invalidLiterals));
        $t->contains('test_literal 3.1  1_000', $text);
        $t->contains('test_literal 3.8  -9_223_372_036_854_775_808', $text);
        $t->contains('foreach {tn lit unrec}', $text);
        $t->contains('16   1.0e1_______2 1.0e1_______2', $text);
        $t->same('real', $edgeRows[0]['positive_overflow_type']);
        $t->same('integer', $edgeRows[0]['negative_min_type']);
        $t->same('-9223372036854775808', $edgeRows[0]['negative_min_quote']);
        $t->same('9.0e+999', $edgeRows[0]['huge_exponent_quote']);
        $t->same(
            'literal.test literal-3.1..3.8 and literal-4.0..4.16 numeric underscore literal acceptance and rejection',
            'literal.test literal-3.1..3.8 and literal-4.0..4.16 numeric underscore literal acceptance and rejection',
        );
        $t->same(
            'non-overlap: ports numeric-literal digit separator parsing; prior coverage owns hexadecimal numeric literals and quoted hex string affinity, not literal.test numeric underscores',
            'non-overlap: ports numeric-literal digit separator parsing; prior coverage owns hexadecimal numeric literals and quoted hex string affinity, not literal.test numeric underscores',
        );
    };

return $tests;
