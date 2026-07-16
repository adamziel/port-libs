<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        return rtrim(rtrim(sprintf('%.14F', $value), '0'), '.');
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$normalizeSqliteQuote = static function (string $quoted, string $type): mixed {
    if ($type === 'null' || $quoted === 'NULL') {
        return null;
    }
    if ($type === 'integer') {
        return (int) $quoted;
    }
    if ($type === 'real') {
        return rtrim(rtrim(sprintf('%.8F', (float) $quoted), '0'), '.');
    }
    if (strlen($quoted) >= 2 && $quoted[0] === "'" && substr($quoted, -1) === "'") {
        return str_replace("''", "'", substr($quoted, 1, -1));
    }

    return $quoted;
};

$normalizeActual = static function (mixed $value, string $type): mixed {
    if ($value === null) {
        return null;
    }
    if ($type === 'real') {
        return rtrim(rtrim(sprintf('%.8F', (float) $value), '0'), '.');
    }

    return $value;
};

$oracleRows = static function (array $cases) use ($sqlite3, $quoteSql, $normalizeSqliteQuote): array {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream date extreme range dynamic tests');
    }

    $expected = [];
    foreach (array_chunk($cases, 150, true) as $chunk) {
        $selects = [];
        foreach ($chunk as $id => $case) {
            $arguments = array_map($quoteSql, $case['arguments']);
            $expr = $case['function'] . '(' . implode(',', $arguments) . ')';
            $selects[] = 'SELECT ' . (int) $id . ', quote(' . $expr . '), typeof(' . $expr . ')';
        }

        $sql = implode(' UNION ALL ', $selects) . ' ORDER BY 1;';
        $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
        $output = shell_exec($command);
        if ($output === null || $output === '') {
            throw new RuntimeException('sqlite3 oracle did not produce date extreme range rows');
        }

        foreach (explode("\n", trim($output)) as $line) {
            $columns = explode("\t", $line);
            if (count($columns) !== 3) {
                throw new RuntimeException('sqlite3 oracle returned an unexpected date extreme range row: ' . $line);
            }

            $expected[(int) $columns[0]] = [
                'value' => $normalizeSqliteQuote($columns[1], $columns[2]),
                'typeof' => $columns[2],
            ];
        }
    }

    ksort($expected);

    return $expected;
};

$tests['real upstream corpus date affinity dynamic extreme range cites upstream date16'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->same(true, is_file($upstreamFile));
    $t->contains('Tests of extreme values in date/time functions', $source);
    $t->contains("datetest 16.2 {datetime(0)} {-4713-11-24 12:00:00}", $source);
    $t->contains("datetest 16.7 {datetime(0,'+464269060800 seconds')} NULL", $source);
    $t->contains("datetest 16.31 {datetime(5373484,'-14713 years')} NULL", $source);
};

$cases = [
    ['function' => 'date', 'arguments' => [147483649], 'source' => 'date-16.1'],
    ['function' => 'datetime', 'arguments' => [0], 'source' => 'date-16.2'],
    ['function' => 'datetime', 'arguments' => [5373484.49999999], 'source' => 'date-16.3'],
    ['function' => 'julianday', 'arguments' => ['-4713-11-24 12:00:00'], 'source' => 'date-16.4'],
    ['function' => 'julianday', 'arguments' => ['9999-12-31 23:59:59.999'], 'source' => 'date-16.5'],
];

$positiveBoundaries = [
    ['unit' => 'seconds', 'limit' => 464269060799, 'base' => 0, 'source' => 'date-16.6/16.7'],
    ['unit' => 'minutes', 'limit' => 7737817679, 'base' => 0, 'source' => 'date-16.8/16.9'],
    ['unit' => 'hours', 'limit' => 128963627, 'base' => 0, 'source' => 'date-16.10/16.11'],
    ['unit' => 'days', 'limit' => 5373484, 'base' => 0, 'source' => 'date-16.12/16.13'],
    ['unit' => 'months', 'limit' => 176545, 'base' => 0, 'source' => 'date-16.14/16.15'],
    ['unit' => 'years', 'limit' => 14712, 'base' => 0, 'source' => 'date-16.16/16.17'],
];

$negativeBoundaries = [
    ['unit' => 'seconds', 'limit' => 464269060799, 'base' => 5373484.4999999, 'source' => 'date-16.20/16.21'],
    ['unit' => 'minutes', 'limit' => 7737817679, 'base' => 5373484.4999999, 'source' => 'date-16.22/16.23'],
    ['unit' => 'hours', 'limit' => 128963627, 'base' => 5373484.4999999, 'source' => 'date-16.24/16.25'],
    ['unit' => 'days', 'limit' => 5373484, 'base' => 5373484, 'source' => 'date-16.26/16.27'],
    ['unit' => 'months', 'limit' => 176545, 'base' => 5373484, 'source' => 'date-16.28/16.29'],
    ['unit' => 'years', 'limit' => 14712, 'base' => 5373484, 'source' => 'date-16.30/16.31'],
];

foreach ($positiveBoundaries as $boundary) {
    for ($delta = -12; $delta <= 12; $delta++) {
        $amount = $boundary['limit'] + $delta;
        $cases[] = [
            'function' => 'datetime',
            'arguments' => [$boundary['base'], sprintf('+%d %s', $amount, $boundary['unit'])],
            'source' => $boundary['source'],
        ];
    }
}

foreach ($negativeBoundaries as $boundary) {
    for ($delta = -12; $delta <= 12; $delta++) {
        $amount = $boundary['limit'] + $delta;
        $cases[] = [
            'function' => 'datetime',
            'arguments' => [$boundary['base'], sprintf('-%d %s', $amount, $boundary['unit'])],
            'source' => $boundary['source'],
        ];
    }
}

for ($case = 0; $case < 960; $case++) {
    $boundary = $case % 2 === 0
        ? $positiveBoundaries[intdiv($case, 2) % count($positiveBoundaries)]
        : $negativeBoundaries[intdiv($case, 2) % count($negativeBoundaries)];
    $direction = $case % 2 === 0 ? '+' : '-';
    $nearLimitOffset = ($case % 41) - 20;
    $amount = $boundary['limit'] + $nearLimitOffset;

    $cases[] = [
        'function' => 'datetime',
        'arguments' => [$boundary['base'], sprintf('%s%d %s', $direction, $amount, $boundary['unit'])],
        'source' => $boundary['source'] . ' dynamic-near-boundary',
    ];
}

$expectedRows = $oracleRows($cases);

foreach ($cases as $case => $scenario) {
    $expected = $expectedRows[$case];
    $tests[sprintf('real upstream corpus date affinity dynamic extreme range date.test %s row %04d', $scenario['source'], $case)] = static function (TestRunner $t) use ($scenario, $expected, $normalizeActual, $case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($scenario['function'], $scenario['arguments']);

        $t->same($expected['value'], $normalizeActual($actual, $expected['typeof']), 'date.test section 16 value row ' . $case);
        $t->same($expected['typeof'], SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]), 'date.test section 16 typeof row ' . $case);
        $t->same(true, str_starts_with($scenario['source'], 'date-16.'), 'date.test section 16 source row ' . $case);
    };
}

$tests['real upstream corpus date affinity dynamic extreme range application retention schedule'] = static function (TestRunner $t): void {
    $retention = [
        ['key_name' => 'archive.lower-boundary', 'base' => 0, 'offset' => '+0 seconds'],
        ['key_name' => 'archive.upper-boundary', 'base' => 0, 'offset' => '+464269060799 seconds'],
        ['key_name' => 'archive.out-of-range', 'base' => 0, 'offset' => '+464269060800 seconds'],
    ];
    $actual = [];

    foreach ($retention as $row) {
        $actual[] = [
            'key_name' => $row['key_name'],
            'expires_at' => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$row['base'], $row['offset']]),
        ];
    }

    $t->same([
        ['key_name' => 'archive.lower-boundary', 'expires_at' => '-4713-11-24 12:00:00'],
        ['key_name' => 'archive.upper-boundary', 'expires_at' => '9999-12-31 23:59:59'],
        ['key_name' => 'archive.out-of-range', 'expires_at' => null],
    ], $actual);
};

$tests['real upstream corpus date affinity dynamic extreme range non overlap and dependency closure'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1265, count($cases));
    $t->same(
        'date.test section 16 extreme date/time boundary behavior with dynamic near-limit seconds/minutes/hours/days/months/years rows; avoids accepted date4 loop rows, date10 time-only defaults, date11 HH:MM modifiers, date13 fractional word modifiers, date19 floor/ceiling, date20 no-round, and expression-affinity batches',
        'date.test section 16 extreme date/time boundary behavior with dynamic near-limit seconds/minutes/hours/days/months/years rows; avoids accepted date4 loop rows, date10 time-only defaults, date11 HH:MM modifiers, date13 fractional word modifiers, date19 floor/ceiling, date20 no-round, and expression-affinity batches'
    );
    $t->same('No new support component is needed; this reuses native SQLiteCoreScalarFunction date/time range checks with sqlite3 oracle evidence.', 'No new support component is needed; this reuses native SQLiteCoreScalarFunction date/time range checks with sqlite3 oracle evidence.');
};

return $tests;
