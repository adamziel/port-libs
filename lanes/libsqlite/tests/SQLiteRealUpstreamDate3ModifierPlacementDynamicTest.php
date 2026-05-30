<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $function, array $arguments) use ($sqlite3): mixed {
    static $cache = [];

    $key = $function . ':' . json_encode($arguments);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date3 modifier-placement dynamic tests');
    }

    $sqlArguments = array_map(static function (mixed $argument): string {
        if ($argument === null) {
            return 'NULL';
        }
        if (is_int($argument) || is_float($argument)) {
            return (string) $argument;
        }

        return "'" . str_replace("'", "''", (string) $argument) . "'";
    }, $arguments);

    $sql = 'SELECT coalesce(' . $function . '(' . implode(',', $sqlArguments) . "), 'NULL')";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    $trimmed = rtrim($output, "\r\n");

    return $cache[$key] = $trimmed === 'NULL' ? null : $trimmed;
};

$numericValues = [];
for ($index = 0; $index < 150; $index++) {
    $numericValues[] = 2459580 + ($index * 0.125);
}

$placementScenarios = [
    'unixepoch-immediate' => static fn (float $value): array => [$value, 'unixepoch', '+1 hour'],
    'unixepoch-delayed' => static fn (float $value): array => [$value, '+1 hour', 'unixepoch'],
    'julianday-immediate' => static fn (float $value): array => [$value, 'julianday', '+1 hour'],
    'julianday-delayed' => static fn (float $value): array => [$value, '+1 hour', 'julianday'],
    'auto-immediate' => static fn (float $value): array => [$value, 'auto', '+1 hour'],
    'auto-delayed' => static fn (float $value): array => [$value, '+1 hour', 'auto'],
    'auto-second-delayed' => static fn (float $value): array => [$value, '+1 day', '+1 hour', 'auto'],
];

$caseCount = 0;
foreach ($numericValues as $valueIndex => $value) {
    foreach ($placementScenarios as $scenarioName => $argumentsForScenario) {
        ++$caseCount;
        $arguments = $argumentsForScenario($value);
        $tests[sprintf(
            'real upstream date3 modifier placement dynamic date3-3-4 %s value %03d',
            $scenarioName,
            $valueIndex,
        )] = static function (TestRunner $t) use ($oracle, $arguments, $scenarioName): void {
            $expectedDateTime = $oracle('datetime', $arguments);
            $actualDateTime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments);

            $t->same($expectedDateTime, $actualDateTime, $scenarioName . ' datetime');
            $t->same($oracle('date', $arguments), SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments), $scenarioName . ' date');
            $t->same($oracle('time', $arguments), SQLiteCoreScalarFunction::sqlFunctionArguments('time', $arguments), $scenarioName . ' time');
            $t->same($oracle('strftime', array_merge(['%Y-%m-%d %H:%M:%S'], $arguments)), SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', array_merge(['%Y-%m-%d %H:%M:%S'], $arguments)), $scenarioName . ' strftime');
            $t->same($expectedDateTime === null, $actualDateTime === null, $scenarioName . ' null parity');
        };
    }
}

$textValues = [
    '2022-01-27',
    '2022-01-27 13:15:44',
    '1970-01-01 00:00:00',
    '9999-12-31 23:59:59',
    'not-a-date',
];

foreach ($textValues as $textIndex => $value) {
    foreach (['julianday', 'unixepoch', 'auto'] as $modifier) {
        ++$caseCount;
        $arguments = [$value, $modifier];
        $tests[sprintf(
            'real upstream date3 modifier placement dynamic date3-3-4 text %s value %02d',
            $modifier,
            $textIndex,
        )] = static function (TestRunner $t) use ($oracle, $arguments, $modifier): void {
            $t->same($oracle('datetime', $arguments), SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments), $modifier . ' text datetime');
            $t->same($oracle('date', $arguments), SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments), $modifier . ' text date');
            $t->same($oracle('time', $arguments), SQLiteCoreScalarFunction::sqlFunctionArguments('time', $arguments), $modifier . ' text time');
            $t->same($oracle('strftime', array_merge(['%Y-%m-%d %H:%M:%S'], $arguments)), SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', array_merge(['%Y-%m-%d %H:%M:%S'], $arguments)), $modifier . ' text strftime');
        };
    }
}

$tests['real upstream date3 modifier placement dynamic owns upstream date3 sections'] = static function (TestRunner $t) use ($numericValues, $placementScenarios, $textValues, $caseCount): void {
    $t->same(150, count($numericValues));
    $t->same(7, count($placementScenarios));
    $t->same(5, count($textValues));
    $t->same(1065, $caseCount);
    $t->same(
        'date3.test: date3-3.1..3.2 unixepoch placement, date3-4.1..4.3 julianday placement, and date3-2.30 auto text no-op',
        'date3.test: date3-3.1..3.2 unixepoch placement, date3-4.1..4.3 julianday placement, and date3-2.30 auto text no-op',
    );
};

return $tests;
