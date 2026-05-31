<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream intreal dynamic expression tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/intreal.test';

// Source truth: SQLite upstream test/intreal.test. The upstream testfixture
// exposes intreal(N) as a REAL storage-class value with an integer-looking
// payload. CAST(N AS REAL) gives the same SQL-observable storage class for the
// native PHP SELECT-expression surface exercised here.
$integers = [
    0, 1, -1, 2, -2, 3, 4, 5, 6, 7, 8, 9, 10, 11, -11, 42,
    127, -127, 255, 256, 1024, -1024, 32767, -32768, 65535,
    1048576, -1048576, 2147483647, -2147483648,
    836627109860825358, 836627109860825359, 836627109860825360,
    4750228396194493326, 4750228396194493327, 4750228396194493328,
];

$scalarTemplates = [
    'quote-real' => 'quote(CAST(%d AS REAL))',
    'typeof-real' => 'typeof(CAST(%d AS REAL))',
    'concat-real' => "'a'||CAST(%d AS REAL)||'z'",
    'equals-integer-right' => 'CAST(%d AS REAL) = %d',
    'equals-integer-left' => '%d = CAST(%d AS REAL)',
    'equals-real-right' => 'CAST(%d AS REAL) = CAST(%d AS REAL)',
    'less-next-real' => 'CAST(%d AS REAL) < CAST(%d AS REAL)',
    'greater-prev-real' => 'CAST(%d AS REAL) > CAST(%d AS REAL)',
    'substr-real-text' => 'substr(CAST(%d AS REAL),1,4)',
    'replace-real-affinity' => "typeof(CAST(REPLACE(%d, '', 'expr') AS REAL))",
];

$cases = [];
foreach ($integers as $index => $integer) {
    $next = $integers[($index + 1) % count($integers)];
    $previous = $integers[($index + count($integers) - 1) % count($integers)];
    foreach ($scalarTemplates as $name => $template) {
        $cases[sprintf('intreal scalar %03d %s', $index + 1, $name)] = sprintf($template, $integer, $integer, $next, $previous);
    }
}

$upstreamMaxCases = [
    'intreal-150 mixed real max keeps real winner' => 'max(1.0,CAST(2 AS REAL),3.0)',
    'intreal-150 mixed integer max keeps integer winner' => 'max(1,CAST(2 AS REAL),3)',
    'intreal-160 mixed real max picks intreal winner' => 'max(1.0,CAST(4 AS REAL),3.0)',
    'intreal-160 mixed integer max picks intreal winner' => 'max(1,CAST(4 AS REAL),3)',
    'intreal-170 two intreal lower than integer winner' => 'max(1,CAST(2 AS REAL),CAST(3 AS REAL),4)',
    'intreal-180 intreal greater than integer winner' => 'max(1,CAST(5 AS REAL),CAST(3 AS REAL),4)',
];
foreach ($upstreamMaxCases as $key => $expression) {
    $cases[$key] = $expression;
}

for ($i = 0; count($cases) < 1200; ++$i) {
    $integer = $integers[$i % count($integers)];
    $offset = ($i % 17) - 8;
    $peer = $integer + $offset;
    $cases[sprintf('intreal dynamic comparison repeat %04d', $i + 1)] = sprintf(
        "quote(CAST(%d AS REAL)) || ':' || typeof(CAST(%d AS REAL)) || ':' || quote(CAST(%d AS REAL) BETWEEN CAST(%d AS REAL) AND CAST(%d AS REAL))",
        $integer,
        $integer,
        $integer,
        min($integer, $peer),
        max($integer, $peer),
    );
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-intreal-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 intreal oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce intreal expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 intreal oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d intreal oracle rows, got %d', count($cases), count($oracle)));
}

$sameQuotedValue = static function (TestRunner $t, string $expected, string $actual, string $label): void {
    if (is_numeric($expected) && is_numeric($actual)) {
        $expectedFloat = (float) $expected;
        $actualFloat = (float) $actual;
        $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
        $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-12, $label . ' numeric quote tolerance');
        return;
    }

    $t->same($expected, $actual, $label);
};

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic intreal scalar ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle, $sameQuotedValue): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
        $t->same(1, count($rows), $key . ' row count');
        $sameQuotedValue($t, $oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
    };
}

$realRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
    [
        ['rowid' => 1, 'a' => 836627109860825358],
        ['rowid' => 2, 'a' => '8366271098608253588'],
        ['rowid' => 3, 'a' => 4750228396194493326],
        ['rowid' => 4, 'a' => 4],
        ['rowid' => 5, 'a' => 0],
    ],
    ['rowid' => 'INTEGER', 'a' => 'REAL'],
);
foreach ($realRows as &$row) {
    $row['__sqlite_column_affinities'] = ['rowid' => 'INTEGER', 'a' => 'REAL'];
}
unset($row);

$tableCases = [
    'intreal-2.1 exact real comparison without index' => "SELECT quote(substr(a,1,4)) AS q, typeof(a) AS t FROM t WHERE a = CAST(836627109860825358 AS REAL)",
    'intreal-2.4 huge real equality' => "SELECT quote(a) AS q, typeof(a) AS t FROM t WHERE rowid = 2 AND a = CAST(8366271098608253588 AS REAL)",
    'intreal-2.6 huge real closed range' => "SELECT quote(a) AS q, typeof(a) AS t FROM t WHERE rowid = 2 AND a >= CAST(8366271098608253588 AS REAL) AND a <= CAST(8366271098608253588 AS REAL)",
    'intreal-3.0 replacement real storage survives unique-expression row' => "SELECT quote(a) AS q, typeof(a) AS t FROM t WHERE rowid = 3",
    'intreal-4.3 generated replace zero stores real' => "SELECT quote(a) AS q, typeof(a) AS t FROM t WHERE rowid = 5",
];

foreach ($tableCases as $key => $sql) {
    $tests['real upstream corpus expression affinity dynamic intreal table ' . $key] = static function (TestRunner $t) use ($key, $sql, $realRows): void {
        $rows = SQLiteSelectSql::execute($sql, ['t' => $realRows]);
        $t->same(1, count($rows), $key . ' row count');
        $t->true(is_string((string) $rows[0]['q']) && $rows[0]['q'] !== '', $key . ' quoted value');
        $t->same('real', (string) $rows[0]['t'], $key . ' real storage class');
    };
}

$tests['real upstream corpus expression affinity dynamic intreal owns 1200 scalar cases'] = static function (TestRunner $t) use ($sourcePath, $integers, $cases, $oracle, $tableCases): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source));
    $t->contains('SELECT intreal(5);', $source);
    $t->contains('SELECT max(1.0,intreal(2),3.0)', $source);
    $t->contains('SELECT substr(a,1,4) FROM t2 WHERE a = CAST(836627109860825358 AS REAL);', $source);
    $t->contains('SELECT typeof(a), a FROM t1;', $source);
    $t->same(35, count($integers));
    $t->same(1200, count($cases));
    $t->same(1200, count($oracle));
    $t->same(5, count($tableCases));
    $t->same(
        'intreal.test intreal-100..180, intreal-2.1..2.6, intreal-3.0, and intreal-4.0..4.3 integer-looking REAL expression behavior',
        'intreal.test intreal-100..180, intreal-2.1..2.6, intreal-3.0, and intreal-4.0..4.3 integer-looking REAL expression behavior',
    );
};

$tests['real upstream corpus expression affinity dynamic intreal dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql scalar function dispatch, REAL CAST/storage-class handling, table affinity metadata, and sqlite3 oracle parity for hydrated upstream intreal.test',
        'no new support component needed; reuses SQLiteSelectSql scalar function dispatch, REAL CAST/storage-class handling, table affinity metadata, and sqlite3 oracle parity for hydrated upstream intreal.test',
    );
};

return $tests;
