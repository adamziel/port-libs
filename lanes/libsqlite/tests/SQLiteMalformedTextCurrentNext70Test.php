<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteMalformedTextCurrentNextCursor;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => "plugin_\xc3", 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => "Plugin_\xc3", 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_\xc3 ", 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => "plugin_\xc3  ", 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => "plugin_\xc3A", 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => "plugin_\xc3a", 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => "plugin_\xe2\x82", 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => "plugin_\xe2A", 'autoload' => 'yes'],
    ['option_id' => 10, 'option_name' => 'plugin_é', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'Plugin_É', 'autoload' => 'no'],
    ['option_id' => 12, 'option_name' => 'plugin_zeta', 'autoload' => 'yes'],
];

$entries = array_map(
    static fn (array $row): array => ['key' => $row['option_name'], 'rowid' => $row['option_id'], 'payload' => $row],
    $rows,
);

$cursor = static fn (string $collation = 'BINARY'): SQLiteMalformedTextCurrentNextCursor => new SQLiteMalformedTextCurrentNextCursor($entries, $collation);
$rowids = static fn (array $entries): array => array_column($entries, 'rowid');
$payloadNames = static fn (array $entries): array => array_map(static fn (array $entry): mixed => $entry['payload']['option_name'], $entries);

$binaryOrder = [3, 11, 1, 12, 2, 4, 5, 6, 7, 10, 9, 8];
$nocaseOrder = [1, 12, 2, 3, 4, 5, 6, 7, 11, 10, 9, 8];
$rtrimOrder = [3, 11, 1, 12, 2, 4, 5, 6, 7, 10, 9, 8];

foreach ([
    'binary preserves byte order before valid unicode' => ['BINARY', $binaryOrder],
    'nocase folds ascii around malformed bytes only' => ['NOCASE', $nocaseOrder],
    'rtrim strips trailing spaces after malformed byte for equality' => ['RTRIM', $rtrimOrder],
] as $name => [$collation, $expected]) {
    $tests['malformed text current next70 order ' . $name] = static function (TestRunner $t) use ($cursor, $rowids, $collation, $expected): void {
        $t->same($expected, $rowids($cursor($collation)->remaining()));
    };
}

$planCases = [
    'binary first current is uppercase malformed' => ['BINARY', 0, null, 'currentRowid', 3],
    'binary first next is uppercase valid unicode' => ['BINARY', 0, null, 'nextRowid', 11],
    'binary first current is valid utf8 because malformed byte has ascii prefix' => ['BINARY', 0, null, 'currentMalformedUtf8', true],
    'binary valid unicode next is not malformed' => ['BINARY', 0, null, 'nextMalformedUtf8', false],
    'binary current compares before next' => ['BINARY', 0, null, 'currentToNext', -1],
    'binary lower-case malformed seek lands on rowid two' => ['BINARY', "plugin_\xc3", null, 'currentRowid', 2],
    'binary seek comparison to malformed probe is equal' => ['BINARY', "plugin_\xc3", null, 'comparisonToProbe', 0],
    'binary malformed row next is padded malformed row' => ['BINARY', "plugin_\xc3", null, 'nextRowid', 4],
    'binary valid unicode seek lands after malformed byte rows' => ['BINARY', 'plugin_é', null, 'currentRowid', 10],
    'binary valid unicode seek has no next before ascii invalid lead ordering' => ['BINARY', 'plugin_é', null, 'nextRowid', 9],
    'nocase uppercase malformed folds beside lowercase malformed' => ['NOCASE', "PLUGIN_\xc3", null, 'currentRowid', 2],
    'nocase uppercase malformed probe equals lowercase malformed current' => ['NOCASE', "PLUGIN_\xc3", null, 'comparisonToProbe', 0],
    'nocase next peer is uppercase malformed rowid three' => ['NOCASE', "PLUGIN_\xc3", null, 'nextRowid', 3],
    'nocase peers compare equal before rowid tiebreak' => ['NOCASE', "PLUGIN_\xc3", null, 'currentToNext', 0],
    'nocase valid e acute does not fold to uppercase e acute' => ['NOCASE', 'plugin_é', null, 'nextRowid', 9],
    'nocase valid unicode current and uppercase next are distinct' => ['NOCASE', 'plugin_é', null, 'currentEqualsNext', false],
    'rtrim malformed padded seek lands on unpadded malformed' => ['RTRIM', "plugin_\xc3 ", null, 'currentRowid', 2],
    'rtrim malformed padded probe equals unpadded malformed current' => ['RTRIM', "plugin_\xc3 ", null, 'comparisonToProbe', 0],
    'rtrim next padded peer stays adjacent by rowid' => ['RTRIM', "plugin_\xc3 ", null, 'nextRowid', 4],
    'rtrim unpadded to padded peer compares equal' => ['RTRIM', "plugin_\xc3 ", null, 'currentToNext', 0],
    'rtrim eof after high probe' => ['RTRIM', 'zzzz', null, 'eof', true],
    'rtrim eof current rowid is null' => ['RTRIM', 'zzzz', null, 'currentRowid', null],
];

foreach ($planCases as $name => [$collation, $seek, $advance, $path, $expected]) {
    $tests['malformed text current next70 plan ' . $name] = static function (TestRunner $t) use ($cursor, $collation, $seek, $advance, $path, $expected): void {
        $cursor = $cursor($collation);
        if ($seek !== null) {
            $cursor->seek($seek);
        }
        for ($i = 0; $i < (int) $advance; $i++) {
            $cursor->next();
        }
        $plan = $cursor->currentNextPlan($seek);
        $t->same($expected, $plan[$path]);
    };
}

$rangeCases = [
    'binary malformed c3 range keeps malformed byte and valid e acute cluster' => ['BINARY', "plugin_\xc3", "plugin_\xc4", [], [2, 4, 5, 6, 7, 10]],
    'binary valid unicode range keeps only lower-case e acute' => ['BINARY', 'plugin_é', 'plugin_ê', [], [10]],
    'binary truncated three byte range keeps bad euro tails' => ['BINARY', "plugin_\xe2", "plugin_\xe3", [], [9, 8]],
    'nocase malformed c3 range folds ascii case peer and keeps valid e acute bytes' => ['NOCASE', "plugin_\xc3", "plugin_\xc4", [], [2, 3, 4, 5, 6, 7, 11, 10]],
    'nocase ascii plugin range includes valid and malformed names' => ['NOCASE', 'PLUGIN_', 'PLUGIN`', [], [1, 12, 2, 3, 4, 5, 6, 7, 11, 10, 9, 8]],
    'nocase autoload yes filter keeps only current imported options' => ['NOCASE', 'PLUGIN_', 'PLUGIN`', ['autoload' => 'yes'], [1, 12, 2, 4, 6, 10, 9, 8]],
    'rtrim malformed exact range treats padded malformed peers equal' => ['RTRIM', "plugin_\xc3", "plugin_\xc3A", [], [2, 4, 5]],
    'rtrim autoload yes exact range keeps unpadded and one padded row' => ['RTRIM', "plugin_\xc3", "plugin_\xc3A", ['autoload' => 'yes'], [2, 4]],
    'binary empty high-low range returns no rows' => ['BINARY', "plugin_\xc4", "plugin_\xc3", [], []],
    'binary text range keeps byte-ordered text rows only' => ['BINARY', 'plugin_', 'plugin`', ['autoload' => 'yes'], [1, 12, 2, 4, 6, 10, 9, 8]],
];

foreach ($rangeCases as $name => [$collation, $lower, $upper, $filters, $expected]) {
    $tests['malformed text current next70 range ' . $name] = static function (TestRunner $t) use ($rows, $rowids, $collation, $lower, $upper, $filters, $expected): void {
        $matched = SQLiteMalformedTextCurrentNextCursor::keyValueRowKeyRange($rows, $lower, $upper, $collation, $filters);
        $t->same($expected, $rowids($matched));
    };
}

$mixedEntries = [
    ['key' => null, 'rowid' => 1],
    ['key' => 7, 'rowid' => 2],
    ['key' => '7', 'rowid' => 3],
    ['key' => "7\xc3", 'rowid' => 4],
    ['key' => new SQLiteBlobValue("7\xc3"), 'rowid' => 5],
];

$mixedCases = [
    'mixed storage orders null integer text malformed text blob' => [null, 'remaining', [1, 2, 3, 4, 5]],
    'mixed seek text seven lands on text storage not integer' => ['7', 'currentRowid', 3],
    'mixed malformed text reports malformed utf8' => ["7\xc3", 'currentMalformedUtf8', true],
    'mixed blob seek lands on blob storage after text' => [new SQLiteBlobValue("7\xc3"), 'currentStorage', 'blob'],
    'mixed blob current malformed flag is null' => [new SQLiteBlobValue("7\xc3"), 'currentMalformedUtf8', null],
];

foreach ($mixedCases as $name => [$seek, $path, $expected]) {
    $tests['malformed text current next70 mixed ' . $name] = static function (TestRunner $t) use ($mixedEntries, $rowids, $seek, $path, $expected): void {
        $cursor = new SQLiteMalformedTextCurrentNextCursor($mixedEntries, 'NOCASE');
        if ($seek !== null) {
            $cursor->seek($seek);
        }
        if ($path === 'remaining') {
            $t->same($expected, $rowids($cursor->remaining()));
            return;
        }
        $t->same($expected, $cursor->currentNextPlan($seek)[$path]);
    };
}

$tests['malformed text current next70 rejects unsupported collation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteMalformedTextCurrentNextCursor([], 'WP_LOCALE'));
};

$tests['malformed text current next70 rejects missing rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteMalformedTextCurrentNextCursor([['key' => 'plugin']]));
};

$tests['malformed text current next70 rejects invalid payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteMalformedTextCurrentNextCursor([['key' => 'plugin', 'rowid' => 1, 'payload' => 'bad']]));
};

$tests['malformed text current next70 rejects missing application option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedTextCurrentNextCursor::keyValueRowKeyRange([['option_id' => 1]], 'a', 'z'));
};

$tests['malformed text current next70 exposes payload names for application diagnostics'] = static function (TestRunner $t) use ($rows, $payloadNames): void {
    $matched = SQLiteMalformedTextCurrentNextCursor::keyValueRowKeyRange($rows, "plugin_\xc3", "plugin_\xc4", 'NOCASE', ['autoload' => 'yes']);
    $t->same(["plugin_\xc3", "plugin_\xc3 ", "plugin_\xc3A", 'plugin_é'], $payloadNames($matched));
};

return $tests;
