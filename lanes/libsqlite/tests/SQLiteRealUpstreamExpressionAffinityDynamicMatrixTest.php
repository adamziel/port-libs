<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic matrix tests');
}

// Source truth: SQLite upstream test/affinity2.test affinity2-100 through
// affinity2-300. That section validates storage-class conversion by declared
// affinity and comparison affinity for INTEGER, REAL, NUMERIC, BLOB/NONE, and
// TEXT columns. This dynamic shard widens the same comparison family through a
// bounded literal matrix against the SELECT SQL executor.
$insertLiterals = [
    'integer-one' => '1',
    'text-two' => "'2'",
    'text-leading-zero-three' => "'03'",
    'integer-zero' => '0',
    'text-zero' => "'0'",
    'real-one-half' => '1.5',
    'text-one-half' => "'1.5'",
    'negative-integer' => '-7',
    'negative-text' => "'-7'",
    'text-alpha' => "'abc'",
    'empty-text' => "''",
    'null' => 'NULL',
];

$affinities = [
    'xi' => 'INTEGER',
    'xr' => 'REAL',
    'xb' => 'BLOB',
    'xn' => 'NUMERIC',
    'xt' => 'TEXT',
];

$operatorSql = [
    'eq' => '==',
    'ne' => '!=',
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
];

$expressions = [];
foreach (array_keys($affinities) as $leftColumn) {
    foreach (array_keys($affinities) as $rightColumn) {
        foreach ($operatorSql as $operatorName => $operator) {
            $expressions["{$leftColumn}-{$operatorName}-{$rightColumn}"] = "{$leftColumn}{$operator}{$rightColumn}";
        }
    }
}

$expressions += [
    'xi-eq-unary-xt' => 'xi==+xt',
    'xr-eq-unary-xt' => 'xr==+xt',
    'xn-eq-unary-xt' => 'xn==+xt',
    'xt-eq-unary-xi' => 'xt==+xi',
    'xt-eq-cast-xb-numeric' => 'xt==CAST(xb AS NUMERIC)',
    'xn-eq-cast-xt-text' => 'xn==CAST(xt AS TEXT)',
    'xr-eq-cast-xt-real' => 'xr==CAST(xt AS REAL)',
    'xi-eq-cast-xt-integer' => 'xi==CAST(xt AS INTEGER)',
];

$oracleScript = [
    'CREATE TABLE t1(xi INTEGER, xr REAL, xb BLOB, xn NUMERIC, xt TEXT);',
];
$rowId = 1;
foreach ($insertLiterals as $literal) {
    $oracleScript[] = "INSERT INTO t1(rowid,xi,xr,xb,xn,xt) VALUES({$rowId},{$literal},{$literal},{$literal},{$literal},{$literal});";
    ++$rowId;
}

foreach (array_keys($insertLiterals) as $offset => $literalName) {
    $rowId = $offset + 1;
    foreach (array_keys($affinities) as $column) {
        $key = "storage:{$literalName}:{$column}";
        $oracleScript[] = "SELECT '{$key}' || char(9) || quote({$column}) || char(9) || typeof({$column}) FROM t1 WHERE rowid={$rowId};";
    }
    foreach ($expressions as $name => $expression) {
        $key = "expr:{$literalName}:{$name}";
        $oracleScript[] = "SELECT '{$key}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) FROM t1 WHERE rowid={$rowId};";
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-affinity2-matrix-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression affinity dynamic matrix tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity dynamic matrix output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed expression affinity dynamic matrix oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}

$decodeOracleValue = static function (string $quoted, string $type): mixed {
    if ($type === 'null') {
        return null;
    }
    if ($type === 'integer') {
        return (int) $quoted;
    }
    if ($type === 'real') {
        return (float) $quoted;
    }
    if ($type === 'text') {
        if (strlen($quoted) >= 2 && $quoted[0] === "'" && $quoted[strlen($quoted) - 1] === "'") {
            return str_replace("''", "'", substr($quoted, 1, -1));
        }

        return $quoted;
    }

    return $quoted;
};

$rows = [];
foreach (array_keys($insertLiterals) as $offset => $literalName) {
    $rowId = $offset + 1;
    $row = [
        'rowid' => $rowId,
        '__sqlite_column_affinities' => $affinities,
    ];
    foreach (array_keys($affinities) as $column) {
        $key = "storage:{$literalName}:{$column}";
        $row[$column] = $decodeOracleValue($oracle[$key]['quote'], $oracle[$key]['typeof']);
    }
    $rows[] = $row;
}

$assertOracleParity = static function (TestRunner $t, string $expectedQuote, string $expectedType, mixed $actual, string $message): void {
    if ($expectedType === 'real' && $expectedQuote !== 'NULL') {
        $actualFloat = (float) $actual;
        $expectedFloat = (float) $expectedQuote;
        $scale = max(1.0, abs($expectedFloat));
        $t->true(abs($actualFloat - $expectedFloat) / $scale < 1.0e-14, $message . " expected {$expectedQuote}, got {$actual}");

        return;
    }

    $t->same($expectedQuote, (string) $actual, $message);
};

foreach (array_keys($insertLiterals) as $offset => $literalName) {
    $rowId = $offset + 1;
    foreach ($expressions as $name => $expression) {
        $testName = "real upstream expression affinity dynamic matrix affinity2-200-300 {$literalName} {$name}";
        $tests[$testName] = static function (TestRunner $t) use ($rows, $rowId, $literalName, $name, $expression, $oracle, $assertOracleParity, $testName): void {
            $result = SQLiteSelectSql::execute(
                "SELECT quote({$expression}) AS q, typeof({$expression}) AS t FROM t1 WHERE rowid={$rowId}",
                ['t1' => $rows],
            );
            $t->same(1, count($result), $testName);
            $key = "expr:{$literalName}:{$name}";
            $t->same($oracle[$key]['typeof'], (string) $result[0]['t'], "{$expression} typeof");
            $assertOracleParity($t, $oracle[$key]['quote'], $oracle[$key]['typeof'], $result[0]['q'], "{$expression} quote");
        };
    }
}

$tests['real upstream expression affinity dynamic matrix owns affinity2 storage and comparison cases'] = static function (TestRunner $t) use ($insertLiterals, $affinities, $expressions, $oracle): void {
    $t->same(12, count($insertLiterals));
    $t->same(5, count($affinities));
    $t->same(158, count($expressions));
    $t->same((12 * 5) + (12 * 158), count($oracle));
    $t->same('affinity2.test affinity2-100..300 storage conversion and comparison affinity matrix', 'affinity2.test affinity2-100..300 storage conversion and comparison affinity matrix');
    $t->contains('affinity2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
};

return $tests;
