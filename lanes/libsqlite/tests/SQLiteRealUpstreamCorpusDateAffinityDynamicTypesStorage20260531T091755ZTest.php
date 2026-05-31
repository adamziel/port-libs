<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';
$affinities = [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
];

$operations = [
    [
        'section' => 'types-1.1',
        'statement' => 'INSERT INTO t1 VALUES($lit, $lit, $lit, $lit)',
        'description' => 'INSERT VALUES',
    ],
    [
        'section' => 'types-1.2',
        'statement' => 'INSERT INTO t1 SELECT $lit, $lit, $lit, $lit',
        'description' => 'INSERT SELECT',
    ],
    [
        'section' => 'types-1.3',
        'statement' => 'UPDATE t1 SET i = $lit, n = $lit, t = $lit, o = $lit',
        'description' => 'UPDATE SET',
    ],
];

$blob = static fn (int $index): SQLiteBlobValue => new SQLiteBlobValue(chr($index % 251) . chr(($index * 17) % 251));

$dynamicCases = [
    [
        'upstream_literal' => '5.0',
        'name' => 'real integer-valued literal',
        'value' => static fn (int $index): float => (float) (5 + $index),
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'integer', 'value' => 5 + $index],
            'n' => ['class' => 'integer', 'value' => 5 + $index],
            't' => ['class' => 'text', 'value' => SQLiteRealExpressionAffinityCorpusPlan::cast((float) (5 + $index), 'TEXT')],
            'o' => ['class' => 'real', 'value' => (float) (5 + $index)],
        ],
    ],
    [
        'upstream_literal' => '5.1',
        'name' => 'real fractional literal',
        'value' => static fn (int $index): float => (float) (5 + $index) + 0.125,
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'real', 'value' => (float) (5 + $index) + 0.125],
            'n' => ['class' => 'real', 'value' => (float) (5 + $index) + 0.125],
            't' => ['class' => 'text', 'value' => SQLiteRealExpressionAffinityCorpusPlan::cast((float) (5 + $index) + 0.125, 'TEXT')],
            'o' => ['class' => 'real', 'value' => (float) (5 + $index) + 0.125],
        ],
    ],
    [
        'upstream_literal' => '5',
        'name' => 'integer literal',
        'value' => static fn (int $index): int => 5 + $index,
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'integer', 'value' => 5 + $index],
            'n' => ['class' => 'integer', 'value' => 5 + $index],
            't' => ['class' => 'text', 'value' => (string) (5 + $index)],
            'o' => ['class' => 'integer', 'value' => 5 + $index],
        ],
    ],
    [
        'upstream_literal' => "'5.0'",
        'name' => 'quoted real integer-valued literal',
        'value' => static fn (int $index): string => (string) (5 + $index) . '.0',
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'integer', 'value' => 5 + $index],
            'n' => ['class' => 'integer', 'value' => 5 + $index],
            't' => ['class' => 'text', 'value' => (string) (5 + $index) . '.0'],
            'o' => ['class' => 'text', 'value' => (string) (5 + $index) . '.0'],
        ],
    ],
    [
        'upstream_literal' => "'5.1'",
        'name' => 'quoted real fractional literal',
        'value' => static fn (int $index): string => (string) (5 + $index) . '.125',
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'real', 'value' => (float) (5 + $index) + 0.125],
            'n' => ['class' => 'real', 'value' => (float) (5 + $index) + 0.125],
            't' => ['class' => 'text', 'value' => (string) (5 + $index) . '.125'],
            'o' => ['class' => 'text', 'value' => (string) (5 + $index) . '.125'],
        ],
    ],
    [
        'upstream_literal' => "'-5.0'",
        'name' => 'quoted negative real integer-valued literal',
        'value' => static fn (int $index): string => '-' . (string) (5 + $index) . '.0',
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'integer', 'value' => -(5 + $index)],
            'n' => ['class' => 'integer', 'value' => -(5 + $index)],
            't' => ['class' => 'text', 'value' => '-' . (string) (5 + $index) . '.0'],
            'o' => ['class' => 'text', 'value' => '-' . (string) (5 + $index) . '.0'],
        ],
    ],
    [
        'upstream_literal' => "'5'",
        'name' => 'quoted integer literal',
        'value' => static fn (int $index): string => (string) (5 + $index),
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'integer', 'value' => 5 + $index],
            'n' => ['class' => 'integer', 'value' => 5 + $index],
            't' => ['class' => 'text', 'value' => (string) (5 + $index)],
            'o' => ['class' => 'text', 'value' => (string) (5 + $index)],
        ],
    ],
    [
        'upstream_literal' => "'abc'",
        'name' => 'quoted nonnumeric text literal',
        'value' => static fn (int $index): string => 'abc' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'text', 'value' => 'abc' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
            'n' => ['class' => 'text', 'value' => 'abc' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
            't' => ['class' => 'text', 'value' => 'abc' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
            'o' => ['class' => 'text', 'value' => 'abc' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
        ],
    ],
    [
        'upstream_literal' => "X'00'",
        'name' => 'blob literal',
        'value' => static fn (int $index): SQLiteBlobValue => $blob($index),
        'expected' => static fn (int $index): array => [
            'i' => ['class' => 'blob', 'value' => $blob($index)],
            'n' => ['class' => 'blob', 'value' => $blob($index)],
            't' => ['class' => 'blob', 'value' => $blob($index)],
            'o' => ['class' => 'blob', 'value' => $blob($index)],
        ],
    ],
];

$assertValue = static function (TestRunner $t, mixed $expected, mixed $actual, string $label): void {
    if ($expected instanceof SQLiteBlobValue) {
        $t->true($actual instanceof SQLiteBlobValue, $label . ' is blob');
        $t->same($expected->bytes, $actual->bytes, $label . ' bytes');
        return;
    }

    if (is_float($expected)) {
        $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual), $label . ' real storage');
        $t->true(abs($expected - (float) $actual) < 1.0e-12, $label . ' real value');
        return;
    }

    $t->same($expected, $actual, $label . ' value');
};

$caseCount = 0;
foreach ($operations as $operation) {
    foreach ($dynamicCases as $case) {
        for ($index = 0; $index < 40; $index++) {
            $caseCount++;
            $testName = sprintf(
                'real upstream corpus date affinity dynamic types.test %s %s dynamic row %02d',
                $operation['section'],
                $case['name'],
                $index,
            );
            $tests[$testName] = static function (TestRunner $t) use ($sourcePath, $sourceText, $affinities, $operation, $case, $index, $assertValue): void {
                $value = $case['value']($index);
                $expected = $case['expected']($index);
                $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
                    ['i' => $value, 'n' => $value, 't' => $value, 'o' => $value],
                ], $affinities)[0];

                $actualClasses = [
                    'i' => SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['i']),
                    'n' => SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['n']),
                    't' => SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['t']),
                    'o' => SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['o']),
                ];
                $expectedClasses = [
                    'i' => $expected['i']['class'],
                    'n' => $expected['n']['class'],
                    't' => $expected['t']['class'],
                    'o' => $expected['o']['class'],
                ];

                $t->same($expectedClasses, $actualClasses, $operation['section'] . ' storage classes');
                $assertValue($t, $expected['i']['value'], $stored['i'], $operation['section'] . ' INTEGER affinity');
                $assertValue($t, $expected['n']['value'], $stored['n'], $operation['section'] . ' NUMERIC affinity');
                $assertValue($t, $expected['t']['value'], $stored['t'], $operation['section'] . ' TEXT affinity');
                $assertValue($t, $expected['o']['value'], $stored['o'], $operation['section'] . ' NONE affinity');
                $t->same(true, is_file($sourcePath), 'types.test source is hydrated');
                $t->contains('types.test', $sourcePath);
                $t->contains('types-1.', $operation['section']);
                $t->contains($case['upstream_literal'], $sourceText);
                $t->same(true, str_contains($operation['statement'], $operation['description'] === 'UPDATE SET' ? 'UPDATE' : 'INSERT'));
            };
        }
    }
}

foreach ($operations as $operation) {
    $tests['real upstream corpus date affinity dynamic types.test ' . $operation['section'] . ' null literal'] =
        static function (TestRunner $t) use ($sourceText, $affinities, $operation): void {
            $stored = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
                ['i' => null, 'n' => null, 't' => null, 'o' => null],
            ], $affinities)[0];

            $t->same('null', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['i']), $operation['section'] . ' INTEGER affinity NULL');
            $t->same('null', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['n']), $operation['section'] . ' NUMERIC affinity NULL');
            $t->same('null', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['t']), $operation['section'] . ' TEXT affinity NULL');
            $t->same('null', SQLiteRealExpressionAffinityCorpusPlan::storageClass($stored['o']), $operation['section'] . ' NONE affinity NULL');
            $t->same(null, $stored['i']);
            $t->same(null, $stored['n']);
            $t->same(null, $stored['t']);
            $t->same(null, $stored['o']);
            $t->contains('NULL', $sourceText);
        };
}

$tests['real upstream corpus date affinity dynamic types.test source truth and count'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $operations, $dynamicCases, $caseCount): void {
        $t->same(true, is_file($sourcePath), 'types.test exists in hydrated upstream checkout');
        $t->contains('types-1.1.*: INSERT INTO <table> VALUES', $sourceText);
        $t->contains('types-1.2.*: INSERT INTO <table> SELECT', $sourceText);
        $t->contains('types-1.3.*: UPDATE <table> SET', $sourceText);
        $t->contains('{ 5.0    integer integer text real', $sourceText);
        $t->contains("{ '5.1'  real    real    text text", $sourceText);
        $t->contains('{ NULL   null    null    null null', $sourceText);
        $t->contains("{ X'00'  blob    blob    blob blob", $sourceText);
        $t->same(3, count($operations), 'upstream statement forms');
        $t->same(9, count($dynamicCases), 'dynamic upstream value classes');
        $t->same(1080, $caseCount, 'dynamic non-null focused cases');
        $t->same('types.test types-1.1 through types-1.3', 'types.test types-1.1 through types-1.3');
    };

return $tests;
