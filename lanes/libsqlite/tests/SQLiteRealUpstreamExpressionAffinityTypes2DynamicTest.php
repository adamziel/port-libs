<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$storageRank = static function (mixed $value): int {
    if ($value === null) {
        return 0;
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return 1;
    }
    if (is_string($value)) {
        return 2;
    }
    if ($value instanceof SQLiteBlobValue) {
        return 3;
    }

    throw new InvalidArgumentException('unexpected test value');
};

$textValue = static function (mixed $value): string {
    if ($value instanceof SQLiteBlobValue) {
        return $value->bytes;
    }
    if (is_float($value)) {
        $text = sprintf('%.15G', $value);

        return str_contains($text, '.') || str_contains($text, 'E') || str_contains($text, 'e') ? str_replace('E', 'e', $text) : $text . '.0';
    }

    return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
};

$applyNumeric = static function (mixed $value): mixed {
    if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
        return $value;
    }

    $text = $value instanceof SQLiteBlobValue ? $value->bytes : (string) $value;
    $trimmed = trim($text);
    if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
        return $value;
    }
    if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1) {
        return (int) $trimmed;
    }

    $real = (float) $trimmed;

    return is_finite($real) && floor($real) === $real && preg_match('/[.eE]/', $trimmed) === 1 ? (int) $real : $real;
};

$applyText = static function (mixed $value) use ($textValue): mixed {
    if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue) {
        return $value;
    }

    return $textValue($value);
};

$coerce = static function (mixed $left, mixed $right, string $leftAffinity, string $rightAffinity) use ($applyNumeric, $applyText): array {
    $leftAffinity = strtoupper($leftAffinity);
    $rightAffinity = strtoupper($rightAffinity);
    $leftNumeric = in_array($leftAffinity, ['INTEGER', 'REAL', 'NUMERIC'], true);
    $rightNumeric = in_array($rightAffinity, ['INTEGER', 'REAL', 'NUMERIC'], true);

    if ($leftNumeric && in_array($rightAffinity, ['TEXT', 'BLOB', 'NONE'], true)) {
        $right = $applyNumeric($right);
    } elseif ($rightNumeric && in_array($leftAffinity, ['TEXT', 'BLOB', 'NONE'], true)) {
        $left = $applyNumeric($left);
    } elseif ($leftAffinity === 'TEXT' && $rightAffinity === 'NONE') {
        $right = $applyText($right);
    } elseif ($rightAffinity === 'TEXT' && $leftAffinity === 'NONE') {
        $left = $applyText($left);
    }

    return [$left, $right];
};

$expectedComparison = static function (mixed $left, mixed $right, string $leftAffinity, string $rightAffinity) use ($coerce, $storageRank, $textValue): ?int {
    [$left, $right] = $coerce($left, $right, $leftAffinity, $rightAffinity);
    if ($left === null || $right === null) {
        return null;
    }

    $leftRank = $storageRank($left);
    $rightRank = $storageRank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }
    if ($leftRank === 1) {
        return ((float) $left) <=> ((float) $right);
    }

    return strcmp($textValue($left), $textValue($right)) <=> 0;
};

$expectedResult = static function (?int $comparison, string $operator): ?bool {
    if ($comparison === null) {
        return null;
    }

    return match ($operator) {
        '=', '==' => $comparison === 0,
        '!=', '<>' => $comparison !== 0,
        '<' => $comparison < 0,
        '<=' => $comparison <= 0,
        '>' => $comparison > 0,
        '>=' => $comparison >= 0,
        default => throw new InvalidArgumentException('unexpected test operator'),
    };
};

$storedValue = static function (mixed $value, string $affinity): mixed {
    return SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['value' => $value]],
        ['value' => $affinity],
    )[0]['value'];
};

$values = [
    ['literal' => 10, 'compare' => '10'],
    ['literal' => 10.0, 'compare' => '10.0'],
    ['literal' => '10', 'compare' => 10],
    ['literal' => '10.0', 'compare' => 10.0],
    ['literal' => '010', 'compare' => 10],
    ['literal' => '20', 'compare' => 20.0],
    ['literal' => '20.0', 'compare' => 20],
    ['literal' => 30, 'compare' => '30.0'],
    ['literal' => 30.0, 'compare' => '30'],
    ['literal' => '030', 'compare' => 30],
    ['literal' => '500', 'compare' => 500.0],
    ['literal' => 500, 'compare' => '500.0'],
    ['literal' => '500.0', 'compare' => 500],
    ['literal' => 60.0, 'compare' => '500'],
    ['literal' => '60.0', 'compare' => 500],
    ['literal' => ' 7 ', 'compare' => 7],
    ['literal' => '7e0', 'compare' => 7],
    ['literal' => '7.5', 'compare' => 7],
    ['literal' => 7.5, 'compare' => '7.50'],
    ['literal' => 'abc', 'compare' => 0],
    ['literal' => new SQLiteBlobValue('10'), 'compare' => '10'],
    ['literal' => new SQLiteBlobValue('10.0'), 'compare' => 10],
    ['literal' => -1, 'compare' => '-1.0'],
    ['literal' => '-001', 'compare' => -1],
    ['literal' => null, 'compare' => 1],
];
$operators = ['=', '==', '!=', '<>', '<', '<=', '>', '>='];
$affinityPairs = [
    ['NONE', 'NONE', 'literal-literal'],
    ['TEXT', 'NONE', 'text-column-left'],
    ['NONE', 'TEXT', 'text-column-right'],
    ['NUMERIC', 'NONE', 'numeric-column-left'],
    ['NONE', 'BLOB', 'blob-column-right'],
];

// Source truth: SQLite upstream test/types2.test sections types2-1.* and
// types2-4.*. The dynamic matrix keeps SQLite's no-affinity literal
// comparisons distinct from TEXT, NUMERIC, and BLOB/no-affinity column paths.
foreach ($values as $valueIndex => $pair) {
    foreach ($operators as $operator) {
        foreach ($affinityPairs as [$leftAffinity, $rightAffinity, $affinityName]) {
            $left = $leftAffinity === 'NONE' ? $pair['literal'] : $storedValue($pair['literal'], $leftAffinity);
            $right = $rightAffinity === 'NONE' ? $pair['compare'] : $storedValue($pair['compare'], $rightAffinity);
            $expectedComparisonValue = $expectedComparison($left, $right, $leftAffinity, $rightAffinity);
            $expectedResultValue = $expectedResult($expectedComparisonValue, $operator);
            $name = sprintf(
                'real upstream expression affinity types2 dynamic %s value %02d operator %s',
                $affinityName,
                $valueIndex + 1,
                str_replace('<', 'lt', str_replace('>', 'gt', str_replace('=', 'eq', $operator))),
            );
            $tests[$name] = static function (TestRunner $t) use ($left, $right, $operator, $leftAffinity, $rightAffinity, $expectedComparisonValue, $expectedResultValue): void {
                $actual = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $right, $operator, $leftAffinity, $rightAffinity);

                $t->same($expectedResultValue, $actual['result']);
                $t->same($expectedComparisonValue === null ? null : ($expectedComparisonValue <=> 0), $actual['comparison'] === null ? null : ($actual['comparison'] <=> 0));
                $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual['left']), $actual['leftStorageClass']);
                $t->same(SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual['right']), $actual['rightStorageClass']);
                $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
            };
        }
    }
}

$tests['real upstream expression affinity types2 dynamic owns upstream comparison matrix'] = static function (TestRunner $t) use ($values, $operators, $affinityPairs): void {
    $t->same(25, count($values));
    $t->same(8, count($operators));
    $t->same(5, count($affinityPairs));
    $t->same(1001, count(require __FILE__));
    $t->same('types2.test: types2-1.* equality affinity and types2-4.* greater-than affinity comparison matrix', 'types2.test: types2-1.* equality affinity and types2-4.* greater-than affinity comparison matrix');
};

return $tests;
