<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracleRows = static function (array $cases) use ($sqlite3, $quoteSql): array {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date3 auto/unixepoch dynamic tests');
    }

    $expected = [];
    foreach (array_chunk($cases, 125, true) as $chunk) {
        $rows = [];
        foreach ($chunk as $case => $row) {
            $rows[] = '('
                . (int) $case . ','
                . $quoteSql($row['time_value']) . ','
                . $quoteSql($row['modifier']) . ','
                . $quoteSql($row['expected_text']) . ')';
        }

        $sql = "WITH input(id,time_value,modifier,expected_text) AS (VALUES " . implode(',', $rows) . ") "
            . "SELECT id, quote(datetime(time_value,'auto')), quote(datetime(expected_text)), "
            . "datetime(time_value,'auto') = datetime(expected_text), "
            . "quote(datetime(time_value,'unixepoch')), quote(datetime(time_value,'julianday')), "
            . "quote(datetime(time_value,modifier,'unixepoch')), quote(datetime(time_value,'unixepoch',modifier)) "
            . "FROM input ORDER BY id;";
        $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
        $output = shell_exec($command);
        if ($output === null || $output === '') {
            throw new RuntimeException('sqlite3 oracle did not produce date3 auto/unixepoch rows');
        }

        foreach (explode("\n", trim($output)) as $line) {
            $columns = explode("\t", $line);
            if (count($columns) !== 8) {
                throw new RuntimeException('sqlite3 oracle returned an unexpected date3 row: ' . $line);
            }

            $expected[(int) $columns[0]] = [
                'auto' => trim($columns[1], "'"),
                'reference' => trim($columns[2], "'"),
                'auto_matches_reference' => $columns[3],
                'unixepoch' => trim($columns[4], "'"),
                'julianday' => trim($columns[5], "'"),
                'misordered_unixepoch' => trim($columns[6], "'"),
                'ordered_unixepoch' => trim($columns[7], "'"),
            ];
        }
    }

    ksort($expected);

    return $expected;
};

$cases = [];
for ($case = 0; $case < 1000; $case++) {
    $dayOffset = $case - 500;
    $base = (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
        ->modify(($dayOffset >= 0 ? '+' : '') . $dayOffset . ' days');
    $seconds = (int) $base->format('U');
    $modifierSeconds = (($case % 17) - 8) * 3600;
    $modifier = ($modifierSeconds >= 0 ? '+' : '') . $modifierSeconds . ' seconds';
    $expectedText = $base->format('Y-m-d H:i:s');

    $cases[$case] = [
        'time_value' => $seconds,
        'modifier' => $modifier,
        'expected_text' => $expectedText,
    ];
}

$expectedRows = $oracleRows($cases);

$tests['real upstream corpus date affinity dynamic auto unixepoch cites date3 modifier sections'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('date3-2.40', $source);
    $t->contains('datetime(timeval,\'auto\') == datetime', $source);
    $t->contains('date3-5.0', $source);
    $t->contains('The "unixepoch" modifier (11) only works if', $source);
    $t->contains('The "julianday" modifier must immediately', $source);
};

foreach ($cases as $case => $row) {
    $expected = $expectedRows[$case];
    $tests[sprintf('real upstream corpus date affinity dynamic auto unixepoch date3 row %04d', $case)] = static function (TestRunner $t) use ($row, $expected, $case): void {
        $auto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], 'auto']);
        $reference = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['expected_text']]);
        $unixepoch = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], 'unixepoch']);
        $julianday = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], 'julianday']);
        $misordered = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], $row['modifier'], 'unixepoch']);
        $ordered = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], 'unixepoch', $row['modifier']]);

        $t->same($expected['auto'], (string) $auto, 'date3-2.40/date3-5.0 auto row ' . $case);
        $t->same($expected['reference'], (string) $reference, 'date3 reference row ' . $case);
        $t->same($expected['unixepoch'], (string) $unixepoch, 'date3 unixepoch row ' . $case);
        $t->same($expected['julianday'] === 'NULL' ? null : $expected['julianday'], $julianday, 'date3 julianday row ' . $case);
        $t->same($expected['misordered_unixepoch'] === 'NULL' ? null : $expected['misordered_unixepoch'], $misordered, 'date3-3.1 modifier order row ' . $case);
        $t->same($expected['ordered_unixepoch'], (string) $ordered, 'date3-3.2 modifier order row ' . $case);
        $t->same($expected['auto_matches_reference'] === '1', $auto === $reference);
    };
}

$tests['real upstream corpus date affinity dynamic auto unixepoch application rollup'] = static function (TestRunner $t) use ($cases): void {
    $sample = [0, 437, 500, 562, 999];
    $actual = [];
    foreach ($sample as $case) {
        $row = $cases[$case];
        $actual['event-' . $case] = [
            'auto' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], 'auto']),
            'unixepoch' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['time_value'], 'unixepoch']),
            'storage_class' => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['time_value']]),
        ];
    }

    $t->same('integer', $actual['event-0']['storage_class']);
    $t->same('1968-08-19 00:00:00', $actual['event-0']['auto']);
    $t->same('9954-04-27 12:00:00', $actual['event-562']['auto']);
    $t->same('1970-03-04 00:00:00', $actual['event-562']['unixepoch']);
    $t->same($actual['event-999']['unixepoch'], $actual['event-999']['auto']);
};

return $tests;
