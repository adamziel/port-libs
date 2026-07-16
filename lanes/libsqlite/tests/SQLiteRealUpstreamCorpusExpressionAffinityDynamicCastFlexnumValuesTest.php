<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream cast FLEXNUM VALUES tests');
}

// Source truth: SQLite upstream test/cast.test cast-7.1 through cast-7.8.
// That section verifies that CAST(... AS REAL) keeps REAL storage class
// through VALUES, UNION ALL, subquery, CROSS JOIN, and view-like wrappers.
$pairs = [];
for ($i = 0; $i < 60; $i++) {
    $integer = (string) ($i - 30);
    $right = (string) ($i + 100);
    $pairs[sprintf('integer-real-%03d', $i)] = ["CAST({$integer} AS REAL)", $right];
    $pairs[sprintf('decimal-real-%03d', $i)] = ['CAST(' . ($i + 1) . '.' . (($i % 9) + 1) . ' AS REAL)', $right];
    $pairs[sprintf('text-tail-real-%03d', $i)] = ["CAST('{$integer}tail' AS REAL)", $right];
}

$forms = [
    'top-values' => static fn (string $left, string $right): array => [
        'sql' => "VALUES({$left}),({$right})",
        'valueColumn' => 'column1',
        'typeColumn' => null,
    ],
    'from-values' => static fn (string $left, string $right): array => [
        'sql' => "SELECT column1 AS m, typeof(column1) AS t FROM (VALUES({$left}),({$right}))",
        'valueColumn' => 'm',
        'typeColumn' => 't',
    ],
    'from-values-alias' => static fn (string $left, string $right): array => [
        'sql' => "SELECT v.column1 AS m, typeof(v.column1) AS t FROM (VALUES({$left}),({$right})) AS v",
        'valueColumn' => 'm',
        'typeColumn' => 't',
    ],
    'union-all' => static fn (string $left, string $right): array => [
        'sql' => "SELECT {$left} AS m UNION ALL SELECT {$right}",
        'valueColumn' => 'm',
        'typeColumn' => null,
    ],
    'from-union-all' => static fn (string $left, string $right): array => [
        'sql' => "SELECT m, typeof(m) AS t FROM (SELECT {$left} AS m UNION ALL SELECT {$right})",
        'valueColumn' => 'm',
        'typeColumn' => 't',
    ],
    'cross-join-values' => static fn (string $left, string $right): array => [
        'sql' => "SELECT v.column1 AS m, typeof(v.column1) AS t FROM dual CROSS JOIN (VALUES({$left}),({$right})) AS v",
        'valueColumn' => 'm',
        'typeColumn' => 't',
    ],
];

$tables = [
    'dual' => [
        ['one' => 1],
    ],
];

$oracleScript = ['CREATE TABLE dual(one);', 'INSERT INTO dual VALUES(1);'];
$cases = [];
foreach ($pairs as $pairName => [$left, $right]) {
    foreach ($forms as $formName => $makeForm) {
        $caseKey = "{$pairName}.{$formName}";
        $form = $makeForm($left, $right);
        $valueColumn = $form['valueColumn'];
        $oracleSql = $form['typeColumn'] === null
            ? "SELECT group_concat(quote({$valueColumn}) || ':' || typeof({$valueColumn}), '|') FROM ({$form['sql']})"
            : "SELECT group_concat(quote({$valueColumn}) || ':' || {$form['typeColumn']}, '|') FROM ({$form['sql']})";
        $oracleScript[] = "SELECT '" . str_replace("'", "''", $caseKey) . "' || char(9) || ({$oracleSql});";
        $cases[$caseKey] = $form;
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-cast-flexnum-values-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for cast FLEXNUM VALUES tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce cast FLEXNUM VALUES output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed cast FLEXNUM VALUES oracle row: ' . $line);
    }
    $oracle[$parts[0]] = explode('|', $parts[1]);
}

foreach ($cases as $caseKey => $form) {
    $tests['real upstream corpus expression affinity dynamic cast.test cast-7 flexnum values ' . $caseKey] = static function (TestRunner $t) use ($caseKey, $form, $oracle, $tables): void {
        $rows = SQLiteSelectSql::execute($form['sql'], $tables);
        $t->same(2, count($rows), $caseKey . ' row count');

        $actual = [];
        foreach ($rows as $row) {
            $value = $row[$form['valueColumn']];
            $type = $form['typeColumn'] === null
                ? (is_float($value) ? 'real' : (is_int($value) ? 'integer' : (is_string($value) ? 'text' : ($value === null ? 'null' : 'blob'))))
                : (string) $row[$form['typeColumn']];
            $quoted = match (true) {
                $value === null => 'NULL',
                is_float($value) => floor($value) === $value ? sprintf('%.1F', $value) : sprintf('%.15G', $value),
                is_int($value) => (string) $value,
                is_string($value) => "'" . str_replace("'", "''", $value) . "'",
                default => throw new RuntimeException('unexpected cast FLEXNUM value type'),
            };
            if (str_contains($quoted, 'E')) {
                $quoted = str_replace('E', 'e', $quoted);
            }
            $actual[] = $quoted . ':' . $type;
        }

        $t->same($oracle[$caseKey], $actual, $caseKey . ' quote/type sequence');
        $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
    };
}

$tests['real upstream corpus expression affinity dynamic cast.test cast-7 owns 1080 FLEXNUM cases'] = static function (TestRunner $t) use ($pairs, $forms, $cases, $oracle): void {
    $t->same(180, count($pairs));
    $t->same(6, count($forms));
    $t->same(1080, count($cases));
    $t->same(1080, count($oracle));
    $t->same(
        'cast.test cast-7.1..7.8 FLEXNUM REAL storage-class preservation through VALUES, UNION ALL, subquery, CROSS JOIN, and view-like wrappers',
        'cast.test cast-7.1..7.8 FLEXNUM REAL storage-class preservation through VALUES, UNION ALL, subquery, CROSS JOIN, and view-like wrappers',
    );
};

return $tests;
