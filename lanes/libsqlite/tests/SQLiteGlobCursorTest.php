<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteGlobCursor;

$tests = [];

$entries = [
    ['key' => 'Plugin_Alpha', 'rowid' => 1, 'payload' => ['key_name' => 'Plugin_Alpha', 'load_policy' => 'yes']],
    ['key' => 'plugin_alpha', 'rowid' => 2, 'payload' => ['key_name' => 'plugin_alpha', 'load_policy' => 'yes']],
    ['key' => 'plugin_beta', 'rowid' => 3, 'payload' => ['key_name' => 'plugin_beta', 'load_policy' => 'no']],
    ['key' => 'plugin_beta ', 'rowid' => 4, 'payload' => ['key_name' => 'plugin_beta ', 'load_policy' => 'no']],
    ['key' => "plugin_\xc3_enabled", 'rowid' => 5, 'payload' => ['key_name' => "plugin_\xc3_enabled", 'load_policy' => 'yes']],
    ['key' => 'plugin_é_enabled', 'rowid' => 6, 'payload' => ['key_name' => 'plugin_é_enabled', 'load_policy' => 'yes']],
    ['key' => 'plugin_É_enabled', 'rowid' => 7, 'payload' => ['key_name' => 'plugin_É_enabled', 'load_policy' => 'no']],
    ['key' => 'plugin_zeta', 'rowid' => 8, 'payload' => ['key_name' => 'plugin_zeta', 'load_policy' => 'no']],
    ['key' => 'theme_alpha', 'rowid' => 9, 'payload' => ['key_name' => 'theme_alpha', 'load_policy' => 'yes']],
];

$makeCursor = static fn (string $pattern = 'plugin_*', string $collation = 'BINARY'): SQLiteGlobCursor => new SQLiteGlobCursor(
    $entries,
    $pattern,
    $collation,
);

$planCases = [
    'binary lower bound skips uppercase plugin peer' => ['plugin_*', 'BINARY', 0, 'currentRowid', 2],
    'binary next row after alpha is beta' => ['plugin_*', 'BINARY', 0, 'nextRowid', 3],
    'binary lower bound is plugin underscore' => ['plugin_*', 'BINARY', 0, 'range.lowerInclusive', 'plugin_'],
    'binary upper bound is plugin backtick' => ['plugin_*', 'BINARY', 0, 'range.upperBound', 'plugin`'],
    'binary first row is inside range' => ['plugin_*', 'BINARY', 0, 'inRange', true],
    'binary first row residual matches' => ['plugin_*', 'BINARY', 0, 'residualMatch', true],
    'binary uppercase peer compares below lower' => ['plugin_*', 'BINARY', 0, 'comparisonToLower', 1],
    'binary unicode row remains below upper byte bound' => ['plugin_*', 'BINARY', 5, 'comparisonToUpper', -1],
    'binary row after plugin prefix exits range' => ['plugin_*', 'BINARY', 6, 'nextInRange', false],
    'binary row after plugin prefix has no residual match' => ['plugin_*', 'BINARY', 6, 'nextResidualMatch', false],
    'nocase folded uppercase plugin peer is first' => ['plugin_*', 'NOCASE', 0, 'currentRowid', 1],
    'nocase folded lowercase plugin peer follows by text then rowid' => ['plugin_*', 'NOCASE', 0, 'nextRowid', 2],
    'nocase range keeps binary prefix metadata' => ['plugin_*', 'NOCASE', 0, 'range.lowerInclusive', 'plugin_'],
    'nocase uppercase peer is in folded range' => ['plugin_*', 'NOCASE', 0, 'inRange', true],
    'nocase uppercase peer fails case-sensitive glob residual' => ['plugin_*', 'NOCASE', 0, 'residualMatch', false],
    'nocase lowercase peer residual matches' => ['plugin_*', 'NOCASE', 1, 'residualMatch', true],
    'rtrim exact padded row is sorted with unpadded key' => ['plugin_beta', 'RTRIM', 1, 'currentRowid', 4],
    'rtrim padded row is inside exact prefix range' => ['plugin_beta', 'RTRIM', 1, 'inRange', true],
    'rtrim padded row fails glob residual without wildcard' => ['plugin_beta', 'RTRIM', 1, 'residualMatch', false],
    'unicode class range first candidate is ascii plugin row' => ['plugin_[À-ÿ]*', 'BINARY', 0, 'currentRowid', 2],
    'unicode class ascii candidate needs residual filter' => ['plugin_[À-ÿ]*', 'BINARY', 0, 'residualMatch', false],
    'unicode class malformed byte candidate residual matches byte codepoint range' => ['plugin_[À-ÿ]*', 'BINARY', 4, 'residualMatch', true],
    'malformed byte pattern current reaches malformed row' => ["plugin_\xc3*", 'BINARY', 0, 'currentRowid', 5],
    'malformed byte pattern residual matches malformed row' => ["plugin_\xc3*", 'BINARY', 0, 'residualMatch', true],
    'malformed byte pattern next unicode row stays in byte range' => ["plugin_\xc3*", 'BINARY', 0, 'nextInRange', true],
    'class leading pattern has no current range' => ['[Pp]lugin_*', 'BINARY', 0, 'range', null],
    'class leading pattern is eof after range rejection' => ['[Pp]lugin_*', 'BINARY', 20, 'eof', true],
];

foreach ($planCases as $name => [$pattern, $collation, $advance, $path, $expected]) {
    $tests['glob current next72 plan ' . $name] = static function (TestRunner $t) use ($makeCursor, $pattern, $collation, $advance, $path, $expected): void {
        $cursor = $makeCursor($pattern, $collation);
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }
        $value = $cursor->currentNextPlan();
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }

        $t->same($expected, $value);
    };
}

$matchCases = [
    'binary plugin prefix returns lowercase binary range rows' => ['plugin_*', 'BINARY', [2, 3, 4, 8, 5, 7, 6]],
    'nocase plugin prefix still residual filters uppercase GLOB' => ['plugin_*', 'NOCASE', [2, 3, 4, 8, 5, 7, 6]],
    'rtrim exact prefix excludes padded residual from exact glob' => ['plugin_beta', 'RTRIM', [3]],
    'rtrim wildcard prefix includes padded row' => ['plugin_beta*', 'RTRIM', [3, 4]],
    'unicode class range returns malformed-byte and accented plugin rows' => ['plugin_[À-ÿ]*', 'BINARY', [5, 7, 6]],
    'negated unicode class returns ascii plugin rows only' => ['plugin_[^À-ÿ]*', 'BINARY', [2, 3, 4, 8]],
    'malformed byte pattern keeps byte-wise split distinct from valid unicode' => ["plugin_\xc3*", 'BINARY', [5]],
    'leading character class has no indexed rows despite residual matches' => ['[Pp]lugin_*', 'BINARY', []],
];

foreach ($matchCases as $name => [$pattern, $collation, $expectedRowids]) {
    $tests['glob current next72 matched rows ' . $name] = static function (TestRunner $t) use ($makeCursor, $pattern, $collation, $expectedRowids): void {
        $cursor = $makeCursor($pattern, $collation);
        $t->same($expectedRowids, array_column($cursor->matchedRows(), 'rowid'));
    };
}

$tests['glob current next72 matched rows preserve payload columns'] = static function (TestRunner $t) use ($makeCursor): void {
    $rows = $makeCursor('plugin_[À-ÿ]*', 'BINARY')->matchedRows();
    $t->same("plugin_\xc3_enabled", $rows[0]['payload']['key_name']);
    $t->same('yes', $rows[0]['payload']['load_policy']);
};

$tests['glob current next72 matched rows expose source positions after collation sort'] = static function (TestRunner $t) use ($makeCursor): void {
    $rows = $makeCursor('plugin_*', 'NOCASE')->matchedRows();
    $t->same([1, 2, 3, 4, 5, 6, 7], array_column($rows, 'position'));
};

$tests['glob current next72 database residual proves leading class would match without range'] = static function (TestRunner $t): void {
    $t->true(SQLiteDatabase::globMatches('Plugin_Alpha', '[Pp]lugin_*'));
    $t->true(SQLiteDatabase::globMatches('plugin_alpha', '[Pp]lugin_*'));
};

$tests['glob current next72 rejects malformed entry payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteGlobCursor([['key' => 'plugin_alpha', 'rowid' => 1]], 'plugin_*'));
};

$tests['glob current next72 rejects non text key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteGlobCursor([['key' => 10, 'rowid' => 1, 'payload' => []]], 'plugin_*'));
};

$tests['glob current next72 rejects non integer rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteGlobCursor([['key' => 'plugin_alpha', 'rowid' => '1', 'payload' => []]], 'plugin_*'));
};

$tests['glob current next72 rejects unsupported collation'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteGlobCursor($entries, 'plugin_*', 'WP_LOCALE'));
};

return $tests;
