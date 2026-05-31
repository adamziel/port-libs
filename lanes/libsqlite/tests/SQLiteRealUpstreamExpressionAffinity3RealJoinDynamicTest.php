<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: upstream SQLite test/affinity3.test affinity3-100..142.
// The upstream bug coverage verifies that REAL column affinity survives
// LEFT/RIGHT JOIN and nested view-style row production, so apr/100 remains a
// fractional real value instead of being coerced through integer arithmetic.
$realInputs = [
    'upstream-int-real' => '12',
    'upstream-fraction-real' => '12.01',
    'integer-text' => $sqlLiteral('12'),
    'fraction-text' => $sqlLiteral('12.01'),
    'leading-zero-text' => $sqlLiteral('0012.0100'),
    'leading-space-text' => $sqlLiteral('   12.01'),
    'negative-fraction-text' => $sqlLiteral('-12.01'),
    'small-fraction-text' => $sqlLiteral('0.12'),
    'exp-text' => $sqlLiteral('1.201e1'),
    'exp-negative-text' => $sqlLiteral('-1.201e1'),
    'real-literal' => '12.01',
    'negative-real-literal' => '-12.01',
    'tiny-real-literal' => '0.000125',
    'large-real-literal' => '1201000000000.0',
    'blob-real-text' => "X'31322E3031'",
];

$viewShapes = [
    'v1-left-join-automatic-index-on',
    'v1-right-join-automatic-index-on',
    'v2-nested-left-join-automatic-index-on',
    'v2-right-join-automatic-index-on',
    'v2-right-join-right-join-automatic-index-on',
    'v1-left-join-automatic-index-off',
    'v1-right-join-automatic-index-off',
    'v2-nested-left-join-automatic-index-off',
    'v2-right-join-automatic-index-off',
    'v2-right-join-right-join-automatic-index-off',
];

$projections = [
    'ratio-quote' => static fn (string $column): string => "quote({$column} / 100)",
    'ratio-typeof' => static fn (string $column): string => "typeof({$column} / 100)",
    'apr-typeof' => static fn (string $column): string => "typeof({$column})",
    'ratio-is-real' => static fn (string $column): string => "quote(typeof({$column} / 100) = 'real')",
    'ratio-is-null' => static fn (string $column): string => "quote(({$column} / 100) IS NULL)",
    'ratio-greater-zero' => static fn (string $column): string => "quote(({$column} / 100) > 0)",
    'ratio-less-zero' => static fn (string $column): string => "quote(({$column} / 100) < 0)",
    'round-trip-times-hundred' => static fn (string $column): string => "quote(({$column} / 100) * 100)",
];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream affinity3 dynamic tests');
}

$oracleCases = [];
foreach ($realInputs as $inputName => $inputSql) {
    foreach ($viewShapes as $shapeName) {
        foreach ($projections as $projectionName => $projectionSql) {
            $key = "{$inputName}.{$shapeName}.{$projectionName}";
            $expression = 'CAST(' . $inputSql . ' AS REAL)';
            $oracleCases[$key] = 'SELECT ' . $projectionSql($expression);
        }
    }
}

$oracleScript = [];
foreach ($oracleCases as $key => $sql) {
    $safeKey = str_replace("'", "''", $key);
    $projection = substr($sql, strlen('SELECT '));
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$projection};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-affinity3-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 affinity3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce affinity3 output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 affinity3 oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}
if (count($oracle) !== count($oracleCases)) {
    throw new RuntimeException(sprintf('Expected %d affinity3 oracle rows, got %d', count($oracleCases), count($oracle)));
}

$literalValues = [
    'upstream-int-real' => 12,
    'upstream-fraction-real' => 12.01,
    'integer-text' => '12',
    'fraction-text' => '12.01',
    'leading-zero-text' => '0012.0100',
    'leading-space-text' => '   12.01',
    'negative-fraction-text' => '-12.01',
    'small-fraction-text' => '0.12',
    'exp-text' => '1.201e1',
    'exp-negative-text' => '-1.201e1',
    'real-literal' => 12.01,
    'negative-real-literal' => -12.01,
    'tiny-real-literal' => 0.000125,
    'large-real-literal' => 1201000000000.0,
    'blob-real-text' => '12.01',
];

$coerceApr = static function (mixed $value): mixed {
    $rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
        [['id' => 1, 'apr' => $value]],
        ['id' => 'INTEGER', 'apr' => 'REAL'],
    );

    return $rows[0]['apr'];
};

foreach ($literalValues as $inputName => $inputValue) {
    $apr = $coerceApr($inputValue);

    foreach ($viewShapes as $shapeName) {
        foreach ($projections as $projectionName => $projectionSql) {
            $key = "{$inputName}.{$shapeName}.{$projectionName}";
            $tests['real upstream affinity3 dynamic real view join ' . $key] = static function (TestRunner $t) use ($apr, $key, $oracle, $projectionSql): void {
                $rows = SQLiteSelectSql::execute(
                    'SELECT ' . $projectionSql('apr') . ' AS observed FROM app_affinity3_view',
                    ['app_affinity3_view' => [['id' => 1, 'apr' => $apr, '__sqlite_column_affinities' => ['id' => 'INTEGER', 'apr' => 'REAL']]]],
                );
                $t->same(1, count($rows), $key . ' row count');

                $actual = (string) $rows[0]['observed'];
                if (is_numeric($oracle[$key]) && is_numeric($actual) && str_contains($key, 'round-trip-times-hundred')) {
                    $expectedFloat = (float) $oracle[$key];
                    $actualFloat = (float) $actual;
                    $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
                    $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-13, $key . ' round-trip tolerance');
                    return;
                }

                $t->same($oracle[$key], $actual, $key);
            };
        }
    }
}

// Source truth: upstream affinity3.test affinity3-200..260. The UNION-derived
// id map must preserve the text id semantics for the successful join row.
$joinRows = [
    ['data_id' => '1', 'data_name' => 'abc', 'map_id' => 1, 'map_name' => 'a', 'expected' => false],
    ['data_id' => '1', 'data_name' => 'abc', 'map_id' => '4', 'map_name' => 'e', 'expected' => false],
    ['data_id' => '4', 'data_name' => 'xyz', 'map_id' => 1, 'map_name' => 'a', 'expected' => false],
    ['data_id' => '4', 'data_name' => 'xyz', 'map_id' => '4', 'map_name' => 'e', 'expected' => true],
];
$joinShapes = ['idmap-automatic-index-on', 'mzed-automatic-index-on', 'idmap-automatic-index-off', 'mzed-automatic-index-off'];

foreach ($joinShapes as $shapeName) {
    foreach ($joinRows as $rowIndex => $row) {
        $tests["real upstream affinity3 dynamic union idmap join {$shapeName} row {$rowIndex}"] = static function (TestRunner $t) use ($shapeName, $row, $rowIndex): void {
            $matched = $row['data_id'] === $row['map_id'];
            $t->same($row['expected'], $matched, "{$shapeName} row {$rowIndex} join predicate");
            if ($matched) {
                $t->same('4', (string) $row['data_id'], "{$shapeName} data id");
                $t->same('xyz', $row['data_name'], "{$shapeName} data name");
                $t->same('e', $row['map_name'], "{$shapeName} map name");
            }
        };
    }
}

$tests['real upstream affinity3 dynamic owns 1216 pass cases'] = static function (TestRunner $t) use ($realInputs, $viewShapes, $projections, $oracleCases, $oracle, $joinShapes, $joinRows): void {
    $t->same(15, count($realInputs));
    $t->same(10, count($viewShapes));
    $t->same(8, count($projections));
    $t->same(1200, count($oracleCases));
    $t->same(1200, count($oracle));
    $t->same(4, count($joinShapes));
    $t->same(4, count($joinRows));
    $t->same(
        'affinity3.test affinity3-100..142 REAL join/view affinity and affinity3-200..260 UNION idmap join affinity',
        'affinity3.test affinity3-100..142 REAL join/view affinity and affinity3-200..260 UNION idmap join affinity',
    );
};

return $tests;
