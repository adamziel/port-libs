<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$textValue = static function (mixed $value): string {
    if ($value instanceof SQLiteBlobValue) {
        return $value->bytes;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string) $value;
};

$truthy = static function (mixed $value): bool {
    if ($value === null) {
        return false;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value != 0;
    }
    if (is_string($value)) {
        return (float) $value != 0.0;
    }

    return true;
};

$frameIndexes = static function (int $count, int $index, string $start, string $end): array {
    $bound = static function (string $boundary, bool $isStart) use ($index, $count): int {
        return match ($boundary) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $index,
            '1 PRECEDING' => $index - 1,
            '2 PRECEDING' => $index - 2,
            '1 FOLLOWING' => $index + 1,
            '2 FOLLOWING' => $index + 2,
            default => throw new RuntimeException('Unsupported windowC boundary ' . $boundary),
        };
    };

    $first = $bound($start, true);
    $last = $bound($end, false);
    if ($first > $last || $last < 0 || $first > $count - 1) {
        return [];
    }

    return range(max(0, $first), min($count - 1, $last));
};

$excludeIndexes = static function (array $indexes, int $current, array $keys, string $exclude): array {
    return array_values(array_filter($indexes, static function (int $candidate) use ($current, $keys, $exclude): bool {
        $peer = $keys[$candidate] === $keys[$current];

        return match ($exclude) {
            'CURRENT ROW' => $candidate !== $current,
            'GROUP' => !$peer,
            'TIES' => !$peer || $candidate === $current,
            default => true,
        };
    }));
};

$expectedConcat = static function (
    array $values,
    array $separators,
    array $keys,
    int $row,
    string $start,
    string $end,
    string $exclude,
    ?array $filters,
) use ($frameIndexes, $excludeIndexes, $truthy, $textValue): ?string {
    $indexes = $frameIndexes(count($values), $row, $start, $end);
    $indexes = $excludeIndexes($indexes, $row, $keys, $exclude);
    if ($filters !== null) {
        $indexes = array_values(array_filter($indexes, static fn (int $index): bool => $truthy($filters[$index])));
    }

    $result = null;
    foreach ($indexes as $index) {
        if ($values[$index] === null) {
            continue;
        }
        if ($result === null) {
            $result = $textValue($values[$index]);
            continue;
        }

        $result .= ($separators[$index] === null ? '' : $textValue($separators[$index])) . $textValue($values[$index]);
    }

    return $result;
};

$separatorSets = [
    'windowC.test 1.text.1 generated separators' => ['a', 'b', 'c', 'def', 'g'],
    'windowC.test 1.text.2 empty alternating separators' => ['abcdefg', '', '', 'abcdefg'],
    'windowC.test 1.text.3 widening separators' => ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
    'windowC.test 1.blob.4 widening blob separators' => ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
    'windowC.test 1.blob.5 comma and dot blob separators' => [',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', '.......', ',', ','],
    'windowC.test 2.0 utf16le fuzz separators' => [',a,', ',a,', ',bc,', ',bc,'],
    'windowC.test 2.1 utf16be fuzz separators' => [',a,', ',a,', ',bc,', ',bc,'],
];

$valueSets = [
    'literal val' => static fn (int $count): array => array_fill(0, $count, 'val'),
    'integer y' => static fn (int $count): array => array_map(static fn (int $index): int => $index + 1, range(0, $count - 1)),
    'utf fuzz y' => static fn (int $count): array => array_map(
        static fn (int $index): mixed => $index % 2 === 0 ? 1 : new SQLiteBlobValue("\x55\x85\xd0\x90\x13\x45\x51\x78\xcd\x11\xce\x4a"),
        range(0, $count - 1),
    ),
    'null sparse y' => static fn (int $count): array => array_map(static fn (int $index): mixed => $index % 4 === 0 ? null : 'val' . $index, range(0, $count - 1)),
];

$frames = [
    '1 PRECEDING AND 1 FOLLOWING' => ['1 PRECEDING', '1 FOLLOWING'],
    '2 PRECEDING AND CURRENT ROW' => ['2 PRECEDING', 'CURRENT ROW'],
    'CURRENT ROW AND UNBOUNDED FOLLOWING' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    '1 PRECEDING AND 1 PRECEDING' => ['1 PRECEDING', '1 PRECEDING'],
];

$excludes = ['NO OTHERS', 'CURRENT ROW', 'TIES', 'GROUP'];
$filterSets = [
    'unfiltered' => null,
    'truthy odd rows' => static fn (int $count): array => array_map(static fn (int $index): int => $index % 2, range(0, $count - 1)),
    'numeric text filters' => static fn (int $count): array => array_map(static fn (int $index): mixed => match ($index % 4) {
        0 => '0',
        1 => '1',
        2 => null,
        default => '2x',
    }, range(0, $count - 1)),
];

foreach ($separatorSets as $separatorName => $rawSeparators) {
    $separatorVariants = [
        'text separators' => $rawSeparators,
        'blob separators' => array_map(static fn (string $separator): SQLiteBlobValue => new SQLiteBlobValue($separator), $rawSeparators),
    ];

    foreach ($separatorVariants as $separatorVariant => $separators) {
        foreach ($valueSets as $valueName => $valueFactory) {
            $values = $valueFactory(count($separators));
            $keys = range(1, count($separators));
            $peerKeys = array_map(static fn (int $index): int => intdiv($index, 2), range(0, count($separators) - 1));

            foreach ([$keys, $peerKeys] as $keySetIndex => $orderKeys) {
                foreach ($frames as $frameName => [$start, $end]) {
                    foreach ($excludes as $exclude) {
                        foreach ($filterSets as $filterName => $filterFactory) {
                            $filters = $filterFactory === null ? null : $filterFactory(count($separators));
                            $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators(
                                $values,
                                $separators,
                                $orderKeys,
                                'ROWS',
                                $start,
                                $end,
                                $exclude,
                                $filters,
                            );

                            foreach ($values as $row => $_value) {
                                $expected = $expectedConcat($values, $separators, $orderKeys, $row, $start, $end, $exclude, $filters);
                                $testName = sprintf(
                                    'real upstream %s %s %s keyset %d %s exclude %s filter %s row %d',
                                    $separatorName,
                                    $separatorVariant,
                                    $valueName,
                                    $keySetIndex + 1,
                                    $frameName,
                                    $exclude,
                                    $filterName,
                                    $row + 1,
                                );
                                $tests[$testName] = static function (TestRunner $t) use ($expected, $actual, $row): void {
                                    $t->same($expected, $actual[$row]);
                                    if ($actual[$row] !== null) {
                                        $t->same('val' === substr($actual[$row], 0, 3) || preg_match('/^[0-9U]/', $actual[$row]) === 1, true);
                                    }
                                };
                            }
                        }
                    }
                }
            }
        }
    }
}

$tests['real upstream windowC exact previous-row utf separator rows'] = static function (TestRunner $t): void {
    $values = [1, new SQLiteBlobValue("\x55\x85\xd0\x90\x13\x45\x51\x78\xcd\x11\xce\x4a"), 1, new SQLiteBlobValue("\x55\x85\xd0\x90\x13\x45\x51\x78\xcd\x11\xce\x4a")];
    $separators = [',a,', ',a,', ',bc,', ',bc,'];
    $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators($values, $separators, range(1, 4), 'ROWS', '1 PRECEDING', '1 PRECEDING');

    $t->same([null, '1', "\x55\x85\xd0\x90\x13\x45\x51\x78\xcd\x11\xce\x4a", '1'], $actual);
};

return $tests;
