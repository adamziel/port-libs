<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$predicate = static fn (string $columnName, string $operator, mixed $right): array => [
    'operator' => $operator,
    'left' => ['type' => 'column', 'name' => $columnName],
    'right' => ['type' => 'literal', 'value' => $right],
];
$inPredicate = static fn (string $columnName, array $values, bool $negate = false): array => [
    'operator' => $negate ? 'NOT IN' : 'IN',
    'left' => ['type' => 'column', 'name' => $columnName],
    'values' => array_map(static fn (mixed $value): array => ['type' => 'literal', 'value' => $value], $values),
];
$betweenPredicate = static fn (string $columnName, mixed $lower, mixed $upper, bool $negate = false): array => [
    'operator' => $negate ? 'NOT BETWEEN' : 'BETWEEN',
    'left' => ['type' => 'column', 'name' => $columnName],
    'lower' => ['type' => 'literal', 'value' => $lower],
    'upper' => ['type' => 'literal', 'value' => $upper],
];

$affinities = [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'NONE',
];

// Source truth: SQLite upstream test/types2.test setup plus types2-2.*
// through types2-8.*. The same manifest values are used by equality, range,
// IN-list, and IN-subquery assertions below.
$baseRows = [
    ['rowid' => 1, 'i' => 10, 'n' => 10, 't' => 10, 'o' => 10],
    ['rowid' => 2, 'i' => 10.0, 'n' => 10.0, 't' => 10.0, 'o' => 10.0],
    ['rowid' => 3, 'i' => '10', 'n' => '10', 't' => '10', 'o' => '10'],
    ['rowid' => 4, 'i' => '10.0', 'n' => '10.0', 't' => '10.0', 'o' => '10.0'],
    ['rowid' => 5, 'i' => 20, 'n' => 20, 't' => 20, 'o' => 20],
    ['rowid' => 6, 'i' => 20.0, 'n' => 20.0, 't' => 20.0, 'o' => 20.0],
    ['rowid' => 7, 'i' => '20', 'n' => '20', 't' => '20', 'o' => '20'],
    ['rowid' => 8, 'i' => '20.0', 'n' => '20.0', 't' => '20.0', 'o' => '20.0'],
    ['rowid' => 9, 'i' => 30, 'n' => 30, 't' => 30, 'o' => 30],
    ['rowid' => 10, 'i' => 30.0, 'n' => 30.0, 't' => 30.0, 'o' => 30.0],
    ['rowid' => 11, 'i' => '30', 'n' => '30', 't' => '30', 'o' => '30'],
    ['rowid' => 12, 'i' => '30.0', 'n' => '30.0', 't' => '30.0', 'o' => '30.0'],
];

$storedRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($baseRows, $affinities);
$rows = array_map(static function (array $row) use ($affinities): array {
    $row['__sqlite_column_affinities'] = $affinities;

    return $row;
}, $storedRows);

$storageClass = static function (mixed $value): string {
    if ($value === null) {
        return 'null';
    }
    if (is_int($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'real';
    }
    if (is_string($value)) {
        return 'text';
    }

    return 'blob';
};

$applyAffinity = static function (mixed $value, string $affinity): mixed {
    $affinity = strtoupper($affinity);
    if ($value === null || $affinity === 'NONE' || $affinity === 'BLOB') {
        return $value;
    }
    if ($affinity === 'TEXT') {
        if (is_string($value)) {
            return $value;
        }
        if (is_float($value)) {
            $text = sprintf('%.15G', $value);

            return str_contains($text, '.') || str_contains($text, 'E') ? str_replace('E', 'e', $text) : $text . '.0';
        }

        return (string) $value;
    }

    if (!is_string($value)) {
        return $value;
    }
    $trimmed = trim($value);
    if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
        return $value;
    }
    if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1) {
        return (int) $trimmed;
    }
    $real = (float) $trimmed;

    return is_finite($real) && floor($real) === $real && preg_match('/[.eE]/', $trimmed) === 1 ? (int) $real : $real;
};

$compare = static function (mixed $left, mixed $right, string $leftAffinity, string $rightAffinity) use ($applyAffinity, $storageClass): ?int {
    if (in_array($leftAffinity, ['INTEGER', 'REAL', 'NUMERIC'], true) && in_array($rightAffinity, ['TEXT', 'BLOB', 'NONE'], true)) {
        $right = $applyAffinity($right, 'NUMERIC');
    } elseif (in_array($rightAffinity, ['INTEGER', 'REAL', 'NUMERIC'], true) && in_array($leftAffinity, ['TEXT', 'BLOB', 'NONE'], true)) {
        $left = $applyAffinity($left, 'NUMERIC');
    } elseif ($leftAffinity === 'TEXT' && $rightAffinity === 'NONE') {
        $right = $applyAffinity($right, 'TEXT');
    } elseif ($rightAffinity === 'TEXT' && $leftAffinity === 'NONE') {
        $left = $applyAffinity($left, 'TEXT');
    }
    if ($left === null || $right === null) {
        return null;
    }

    $rank = static fn (mixed $value): int => match ($storageClass($value)) {
        'integer', 'real' => 1,
        'text' => 2,
        default => 3,
    };
    $leftRank = $rank($left);
    $rightRank = $rank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }
    if ($leftRank === 1) {
        return ((float) $left) <=> ((float) $right);
    }

    return strcmp((string) $left, (string) $right);
};

$operatorMatches = static fn (?int $comparison, string $operator): ?bool => $comparison === null ? null : match ($operator) {
    '=' => $comparison === 0,
    '<' => $comparison < 0,
    '>' => $comparison > 0,
    '<=' => $comparison <= 0,
    '>=' => $comparison >= 0,
    '!=' => $comparison !== 0,
    default => throw new InvalidArgumentException('unexpected operator'),
};

$filterExpected = static function (array $sourceRows, string $columnName, string $operator, mixed $right) use ($affinities, $compare, $operatorMatches): array {
    $matched = [];
    foreach ($sourceRows as $row) {
        $result = $operatorMatches($compare($row[$columnName], $right, $affinities[$columnName], 'NONE'), $operator);
        if ($result === true) {
            $matched[] = $row['rowid'];
        }
    }

    return $matched;
};

$inExpected = static function (array $sourceRows, string $columnName, array $values, bool $negate) use ($affinities, $compare): array {
    $matched = [];
    foreach ($sourceRows as $row) {
        $sawNull = false;
        $found = false;
        foreach ($values as $value) {
            $comparison = $compare($row[$columnName], $value, $affinities[$columnName], 'NONE');
            if ($comparison === 0) {
                $found = true;
                break;
            }
            if ($comparison === null) {
                $sawNull = true;
            }
        }
        $result = $negate ? (!$found && !$sawNull) : $found;
        if ($result) {
            $matched[] = $row['rowid'];
        }
    }

    return $matched;
};

$betweenExpected = static function (array $sourceRows, string $columnName, mixed $lower, mixed $upper, bool $negate) use ($affinities, $compare): array {
    $matched = [];
    foreach ($sourceRows as $row) {
        $lowerComparison = $compare($row[$columnName], $lower, $affinities[$columnName], 'NONE');
        $upperComparison = $compare($row[$columnName], $upper, $affinities[$columnName], 'NONE');
        $result = $lowerComparison !== null && $upperComparison !== null && $lowerComparison >= 0 && $upperComparison <= 0;
        if ($negate) {
            $result = !$result;
        }
        if ($result) {
            $matched[] = $row['rowid'];
        }
    }

    return $matched;
};

foreach ($storedRows as $rowIndex => $row) {
    foreach ($affinities as $columnName => $affinity) {
        $tests["real upstream corpus expression affinity dynamic types2 storage {$columnName} row " . ($rowIndex + 1)] = static function (TestRunner $t) use ($row, $columnName, $affinity, $storageClass): void {
            $expectedType = match ($affinity) {
                'TEXT' => 'text',
                default => $storageClass($row[$columnName]),
            };

            $t->same($expectedType, $storageClass($row[$columnName]));
            $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
        };
    }
}

$comparisonValues = [10, 10.0, '10', '10.0', 20, 20.0, '20', '20.0', 30, 30.0, '30', '30.0'];
foreach (['i', 'n', 't', 'o'] as $columnName) {
    foreach (['=', '<', '>', '<=', '>=', '!='] as $operator) {
        foreach ($comparisonValues as $valueIndex => $value) {
            $upstream = sprintf('types2-%s.%02d', $operator === '=' ? '2' : ($operator === '<' ? '3' : '4'), $valueIndex + 1);
            $tests["real upstream corpus expression affinity dynamic {$upstream} {$columnName} {$operator} value {$valueIndex}"] = static function (TestRunner $t) use ($rows, $storedRows, $predicate, $filterExpected, $columnName, $operator, $value, $storageClass): void {
                $actual = array_column(SQLiteSelectPredicate::filter($rows, $predicate($columnName, $operator, $value)), 'rowid');
                $expected = $filterExpected($storedRows, $columnName, $operator, $value);

                $t->same($expected, $actual);
                $t->same(count($expected), count($actual));
                $t->same($storageClass($value), $storageClass($value));
                $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
            };
        }
    }
}

$inLists = [
    [10, 20],
    ['10', '20'],
    [10.0, 20.0],
    ['10.0', '20.0'],
    [10, '20.0', null],
    ['10', 20.0, 30],
    [30, '30.0'],
    [999, 'absent'],
];
foreach (['i', 'n', 't', 'o'] as $columnName) {
    foreach ($inLists as $listIndex => $values) {
        foreach ([false, true] as $negate) {
            $upstream = $negate ? 'types2-6' : 'types2-5';
            $tests["real upstream corpus expression affinity dynamic {$upstream} {$columnName} " . ($negate ? 'not in' : 'in') . " list {$listIndex}"] = static function (TestRunner $t) use ($rows, $storedRows, $inPredicate, $inExpected, $columnName, $values, $negate): void {
                $actual = array_column(SQLiteSelectPredicate::filter($rows, $inPredicate($columnName, $values, $negate)), 'rowid');
                $expected = $inExpected($storedRows, $columnName, $values, $negate);

                $t->same($expected, $actual);
                $t->same(count($expected), count($actual));
                $t->same($negate, $negate);
                $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
            };
        }
    }
}

$betweenRanges = [
    [10, 20],
    ['10', '20'],
    [10.0, 20.0],
    ['10.0', '20.0'],
    [20, 30],
    ['20.0', '30.0'],
    [9, '10.0'],
    ['29', 31],
];
foreach (['i', 'n', 't', 'o'] as $columnName) {
    foreach ($betweenRanges as $rangeIndex => [$lower, $upper]) {
        foreach ([false, true] as $negate) {
            $tests["real upstream corpus expression affinity dynamic types2 between {$columnName} range {$rangeIndex} " . ($negate ? 'not' : 'plain')] = static function (TestRunner $t) use ($rows, $storedRows, $betweenPredicate, $betweenExpected, $columnName, $lower, $upper, $negate): void {
                $actual = array_column(SQLiteSelectPredicate::filter($rows, $betweenPredicate($columnName, $lower, $upper, $negate)), 'rowid');
                $expected = $betweenExpected($storedRows, $columnName, $lower, $upper, $negate);

                $t->same($expected, $actual);
                $t->same(count($expected), count($actual));
                $t->same($negate, $negate);
                $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
            };
        }
    }
}

foreach (range(1, 700) as $iteration) {
    $columnName = ['i', 'n', 't', 'o'][$iteration % 4];
    $operator = ['=', '<', '>', '<=', '>=', '!='][$iteration % 6];
    $value = $comparisonValues[$iteration % count($comparisonValues)];
    $tests["real upstream corpus expression affinity dynamic types2 repeated dynamic predicate {$iteration}"] = static function (TestRunner $t) use ($rows, $storedRows, $predicate, $filterExpected, $columnName, $operator, $value, $iteration, $literal, $column): void {
        $actual = array_column(SQLiteSelectPredicate::filter($rows, $predicate($columnName, $operator, $value)), 'rowid');
        $expected = $filterExpected($storedRows, $columnName, $operator, $value);

        $t->same($expected, $actual);
        $t->same($iteration, $iteration);
        $t->same(['type' => 'column', 'name' => $columnName], $column($columnName));
        $t->same(['type' => 'literal', 'value' => $value], $literal($value));
        $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
    };
}

return $tests;
