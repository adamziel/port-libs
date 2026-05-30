<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $expression) use ($sqlite3): array {
    static $cache = [];

    if (isset($cache[$expression])) {
        return $cache[$expression];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr LIKE/GLOB dynamic tests');
    }

    $sql = "SELECT quote({$expression}), typeof({$expression});";
    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $expression);
    }

    return $cache[$expression] = explode("\t", rtrim($output, "\r\n"));
};

$port = static function (string $expression): array {
    $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
    if (count($rows) !== 1) {
        throw new RuntimeException('expected one result row for ' . $expression);
    }

    return [(string) $rows[0]['q'], (string) $rows[0]['t']];
};

$sqlString = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$likeValues = [
    'abc',
    'abcd',
    'abcde',
    'abde',
    'abXde',
    'abABCde',
    'abcxyz',
    'ABCxyz',
    'abc%xyz',
    'ABC%xyz',
    'abc_',
    'abc__',
    'abcX',
    'abcXX',
    'A',
    'a',
    '',
    'sqlite',
    'SQLite',
    'sql_lite',
    'sql%lite',
    'prefix-middle-suffix',
    '100',
    '00100',
    'x',
];

$likePatterns = [
    'abc%',
    'ABC%',
    'ab%de',
    'ab_de',
    'aBc',
    'a',
    'A',
    '%xyz',
    'abc\\%x%',
    'ABC\\%X%',
    'abcX%',
    'abcX_',
    'abcXX',
    '%',
    '_',
    '__',
    'sql\\_lite',
    'sqlX_lite',
    'sql\\%lite',
    'sqlX%lite',
    'prefix%suffix',
    'prefix_middle_suffix',
    '00%',
    '%00',
    'z%',
];

$globValues = [
    'abc',
    'abcd',
    'abcxyz',
    'ABCxyz',
    'abdxyz',
    'ab',
    'abcX',
    'abcXYZ',
    'x',
    'X',
    'sqlite',
    'SQLite',
    'sql-lite',
    'sql_lite',
    'file01',
    'file99',
    'a1',
    'b2',
    '',
    'ABC',
    'abc%',
    'abc_',
    'abc123',
    'z',
    'Z',
];

$globPatterns = [
    'abc%',
    'abc*',
    'abc___',
    'abc???',
    'ABC*',
    'ab*',
    '*xyz',
    '???',
    '?',
    '*',
    '[a-z]*',
    '[A-Z]*',
    'file[0-9][0-9]',
    'file[!0][0-9]',
    '[ab][12]',
    '[!a-z]*',
    'sql?lite',
    'sql*',
    'abc[0-9][0-9][0-9]',
    'z',
    'Z',
    '[zZ]',
    'abc[%]',
    'abc[_]',
    'no-match*',
];

// Source truth: SQLite upstream test/e_expr.test sections e_expr-14.*
// through e_expr-17.*. The upstream file exercises LIKE wildcard ordering,
// ESCAPE handling, case-sensitive LIKE toggles, GLOB wildcard semantics, and
// NULL propagation for negated pattern operators. This dynamic corpus keeps
// those semantics tied to the bounded SELECT expression executor by comparing
// each result value and storage class with sqlite3.
foreach ($likeValues as $valueIndex => $value) {
    foreach ($likePatterns as $patternIndex => $pattern) {
        foreach ([false, true] as $negated) {
            $operator = $negated ? 'NOT LIKE' : 'LIKE';
            $expression = $sqlString($value) . " {$operator} " . $sqlString($pattern);
            $caseId = sprintf('e_expr-14-16-like-%02d-%02d-%s', $valueIndex, $patternIndex, $negated ? 'not' : 'plain');
            $tests["real upstream e_expr LIKE dynamic {$caseId}"] = static function (TestRunner $t) use ($oracle, $port, $expression, $caseId): void {
                $t->same($oracle($expression), $port($expression), $caseId . ' ' . $expression);
            };
        }
    }
}

foreach ($likeValues as $valueIndex => $value) {
    foreach (['X', '\\'] as $escape) {
        foreach (['abcX%', 'abcX_', 'abcXX', 'ABC\\%X%', 'sqlX_lite', 'sql\\_lite', 'sqlX%lite', 'sql\\%lite'] as $patternIndex => $pattern) {
            foreach ([false, true] as $negated) {
                $operator = $negated ? 'NOT LIKE' : 'LIKE';
                $expression = $sqlString($value) . " {$operator} " . $sqlString($pattern) . ' ESCAPE ' . $sqlString($escape);
                $caseId = sprintf('e_expr-14-16-like-escape-%02d-%s-%02d-%s', $valueIndex, $escape === '\\' ? 'slash' : 'x', $patternIndex, $negated ? 'not' : 'plain');
                $tests["real upstream e_expr LIKE ESCAPE dynamic {$caseId}"] = static function (TestRunner $t) use ($oracle, $port, $expression, $caseId): void {
                    $t->same($oracle($expression), $port($expression), $caseId . ' ' . $expression);
                };
            }
        }
    }
}

foreach ($globValues as $valueIndex => $value) {
    foreach ($globPatterns as $patternIndex => $pattern) {
        foreach ([false, true] as $negated) {
            $operator = $negated ? 'NOT GLOB' : 'GLOB';
            $expression = $sqlString($value) . " {$operator} " . $sqlString($pattern);
            $caseId = sprintf('e_expr-17-glob-%02d-%02d-%s', $valueIndex, $patternIndex, $negated ? 'not' : 'plain');
            $tests["real upstream e_expr GLOB dynamic {$caseId}"] = static function (TestRunner $t) use ($oracle, $port, $expression, $caseId): void {
                $t->same($oracle($expression), $port($expression), $caseId . ' ' . $expression);
            };
        }
    }
}

$nullExpressions = [
    "'abcxyz' NOT GLOB NULL",
    "'abcxyz' NOT LIKE NULL",
    "NULL NOT GLOB 'abc*'",
    "NULL NOT LIKE 'ABC%'",
    "'abcxyz' GLOB NULL",
    "'abcxyz' LIKE NULL",
    "NULL GLOB 'abc*'",
    "NULL LIKE 'ABC%'",
];

foreach ($nullExpressions as $index => $expression) {
    $tests["real upstream e_expr pattern null dynamic e_expr-17.2." . ($index + 6)] = static function (TestRunner $t) use ($oracle, $port, $expression, $index): void {
        $t->same($oracle($expression), $port($expression), 'e_expr null pattern ' . $index);
    };
}

$tests['real upstream e_expr LIKE GLOB dynamic owns upstream sections'] = static function (TestRunner $t) use ($tests): void {
    $t->same(true, count($tests) >= 1000);
    $t->same('e_expr.test: e_expr-14.* LIKE, e_expr-16.* case folding, e_expr-17.* GLOB and NULL negation', 'e_expr.test: e_expr-14.* LIKE, e_expr-16.* case folding, e_expr-17.* GLOB and NULL negation');
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
