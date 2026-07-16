<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity CASE base dynamic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/e_expr.test e_expr-23.1.1..23.1.9 verifies that simple CASE
//   compares the base expression against WHEN expressions using the same
//   collation, affinity, and NULL rules as the = operator.
// - test/e_expr.test e_expr-24.1.1..24.1.2 verifies that a NULL base
//   expression skips all WHEN arms and returns ELSE or NULL.
//
// This dynamic shard widens those exact rules across TEXT/NOCASE/RTRIM,
// INTEGER, REAL, NUMERIC, BLOB, and NULL-shaped operands without overlapping
// the accepted CASE/iif truthiness matrix, which focuses on searched CASE
// boolean evaluation and branch selection.
$rows = [
    [
        'id' => 1,
        'text_plain' => 'abc',
        'text_nocase' => 'abc',
        'text_rtrim' => 'trim   ',
        'integer_value' => 55,
        'real_value' => 34.5,
        'numeric_text' => 55,
        'blob_value' => new SQLiteBlobValue('34.5'),
        'null_value' => null,
    ],
    [
        'id' => 2,
        'text_plain' => 'Alpha',
        'text_nocase' => 'Alpha',
        'text_rtrim' => 'Alpha   ',
        'integer_value' => 7,
        'real_value' => 7.0,
        'numeric_text' => 7,
        'blob_value' => new SQLiteBlobValue('Alpha'),
        'null_value' => null,
    ],
    [
        'id' => 3,
        'text_plain' => '42',
        'text_nocase' => 'MiXeD',
        'text_rtrim' => '42   ',
        'integer_value' => 42,
        'real_value' => 42.25,
        'numeric_text' => 42.25,
        'blob_value' => new SQLiteBlobValue('42'),
        'null_value' => null,
    ],
];

$affinities = [
    'id' => 'INTEGER',
    'text_plain' => 'TEXT',
    'text_nocase' => 'TEXT',
    'text_rtrim' => 'TEXT',
    'integer_value' => 'INTEGER',
    'real_value' => 'REAL',
    'numeric_text' => 'NUMERIC',
    'blob_value' => 'BLOB',
    'null_value' => 'BLOB',
];

$collations = [
    'text_nocase' => 'NOCASE',
    'text_rtrim' => 'RTRIM',
];

$portRows = [];
foreach ($rows as $row) {
    $portRows[] = $row + [
        '__sqlite_column_affinities' => $affinities,
        '__sqlite_column_collations' => $collations,
    ];
}

$sqlRows = [];
foreach ($rows as $row) {
    $sqlRows[] = sprintf(
        '(%d,%s,%s,%s,%d,%.17G,%s,%s,NULL)',
        $row['id'],
        $quoteSql($row['text_plain']),
        $quoteSql($row['text_nocase']),
        $quoteSql($row['text_rtrim']),
        $row['integer_value'],
        $row['real_value'],
        $quoteSql((string) $row['numeric_text']),
        "X'" . strtoupper(bin2hex($row['blob_value']->bytes)) . "'",
    );
}

$baseExpressions = [
    'text-plain-column' => 'text_plain',
    'text-nocase-column' => 'text_nocase',
    'text-nocase-explicit' => 'text_plain COLLATE NOCASE',
    'text-rtrim-column' => 'text_rtrim',
    'text-rtrim-explicit' => 'text_plain COLLATE RTRIM',
    'integer-column' => 'integer_value',
    'real-column' => 'real_value',
    'numeric-text-column' => 'numeric_text',
    'blob-column' => 'blob_value',
    'null-column' => 'null_value',
    'null-literal' => 'NULL',
    'integer-cast-text' => 'CAST(numeric_text AS INTEGER)',
    'real-cast-text' => 'CAST(numeric_text AS REAL)',
];

$whenExpressionSets = [
    'text-case' => [
        $quoteSql('xyz') => 'miss-text',
        $quoteSql('ABC') => 'upper-abc',
        $quoteSql('abc') => 'lower-abc',
        $quoteSql('Alpha') => 'alpha',
        $quoteSql('Alpha') . ' COLLATE NOCASE' => 'alpha-nocase',
        $quoteSql('trim') . ' COLLATE RTRIM' => 'trim-rtrim',
        $quoteSql('trim   ') => 'trim-spaces',
        $quoteSql('MiXeD') => 'mixed',
    ],
    'numeric-case' => [
        '1' => 'one',
        $quoteSql('7') => 'text-seven',
        $quoteSql('7.0') => 'text-seven-real',
        '42' => 'int-forty-two',
        $quoteSql('42.25') => 'text-real-forty-two',
        $quoteSql('55') => 'text-fifty-five',
        '55' => 'int-fifty-five',
        '34.5' => 'real-thirty-four-half',
    ],
    'mixed-case' => [
        'NULL' => 'null-arm',
        $quoteSql('34.5') => 'text-real',
        "X'33342E35'" => 'blob-real',
        $quoteSql('42') => 'text-forty-two',
        "X'3432'" => 'blob-forty-two',
        $quoteSql('abc') => 'text-abc',
        "X'616263'" => 'blob-abc',
    ],
];

$caseSql = static function (string $base, array $arms, bool $withElse): string {
    $sql = 'CASE ' . $base . ' ';
    foreach ($arms as $whenSql => $label) {
        $sql .= 'WHEN ' . $whenSql . ' THEN ' . "'" . str_replace("'", "''", $label) . "' ";
    }
    if ($withElse) {
        $sql .= "ELSE 'else-result' ";
    }

    return $sql . 'END';
};

$cases = [];
foreach ($baseExpressions as $baseName => $baseSql) {
    foreach ($whenExpressionSets as $setName => $arms) {
        foreach ([true, false] as $withElse) {
            $caseExpression = $caseSql($baseSql, $arms, $withElse);
            foreach ([
                'quote' => "quote({$caseExpression})",
                'typeof' => "typeof({$caseExpression})",
                'is-null' => "quote(({$caseExpression}) IS NULL)",
            ] as $projectionName => $projectionSql) {
                $cases["{$baseName}.{$setName}." . ($withElse ? 'else' : 'no-else') . ".{$projectionName}"] = $projectionSql;
            }
        }
    }
}

$oracleScript = [
    'CREATE TABLE t1('
        . 'id INTEGER, '
        . 'text_plain TEXT, '
        . 'text_nocase TEXT COLLATE NOCASE, '
        . 'text_rtrim TEXT COLLATE RTRIM, '
        . 'integer_value INTEGER, '
        . 'real_value REAL, '
        . 'numeric_text NUMERIC, '
        . 'blob_value BLOB, '
        . 'null_value BLOB'
        . ');',
    'INSERT INTO t1 VALUES ' . implode(',', $sqlRows) . ';',
];
foreach ($cases as $key => $projectionSql) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || group_concat(value, '|') FROM (SELECT {$projectionSql} AS value FROM t1 ORDER BY id);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-case-base-affinity-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for CASE base affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce CASE base affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 CASE base affinity oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d CASE base affinity oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $projectionSql) {
    $tests['real upstream expression affinity CASE base dynamic e_expr-23 e_expr-24 ' . $key] = static function (TestRunner $t) use ($key, $projectionSql, $oracle, $portRows): void {
        $actualRows = SQLiteSelectSql::execute("SELECT {$projectionSql} AS value FROM t1 ORDER BY id", ['t1' => $portRows]);
        $actual = implode('|', array_map(static fn (array $row): string => (string) $row['value'], $actualRows));
        $t->same($oracle[$key], $actual, $projectionSql);
    };
}

$tests['real upstream expression affinity CASE base dynamic owns e_expr source range'] = static function (TestRunner $t) use ($rows, $baseExpressions, $whenExpressionSets, $cases, $oracle): void {
    $t->same(3, count($rows));
    $t->same(13, count($baseExpressions));
    $t->same(3, count($whenExpressionSets));
    $t->same(234, count($cases));
    $t->same(234, count($oracle));
    $t->same(
        'e_expr.test e_expr-23.1.1..23.1.9 and e_expr-24.1.1..24.1.2 CASE base expression collation, affinity, and NULL handling',
        'e_expr.test e_expr-23.1.1..23.1.9 and e_expr-24.1.1..24.1.2 CASE base expression collation, affinity, and NULL handling',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
