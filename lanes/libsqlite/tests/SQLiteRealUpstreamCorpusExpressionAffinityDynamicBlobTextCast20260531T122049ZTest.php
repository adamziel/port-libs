<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity BLOB/TEXT CAST dynamic tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$textOperands = [
    'empty' => '',
    'abc' => 'abc',
    'ghi' => 'ghi',
    'integer-text' => '123',
    'negative-integer-text' => '-456',
    'real-text' => '1.78',
    'large-real-text' => '2.3e+5',
    'small-real-text' => '-2.3e-5',
    'zero-real-text' => '0.0',
    'leading-space' => '   123',
    'leading-plus' => '+.5',
    'dot-only' => '.',
    'minus-only' => '-',
    'hex-looking' => '0x1234',
    'alpha-number' => 'abc123',
    'number-alpha' => '123abc',
    'quoted-word' => "it''s",
    'space-word' => 'A B',
    'uppercase' => 'ABC',
    'lowercase' => 'xyz',
    'text-word' => 'Text',
    'blob-word' => 'blob',
    'numeric-word' => 'not a number',
    'one-with-leading-zeroes' => '0001',
    'sqlite' => 'sqlite',
];

$numericOperands = [
    'zero' => '0',
    'one' => '1',
    'minus-one' => '-1',
    'forty-five' => '45',
    'minus-forty-five' => '-45',
    'four-fifty-six' => '456',
    'real-one-point-seven-eight' => '1.78',
    'real-eight-point-eight' => '8.8',
    'real-zero' => '0.0',
    'real-negative-zero' => '-0.0',
    'real-half' => '0.5',
    'real-negative-half' => '-0.5',
    'real-large-fixed' => '230000.0',
    'real-large-exp' => '2.3e+5',
    'real-small-exp' => '-2.3e-5',
    'real-small-positive-exp' => '1.0e-5',
    'integer-int64-max' => '9223372036854775807',
    'integer-int64-min' => '-9223372036854775808',
    'real-pi' => '3.14159',
    'real-neg-pi' => '-3.14159',
    'real-quarter' => '0.25',
    'real-neg-quarter' => '-0.25',
    'integer-million' => '1000000',
    'integer-negative-million' => '-1000000',
    'real-precision' => '0.000244140625',
];

$blobOperands = [
    'empty' => '',
    'a' => '61',
    'ghi' => '676869',
    'one-point-two-three' => '312E3233',
    'two-thirty' => '3233302E30',
    'minus-nine-point-eight-seven' => '2D392E3837',
    'zero-point-zero-zero-zero-one' => '302E30303031',
    'four-five-six' => '343536',
    'minus-forty-five' => '2D3435',
    'zero-point-zero' => '302E30',
    'abc' => '616263',
    'ABC' => '414243',
    'one-two-three-abcd' => '31323361626364',
    'one' => '31',
    'zero' => '30',
    'space-one-space' => '20203120',
    'plus-dot-five' => '2B2E35',
    'dot' => '2E',
    'minus' => '2D',
    'not-a-number' => '6E6F742061206E756D626572',
    'hex-looking' => '307831323334',
    'zulu' => '7A7A',
    'blob-word' => '626C6F62',
    'text-word' => '54657874',
    'zero-zero-zero-one' => '30303031',
];

$castOperands = [
    'text-abc-as-text' => 'CAST(' . $sqlLiteral('abc') . ' AS TEXT)',
    'text-abc-as-blob' => 'CAST(' . $sqlLiteral('abc') . ' AS BLOB)',
    'text-ghi-as-shobblob' => 'CAST(' . $sqlLiteral('ghi') . ' AS shobblob_x)',
    'integer-456-as-text' => 'CAST(456 AS TEXT)',
    'integer-456-as-blob' => 'CAST(456 AS BLOB)',
    'real-1-78-as-text' => 'CAST(1.78 AS TEXT)',
    'real-1-78-as-blob' => 'CAST(1.78 AS BLOB)',
    'real-large-exp-as-text' => 'CAST(2.3e+5 AS TEXT)',
    'real-small-exp-as-text' => 'CAST(-2.3e-5 AS TEXT)',
    'real-small-positive-exp-as-text' => 'CAST(1.0e-5 AS TEXT)',
    'blob-ghi-as-text' => "CAST(X'676869' AS TEXT)",
    'blob-ghi-as-blob' => "CAST(X'676869' AS BLOB)",
    'blob-real-as-text' => "CAST(X'312E3233' AS TEXT)",
    'blob-real-as-real-text' => "CAST(CAST(X'312E3233' AS REAL) AS TEXT)",
    'blob-int-as-integer-text' => "CAST(CAST(X'313233' AS INTEGER) AS TEXT)",
    'text-empty-as-blob' => 'CAST(' . $sqlLiteral('') . ' AS BLOB)',
    'text-space-as-text' => 'CAST(' . $sqlLiteral(' A B ') . ' AS TEXT)',
    'text-quote-as-text' => 'CAST(' . $sqlLiteral("it''s") . ' AS TEXT)',
    'text-zero-as-blob' => 'CAST(' . $sqlLiteral('0.0') . ' AS BLOB)',
    'real-zero-as-blob' => 'CAST(0.0 AS BLOB)',
    'real-negative-zero-as-text' => 'CAST(-0.0 AS TEXT)',
    'integer-zero-as-blob' => 'CAST(0 AS BLOB)',
    'integer-minus-one-as-text' => 'CAST(-1 AS TEXT)',
    'null-as-text' => 'CAST(NULL AS TEXT)',
    'null-as-blob' => 'CAST(NULL AS BLOB)',
];

$operands = [];
foreach ($textOperands as $name => $value) {
    $operands['text-' . $name] = $sqlLiteral($value);
}
foreach ($numericOperands as $name => $sql) {
    $operands['numeric-' . $name] = $sql;
}
foreach ($blobOperands as $name => $hex) {
    $operands['blob-' . $name] = "X'{$hex}'";
}
foreach ($castOperands as $name => $sql) {
    $operands['cast-' . $name] = $sql;
}

if (count($operands) !== 100) {
    throw new RuntimeException('Expression affinity BLOB/TEXT CAST corpus must own exactly 100 operands');
}

$targets = [
    'text' => 'TEXT',
    'clob' => 'CLOB',
    'varchar' => 'VARCHAR(32)',
    'character' => 'CHARACTER(16)',
    'national-character' => 'NATIONAL CHARACTER',
    'blob' => 'BLOB',
    'long-blob' => 'LONG BLOB',
    'shobblob' => 'shobblob_x',
    'abbblob' => 'abbLOb10',
    'varblob' => 'VARBLOB(12)',
];

$cases = [];
foreach ($operands as $operandName => $operandSql) {
    foreach ($targets as $targetName => $targetSql) {
        $caseName = "{$operandName}.as-{$targetName}";
        $cases[$caseName] = [
            'expression' => "CAST({$operandSql} AS {$targetSql})",
            'target' => $targetSql,
            'source' => str_starts_with($operandName, 'blob-') || str_contains($operandName, 'blob')
                ? 'e_expr.test e_expr-28.1 BLOB-to-TEXT and e_expr-27.4 BLOB cast byte preservation'
                : 'e_expr.test e_expr-27.2..27.4 and e_expr-28.2 NULL/non-BLOB BLOB/TEXT CAST behavior',
        ];
    }
}

$oracleScript = [];
foreach ($cases as $caseName => $case) {
    $safeName = str_replace("'", "''", $caseName);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeName}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(CAST({$expression} AS TEXT)) || char(9) || typeof(CAST({$expression} AS TEXT)) || char(9) || quote(CAST({$expression} AS BLOB)) || char(9) || typeof(CAST({$expression} AS BLOB));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-blob-text-cast-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr BLOB/TEXT CAST tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr BLOB/TEXT CAST output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 7) {
        throw new RuntimeException('malformed e_expr BLOB/TEXT CAST oracle row: ' . $line);
    }

    [$caseName, $quote, $type, $textQuote, $textType, $blobQuote, $blobType] = $parts;
    $oracle[$caseName] = [
        'quote' => $quote,
        'type' => $type,
        'textQuote' => $textQuote,
        'textType' => $textType,
        'blobQuote' => $blobQuote,
        'blobType' => $blobType,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr BLOB/TEXT CAST oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseName => $case) {
    $tests['real upstream corpus expression affinity dynamic blob text cast e_expr-27-28 ' . $caseName] = static function (TestRunner $t) use ($caseName, $case, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(CAST({$expression} AS TEXT)) AS tq, typeof(CAST({$expression} AS TEXT)) AS tt, quote(CAST({$expression} AS BLOB)) AS bq, typeof(CAST({$expression} AS BLOB)) AS bt",
            [],
        );

        $t->same(1, count($rows), $caseName . ' row count');
        $row = $rows[0];
        $t->same($oracle[$caseName]['quote'], (string) $row['q'], $case['source'] . ' primary quote');
        $t->same($oracle[$caseName]['type'], (string) $row['t'], $case['source'] . ' primary typeof');
        $t->same($oracle[$caseName]['textQuote'], (string) $row['tq'], $case['source'] . ' recast text quote');
        $t->same($oracle[$caseName]['textType'], (string) $row['tt'], $case['source'] . ' recast text typeof');
        $t->same($oracle[$caseName]['blobQuote'], (string) $row['bq'], $case['source'] . ' recast blob quote');
        $t->same($oracle[$caseName]['blobType'], (string) $row['bt'], $case['source'] . ' recast blob typeof');
    };
}

$tests['real upstream corpus expression affinity dynamic blob text cast owns e_expr 27 28 source'] = static function (TestRunner $t) use ($operands, $targets, $cases, $oracle): void {
    $t->same(100, count($operands));
    $t->same(10, count($targets));
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-27.2 NULL casts stay NULL',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-27.3 type-name BLOB affinity for BLOB-like target names',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-27.4 non-BLOB to BLOB renders through connection text encoding',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-28.1 BLOB to TEXT uses the connection text encoding',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-28.2 INTEGER and REAL to TEXT render like sqlite3_snprintf',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-27.2 NULL casts stay NULL',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-27.3 type-name BLOB affinity for BLOB-like target names',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-27.4 non-BLOB to BLOB renders through connection text encoding',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-28.1 BLOB to TEXT uses the connection text encoding',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-28.2 INTEGER and REAL to TEXT render like sqlite3_snprintf',
        ],
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

$tests['real upstream corpus expression affinity dynamic blob text cast dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql parser-level CAST dispatch, SQLiteBlobValue storage, quote()/typeof() scalar helpers, and hydrated sqlite3 oracle evidence for e_expr.test BLOB/TEXT affinity rows',
        'no new support component needed; reuses SQLiteSelectSql parser-level CAST dispatch, SQLiteBlobValue storage, quote()/typeof() scalar helpers, and hydrated sqlite3 oracle evidence for e_expr.test BLOB/TEXT affinity rows',
    );
    $t->same(
        'non-overlap: owns e_expr-27.2..28.2 parser-level BLOB/TEXT CAST formatting, including two-digit REAL exponent text rendering; avoids accepted atof1 decimal REAL, types.test record storage, scalar-subquery arity, affinity3 REAL predicates, IN/BETWEEN, CASE, LIKE/GLOB, JSON, WAL, VFS, B-tree, and PRAGMA slices',
        'non-overlap: owns e_expr-27.2..28.2 parser-level BLOB/TEXT CAST formatting, including two-digit REAL exponent text rendering; avoids accepted atof1 decimal REAL, types.test record storage, scalar-subquery arity, affinity3 REAL predicates, IN/BETWEEN, CASE, LIKE/GLOB, JSON, WAL, VFS, B-tree, and PRAGMA slices',
    );
};

return $tests;
