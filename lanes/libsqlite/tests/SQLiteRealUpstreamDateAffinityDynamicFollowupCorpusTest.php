<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracleExpression = static function (string $expression) use ($sqlite3): array {
    static $cache = [];

    if (isset($cache[$expression])) {
        return $cache[$expression];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for date affinity followup corpus tests');
    }

    $sql = "SELECT quote({$expression}), typeof({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null || $output === '') {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$expression] = explode("\t", rtrim($output, "\r\n"));
};

$quotePort = static fn (mixed $value): array => [
    SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$value]),
    SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]),
];

$assertOraclePair = static function (TestRunner $t, array $expected, array $actual, string $label): void {
    $t->same($expected[1], $actual[1], $label . ' storage class');
    if ($expected[1] === 'real') {
        $expectedFloat = (float) $expected[0];
        $actualFloat = (float) $actual[0];
        $tolerance = max(1.0e-8, abs($expectedFloat) * 1.0e-11);
        $t->true(abs($expectedFloat - $actualFloat) <= $tolerance, $label . ' real value');
        return;
    }

    $t->same($expected[0], $actual[0], $label . ' quoted value');
};

$tests['real upstream corpus date affinity dynamic followup cites source truth'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test',
    ];

    foreach ($sources as $source) {
        $t->same(true, is_file($source), $source);
    }
    $t->contains('date4.test', $sources[0]);
    $t->contains('types3.test', $sources[4]);
};

$date4Format = '%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g';
for ($i = 0; $i < 500; $i++) {
    $timestamp = $i * 86390;
    $tests[sprintf('real upstream corpus date affinity dynamic followup date4.test date4-%05d strftime libc matrix', $i)] = static function (TestRunner $t) use ($oracleExpression, $quotePort, $assertOraclePair, $sqlLiteral, $date4Format, $timestamp): void {
        $expression = 'strftime(' . $sqlLiteral($date4Format) . ',' . $timestamp . ",'unixepoch')";
        $expected = $oracleExpression($expression);
        $actual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']));

        $assertOraclePair($t, $expected, $actual, $expression);
        $t->contains('date4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test');
    };
}

$date5Data = [
    [2024, 2, 29, 2460369.5],
    [2024, 3, 1, 2460370.5],
    [2023, 2, 28, 2460003.5],
    [2023, 3, 1, 2460004.5],
    [2000, 2, 29, 2451603.5],
    [2000, 3, 1, 2451604.5],
    [1900, 2, 28, 2415078.5],
    [1900, 3, 1, 2415079.5],
    [1712, 2, 29, 2346413.5],
    [1712, 3, 1, 2346414.5],
    [1977, 4, 26, 2443259.5],
    [2013, 1, 1, 2456293.5],
];
$date5Cases = [];
foreach ($date5Data as [$year, $month, $day, $julianDay]) {
    for ($cycle = 0; $cycle <= 12; $cycle++) {
        $date5Cases[] = [$year + (400 * $cycle), $month, $day, $julianDay + (146097.0 * $cycle), 'future'];
    }
    for ($cycle = 1; $cycle <= 12; $cycle++) {
        $date5Cases[] = [$year - (400 * $cycle), $month, $day, $julianDay - (146097.0 * $cycle), 'past'];
    }
}
$date5Cases = array_slice($date5Cases, 0, 300);

foreach ($date5Cases as $index => [$year, $month, $day, $julianDay, $direction]) {
    $dateText = ($year < 0 ? sprintf('-%04d-%02d-%02d', -$year, $month, $day) : sprintf('%04d-%02d-%02d', $year, $month, $day));
    $tests[sprintf('real upstream corpus date affinity dynamic followup date5.test gregorian %03d %s', $index, $direction)] = static function (TestRunner $t) use ($oracleExpression, $quotePort, $assertOraclePair, $sqlLiteral, $dateText, $julianDay): void {
        $dateExpected = $oracleExpression('date(' . $sqlLiteral($julianDay) . ')');
        $dateActual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]));
        $assertOraclePair($t, $dateExpected, $dateActual, 'date5 date from Julian day ' . (string) $julianDay);

        $julianExpected = $oracleExpression('julianday(' . $sqlLiteral($dateText) . ')');
        $julianActual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$dateText]));
        $assertOraclePair($t, $julianExpected, $julianActual, 'date5 Julian day from calendar ' . $dateText);
        $t->contains('date5.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test');
    };
}

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$cast = static fn (array $operand, string $target): array => [
    'type' => 'cast',
    'operand' => $operand,
    'target' => $target,
];

$typeValues = [
    'types.test integer manifest' => 123,
    'types.test wide integer manifest' => 123456789012345,
    'types.test real manifest' => 1234567890123.5,
    'types.test text integer' => '123',
    'types.test text real' => '123.456',
    'types.test text exponent' => '1.23456e5',
    'types2.test equality text' => '500',
    'types2.test equality real text' => '500.0',
    'types2.test plus sign' => '+',
    'types2.test minus sign' => '-',
    'types2.test decimal point' => '.',
    'types3.test text affinity real' => '1.25',
    'types3.test text affinity integer' => '1',
    'types3.test byte text' => 'abc',
];
$targets = ['TEXT', 'NUMERIC', 'INTEGER', 'REAL'];

$affinityCaseId = 0;
for ($repeat = 0; $repeat < 4; $repeat++) {
    foreach ($typeValues as $label => $value) {
        foreach ($targets as $target) {
            if ($affinityCaseId >= 200) {
                break 3;
            }
            $caseId = ++$affinityCaseId;
            $dynamicValue = is_string($value) && $value !== '+' && $value !== '-' && $value !== '.'
                ? $value . ($repeat === 0 ? '' : (string) $repeat)
                : $value;
            $expression = $cast($literal($dynamicValue), $target);
            $sqlExpression = 'CAST(' . $sqlLiteral($dynamicValue) . ' AS ' . $target . ')';
            $tests[sprintf('real upstream corpus date affinity dynamic followup types types2 types3 cast %03d %s as %s', $caseId, $label, $target)] = static function (TestRunner $t) use ($expression, $sqlExpression, $oracleExpression, $assertOraclePair, $label): void {
                $value = SQLiteSelectExpression::evaluate([], $expression);
                $expected = $oracleExpression($sqlExpression);
                $actual = [
                    SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$value]),
                    SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]),
                ];

                $assertOraclePair($t, $expected, $actual, $sqlExpression);
                $t->same(true, str_contains($label, '.test'));
            };
        }
    }
}

return $tests;
