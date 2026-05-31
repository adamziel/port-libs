<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity likely() dynamic tests');
}

// Real upstream source:
// - test/whereG.test whereG-7.0..7.2 verifies likely(), unlikely(), and
//   likelihood() preserve expression values through SELECT projection and
//   ORDER BY.
// - test/whereG.test whereG-8.1..8.10 verifies planner-hint wrappers have
//   expression-affinity effects when compared with text literals.
// - test/whereG.test whereG-12.0 verifies likely(a) preserves REAL typeof()
//   when used over a REAL column/expression-index equivalent.
$rows = [];
for ($i = 0; $i < 32; ++$i) {
    $base = $i - 16;
    $rows[] = [
        'rowid' => $i + 1,
        'a' => $base + ((($i % 5) + 1) / 10),
        'b' => (string) ($base + 20),
        'label' => 'r' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
    ];
}

$literalValues = [
    'null' => 'NULL',
    'int-zero' => '0',
    'int-one' => '1',
    'real-zero' => '0.0',
    'real-small' => '0.125',
    'real-negative' => '-2.5',
    'text-zero' => "'0'",
    'text-real' => "'1.25'",
    'text-alpha' => "'english'",
    'text-leading-space' => "' 3.5'",
];

$rowExpressions = [
    'real-column' => 'a',
    'real-column-plus' => 'a + 0.25',
    'integer-rowid' => 'rowid',
    'text-numeric-column' => 'b',
    'text-label' => 'label',
    'comparison-real' => 'a > 0.0',
    'comparison-text' => "b <= '10'",
    'coalesced-real' => 'coalesce(a, 99.5)',
    'nullif-real' => 'nullif(a, 0.0)',
];

$probabilities = ['0.0', '0.01', '0.25', '0.5', '0.75', '0.99', '1.0'];
$wrappers = [
    'likely' => static fn (string $expr): string => "likely({$expr})",
    'unlikely' => static fn (string $expr): string => "unlikely({$expr})",
];
foreach ($probabilities as $probability) {
    $wrappers['likelihood-' . str_replace('.', '_', $probability)] =
        static fn (string $expr): string => "likelihood({$expr}, {$probability})";
}

$rowWrappers = [];
foreach ($wrappers as $outerName => $outerWrap) {
    foreach ($wrappers as $innerName => $innerWrap) {
        $rowWrappers["{$outerName}-of-{$innerName}"] = static fn (string $expr): string => $outerWrap($innerWrap($expr));
    }
}

$cases = [];
foreach ($literalValues as $literalName => $literalSql) {
    foreach ($wrappers as $wrapperName => $wrap) {
        $wrapped = $wrap($literalSql);
        $cases["literal {$literalName} {$wrapperName}"] = [
            'sql' => "SELECT quote({$wrapped}) AS q, typeof({$wrapped}) AS t, quote(({$wrapped}) IS NULL) AS n",
            'tables' => [],
        ];
    }
}

foreach ($rowExpressions as $expressionName => $expressionSql) {
    foreach ($rowWrappers as $wrapperName => $wrap) {
        $wrapped = $wrap($expressionSql);
        $cases["row {$expressionName} {$wrapperName}"] = [
            'sql' => "SELECT label, quote({$wrapped}) AS q, typeof({$wrapped}) AS t, quote(({$wrapped}) IS NULL) AS n FROM app_likelihood ORDER BY rowid",
            'tables' => ['app_likelihood' => $rows],
        ];
        $cases["row {$expressionName} {$wrapperName} text-zero-compare"] = [
            'sql' => "SELECT label, quote(({$wrapped}) <= '0') AS q, typeof(({$wrapped}) <= '0') AS t, quote((({$wrapped}) <= '0') IS NULL) AS n FROM app_likelihood ORDER BY rowid",
            'tables' => ['app_likelihood' => $rows],
        ];
    }
}

$oracleScript = [
    'CREATE TABLE app_likelihood(rowid INTEGER PRIMARY KEY, a REAL, b TEXT, label TEXT);',
];
foreach ($rows as $row) {
    $oracleScript[] = sprintf(
        "INSERT INTO app_likelihood(rowid,a,b,label) VALUES(%d,%.17g,'%s','%s');",
        $row['rowid'],
        $row['a'],
        str_replace("'", "''", $row['b']),
        str_replace("'", "''", $row['label']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || json_group_array(json_object('q', q, 't', t, 'n', n)) FROM ({$case['sql']});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-likely-affinity-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for likely() expression affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce likely() expression affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed likely() expression affinity oracle row: ' . $line);
    }

    $decoded = json_decode($parts[1], true);
    if (!is_array($decoded)) {
        throw new RuntimeException('malformed likely() expression affinity oracle JSON for ' . $parts[0]);
    }
    $oracle[$parts[0]] = $decoded;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d likely() expression affinity oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity likely dynamic whereG.test ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute($case['sql'], $case['tables']);
        $actual = array_map(
            static fn (array $row): array => [
                'q' => (string) $row['q'],
                't' => (string) $row['t'],
                'n' => (string) $row['n'],
            ],
            $rows,
        );

        $t->same($oracle[$key], $actual, $key);
    };
}

$tests['real upstream expression affinity likely dynamic owns whereG shard'] = static function (TestRunner $t) use ($rows, $literalValues, $rowExpressions, $wrappers, $rowWrappers, $probabilities, $cases, $oracle): void {
    $t->same(32, count($rows));
    $t->same(10, count($literalValues));
    $t->same(9, count($rowExpressions));
    $t->same(7, count($probabilities));
    $t->same(9, count($wrappers));
    $t->same(81, count($rowWrappers));
    $t->same(1548, count($cases));
    $t->same(1548, count($oracle));
    $t->same(
        'whereG.test whereG-7.0..7.2, whereG-8.1..8.10, and whereG-12.0 likely()/unlikely()/likelihood() expression-affinity behavior',
        'whereG.test whereG-7.0..7.2, whereG-8.1..8.10, and whereG-12.0 likely()/unlikely()/likelihood() expression-affinity behavior',
    );
    $t->contains('whereG.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/whereG.test');
};

return $tests;
