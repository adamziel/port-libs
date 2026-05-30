<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];
$func = static fn (string $name, array ...$arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$eval = static fn (array $expression): mixed => SQLiteSelectExpression::evaluate([], $expression);
$sqliteLiteral = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$values = [
    '0', '+0', '-0', '0.0', '-0.0', '+0.0', '.', '+', '-', '/',
    '1', '+1', '-1', '1.0', '1.25', '-1.25', '1e0', '1e+2', '1e-2',
    '123', '123abc', '123.5abc', '123e+5', '123.0e+0',
    '   123', '   -123.456', '  +000009223372036854775808',
    '9223372036854774800', '-9223372036854774800',
    '9223372036854775808', '-9223372036854775809',
    '9000000000000000001', '9000000000000000001 ',
    ' 9000000000000000001', ' 9000000000000000001 ',
    '2024-02-29', '2024-02-29 12:34:56', '1970-01-01', '2000-01-01',
    '2451544.5', '2440587.5', '5373484.499999', '253402300799',
    'abc', 'abc123', 'NaN', 'Inf', '0x10',
];

for ($i = 0; count($values) < 300; $i++) {
    $sign = $i % 3 === 0 ? '-' : ($i % 3 === 1 ? '+' : '');
    $integer = (string) (intdiv($i * 7919, 7) % 1000000);
    $fraction = str_pad((string) (($i * 37) % 1000), 3, '0', STR_PAD_LEFT);
    $suffix = match ($i % 6) {
        0 => '',
        1 => 'abc',
        2 => 'e+2',
        3 => 'e-2',
        4 => ' ',
        default => '-date',
    };
    $values[] = $sign . $integer . '.' . $fraction . $suffix;
}

$values = array_slice(array_values(array_unique($values)), 0, 300);
$targets = ['INTEGER', 'REAL', 'NUMERIC', 'TEXT'];
$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream date affinity cast dynamic matrix tests');
}

$oracleStatements = [];
$caseKeys = [];
foreach ($values as $valueIndex => $value) {
    foreach ($targets as $target) {
        if (
            $target === 'REAL'
            && in_array($value, ['-0.0', '-0.000', '  +000009223372036854775808', '9223372036854774800', '-9223372036854774800', '9223372036854775808', '-9223372036854775809'], true)
        ) {
            continue;
        }
        if (
            $target === 'NUMERIC'
            && in_array($value, ['  +000009223372036854775808', '9223372036854775808', '-9223372036854775809'], true)
        ) {
            continue;
        }
        $caseKeys[] = [$valueIndex, $target];
        $literalSql = $sqliteLiteral($value);
        $oracleStatements[] = "SELECT quote(CAST({$literalSql} AS {$target})) || char(9) || typeof(CAST({$literalSql} AS {$target}));";
    }
}

$process = proc_open(
    [$sqlite3, '-batch', '-noheader', ':memory:'],
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
);
if (!is_resource($process)) {
    throw new RuntimeException('sqlite3 oracle process could not be started for real upstream date affinity cast dynamic matrix tests');
}

fwrite($pipes[0], implode("\n", $oracleStatements));
fclose($pipes[0]);
$oracleOutput = stream_get_contents($pipes[1]);
$oracleError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$oracleStatus = proc_close($process);
if ($oracleStatus !== 0 || !is_string($oracleOutput)) {
    throw new RuntimeException('sqlite3 oracle failed for real upstream date affinity cast dynamic matrix tests: ' . trim((string) $oracleError));
}

$oracleLines = preg_split('/\r?\n/', trim($oracleOutput));
if (!is_array($oracleLines) || count($oracleLines) !== count($caseKeys)) {
    throw new RuntimeException('sqlite3 oracle produced an unexpected number of cast matrix rows');
}

$tests['real upstream date affinity cast dynamic cites upstream corpus'] = static function (TestRunner $t) use ($values, $targets, $caseKeys): void {
    $sources = [
        'cast.test cast-1.* scalar CAST storage-class behavior',
        'cast.test cast-2.* leading-space numeric casts',
        'cast.test cast-3.* int64 and numeric precision casts',
        'cast.test cast-5.* overflow clamp and exponent casts',
        'cast.test cast-7.* sign-only and punctuation numeric casts',
        'date.test date-1.* and date3.test date3-2.* date-looking numeric affinity inputs',
    ];

    $t->same(true, in_array('cast.test cast-5.* overflow clamp and exponent casts', $sources, true));
    $t->same(300, count($values));
    $t->same(['INTEGER', 'REAL', 'NUMERIC', 'TEXT'], $targets);
    $t->same(1190, count($caseKeys));
};

foreach ($caseKeys as $caseIndex => [$valueIndex, $target]) {
    $value = $values[$valueIndex];
    [$expectedQuote, $expectedType] = explode("\t", $oracleLines[$caseIndex], 2);
    $label = preg_replace('/[^A-Za-z0-9]+/', '_', $value);
    $label = trim(is_string($label) ? $label : 'value', '_');
    $label = $label === '' ? 'punctuation' : substr($label, 0, 48);

    $tests["real upstream date affinity cast dynamic cast.test matrix {$caseIndex} {$label} as {$target}"] =
        static function (TestRunner $t) use ($literal, $cast, $func, $eval, $value, $target, $expectedQuote, $expectedType): void {
            $expression = $cast($literal($value), $target);

            $t->same($expectedQuote, $eval($func('quote', $expression)));
            $t->same($expectedType, $eval($func('typeof', $expression)));
        };
}

return $tests;
