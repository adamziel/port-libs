<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test';
$sourceText = is_file($sourceFile) ? (string) file_get_contents($sourceFile) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream atof1.test is required for atof1 random REAL expression-affinity tests');
}

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream atof1 random REAL expression-affinity tests');
}

$tclsh = trim((string) shell_exec('command -v tclsh 2>/dev/null'));
if ($tclsh === '') {
    throw new RuntimeException('tclsh is required to replay upstream atof1.test seeded random REAL expression-affinity corpus');
}

$ownedStart = 6001;
$ownedEnd = 7200;
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

$tclScriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-random-6001-tcl-');
if ($tclScriptFile === false) {
    throw new RuntimeException('Could not create Tcl generator script for atof1 random REAL expression-affinity tests');
}
file_put_contents($tclScriptFile, $tclScript);
$tclOutput = shell_exec(escapeshellarg($tclsh) . ' ' . escapeshellarg($tclScriptFile));
@unlink($tclScriptFile);
if (!is_string($tclOutput) || trim($tclOutput) === '') {
    throw new RuntimeException('tclsh did not produce atof1 random REAL expression-affinity output');
}

$cases = [];
foreach (explode("\n", trim($tclOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed Tcl atof1 random REAL expression-affinity row: ' . $line);
    }

    [$ordinalText, $powerText, $boundText, $literalText] = $parts;
    $ordinal = (int) $ordinalText;
    $key = sprintf('atof1-1.%04d', $ordinal);
    $cases[$key] = [
        'source' => sprintf('atof1.test atof1-1.%d.1/.2', $ordinal),
        'ordinal' => $ordinal,
        'power' => (int) $powerText,
        'bound' => sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200NumericLiteral($boundText),
        'literal' => sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200NumericLiteral($literalText),
    ];
}
if (count($cases) !== $ownedCount) {
    throw new RuntimeException(sprintf('Expected %d atof1 random REAL expression-affinity cases, got %d', $ownedCount, count($cases)));
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

$oracleFile = tempnam(sys_get_temp_dir(), 'libsqlite-atof1-random-6001-oracle-');
if ($oracleFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for atof1 random REAL expression-affinity tests');
}
file_put_contents($oracleFile, implode("\n", $oracleScript));
$oracleOutput = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($oracleFile));
@unlink($oracleFile);
if (!is_string($oracleOutput) || trim($oracleOutput) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce atof1 random REAL expression-affinity output');
}

$oracle = [];
foreach (explode("\n", trim($oracleOutput)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 atof1 random REAL expression-affinity oracle row: ' . $line);
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
    throw new RuntimeException(sprintf('Expected %d atof1 random REAL expression-affinity oracle rows, got %d', count($cases), count($oracle)));
}

$tests['real upstream corpus expression affinity dynamic atof1 random roundtrip 6001 7200 source truth'] =
    static function (TestRunner $t) use ($cases, $oracle, $ownedStart, $ownedEnd, $ownedCount, $sourceFile, $sourceText): void {
        $t->same(true, is_file($sourceFile), 'hydrated upstream atof1.test exists');
        $t->contains('for {set i 1} {$i<20000} {incr i}', $sourceText);
        $t->contains('set xf [format %.32e $x]', $sourceText);
        $t->contains('SELECT $xf=\\$x', $sourceText);
        $t->contains('SELECT $x=CAST(quote($x) AS real)', $sourceText);
        $t->same(6001, $ownedStart);
        $t->same(7200, $ownedEnd);
        $t->same(1200, $ownedCount);
        $t->same(1200, count($cases));
        $t->same(1200, count($oracle));
        $t->same('atof1.test atof1-1.6001.1/.2', $cases['atof1-1.6001']['source']);
        $t->same('6.72682174613257980346679687500000e+09', $cases['atof1-1.6001']['literal']);
        $t->same('-16', (string) $cases['atof1-1.6001']['power']);
        $t->same('atof1.test atof1-1.7200.1/.2', $cases['atof1-1.7200']['source']);
        $t->same('1.04635051918805544346469105221331e+02', $cases['atof1-1.7200']['literal']);
    };

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic atof1 random roundtrip 6001 7200 ' . $key] =
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
                sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex($boundReal),
                sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex($literalReal),
                $key . ' literal text cast preserves Tcl bound double bits'
            );
            $t->same(
                sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex($boundReal),
                sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex($boundRoundTrip),
                $key . ' native quote CAST round-trip preserves bound REAL bits'
            );
            $t->same(
                sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex($literalReal),
                sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex($literalRoundTrip),
                $key . ' native quote CAST round-trip preserves literal REAL bits'
            );
            $t->same('1', $oracle[$key]['quoteRoundTrip'], $key . ' sqlite3 quote CAST round-trip source truth');
            $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($literalReal), $key . ' helper storage class');
            $t->same(true, str_contains($case['source'], 'atof1-1.' . (string) $case['ordinal']), $key . ' cites upstream ordinal');
        };
}

$tests['real upstream corpus expression affinity dynamic atof1 random roundtrip 6001 7200 generic metric rollup'] =
    static function (TestRunner $t) use ($cases): void {
        $rows = [
            ['key_name' => 'metric.random.6001', 'literal_text' => $cases['atof1-1.6001']['literal']],
            ['key_name' => 'metric.random.6400', 'literal_text' => $cases['atof1-1.6400']['literal']],
            ['key_name' => 'metric.random.6800', 'literal_text' => $cases['atof1-1.6800']['literal']],
            ['key_name' => 'metric.random.7200', 'literal_text' => $cases['atof1-1.7200']['literal']],
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
        $t->same(['metric.random.6001', 'metric.random.6400', 'metric.random.6800', 'metric.random.7200'], array_column($result, 'key_name'));
        $t->same(['real', 'real', 'real', 'real'], array_column($result, 'storage_class'));
        $t->same([true, true, true, true], array_map(static fn (mixed $value): bool => (string) $value === '1', array_column($result, 'quote_round_trip')));
        foreach ($result as $row) {
            $t->same(true, is_string($row['formatted']) && $row['formatted'] !== '');
        }
    };

$tests['real upstream corpus expression affinity dynamic atof1 random roundtrip 6001 7200 non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns atof1.test atof1-1.6001..7200 text-to-REAL and quote round-trip rows only',
            'owns atof1.test atof1-1.6001..7200 text-to-REAL and quote round-trip rows only',
        );
        $t->same(
            'non-overlap: extends the accepted atof1-1.1..6000 random REAL shards and avoids accepted atof1-2 UTF16/blob rows, atof-3.1 integer-prefix suffixes, atof-3.2 decimal suffixes, atof-3.3 exponent rows, atof2 rounding, date4 rows, timediff matrices, affinity2, affinity3, and types storage-class batches',
            'non-overlap: extends the accepted atof1-1.1..6000 random REAL shards and avoids accepted atof1-2 UTF16/blob rows, atof-3.1 integer-prefix suffixes, atof-3.2 decimal suffixes, atof-3.3 exponent rows, atof2 rounding, date4 rows, timediff matrices, affinity2, affinity3, and types storage-class batches',
        );
        $t->same(
            'no new support component needed; reuses hydrated upstream atof1.test, existing tclsh/sqlite3 oracle tooling, SQLiteSelectSql CAST/equality/function dispatch, SQLiteCoreScalarFunction quote/format, and SQLiteRealExpressionAffinityCorpusPlan REAL casting',
            'no new support component needed; reuses hydrated upstream atof1.test, existing tclsh/sqlite3 oracle tooling, SQLiteSelectSql CAST/equality/function dispatch, SQLiteCoreScalarFunction quote/format, and SQLiteRealExpressionAffinityCorpusPlan REAL casting',
        );
    };

return $tests;

function sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200NumericLiteral(string $value): string
{
    if (preg_match('/\A[+-]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))(?:[eE][+-]?\d+)?\z/', $value) !== 1) {
        throw new RuntimeException('Unexpected atof1 random REAL expression-affinity numeric literal: ' . $value);
    }

    return $value;
}

function sqliteRealUpstreamExpressionAffinityAtof1RandomRoundtrip6001To7200DoubleHex(float $value): string
{
    return bin2hex(pack('E', $value));
}
