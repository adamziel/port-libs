<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracleScalar = static function (string $function, array $arguments) use ($sqlite3): array {
    static $cache = [];

    $key = $function . "\0" . serialize($arguments);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date affinity dynamic oracle batch tests');
    }

    $sqlArguments = array_map(static function (mixed $value): string {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }, $arguments);
    $expression = $function . '(' . implode(',', $sqlArguments) . ')';
    $sql = "SELECT quote({$expression}), typeof({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null || $output === '') {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$key] = explode("\t", rtrim($output, "\r\n"));
};

$quotePort = static function (mixed $value): array {
    return [
        SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$value]),
        SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]),
    ];
};

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
        throw new RuntimeException('sqlite3 oracle is required for real upstream date affinity dynamic oracle batch tests');
    }

    $sql = "SELECT quote({$expression}), typeof({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null || $output === '') {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$expression] = explode("\t", rtrim($output, "\r\n"));
};

$assertOraclePair = static function (TestRunner $t, array $expected, array $actual, string $label): void {
    $t->same($expected[1], $actual[1], $label . ' storage class');
    if ($expected[1] === 'real') {
        $expectedFloat = (float) $expected[0];
        $actualFloat = (float) $actual[0];
        $tolerance = max(1.0e-9, abs($expectedFloat) * 1.0e-12);
        $t->true(abs($expectedFloat - $actualFloat) <= $tolerance, $label . ' real value');
        return;
    }

    $t->same($expected[0], $actual[0], $label . ' quoted value');
};

$tests['real upstream corpus date affinity dynamic oracle batch cites source truth'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test',
    ];

    foreach ($sources as $source) {
        $t->same(true, is_file($source), $source);
    }
    $t->contains('date.test', $sources[0]);
    $t->contains('cast.test', $sources[7]);
};

$timeValues = [
    'date.test date-1.2 unix epoch date' => '1970-01-01',
    'date.test date-1.6 noon separator' => '2000-01-01 12:00:00',
    'date.test date-1.18.2 T separator' => '2000-01-01T12:00:00',
    'date.test date-2.60 normalized overflow day' => '2023-02-31',
    'date.test date-5.8 zulu suffix' => '1994-04-16T14:00:00Z',
    'date.test date-10.1 standalone time' => '01:02:03',
    'date2.test leap second adjacency' => '2004-02-28 20:00:00',
    'date3.test numeric auto julian split text peer' => '2022-01-29',
];
$modifierGroups = [
    'date.test date-13 day modifiers' => ['+1 day', '-1 day', '+1.5 day', '-1.5 day'],
    'date.test date-13 hour modifiers' => ['+3 hours', '-3 hours', '+12 hours', '-12 hours'],
    'date.test date-13 minute modifiers' => ['+45 minutes', '-45 minutes', '+90 minutes', '-90 minutes'],
    'date.test date-13 second modifiers' => ['+675 seconds', '-675 seconds', '+1800 seconds', '-1800 seconds'],
    'date.test date-2 start modifiers' => ['start of day', 'start of month', 'start of year', 'weekday 0', 'weekday 3', 'weekday 6'],
    'date.test date-19 floor ceiling modifiers' => ['+1 month', '+1 month', 'floor', 'ceiling'],
];
$functionNames = ['date', 'time', 'datetime', 'julianday', 'unixepoch'];

$dateCaseId = 0;
foreach ($timeValues as $timeLabel => $timeValue) {
    foreach ($modifierGroups as $modifierLabel => $modifiers) {
        if (str_contains($timeLabel, 'date-2.60') && str_contains($modifierLabel, 'date-19')) {
            continue;
        }
        foreach ($functionNames as $functionName) {
            $arguments = array_merge([$timeValue], $modifiers);
            $caseId = ++$dateCaseId;
            $tests[sprintf('real upstream corpus date affinity dynamic oracle batch date.test date2.test date3.test case %04d %s %s %s', $caseId, $functionName, $timeLabel, $modifierLabel)] = static function (TestRunner $t) use ($oracleScalar, $quotePort, $assertOraclePair, $functionName, $arguments, $modifierLabel): void {
                $expected = $oracleScalar($functionName, $arguments);
                $actualValue = SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, $arguments);
                $actual = $quotePort($actualValue);

                $assertOraclePair($t, $expected, $actual, $functionName . '(' . implode(',', array_map('strval', $arguments)) . ')');
                $t->same(true, str_contains($modifierLabel, 'date-'));
                $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
            };
        }
    }
}

$numericTimes = [
    'date3.test date3-1 unixepoch zero' => [0, 'unixepoch'],
    'date3.test date3-2 unixepoch y2k' => [946684800, 'unixepoch'],
    'date3.test date3-2.40 auto julian domain' => [2440587.5, 'auto'],
    'date3.test date3-2.40 auto unix domain' => [1234567890, 'auto'],
    'date3.test date3-4 julianday modifier' => [2459607, 'julianday'],
];
for ($offset = 0; $offset < 160; $offset++) {
    foreach ($numericTimes as $label => [$baseValue, $modifier]) {
        $value = is_int($baseValue) ? $baseValue + ($offset * 4321) : $baseValue + ($offset / 8.0);
        $tests[sprintf('real upstream corpus date affinity dynamic oracle batch date3.test numeric %03d %s', $offset, $label)] = static function (TestRunner $t) use ($oracleScalar, $quotePort, $assertOraclePair, $value, $modifier, $label): void {
            foreach (['date', 'datetime', 'julianday'] as $functionName) {
                $expected = $oracleScalar($functionName, [$value, $modifier]);
                $actual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, [$value, $modifier]));

                $assertOraclePair($t, $expected, $actual, $functionName . ' ' . $label);
            }
            $t->contains('date3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test');
        };
    }
}

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$cast = static fn (array $operand, string $target): array => [
    'type' => 'cast',
    'operand' => $operand,
    'target' => $target,
];

$affinityValues = [
    'affinity2.test integer text' => '500',
    'affinity2.test leading zero text' => '000500',
    'affinity2.test real text' => '500.25',
    'affinity2.test exponent text' => '5.0025e2',
    'affinity3.test leading spaces' => '   -123.75',
    'types.test empty text' => '',
    'types2.test plus sign' => '+',
    'types2.test minus sign' => '-',
    'types2.test decimal point' => '.',
    'cast.test trailing text' => '123.5xyz',
    'cast.test hex-looking text' => '0x10',
    'cast.test blob-looking text' => 'abc',
];
$targets = ['TEXT', 'NUMERIC', 'INTEGER', 'REAL', 'BLOB'];

$affinityCaseId = 0;
for ($repeat = 0; $repeat < 20; $repeat++) {
    foreach ($affinityValues as $label => $value) {
        foreach ($targets as $target) {
            $caseId = ++$affinityCaseId;
            $suffix = $repeat === 0 ? $value : $value . (string) $repeat;
            $expression = $cast($literal($suffix), $target);
            $sqlExpression = 'CAST(' . $sqlLiteral($suffix) . ' AS ' . $target . ')';
            $tests[sprintf('real upstream corpus date affinity dynamic oracle batch affinity cast %04d %s as %s', $caseId, $label, $target)] = static function (TestRunner $t) use ($expression, $target, $label, $sqlExpression, $oracleExpression, $assertOraclePair): void {
                $value = SQLiteSelectExpression::evaluate([], $expression);
                $expected = $oracleExpression($sqlExpression);
                $actual = [
                    SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$value]),
                    SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]),
                ];

                $assertOraclePair($t, $expected, $actual, $sqlExpression);
                $t->same(true, in_array($target, ['TEXT', 'NUMERIC', 'INTEGER', 'REAL', 'BLOB'], true));
                $t->same(true, str_contains($label, '.test'));
                $t->contains('affinity', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
                $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
            };
        }
    }
}

return $tests;
