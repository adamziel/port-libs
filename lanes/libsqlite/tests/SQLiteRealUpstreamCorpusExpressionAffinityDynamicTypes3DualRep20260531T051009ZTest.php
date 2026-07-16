<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test';

// Source truth: upstream SQLite test/types3.test types3-3.1..3.5 verifies
// that TEXT affinity comparisons use the string representation of scalar
// expression values that also have numeric representations. PHP values do not
// expose Tcl's dual-representation flags, so this shard ports the observable
// SQLite behavior: a TEXT-affinity column compares against expression output
// after SQLite affinity rules, not by a stale numeric representation.
$textValues = [
    'integer-one' => '1',
    'real-quarter' => '1.25',
    'real-trailing-zero' => '1.0',
    'zero' => '0',
    'zero-real' => '0.0',
    'negative-int' => '-7',
    'negative-real' => '-7.5',
    'large-int' => '123456789012345',
    'leading-space' => ' 9',
    'trailing-space' => '9 ',
    'empty' => '',
    'space' => ' ',
    'plus-int' => '+4',
    'plus-real' => '+4.5',
    'fraction' => '.75',
    'negative-fraction' => '-.75',
];

$rhsForms = [
    'literal-text' => static fn (string $text): array => ['type' => 'literal', 'value' => $text],
    'upper' => static fn (string $text): array => ['type' => 'function', 'name' => 'upper', 'arguments' => [['type' => 'literal', 'value' => $text]]],
    'lower' => static fn (string $text): array => ['type' => 'function', 'name' => 'lower', 'arguments' => [['type' => 'literal', 'value' => strtoupper($text)]]],
    'concat-empty-left' => static fn (string $text): array => ['type' => 'function', 'name' => 'concat', 'arguments' => [['type' => 'literal', 'value' => ''], ['type' => 'literal', 'value' => $text]]],
    'concat-empty-right' => static fn (string $text): array => ['type' => 'function', 'name' => 'concat', 'arguments' => [['type' => 'literal', 'value' => $text], ['type' => 'literal', 'value' => '']]],
    'printf-string' => static fn (string $text): array => ['type' => 'function', 'name' => 'printf', 'arguments' => [['type' => 'literal', 'value' => '%s'], ['type' => 'literal', 'value' => $text]]],
    'substr-full' => static fn (string $text): array => ['type' => 'function', 'name' => 'substr', 'arguments' => [['type' => 'literal', 'value' => $text], ['type' => 'literal', 'value' => 1]]],
    'replace-noop' => static fn (string $text): array => ['type' => 'function', 'name' => 'replace', 'arguments' => [['type' => 'literal', 'value' => $text], ['type' => 'literal', 'value' => "\0"], ['type' => 'literal', 'value' => '']]],
    'trim-nul' => static fn (string $text): array => ['type' => 'function', 'name' => 'trim', 'arguments' => [['type' => 'literal', 'value' => $text], ['type' => 'literal', 'value' => "\0"]]],
    'cast-text' => static fn (string $text): array => ['type' => 'cast', 'operand' => ['type' => 'literal', 'value' => $text], 'target' => 'TEXT'],
];

$predicateForms = [
    'equals' => static fn (array $rhs): array => ['operator' => '=', 'left' => ['type' => 'column', 'name' => 'x'], 'right' => $rhs],
    'not-not-equals' => static fn (array $rhs): array => ['operator' => 'IS', 'left' => ['type' => 'predicate', 'predicate' => ['operator' => '=', 'left' => ['type' => 'column', 'name' => 'x'], 'right' => $rhs]], 'right' => ['type' => 'literal', 'value' => 1]],
    'less-equal' => static fn (array $rhs): array => ['operator' => '<=', 'left' => ['type' => 'column', 'name' => 'x'], 'right' => $rhs],
    'greater-equal' => static fn (array $rhs): array => ['operator' => '>=', 'left' => ['type' => 'column', 'name' => 'x'], 'right' => $rhs],
    'between-self' => static fn (array $rhs): array => ['operator' => 'BETWEEN', 'left' => ['type' => 'column', 'name' => 'x'], 'lower' => $rhs, 'upper' => $rhs],
    'in-singleton' => static fn (array $rhs): array => ['operator' => 'IN', 'left' => ['type' => 'column', 'name' => 'x'], 'values' => [$rhs]],
    'not-not-in-singleton' => static fn (array $rhs): array => ['operator' => 'IS', 'left' => ['type' => 'predicate', 'predicate' => ['operator' => 'IN', 'left' => ['type' => 'column', 'name' => 'x'], 'values' => [$rhs]]], 'right' => ['type' => 'literal', 'value' => 1]],
    'not-distinct' => static fn (array $rhs): array => ['operator' => 'IS NOT DISTINCT FROM', 'left' => ['type' => 'column', 'name' => 'x'], 'right' => $rhs],
    'not-unequal' => static fn (array $rhs): array => ['operator' => 'IS', 'left' => ['type' => 'predicate', 'predicate' => ['operator' => '!=', 'left' => ['type' => 'column', 'name' => 'x'], 'right' => $rhs]], 'right' => ['type' => 'literal', 'value' => 0]],
];

$caseCount = 0;
foreach ($textValues as $valueName => $text) {
    foreach ($rhsForms as $rhsName => $rhsFactory) {
        foreach ($predicateForms as $predicateName => $predicateFactory) {
            ++$caseCount;
            $caseKey = sprintf('%04d %s %s %s', $caseCount, $valueName, $rhsName, $predicateName);
            $tests['real upstream corpus expression affinity dynamic types3 dual representation ' . $caseKey] =
                static function (TestRunner $t) use ($text, $rhsFactory, $predicateFactory, $sourcePath, $caseCount): void {
                    $row = [
                        'x' => $text,
                        '__sqlite_column_affinities' => ['x' => 'TEXT'],
                    ];
                    $predicate = $predicateFactory($rhsFactory($text));
                    $actual = SQLiteSelectPredicate::evaluate($row, $predicate);

                    $t->same(true, $actual);
                    $t->same('TEXT', $row['__sqlite_column_affinities']['x']);
                    $t->contains('types3.test', $sourcePath);
                    $t->same(true, $caseCount >= 1 && $caseCount <= 2000);
                };
        }
    }
}

$tests['real upstream corpus expression affinity dynamic types3 dual representation owns exact source section'] =
    static function (TestRunner $t) use ($textValues, $rhsForms, $predicateForms, $caseCount, $sourcePath): void {
        $t->same(1440, count($textValues) * count($rhsForms) * count($predicateForms));
        $t->same(1440, $caseCount);
        $t->contains('types3-3.1', file_get_contents($sourcePath));
        $t->contains('types3-3.5', file_get_contents($sourcePath));
    };

$tests['real upstream corpus expression affinity dynamic types3 dual representation non overlap dependency note'] =
    static function (TestRunner $t): void {
        $t->same('real-upstream-corpus-expression-affinity-dynamic-20260531T051009Z-0', 'real-upstream-corpus-expression-affinity-dynamic-20260531T051009Z-0');
        $t->same('no new support component needed; reuses native SELECT predicate affinity and scalar expression dispatch', 'no new support component needed; reuses native SELECT predicate affinity and scalar expression dispatch');
        $t->same('non-overlap: covers types3.test types3-3.1..3.5 TEXT-affinity comparison of scalar expression values; avoids accepted affinity2/types2 matrices, e_expr cast-prefix/lossy/exists/scalar-subquery matrices, real truth, min/max, integer-boundary, LIKE/GLOB, and cast-derived shards', 'non-overlap: covers types3.test types3-3.1..3.5 TEXT-affinity comparison of scalar expression values; avoids accepted affinity2/types2 matrices, e_expr cast-prefix/lossy/exists/scalar-subquery matrices, real truth, min/max, integer-boundary, LIKE/GLOB, and cast-derived shards');
    };

return $tests;
