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
        throw new RuntimeException('sqlite3 oracle is required for real upstream UTC suffix dynamic date tests');
    }

    $expected = [];
    foreach (array_chunk($cases, 125, true) as $chunk) {
        $rows = [];
        foreach ($chunk as $case => $definition) {
            $args = array_map($quoteSql, array_merge([$definition['time_value']], $definition['modifiers']));
            $expression = 'datetime(' . implode(',', $args) . ')';
            $rows[] = 'SELECT ' . (int) $case . ' AS id, quote(' . $expression . ') AS value, typeof(' . $expression . ') AS storage_class';
        }

        $sql = implode(' UNION ALL ', $rows) . ' ORDER BY id;';
        $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
        $output = shell_exec($command);
        if ($output === null || $output === '') {
            throw new RuntimeException('sqlite3 oracle did not produce UTC suffix rows');
        }

        foreach (explode("\n", trim($output)) as $line) {
            $columns = explode("\t", $line);
            if (count($columns) !== 3) {
                throw new RuntimeException('sqlite3 oracle returned an unexpected UTC suffix row: ' . $line);
            }

            $expected[(int) $columns[0]] = [
                'value' => $columns[1],
                'storage_class' => $columns[2],
            ];
        }
    }

    ksort($expected);

    return $expected;
};

$suffixes = [
    'Z',
    'z',
    ' Z',
    ' z',
    '+00:00',
    ' +00:00',
    '-00:00',
    ' -00:00',
];
$modifierSets = [
    ['utc'],
    ['utc', 'utc'],
    ['utc', 'utc', 'utc'],
    [],
];

$cases = [];
for ($case = 0; $case < 1000; $case++) {
    $year = 1996 + ($case % 48);
    $month = 1 + (($case * 7) % 12);
    $day = 1 + (($case * 11) % 28);
    $hour = ($case * 5) % 24;
    $minute = ($case * 13) % 60;
    $second = ($case * 17) % 60;
    $suffix = $suffixes[$case % count($suffixes)];
    $modifiers = $modifierSets[intdiv($case, count($suffixes)) % count($modifierSets)];
    $separator = ($case % 3) === 0 ? 'T' : ' ';

    $cases[$case] = [
        'time_value' => sprintf('%04d-%02d-%02d%s%02d:%02d:%02d%s', $year, $month, $day, $separator, $hour, $minute, $second, $suffix),
        'modifiers' => $modifiers,
        'suffix' => $suffix,
    ];
}

$expectedRows = $oracleRows($cases);

$tests['real upstream corpus date affinity dynamic UTC suffix cites upstream date section 6'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

    $t->same(true, is_file($source));
    $contents = file_get_contents($source);
    $t->contains('date-6.25.1', $contents);
    $t->contains('date-6.27', $contents);
    $t->contains('The "+00:00" suffix should work like "Z"', $contents);
};

foreach ($cases as $case => $definition) {
    $expected = $expectedRows[$case];
    $tests[sprintf('real upstream corpus date affinity dynamic UTC suffix date.test date-6.25 row %04d', $case)] = static function (TestRunner $t) use ($definition, $expected, $case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', array_merge([$definition['time_value']], $definition['modifiers']));

        $t->same($expected['value'], SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$actual]), 'date.test date-6.25 UTC suffix row ' . $case);
        $t->same($expected['storage_class'], SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(true, str_contains($definition['suffix'], '0') || str_contains(strtolower($definition['suffix']), 'z'));
    };
}

return $tests;
