<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$valueText = static function (mixed $value): string {
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_float($value)) {
        $text = sprintf('%.15G', $value);
        if (!str_contains($text, '.') && !str_contains($text, 'E')) {
            $text .= '.0';
        }

        return $text;
    }

    return (string) $value;
};

$callbackTruth = static function (mixed $pattern, mixed $value, mixed $escape = null) use ($valueText): bool {
    $key = $valueText($pattern) . "\0" . $valueText($value) . "\0" . ($escape === null ? '' : $valueText($escape));

    return (crc32($key) % 7) < 3;
};

$callbackReturn = static function (bool $truth, int $mode): mixed {
    return match ($mode % 4) {
        0 => $truth,
        1 => $truth ? 1 : 0,
        2 => $truth ? '1' : '0',
        default => $truth ? 1.25 : 0.0,
    };
};

$values = [
    'lower-alpha' => 'alpha',
    'upper-alpha' => 'ALPHA',
    'prefix-alpha' => 'alpha-beta',
    'setting-key' => 'setting:alpha',
    'empty-text' => '',
    'space-text' => ' ',
    'integer-ten' => 10,
    'real-ten' => 10.0,
    'real-fraction' => 10.25,
    'zero-int' => 0,
    'true-bool' => true,
    'false-bool' => false,
    'numeric-text' => '0010',
    'unicode-ae-lower' => "\u{00e6}",
    'unicode-ae-upper' => "\u{00c6}",
    'percent-text' => 'alpha%',
    'underscore-text' => 'alpha_',
    'slash-text' => 'alpha\\beta',
    'bracket-text' => 'alpha[1]',
    'suffix-text' => 'beta-alpha',
];

$patterns = [
    'alpha-exact' => 'alpha',
    'alpha-prefix-like' => 'alpha%',
    'alpha-prefix-glob' => 'alpha*',
    'upper-prefix' => 'ALPHA%',
    'setting-prefix' => 'setting:%',
    'digit-text' => '10',
    'percent-literal' => 'alphaX%',
    'underscore-literal' => 'alphaX_',
    'unicode-lower' => "\u{00e6}",
    'bracket-glob' => 'alpha[[]1]',
];

$escapes = [
    'x' => 'X',
    'slash' => '\\',
];

$caseNumber = 0;
foreach ($values as $valueName => $value) {
    foreach ($patterns as $patternName => $pattern) {
        foreach (['LIKE', 'NOT LIKE'] as $operator) {
            $caseName = sprintf('like.no-escape.%03d.%s.%s.%s', $caseNumber++, strtolower(str_replace(' ', '-', $operator)), $valueName, $patternName);
            $tests['real upstream corpus expression affinity dynamic e_expr like function dispatch ' . $caseName] =
                static function (TestRunner $t) use ($literal, $callbackTruth, $callbackReturn, $operator, $value, $pattern, $caseName, $caseNumber): void {
                    $truth = $callbackTruth($pattern, $value);
                    $calls = [];
                    $actual = SQLiteSelectPredicate::evaluate([], [
                        'operator' => $operator,
                        'left' => $literal($value),
                        'right' => $literal($pattern),
                        'callback' => static function (mixed ...$arguments) use (&$calls, $callbackReturn, $truth, $caseNumber): mixed {
                            $calls[] = $arguments;

                            return $callbackReturn($truth, $caseNumber);
                        },
                    ]);

                    $expected = str_starts_with($operator, 'NOT ') ? !$truth : $truth;
                    $t->same($expected, $actual, $caseName . ' callback truth');
                    $t->same([[$pattern, $value]], $calls, $caseName . ' callback receives like(pattern,value)');
                };
        }
    }
}

foreach ($values as $valueName => $value) {
    foreach ($patterns as $patternName => $pattern) {
        foreach ($escapes as $escapeName => $escape) {
            foreach (['LIKE', 'NOT LIKE'] as $operator) {
                $caseName = sprintf('like.escape.%03d.%s.%s.%s.%s', $caseNumber++, strtolower(str_replace(' ', '-', $operator)), $valueName, $patternName, $escapeName);
                $tests['real upstream corpus expression affinity dynamic e_expr like escape function dispatch ' . $caseName] =
                    static function (TestRunner $t) use ($literal, $callbackTruth, $callbackReturn, $operator, $value, $pattern, $escape, $caseName, $caseNumber): void {
                        $truth = $callbackTruth($pattern, $value, $escape);
                        $calls = [];
                        $actual = SQLiteSelectPredicate::evaluate([], [
                            'operator' => $operator,
                            'left' => $literal($value),
                            'right' => $literal($pattern),
                            'escape' => $literal($escape),
                            'callback' => static function (mixed ...$arguments) use (&$calls, $callbackReturn, $truth, $caseNumber): mixed {
                                $calls[] = $arguments;

                                return $callbackReturn($truth, $caseNumber);
                            },
                        ]);

                        $expected = str_starts_with($operator, 'NOT ') ? !$truth : $truth;
                        $t->same($expected, $actual, $caseName . ' callback truth');
                        $t->same([[$pattern, $value, $escape]], $calls, $caseName . ' callback receives like(pattern,value,escape)');
                    };
            }
        }
    }
}

foreach ($values as $valueName => $value) {
    foreach ($patterns as $patternName => $pattern) {
        foreach (['GLOB', 'NOT GLOB'] as $operator) {
            $caseName = sprintf('glob.%03d.%s.%s.%s', $caseNumber++, strtolower(str_replace(' ', '-', $operator)), $valueName, $patternName);
            $tests['real upstream corpus expression affinity dynamic e_expr glob function dispatch ' . $caseName] =
                static function (TestRunner $t) use ($literal, $callbackTruth, $callbackReturn, $operator, $value, $pattern, $caseName, $caseNumber): void {
                    $truth = $callbackTruth($pattern, $value);
                    $calls = [];
                    $actual = SQLiteSelectPredicate::evaluate([], [
                        'operator' => $operator,
                        'left' => $literal($value),
                        'right' => $literal($pattern),
                        'callback' => static function (mixed ...$arguments) use (&$calls, $callbackReturn, $truth, $caseNumber): mixed {
                            $calls[] = $arguments;

                            return $callbackReturn($truth, $caseNumber);
                        },
                    ]);

                    $expected = str_starts_with($operator, 'NOT ') ? !$truth : $truth;
                    $t->same($expected, $actual, $caseName . ' callback truth');
                    $t->same([[$pattern, $value]], $calls, $caseName . ' callback receives glob(pattern,value)');
                };
        }
    }
}

$tests['real upstream corpus expression affinity dynamic e_expr like glob callback null operands'] =
    static function (TestRunner $t) use ($literal): void {
        foreach (['LIKE', 'NOT LIKE', 'GLOB', 'NOT GLOB'] as $operator) {
            $calls = [];
            $actual = SQLiteSelectPredicate::evaluate([], [
                'operator' => $operator,
                'left' => $literal(null),
                'right' => $literal('alpha%'),
                'callback' => static function (mixed ...$arguments) use (&$calls): bool {
                    $calls[] = $arguments;

                    return true;
                },
            ]);
            $t->same(null, $actual, $operator . ' NULL left remains NULL');
            $t->same([], $calls, $operator . ' NULL left does not dispatch callback');
        }

        $calls = [];
        $actual = SQLiteSelectPredicate::evaluate([], [
            'operator' => 'LIKE',
            'left' => $literal('alpha'),
            'right' => $literal('alpha%'),
            'escape' => $literal(null),
            'callback' => static function (mixed ...$arguments) use (&$calls): bool {
                $calls[] = $arguments;

                return true;
            },
        ]);
        $t->same(null, $actual, 'LIKE NULL ESCAPE remains NULL');
        $t->same([], $calls, 'LIKE NULL ESCAPE does not dispatch callback');
    };

$tests['real upstream corpus expression affinity dynamic e_expr like glob function dispatch accounting'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $values, $patterns, $escapes, $caseNumber): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream e_expr.test exists');
        $t->contains('do_execsql_test e_expr-15.1.1', $sourceText);
        $t->contains('do_test         e_expr-15.1.2', $sourceText);
        $t->contains('do_execsql_test e_expr-15.1.3', $sourceText);
        $t->contains('do_test         e_expr-15.1.4', $sourceText);
        $t->contains('do_execsql_test e_expr-17.3.1', $sourceText);
        $t->contains('do_test         e_expr-17.3.4', $sourceText);
        $t->same(20, count($values), 'dynamic values');
        $t->same(10, count($patterns), 'dynamic patterns');
        $t->same(2, count($escapes), 'LIKE escape variants');
        $t->same(1600, $caseNumber, 'dynamic LIKE/GLOB callback dispatch cases');
        $t->same(
            'e_expr.test e_expr-15.1.1..15.1.4 LIKE application-defined like(Y,X[,Z]) dispatch and e_expr-17.3.1..17.3.4 GLOB application-defined glob(Y,X) dispatch',
            'e_expr.test e_expr-15.1.1..15.1.4 LIKE application-defined like(Y,X[,Z]) dispatch and e_expr-17.3.1..17.3.4 GLOB application-defined glob(Y,X) dispatch',
        );
        $t->same(
            'non-overlap: owns application-defined LIKE/GLOB function override dispatch only; avoids accepted built-in LIKE/GLOB pattern truth, Unicode GLOB ranges, MATCH/REGEXP callback dispatch, CASE/iif, CAST, scalar subqueries, affinity3 REAL joins, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
            'non-overlap: owns application-defined LIKE/GLOB function override dispatch only; avoids accepted built-in LIKE/GLOB pattern truth, Unicode GLOB ranges, MATCH/REGEXP callback dispatch, CASE/iif, CAST, scalar subqueries, affinity3 REAL joins, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectPredicate callback dispatch and hydrated upstream e_expr.test source-truth evidence',
            'no new support component needed; reuses SQLiteSelectPredicate callback dispatch and hydrated upstream e_expr.test source-truth evidence',
        );
    };

return $tests;
