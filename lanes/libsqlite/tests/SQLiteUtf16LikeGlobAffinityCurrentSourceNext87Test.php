<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceCursor;

$tests = [];

$entries = [
    ['key' => null, 'textEncoding' => 'UTF-16LE', 'rowid' => 1, 'payload' => ['option_name' => 'nullish']],
    ['key' => 1, 'textEncoding' => 'UTF-16LE', 'rowid' => 2, 'payload' => ['option_name' => 'int_one']],
    ['key' => true, 'textEncoding' => 'UTF-16BE', 'rowid' => 3, 'payload' => ['option_name' => 'bool_one']],
    ['key' => '10', 'textEncoding' => 'UTF-16LE', 'rowid' => 4, 'payload' => ['option_name' => 'text_ten']],
    ['key' => 10, 'textEncoding' => 'UTF-16BE', 'rowid' => 5, 'payload' => ['option_name' => 'int_ten']],
    ['key' => 10.5, 'textEncoding' => 'UTF-16LE', 'rowid' => 6, 'payload' => ['option_name' => 'real_ten_half']],
    ['key' => 'Plugin-Alpha', 'textEncoding' => 'UTF-16LE', 'rowid' => 7, 'payload' => ['option_name' => 'Plugin-Alpha']],
    ['key' => 'plugin-alpha', 'textEncoding' => 'UTF-16BE', 'rowid' => 8, 'payload' => ['option_name' => 'plugin-alpha']],
    ['key' => 'plugin-alpha ', 'textEncoding' => 'UTF-16LE', 'rowid' => 9, 'payload' => ['option_name' => 'plugin-alpha ']],
    ['key' => 'plugin-beta', 'textEncoding' => 'UTF-16BE', 'rowid' => 10, 'payload' => ['option_name' => 'plugin-beta']],
    ['key' => 'plugin_100%_enabled', 'textEncoding' => 'UTF-16LE', 'rowid' => 11, 'payload' => ['option_name' => 'plugin_100%_enabled']],
    ['key' => 'plugin_100x_enabled', 'textEncoding' => 'UTF-16BE', 'rowid' => 12, 'payload' => ['option_name' => 'plugin_100x_enabled']],
    ['key' => 'plugin_Éclair', 'textEncoding' => 'UTF-16BE', 'rowid' => 13, 'payload' => ['option_name' => 'plugin_Éclair']],
    ['key' => 'plugin_éclair', 'textEncoding' => 'UTF-16LE', 'rowid' => 14, 'payload' => ['option_name' => 'plugin_éclair']],
    ['key' => 'plugin_Ωmega', 'textEncoding' => 'UTF-16BE', 'rowid' => 15, 'payload' => ['option_name' => 'plugin_Ωmega']],
    ['key' => 'plugin_😀_cache', 'textEncoding' => 'UTF-16LE', 'rowid' => 16, 'payload' => ['option_name' => 'plugin_😀_cache']],
    ['key' => new SQLiteBlobValue('plugin_blob'), 'textEncoding' => 'UTF-16LE', 'rowid' => 17, 'payload' => ['option_name' => 'blob']],
    ['key' => 'theme-alpha', 'textEncoding' => 'UTF-16LE', 'rowid' => 18, 'payload' => ['option_name' => 'theme-alpha']],
];

$cursor = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitive = false,
): SQLiteUtf16LikeGlobAffinityCurrentSourceCursor => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor(
    $entries,
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitive,
);

$valueAt = static function (array $plan, string $path): mixed {
    $value = $plan;
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$planCases = [
    'nocase LIKE starts at plugin percent numeric text is below range skipped' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentRowid', 7],
    'nocase LIKE folds uppercase plugin current into lower range' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentInRange', true],
    'nocase LIKE keeps residual match for uppercase ASCII prefix' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentResidualMatch', true],
    'nocase LIKE current original storage is text' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentOriginalStorage', 'text'],
    'nocase LIKE current encoding is utf16le' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentEncoding', 'UTF-16LE'],
    'nocase LIKE next row is lowercase peer' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'nextRowid', 8],
    'nocase LIKE next encoding is utf16be' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'nextEncoding', 'UTF-16BE'],
    'nocase LIKE lower range is folded plugin prefix' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'range.lowerInclusive', 'plugin'],
    'nocase LIKE upper range is plugio' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'range.upperBound', 'plugio'],
    'nocase LIKE escaped percent lands on literal percent row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 0, 'currentRowid', 11],
    'nocase LIKE escaped percent current residual true' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 0, 'currentResidualMatch', true],
    'nocase LIKE escaped percent next x residual false' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 1, 'currentResidualMatch', false],
    'binary LIKE case-sensitive starts at lowercase plugin' => ['plugin%', 'LIKE', 'BINARY', null, true, 0, 'currentRowid', 8],
    'binary LIKE case-sensitive excludes uppercase residual from range start' => ['plugin%', 'LIKE', 'BINARY', null, true, 0, 'currentText', 'plugin-alpha'],
    'binary LIKE emoji range starts at first binary plugin candidate' => ['plugin_😀%', 'LIKE', 'BINARY', null, true, 0, 'currentRowid', 8],
    'glob prefix starts at lowercase plugin for binary scan' => ['plugin_*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 11],
    'glob prefix current residual true' => ['plugin_*', 'GLOB', 'BINARY', null, false, 0, 'currentResidualMatch', true],
    'glob prefix uppercase hyphen source remains below binary lower' => ['plugin-*', 'GLOB', 'NOCASE', null, false, 0, 'currentRowid', 7],
    'glob prefix uppercase residual false because glob is case sensitive' => ['plugin-*', 'GLOB', 'NOCASE', null, false, 0, 'currentResidualMatch', false],
    'glob latin class first ascii candidate needs residual filter' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 0, 'currentResidualMatch', false],
    'glob latin class reaches capital e acute row' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 2, 'currentRowid', 13],
    'glob greek class starts at first underscore candidate before residual' => ['plugin_[Α-ω]*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 11],
    'glob emoji current is emoji row' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 16],
    'leading wildcard like has no range and eof' => ['%plugin', 'LIKE', 'NOCASE', null, false, 0, 'eof', true],
    'leading class glob has no range and eof' => ['[Pp]lugin*', 'GLOB', 'BINARY', null, false, 0, 'eof', true],
    'numeric LIKE starts at text-affinity one value' => ['1%', 'LIKE', 'BINARY', null, true, 0, 'currentRowid', 2],
    'numeric LIKE integer one original storage retained' => ['1%', 'LIKE', 'BINARY', null, true, 0, 'currentOriginalStorage', 'integer'],
    'numeric LIKE boolean coerces to one text' => ['1%', 'LIKE', 'BINARY', null, true, 1, 'currentOriginalStorage', 'integer'],
    'numeric LIKE real value string is in range' => ['10.%', 'LIKE', 'BINARY', null, true, 0, 'currentText', '10.5'],
    'numeric LIKE real bytes encoded as utf16le' => ['10.%', 'LIKE', 'BINARY', null, true, 0, 'currentBytesHex', '310030002e003500'],
    'blob operand range still starts at earlier residual candidate' => ['plugin_blob%', 'LIKE', 'BINARY', null, true, 0, 'currentRowid', 8],
    'plan dependencies name text affinity' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'dependencies.0', 'sqlite-text-affinity'],
    'plan dependencies name utf16 encoding' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'dependencies.1', 'sqlite-utf16-encoding'],
];

foreach ($planCases as $name => [$pattern, $operator, $collation, $escape, $caseSensitive, $advance, $path, $expected]) {
    $tests['utf16 like glob affinity current source nextEightSeven plan ' . $name] = static function (TestRunner $t) use ($cursor, $valueAt, $pattern, $operator, $collation, $escape, $caseSensitive, $advance, $path, $expected): void {
        $scan = $cursor($pattern, $operator, $collation, $escape, $caseSensitive);
        for ($i = 0; $i < $advance; $i++) {
            $scan->next();
        }
        $t->same($expected, $valueAt($scan->currentNextPlan(), $path));
    };
}

$matchCases = [
    'nocase LIKE plugin percent includes mixed case text values' => ['plugin%', 'LIKE', 'NOCASE', null, false, [7, 8, 9, 10, 11, 12, 13, 14, 15, 16]],
    'binary case-sensitive LIKE skips uppercase plugin alpha' => ['plugin%', 'LIKE', 'BINARY', null, true, [8, 9, 10, 11, 12, 13, 14, 15, 16]],
    'escaped percent LIKE includes literal percent only' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, [11]],
    'escaped underscore LIKE with trailing wildcard includes percent and x rows' => ['plugin\_100%', 'LIKE', 'NOCASE', '\\', false, [11, 12]],
    'binary GLOB underscore prefix returns underscore rows only' => ['plugin_*', 'GLOB', 'BINARY', null, false, [11, 12, 13, 14, 15, 16]],
    'nocase GLOB hyphen prefix filters uppercase residual' => ['plugin-*', 'GLOB', 'NOCASE', null, false, [8, 9, 10]],
    'RTRIM GLOB exact padded key residual excludes padded row' => ['plugin-alpha', 'GLOB', 'RTRIM', null, false, [8]],
    'RTRIM GLOB wildcard includes padded key' => ['plugin-alpha*', 'GLOB', 'RTRIM', null, false, [8, 9]],
    'GLOB latin class returns e acute values' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, [13, 14]],
    'GLOB greek class returns omega value' => ['plugin_[Α-ω]*', 'GLOB', 'BINARY', null, false, [15]],
    'GLOB emoji prefix returns emoji value' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, [16]],
    'LIKE numeric one prefix returns integer boolean and ten values' => ['1%', 'LIKE', 'BINARY', null, true, [2, 3, 4, 5, 6]],
    'LIKE numeric ten prefix returns text integer and real ten values' => ['10%', 'LIKE', 'BINARY', null, true, [4, 5, 6]],
    'LIKE real decimal prefix returns real value' => ['10.%', 'LIKE', 'BINARY', null, true, [6]],
    'leading wildcard LIKE returns no cursor rows' => ['%alpha', 'LIKE', 'NOCASE', null, false, []],
    'leading class GLOB returns no cursor rows' => ['[Pp]lugin*', 'GLOB', 'BINARY', null, false, []],
    'blob-like pattern skips blob operand under text affinity scan' => ['plugin_blob%', 'LIKE', 'BINARY', null, true, []],
];

foreach ($matchCases as $name => [$pattern, $operator, $collation, $escape, $caseSensitive, $expectedRowids]) {
    $tests['utf16 like glob affinity current source nextEightSeven matched rows ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $operator, $collation, $escape, $caseSensitive, $expectedRowids): void {
        $t->same($expectedRowids, array_column($cursor($pattern, $operator, $collation, $escape, $caseSensitive)->matchedRows(), 'rowid'));
    };
}

$tests['utf16 like glob affinity current source nextEightSeven matched rows preserve original storage classes'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('10%', 'LIKE', 'BINARY', null, true)->matchedRows();
    $t->same(['text', 'integer', 'real'], array_column($rows, 'originalStorage'));
};

$tests['utf16 like glob affinity current source nextEightSeven matched rows preserve utf16 encodings'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin_1%', 'LIKE', 'BINARY', null, true)->matchedRows();
    $t->same(['UTF-16LE', 'UTF-16BE'], array_column($rows, 'textEncoding'));
};

$tests['utf16 like glob affinity current source nextEightSeven matched rows preserve payload'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin_😀%', 'LIKE', 'BINARY', null, true)->matchedRows();
    $t->same('plugin_😀_cache', $rows[0]['payload']['option_name']);
};

$tests['utf16 like glob affinity current source nextEightSeven matched rows expose emoji utf16 surrogate bytes'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin_😀%', 'LIKE', 'BINARY', null, true)->matchedRows();
    $t->same('70006c007500670069006e005f003dd800de5f0063006100630068006500', $rows[0]['bytesHex']);
};

$tests['utf16 like glob affinity current source nextEightSeven application value scan matches numeric-looking autoload values'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 101, 'option_name' => 'blog_public', 'option_value' => 1, 'autoload' => 'yes'],
        ['option_id' => 102, 'option_name' => 'rewrite_rules', 'option_value' => '10-rules', 'autoload' => 'yes'],
        ['option_id' => 103, 'option_name' => 'theme_mods', 'option_value' => 'plugin_éclair', 'autoload' => 'yes'],
        ['option_id' => 104, 'option_name' => 'binary_payload', 'option_value' => new SQLiteBlobValue('plugin_blob'), 'autoload' => 'no'],
    ];
    $matched = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::keyValueRowValueScan($rows, 'option_value', '1%', 'LIKE', 'BINARY', null, true, 'UTF-16LE');
    $t->same([101, 102], array_column($matched, 'rowid'));
};

$tests['utf16 like glob affinity current source nextEightSeven application value scan matches unicode glob values'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 201, 'option_name' => 'theme_mods', 'option_value' => 'plugin_éclair'],
        ['option_id' => 202, 'option_name' => 'theme_mods_old', 'option_value' => 'plugin_alpha'],
        ['option_id' => 203, 'option_name' => 'theme_mods_emoji', 'option_value' => 'plugin_😀_cache'],
    ];
    $matched = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::keyValueRowValueScan($rows, 'option_value', 'plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'UTF-16BE');
    $t->same([201], array_column($matched, 'rowid'));
    $t->same('UTF-16BE', $matched[0]['textEncoding']);
};

$tests['utf16 like glob affinity current source nextEightSeven rejects malformed utf8 string before utf16 encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor([['key' => "plugin_\xc3", 'rowid' => 1]], 'plugin%'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects unsupported operator'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($entries, 'plugin%', 'REGEXP'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects unsupported collation'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($entries, 'plugin%', 'LIKE', 'UNICODE'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects malformed escape'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor($entries, 'plugin%', 'LIKE', 'NOCASE', 'xx'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects unsupported text encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor([['key' => 'plugin', 'textEncoding' => 4, 'rowid' => 1]], 'plugin%'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects array key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor([['key' => [], 'rowid' => 1]], 'plugin%'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects non integer rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor([['key' => 'plugin', 'rowid' => '1']], 'plugin%'));
};

$tests['utf16 like glob affinity current source nextEightSeven rejects non array payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobAffinityCurrentSourceCursor([['key' => 'plugin', 'rowid' => 1, 'payload' => 'bad']], 'plugin%'));
};

$tests['utf16 like glob affinity current source nextEightSeven application scan rejects missing column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::keyValueRowValueScan([['option_id' => 1, 'option_name' => 'siteurl']], 'option_value', 's%'));
};

return $tests;
