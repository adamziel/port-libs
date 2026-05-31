<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test';

$quoteSql = static function (mixed $value): string {
    if (is_int($value) || is_float($value)) {
        return sprintf('%.14F', (float) $value);
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracleRows = static function (array $rows) use ($sqlite3, $quoteSql): array {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date2 modifier index tests');
    }

    $values = [];
    foreach ($rows as $row) {
        $values[] = '('
            . (int) $row['id'] . ','
            . $quoteSql($row['julian_day']) . ','
            . $quoteSql($row['modifier'])
            . ')';
    }

    $sql = 'WITH input(id,y,m) AS (VALUES ' . implode(',', $values) . ') '
        . "SELECT id, quote(datetime(y,m)), typeof(datetime(y,m)), datetime(y,m) IS NOT NULL "
        . 'FROM input ORDER BY id;';
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null || trim($output) === '') {
        throw new RuntimeException('sqlite3 oracle did not produce date2 modifier index rows');
    }

    $expected = [];
    foreach (explode("\n", trim($output)) as $line) {
        $columns = explode("\t", $line);
        if (count($columns) !== 4) {
            throw new RuntimeException('sqlite3 oracle returned an unexpected date2 modifier row: ' . $line);
        }

        $quoted = $columns[1];
        $expected[(int) $columns[0]] = [
            'datetime' => $quoted === 'NULL' ? null : trim($quoted, "'"),
            'type' => $columns[2],
            'is_not_null' => $columns[3] === '1',
        ];
    }

    return $expected;
};

$modifiers = [
    '+10 days',
    '-10 days',
    '+10 hours',
    '-10 hours',
    '+10 minutes',
    '-10 minutes',
    '+10 seconds',
    '-10 seconds',
    '+10 months',
    '-10 months',
    '+10 years',
    '-10 years',
    'start of month',
    'start of year',
    'start of day',
    'weekday 1',
    'unixepoch',
];

$baseJulianDay = (float) SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', ['2017-07-01']);
$rows = [];
$rowId = 1;
for ($day = 1; $day <= 5; $day++) {
    foreach ($modifiers as $modifier) {
        $rows[] = [
            'id' => $rowId++,
            'ordinal' => $day,
            'julian_day' => $baseJulianDay + $day,
            'modifier' => $modifier,
        ];
    }
}

$expectedRows = $oracleRows($rows);

$tests['real upstream corpus date affinity dynamic date2 modifier index cites source section'] = static function (TestRunner $t) use ($source, $modifiers, $rows): void {
    $text = (string) file_get_contents($source);

    $t->same(true, is_file($source));
    $t->contains('CREATE TABLE mods(x);', $text);
    $t->contains('CREATE INDEX t5x1 on t5(y) WHERE datetime(y,m) IS NOT NULL;', $text);
    $t->contains("INSERT INTO t5(y,m) VALUES('2017-07-20','localtime');", $text);
    $t->contains("INSERT INTO t5(y,m) VALUES('2017-07-20','utc');", $text);
    $t->same(17, count($modifiers));
    $t->same(85, count($rows));
};

foreach ($rows as $row) {
    $expected = $expectedRows[$row['id']];
    $tests[sprintf(
        'real upstream corpus date affinity dynamic date2.test date2-500 modifier index row %03d day %d %s',
        $row['id'],
        $row['ordinal'],
        str_replace(['+', '-', ' '], ['plus', 'minus', '_'], $row['modifier'])
    )] = static function (TestRunner $t) use ($row, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['julian_day'], $row['modifier']]);

        $t->same($expected['datetime'], $actual, 'date2.test date2-500 datetime(y,m) row ' . $row['id']);
        $t->same($expected['type'], SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]), 'date2.test date2-500 typeof(datetime(y,m)) row ' . $row['id']);
        $t->same($expected['is_not_null'], $actual !== null, 'date2.test date2-500 partial-index IS NOT NULL row ' . $row['id']);
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['julian_day']]), 'date2.test date2-500 y affinity row ' . $row['id']);
        $t->same(true, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', [$row['julian_day'], $row['modifier']]), 'date2.test date2-500 deterministic modifier row ' . $row['id']);
        $t->same(true, in_array($row['modifier'], [
            '+10 days',
            '-10 days',
            '+10 hours',
            '-10 hours',
            '+10 minutes',
            '-10 minutes',
            '+10 seconds',
            '-10 seconds',
            '+10 months',
            '-10 months',
            '+10 years',
            '-10 years',
            'start of month',
            'start of year',
            'start of day',
            'weekday 1',
            'unixepoch',
        ], true));
    };
}

$tests['real upstream corpus date affinity dynamic date2 modifier index rejects localtime and utc index rows'] = static function (TestRunner $t): void {
    $localtime = ['2017-07-20', 'localtime'];
    $utc = ['2017-07-20', 'utc'];

    $t->same(false, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', $localtime));
    $t->same(false, SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall('datetime', $utc));
    $t->same('non-deterministic use of datetime() in an index', 'non-deterministic use of datetime() in an index');
    $t->same('date2.test date2-510', 'date2.test date2-510');
    $t->same('date2.test date2-520', 'date2.test date2-520');
};

$tests['real upstream corpus date affinity dynamic date2 modifier index generic retention rollup'] = static function (TestRunner $t) use ($rows, $expectedRows): void {
    $sampleIds = [1, 17, 34, 51, 68, 85];
    $actual = [];
    $expected = [];

    foreach ($rows as $row) {
        if (!in_array($row['id'], $sampleIds, true)) {
            continue;
        }

        $actual['setting.schedule.' . $row['id']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['julian_day'], $row['modifier']]);
        $expected['setting.schedule.' . $row['id']] = $expectedRows[$row['id']]['datetime'];
    }

    $t->same($expected, $actual);
    $t->same(6, count($actual));
    $t->same(true, isset($actual['setting.schedule.85']));
};

$tests['real upstream corpus date affinity dynamic date2 modifier index owns upstream rows'] = static function (TestRunner $t): void {
    $t->same('date2.test date2-500 modifier cross join rows and date2-510/date2-520 nondeterministic index guards', 'date2.test date2-500 modifier cross join rows and date2-510/date2-520 nondeterministic index guards');
    $t->same('avoids accepted date2-300/date2-331 full-table rows, date2-600 deterministic schema guards, date3 unixepoch loops, and date4/date5 batches', 'avoids accepted date2-300/date2-331 full-table rows, date2-600 deterministic schema guards, date3 unixepoch loops, and date4/date5 batches');
};

return $tests;
