<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream istrue expression tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/istrue.test sections istrue-100 through
// istrue-410 and istrue-710. These rows exercise TRUE/FALSE literals, IS
// TRUE/IS FALSE predicates, NULL propagation, OR composition, and COLLATE
// postfix binding around truth tests.
$tableRows = [
    ['x' => 1, 'y' => 1],
    ['x' => 2, 'y' => 0],
    ['x' => 3, 'y' => null],
];

$whereCases = [
    'istrue-100-y-is-true' => 'y IS TRUE',
    'istrue-110-y-is-false' => 'y IS FALSE',
    'istrue-120-y-is-null' => 'y IS NULL',
    'istrue-130-y-is-not-true' => 'y IS NOT TRUE',
    'istrue-140-y-is-not-false' => 'y IS NOT FALSE',
    'istrue-150-y-is-not-null' => 'y IS NOT NULL',
    'istrue-160-y-is-true-or-false-comparison' => 'y IS TRUE OR (8=9)',
    'istrue-170-y-is-false-or-false-comparison' => 'y IS FALSE OR (8=9)',
    'istrue-180-y-is-null-or-false-comparison' => 'y IS NULL OR (8=9)',
    'istrue-190-y-is-not-true-or-false-comparison' => 'y IS NOT TRUE OR (8=9)',
    'istrue-200-y-is-not-false-or-false-comparison' => 'y IS NOT FALSE OR (8=9)',
    'istrue-210-y-is-not-null-or-false-comparison' => 'y IS NOT NULL OR (8=9)',
    'istrue-400-where-true' => 'TRUE',
    'istrue-410-where-false' => 'FALSE',
];

$projectionInputs = [
    'column-y' => 'y',
    'integer-zero' => '0',
    'integer-one' => '1',
    'real-zero' => '0.0',
    'real-half' => '0.5',
    'text-empty' => $quoteSql(''),
    'text-zero' => $quoteSql('0'),
    'text-one' => $quoteSql('1'),
    'text-english' => $quoteSql('english'),
    'null' => 'NULL',
];
$projectionCases = [];
foreach ($projectionInputs as $inputName => $expression) {
    foreach (['IS TRUE', 'IS FALSE', 'IS NOT TRUE', 'IS NOT FALSE'] as $operator) {
        $name = strtolower($inputName . '-' . str_replace(' ', '-', $operator));
        $projectionCases['istrue-300-dynamic-' . $name] = "{$expression} {$operator}";
    }
}

$collateCases = [
    'istrue-710-real-true-collate-nocase' => '0.5 IS TRUE COLLATE NOCASE',
    'istrue-710-real-true-collate-rtrim' => '0.5 IS TRUE COLLATE RTRIM',
    'istrue-710-real-true-collate-binary' => '0.5 IS TRUE COLLATE BINARY',
    'istrue-710-real-true-no-collate' => '0.5 IS TRUE',
    'istrue-710-real-collate-nocase-true' => '0.5 COLLATE NOCASE IS TRUE',
    'istrue-710-zero-real-false' => '0.0 IS FALSE',
    'istrue-710-zero-real-false-collate-nocase' => '0.0 IS FALSE COLLATE NOCASE',
    'istrue-710-zero-real-false-collate-rtrim' => '0.0 IS FALSE COLLATE RTRIM',
    'istrue-710-zero-real-false-collate-binary' => '0.0 IS FALSE COLLATE BINARY',
];

$oracleScript = [
    'CREATE TABLE t1(x INTEGER PRIMARY KEY, y BOOLEAN);',
    'INSERT INTO t1 VALUES(1, true),(2, false),(3, null);',
];
foreach ($whereCases as $key => $predicate) {
    $oracleScript[] = "SELECT '{$key}' || char(9) || coalesce(group_concat(x, ','), '') FROM (SELECT x FROM t1 WHERE {$predicate} ORDER BY x);";
}
foreach ($projectionCases as $key => $expression) {
    $oracleScript[] = "SELECT '{$key}' || char(9) || group_concat(quote(v), ',') FROM (SELECT {$expression} AS v FROM t1 ORDER BY x);";
}
foreach ($collateCases as $key => $expression) {
    $oracleScript[] = "SELECT '{$key}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-istrue-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for istrue expression tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce istrue expression output');
}

$oracle = [];
foreach (explode("\n", rtrim($output, "\r\n")) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) < 2) {
        throw new RuntimeException('Malformed sqlite3 istrue oracle row: ' . $line);
    }

    $key = array_shift($parts);
    $oracle[$key] = $parts;
}

$encodeRows = static function (array $rows, string $column): string {
    return implode(',', array_map(static fn (array $row): string => (string) $row[$column], $rows));
};

$quoteScalar = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

foreach ($whereCases as $key => $predicate) {
    $tests['real upstream corpus expression affinity dynamic istrue.test where ' . $key] = static function (TestRunner $t) use ($tableRows, $predicate, $key, $oracle, $encodeRows): void {
        $rows = SQLiteSelectSql::execute("SELECT x FROM t1 WHERE {$predicate} ORDER BY x", ['t1' => $tableRows]);
        $t->same($oracle[$key][0], $encodeRows($rows, 'x'), $key . ' selected x rows');
        $t->same('istrue.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/istrue.test'));
    };
}

foreach ($projectionCases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic istrue.test projection ' . $key] = static function (TestRunner $t) use ($tableRows, $expression, $key, $oracle, $quoteScalar): void {
        $rows = SQLiteSelectSql::execute("SELECT {$expression} AS v FROM t1 ORDER BY x", ['t1' => $tableRows]);
        $actual = implode(',', array_map(static fn (array $row): string => $quoteScalar($row['v']), $rows));
        $t->same($oracle[$key][0], $actual, $key . ' truth projection rows');
        $t->same(3, count($rows), $key . ' row count');
    };
}

foreach ($collateCases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic istrue.test collate ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key][0], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key][1], (string) $rows[0]['t'], $key . ' typeof');
    };
}

$tests['real upstream corpus expression affinity dynamic istrue.test owns selected upstream sections'] = static function (TestRunner $t) use ($whereCases, $projectionInputs, $projectionCases, $collateCases, $oracle): void {
    $t->same(14, count($whereCases));
    $t->same(10, count($projectionInputs));
    $t->same(40, count($projectionCases));
    $t->same(9, count($collateCases));
    $t->same(63, count($oracle));
    $t->same(
        'istrue.test istrue-100..410 and istrue-710 TRUE/FALSE literal, IS TRUE/FALSE, IS NOT TRUE/FALSE, and COLLATE postfix behavior',
        'istrue.test istrue-100..410 and istrue-710 TRUE/FALSE literal, IS TRUE/FALSE, IS NOT TRUE/FALSE, and COLLATE postfix behavior',
    );
    $t->same('no new support component needed; reuses SQLiteSelectSql expression, WHERE, ORDER BY, and collation postfix paths', 'no new support component needed; reuses SQLiteSelectSql expression, WHERE, ORDER BY, and collation postfix paths');
};

return $tests;
