<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteGlobCursor;
use PortLibs\LibSqlite\SQLiteLikeCurrentNextCursor;

$tests = [];

$rows = [
    ['setting_id' => 1, 'key_name' => 'plugin_alpha', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => "plugin_\xc2", 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => "plugin_\xc2a", 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => "plugin_\xc3", 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => "plugin_\xc3 ", 'load_policy' => 'no'],
    ['setting_id' => 6, 'key_name' => "plugin_\xc3A", 'load_policy' => 'yes'],
    ['setting_id' => 7, 'key_name' => 'plugin_é', 'load_policy' => 'yes'],
    ['setting_id' => 8, 'key_name' => "plugin_\xe2", 'load_policy' => 'yes'],
    ['setting_id' => 9, 'key_name' => "plugin_\xe2\x82", 'load_policy' => 'no'],
    ['setting_id' => 10, 'key_name' => 'plugin_zeta', 'load_policy' => 'yes'],
    ['setting_id' => 11, 'key_name' => 'Plugin_É', 'load_policy' => 'no'],
    ['setting_id' => 12, 'key_name' => 'theme_alpha', 'load_policy' => 'yes'],
];

$entries = array_map(
    static fn (array $row): array => ['key' => $row['key_name'], 'rowid' => $row['setting_id'], 'payload' => $row],
    $rows,
);
$likeCursor = static fn (string $pattern, string $collation = 'BINARY', ?string $escape = null, bool $caseSensitive = true): SQLiteLikeCurrentNextCursor => new SQLiteLikeCurrentNextCursor(
    $entries,
    $pattern,
    $collation,
    $escape,
    $caseSensitive,
);
$globCursor = static fn (string $pattern, string $collation = 'BINARY'): SQLiteGlobCursor => new SQLiteGlobCursor($entries, $pattern, $collation);

$planCases = [
    'like c3 malformed lower bound is raw prefix' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'range.lowerInclusive', "plugin_\xc3"],
    'like c3 malformed upper bound increments raw byte' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'range.upperBound', "plugin_\xc4"],
    'like c3 malformed pattern is reported damaged' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'patternMalformedUtf8', true],
    'like c3 first current is damaged c3 row' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'currentRowid', 4],
    'like c3 first current reports damaged utf8' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'currentMalformedUtf8', true],
    'like c3 next padded row remains in range' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'nextInRange', true],
    'like c3 next padded row residual matches' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 0, 'nextResidualMatch', true],
    'like c3 valid unicode peer is inside byte range' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 3, 'currentRowid', 7],
    'like c3 valid unicode peer is not damaged' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 3, 'currentMalformedUtf8', false],
    'like c3 exits before e2 range' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, 3, 'nextInRange', false],
    'like e2 lower bound is raw lead byte' => ['like', "plugin\_\xe2%", 'BINARY', '\\', true, 0, 'range.lowerInclusive', "plugin_\xe2"],
    'like e2 upper bound increments raw lead byte' => ['like', "plugin\_\xe2%", 'BINARY', '\\', true, 0, 'range.upperBound', "plugin_\xe3"],
    'like e2 first current is one byte malformed tail' => ['like', "plugin\_\xe2%", 'BINARY', '\\', true, 0, 'currentRowid', 8],
    'like e2 next current is two byte malformed tail' => ['like', "plugin\_\xe2%", 'BINARY', '\\', true, 0, 'nextRowid', 9],
    'like e2 next current reports malformed' => ['like', "plugin\_\xe2%", 'BINARY', '\\', true, 0, 'nextMalformedUtf8', true],
    'like escaped malformed percent literal uses c3 percent prefix' => ['like', "plugin\_\xc3\\%%", 'BINARY', '\\', true, 0, 'range.lowerInclusive', "plugin_\xc3%"],
    'like escaped malformed percent literal seeks after c3 percent lower bound' => ['like', "plugin\_\xc3\\%%", 'BINARY', '\\', true, 0, 'currentRowid', 6],
    'like escaped malformed percent literal residual is false without percent row' => ['like', "plugin\_\xc3\\%%", 'BINARY', '\\', true, 0, 'residualMatch', false],
    'default nocase malformed prefix is rejected for safe range' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', false, 0, 'rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'default nocase malformed prefix keeps damaged pattern flag' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', false, 0, 'patternMalformedUtf8', true],
    'default nocase malformed prefix exposes no range' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', false, 0, 'range', null],
    'default nocase malformed prefix current is not range usable' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', false, 0, 'inRange', false],
    'case sensitive nocase collation rejected for malformed prefix' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', true, 0, 'rejectedReason', 'case_sensitive_like_requires_binary_index'],
    'glob c3 matched cursor lower bound remains raw byte prefix' => ['glob', "plugin_\xc3*", 'BINARY', null, true, 0, 'range.lowerInclusive', "plugin_\xc3"],
    'glob c3 matched cursor reports damaged pattern' => ['glob', "plugin_\xc3*", 'BINARY', null, true, 0, 'patternMalformedUtf8', true],
    'glob c3 current is same first row as LIKE' => ['glob', "plugin_\xc3*", 'BINARY', null, true, 0, 'currentRowid', 4],
    'glob e2 current row agrees with LIKE byte prefix' => ['glob', "plugin_\xe2*", 'BINARY', null, true, 0, 'currentRowid', 8],
];

foreach ($planCases as $name => [$type, $pattern, $collation, $escape, $caseSensitive, $advance, $path, $expected]) {
    $tests['like glob malformed text current source next84 plan ' . $name] = static function (TestRunner $t) use ($likeCursor, $globCursor, $type, $pattern, $collation, $escape, $caseSensitive, $advance, $path, $expected): void {
        $cursor = $type === 'like' ? $likeCursor($pattern, $collation, $escape, $caseSensitive) : $globCursor($pattern, $collation);
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
    'like c2 prefix returns malformed c2 rows' => ['like', "plugin\_\xc2%", 'BINARY', '\\', true, [2, 3], [true, true]],
    'like c3 prefix returns damaged c3 byte rows only' => ['like', "plugin\_\xc3%", 'BINARY', '\\', true, [4, 5, 6], [true, true, true]],
    'like c3 underscore after prefix matches one following character' => ['like', "plugin\_\xc3_", 'BINARY', '\\', true, [5, 6], [true, true]],
    'like c3 double underscore reaches valid e acute bytes as one codepoint plus suffix absence' => ['like', "plugin\_\xc3__", 'BINARY', '\\', true, [], []],
    'like e2 prefix returns truncated e2 rows only' => ['like', "plugin\_\xe2%", 'BINARY', '\\', true, [8, 9], [true, true]],
    'like wildcard all plugin rows includes malformed diagnostics' => ['like', 'plugin\_%', 'BINARY', '\\', true, [1, 10, 2, 3, 4, 5, 6, 7, 8, 9], [false, false, true, true, true, true, true, false, true, true]],
    'default nocase malformed prefix returns no indexed rows' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', false, [], []],
    'case sensitive nocase malformed prefix returns no indexed rows' => ['like', "plugin\_\xc3%", 'NOCASE', '\\', true, [], []],
    'glob c3 prefix parity with like byte range' => ['glob', "plugin_\xc3*", 'BINARY', null, true, [4, 5, 6], [true, true, true]],
    'glob e2 prefix parity with like byte range' => ['glob', "plugin_\xe2*", 'BINARY', null, true, [8, 9], [true, true]],
];

foreach ($matchCases as $name => [$type, $pattern, $collation, $escape, $caseSensitive, $expectedRowids, $expectedMalformed]) {
    $tests['like glob malformed text current source next84 matched rows ' . $name] = static function (TestRunner $t) use ($likeCursor, $globCursor, $type, $pattern, $collation, $escape, $caseSensitive, $expectedRowids, $expectedMalformed): void {
        $rows = $type === 'like' ? $likeCursor($pattern, $collation, $escape, $caseSensitive)->matchedRows() : $globCursor($pattern, $collation)->matchedRows();
        $t->same($expectedRowids, array_column($rows, 'rowid'));
        $t->same($expectedMalformed, array_column($rows, 'malformedUtf8'));
    };
}

$databaseCases = [
    'LIKE malformed c3 prefix does not split valid e acute codepoint' => ['plugin_é', "plugin\_\xc3%", '\\', true, false],
    'LIKE malformed c3 underscore matches c3 plus ascii suffix' => ["plugin_\xc3A", "plugin\_\xc3_", '\\', true, true],
    'LIKE malformed c3 underscore matches c3 plus space suffix' => ["plugin_\xc3 ", "plugin\_\xc3_", '\\', true, true],
    'LIKE malformed e2 percent matches one byte truncated e2' => ["plugin_\xe2", "plugin\_\xe2%", '\\', true, true],
    'LIKE malformed e2 percent matches two byte truncated e2' => ["plugin_\xe2\x82", "plugin\_\xe2%", '\\', true, true],
    'LIKE malformed c2 percent keeps c2 byte rows separate' => ["plugin_\xc2a", "plugin\_\xc2%", '\\', true, true],
    'LIKE default nocase folds ascii before malformed tail' => ["PLUGIN_\xc3", "plugin_\xc3", null, false, true],
    'LIKE case sensitive keeps ascii case before malformed tail' => ["PLUGIN_\xc3", "plugin_\xc3", null, true, false],
    'GLOB malformed c3 prefix remains literal byte prefix' => ['plugin_é', "plugin_\xc3*", null, true, false],
    'GLOB malformed c3 question matches c3 plus ascii suffix' => ["plugin_\xc3A", "plugin_\xc3?", null, true, true],
];

foreach ($databaseCases as $name => [$value, $pattern, $escape, $caseSensitive, $expected]) {
    $tests['like glob malformed text current source next84 database ' . $name] = static function (TestRunner $t) use ($name, $value, $pattern, $escape, $caseSensitive, $expected): void {
        if (str_starts_with($name, 'GLOB')) {
            $t->same($expected, SQLiteDatabase::globMatches($value, $pattern));
            return;
        }
        $t->same($expected, SQLiteDatabase::likeMatches($value, $pattern, $escape, $caseSensitive));
    };
}

$tests['like glob malformed text current source next84 matched rows preserve application payload'] = static function (TestRunner $t) use ($likeCursor): void {
    $rows = $likeCursor("plugin\_\xc3%", 'BINARY', '\\', true)->matchedRows();
    $t->same('yes', $rows[0]['payload']['load_policy']);
    $t->same("plugin_\xc3", $rows[0]['payload']['key_name']);
    $t->same(true, $rows[2]['malformedUtf8']);
};

$tests['like glob malformed text current source next84 eof keeps malformed flags nullable'] = static function (TestRunner $t) use ($likeCursor): void {
    $plan = $likeCursor('zzzz%', 'BINARY', null, true)->currentNextPlan();
    $t->true($plan['eof']);
    $t->same(null, $plan['currentMalformedUtf8']);
    $t->same(null, $plan['nextMalformedUtf8']);
};

$tests['like glob malformed text current source next84 rejects malformed escape through like cursor'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor($entries, "plugin\_\xc3%", 'BINARY', 'xx', true));
};

$tests['like glob malformed text current source next84 rejects missing like payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteLikeCurrentNextCursor([['key' => "plugin_\xc3", 'rowid' => 1]], "plugin\_\xc3%", 'BINARY', null, true));
};

return $tests;
