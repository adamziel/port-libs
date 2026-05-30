<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteGlobCursor;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => "plugin_\xc2", 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => "plugin_\xc2a", 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_\xc3", 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => "plugin_\xc3A", 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => "plugin_\xc3 ", 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'plugin_é', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => "plugin_\xe2\x82", 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => "plugin_\xe2A", 'autoload' => 'no'],
    ['option_id' => 10, 'option_name' => 'plugin_zeta', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'Plugin_É', 'autoload' => 'no'],
    ['option_id' => 12, 'option_name' => 'theme_alpha', 'autoload' => 'yes'],
];

$entries = array_map(
    static fn (array $row): array => ['key' => $row['option_name'], 'rowid' => $row['option_id'], 'payload' => $row],
    $rows,
);
$cursor = static fn (string $pattern, string $collation = 'BINARY'): SQLiteGlobCursor => new SQLiteGlobCursor($entries, $pattern, $collation);
$rowids = static fn (array $rows): array => array_column($rows, 'rowid');

$planCases = [
    'malformed c3 prefix lower bound is raw byte prefix' => ["plugin_\xc3*", 'BINARY', 0, 'range.lowerInclusive', "plugin_\xc3"],
    'malformed c3 prefix upper bound increments raw byte' => ["plugin_\xc3*", 'BINARY', 0, 'range.upperBound', "plugin_\xc4"],
    'malformed c3 pattern is reported damaged' => ["plugin_\xc3*", 'BINARY', 0, 'patternMalformedUtf8', true],
    'malformed c3 current row is damaged' => ["plugin_\xc3*", 'BINARY', 0, 'currentMalformedUtf8', true],
    'malformed c3 current row is first c3 byte row' => ["plugin_\xc3*", 'BINARY', 0, 'currentRowid', 4],
    'malformed c3 next row stays in byte range' => ["plugin_\xc3*", 'BINARY', 0, 'nextInRange', true],
    'malformed c3 next residual matches literal byte prefix' => ["plugin_\xc3*", 'BINARY', 0, 'nextResidualMatch', true],
    'malformed c3 valid utf8 peer still inside byte range' => ["plugin_\xc3*", 'BINARY', 3, 'currentRowid', 7],
    'malformed c3 valid utf8 peer is not reported damaged' => ["plugin_\xc3*", 'BINARY', 3, 'currentMalformedUtf8', false],
    'malformed c3 range exits before e2 byte rows' => ["plugin_\xc3*", 'BINARY', 3, 'nextInRange', false],
    'malformed e2 prefix lower bound is raw byte prefix' => ["plugin_\xe2*", 'BINARY', 0, 'range.lowerInclusive', "plugin_\xe2"],
    'malformed e2 prefix upper bound increments raw byte' => ["plugin_\xe2*", 'BINARY', 0, 'range.upperBound', "plugin_\xe3"],
    'malformed e2 current row is bad short three byte prefix' => ["plugin_\xe2*", 'BINARY', 0, 'currentRowid', 9],
    'malformed e2 current row is reported damaged' => ["plugin_\xe2*", 'BINARY', 0, 'currentMalformedUtf8', true],
    'malformed e2 next row remains damaged' => ["plugin_\xe2*", 'BINARY', 0, 'nextMalformedUtf8', true],
    'valid unicode class pattern is not damaged' => ['plugin_[À-ÿ]*', 'BINARY', 4, 'patternMalformedUtf8', false],
    'valid unicode class current malformed c3 row still matches range' => ['plugin_[À-ÿ]*', 'BINARY', 4, 'currentRowid', 4],
    'valid unicode class current malformed byte reports damaged' => ['plugin_[À-ÿ]*', 'BINARY', 4, 'currentMalformedUtf8', true],
    'nocase malformed prefix starts at lowercase damaged row' => ["PLUGIN_\xc3*", 'NOCASE', 0, 'currentRowid', 4],
    'nocase malformed prefix keeps damaged pattern flag' => ["PLUGIN_\xc3*", 'NOCASE', 0, 'patternMalformedUtf8', true],
    'nocase malformed prefix residual remains case sensitive' => ["PLUGIN_\xc3*", 'NOCASE', 0, 'residualMatch', false],
    'leading class damaged pattern has no range' => ["[Pp]lugin_\xc3*", 'BINARY', 0, 'range', null],
    'leading class damaged pattern reports damaged' => ["[Pp]lugin_\xc3*", 'BINARY', 0, 'patternMalformedUtf8', true],
    'leading class damaged pattern cursor still starts before residual scan' => ["[Pp]lugin_\xc3*", 'BINARY', 0, 'eof', false],
];

foreach ($planCases as $name => [$pattern, $collation, $advance, $path, $expected]) {
    $tests['glob malformed utf current next74 plan ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $collation, $advance, $path, $expected): void {
        $cursor = $cursor($pattern, $collation);
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
    'malformed c2 prefix returns c2 byte rows only' => ["plugin_\xc2*", 'BINARY', [2, 3], [true, true]],
    'malformed c3 prefix returns damaged byte-character rows only' => ["plugin_\xc3*", 'BINARY', [4, 6, 5], [true, true, true]],
    'malformed e2 prefix returns truncated e2 rows only' => ["plugin_\xe2*", 'BINARY', [9, 8], [true, true]],
    'valid unicode class includes damaged high-byte and valid unicode rows' => ['plugin_[À-ÿ]*', 'BINARY', [2, 3, 4, 6, 5, 7, 9, 8], [true, true, true, true, true, false, true, true]],
    'negated unicode class excludes damaged high-byte rows' => ['plugin_[^À-ÿ]*', 'BINARY', [1, 10], [false, false]],
    'nocase byte prefix uses index folding but residual remains case sensitive' => ["PLUGIN_\xc3*", 'NOCASE', [], []],
    'wildcard prefix returns all lowercase plugin rows with damage flags' => ['plugin_*', 'BINARY', [1, 10, 2, 3, 4, 6, 5, 7, 9, 8], [false, false, true, true, true, true, true, false, true, true]],
];

foreach ($matchCases as $name => [$pattern, $collation, $expectedRowids, $expectedMalformed]) {
    $tests['glob malformed utf current next74 matched rows ' . $name] = static function (TestRunner $t) use ($cursor, $rowids, $pattern, $collation, $expectedRowids, $expectedMalformed): void {
        $rows = $cursor($pattern, $collation)->matchedRows();
        $t->same($expectedRowids, $rowids($rows));
        $t->same($expectedMalformed, array_column($rows, 'malformedUtf8'));
    };
}

$databaseCases = [
    'literal malformed c3 matches damaged c3 name' => ["plugin_\xc3", "plugin_\xc3*", true],
    'literal malformed c3 star does not split valid e acute codepoint' => ['plugin_é', "plugin_\xc3*", false],
    'literal malformed e2 star matches short euro prefix' => ["plugin_\xe2\x82", "plugin_\xe2*", true],
    'unicode class matches truncated c2 byte by byte codepoint' => ["plugin_\xc2", 'plugin_[À-ÿ]*', true],
    'unicode class matches truncated e2 lead byte by byte codepoint' => ["plugin_\xe2A", 'plugin_[À-ÿ]*', true],
    'negated unicode class keeps ascii zeta' => ['plugin_zeta', 'plugin_[^À-ÿ]*', true],
    'negated unicode class rejects damaged high byte' => ["plugin_\xc3A", 'plugin_[^À-ÿ]*', false],
    'leading class damaged pattern matches without cursor range' => ["Plugin_\xc3", "[Pp]lugin_\xc3*", true],
];

foreach ($databaseCases as $name => [$value, $pattern, $expected]) {
    $tests['glob malformed utf current next74 database match ' . $name] = static function (TestRunner $t) use ($value, $pattern, $expected): void {
        $t->same($expected, SQLiteDatabase::globMatches($value, $pattern));
    };
}

$tests['glob malformed utf current next74 matched rows preserve application payload'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor("plugin_\xc3*", 'BINARY')->matchedRows();
    $t->same('yes', $rows[0]['payload']['autoload']);
    $t->same("plugin_\xc3", $rows[0]['payload']['option_name']);
};

$tests['glob malformed utf current next74 current next reports valid pattern when only row is damaged'] = static function (TestRunner $t) use ($cursor): void {
    $plan = $cursor('plugin_[À-ÿ]*', 'BINARY')->currentNextPlan();
    $t->same(false, $plan['patternMalformedUtf8']);
};

$tests['glob malformed utf current next74 eof keeps malformed flags nullable'] = static function (TestRunner $t) use ($cursor): void {
    $cursor = $cursor('zzzz*', 'BINARY');
    $plan = $cursor->currentNextPlan();
    $t->true($plan['eof']);
    $t->same(null, $plan['currentMalformedUtf8']);
    $t->same(null, $plan['nextMalformedUtf8']);
};

return $tests;
