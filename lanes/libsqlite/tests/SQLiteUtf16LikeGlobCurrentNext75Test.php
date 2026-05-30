<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16LikeGlobCurrentNextCursor;

$tests = [];

$utf16 = static fn (string $text, string $encoding = 'UTF-16LE'): string => SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($text, $encoding);

$names = [
    1 => 'SiteURL',
    2 => 'siteurl',
    3 => 'siteurl ',
    4 => 'site_admin_email',
    5 => 'Site_Admin_Email',
    6 => 'plugin_100%_enabled',
    7 => 'Plugin_100%_Enabled',
    8 => 'plugin_100%_enabled_beta',
    9 => 'plugin_100x_enabled',
    10 => 'plugin_é_enabled',
    11 => 'plugin_É_enabled',
    12 => 'plugin_😀_enabled',
    13 => 'theme_alpha',
];

$entries = static function (string $encoding = 'UTF-16LE') use ($names, $utf16): array {
    $rows = [];
    foreach ($names as $rowid => $name) {
        $rows[] = [
            'keyBytes' => $utf16($name, $encoding),
            'rowid' => $rowid,
            'payload' => ['option_id' => $rowid, 'option_name' => $name, 'autoload' => $rowid % 2 === 0 ? 'yes' : 'no'],
        ];
    }

    return $rows;
};

$makeCursor = static fn (
    string $pattern,
    string $operator = 'LIKE',
    string $encoding = 'UTF-16LE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
): SQLiteUtf16LikeGlobCurrentNextCursor => new SQLiteUtf16LikeGlobCurrentNextCursor(
    $entries($encoding),
    $pattern,
    $operator,
    $encoding,
    $collation,
    $escape,
    $caseSensitiveLike,
);

$planCases = [
    'like nocase site first current is admin lower peer' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 0, 'currentRowid', 4],
    'like nocase site next is admin upper peer' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 0, 'nextRowid', 5],
    'like nocase site lower range is decoded utf8' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 0, 'range.lowerInclusive', 'site'],
    'like nocase site upper range is decoded utf8' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 0, 'range.upperBound', 'sitf'],
    'like nocase current residual matches decoded text' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 0, 'residualMatch', true],
    'like nocase current bytes are utf16le' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 0, 'currentBytesHex', '73006900740065005f00610064006d0069006e005f0065006d00610069006c00'],
    'like nocase folded siteurl keeps rowid tiebreak' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 3, 'currentRowid', 2],
    'like nocase padded siteurl is next after upper peer' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 4, 'currentRowid', 3],
    'like nocase padded siteurl is terminal site range row' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, 5, 'nextInRange', null],
    'like binary default has no usable range' => ['site%', 'LIKE', 'UTF-16LE', 'BINARY', null, false, 0, 'range', null],
    'like binary default first decoded row remains outside range' => ['site%', 'LIKE', 'UTF-16LE', 'BINARY', null, false, 0, 'inRange', false],
    'like binary case-sensitive starts lowercase plugin literal percent' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, 0, 'currentRowid', 6],
    'like binary escaped percent lower range is literal percent' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, 0, 'range.lowerInclusive', 'plugin_100%'],
    'like binary escaped percent current residual matches' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, 0, 'residualMatch', true],
    'like binary escaped percent x row exits literal range' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, 2, 'inRange', false],
    'like nocase escaped percent includes uppercase peer as next' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'NOCASE', '\\', false, 0, 'nextRowid', 7],
    'like nocase escaped emoji prefix rejected for index range' => ['plugin\_😀%', 'LIKE', 'UTF-16LE', 'NOCASE', '\\', false, 0, 'range', null],
    'like binary escaped emoji prefix keeps decoded range' => ['plugin\_😀%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, 0, 'currentRowid', 12],
    'like binary escaped emoji bytes expose surrogate pair' => ['plugin\_😀%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, 0, 'currentBytesHex', '70006c007500670069006e005f003dd800de5f0065006e00610062006c0065006400'],
    'glob binary plugin first decoded row is lowercase plugin literal' => ['plugin_*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'currentRowid', 6],
    'glob binary plugin lower range is decoded text' => ['plugin_*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'range.lowerInclusive', 'plugin_'],
    'glob binary plugin residual is case sensitive' => ['plugin_*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'residualMatch', true],
    'glob nocase plugin range reaches uppercase peer after lowercase' => ['plugin_*', 'GLOB', 'UTF-16LE', 'NOCASE', null, false, 1, 'currentRowid', 7],
    'glob nocase uppercase plugin residual remains false' => ['plugin_*', 'GLOB', 'UTF-16LE', 'NOCASE', null, false, 1, 'residualMatch', false],
    'glob unicode class range starts at fixed plugin prefix' => ['plugin_[À-ÿ]*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'currentRowid', 6],
    'glob unicode class first fixed-prefix row needs residual filter' => ['plugin_[À-ÿ]*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'residualMatch', false],
    'glob unicode class reaches uppercase e acute before lowercase under binary order' => ['plugin_[À-ÿ]*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 3, 'currentRowid', 11],
    'glob emoji class uses fixed prefix before supplementary residual' => ['plugin_[😀-😀]*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'currentRowid', 6],
    'glob leading class has no current range' => ['[Pp]lugin_*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, 0, 'range', null],
    'utf16be like current bytes are big endian' => ['site%', 'LIKE', 'UTF-16BE', 'NOCASE', null, false, 0, 'currentBytesHex', '0073006900740065005f00610064006d0069006e005f0065006d00610069006c'],
    'utf16be like plan reports encoding' => ['site%', 'LIKE', 'UTF-16BE', 'NOCASE', null, false, 0, 'encoding', 'UTF-16BE'],
];

foreach ($planCases as $name => [$pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike, $advance, $path, $expected]) {
    $tests['utf16 like glob current next75 plan ' . $name] = static function (TestRunner $t) use ($makeCursor, $pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike, $advance, $path, $expected): void {
        $cursor = $makeCursor($pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike);
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
    'like nocase site prefix returns decoded folded rows' => ['site%', 'LIKE', 'UTF-16LE', 'NOCASE', null, false, [4, 5, 1, 2, 3]],
    'like binary default rejected range returns no rows' => ['site%', 'LIKE', 'UTF-16LE', 'BINARY', null, false, []],
    'like binary case-sensitive site returns lowercase rows only' => ['site%', 'LIKE', 'UTF-16LE', 'BINARY', null, true, [4, 2, 3]],
    'like nocase escaped literal percent returns case folded plugin rows' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'NOCASE', '\\', false, [6, 7, 8]],
    'like binary escaped literal percent keeps uppercase peer out' => ['plugin\_100\%%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, [6, 8]],
    'like binary escaped emoji prefix returns supplementary row' => ['plugin\_😀%', 'LIKE', 'UTF-16LE', 'BINARY', '\\', true, [12]],
    'glob binary plugin prefix returns lowercase decoded rows' => ['plugin_*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, [6, 8, 9, 11, 10, 12]],
    'glob nocase range still filters uppercase residual' => ['plugin_*', 'GLOB', 'UTF-16LE', 'NOCASE', null, false, [6, 8, 9, 11, 10, 12]],
    'glob unicode class includes e acute rows but not emoji' => ['plugin_[À-ÿ]*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, [11, 10]],
    'glob emoji class includes only surrogate pair row' => ['plugin_[😀-😀]*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, [12]],
    'glob leading class has no indexed rows despite residual possibility' => ['[Pp]lugin_*', 'GLOB', 'UTF-16LE', 'BINARY', null, false, []],
    'utf16be like nocase site prefix returns same decoded rowids' => ['site%', 'LIKE', 'UTF-16BE', 'NOCASE', null, false, [4, 5, 1, 2, 3]],
];

foreach ($matchCases as $name => [$pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike, $expectedRowids]) {
    $tests['utf16 like glob current next75 matched rows ' . $name] = static function (TestRunner $t) use ($makeCursor, $pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike, $expectedRowids): void {
        $cursor = $makeCursor($pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike);
        $t->same($expectedRowids, array_column($cursor->matchedRows(), 'rowid'));
    };
}

$applicationRows = array_map(static fn (array $entry): array => [
    'option_id' => $entry['rowid'],
    'option_name_utf16' => $entry['keyBytes'],
    'autoload' => $entry['payload']['autoload'],
], $entries('UTF-16LE'));

$tests['utf16 like glob current next75 application helper scans option_name_utf16'] = static function (TestRunner $t) use ($applicationRows): void {
    $rows = SQLiteUtf16LikeGlobCurrentNextCursor::keyValueRowKeyScan($applicationRows, 'plugin\_100\%%', 'LIKE', 'UTF-16LE', 'NOCASE', '\\');
    $t->same([6, 7, 8], array_column($rows, 'rowid'));
};

$tests['utf16 like glob current next75 matched rows preserve decoded text and payload'] = static function (TestRunner $t) use ($makeCursor): void {
    $rows = $makeCursor('plugin_[😀-😀]*', 'GLOB', 'UTF-16LE', 'BINARY')->matchedRows();
    $t->same('plugin_😀_enabled', $rows[0]['keyText']);
    $t->same('yes', $rows[0]['payload']['autoload']);
};

$tests['utf16 like glob current next75 rejects odd byte payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor([['keyBytes' => "a\0b", 'rowid' => 1, 'payload' => []]], 'a%'));
};

$tests['utf16 like glob current next75 rejects unpaired high surrogate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor([['keyBytes' => "\x3d\xd8A\0", 'rowid' => 1, 'payload' => []]], 'a%'));
};

$tests['utf16 like glob current next75 rejects unpaired low surrogate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor([['keyBytes' => "\0\xde", 'rowid' => 1, 'payload' => []]], 'a%'));
};

$tests['utf16 like glob current next75 rejects unsupported encoding'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor($entries(), 'a%', 'LIKE', 'UTF-32LE'));
};

$tests['utf16 like glob current next75 rejects unsupported operator'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor($entries(), 'a%', 'REGEXP'));
};

$tests['utf16 like glob current next75 rejects missing application utf16 column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobCurrentNextCursor::keyValueRowKeyScan([['option_id' => 1]], 'a%'));
};

return $tests;
