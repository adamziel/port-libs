<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectResult;

$tests = [];

$tests['decorated select result sort preserves storage classes json subtype and stable peers'] = static function (TestRunner $t): void {
    $rows = [
        ['label' => 'null-first', 'value' => null],
        ['label' => 'null-second', 'value' => null],
        ['label' => 'false', 'value' => false],
        ['label' => 'zero', 'value' => 0],
        ['label' => 'half', 'value' => 0.5],
        ['label' => 'one', 'value' => 1],
        ['label' => 'json-alpha', 'value' => new SQLiteJsonSubtypeValue('Alpha')],
        ['label' => 'text-alpha-peer', 'value' => 'Alpha'],
        ['label' => 'text-alpha-lower', 'value' => 'alpha'],
        ['label' => 'blob-alpha', 'value' => new SQLiteBlobValue('Alpha')],
    ];

    $ascending = SQLiteSelectResult::orderBy($rows, [['column' => 'value']]);
    $t->same([
        'null-first',
        'null-second',
        'false',
        'zero',
        'half',
        'one',
        'json-alpha',
        'text-alpha-peer',
        'text-alpha-lower',
        'blob-alpha',
    ], array_column($ascending, 'label'));

    $descendingNullsLast = SQLiteSelectResult::orderBy(
        $rows,
        [['column' => 'value', 'direction' => 'DESC', 'nulls' => 'LAST']],
    );
    $t->same([
        'blob-alpha',
        'text-alpha-lower',
        'json-alpha',
        'text-alpha-peer',
        'one',
        'half',
        'false',
        'zero',
        'null-first',
        'null-second',
    ], array_column($descendingNullsLast, 'label'));
};

$tests['decorated select result sort preserves built in and custom collation semantics'] = static function (TestRunner $t): void {
    $rows = [
        ['label' => 'plugin', 'value' => 'plugin'],
        ['label' => 'plugin-upper', 'value' => 'Plugin'],
        ['label' => 'plugin-space', 'value' => 'plugin '],
        ['label' => 'alpha', 'value' => 'alpha'],
        ['label' => 'null', 'value' => null],
    ];

    $orderedLabels = static fn (string $collation): array => array_column(
        SQLiteSelectResult::orderBy(
            $rows,
            [['column' => 'value', 'collation' => $collation, 'nulls' => 'LAST']],
        ),
        'label',
    );

    $t->same(
        ['plugin-upper', 'alpha', 'plugin', 'plugin-space', 'null'],
        $orderedLabels('BINARY'),
    );
    $t->same(
        ['alpha', 'plugin', 'plugin-upper', 'plugin-space', 'null'],
        $orderedLabels('NOCASE'),
    );
    $t->same(
        ['plugin-upper', 'alpha', 'plugin', 'plugin-space', 'null'],
        $orderedLabels('RTRIM'),
    );
    $t->same(
        ['plugin-space', 'plugin', 'alpha', 'plugin-upper', 'null'],
        $orderedLabels('REVERSE'),
    );

    $custom = static function (string $left, string $right): int {
        $lengthComparison = strlen($left) <=> strlen($right);

        return $lengthComparison !== 0 ? $lengthComparison : strcmp($right, $left);
    };
    $customOrder = SQLiteSelectResult::orderBy(
        $rows,
        [['column' => 'value', 'collation' => 'binary', 'nulls' => 'FIRST']],
        ['BINARY' => $custom],
    );
    $t->same(
        ['null', 'alpha', 'plugin', 'plugin-upper', 'plugin-space'],
        array_column($customOrder, 'label'),
    );
};

$tests['decorated select result sort matches legacy wide key ordering'] = static function (TestRunner $t): void {
    $rows = [];
    for ($id = 1; $id <= 3000; $id++) {
        $rank = ($id * 7919) % 3000;
        $prefix = sprintf('%06d:%06d:', $rank, $id);
        $rows[] = [
            'id' => $id,
            'payload' => str_pad($prefix, 160, chr(97 + ($id % 26))),
        ];
    }
    $terms = [
        ['column' => 'payload', 'direction' => 'DESC'],
        ['column' => 'id', 'direction' => 'DESC'],
    ];

    $legacyRank = static fn (mixed $value): int => match (true) {
        $value === null => 0,
        is_int($value) || is_float($value) || is_bool($value) => 1,
        is_string($value) => 2,
        $value instanceof SQLiteBlobValue => 3,
        default => throw new InvalidArgumentException('Unsupported legacy ORDER BY value'),
    };
    $legacyCompare = static function (mixed $left, mixed $right) use ($legacyRank): int {
        $leftRank = $legacyRank($left);
        $rightRank = $legacyRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null || $right === null) {
            return $left === null && $right === null ? 0 : ($left === null ? -1 : 1);
        }

        $comparison = SQLiteAffinityComparison::compare(
            $left,
            $right,
            'NONE',
            'NONE',
            strtoupper('BINARY'),
        );
        if ($comparison === null) {
            throw new RuntimeException('Legacy ORDER BY comparison returned NULL');
        }

        return $comparison;
    };
    $legacyOrder = static function () use ($rows, $legacyCompare): array {
        $ordered = [];
        foreach ($rows as $index => $row) {
            $ordered[] = [$row, $index];
        }
        usort($ordered, static function (array $left, array $right) use ($legacyCompare): int {
            $comparison = $legacyCompare($left[0]['payload'], $right[0]['payload']);
            if ($comparison !== 0) {
                return -$comparison;
            }
            $comparison = $legacyCompare($left[0]['id'], $right[0]['id']);

            return $comparison !== 0 ? -$comparison : $left[1] <=> $right[1];
        });

        return array_column($ordered, 0);
    };
    $decoratedOrder = static fn (): array => SQLiteSelectResult::orderBy($rows, $terms);

    $expected = $legacyOrder();
    $actual = $decoratedOrder();
    $t->same(array_column($expected, 'id'), array_column($actual, 'id'));
};

return $tests;
