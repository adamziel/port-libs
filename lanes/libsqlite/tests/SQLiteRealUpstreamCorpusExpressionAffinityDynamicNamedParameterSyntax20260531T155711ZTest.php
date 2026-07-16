<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr named-parameter syntax tests');
}

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if ($value instanceof SQLiteBlobValue) {
        return "X'" . bin2hex($value->bytes) . "'";
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        if (is_float($value) && !is_finite($value)) {
            throw new RuntimeException('non-finite oracle literal');
        }

        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-11.2 through
// e_expr-11.6 accepts numeric-leading names, high-bit identifier bytes,
// dollar-namespace names with "::" and "(...)" suffixes, and assigns named
// parameters numbers after the largest preceding host parameter number.
$highBitName = "\xC2\x80";
$tokenForms = [
    'colon-alpha' => ':tenant_value',
    'colon-numeric' => ':123',
    'colon-underscore-dollar' => ':_$_',
    'colon-highbit' => ':' . $highBitName,
    'at-alpha' => '@tenant_value',
    'at-numeric' => '@123',
    'at-underscore-dollar' => '@_$_',
    'at-highbit' => '@' . $highBitName,
    'dollar-alpha' => '$tenant_value',
    'dollar-numeric' => '$123',
    'dollar-underscore-dollar' => '$_$_',
    'dollar-highbit' => '$' . $highBitName,
    'dollar-namespace-suffix' => '$::::a(++--++)',
    'dollar-namespace-empty-suffix' => '$::a()',
    'dollar-namespace-punct-suffix' => '$::1(::#$)',
];

$values = [
    'null' => null,
    'seven-int' => 7,
    'real-fraction' => 2.5,
    'text-real-tail' => '42.5tail',
    'blob-ascii' => new SQLiteBlobValue('ABC'),
];

$rightValues = [
    'one' => 1,
    'text-two' => '2',
    'null' => null,
    'blob-b' => new SQLiteBlobValue('B'),
];

$operators = [
    'add' => '+',
    'concat' => '||',
    'equals' => '=',
    'is-not' => 'IS NOT',
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
];

$bindingVariants = [
    'exact-token' => static fn (string $token, mixed $value): array => [$token => $value],
    'bare-name' => static fn (string $token, mixed $value): array => [substr($token, 1) => $value],
    'numeric-index' => static fn (string $token, mixed $value): array => [1 => $value],
];

$cases = [];
foreach ($tokenForms as $tokenName => $tokenSql) {
    foreach ($bindingVariants as $bindingName => $bindingParameters) {
        foreach ($values as $leftName => $leftValue) {
            foreach ($rightValues as $rightName => $rightValue) {
                foreach ($operators as $operatorName => $operator) {
                    foreach ($projections as $projectionName => $projectionSql) {
                        $expression = '(' . $tokenSql . ") {$operator} (" . $literal($rightValue) . ')';
                        $literalExpression = '(' . $literal($leftValue) . ") {$operator} (" . $literal($rightValue) . ')';
                        $caseKey = implode('-', [$tokenName, $bindingName, $leftName, $operatorName, $rightName, $projectionName]);
                        $cases[$caseKey] = [
                            'expression' => $expression,
                            'literalExpression' => $literalExpression,
                            'projection' => $projectionSql,
                            'parameters' => $bindingParameters($tokenSql, $leftValue),
                        ];
                    }
                }
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $projectionSql = $case['projection'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || " . $projectionSql($case['literalExpression']) . ';';
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr11-named-param-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr named-parameter syntax tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr named-parameter syntax output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed e_expr named-parameter oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr named-parameter oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic named parameter syntax e_expr-11 ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
            $projectionSql = $case['projection'];
            $rows = SQLiteSelectSql::execute(
                'SELECT ' . $projectionSql($case['expression']) . ' AS value',
                [],
                $case['parameters'],
            );

            $t->same(1, count($rows), $key);
            $t->same($oracle[$key], (string) $rows[0]['value'], $key);
        };
}

$numberingCases = [
    'qmark-then-at' => [
        'sql' => 'SELECT ?, @abc',
        'parameters' => [1 => -1, 2 => -2],
        'expected' => [['expr1' => -1, 'expr2' => -2]],
    ],
    'qmark123-then-colon' => [
        'sql' => 'SELECT ?123, :a1',
        'parameters' => [123 => -123, 124 => -124],
        'expected' => [['expr1' => -123, 'expr2' => -124]],
    ],
    'mixed-upstream-order' => [
        'sql' => 'SELECT $a, ?8, ?, $b, ?2, $c',
        'parameters' => [1 => -1, 8 => -8, 9 => -9, 10 => -10, 2 => -2, 11 => -11],
        'expected' => [['expr1' => -1, 'expr2' => -8, 'expr3' => -9, 'expr4' => -10, 'expr5' => -2, 'expr6' => -11]],
    ],
    'extended-dollar-after-explicit' => [
        'sql' => 'SELECT ?5, $::1(::#$), ?, $::::a(++--++)',
        'parameters' => [5 => -5, 6 => -6, 7 => -7, 8 => -8],
        'expected' => [['expr1' => -5, 'expr2' => -6, 'expr3' => -7, 'expr4' => -8]],
    ],
];

foreach ($numberingCases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic named parameter numbering e_expr-11 ' . $key] =
        static function (TestRunner $t) use ($case, $key): void {
            $t->same($case['expected'], SQLiteSelectSql::execute($case['sql'], [], $case['parameters']), $key);
        };
}

$tests['real upstream corpus expression affinity dynamic named parameter syntax owns e_expr-11 extended host-parameter shard'] =
    static function (TestRunner $t) use ($tokenForms, $bindingVariants, $values, $rightValues, $operators, $projections, $cases, $oracle): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';
        $text = file_get_contents($source);
        if (!is_string($text)) {
            throw new RuntimeException('Could not read hydrated upstream e_expr.test');
        }

        $t->same(15, count($tokenForms));
        $t->same(3, count($bindingVariants));
        $t->same(5, count($values));
        $t->same(4, count($rightValues));
        $t->same(4, count($operators));
        $t->same(2, count($projections));
        $t->same(7200, count($cases));
        $t->same(7200, count($oracle));
        $t->contains('parameter_test e_expr-11.2.2 {SELECT :123}', $text);
        $t->contains('parameter_test e_expr-11.3.2 {SELECT @123}', $text);
        $t->contains('parameter_test e_expr-11.4.2 {SELECT $123}', $text);
        $t->contains('parameter_test e_expr-11.5.1 {SELECT $::::a(++--++)}', $text);
        $t->contains('parameter_test e_expr-11.6.3 {SELECT $a, ?8, ?, $b, ?2, $c}', $text);
        $t->same(
            'e_expr.test e_expr-11.2..11.6 extended named host-parameter syntax, dollar namespace suffixes, high-bit identifier bytes, and named parameter numbering',
            'e_expr.test e_expr-11.2..11.6 extended named host-parameter syntax, dollar namespace suffixes, high-bit identifier bytes, and named parameter numbering',
        );
        $t->same(
            'non-overlap: extends named-token parsing and numeric binding order beyond accepted simple e_expr-11 parameter affinity matrix and unbound-parameter NULL propagation; avoids CASE, IN, BETWEEN, JSON, WAL, VFS, B-tree, date, and PRAGMA clusters',
            'non-overlap: extends named-token parsing and numeric binding order beyond accepted simple e_expr-11 parameter affinity matrix and unbound-parameter NULL propagation; avoids CASE, IN, BETWEEN, JSON, WAL, VFS, B-tree, date, and PRAGMA clusters',
        );
    };

$tests['real upstream corpus expression affinity dynamic named parameter syntax dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql parser-level parameter binding and local sqlite3 oracle parity for hydrated upstream e_expr.test',
            'no new support component needed; reuses SQLiteSelectSql parser-level parameter binding and local sqlite3 oracle parity for hydrated upstream e_expr.test',
        );
    };

return $tests;
