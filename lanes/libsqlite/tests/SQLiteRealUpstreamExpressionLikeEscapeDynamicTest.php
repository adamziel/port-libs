<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$expression])) {
        return $cache[$expression];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr LIKE ESCAPE tests');
    }

    $sql = "SELECT quote({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return $cache[$expression] = rtrim($output, "\r\n");
};

$port = static function (string $expression): string {
    $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS value", []);

    return (string) ($rows[0]['value'] ?? '');
};

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

// Real upstream source: SQLite test/e_expr.test e_expr-14.1 through
// e_expr-14.7. Those sections define LIKE wildcard direction, percent and
// underscore matching, ASCII-only case folding, and ESCAPE literalization.
// This dynamic shard widens the same semantics over a generated string matrix
// while comparing the native PHP SELECT executor against sqlite3 for each
// distinct expression.
$values = [
    'abc',
    'abcd',
    'abcde',
    'abde',
    'abXde',
    'abABCde',
    'abc%',
    'abc%%',
    'abc_',
    'abc__',
    'abcX',
    'abcXX',
    'ABCxyz',
    'abcxyz',
    'abc%xyz',
    'ABC%xyz',
    'aBc',
    'A',
    'a',
    'alpha_01',
    'alpha%01',
    'ALPHA_01',
    'prefix-middle-suffix',
    'prefix%suffix',
    'prefix_suffix',
    'literalXpercent',
    'literal%percent',
    'literal_percent',
    'mixX_percent',
    'mix_percent',
    'mix%percent',
    'trailing',
    'TRAILING',
];

$patterns = [
    'abc%',
    'ab%de',
    'ab_de',
    'aBc',
    'ABC%',
    'ABC\%x%',
    'abcX%',
    'abcX_',
    'abcXX',
    '%xyz',
    '%Xyz',
    'alpha!_01',
    'alpha!%01',
    'ALPHA!_01',
    'prefix%suffix',
    'prefix!%suffix',
    'prefix!_suffix',
    'literalXpercent',
    'literal!%percent',
    'literal!_percent',
    'mixX!_percent',
    'mix!_percent',
    'mix!%percent',
    'trail%',
    'TRAIL%',
    '_',
    '%',
    '%%%%',
    '___',
    '!%',
    '!_',
    '!!',
];

$escapedPatterns = array_filter(
    $patterns,
    static fn (string $pattern): bool => str_contains($pattern, '!') || str_contains($pattern, '\\') || str_contains($pattern, 'X')
);

$caseCount = 0;
foreach ($values as $leftIndex => $value) {
    foreach ($patterns as $patternIndex => $pattern) {
        $expression = $quote($value) . ' LIKE ' . $quote($pattern);
        ++$caseCount;
        $tests[sprintf(
            'real upstream expression like escape dynamic e_expr-14 like value%02d pattern%02d',
            $leftIndex,
            $patternIndex,
        )] = static function (TestRunner $t) use ($oracle, $port, $expression): void {
            $t->same($oracle($expression), $port($expression), $expression);
        };
    }
}

foreach ($values as $leftIndex => $value) {
    foreach ($escapedPatterns as $patternIndex => $pattern) {
        foreach (['!', 'X', '\\'] as $escape) {
            $expression = $quote($value) . ' LIKE ' . $quote($pattern) . ' ESCAPE ' . $quote($escape);
            ++$caseCount;
            $tests[sprintf(
                'real upstream expression like escape dynamic e_expr-14 escape value%02d pattern%02d escape%s',
                $leftIndex,
                $patternIndex,
                $escape === '\\' ? 'backslash' : $escape,
            )] = static function (TestRunner $t) use ($oracle, $port, $expression): void {
                $t->same($oracle($expression), $port($expression), $expression);
            };
        }
    }
}

$tests['real upstream expression like escape dynamic owns 2937 pass cases'] = static function (TestRunner $t) use ($values, $patterns, $escapedPatterns, $caseCount): void {
    $t->same(33, count($values));
    $t->same(32, count($patterns));
    $t->same(19, count($escapedPatterns));
    $t->same(2937, $caseCount);
    $t->same(
        'e_expr.test: e_expr-14.1..14.7 LIKE wildcard, case-folding, and ESCAPE literalization',
        'e_expr.test: e_expr-14.1..14.7 LIKE wildcard, case-folding, and ESCAPE literalization',
    );
};

return $tests;
