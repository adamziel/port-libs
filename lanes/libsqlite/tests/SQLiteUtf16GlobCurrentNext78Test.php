<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16GlobCurrentNextCursor;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_beta', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_beta ', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_éclair', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_Éclair', 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'plugin_😀_cache', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'plugin_🚀_cache', 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'plugin_Ωmega', 'autoload' => 'yes'],
    ['option_id' => 10, 'option_name' => 'plugin_Журнал', 'autoload' => 'no'],
    ['option_id' => 11, 'option_name' => 'plugin_東京', 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'theme_alpha', 'autoload' => 'yes'],
];

$entries = static function (int|string $encoding) use ($rows): array {
    return array_map(
        static fn (array $row): array => [
            'encodedKey' => SQLiteUtf16GlobCurrentNextCursor::encodeUtf16($row['option_name'], $encoding),
            'rowid' => $row['option_id'],
            'payload' => $row,
        ],
        $rows,
    );
};

$cursor = static fn (string $pattern = 'plugin_*', int|string $encoding = 2, string $collation = 'BINARY'): SQLiteUtf16GlobCurrentNextCursor => new SQLiteUtf16GlobCurrentNextCursor(
    $entries($encoding),
    $pattern,
    $encoding,
    $collation,
);

$planCases = [
    'le reports text encoding' => ['plugin_*', 2, 'BINARY', 0, 'textEncoding', 'UTF-16le'],
    'be reports text encoding' => ['plugin_*', 3, 'BINARY', 0, 'textEncoding', 'UTF-16be'],
    'le lower bound is decoded prefix' => ['plugin_*', 2, 'BINARY', 0, 'range.lowerInclusive', 'plugin_'],
    'be upper bound is decoded binary prefix' => ['plugin_*', 3, 'BINARY', 0, 'range.upperBound', 'plugin`'],
    'le binary scan skips uppercase peer' => ['plugin_*', 2, 'BINARY', 0, 'currentRowid', 2],
    'be binary scan skips uppercase peer' => ['plugin_*', 3, 'BINARY', 0, 'currentRowid', 2],
    'le binary current key is decoded' => ['plugin_*', 2, 'BINARY', 0, 'currentKey', 'plugin_alpha'],
    'be binary current key is decoded' => ['plugin_*', 3, 'BINARY', 0, 'currentKey', 'plugin_alpha'],
    'le exposes little endian encoded current key' => ['plugin_*', 2, 'BINARY', 0, 'currentEncodedKeyHex', '70006c007500670069006e005f0061006c00700068006100'],
    'be exposes big endian encoded current key' => ['plugin_*', 3, 'BINARY', 0, 'currentEncodedKeyHex', '0070006c007500670069006e005f0061006c007000680061'],
    'le next row is beta' => ['plugin_*', 2, 'BINARY', 0, 'nextRowid', 3],
    'be next encoded key is big endian beta' => ['plugin_*', 3, 'BINARY', 0, 'nextEncodedKeyHex', '0070006c007500670069006e005f0062006500740061'],
    'nocase starts at uppercase decoded peer' => ['plugin_*', 2, 'NOCASE', 0, 'currentRowid', 1],
    'nocase uppercase peer stays in decoded range' => ['plugin_*', 2, 'NOCASE', 0, 'inRange', true],
    'nocase uppercase peer fails case-sensitive glob residual' => ['plugin_*', 2, 'NOCASE', 0, 'residualMatch', false],
    'nocase lowercase peer is next decoded row' => ['plugin_*', 2, 'NOCASE', 0, 'nextRowid', 2],
    'rtrim padded exact row remains in exact decoded range' => ['plugin_beta', 2, 'RTRIM', 1, 'currentRowid', 4],
    'rtrim padded exact row fails residual without wildcard' => ['plugin_beta', 2, 'RTRIM', 1, 'residualMatch', false],
    'rtrim wildcard padded row matches residual' => ['plugin_beta*', 3, 'RTRIM', 1, 'residualMatch', true],
    'supplementary emoji row is decoded from surrogate pair' => ['plugin_😀*', 2, 'BINARY', 0, 'currentRowid', 7],
    'supplementary emoji current key is decoded' => ['plugin_😀*', 2, 'BINARY', 0, 'currentKey', 'plugin_😀_cache'],
    'supplementary be encoded key contains high surrogate first' => ['plugin_😀*', 3, 'BINARY', 0, 'currentEncodedKeyHex', '0070006c007500670069006e005fd83dde00005f00630061006300680065'],
    'supplementary next rocket stays outside emoji byte range' => ['plugin_😀*', 2, 'BINARY', 0, 'nextInRange', false],
    'greek range starts at omega row' => ['plugin_[Α-ω]*', 2, 'BINARY', 0, 'currentRowid', 2],
    'greek range ascii first candidate needs residual filter' => ['plugin_[Α-ω]*', 2, 'BINARY', 0, 'residualMatch', false],
    'leading class has no decoded index range' => ['[Pp]lugin_*', 2, 'BINARY', 0, 'range', null],
    'plan keeps decoded entry count' => ['plugin_*', 2, 'BINARY', 0, 'decodedEntryCount', 12],
    'plan records dependencies' => ['plugin_*', 2, 'BINARY', 0, 'dependencies.0', 'sqlite-utf16-glob-current-next-cursor'],
];

foreach ($planCases as $name => [$pattern, $encoding, $collation, $advance, $path, $expected]) {
    $tests['utf16 glob current next78 plan ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $encoding, $collation, $advance, $path, $expected): void {
        $cursor = $cursor($pattern, $encoding, $collation);
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
    'le binary plugin prefix returns decoded lowercase range rows' => ['plugin_*', 2, 'BINARY', [2, 3, 4, 6, 5, 9, 10, 11, 7, 8]],
    'be binary plugin prefix returns decoded lowercase range rows' => ['plugin_*', 3, 'BINARY', [2, 3, 4, 6, 5, 9, 10, 11, 7, 8]],
    'le nocase plugin prefix still residual filters uppercase glob' => ['plugin_*', 2, 'NOCASE', [2, 3, 4, 6, 5, 9, 10, 11, 7, 8]],
    'le rtrim exact prefix excludes padded residual' => ['plugin_beta', 2, 'RTRIM', [3]],
    'be rtrim wildcard includes padded residual' => ['plugin_beta*', 3, 'RTRIM', [3, 4]],
    'le accented latin range returns e acute peers' => ['plugin_[À-ÿ]*', 2, 'BINARY', [6, 5]],
    'be accented latin range returns e acute peers' => ['plugin_[À-ÿ]*', 3, 'BINARY', [6, 5]],
    'le greek range returns omega only' => ['plugin_[Α-ω]*', 2, 'BINARY', [9]],
    'be cyrillic range returns zhe option' => ['plugin_[А-я]*', 3, 'BINARY', [10]],
    'le cjk range returns tokyo option' => ['plugin_[一-龥]*', 2, 'BINARY', [11]],
    'be emoji wildcard returns grinning cache' => ['plugin_😀*', 3, 'BINARY', [7]],
    'le negated latin range returns ascii and non latin decoded rows' => ['plugin_[^À-ÿ]*', 2, 'BINARY', [2, 3, 4, 9, 10, 11, 7, 8]],
    'leading character class remains residual only for indexed cursor' => ['[Pp]lugin_*', 2, 'BINARY', []],
];

foreach ($matchCases as $name => [$pattern, $encoding, $collation, $expectedRowids]) {
    $tests['utf16 glob current next78 matched rows ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $encoding, $collation, $expectedRowids): void {
        $t->same($expectedRowids, array_column($cursor($pattern, $encoding, $collation)->matchedRows(), 'rowid'));
    };
}

$tests['utf16 glob current next78 matched rows preserve application payload and encoding metadata'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin_😀*', 2, 'BINARY')->matchedRows();
    $t->same('plugin_😀_cache', $rows[0]['payload']['option_name']);
    $t->same('yes', $rows[0]['payload']['autoload']);
    $t->same('UTF-16le', $rows[0]['textEncoding']);
    $t->same(30, $rows[0]['byteLength']);
};

$tests['utf16 glob current next78 matched rows expose endian-distinct encoded keys'] = static function (TestRunner $t) use ($cursor): void {
    $le = $cursor('plugin_é*', 2, 'BINARY')->matchedRows();
    $be = $cursor('plugin_é*', 3, 'BINARY')->matchedRows();
    $t->same('70006c007500670069006e005f00e90063006c00610069007200', $le[0]['encodedKeyHex']);
    $t->same('0070006c007500670069006e005f00e90063006c006100690072', $be[0]['encodedKeyHex']);
};

$tests['utf16 glob current next78 encode helper rejects malformed utf8 input'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16GlobCurrentNextCursor::encodeUtf16("plugin_\xc3", 2));
};

$tests['utf16 glob current next78 rejects odd byte key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor([['encodedKey' => "\x70", 'rowid' => 1, 'payload' => []]], 'p*', 2));
};

$tests['utf16 glob current next78 rejects lone high surrogate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor([['encodedKey' => "\x3d\xd8", 'rowid' => 1, 'payload' => []]], '*', 2));
};

$tests['utf16 glob current next78 rejects lone low surrogate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor([['encodedKey' => "\xdc\x00", 'rowid' => 1, 'payload' => []]], '*', 3));
};

$tests['utf16 glob current next78 rejects utf8 encoding mode'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor($entries(2), 'plugin_*', 1));
};

$tests['utf16 glob current next78 rejects missing payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor([['encodedKey' => "\x70\x00", 'rowid' => 1]], 'p*', 2));
};

$tests['utf16 glob current next78 rejects non integer rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor([['encodedKey' => "\x70\x00", 'rowid' => '1', 'payload' => []]], 'p*', 2));
};

$tests['utf16 glob current next78 rejects unsupported collation through delegated cursor'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16GlobCurrentNextCursor($entries(2), 'plugin_*', 2, 'WP_LOCALE'));
};

return $tests;
