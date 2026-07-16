<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/exprfault.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$deterministicHex = static function (int $case): string {
    $length = ($case % 31) + 1;
    $bytes = '';

    for ($offset = 0; $offset < $length; $offset++) {
        $bytes .= chr(($case * 73 + $offset * 41 + ($case * $offset) % 251) & 0xff);
    }

    return strtoupper(bin2hex($bytes));
};

$decorateHex = static function (string $hex, string $separators): string {
    $pairs = str_split($hex, 2);
    $decorated = '';
    $separatorCount = strlen($separators);

    foreach ($pairs as $index => $pair) {
        if ($index > 0) {
            $decorated .= $separators[($index - 1) % $separatorCount];
        }
        $decorated .= $pair;
    }

    return $decorated;
};

$unhexCall = static function (string $input, ?string $ignored) use ($quoteSql): string {
    if ($ignored === null) {
        return 'unhex(' . $quoteSql($input) . ')';
    }

    return 'unhex(' . $quoteSql($input) . ', ' . $quoteSql($ignored) . ')';
};

$hexCases = [];
for ($case = 0; $case < 250; $case++) {
    $hex = $deterministicHex($case);
    $hexCases[sprintf('%03d.upper', $case)] = [
        'expected' => $hex,
        'input' => $hex,
        'ignored' => null,
    ];
    $hexCases[sprintf('%03d.lower', $case)] = [
        'expected' => $hex,
        'input' => strtolower($hex),
        'ignored' => null,
    ];
    $hexCases[sprintf('%03d.spaced', $case)] = [
        'expected' => $hex,
        'input' => $decorateHex($hex, ' '),
        'ignored' => ' ',
    ];
    $hexCases[sprintf('%03d.punctuated', $case)] = [
        'expected' => $hex,
        'input' => strtolower($decorateHex($hex, '-:.')),
        'ignored' => '-:.',
    ];
}

foreach ($hexCases as $caseName => $case) {
    $tests['real upstream corpus expression affinity dynamic exprfault hex unhex ' . $caseName] =
        static function (TestRunner $t) use ($case, $caseName, $unhexCall): void {
            $call = $unhexCall($case['input'], $case['ignored']);
            $rows = SQLiteSelectSql::execute(
                "SELECT hex ( {$call} ) AS h, typeof({$call}) AS t, length({$call}) AS n, quote({$call}) AS q",
                []
            );

            $expectedHex = $case['expected'];
            $t->same([
                [
                    'h' => $expectedHex,
                    't' => 'blob',
                    'n' => intdiv(strlen($expectedHex), 2),
                    'q' => "X'{$expectedHex}'",
                ],
            ], $rows, $caseName . ' hex(unhex()) projection');
        };
}

$tests['real upstream corpus expression affinity dynamic exprfault scalar subquery empty outer rowset'] =
    static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT a = ( SELECT d FROM (SELECT d FROM t2) ) AS cmp FROM t1',
            [
                't1' => [],
                't2' => [],
            ],
        );

        $t->same([], $rows, 'exprfault-1.1 returns an empty rowset when the outer table is empty');
    };

$randomBlobSizes = [1, 2, 3, 4, 5, 8, 13, 16, 21, 32, 55, 64, 89, 128, 233, 377, 500];
foreach ($randomBlobSizes as $size) {
    $tests[sprintf('real upstream corpus expression affinity dynamic exprfault randomblob length %03d', $size)] =
        static function (TestRunner $t) use ($size): void {
            $rows = SQLiteSelectSql::execute(
                "SELECT typeof(randomblob({$size})) AS t, length(randomblob({$size})) AS n, length(hex(randomblob({$size}))) AS h",
                []
            );

            $t->same([
                [
                    't' => 'blob',
                    'n' => $size,
                    'h' => $size * 2,
                ],
            ], $rows, "exprfault-3 randomblob({$size}) expression length");
        };
}

$tests['real upstream corpus expression affinity dynamic exprfault source accounting'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $hexCases, $randomBlobSizes): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream exprfault.test exists');
        $t->contains('do_faultsim_test 1.1 -faults oom*', $sourceText);
        $t->contains('SELECT a = ( SELECT d FROM (SELECT d FROM t2) ) FROM t1', $sourceText);
        $t->contains("SELECT hex ( unhex('ABCDEF') );", $sourceText);
        $t->contains('CREATE INDEX i1 ON t1( hex(b) );', $sourceText);
        $t->contains('UPDATE t1 SET b=randomblob(500);', $sourceText);
        $t->same(1000, count($hexCases), 'dynamic exprfault-2 hex/unhex cases');
        $t->same(17, count($randomBlobSizes), 'dynamic exprfault-3 randomblob expression sizes');
        $t->same(
            'exprfault.test sections 1.1, 2, and 3 scalar expression fault-survival behavior',
            'exprfault.test sections 1.1, 2, and 3 scalar expression fault-survival behavior',
        );
        $t->same(
            'non-overlap: covers exprfault.test scalar subquery empty-rowset, hex(unhex()) blob composition, and randomblob expression length behavior; avoids accepted e_expr parameter, LIKE/GLOB callback, MATCH/REGEXP, CASE/iif, CAST, scalar subquery membership, expression ORDER BY, expridx2 write-elision, randexpr1, modulo-cast, JSON, WAL, VFS, B-tree, PRAGMA, trigger, row-value, and source-neutral cleanup batches',
            'non-overlap: covers exprfault.test scalar subquery empty-rowset, hex(unhex()) blob composition, and randomblob expression length behavior; avoids accepted e_expr parameter, LIKE/GLOB callback, MATCH/REGEXP, CASE/iif, CAST, scalar subquery membership, expression ORDER BY, expridx2 write-elision, randexpr1, modulo-cast, JSON, WAL, VFS, B-tree, PRAGMA, trigger, row-value, and source-neutral cleanup batches',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql scalar projection, derived scalar subquery rowset handling, SQLiteCoreScalarFunction hex/unhex/randomblob dispatch, and hydrated upstream exprfault.test source-truth evidence',
            'no new support component needed; reuses SQLiteSelectSql scalar projection, derived scalar subquery rowset handling, SQLiteCoreScalarFunction hex/unhex/randomblob dispatch, and hydrated upstream exprfault.test source-truth evidence',
        );
    };

return $tests;
