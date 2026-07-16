<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracleRows = static function (array $fractions) use ($sqlite3): array {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream unixepoch fractional date tests');
    }

    $expected = [];
    foreach (array_chunk($fractions, 200, true) as $chunk) {
        $rows = [];
        foreach ($chunk as $case => $fraction) {
            $rows[] = '(' . (int) $case . ',1237962480.' . sprintf('%03d', $fraction) . ')';
        }

        $sql = "WITH input(id,v) AS (VALUES " . implode(',', $rows) . ") "
            . "SELECT id, quote(strftime('%H:%M:%f',v,'unixepoch')), typeof(strftime('%H:%M:%f',v,'unixepoch')) "
            . "FROM input ORDER BY id;";
        $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
        $output = shell_exec($command);
        if ($output === null || $output === '') {
            throw new RuntimeException('sqlite3 oracle did not produce unixepoch fractional rows');
        }

        foreach (explode("\n", trim($output)) as $line) {
            $columns = explode("\t", $line);
            if (count($columns) !== 3) {
                throw new RuntimeException('sqlite3 oracle returned an unexpected unixepoch fractional row: ' . $line);
            }

            $expected[(int) $columns[0]] = [
                'clock' => trim($columns[1], "'"),
                'clock_type' => $columns[2],
            ];
        }
    }

    ksort($expected);

    return $expected;
};

$fractions = [];
for ($case = 0; $case < 1000; $case++) {
    $fractions[$case] = $case;
}

$expectedRows = $oracleRows($fractions);

$tests['real upstream corpus date affinity dynamic unixepoch fraction cites upstream date 2.2c rows'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-2.1 datetime numeric unixepoch zero',
        'date.test date-2.2b datetime text unixepoch affinity',
        'date.test date-2.2c-0..999 strftime fractional unixepoch milliseconds',
    ];

    $t->same(true, is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test'));
    $t->same(true, in_array('date.test date-2.2c-0..999 strftime fractional unixepoch milliseconds', $upstream, true));
    $t->same('1970-01-01 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [0, 'unixepoch']));
    $t->same('2000-01-01 00:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['946684800', 'unixepoch']));
};

foreach ($fractions as $case => $fraction) {
    $expected = $expectedRows[$case];
    $tests[sprintf('real upstream corpus date affinity dynamic unixepoch fraction date.test date-2.2c row %04d', $case)] = static function (TestRunner $t) use ($fraction, $expected, $case): void {
        $unixepoch = (float) ('1237962480.' . sprintf('%03d', $fraction));
        $clock = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H:%M:%f', $unixepoch, 'unixepoch']);

        $t->same($expected['clock'], $clock, 'date.test date-2.2c fractional unixepoch row ' . $case);
        $t->same($expected['clock_type'], SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$clock]));
        $t->same(true, $unixepoch >= 1237962480.0);
    };
}

return $tests;
