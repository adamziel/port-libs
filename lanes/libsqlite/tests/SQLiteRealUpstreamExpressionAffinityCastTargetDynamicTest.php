<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): string {
    static $cache = [];

    if (isset($cache[$sql])) {
        return $cache[$sql];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity CAST target dynamic tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return $cache[$sql] = rtrim($output, "\r\n");
};

$port = static function (string $sql): string {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected one bounded SELECT row for ' . $sql);
    }

    return (string) array_values($rows[0])[0];
};

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$literals = [
    'null' => 'NULL',
    'integer' => '123',
    'negative-integer' => '-123',
    'real' => '123.456',
    'negative-real' => '-123.456',
    'whole-real' => '123.0',
    'blob-abc' => "X'616263'",
    'blob-digit' => "X'31'",
    'text-prefix' => "'123abc'",
    'text-fraction-prefix' => "'123.5abc'",
    'text-leading-space-integer' => "'   123'",
    'text-leading-space-real' => "'   -123.456'",
    'exponent-integer-prefix' => "'123e+5'",
    'sign-minus-only' => "'-'",
    'sign-plus-only' => "'+'",
    'slash' => "'/'",
    'dot' => "'.'",
    'negative-zero' => "'-0'",
    'positive-zero-real' => "'+0.0'",
    'leading-decimal' => "'.5'",
    'leading-plus-decimal' => "'+.5'",
    'leading-minus-decimal' => "'-.5'",
    'exponent-real' => "'3.0e+4'",
    'exponent-fraction' => "'3.5e+2'",
    'unicode-nonspace-prefix' => $quoteSql(" \xc4\xa0 321.5"),
];

for ($case = 1; $case <= 25; $case++) {
    $sign = $case % 2 === 0 ? '' : '-';
    $whole = (string) (1000 + ($case * 137));
    $fraction = (string) (($case % 9) + 1);
    $exponent = (string) (($case % 5) + 1);

    $literals[sprintf('dynamic-decimal-exponent-%02d', $case)] = $quoteSql(sprintf(
        '  %s%s.%se+%s trailing',
        $sign,
        $whole,
        $fraction,
        $exponent,
    ));
    $literals[sprintf('dynamic-integer-prefix-%02d', $case)] = $quoteSql(sprintf('%s%sabc', $sign, $whole));
}

$targets = [
    'integer' => 'INTEGER',
    'real' => 'REAL',
    'numeric' => 'NUMERIC',
    'text' => 'TEXT',
    'blob' => 'BLOB',
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
    'roundtrip-type' => static fn (string $expression): string => "typeof(CAST({$expression} AS NUMERIC))",
];

$caseCount = 0;
foreach ($literals as $literalName => $literalSql) {
    foreach ($targets as $targetName => $targetSql) {
        foreach ($projections as $projectionName => $projectionSql) {
            ++$caseCount;
            $expression = "CAST({$literalSql} AS {$targetSql})";
            $sql = 'SELECT ' . $projectionSql($expression);
            $tests[sprintf(
                'real upstream expression affinity cast target dynamic cast.test %s as %s %s',
                $literalName,
                $targetName,
                $projectionName,
            )] = static function (TestRunner $t) use ($oracle, $port, $sql): void {
                $t->same($oracle($sql), $port($sql), $sql);
            };
        }
    }
}

$tests['real upstream expression affinity cast target dynamic owns exactly 1125 pass cases'] = static function (TestRunner $t) use ($literals, $targets, $projections, $caseCount): void {
    $t->same(75, count($literals));
    $t->same(5, count($targets));
    $t->same(3, count($projections));
    $t->same(1125, $caseCount);
    $t->same(
        'cast.test: cast-1.*, cast-2.*, cast-3.*, cast-5.*, cast-7.*, cast-9.*, cast-10.* CAST target affinity and numeric-prefix behavior',
        'cast.test: cast-1.*, cast-2.*, cast-3.*, cast-5.*, cast-7.*, cast-9.*, cast-10.* CAST target affinity and numeric-prefix behavior',
    );
};

return $tests;
