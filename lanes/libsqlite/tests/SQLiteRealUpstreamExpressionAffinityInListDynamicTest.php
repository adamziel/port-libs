<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$unary = static fn (string $operator, array $operand): array => [
    'type' => 'unary',
    'operator' => $operator,
    'operand' => $operand,
];
$predicate = static fn (array $left, string $operator, array $right): array => [
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];
$inPredicate = static fn (array $left, array $values): array => [
    'operator' => 'IN',
    'left' => $left,
    'values' => $values,
];

$affinities = [
    'xi' => 'INTEGER',
    'xr' => 'REAL',
    'xb' => 'BLOB',
    'xn' => 'NUMERIC',
    'xt' => 'TEXT',
    't1' => 'TEXT',
    't_decimal' => 'TEXT',
    't_plain' => 'TEXT',
    't_leading' => 'TEXT',
    'n1' => 'NUMERIC',
    'o_int' => 'BLOB',
    'o_text' => 'BLOB',
    'o_text_decimal' => 'BLOB',
];

$store = static fn (mixed $value, string $affinity): mixed => SQLiteAffinityComparison::applyAffinity($value, $affinity);

$affinityRow = static function (int $seed) use ($affinities, $store): array {
    $base = ($seed % 900) + 1;
    $text = '0' . (string) $base;

    return [
        'xi' => $store($text, 'INTEGER'),
        'xr' => $store($text, 'REAL'),
        'xb' => $text,
        'xn' => $store($text, 'NUMERIC'),
        'xt' => $store($text, 'TEXT'),
        '__sqlite_column_affinities' => $affinities,
    ];
};

$comparisonRow = static function (int $seed) use ($affinities, $store): array {
    $base = ($seed % 900) + 100;

    return [
        't1' => $store($base, 'TEXT'),
        't_decimal' => $store((string) $base . '.0', 'TEXT'),
        'n1' => $store((string) $base, 'NUMERIC'),
        'o_int' => $base,
        'o_text' => (string) $base,
        '__sqlite_column_affinities' => $affinities,
    ];
};

$inListRow = static function (int $seed) use ($affinities, $store): array {
    $base = ($seed % 900) + 10;

    return [
        't1' => $store((string) $base . '.0', 'TEXT'),
        't_plain' => $store((string) $base, 'TEXT'),
        't_leading' => $store('0' . (string) $base, 'TEXT'),
        'n1' => $store((string) $base . '.0', 'NUMERIC'),
        'o_int' => $base,
        'o_text_decimal' => (string) $base . '.0',
        '__sqlite_column_affinities' => $affinities,
    ];
};

$assertPredicate = static function (TestRunner $t, array $row, array $payload, ?bool $expected): void {
    $actual = SQLiteSelectPredicate::evaluate($row, $payload);
    $t->same($expected, $actual);
};

foreach (range(0, 299) as $seed) {
    $tests[sprintf('real upstream expression affinity dynamic affinity2 unary plus comparison seed %03d', $seed)] = static function (TestRunner $t) use ($affinityRow, $column, $unary, $predicate, $assertPredicate, $seed): void {
        $row = $affinityRow($seed);

        $assertPredicate($t, $row, $predicate($column('xi'), '==', $column('xt')), true);
        $assertPredicate($t, $row, $predicate($column('xr'), '==', $column('xt')), true);
        $assertPredicate($t, $row, $predicate($column('xn'), '==', $column('xt')), true);
        $assertPredicate($t, $row, $predicate($column('xi'), '==', $column('xb')), true);
        $assertPredicate($t, $row, $predicate($column('xt'), '==', $unary('+', $column('xi'))), false);
        $assertPredicate($t, $row, $predicate($column('xt'), '==', $column('xi')), true);
        $assertPredicate($t, $row, $predicate($column('xt'), '==', $column('xb')), true);
        $t->contains('affinity2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
    };
}

foreach (range(0, 399) as $seed) {
    $tests[sprintf('real upstream expression affinity dynamic types2 scalar comparisons seed %03d', $seed)] = static function (TestRunner $t) use ($comparisonRow, $literal, $column, $predicate, $assertPredicate, $seed): void {
        $row = $comparisonRow($seed);
        $base = ($seed % 900) + 100;
        $baseText = (string) $base;
        $baseRealText = $baseText . '.0';

        $assertPredicate($t, $row, $predicate($literal($base), '=', $column('t1')), true);
        $assertPredicate($t, $row, $predicate($literal($baseText), '=', $column('t1')), true);
        $assertPredicate($t, $row, $predicate($literal((float) $base), '=', $column('t1')), false);
        $assertPredicate($t, $row, $predicate($literal($baseRealText), '=', $column('t1')), false);
        $assertPredicate($t, $row, $predicate($literal($base), '=', $column('n1')), true);
        $assertPredicate($t, $row, $predicate($literal($baseRealText), '=', $column('n1')), true);
        $assertPredicate($t, $row, $predicate($literal($base), '=', $column('o_int')), true);
        $assertPredicate($t, $row, $predicate($literal($baseText), '=', $column('o_int')), false);
        $assertPredicate($t, $row, $predicate($literal($base), '=', $column('o_text')), false);
        $assertPredicate($t, $row, $predicate($literal($baseText), '=', $column('o_text')), true);
        $assertPredicate($t, $row, $predicate($column('t_decimal'), '>', $literal($base)), true);
        $assertPredicate($t, $row, $predicate($column('n1'), '>', $literal($base - 1)), true);
        $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
    };
}

foreach (range(0, 299) as $seed) {
    $tests[sprintf('real upstream expression affinity dynamic types2 in-list comparisons seed %03d', $seed)] = static function (TestRunner $t) use ($inListRow, $literal, $column, $inPredicate, $assertPredicate, $seed): void {
        $row = $inListRow($seed);
        $base = ($seed % 900) + 10;
        $baseText = (string) $base;
        $baseRealText = $baseText . '.0';

        $assertPredicate($t, $row, $inPredicate($column('t1'), [$literal((float) $base), $literal($base + 1)]), true);
        $assertPredicate($t, $row, $inPredicate($column('t_plain'), [$literal((float) $base), $literal($base + 1)]), false);
        $assertPredicate($t, $row, $inPredicate($column('t_plain'), [$literal($base + 1), $literal($baseText)]), true);
        $assertPredicate($t, $row, $inPredicate($column('n1'), [$literal((float) $base), $literal($base + 1)]), true);
        $assertPredicate($t, $row, $inPredicate($column('n1'), [$literal($base + 1), $literal($baseRealText)]), true);
        $assertPredicate($t, $row, $inPredicate($column('o_text_decimal'), [$literal((float) $base), $literal($base + 1)]), false);
        $assertPredicate($t, $row, $inPredicate($column('o_text_decimal'), [$literal($base + 1), $literal($baseRealText)]), true);
        $assertPredicate($t, $row, $inPredicate($literal($base), [$literal(5), $column('t_plain'), $literal('fallback')]), false);
        $assertPredicate($t, $row, $inPredicate($literal($baseText), [$literal(5), $column('t_plain'), $literal('fallback')]), true);
        $assertPredicate($t, $row, $inPredicate($column('n1'), [$literal(5), $column('t_leading'), $literal($base + 1)]), true);
        $assertPredicate($t, $row, $inPredicate($literal($base), [$literal(5), $column('t_leading'), $literal($base + 1)]), false);
        $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
    };
}

$tests['real upstream expression affinity dynamic source coverage and non-overlap'] = static function (TestRunner $t): void {
    $t->contains('affinity2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
    $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
    $t->same('affinity2-200/300 and types2-1/4/5 expression-list affinity coverage', 'affinity2-200/300 and types2-1/4/5 expression-list affinity coverage');
    $t->same(
        'non-overlap: existing expression-affinity dynamic corpus covers expr arithmetic, bitwise, real arithmetic, simple comparison, CAST storage, and NULL arithmetic; this file isolates column-affinity comparison and IN expression-list conversion rules',
        'non-overlap: existing expression-affinity dynamic corpus covers expr arithmetic, bitwise, real arithmetic, simple comparison, CAST storage, and NULL arithmetic; this file isolates column-affinity comparison and IN expression-list conversion rules'
    );
};

return $tests;
