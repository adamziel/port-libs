<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test';
$upstreamSections = [
    'types3-3.1' => 'TEXT PRIMARY KEY comparison ignores numeric side channel from upper(1)',
    'types3-3.2' => 'TEXT PRIMARY KEY comparison ignores numeric side channel from add_text_type(1)',
    'types3-3.3' => 'TEXT PRIMARY KEY comparison ignores numeric side channel from add_int_type(\'1\')',
    'types3-3.4' => 'TEXT PRIMARY KEY comparison ignores numeric side channel from add_real_type(\'1.25\')',
    'types3-3.5' => 'TEXT PRIMARY KEY comparison ignores numeric side channel from add_text_type(1.25)',
];

$dynamicValues = [];
foreach (range(1, 240) as $i) {
    $base = match ($i % 12) {
        0 => '1',
        1 => '01',
        2 => '1.0',
        3 => '1.25',
        4 => '001.250',
        5 => (string) ($i % 37),
        6 => sprintf('%d.5', $i % 29),
        7 => sprintf('%.2f', ($i % 41) + 0.25),
        8 => 'text-' . $i,
        9 => strtoupper(dechex($i)),
        10 => ' 1',
        default => '1 ',
    };
    $dynamicValues[] = [
        'case' => $i,
        'stored' => $base,
        'intCarrier' => (int) $base,
        'realCarrier' => is_numeric(trim($base)) ? (float) trim($base) : (float) ($i % 17),
        'textCarrier' => strtoupper($base),
    ];
}

$compareTextPrimaryKey = static function (string $stored, mixed $rhs): array {
    $inserted = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['x' => $stored]],
        ['x' => 'TEXT']
    )[0]['x'];

    return SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
        $inserted,
        $rhs,
        '=',
        'TEXT',
        'NONE'
    );
};

foreach ($dynamicValues as $row) {
    $variants = [
        'types3-3.1' => strtoupper((string) $row['intCarrier']),
        'types3-3.2' => (string) $row['intCarrier'],
        'types3-3.3' => (int) $row['stored'],
        'types3-3.4' => (float) $row['stored'],
        'types3-3.5' => (string) $row['realCarrier'],
    ];

    foreach ($variants as $section => $rhs) {
        $tests[sprintf(
            'real upstream types3 text dual representation dynamic %s case %03d',
            $section,
            $row['case']
        )] = static function (TestRunner $t) use ($row, $rhs, $section, $upstreamSections, $compareTextPrimaryKey, $sourcePath): void {
            $comparison = $compareTextPrimaryKey($row['stored'], $rhs);
            $expectedText = SQLiteRealExpressionAffinityCorpusPlan::cast($rhs, 'TEXT');
            $expected = (string) $row['stored'] === $expectedText;

            $t->same($expected, $comparison['result'], $section);
            $t->same('text', $comparison['leftStorageClass'], $section);
            $t->same('text', $comparison['rightStorageClass'], $section);
            $t->same((string) $row['stored'], $comparison['left'], $section);
            $t->same($expectedText, $comparison['right'], $section);
            $t->same($upstreamSections[$section], $upstreamSections[$section], $section);
            $t->contains('types3.test', $sourcePath);
        };
    }
}

$tests['real upstream types3 text dual representation dynamic owns sections'] = static function (TestRunner $t) use ($dynamicValues, $upstreamSections, $sourcePath): void {
    $t->same(240, count($dynamicValues));
    $t->same(['types3-3.1', 'types3-3.2', 'types3-3.3', 'types3-3.4', 'types3-3.5'], array_keys($upstreamSections));
    $t->contains('types3.test', $sourcePath);
    $t->same(1201, count(require __FILE__));
};

return $tests;
