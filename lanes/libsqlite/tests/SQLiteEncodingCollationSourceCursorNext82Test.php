<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$row = static function (int $id, string $name, int|string $encoding, string $autoload = 'yes'): array {
    return [
        'keyBytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'textEncoding' => is_int($encoding) ? $encoding : match (strtoupper($encoding)) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'rowid' => $id,
        'payload' => ['option_id' => $id, 'option_name' => $name, 'autoload' => $autoload],
    ];
};

$entries = [
    $row(1, 'Plugin_Alpha', 'UTF-8', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-16BE', 'no'),
    $row(4, 'plugin_beta ', 'UTF-8', 'no'),
    $row(5, 'plugin_100%_enabled', 'UTF-16LE'),
    $row(6, 'plugin_100x_enabled', 'UTF-16BE'),
    $row(7, 'plugin_éclair', 'UTF-8'),
    $row(8, 'plugin_Éclair', 'UTF-16LE', 'no'),
    $row(9, 'plugin_Ωmega', 'UTF-16BE'),
    $row(10, 'plugin_😀_cache', 'UTF-16LE'),
    $row(11, 'theme_alpha', 'UTF-8'),
];

$cursor = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitive = false,
): SQLiteEncodingCollationSourceCursor => new SQLiteEncodingCollationSourceCursor(
    $entries,
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitive,
);

$planCases = [
    'default like starts at literal digit prefix under nocase' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentRowid', 5],
    'default like current encoding is utf16le' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'currentEncoding', 'UTF-16LE'],
    'default like next row is utf16be literal x peer' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'nextRowid', 6],
    'default like next encoding is utf16be' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'nextEncoding', 'UTF-16BE'],
    'default like lower bound is folded ascii prefix' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'range.lowerInclusive', 'plugin'],
    'default like upper bound is next ascii prefix' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'range.upperBound', 'plugio'],
    'default like uppercase peer residual matches' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'residualMatch', true],
    'default like uppercase peer stays in range' => ['plugin%', 'LIKE', 'NOCASE', null, false, 0, 'inRange', true],
    'default like escaped literal percent current row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 0, 'currentRowid', 5],
    'default like escaped literal percent skips wildcard x residual' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 1, 'residualMatch', false],
    'case sensitive like binary starts at literal digit prefix' => ['plugin%', 'LIKE', 'BINARY', null, true, 0, 'currentRowid', 5],
    'case sensitive like binary current is above lower bound' => ['plugin%', 'LIKE', 'BINARY', null, true, 0, 'comparisonToLower', 1],
    'case sensitive literal percent keeps utf16le bytes' => ['plugin\_100\%%', 'LIKE', 'BINARY', '\\', true, 0, 'currentBytesHex', '70006c007500670069006e005f0031003000300025005f0065006e00610062006c0065006400'],
    'case sensitive literal percent next x exits residual' => ['plugin\_100\%%', 'LIKE', 'BINARY', '\\', true, 1, 'residualMatch', false],
    'rtrim like is rejected before current source scan' => ['plugin_beta', 'LIKE', 'RTRIM', null, true, 0, 'range', null],
    'rtrim like current is not range usable' => ['plugin_beta', 'LIKE', 'RTRIM', null, true, 0, 'inRange', false],
    'rtrim like current residual may still match outside index range' => ['plugin_beta', 'LIKE', 'RTRIM', null, true, 0, 'residualMatch', false],
    'glob binary starts at literal digit prefix' => ['plugin_*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 5],
    'glob binary current encoding is utf16le' => ['plugin_*', 'GLOB', 'BINARY', null, false, 0, 'currentEncoding', 'UTF-16LE'],
    'glob binary beta next is utf16be' => ['plugin_*', 'GLOB', 'BINARY', null, false, 1, 'currentEncoding', 'UTF-16BE'],
    'glob nocase starts at literal digit prefix' => ['plugin_*', 'GLOB', 'NOCASE', null, false, 0, 'currentRowid', 5],
    'glob nocase digit peer residual is true' => ['plugin_*', 'GLOB', 'NOCASE', null, false, 0, 'residualMatch', true],
    'glob unicode latin range begins literal digit candidate' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 5],
    'glob unicode latin first ascii candidate residual false' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 0, 'residualMatch', false],
    'glob emoji range current is emoji option' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 10],
    'glob emoji current utf16 bytes expose surrogate pair' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 0, 'currentBytesHex', '70006c007500670069006e005f003dd800de5f0063006100630068006500'],
    'leading wildcard like has no range' => ['%plugin', 'LIKE', 'NOCASE', null, false, 0, 'range', null],
    'leading class glob has no range' => ['[Pp]lugin_*', 'GLOB', 'BINARY', null, false, 0, 'range', null],
    'eof keeps dependencies after exhausted scan' => ['plugin_zz%', 'LIKE', 'NOCASE', null, false, 20, 'dependencies.0', 'sqlite-encoding-source-cursor'],
    'eof reports null current after exhausted scan' => ['plugin_zz%', 'LIKE', 'NOCASE', null, false, 20, 'currentRowid', null],
];

foreach ($planCases as $name => [$pattern, $operator, $collation, $escape, $caseSensitive, $advance, $path, $expected]) {
    $tests['encoding collation source next82 plan ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $operator, $collation, $escape, $caseSensitive, $advance, $path, $expected): void {
        $scan = $cursor($pattern, $operator, $collation, $escape, $caseSensitive);
        for ($i = 0; $i < $advance; $i++) {
            $scan->next();
        }
        $value = $scan->currentNextPlan();
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }

        $t->same($expected, $value);
    };
}

$matchCases = [
    'default nocase like matches mixed encodings' => ['plugin%', 'LIKE', 'NOCASE', null, false, [5, 6, 1, 2, 3, 4, 8, 7, 9, 10]],
    'case sensitive binary like skips uppercase' => ['plugin%', 'LIKE', 'BINARY', null, true, [5, 6, 2, 3, 4, 8, 7, 9, 10]],
    'escaped percent like excludes x row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, [5]],
    'unescaped percent like includes x row' => ['plugin\_100%', 'LIKE', 'NOCASE', '\\', false, [5, 6]],
    'rtrim exact beta has no usable case-sensitive like cursor range' => ['plugin_beta', 'LIKE', 'RTRIM', null, true, []],
    'rtrim wildcard beta has no usable case-sensitive like cursor range' => ['plugin_beta%', 'LIKE', 'RTRIM', null, true, []],
    'binary glob skips uppercase peer' => ['plugin_*', 'GLOB', 'BINARY', null, false, [5, 6, 2, 3, 4, 8, 7, 9, 10]],
    'nocase glob range still residual filters uppercase peer' => ['plugin_*', 'GLOB', 'NOCASE', null, false, [5, 6, 2, 3, 4, 8, 7, 9, 10]],
    'glob latin range returns encoded e acute peers' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, [8, 7]],
    'glob greek range returns omega row' => ['plugin_[Α-ω]*', 'GLOB', 'BINARY', null, false, [9]],
    'glob emoji prefix returns utf16le emoji row' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, [10]],
    'leading wildcard like has no source cursor matches' => ['%alpha', 'LIKE', 'NOCASE', null, false, []],
    'leading class glob has no source cursor matches' => ['[Pp]lugin_*', 'GLOB', 'BINARY', null, false, []],
];

foreach ($matchCases as $name => [$pattern, $operator, $collation, $escape, $caseSensitive, $expectedRowids]) {
    $tests['encoding collation source next82 matched rows ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $operator, $collation, $escape, $caseSensitive, $expectedRowids): void {
        $t->same($expectedRowids, array_column($cursor($pattern, $operator, $collation, $escape, $caseSensitive)->matchedRows(), 'rowid'));
    };
}

$tests['encoding collation source next82 matched rows preserve payload and source encoding'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin_😀%', 'LIKE', 'BINARY', null, true)->matchedRows();
    $t->same('plugin_😀_cache', $rows[0]['payload']['option_name']);
    $t->same('UTF-16LE', $rows[0]['textEncoding']);
    $t->same('yes', $rows[0]['payload']['autoload']);
};

$tests['encoding collation source next82 application option scan maps copied columns'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('plugin_100%_enabled', 'UTF-16LE'), 'text_encoding' => 2, 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('Plugin_100%_Enabled', 'UTF-8'), 'text_encoding' => 1, 'autoload' => 'no'],
        ['option_id' => 3, 'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('plugin_100x_enabled', 'UTF-16BE'), 'text_encoding' => 3, 'autoload' => 'yes'],
    ];

    $matched = SQLiteEncodingCollationSourceCursor::optionRowNameScan($rows, 'plugin\_100\%%', 'LIKE', 'NOCASE', '\\');
    $t->same([1, 2], array_column($matched, 'rowid'));
    $t->same(['UTF-16LE', 'UTF-8'], array_column($matched, 'textEncoding'));
};

$tests['encoding collation source next82 encoder rejects malformed utf8'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationSourceCursor::encodeText("plugin_\xc3", 'UTF-16LE'));
};

$tests['encoding collation source next82 rejects malformed utf8 source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => "plugin_\xc3", 'textEncoding' => 1, 'rowid' => 1, 'payload' => []]], 'plugin%'));
};

$tests['encoding collation source next82 rejects odd utf16 source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => "\x70", 'textEncoding' => 2, 'rowid' => 1, 'payload' => []]], 'p%'));
};

$tests['encoding collation source next82 rejects utf16 high surrogate source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => "\x3d\xd8", 'textEncoding' => 2, 'rowid' => 1, 'payload' => []]], 'p%'));
};

$tests['encoding collation source next82 rejects utf16 low surrogate source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => "\xdc\x00", 'textEncoding' => 3, 'rowid' => 1, 'payload' => []]], 'p%'));
};

$tests['encoding collation source next82 rejects unsupported text encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => 'p', 'textEncoding' => 4, 'rowid' => 1, 'payload' => []]], 'p%'));
};

$tests['encoding collation source next82 rejects unsupported operator'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor($entries, 'p%', 'REGEXP'));
};

$tests['encoding collation source next82 rejects unsupported collation'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor($entries, 'p%', 'LIKE', 'WP_LOCALE'));
};

$tests['encoding collation source next82 rejects malformed escape'] = static function (TestRunner $t) use ($entries): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor($entries, 'p%', 'LIKE', 'NOCASE', 'xx'));
};

$tests['encoding collation source next82 rejects missing payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => 'p', 'textEncoding' => 1, 'rowid' => 1]], 'p%'));
};

$tests['encoding collation source next82 rejects non string source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => 10, 'textEncoding' => 1, 'rowid' => 1, 'payload' => []]], 'p%'));
};

$tests['encoding collation source next82 rejects non integer rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => 'p', 'textEncoding' => 1, 'rowid' => '1', 'payload' => []]], 'p%'));
};

$tests['encoding collation source next82 application scan rejects missing encoding column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationSourceCursor::optionRowNameScan([['option_id' => 1, 'option_name_bytes' => 'plugin']], 'p%'));
};

return $tests;
