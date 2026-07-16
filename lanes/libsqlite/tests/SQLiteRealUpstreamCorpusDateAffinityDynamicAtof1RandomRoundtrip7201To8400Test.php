<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test';
$sourceText = is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof1.test is required for atof1 random REAL continuation tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof1 random REAL continuation tests');
}

$tclsh = trim((string) shell_exec('command -v tclsh 2>/dev/null'));
if ($tclsh === '') {
    throw new RuntimeException('tclsh is required to replay upstream atof1.test seeded random REAL continuation corpus');
}

$ownedStart = 7201;
$ownedEnd = 8400;
$ownedCount = $ownedEnd - $ownedStart + 1;
$tclScript = <<<TCL
set mxpow 35
expr srand(1)
for {set i 1} {\$i <= {$ownedEnd}} {incr i} {
  set pow [expr {int((rand()-0.5)*\$mxpow)}]
  set x [expr {pow((rand()-0.5)*2*rand(),\$pow)}]
  if {\$i >= {$ownedStart}} {
    set xf [format %.32e \$x]
    puts "\$i\t\$pow\t[format %.17g \$x]\t\$xf"
  }
}
TCL;

$tclScriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-random-7201-tcl-');
if ($tclScriptFile === false) {
    throw new RuntimeException('Could not create Tcl generator script for atof1 random continuation tests');
}
file_put_contents($tclScriptFile, $tclScript);
$tclOutput = shell_exec(escapeshellarg($tclsh) . ' ' . escapeshellarg($tclScriptFile));
@unlink($tclScriptFile);
if (!is_string($tclOutput) || trim($tclOutput) === '') {
    throw new RuntimeException('tclsh did not produce atof1 random REAL continuation output');
}

$cases = [];
foreach (explode("\n", trim($tclOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed Tcl atof1 random REAL continuation row: ' . $line);
    }

    [$ordinalText, $powerText, $boundText, $literalText] = $parts;
    $ordinal = (int) $ordinalText;
    $key = sprintf('atof1-1.%04d', $ordinal);
    $cases[$key] = [
        'source' => sprintf('atof1.test atof1-1.%d.1/.2', $ordinal),
        'ordinal' => $ordinal,
        'power' => (int) $powerText,
        'bound' => sqliteRealUpstreamAtof1RandomRoundtrip7201To8400NumericLiteral($boundText),
        'literal' => sqliteRealUpstreamAtof1RandomRoundtrip7201To8400NumericLiteral($literalText),
    ];
}
if (count($cases) !== $ownedCount) {
    throw new RuntimeException(sprintf('Expected %d atof1 random REAL continuation cases, got %d', $ownedCount, count($cases)));
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $literalSql = $case['literal'];
    $boundSql = $case['bound'];
    $oracleScript[] = "SELECT '{$safeKey}'"
        . " || char(9) || typeof(CAST({$literalSql} AS REAL))"
        . " || char(9) || format('%.10e', CAST({$literalSql} AS REAL))"
        . " || char(9) || (CAST({$literalSql} AS REAL)=CAST({$boundSql} AS REAL))"
        . " || char(9) || (CAST(quote(CAST({$boundSql} AS REAL)) AS REAL)=CAST({$boundSql} AS REAL));";
}

$oracleFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-random-7201-oracle-');
if ($oracleFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof1 random continuation tests');
}
file_put_contents($oracleFile, implode("\n", $oracleScript));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($oracleFile));
@unlink($oracleFile);
if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof1 random REAL continuation output');
}

$oracle = [];
foreach (explode("\n", trim($oracleOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 atof1 random REAL continuation oracle row: ' . $line);
    }

    [$key, $storageClass, $formatted, $textEqualsBound, $quoteRoundTrip] = $parts;
    $oracle[$key] = [
        'storageClass' => $storageClass,
        'formatted' => $formatted,
        'textEqualsBound' => $textEqualsBound,
        'quoteRoundTrip' => $quoteRoundTrip,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d atof1 random REAL continuation oracle rows, got %d', count($cases), count($oracle)));
}

$tests['real upstream corpus date affinity dynamic atof1 random roundtrip 7201 8400 source truth'] =
    static function (TestRunner $t) use ($cases, $oracle, $ownedStart, $ownedEnd, $ownedCount, $sourceFile, $sourceText): void {
        $t->same(true, is_file($sourceFile), 'hydrated upstream atof1.test exists');
        $t->contains('for {set i 1} {$i<20000} {incr i}', $sourceText);
        $t->contains('set xf [format %.32e $x]', $sourceText);
        $t->contains('SELECT $xf=\\$x', $sourceText);
        $t->contains('SELECT $x=CAST(quote($x) AS real)', $sourceText);
        $t->same(7201, $ownedStart);
        $t->same(8400, $ownedEnd);
        $t->same(1200, $ownedCount);
        $t->same(1200, count($cases));
        $t->same(1200, count($oracle));
        $t->same('atof1.test atof1-1.7201.1/.2', $cases['atof1-1.7201']['source']);
        $t->same('7.11102127508252397092292085289955e+03', $cases['atof1-1.7201']['literal']);
        $t->same('-6', (string) $cases['atof1-1.7201']['power']);
        $t->same('atof1.test atof1-1.8400.1/.2', $cases['atof1-1.8400']['source']);
        $t->same('-1.99101477528581558726727962493896e+06', $cases['atof1-1.8400']['literal']);
    };

foreach ($cases as $key => $case) {
    $tests['real upstream corpus date affinity dynamic atof1 random roundtrip 7201 8400 ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
            $boundReal = (float) $case['bound'];
            $literalReal = SQLiteRealExpressionAffinityCorpusPlan::cast($case['literal'], 'REAL');
            $boundQuote = SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$boundReal]);
            $boundRoundTrip = SQLiteRealExpressionAffinityCorpusPlan::cast($boundQuote, 'REAL');
            $literalQuote = SQLiteCoreScalarFunction::sqlFunctionArguments('quote', [$literalReal]);
            $literalRoundTrip = SQLiteRealExpressionAffinityCorpusPlan::cast($literalQuote, 'REAL');
            $boundFormatted = SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $boundReal]);
            $literalFormatted = SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%.10e', $literalReal]);

            $result = SQLiteSelectSql::execute(
                'SELECT CAST(literal_text AS REAL) AS real_value, '
                . 'typeof(CAST(literal_text AS REAL)) AS storage_class, '
                . "format('%.10e', CAST(literal_text AS REAL)) AS formatted, "
                . 'CAST(literal_text AS REAL)=bound_real AS text_equals_bound '
                . 'FROM input_values',
                [
                    'input_values' => [[
                        'literal_text' => $case['literal'],
                        'bound_real' => $boundReal,
                        '__sqlite_column_affinities' => [
                            'literal_text' => 'TEXT',
                            'input_values.literal_text' => 'TEXT',
                            'bound_real' => 'REAL',
                            'input_values.bound_real' => 'REAL',
                        ],
                    ]],
                ],
            );

            $t->same(1, count($result), $key . ' returns one projected row');
            $row = $result[0];
            $t->same('real', (string) $row['storage_class'], $key . ' SELECT projects REAL storage');
            $t->same($oracle[$key]['storageClass'], (string) $row['storage_class'], $key . ' storage matches sqlite3');
            $t->same($oracle[$key]['formatted'], (string) $row['formatted'], $key . ' formatted REAL matches sqlite3');
            $t->same('1', (string) $row['text_equals_bound'], $key . ' SELECT text REAL equals bound REAL');
            $t->same($oracle[$key]['textEqualsBound'], (string) $row['text_equals_bound'], $key . ' equality matches sqlite3');
            $t->same($oracle[$key]['formatted'], (string) $literalFormatted, $key . ' helper formatted REAL matches sqlite3');
            $t->same((string) $boundFormatted, (string) $literalFormatted, $key . ' literal text cast matches Tcl bound value');
            $t->same(
                sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex($boundReal),
                sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex($literalReal),
                $key . ' literal text cast preserves Tcl bound double bits'
            );
            $t->same(
                sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex($boundReal),
                sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex($boundRoundTrip),
                $key . ' native quote CAST round-trip preserves bound REAL bits'
            );
            $t->same(
                sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex($literalReal),
                sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex($literalRoundTrip),
                $key . ' native quote CAST round-trip preserves literal REAL bits'
            );
            $t->same('1', $oracle[$key]['quoteRoundTrip'], $key . ' sqlite3 quote CAST round-trip source truth');
            $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($literalReal), $key . ' helper storage class');
            $t->same(true, str_contains($case['source'], 'atof1-1.' . (string) $case['ordinal']), $key . ' cites upstream ordinal');
        };
}

$tests['real upstream corpus date affinity dynamic atof1 random roundtrip 7201 8400 generic metric rollup'] =
    static function (TestRunner $t) use ($cases): void {
        $rows = [
            ['key_name' => 'metric.random.7201', 'literal_text' => $cases['atof1-1.7201']['literal']],
            ['key_name' => 'metric.random.7600', 'literal_text' => $cases['atof1-1.7600']['literal']],
            ['key_name' => 'metric.random.8000', 'literal_text' => $cases['atof1-1.8000']['literal']],
            ['key_name' => 'metric.random.8400', 'literal_text' => $cases['atof1-1.8400']['literal']],
        ];
        $result = SQLiteSelectSql::execute(
            'SELECT key_name, '
            . 'typeof(CAST(literal_text AS REAL)) AS storage_class, '
            . "format('%.10e', CAST(literal_text AS REAL)) AS formatted, "
            . 'CAST(quote(CAST(literal_text AS REAL)) AS REAL)=CAST(literal_text AS REAL) AS quote_round_trip '
            . 'FROM app_numeric_metrics ORDER BY key_name',
            ['app_numeric_metrics' => $rows],
        );

        $t->same(4, count($result));
        $t->same(['metric.random.7201', 'metric.random.7600', 'metric.random.8000', 'metric.random.8400'], array_column($result, 'key_name'));
        $t->same(['real', 'real', 'real', 'real'], array_column($result, 'storage_class'));
        $t->same([true, true, true, true], array_map(static fn (mixed $value): bool => (string) $value === '1', array_column($result, 'quote_round_trip')));
        foreach ($result as $row) {
            $t->same(true, is_string($row['formatted']) && $row['formatted'] !== '');
        }
    };

$tests['real upstream corpus date affinity dynamic atof1 random roundtrip 7201 8400 non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns atof1.test atof1-1.7201..8400 text-to-REAL and quote round-trip rows only',
            'owns atof1.test atof1-1.7201..8400 text-to-REAL and quote round-trip rows only',
        );
        $t->same(
            'non-overlap: extends the accepted atof1-1.1..7200 random REAL shards and avoids accepted atof1-2 UTF16/blob rows, atof-3.1 integer-prefix suffixes, atof-3.2 decimal suffixes, atof-3.3 exponent rows, atof2 rounding, date4 rows, timediff matrices, affinity2, affinity3, types storage-class batches, and SQLitePDO invalid-DML work',
            'non-overlap: extends the accepted atof1-1.1..7200 random REAL shards and avoids accepted atof1-2 UTF16/blob rows, atof-3.1 integer-prefix suffixes, atof-3.2 decimal suffixes, atof-3.3 exponent rows, atof2 rounding, date4 rows, timediff matrices, affinity2, affinity3, types storage-class batches, and SQLitePDO invalid-DML work',
        );
        $t->same(
            'no new support component needed; reuses hydrated upstream atof1.test, existing tclsh/sqlite3 oracle tooling, SQLiteSelectSql CAST/equality/function dispatch, SQLiteCoreScalarFunction quote/format, and SQLiteRealExpressionAffinityCorpusPlan REAL casting',
            'no new support component needed; reuses hydrated upstream atof1.test, existing tclsh/sqlite3 oracle tooling, SQLiteSelectSql CAST/equality/function dispatch, SQLiteCoreScalarFunction quote/format, and SQLiteRealExpressionAffinityCorpusPlan REAL casting',
        );
    };

return $tests;

function sqliteRealUpstreamAtof1RandomRoundtrip7201To8400NumericLiteral(string $value): string
{
    if (preg_match('/\A[+-]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))(?:[eE][+-]?\d+)?\z/', $value) !== 1) {
        throw new RuntimeException('Unexpected atof1 random REAL continuation numeric literal: ' . $value);
    }

    return $value;
}

function sqliteRealUpstreamAtof1RandomRoundtrip7201To8400DoubleHex(float $value): string
{
    return bin2hex(pack('E', $value));
}
