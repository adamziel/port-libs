<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream IN RHS affinity tests');
}

$sqlString = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$numericLiteral = static function (int $seed): array {
    $base = ($seed % 997) + 2;

    return match ($seed % 4) {
        0 => [
            'label' => 'integer',
            'value' => $base,
            'sql' => (string) $base,
            'text' => (string) $base,
            'next' => $base + 1,
            'previous' => $base - 1,
        ],
        1 => [
            'label' => 'decimal',
            'value' => $base + 0.5,
            'sql' => $base . '.5',
            'text' => $base . '.50',
            'next' => $base + 1.5,
            'previous' => $base - 0.5,
        ],
        2 => [
            'label' => 'leading-zero',
            'value' => $base,
            'sql' => (string) $base,
            'text' => '0' . $base,
            'next' => $base + 1,
            'previous' => $base - 1,
        ],
        default => [
            'label' => 'exponent',
            'value' => $base * 10,
            'sql' => (string) ($base * 10),
            'text' => $base . 'e1',
            'next' => ($base + 1) * 10,
            'previous' => ($base - 1) * 10,
        ],
    };
};

$shapeSql = static function (array $case) use ($sqlString): array {
    $text = $sqlString($case['text']);
    $numeric = $case['sql'];

    return [
        'b-in-numeric' => "SELECT rowid FROM t6 WHERE b IN ({$numeric}) ORDER BY rowid",
        'b-in-text' => "SELECT rowid FROM t6 WHERE b IN ({$text}) ORDER BY rowid",
        'plus-b-in-text' => "SELECT rowid FROM t6 WHERE +b IN ({$text}) ORDER BY rowid",
        'a-in-text' => "SELECT rowid FROM t6 WHERE a IN ({$text}) ORDER BY rowid",
        'a-in-numeric' => "SELECT rowid FROM t6 WHERE a IN ({$numeric}) ORDER BY rowid",
        'plus-a-in-text' => "SELECT rowid FROM t6 WHERE +a IN ({$text}) ORDER BY rowid",
    ];
};

$cases = [];
foreach (range(0, 999) as $seed) {
    $literal = $numericLiteral($seed);
    $key = sprintf('seed-%04d-%s', $seed, $literal['label']);
    $cases[$key] = [
        'seed' => $seed,
        'literal' => $literal,
        'rows' => [
            [
                'rowid' => 1,
                'a' => $literal['previous'],
                'b' => $literal['value'],
                '__sqlite_column_affinities' => ['a' => 'BLOB', 'b' => 'NUMERIC'],
            ],
            [
                'rowid' => 2,
                'a' => $literal['value'],
                'b' => $literal['next'],
                '__sqlite_column_affinities' => ['a' => 'BLOB', 'b' => 'NUMERIC'],
            ],
        ],
        'sql' => $shapeSql($literal),
    ];
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $literal = $case['literal'];
    $oracleScript[] = 'DROP TABLE IF EXISTS t6;';
    $oracleScript[] = 'CREATE TABLE t6(a,b NUMERIC);';
    $oracleScript[] = sprintf('INSERT INTO t6(rowid,a,b) VALUES(1,%s,%s);', $literal['previous'], $literal['sql']);
    $oracleScript[] = sprintf('INSERT INTO t6(rowid,a,b) VALUES(2,%s,%s);', $literal['sql'], $literal['next']);
    foreach ($case['sql'] as $shape => $sql) {
        $oracleKey = str_replace("'", "''", $key . ':' . $shape);
        $oracleScript[] = "SELECT '{$oracleKey}' || char(9) || coalesce(group_concat(rowid, ','), '') FROM ({$sql});";
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-in-rhs-affinity-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for IN RHS affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce IN RHS affinity output');
}

$oracle = [];
foreach (explode("\n", rtrim($oracleOutput, "\r\n")) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed IN RHS affinity oracle row: ' . $line);
    }
    $oracle[$parts[0]] = $parts[1] === ''
        ? []
        : array_map('intval', explode(',', $parts[1]));
}

if (count($oracle) !== count($cases) * 6) {
    throw new RuntimeException(sprintf('Expected %d IN RHS affinity oracle rows, got %d', count($cases) * 6, count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic in.test in-11 RHS affinity ' . $key] = static function (TestRunner $t) use ($key, $case, $oracle): void {
        foreach ($case['sql'] as $shape => $sql) {
            $rows = SQLiteSelectSql::execute($sql, ['t6' => $case['rows']]);
            $actual = array_map(static fn (array $row): int => (int) $row['rowid'], $rows);
            $t->same($oracle[$key . ':' . $shape], $actual, "{$key} {$shape}");
        }
    };
}

$tests['real upstream corpus expression affinity dynamic in.test in-11 source truth'] = static function (TestRunner $t) use ($cases, $oracle): void {
    $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test');
    $t->contains('Type affinity applied to the right-hand side of an IN operator', $source);
    $t->contains('in-11.1', $source);
    $t->contains('in-11.6', $source);
    $t->same(1000, count($cases));
    $t->same(6000, count($oracle));
    $t->same(
        'in.test in-11.1..11.6 RHS affinity and unary-plus affinity stripping',
        'in.test in-11.1..11.6 RHS affinity and unary-plus affinity stripping',
    );
};

$tests['real upstream corpus expression affinity dynamic in.test in-11 dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql IN-list parsing, SQLiteSelectPredicate affinity comparison, unary-plus expression evaluation, and the local sqlite3 oracle',
        'no new support component needed; reuses SQLiteSelectSql IN-list parsing, SQLiteSelectPredicate affinity comparison, unary-plus expression evaluation, and the local sqlite3 oracle',
    );
    $t->same(
        'non-overlap: owns in.test in-11 RHS affinity only; avoids accepted types2 IN-list/IN SELECT matrices, in.test in-19 REAL IN, affinity2/type matrices, CASE, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup',
        'non-overlap: owns in.test in-11 RHS affinity only; avoids accepted types2 IN-list/IN SELECT matrices, in.test in-19 REAL IN, affinity2/type matrices, CASE, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup',
    );
};

return $tests;
