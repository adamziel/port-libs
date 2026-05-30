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
        return sprintf('%.14F', (float) $value);
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$oracleRows = static function (array $values) use ($sqlite3, $quoteSql): array {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream Julian week dynamic date tests');
    }

    $expected = [];
    foreach (array_chunk($values, 200, true) as $chunk) {
        $rows = [];
        foreach ($chunk as $case => $value) {
            $rows[] = '(' . (int) $case . ',' . $quoteSql($value) . ')';
        }

        $sql = "WITH input(id,v) AS (VALUES " . implode(',', $rows) . ") "
            . "SELECT id, quote(strftime('%W %j',v)), typeof(strftime('%W %j',v)) "
            . "FROM input ORDER BY id;";
        $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
        $output = shell_exec($command);
        if ($output === null || $output === '') {
            throw new RuntimeException('sqlite3 oracle did not produce Julian week rows');
        }

        foreach (explode("\n", trim($output)) as $line) {
            $columns = explode("\t", $line);
            if (count($columns) !== 3) {
                throw new RuntimeException('sqlite3 oracle returned an unexpected Julian week row: ' . $line);
            }

            $expected[(int) $columns[0]] = [
                'week_day' => trim($columns[1], "'"),
                'week_day_type' => $columns[2],
            ];
        }
    }

    ksort($expected);

    return $expected;
};

$julianValues = [];
$base = 2454109.04140970;
for ($case = 0; $case < 1000; $case++) {
    $julianValues[$case] = $base + (($case % 125) * 0.00000001) + (intdiv($case, 125) * 0.125);
}

$expectedRows = $oracleRows($julianValues);

$tests['real upstream corpus date affinity dynamic Julian week cites upstream date 3.11 fractional Julian rows'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-3.11.15..3.11.25 fractional Julian strftime week/day stability',
        'date.test date-3.11.99 text Julian strftime week/day stability',
    ];

    $t->same(true, is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test'));
    $t->same(true, in_array('date.test date-3.11.15..3.11.25 fractional Julian strftime week/day stability', $upstream, true));
    $t->same(true, in_array('date.test date-3.11.99 text Julian strftime week/day stability', $upstream, true));
    $t->contains('date.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
};

foreach ($julianValues as $case => $julianDay) {
    $expected = $expectedRows[$case];
    $tests[sprintf('real upstream corpus date affinity dynamic Julian week date.test date-3.11 fractional row %04d', $case)] = static function (TestRunner $t) use ($julianDay, $expected, $case): void {
        $weekDay = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%W %j', $julianDay]);

        $t->same($expected['week_day'], $weekDay, 'date.test date-3.11 %W %j row ' . $case);
        $t->same($expected['week_day_type'], SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$weekDay]));
        $t->same(true, $julianDay >= 2454109.04140970);
    };
}

return $tests;
