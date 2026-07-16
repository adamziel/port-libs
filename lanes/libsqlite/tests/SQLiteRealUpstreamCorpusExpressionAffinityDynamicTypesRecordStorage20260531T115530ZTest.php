<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteVarint;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';
$string10 = 'abcdefghij';

$affinityClass = static function (string $affinity): string {
    $normalized = strtoupper($affinity);
    if ($normalized === '' || $normalized === 'NONE' || str_contains($normalized, 'BLOB')) {
        return 'NONE';
    }
    if (str_contains($normalized, 'INT')) {
        return 'INTEGER';
    }
    if (str_contains($normalized, 'CHAR') || str_contains($normalized, 'CLOB') || str_contains($normalized, 'TEXT')) {
        return 'TEXT';
    }
    if (str_contains($normalized, 'REAL') || str_contains($normalized, 'FLOA') || str_contains($normalized, 'DOUB')) {
        return 'REAL';
    }

    return 'NUMERIC';
};

$integerSerialType = static function (int $value): int {
    if ($value === 0) {
        return 8;
    }
    if ($value === 1) {
        return 9;
    }

    $magnitude = $value < 0 ? ~$value : $value;

    return match (true) {
        $magnitude <= 127 => 1,
        $magnitude <= 32767 => 2,
        $magnitude <= 8388607 => 3,
        $magnitude <= 2147483647 => 4,
        $magnitude <= 140737488355327 => 5,
        default => 6,
    };
};

$serialBodyBytes = static function (int $serialType): int {
    return match (true) {
        $serialType === 0 || $serialType === 8 || $serialType === 9 => 0,
        $serialType === 1 => 1,
        $serialType === 2 => 2,
        $serialType === 3 => 3,
        $serialType === 4 => 4,
        $serialType === 5 => 6,
        $serialType === 6 || $serialType === 7 => 8,
        $serialType >= 12 => intdiv($serialType - ($serialType % 2 === 0 ? 12 : 13), 2),
        default => throw new InvalidArgumentException('Unsupported serial type in expectation'),
    };
};

$payloadLength = static function (array $serialTypes) use ($serialBodyBytes): int {
    $serialTypeBytes = '';
    $bodyBytes = 0;
    foreach ($serialTypes as $serialType) {
        $serialTypeBytes .= SQLiteVarint::encode($serialType);
        $bodyBytes += $serialBodyBytes($serialType);
    }

    $headerSize = strlen($serialTypeBytes) + 1;
    while (true) {
        $actualHeaderSize = strlen(SQLiteVarint::encode($headerSize)) + strlen($serialTypeBytes);
        if ($actualHeaderSize === $headerSize) {
            return $actualHeaderSize + $bodyBytes;
        }
        $headerSize = $actualHeaderSize;
    }
};

$realCanUseIntegerSerialType = static function (float $value): bool {
    if (!is_finite($value) || $value < (float) PHP_INT_MIN || $value > (float) PHP_INT_MAX) {
        return false;
    }

    return (float) (int) $value === $value;
};

$storedValueForAffinity = static function (mixed $value, string $affinity) use ($affinityClass, $realCanUseIntegerSerialType): mixed {
    $class = $affinityClass($affinity);
    $stored = SQLiteAffinityComparison::applyAffinity($value, $class);

    return $class === 'REAL' && is_float($stored) && $realCanUseIntegerSerialType($stored) ? (int) $stored : $stored;
};

$serialTypeForStoredValue = static function (mixed $value) use ($integerSerialType): int {
    if ($value === null) {
        return 0;
    }
    if (is_int($value)) {
        return $integerSerialType($value);
    }
    if (is_float($value)) {
        return 7;
    }
    if (is_string($value)) {
        return 13 + (strlen($value) * 2);
    }

    throw new InvalidArgumentException('Unsupported dynamic value in expectation');
};

$assertSqliteValue = static function (TestRunner $t, mixed $expected, mixed $actual, string $label): void {
    if (is_float($expected)) {
        $t->same('real', SQLiteAffinityComparison::storageClass($actual), $label . ' storage class');
        $t->true(abs($expected - (float) $actual) < 1.0e-9, $label . ' value');
        return;
    }

    $t->same($expected, $actual, $label . ' value');
    $t->same(SQLiteAffinityComparison::storageClass($expected), SQLiteAffinityComparison::storageClass($actual), $label . ' storage class');
};

$tests['real upstream corpus expression affinity dynamic types.test types-2 source ownership and exact record sizes'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $string10, $payloadLength, $assertSqliteValue): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream types.test exists');
        $t->contains('types-2.1.*: INTEGER', $sourceText);
        $t->contains('types-2.2.*: REAL', $sourceText);
        $t->contains('types-2.3.*: NULL', $sourceText);
        $t->contains('types-2.4.*: TEXT', $sourceText);
        $t->contains('types-2.5.*: Records with a few different storage classes.', $sourceText);
        $t->contains('{2 3 3 4 4 6 6 10 10}', $sourceText);
        $t->contains('{2 10 10}', $sourceText);
        $t->contains('{12 503 500004}', $sourceText);

        $integerRows = [
            [0, 2, 8],
            [120, 3, 1],
            [-120, 3, 1],
            [30000, 4, 2],
            [-30000, 4, 2],
            [2100000000, 6, 4],
            [-2100000000, 6, 4],
            [9000000000000000000, 10, 6],
            [-9000000000000000000, 10, 6],
        ];
        foreach ($integerRows as [$value, $expectedSize, $expectedSerialType]) {
            $payload = SQLiteRecord::encodeWithColumnAffinities([$value], ['INTEGER']);
            $raw = SQLiteRecord::parse($payload);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['INTEGER']);
            $t->same($expectedSize, strlen($payload), 'types-2.1 payload size ' . $value);
            $t->same($payloadLength([$expectedSerialType]), strlen($payload), 'types-2.1 computed payload size ' . $value);
            $t->same([$expectedSerialType], $raw->serialTypes, 'types-2.1 serial type ' . $value);
            $assertSqliteValue($t, $value, $typed->values[0], 'types-2.1 value ' . $value);
        }

        $realRows = [
            [0.0, 2, 8],
            [12345.678, 10, 7],
            [-12345.678, 10, 7],
        ];
        foreach ($realRows as [$value, $expectedSize, $expectedSerialType]) {
            $payload = SQLiteRecord::encodeWithColumnAffinities([$value], ['FLOAT']);
            $raw = SQLiteRecord::parse($payload);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['FLOAT']);
            $t->same($expectedSize, strlen($payload), 'types-2.2 payload size ' . (string) $value);
            $t->same([$expectedSerialType], $raw->serialTypes, 'types-2.2 serial type ' . (string) $value);
            $assertSqliteValue($t, $value, $typed->values[0], 'types-2.2 typed value ' . (string) $value);
        }

        $nullPayload = SQLiteRecord::encodeWithColumnAffinities([null], ['NULLVALUE']);
        $t->same(2, strlen($nullPayload), 'types-2.3 null payload size');
        $t->same([0], SQLiteRecord::parse($nullPayload)->serialTypes, 'types-2.3 null serial type');
        $t->same(null, SQLiteRecord::parseWithColumnAffinities($nullPayload, ['NULLVALUE'])->values[0], 'types-2.3 null value');

        $string500 = str_repeat($string10, 50);
        $string500000 = str_repeat($string10, 50000);
        foreach ([[$string10, 12], [$string500, 503], [$string500000, 500004]] as [$value, $expectedSize]) {
            $payload = SQLiteRecord::encodeWithColumnAffinities([$value], ['STRING']);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['STRING']);
            $t->same($expectedSize, strlen($payload), 'types-2.4 text payload size ' . strlen($value));
            $t->same(strlen($value), strlen($typed->values[0]), 'types-2.4 text length ' . strlen($value));
            $t->same(substr($value, 0, 20), substr($typed->values[0], 0, 20), 'types-2.4 text prefix ' . strlen($value));
        }

        $mixedRows = [
            [null, $string10, 4000],
            [$string500, 4000, null],
            [4000, null, $string500000],
        ];
        foreach ($mixedRows as $index => $row) {
            $payload = SQLiteRecord::encodeWithColumnAffinities($row, ['NONE', 'NONE', 'NONE']);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['NONE', 'NONE', 'NONE']);
            $t->same(count($row), count($typed->values), 'types-2.5 column count row ' . $index);
            $t->same($row[0] === null ? null : SQLiteAffinityComparison::storageClass($row[0]), $typed->values[0] === null ? null : SQLiteAffinityComparison::storageClass($typed->values[0]), 'types-2.5 first storage class row ' . $index);
            $t->same($row[1] === null ? null : SQLiteAffinityComparison::storageClass($row[1]), $typed->values[1] === null ? null : SQLiteAffinityComparison::storageClass($typed->values[1]), 'types-2.5 second storage class row ' . $index);
            $t->same($row[2] === null ? null : SQLiteAffinityComparison::storageClass($row[2]), $typed->values[2] === null ? null : SQLiteAffinityComparison::storageClass($typed->values[2]), 'types-2.5 third storage class row ' . $index);
        }
    };

$integerSeeds = [
    0,
    1,
    120,
    -120,
    127,
    -128,
    128,
    -129,
    30000,
    -30000,
    32767,
    -32768,
    32768,
    -32769,
    8388607,
    -8388608,
    8388608,
    -8388609,
    2100000000,
    -2100000000,
    2147483647,
    -2147483648,
    2147483648,
    -2147483649,
    140737488355327,
    -140737488355328,
    140737488355328,
    -140737488355329,
    9000000000000000000,
    -9000000000000000000,
];

for ($index = 0; $index < 320; $index++) {
    $tests[sprintf('real upstream corpus expression affinity dynamic types.test types-2.1 integer record matrix %03d', $index)] =
        static function (TestRunner $t) use ($index, $integerSeeds, $storedValueForAffinity, $serialTypeForStoredValue, $payloadLength, $assertSqliteValue): void {
            $seed = $integerSeeds[$index % count($integerSeeds)];
            $offset = intdiv($index, count($integerSeeds));
            $value = $seed < 0 ? $seed - $offset : $seed + $offset;
            $stored = $storedValueForAffinity($value, 'INTEGER');
            $serialType = $serialTypeForStoredValue($stored);

            $payload = SQLiteRecord::encodeWithColumnAffinities([$value], ['INTEGER']);
            $raw = SQLiteRecord::parse($payload);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['INTEGER']);

            $t->same($payloadLength([$serialType]), strlen($payload), 'types-2.1 dynamic payload size');
            $t->same([$serialType], $raw->serialTypes, 'types-2.1 dynamic serial type');
            $t->same($stored, $raw->values[0], 'types-2.1 raw stored value');
            $assertSqliteValue($t, $value, $typed->values[0], 'types-2.1 typed integer value');
        };
}

$realIntegralSeeds = [0, 1, 2, 120, -120, 127, -128, 128, -129, 30000, -30000, 2100000000, -2100000000];
for ($index = 0; $index < 260; $index++) {
    $tests[sprintf('real upstream corpus expression affinity dynamic types.test types-2.2 real record matrix %03d', $index)] =
        static function (TestRunner $t) use ($index, $realIntegralSeeds, $storedValueForAffinity, $serialTypeForStoredValue, $payloadLength, $assertSqliteValue): void {
            $seed = $realIntegralSeeds[$index % count($realIntegralSeeds)];
            $round = intdiv($index, count($realIntegralSeeds));
            $value = ($index % 2) === 0
                ? (float) ($seed + $round)
                : (float) ($seed + $round) + 0.125;
            $stored = $storedValueForAffinity($value, 'REAL');
            $serialType = $serialTypeForStoredValue($stored);

            $payload = SQLiteRecord::encodeWithColumnAffinities([$value], ['FLOAT']);
            $raw = SQLiteRecord::parse($payload);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['FLOAT']);

            $t->same($payloadLength([$serialType]), strlen($payload), 'types-2.2 dynamic payload size');
            $t->same([$serialType], $raw->serialTypes, 'types-2.2 dynamic serial type');
            $t->same($stored, $raw->values[0], 'types-2.2 raw stored value');
            $assertSqliteValue($t, $value, $typed->values[0], 'types-2.2 typed real value');
        };
}

for ($index = 0; $index < 220; $index++) {
    $tests[sprintf('real upstream corpus expression affinity dynamic types.test types-2.4 text record matrix %03d', $index)] =
        static function (TestRunner $t) use ($index, $string10, $serialTypeForStoredValue, $payloadLength, $assertSqliteValue): void {
            $length = match ($index % 5) {
                0 => 10 + intdiv($index, 5),
                1 => 500 + intdiv($index, 5),
                2 => 1 + intdiv($index, 5),
                3 => 127 + intdiv($index, 5),
                default => 16384 + intdiv($index, 5),
            };
            $value = substr(str_repeat($string10, intdiv($length + 9, 10)), 0, $length);
            $serialType = $serialTypeForStoredValue($value);

            $payload = SQLiteRecord::encodeWithColumnAffinities([$value], ['STRING']);
            $raw = SQLiteRecord::parse($payload);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['STRING']);

            $t->same($payloadLength([$serialType]), strlen($payload), 'types-2.4 dynamic payload size');
            $t->same([$serialType], $raw->serialTypes, 'types-2.4 dynamic serial type');
            $t->same(strlen($value), strlen($raw->values[0]), 'types-2.4 raw text length');
            $assertSqliteValue($t, $value, $typed->values[0], 'types-2.4 typed text value');
        };
}

for ($index = 0; $index < 200; $index++) {
    $tests[sprintf('real upstream corpus expression affinity dynamic types.test types-2.5 mixed storage record matrix %03d', $index)] =
        static function (TestRunner $t) use ($index, $string10, $serialTypeForStoredValue, $payloadLength): void {
            $length = ($index % 2) === 0 ? 10 + $index : 500 + $index;
            $text = substr(str_repeat($string10, intdiv($length + 9, 10)), 0, $length);
            $row = match ($index % 3) {
                0 => [null, $text, 4000 + $index],
                1 => [$text, 4000 + $index, null],
                default => [4000 + $index, null, $text],
            };
            $serialTypes = array_map($serialTypeForStoredValue, $row);

            $payload = SQLiteRecord::encodeWithColumnAffinities($row, ['NONE', 'NONE', 'NONE']);
            $raw = SQLiteRecord::parse($payload);
            $typed = SQLiteRecord::parseWithColumnAffinities($payload, ['NONE', 'NONE', 'NONE']);

            $t->same($payloadLength($serialTypes), strlen($payload), 'types-2.5 dynamic payload size');
            $t->same($serialTypes, $raw->serialTypes, 'types-2.5 dynamic serial types');
            $t->same($row, $raw->values, 'types-2.5 raw values');
            $t->same($row, $typed->values, 'types-2.5 typed values');
        };
}

return $tests;
