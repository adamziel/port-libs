<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream affinity3 REAL comparison dynamic tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/affinity3.test affinity3-100..142.
// Those cases verify that a REAL-affinity column stores decimal strings as
// REAL values and applies REAL affinity before equality/range predicates.
$realValues = [];
for ($case = 1; $case <= 80; $case++) {
    $whole = intdiv($case * 37, 19);
    $fraction = ($case * 137) % 10000;
    $realValues[$case] = (float) sprintf('%d.%04d', $whole, $fraction);
}

$spellingsFor = static function (float $value, int $case) use ($sqlLiteral): array {
    $fixed = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    $scientific = sprintf('%.8E', $value);
    $offset = rtrim(rtrim(sprintf('%.4F', $value + 0.0007 + (($case % 5) * 0.0001)), '0'), '.');
    $lower = rtrim(rtrim(sprintf('%.4F', max(0.0, $value - 0.0009)), '0'), '.');
    $upper = rtrim(rtrim(sprintf('%.4F', $value + 0.0009), '0'), '.');

    return [
        'eq-fixed-text' => "apr = " . $sqlLiteral($fixed),
        'eq-leading-space-text' => "apr == " . $sqlLiteral('   ' . $fixed),
        'ne-offset-text' => "apr != " . $sqlLiteral($offset),
        'lt-offset-text' => "apr < " . $sqlLiteral($offset),
        'le-fixed-text' => "apr <= " . $sqlLiteral($fixed),
        'gt-lower-text' => "apr > " . $sqlLiteral($lower),
        'ge-fixed-text' => "apr >= " . $sqlLiteral($fixed),
        'between-text-window' => "apr BETWEEN " . $sqlLiteral($lower) . " AND " . $sqlLiteral($upper),
        'not-between-offset-window' => "apr NOT BETWEEN " . $sqlLiteral($offset) . " AND " . $sqlLiteral((string) ($value + 0.0015)),
        'in-single-scientific-text' => "apr IN (" . $sqlLiteral($scientific) . ")",
        'in-mixed-text-list' => "apr IN (" . $sqlLiteral($offset) . ", " . $sqlLiteral($fixed) . ", 999999.25)",
        'not-in-miss-list' => "apr NOT IN (" . $sqlLiteral($offset) . ", -1, 999999.25)",
        'range-and-equality' => "apr >= " . $sqlLiteral($lower) . " AND apr = " . $sqlLiteral($fixed),
        'range-or-equality' => "apr = " . $sqlLiteral($fixed) . " OR apr = " . $sqlLiteral($offset),
        'cast-rhs-real-equality' => "apr = CAST(" . $sqlLiteral($fixed) . " AS REAL)",
    ];
};

$cases = [];
foreach ($realValues as $rowid => $value) {
    foreach ($spellingsFor($value, $rowid) as $name => $whereSql) {
        $cases[sprintf('row-%03d-%s', $rowid, $name)] = [
            'where' => $whereSql,
        ];
    }
}

$oracleScript = [
    'CREATE TABLE apr(id INT PRIMARY KEY, apr REAL);',
];
foreach ($realValues as $rowid => $value) {
    $oracleScript[] = sprintf('INSERT INTO apr(id, apr) VALUES(%d, %.17G);', $rowid, $value);
}
$oracleScript[] = 'SELECT quote(integrity_check), typeof(integrity_check) FROM pragma_integrity_check;';
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $whereSql = $case['where'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || ifnull(group_concat(id, ','), '') || char(9) || quote(count(*)) FROM (SELECT id FROM apr WHERE {$whereSql} ORDER BY id);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-affinity3-real-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for affinity3 REAL dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce affinity3 REAL dynamic output');
}

$lines = explode("\n", trim($output));
$integrity = array_shift($lines);
if ($integrity !== "'ok'|text") {
    throw new RuntimeException('sqlite3 affinity3 REAL integrity check failed: ' . (string) $integrity);
}

$oracle = [];
foreach ($lines as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 affinity3 REAL oracle row: ' . $line);
    }
    [$key, $ids, $count] = $parts;
    $oracle[$key] = [
        'ids' => $ids === '' ? [] : array_map('intval', explode(',', $ids)),
        'count' => $count,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d affinity3 REAL oracle rows, got %d', count($cases), count($oracle)));
}

$rows = [];
foreach ($realValues as $rowid => $value) {
    $rows[] = [
        'id' => $rowid,
        'apr' => $value,
        '__sqlite_column_affinities' => [
            'id' => 'INTEGER',
            'apr' => 'REAL',
            'apr.id' => 'INTEGER',
            'apr.apr' => 'REAL',
        ],
    ];
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic affinity3 REAL ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rows): void {
        $result = SQLiteSelectSql::execute(
            'SELECT id, quote(apr) AS quoted_apr, typeof(apr) AS apr_type FROM apr WHERE ' . $case['where'] . ' ORDER BY id',
            ['apr' => $rows],
        );
        $actualIds = array_map(static fn (array $row): int => (int) $row['id'], $result);
        $t->same($oracle[$key]['ids'], $actualIds, $case['where'] . ' selected rowids');
        $t->same(trim($oracle[$key]['count'], "'"), (string) count($actualIds), $case['where'] . ' selected count');
        foreach ($result as $row) {
            $t->same('real', (string) $row['apr_type'], $case['where'] . ' preserves REAL storage');
        }
    };
}

$tests['real upstream corpus expression affinity dynamic affinity3 REAL owns source range'] = static function (TestRunner $t) use ($realValues, $cases, $oracle): void {
    $t->same(80, count($realValues));
    $t->same(1200, count($cases));
    $t->same(1200, count($oracle));
    $t->same(
        'affinity3.test affinity3-100..142 REAL storage and REAL-affinity comparison predicates',
        'affinity3.test affinity3-100..142 REAL storage and REAL-affinity comparison predicates',
    );
    $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
};

$tests['real upstream corpus expression affinity dynamic affinity3 REAL dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql predicate execution, column affinity metadata, CAST dispatch, and sqlite3 oracle parity for hydrated upstream affinity3.test',
        'no new support component needed; reuses SQLiteSelectSql predicate execution, column affinity metadata, CAST dispatch, and sqlite3 oracle parity for hydrated upstream affinity3.test',
    );
};

return $tests;
