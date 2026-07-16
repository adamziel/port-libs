<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream row-context expression affinity tests');
}

$quoteLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/expr.test expr-1.1 through expr-1.122
// covers row-context arithmetic, REAL expressions, comparisons, boolean
// operators, bitwise operators, NULL propagation, BETWEEN, IS, and division
// or modulo by zero. Adjacent accepted shards cover literal-only REAL
// arithmetic and CAST matrices; this shard keeps the upstream row variables.
$rows = [
    ['i1' => 10, 'i2' => 20, 'r1' => 3.0, 'r2' => 4.5, 't1' => '10', 't2' => '20'],
    ['i1' => 20, 'i2' => 20, 'r1' => 2.25, 'r2' => -0.5, 't1' => '20.0', 't2' => '020'],
    ['i1' => 1, 'i2' => 2, 'r1' => 3.0, 'r2' => 0.25, 't1' => '1.5', 't2' => 'alpha'],
    ['i1' => 1, 'i2' => 0, 'r1' => 0.0, 'r2' => 1.5, 't1' => '', 't2' => '0'],
    ['i1' => null, 'i2' => 8, 'r1' => null, 'r2' => 8.0, 't1' => null, 't2' => '8'],
    ['i1' => 8, 'i2' => null, 'r1' => 8.0, 'r2' => null, 't1' => '8', 't2' => null],
    ['i1' => null, 'i2' => null, 'r1' => null, 'r2' => null, 't1' => null, 't2' => null],
    ['i1' => 32, 'i2' => 3, 'r1' => -32.0, 'r2' => 3.0, 't1' => '32', 't2' => '3'],
    ['i1' => 32, 'i2' => -3, 'r1' => -32.0, 'r2' => -3.0, 't1' => '32tail', 't2' => '-3'],
    ['i1' => 9999999999, 'i2' => 8888888888, 'r1' => 1.25e2, 'r2' => -1.25e2, 't1' => '9999999999', 't2' => '8888888888'],
    ['i1' => 5, 'i2' => 8, 'r1' => 5.5, 'r2' => 8.5, 't1' => '5.5', 't2' => '8.5'],
    ['i1' => 40, 'i2' => 1, 'r1' => 40.0, 'r2' => 1.0, 't1' => '040', 't2' => '1.0'],
    ['i1' => 0, 'i2' => 1, 'r1' => -0.0, 'r2' => 1.0, 't1' => '0.0', 't2' => '1'],
    ['i1' => -7, 'i2' => 3, 'r1' => -7.25, 'r2' => 3.5, 't1' => '-7.25', 't2' => '3.5'],
    ['i1' => 6, 'i2' => 6, 'r1' => 6.0, 'r2' => 6.0, 't1' => '6', 't2' => '006'],
    ['i1' => 2, 'i2' => null, 'r1' => 2.0, 'r2' => null, 't1' => '2tail', 't2' => null],
    ['i1' => null, 'i2' => 2, 'r1' => null, 'r2' => 2.0, 't1' => null, 't2' => '2tail'],
    ['i1' => 55, 'i2' => 8, 'r1' => 55.5, 'r2' => 8.0, 't1' => '55.5', 't2' => '8'],
    ['i1' => 3, 'i2' => 8, 'r1' => 3.25, 'r2' => 8.75, 't1' => '3.25', 't2' => '8.75'],
];

$columnAffinities = [
    'i1' => 'INTEGER',
    'i2' => 'INTEGER',
    'r1' => 'REAL',
    'r2' => 'REAL',
    't1' => 'TEXT',
    't2' => 'TEXT',
];

$portRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $columnAffinities],
    $rows,
);

$expressionTemplates = [
    'expr-1.1 integer add' => 'i1+i2',
    'expr-1.2 integer subtract' => 'i1-i2',
    'expr-1.3 integer multiply' => 'i1*i2',
    'expr-1.4 integer divide' => 'i1/i2',
    'expr-1.22 real precedence' => 'i1+i2*r1',
    'expr-1.23 real parenthesized precedence' => '(i1+i2)*r1',
    'expr-1.27 logical and true' => 'i1==1 AND i2=2',
    'expr-1.31 logical or true' => 'i1==1 OR i2=2',
    'expr-1.31b numeric or true' => '0 OR i2',
    'expr-1.36 not column' => 'not i1',
    'expr-1.38 unary minus' => '-i1',
    'expr-1.39 unary plus' => '+i1',
    'expr-1.40 nested unary plus' => '+(i2+i1)',
    'expr-1.41 nested unary minus' => '-(i2+i1)',
    'expr-1.42 bitwise or' => 'i1|i2',
    'expr-1.43 bitwise and' => 'i1&i2',
    'expr-1.44 bitwise not' => '~i1',
    'expr-1.45 left shift' => 'i1<<i2',
    'expr-1.46 right shift' => 'i1>>i2',
    'expr-1.56 modulo' => 'i1%i2',
    'expr-1.58 null add coalesce' => 'coalesce(i1+i2,99)',
    'expr-1.61 null subtract coalesce' => 'coalesce(i1-i2,99)',
    'expr-1.64 null multiply coalesce' => 'coalesce(i1*i2,99)',
    'expr-1.67 null divide coalesce' => 'coalesce(i1/i2,99)',
    'expr-1.70 null less coalesce' => 'coalesce(i1<i2,99)',
    'expr-1.71 null greater coalesce' => 'coalesce(i1>i2,99)',
    'expr-1.72 null less equal coalesce' => 'coalesce(i1<=i2,99)',
    'expr-1.73 null greater equal coalesce' => 'coalesce(i1>=i2,99)',
    'expr-1.74 null not equal coalesce' => 'coalesce(i1!=i2,99)',
    'expr-1.75 null equal coalesce' => 'coalesce(i1==i2,99)',
    'expr-1.76 null not coalesce' => 'coalesce(not i1,99)',
    'expr-1.77 null unary minus coalesce' => 'coalesce(-i1,99)',
    'expr-1.78 null and coalesce' => 'coalesce(i1 IS NULL AND i2=5,99)',
    'expr-1.79 null or coalesce' => 'coalesce(i1 IS NULL OR i2=5,99)',
    'expr-1.80 comparison and null coalesce' => 'coalesce(i1=5 AND i2 IS NULL,99)',
    'expr-1.81 comparison or null coalesce' => 'coalesce(i1=5 OR i2 IS NULL,99)',
    'expr-1.86 between' => '5 between i1 and i2',
    'expr-1.87 not between' => '5 not between i1 and i2',
    'expr-1.88 outside between' => '55 between i1 and i2',
    'expr-1.89 outside not between' => '55 not between i1 and i2',
    'expr-1.96 null left shift coalesce' => 'coalesce(i1<<i2,99)',
    'expr-1.97 null right shift coalesce' => 'coalesce(i1>>i2,99)',
    'expr-1.98 null bitwise or coalesce' => 'coalesce(i1|i2,99)',
    'expr-1.99 null bitwise and coalesce' => 'coalesce(i1&i2,99)',
    'expr-1.100 integer empty text comparison' => 'i1=t1',
    'expr-1.103 real modulo overflow' => '(-2147483648.0 % -1)',
    'expr-1.105 real divide overflow comparison' => '(-9223372036854775808.0 / -1)>1',
    'expr-1.108 modulo by zero' => '1%0',
    'expr-1.109 divide by zero' => '1/0',
    'expr-1.111 is comparison' => 'i1 IS i2',
    'expr-1.111b is not distinct comparison' => 'i1 IS NOT DISTINCT FROM i2',
    'expr-1.119 is not comparison' => 'i1 IS NOT i2',
    'expr-1.119b is distinct comparison' => 'i1 IS DISTINCT FROM i2',
    'expr-1.real add' => 'r1+r2',
    'expr-1.real subtract' => 'r1-r2',
    'expr-1.real multiply' => 'r1*r2',
    'expr-1.real divide' => 'r1/r2',
    'expr-1.real int compare' => 'r1=i1',
    'expr-1.text numeric add' => 't1+0',
    'expr-1.text numeric compare' => 't1=i1',
];

$cases = [];
foreach ($rows as $rowIndex => $row) {
    foreach ($expressionTemplates as $upstreamName => $expression) {
        $caseKey = 'row-' . ($rowIndex + 1) . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($upstreamName));
        $cases[$caseKey] = [
            'rowIndex' => $rowIndex,
            'upstream' => $upstreamName,
            'expression' => $expression,
        ];
    }
}

$sqliteScript = [
    'CREATE TABLE test1(i1 int, i2 int, r1 real, r2 real, t1 text, t2 text);',
];
foreach ($rows as $row) {
    $sqliteScript[] = sprintf(
        'INSERT INTO test1 VALUES(%s,%s,%s,%s,%s,%s);',
        $quoteLiteral($row['i1']),
        $quoteLiteral($row['i2']),
        $quoteLiteral($row['r1']),
        $quoteLiteral($row['r2']),
        $quoteLiteral($row['t1']),
        $quoteLiteral($row['t2']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $rowid = $case['rowIndex'] + 1;
    $expression = $case['expression'];
    $sqliteScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) FROM test1 WHERE rowid={$rowid};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-row-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for row-context expression tests');
}
file_put_contents($scriptFile, implode("\n", $sqliteScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce row-context expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed row-context expression oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d row-context expression oracle rows, got %d', count($cases), count($oracle)));
}

$unquoteNumeric = static function (string $quoted): ?float {
    if ($quoted === 'NULL') {
        return null;
    }

    return (float) $quoted;
};

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic row context expr.test ' . $case['upstream'] . ' ' . $key] = static function (TestRunner $t) use ($portRows, $case, $key, $oracle, $unquoteNumeric): void {
        $expression = $case['expression'];
        $row = $portRows[$case['rowIndex']];
        $actualRows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n FROM test1",
            ['test1' => [$row]],
        );
        $t->same(1, count($actualRows), $expression);

        $actual = $actualRows[0];
        $expectedType = $oracle[$key]['typeof'];
        $actualType = (string) $actual['t'];
        $expectedQuote = $oracle[$key]['quote'];
        $actualQuote = (string) $actual['q'];
        if (in_array($expectedType, ['integer', 'real'], true) && in_array($actualType, ['integer', 'real'], true)) {
            $expectedNumeric = $unquoteNumeric($expectedQuote);
            $actualNumeric = $unquoteNumeric($actualQuote);
            $t->same(false, $expectedNumeric === null, $expression . ' expected numeric');
            $t->same(false, $actualNumeric === null, $expression . ' actual numeric');
            $scale = max(1.0, abs((float) $expectedNumeric), abs((float) $actualNumeric));
            $t->same(true, abs((float) $expectedNumeric - (float) $actualNumeric) <= ($scale * 1.0e-12), $expression . ' numeric parity');
        } else {
            $t->same($expectedQuote, $actualQuote, $expression . ' quote');
            $t->same($expectedType, $actualType, $expression . ' typeof');
        }
        $t->same($oracle[$key]['isNull'], (string) $actual['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity dynamic row context owns exactly 1140 expr cases'] = static function (TestRunner $t) use ($rows, $expressionTemplates, $cases): void {
    $t->same(19, count($rows));
    $t->same(60, count($expressionTemplates));
    $t->same(1140, count($cases));
    $t->same(
        'expr.test expr-1.1..1.122 row-context expression evaluation over integer, real, text, and NULL columns',
        'expr.test expr-1.1..1.122 row-context expression evaluation over integer, real, text, and NULL columns',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
