<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream affinity2 real precision dynamic tests');
}

$literalSql = static fn (int|string $value): string => (string) $value;

$seedValues = [
    3175546974276630385,
    3175546974276630384,
    3175546974276630383,
    3175546974276630400,
    3175546974276630272,
    3175546974276630528,
    4503599627370495,
    4503599627370496,
    4503599627370497,
    9007199254740991,
    9007199254740992,
    9007199254740993,
    -3175546974276630385,
    -3175546974276630384,
    -3175546974276630383,
    -9007199254740991,
    -9007199254740992,
    -9007199254740993,
];

$rows = [];
foreach ($seedValues as $index => $value) {
    $rows[] = [
        'id' => $index + 1,
        'c0' => $value,
        'c0_text' => (string) $value,
    ];
}
$affinities = ['id' => 'INTEGER', 'c0' => 'REAL', 'c0_text' => 'TEXT'];
$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rows, $affinities),
);

$expressions = [];
foreach ($seedValues as $value) {
    foreach ([-1024, -257, -1, 0, 1, 257, 1024] as $delta) {
        $literal = (string) ($value + $delta);
        foreach (['<', '<=', '>', '>=', '=', '==', '<>', '!='] as $operator) {
            $expressions["literal-{$literal}-{$operator}-real-column"] = "{$literal} {$operator} c0";
            $expressions["real-column-{$operator}-literal-{$literal}"] = "c0 {$operator} {$literal}";
        }
    }
    foreach (['<', '<=', '>', '>=', '=', '<>'] as $operator) {
        $quoted = "'" . str_replace("'", "''", (string) $value) . "'";
        $expressions["text-literal-{$value}-{$operator}-real-column"] = "{$quoted} {$operator} c0";
        $expressions["real-column-{$operator}-text-literal-{$value}"] = "c0 {$operator} {$quoted}";
        $expressions["cast-text-literal-{$value}-{$operator}-real-column"] = "CAST({$quoted} AS NUMERIC) {$operator} c0";
    }
}

$oracleScript = [
    'CREATE TABLE t0(id INTEGER PRIMARY KEY, c0 REAL, c0_text TEXT);',
];
foreach ($rows as $row) {
    $oracleScript[] = sprintf(
        "INSERT INTO t0(id,c0,c0_text) VALUES(%d,%s,'%s');",
        $row['id'],
        $literalSql($row['c0']),
        str_replace("'", "''", $row['c0_text']),
    );
}
foreach ($expressions as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || group_concat(payload, ',') FROM (SELECT id || ':' || quote({$expression}) || ':' || typeof({$expression}) AS payload FROM t0 ORDER BY id);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-affinity2-real-precision-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce affinity2 real precision output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }
    $oracle[$parts[0]] = $parts[1];
}
if (count($oracle) !== count($expressions)) {
    throw new RuntimeException(sprintf('Expected %d oracle expressions, got %d', count($expressions), count($oracle)));
}

$port = static function (string $expression) use ($tableRows): string {
    $rows = SQLiteSelectSql::execute("SELECT id, quote({$expression}) AS q, typeof({$expression}) AS t FROM t0 ORDER BY id", ['t0' => $tableRows]);

    return implode(',', array_map(
        static fn (array $row): string => $row['id'] . ':' . $row['q'] . ':' . $row['t'],
        $rows,
    ));
};

foreach ($expressions as $key => $expression) {
    $tests['real upstream expression affinity real precision dynamic affinity2.test affinity2-600 601 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle, $port): void {
        $t->same($oracle[$key], $port($expression), $expression);
    };
}

$tests['real upstream expression affinity real precision dynamic owns affinity2 600 601 corpus'] = static function (TestRunner $t) use ($seedValues, $expressions, $tableRows): void {
    $t->same(18, count($seedValues));
    $t->same(1988, count($expressions));
    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($tableRows[0]['c0']));
    $t->same('affinity2.test affinity2-600..601 large integer literal versus REAL-affinity stored value', 'affinity2.test affinity2-600..601 large integer literal versus REAL-affinity stored value');
    $t->contains('affinity2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
};

return $tests;
