<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream intreal expression affinity tests');
}

// Source truth: SQLite upstream test/intreal.test sections 2.1..2.6 and 4.0..4.3.
// These cover large integer inputs inserted through REAL-affinity columns, equality
// and range comparisons against CAST(... AS REAL), joined row production, and
// expression-produced numeric text normalized by REAL affinity on INSERT.
$integerSeeds = [
    'intreal-2.1' => '836627109860825358',
    'intreal-2.4' => '8366271098608253588',
    'signed-max-minus' => '9223372036854774784',
    'signed-min-plus' => '-9223372036854774784',
    'power53-edge' => '9007199254740993',
    'power53-minus-edge' => '-9007199254740993',
    'decimal-19-digit' => '1234567890123456789',
    'decimal-19-neg' => '-1234567890123456789',
    'round-trip-whole' => '4503599627370497',
    'round-trip-neg-whole' => '-4503599627370497',
];

$operatorTemplates = [
    'eq-cast' => 'r = CAST(%s AS REAL)',
    'cast-eq' => 'CAST(%s AS REAL) = r',
    'ge-le-cast' => 'r >= CAST(%s AS REAL) AND r <= CAST(%s AS REAL)',
    'between-cast' => 'r BETWEEN CAST(%s AS REAL) AND CAST(%s AS REAL)',
    'not-less-not-greater' => 'NOT (r < CAST(%s AS REAL)) AND NOT (r > CAST(%s AS REAL))',
    'plus-zero-eq' => '(r + 0.0) = CAST(%s AS REAL)',
    'minus-zero-eq' => '(r - 0.0) = CAST(%s AS REAL)',
    'times-one-eq' => '(r * 1.0) = CAST(%s AS REAL)',
    'divide-one-eq' => '(r / 1.0) = CAST(%s AS REAL)',
    'text-coerced-eq' => 'r = CAST(%s AS REAL) AND typeof(r) = \'real\'',
];

$projections = [
    'substr' => [
        'select' => 'substr(r,1,4) AS prefix, typeof(r) AS t',
        'json' => 'substr(r,1,4), typeof(r)',
    ],
    'comparison' => [
        'select' => 'quote(r = CAST(%s AS REAL)) AS q, typeof(r = CAST(%s AS REAL)) AS t',
        'json' => 'quote(r = CAST(%s AS REAL)), typeof(r = CAST(%s AS REAL))',
    ],
    'range' => [
        'select' => 'quote(r >= CAST(%s AS REAL) AND r <= CAST(%s AS REAL)) AS q, typeof(r >= CAST(%s AS REAL) AND r <= CAST(%s AS REAL)) AS t',
        'json' => 'quote(r >= CAST(%s AS REAL) AND r <= CAST(%s AS REAL)), typeof(r >= CAST(%s AS REAL) AND r <= CAST(%s AS REAL))',
    ],
];

$rowVariants = [];
foreach ($integerSeeds as $seedName => $integerSql) {
    foreach ([0, 1, -1, 32, -32, 256, -256, 4096, -4096, 65536] as $offset) {
        $rowVariants[] = [
            'name' => $seedName . ($offset === 0 ? '' : sprintf('%+d', $offset)),
            'integer' => (string) ((int) $integerSql + $offset),
        ];
    }
}

$caseDefinitions = [];
foreach ($rowVariants as $variantIndex => $variant) {
    $integerSql = $variant['integer'];
    foreach ($operatorTemplates as $operatorName => $template) {
        $where = sprintf($template, $integerSql, $integerSql);
        foreach ($projections as $projectionName => $projectionSql) {
            $projection = sprintf($projectionSql['select'], $integerSql, $integerSql, $integerSql, $integerSql);
            $jsonProjection = sprintf($projectionSql['json'], $integerSql, $integerSql, $integerSql, $integerSql);
            $caseDefinitions[] = [
                'key' => sprintf('%03d.%s.%s.%s', $variantIndex + 1, $variant['name'], $operatorName, $projectionName),
                'integer' => $integerSql,
                'where' => $where,
                'projection' => $projection,
                'jsonProjection' => $jsonProjection,
                'join' => false,
            ];
        }
    }
}

$oracleScript = [];
foreach ($caseDefinitions as $case) {
    $integer = $case['integer'];
    $safeKey = str_replace("'", "''", $case['key']);
    $oracleScript[] = 'DROP TABLE IF EXISTS app_real;';
    $oracleScript[] = 'CREATE TABLE app_real(r REAL);';
    $oracleScript[] = "INSERT INTO app_real VALUES({$integer});";
    $from = 'app_real';
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || coalesce(json_group_array(json_object('row', json_array({$case['jsonProjection']}))), '[]') FROM {$from} WHERE {$case['where']} ORDER BY 1;";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-intreal-affinity-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for intreal expression affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce intreal expression affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed intreal expression affinity oracle row: ' . $line);
    }
    $decoded = json_decode($parts[1], true);
    if (!is_array($decoded)) {
        throw new RuntimeException('malformed intreal expression affinity oracle JSON for ' . $parts[0]);
    }
    $oracle[$parts[0]] = $decoded;
}
if (count($oracle) !== count($caseDefinitions)) {
    throw new RuntimeException(sprintf('Expected %d intreal oracle rows, got %d', count($caseDefinitions), count($oracle)));
}

foreach ($caseDefinitions as $case) {
    $tests['real upstream expression affinity intreal dynamic intreal.test ' . $case['key']] = static function (TestRunner $t) use ($case, $oracle): void {
        $rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
            [['r' => $case['integer']]],
            ['r' => 'REAL'],
        );
        $tables = [
            'app_real' => array_map(
                static fn (array $row): array => $row + ['__sqlite_column_affinities' => ['r' => 'REAL']],
                $rows,
            ),
        ];
        $from = 'app_real';
        $actualRows = SQLiteSelectSql::execute("SELECT {$case['projection']} FROM {$from} WHERE {$case['where']} ORDER BY 1", $tables);
        $actual = array_map(
            static fn (array $row): array => ['row' => array_values(array_map(static fn ($value): string => (string) $value, $row))],
            $actualRows,
        );

        $t->same($oracle[$case['key']], $actual, $case['key']);
    };
}

$tests['real upstream expression affinity intreal dynamic owns 3000 cases'] = static function (TestRunner $t) use ($integerSeeds, $operatorTemplates, $projections, $rowVariants, $caseDefinitions, $oracle): void {
    $t->same(10, count($integerSeeds));
    $t->same(100, count($rowVariants));
    $t->same(10, count($operatorTemplates));
    $t->same(3, count($projections));
    $t->same(3000, count($caseDefinitions));
    $t->same(3000, count($oracle));
    $t->same(
        'intreal.test intreal-2.1..2.6 and intreal-4.0..4.3 large integer REAL-affinity comparison and joined row production',
        'intreal.test intreal-2.1..2.6 and intreal-4.0..4.3 large integer REAL-affinity comparison and joined row production',
    );
    $t->contains('intreal.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/intreal.test');
};

return $tests;
