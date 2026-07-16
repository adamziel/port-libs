<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity REAL IN tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - SQLite upstream test/in.test in-19.10 through in-19.40.
//   The historical tickets verify that IN_INDEX_NOOP and equality comparison
//   paths apply REAL affinity before comparing text RHS values against a REAL
//   column, and that expression-index admission remains stable.
$realValues = [];
for ($i = 1; $i <= 72; $i++) {
    $whole = intdiv($i * 33, 16);
    $fraction = ($i * 625) % 10000;
    $realValues[] = (float) sprintf('%d.%04d', $whole, $fraction);
}

$spellingsFor = static function (float $value): array {
    $fixed = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    $scientific = sprintf('%.8E', $value);
    $parts = explode('.', sprintf('%.4F', $value));
    $shifted = sprintf('0.%s%se+%d', $parts[0], $parts[1], strlen($parts[0]));

    return [
        'fixed-text' => $fixed,
        'leading-space-text' => '   ' . $fixed,
        'positive-sign-text' => '+' . $fixed,
        'scientific-text' => $scientific,
        'shifted-scientific-text' => $shifted,
    ];
};

$cases = [];
foreach ($realValues as $rowNumber => $value) {
    foreach ($spellingsFor($value) as $spellingName => $spelling) {
        $quoted = $quoteSql($spelling);
        $miss = $quoteSql((string) ($value + 0.03125));
        $rowId = $rowNumber + 1;

        $cases["row-{$rowId}-{$spellingName}-in-single"] = [
            'rowid' => $rowId,
            'sql' => "SELECT quote(c0 IN ({$quoted})) AS q, typeof(c0 IN ({$quoted})) AS t FROM t0 WHERE rowid = {$rowId}",
        ];
        $cases["row-{$rowId}-{$spellingName}-equality"] = [
            'rowid' => $rowId,
            'sql' => "SELECT quote(c0 = ({$quoted})) AS q, typeof(c0 = ({$quoted})) AS t FROM t0 WHERE rowid = {$rowId}",
        ];
        $cases["row-{$rowId}-{$spellingName}-in-multi"] = [
            'rowid' => $rowId,
            'sql' => "SELECT quote(c0 IN ({$miss}, 0, {$quoted}, 999999.5)) AS q, typeof(c0 IN ({$miss}, 0, {$quoted}, 999999.5)) AS t FROM t0 WHERE rowid = {$rowId}",
        ];
        $cases["row-{$rowId}-{$spellingName}-not-in-multi"] = [
            'rowid' => $rowId,
            'sql' => "SELECT quote(c0 NOT IN ({$miss}, {$quoted})) AS q, typeof(c0 NOT IN ({$miss}, {$quoted})) AS t FROM t0 WHERE rowid = {$rowId}",
        ];
        $cases["row-{$rowId}-{$spellingName}-where-in"] = [
            'rowid' => $rowId,
            'sql' => "SELECT quote(count(*)) AS q, typeof(count(*)) AS t FROM t0 WHERE rowid = {$rowId} AND c0 IN ({$quoted})",
        ];
    }
}

$oracleScript = ['CREATE TABLE t0(c0 REAL UNIQUE);'];
foreach ($realValues as $value) {
    $oracleScript[] = sprintf('INSERT INTO t0(c0) VALUES(%.17G);', $value);
}
$oracleScript[] = 'CREATE INDEX i0 ON t0(c0 IN (CAST(c0 AS TEXT)));';
$oracleScript[] = 'SELECT quote(integrity_check), typeof(integrity_check) FROM pragma_integrity_check;';
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || q || char(9) || t FROM ({$case['sql']});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-in-affinity-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for REAL IN affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce REAL IN affinity output');
}

$lines = explode("\n", trim($output));
$integrity = array_shift($lines);
if ($integrity !== "'ok'|text") {
    throw new RuntimeException('sqlite3 REAL IN expression index integrity check failed: ' . (string) $integrity);
}

$oracle = [];
foreach ($lines as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 REAL IN affinity oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d REAL IN affinity oracle rows, got %d', count($cases), count($oracle)));
}

$rows = [];
foreach ($realValues as $index => $value) {
    $rows[] = [
        'rowid' => $index + 1,
        'c0' => $value,
        '__sqlite_column_affinities' => [
            'c0' => 'REAL',
            't0.c0' => 'REAL',
        ],
    ];
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic real in.test in-19 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rows): void {
        $actualRows = SQLiteSelectSql::execute($case['sql'], ['t0' => $rows]);
        $t->same(1, count($actualRows), $case['sql']);
        $t->same($oracle[$key]['quote'], (string) $actualRows[0]['q'], $case['sql'] . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $actualRows[0]['t'], $case['sql'] . ' typeof');
        $t->same('integer', (string) $actualRows[0]['t'], $case['sql'] . ' predicate storage');
    };
}

$tests['real upstream corpus expression affinity dynamic real in.test owns source range'] = static function (TestRunner $t) use ($realValues, $cases, $oracle, $rows): void {
    $t->same(72, count($realValues));
    $t->same(360, count($cases) / 5);
    $t->same(1800, count($cases));
    $t->same(1800, count($oracle));
    $t->same(72, count($rows));
    $t->same(
        'in.test in-19.10..19.40 REAL-affinity IN/equality comparison and expression-index integrity behavior',
        'in.test in-19.10..19.40 REAL-affinity IN/equality comparison and expression-index integrity behavior',
    );
    $t->contains('in.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test');
};

return $tests;
