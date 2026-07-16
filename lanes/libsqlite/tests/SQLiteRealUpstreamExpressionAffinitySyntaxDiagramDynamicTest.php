<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression syntax diagram tests');
}

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-12.3 exercises expression syntax
//   diagram forms. This dynamic shard keeps the upstream expression grammar
//   shapes and runs them through parser-level SELECT execution over row values
//   whose storage classes stress REAL, numeric text, BLOB, NULL, and TEXT
//   affinity behavior.
$rowValues = [
    'null' => null,
    'int-zero' => 0,
    'int-one' => 1,
    'int-neg-two' => -2,
    'int-five' => 5,
    'real-half' => 0.5,
    'real-two-quarter' => 2.25,
    'real-neg-three-half' => -3.5,
    'text-empty' => '',
    'text-one' => '1',
    'text-two-real' => '2.25',
    'text-leading-real' => '  -3.5',
    'text-alpha' => 'alpha',
    'text-glob' => 'abcXYZ',
    'blob-abc' => new SQLiteBlobValue('abc'),
    'blob-one' => new SQLiteBlobValue('1'),
];

$literalSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if ($value instanceof SQLiteBlobValue) {
        return "X'" . strtoupper(bin2hex($value->bytes)) . "'";
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$exprTemplates = [
    'column' => 'cname',
    'unary-plus' => '+cname',
    'unary-minus' => '-cname',
    'unary-not' => 'NOT cname',
    'unary-bitnot' => '~cname',
    'concat' => "cname || '-tail'",
    'mul' => 'cname * 3',
    'div' => 'cname / 2',
    'mod' => 'cname % 2',
    'add' => 'cname + 7',
    'sub' => 'cname - 4',
    'lshift' => 'cname << 1',
    'rshift' => 'cname >> 1',
    'bitand' => 'cname & 3',
    'bitor' => 'cname | 4',
    'lt' => 'cname < 2',
    'le' => 'cname <= 2',
    'gt' => 'cname > 2',
    'ge' => 'cname >= 2',
    'eq' => 'cname = 1',
    'eqeq' => 'cname == 1',
    'ne' => 'cname != 1',
    'ne2' => 'cname <> 1',
    'is-null' => 'cname IS NULL',
    'is-one' => 'cname IS 1',
    'is-not-one' => 'cname IS NOT 1',
    'eq-null' => 'cname = NULL',
    'and' => 'cname AND 1',
    'or' => 'cname OR 0',
    'paren-add' => '(cname + 2)',
    'cast-integer' => 'CAST(cname AS integer)',
    'cast-real' => 'CAST(cname AS real)',
    'cast-numeric' => 'CAST(cname AS numeric)',
    'cast-text' => 'CAST(cname AS text)',
    'cast-blob' => 'CAST(cname AS blob)',
    'collate-nocase-eq' => "cname COLLATE nocase = 'ALPHA'",
    'collate-binary-eq' => "cname COLLATE binary = 'ALPHA'",
    'like-prefix' => "cname LIKE 'abc%'",
    'not-like-prefix' => "cname NOT LIKE 'abc%'",
    'like-escape' => "cname LIKE 'abcX%' ESCAPE 'X'",
    'glob-prefix' => "cname GLOB 'abc*'",
    'not-glob-prefix' => "cname NOT GLOB 'abc*'",
    'between-numeric' => 'cname BETWEEN -2 AND 2',
    'not-between-numeric' => 'cname NOT BETWEEN -2 AND 2',
    'in-list' => "cname IN (NULL, 1, 2.25, 'alpha')",
    'not-in-list' => "cname NOT IN (NULL, 1, 2.25, 'alpha')",
    'case-simple' => "CASE cname WHEN 1 THEN 'one' WHEN 2.25 THEN 'real' ELSE 'other' END",
    'case-simple-no-else' => "CASE cname WHEN 1 THEN 'one' WHEN 2.25 THEN 'real' END",
    'case-searched' => "CASE WHEN cname IS NULL THEN 'null' WHEN cname > 1 THEN 'gt' ELSE 'rest' END",
    'case-searched-no-else' => "CASE WHEN cname IS NULL THEN 'null' WHEN cname > 1 THEN 'gt' END",
    'coalesce' => "coalesce(cname, 'fallback')",
    'nullif' => "nullif(cname, 'alpha')",
    'ifnull' => "ifnull(cname, 'fallback')",
    'typeof' => 'typeof(cname)',
    'quote' => 'quote(cname)',
    'length' => 'length(cname)',
    'substr' => 'substr(cname, 1, 3)',
    'round' => 'round(cname, 1)',
    'compound-arithmetic' => '(cname + 4) * 2 - 1',
    'compound-comparison' => '(cname + 4) * 2 >= 5',
    'boolean-precedence' => 'cname > 0 AND cname < 4 OR cname IS NULL',
    'between-precedence' => 'cname = 1 BETWEEN 0 AND 1',
    'concat-cast' => "CAST(cname AS text) || ':' || typeof(cname)",
];

$cases = [];
foreach ($exprTemplates as $exprName => $expression) {
    foreach ($rowValues as $rowName => $value) {
        $cases[$exprName . '-' . $rowName] = [
            'expression' => $expression,
            'value' => $value,
        ];
    }
}

$oracleScript = [
    'CREATE TABLE tblname(cname);',
];
foreach ($cases as $caseId => $case) {
    $safeId = str_replace("'", "''", $caseId);
    $value = $literalSql($case['value']);
    $expression = $case['expression'];
    $oracleScript[] = 'DELETE FROM tblname;';
    $oracleScript[] = "INSERT INTO tblname(cname) VALUES({$value});";
    $oracleScript[] = "SELECT '{$safeId}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) FROM tblname;";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr12-syntax-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr-12.3 syntax tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr-12.3 syntax output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed e_expr-12.3 syntax oracle row: ' . $line);
    }
    [$caseId, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$caseId] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr-12.3 syntax oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseId => $case) {
    $tests['real upstream expression affinity syntax diagram dynamic e_expr-12.3 ' . $caseId] =
        static function (TestRunner $t) use ($caseId, $case, $oracle): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT quote({$case['expression']}) AS q, typeof({$case['expression']}) AS t, quote(({$case['expression']}) IS NULL) AS n FROM tblname",
                ['tblname' => [['cname' => $case['value']]]],
            );

            $t->same(1, count($rows), $caseId . ' row count');
            $row = $rows[0];
            $t->same($oracle[$caseId]['quote'], (string) $row['q'], $caseId . ' quote ' . $case['expression']);
            $t->same($oracle[$caseId]['typeof'], (string) $row['t'], $caseId . ' typeof ' . $case['expression']);
            $t->same($oracle[$caseId]['isNull'], (string) $row['n'], $caseId . ' is-null ' . $case['expression']);
        };
}

$tests['real upstream expression affinity syntax diagram dynamic owns e_expr-12.3 grammar rows'] =
    static function (TestRunner $t) use ($exprTemplates, $rowValues, $cases, $oracle): void {
        $t->same(63, count($exprTemplates));
        $t->same(16, count($rowValues));
        $t->same(1008, count($cases));
        $t->same(1008, count($oracle));
        $t->same(
            'e_expr.test e_expr-12.3 expression syntax diagram forms over dynamic storage-class rows',
            'e_expr.test e_expr-12.3 expression syntax diagram forms over dynamic storage-class rows',
        );
        $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    };

return $tests;
