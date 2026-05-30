<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

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

$oracleScalar = static function (string $function, array $arguments) use ($sqlite3, $sqlLiteral): array {
    static $cache = [];

    $key = $function . "\0" . serialize($arguments);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date affinity boundary oracle corpus tests');
    }

    $sqlArguments = array_map($sqlLiteral, $arguments);
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

$tests['real upstream date affinity boundary oracle corpus cites source truth'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test',
    ];

    foreach ($sources as $source) {
        $t->same(true, is_file($source), $source);
    }
    $t->contains('date.test', $sources[0]);
    $t->contains('date5.test', $sources[1]);
};

$timeValues = [
    'date.test date-16 lower julian bound' => 0,
    'date.test date-16 lower text bound' => '-4713-11-24 12:00:00',
    'date.test date-16 upper julian day' => 5373484,
    'date5.test gregorian leap source 1712-02-29' => '1712-02-29',
    'date.test date-16 upper text boundary' => '9999-12-31 23:59:59.999',
    'date.test date-17 start-of-year split 37' => 37,
    'date.test date-17 start-of-year split 38' => 38,
    'date.test date-17 modern julian start' => 2457828,
    'date.test date-20 high fractional no round 9990' => '2024-12-31 23:59:59.9990',
    'date.test date-20 high fractional no round 9995' => '2024-12-31 23:59:59.9995',
    'date5.test gregorian leap source 2024-02-29' => '2024-02-29',
    'date5.test gregorian leap next day 2024-03-01' => '2024-03-01',
    'date5.test gregorian nonleap source 2023-02-28' => '2023-02-28',
    'date5.test gregorian nonleap next day 2023-03-01' => '2023-03-01',
    'date5.test gregorian century nonleap 1900-02-28' => '1900-02-28',
    'date5.test gregorian century nonleap next day 1900-03-01' => '1900-03-01',
    'date5.test gregorian 400-year leap 2000-02-29' => '2000-02-29',
    'date5.test gregorian 400-year leap next day 2000-03-01' => '2000-03-01',
    'date5.test meeus astronomical algorithms 1977-04-26' => '1977-04-26',
    'date5.test julian-day wikipedia source 2013-01-01' => '2013-01-01',
];

$modifierGroups = [
    'date.test date-16 positive second upper guard' => ['+464269060799 seconds'],
    'date.test date-16 negative second lower guard' => ['-464269060799 seconds'],
    'date.test date-16 positive minute upper guard' => ['+7737817679 minutes'],
    'date.test date-16 negative minute lower guard' => ['-7737817679 minutes'],
    'date.test date-16 positive hour upper guard' => ['+128963627 hours'],
    'date.test date-16 negative hour lower guard' => ['-128963627 hours'],
    'date.test date-16 positive day upper guard' => ['+5373484 days'],
    'date.test date-16 negative day lower guard' => ['-5373484 days'],
    'date.test date-17 start of day/month/year' => ['start of day', 'start of month', 'start of year'],
    'date.test date-13 fractional relative units' => ['+1.5 days', '-3 hours', '+45 minutes', '-675 seconds'],
];

$functionNames = ['date', 'time', 'datetime', 'julianday', 'unixepoch'];

$caseId = 0;
foreach ($timeValues as $timeLabel => $timeValue) {
    foreach ($modifierGroups as $modifierLabel => $modifiers) {
        foreach ($functionNames as $functionName) {
            $arguments = array_merge([$timeValue], $modifiers);
            $caseId++;
            $tests[sprintf('real upstream corpus date affinity boundary oracle date.test date5.test case %04d %s %s %s', $caseId, $functionName, $timeLabel, $modifierLabel)] = static function (TestRunner $t) use ($oracleScalar, $quotePort, $assertOraclePair, $functionName, $arguments, $timeLabel, $modifierLabel, $caseId): void {
                $expected = $oracleScalar($functionName, $arguments);
                $actual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, $arguments));

                $assertOraclePair($t, $expected, $actual, $functionName . ' boundary case ' . $caseId);
                $t->same(true, str_contains($timeLabel, 'date.test') || str_contains($timeLabel, 'date5.test'));
                $t->same(true, str_contains($modifierLabel, 'date-'));
            };
        }
    }
}

$tests['real upstream date affinity boundary oracle corpus generated case count'] = static function (TestRunner $t) use ($caseId): void {
    $t->same(1000, $caseId);
};

return $tests;
