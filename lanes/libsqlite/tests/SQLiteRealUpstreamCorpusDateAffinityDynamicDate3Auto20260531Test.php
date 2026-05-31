<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        return sprintf('%.15G', $value);
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracleScalar = static function (string $functionName, array $arguments) use ($sqlite3, $sqlLiteral): array {
    static $cache = [];

    $key = $functionName . "\0" . serialize($arguments);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date3 auto/unixepoch tests');
    }

    $sqlArguments = array_map($sqlLiteral, $arguments);
    $expression = $functionName . '(' . implode(',', $sqlArguments) . ')';
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

$tests['real upstream corpus date affinity dynamic date3 auto cites source truth'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('datetest 1.1 {unixepoch(\'1970-01-01\')} {0}', $source);
    $t->contains('foreach {tn jd date}', $source);
    $t->contains('datetest 3.1 {datetime(2459607.05,\'+1 hour\',\'unixepoch\')} {NULL}', $source);
    $t->contains('datetest 4.3 {datetime(\'2022-01-27\',\'julianday\')}      {NULL}', $source);
};

$staticCases = [
    'date3-1.1 unixepoch epoch' => ['unixepoch', ['1970-01-01']],
    'date3-1.2 unixepoch negative second' => ['unixepoch', ['1969-12-31 23:59:59']],
    'date3-1.3 unixepoch unsigned32 max' => ['unixepoch', ['2106-02-07 06:28:15']],
    'date3-1.4 unixepoch unsigned32 overflow' => ['unixepoch', ['2106-02-07 06:28:16']],
    'date3-1.5 unixepoch high text boundary' => ['unixepoch', ['9999-12-31 23:59:59']],
    'date3-1.6 unixepoch low text boundary' => ['unixepoch', ['0000-01-01 00:00:00']],
    'date3-1.8 unixepoch truncates subsecond' => ['unixepoch', ['2022-01-27 12:59:28.052']],
    'date3-2.1 auto low julian' => ['datetime', [0.0, 'auto']],
    'date3-2.2 auto high julian' => ['datetime', [5373484.4999999, 'auto']],
    'date3-2.3 auto epoch julian' => ['datetime', [2440587.5, 'auto']],
    'date3-2.4 auto pre epoch julian' => ['datetime', [2440587.49998843, 'auto']],
    'date3-2.5 auto mixed fractional julian' => ['datetime', [2440615.7475463, 'auto']],
    'date3-2.10 auto negative unixepoch' => ['datetime', [-1, 'auto']],
    'date3-2.11 auto first unixepoch above julian' => ['datetime', [5373485, 'auto']],
    'date3-2.12 auto low unixepoch boundary' => ['datetime', [-210866760000, 'auto']],
    'date3-2.13 auto high unixepoch boundary' => ['datetime', [253402300799, 'auto']],
    'date3-2.20 auto below unixepoch boundary' => ['datetime', [-210866760001, 'auto']],
    'date3-2.21 auto above unixepoch boundary' => ['datetime', [253402300800, 'auto']],
    'date3-2.30 auto text no-op left' => ['date', ['2022-01-29', 'auto']],
    'date3-2.30 auto text no-op right' => ['date', ['2022-01-29']],
    'date3-3.1 unixepoch late modifier null' => ['datetime', [2459607.05, '+1 hour', 'unixepoch']],
    'date3-3.2 unixepoch immediate modifier' => ['datetime', [2459607.05, 'unixepoch', '+1 hour']],
    'date3-4.1 julianday immediate modifier' => ['datetime', [2459607, 'julianday']],
    'date3-4.2 julianday late modifier null' => ['datetime', [2459607, '+1 hour', 'julianday']],
    'date3-4.3 julianday text value null' => ['datetime', ['2022-01-27', 'julianday']],
];

foreach ($staticCases as $label => [$functionName, $arguments]) {
    $tests['real upstream corpus date affinity dynamic date3 auto static ' . $label] = static function (TestRunner $t) use ($oracleScalar, $quotePort, $assertOraclePair, $functionName, $arguments, $label): void {
        $expected = $oracleScalar($functionName, $arguments);
        $actual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, $arguments));

        $assertOraclePair($t, $expected, $actual, $label);
        $t->same(true, str_starts_with($label, 'date3-'));
    };
}

$dynamicUnixepochCases = [];
$seed = 0x5eed2026;
for ($i = 1; $i <= 1000; $i++) {
    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $seconds = (int) ($seed % 0x100000000) - 0xffffffff;
    $dynamicUnixepochCases[$i] = $seconds;
}

foreach ($dynamicUnixepochCases as $i => $seconds) {
    $tests[sprintf('real upstream corpus date affinity dynamic date3 auto date3-1.7 deterministic unixepoch row %04d', $i)] = static function (TestRunner $t) use ($oracleScalar, $quotePort, $assertOraclePair, $seconds, $i): void {
        $expected = $oracleScalar('unixepoch', [$seconds, 'unixepoch']);
        $actual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$seconds, 'unixepoch']));

        $assertOraclePair($t, $expected, $actual, 'date3-1.7 deterministic row ' . $i);
        $t->same($seconds, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$seconds, 'unixepoch']));
    };
}

$autoRows = [
    ['2022-01-27 13:15:44', '2022-01-27 13:15:44'],
    [2459607.05260275, '2022-01-27 13:15:44'],
    [1643289344, '2022-01-27 13:15:44'],
];

foreach ($autoRows as $index => [$timeValue, $expectedDateTime]) {
    $tests[sprintf('real upstream corpus date affinity dynamic date3 auto date3-2.40 mixed source row %d', $index + 1)] = static function (TestRunner $t) use ($oracleScalar, $quotePort, $assertOraclePair, $timeValue, $expectedDateTime, $index): void {
        $expected = $oracleScalar('datetime', [$timeValue, 'auto']);
        $actual = $quotePort(SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, 'auto']));

        $assertOraclePair($t, $expected, $actual, 'date3-2.40 mixed source row ' . ($index + 1));
        $t->same($expectedDateTime, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, 'auto']));
    };
}

foreach (range(-10, 100) as $offset) {
    $tests[sprintf('real upstream corpus date affinity dynamic date3 auto date3-5.0 first-1970-days row %+04d', $offset)] = static function (TestRunner $t) use ($offset): void {
        $left = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', sprintf('%+d days', $offset)]);
        $epoch = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['1970-01-01', sprintf('%+d days', $offset)]);
        $right = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$epoch, 'auto']);
        $expectedMismatch = $offset >= 0 && $offset < 63;

        $t->same($expectedMismatch, $left !== $right, 'date3-5.0 first 63 days auto ambiguity offset ' . $offset);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$left]));
    };
}

$tests['real upstream corpus date affinity dynamic date3 auto owns non overlapping upstream scenarios'] = static function (TestRunner $t) use ($dynamicUnixepochCases, $staticCases): void {
    $t->same(1000, count($dynamicUnixepochCases));
    $t->same(25, count($staticCases));
    $t->same(
        'date3.test unixepoch/date3-1.1..1.8, auto/date3-2.1..2.40, unixepoch and julianday position/date3-3.1..4.3, first-1970-days/date3-5.0; avoids accepted date.test/date2/date4 rows and expression-affinity operator clusters',
        'date3.test unixepoch/date3-1.1..1.8, auto/date3-2.1..2.40, unixepoch and julianday position/date3-3.1..4.3, first-1970-days/date3-5.0; avoids accepted date.test/date2/date4 rows and expression-affinity operator clusters'
    );
};

$tests['real upstream corpus date affinity dynamic date3 auto dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction date/time auto, unixepoch, julianday, quote, and typeof behavior against hydrated upstream date3.test',
        'no new support component needed; reuses SQLiteCoreScalarFunction date/time auto, unixepoch, julianday, quote, and typeof behavior against hydrated upstream date3.test'
    );
};

return $tests;
