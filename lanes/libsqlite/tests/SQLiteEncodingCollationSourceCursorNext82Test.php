<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$row = static function (int $id, string $name, int|string $encoding, string $loadPolicy = 'eager'): array {
    return [
        'keyBytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'textEncoding' => is_int($encoding) ? $encoding : match (strtoupper($encoding)) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'rowid' => $id,
        'payload' => ['setting_id' => $id, 'key_name' => $name, 'load_policy' => $loadPolicy],
    ];
};

$entries = [
    $row(1, 'Module_Alpha', 'UTF-8', 'lazy'),
    $row(2, 'module_alpha', 'UTF-16LE'),
    $row(3, 'module_beta', 'UTF-16BE', 'lazy'),
    $row(4, 'module_beta ', 'UTF-8', 'lazy'),
    $row(5, 'module_100%_enabled', 'UTF-16LE'),
    $row(6, 'module_100x_enabled', 'UTF-16BE'),
    $row(7, 'module_éclair', 'UTF-8'),
    $row(8, 'module_Éclair', 'UTF-16LE', 'lazy'),
    $row(9, 'module_Ωmega', 'UTF-16BE'),
    $row(10, 'module_😀_cache', 'UTF-16LE'),
    $row(11, 'profile_alpha', 'UTF-8'),
];

$cursor = static fn (
    string $pattern = 'module%',
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
    'default like starts at literal digit prefix under nocase' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'currentRowid', 5],
    'default like current encoding is utf16le' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'currentEncoding', 'UTF-16LE'],
    'default like next row is utf16be literal x peer' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'nextRowid', 6],
    'default like next encoding is utf16be' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'nextEncoding', 'UTF-16BE'],
    'default like lower bound is folded ascii prefix' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'range.lowerInclusive', 'module'],
    'default like upper bound is next ascii prefix' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'range.upperBound', 'modulf'],
    'default like uppercase peer residual matches' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'residualMatch', true],
    'default like uppercase peer stays in range' => ['module%', 'LIKE', 'NOCASE', null, false, 0, 'inRange', true],
    'default like escaped literal percent current row' => ['module\_100\%%', 'LIKE', 'NOCASE', '\\', false, 0, 'currentRowid', 5],
    'default like escaped literal percent skips wildcard x residual' => ['module\_100\%%', 'LIKE', 'NOCASE', '\\', false, 1, 'residualMatch', false],
    'case sensitive like binary starts at literal digit prefix' => ['module%', 'LIKE', 'BINARY', null, true, 0, 'currentRowid', 5],
    'case sensitive like binary current is above lower bound' => ['module%', 'LIKE', 'BINARY', null, true, 0, 'comparisonToLower', 1],
    'case sensitive literal percent keeps utf16le bytes' => ['module\_100\%%', 'LIKE', 'BINARY', '\\', true, 0, 'currentBytesHex', '6d006f00640075006c0065005f0031003000300025005f0065006e00610062006c0065006400'],
    'case sensitive literal percent next x exits residual' => ['module\_100\%%', 'LIKE', 'BINARY', '\\', true, 1, 'residualMatch', false],
    'rtrim like is rejected before current source scan' => ['module_beta', 'LIKE', 'RTRIM', null, true, 0, 'range', null],
    'rtrim like current is not range usable' => ['module_beta', 'LIKE', 'RTRIM', null, true, 0, 'inRange', false],
    'rtrim like current residual may still match outside index range' => ['module_beta', 'LIKE', 'RTRIM', null, true, 0, 'residualMatch', false],
    'glob binary starts at literal digit prefix' => ['module_*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 5],
    'glob binary current encoding is utf16le' => ['module_*', 'GLOB', 'BINARY', null, false, 0, 'currentEncoding', 'UTF-16LE'],
    'glob binary beta next is utf16be' => ['module_*', 'GLOB', 'BINARY', null, false, 1, 'currentEncoding', 'UTF-16BE'],
    'glob nocase starts at literal digit prefix' => ['module_*', 'GLOB', 'NOCASE', null, false, 0, 'currentRowid', 5],
    'glob nocase digit peer residual is true' => ['module_*', 'GLOB', 'NOCASE', null, false, 0, 'residualMatch', true],
    'glob unicode latin range begins literal digit candidate' => ['module_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 5],
    'glob unicode latin first ascii candidate residual false' => ['module_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 0, 'residualMatch', false],
    'glob emoji range current is emoji setting' => ['module_😀*', 'GLOB', 'BINARY', null, false, 0, 'currentRowid', 10],
    'glob emoji current utf16 bytes expose surrogate pair' => ['module_😀*', 'GLOB', 'BINARY', null, false, 0, 'currentBytesHex', '6d006f00640075006c0065005f003dd800de5f0063006100630068006500'],
    'leading wildcard like has no range' => ['%module', 'LIKE', 'NOCASE', null, false, 0, 'range', null],
    'leading class glob has no range' => ['[Mm]odule_*', 'GLOB', 'BINARY', null, false, 0, 'range', null],
    'eof keeps dependencies after exhausted scan' => ['module_zz%', 'LIKE', 'NOCASE', null, false, 20, 'dependencies.0', 'sqlite-encoding-source-cursor'],
    'eof reports null current after exhausted scan' => ['module_zz%', 'LIKE', 'NOCASE', null, false, 20, 'currentRowid', null],
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
    'default nocase like matches mixed encodings' => ['module%', 'LIKE', 'NOCASE', null, false, [5, 6, 1, 2, 3, 4, 8, 7, 9, 10]],
    'case sensitive binary like skips uppercase' => ['module%', 'LIKE', 'BINARY', null, true, [5, 6, 2, 3, 4, 8, 7, 9, 10]],
    'escaped percent like excludes x row' => ['module\_100\%%', 'LIKE', 'NOCASE', '\\', false, [5]],
    'unescaped percent like includes x row' => ['module\_100%', 'LIKE', 'NOCASE', '\\', false, [5, 6]],
    'rtrim exact beta has no usable case-sensitive like cursor range' => ['module_beta', 'LIKE', 'RTRIM', null, true, []],
    'rtrim wildcard beta has no usable case-sensitive like cursor range' => ['module_beta%', 'LIKE', 'RTRIM', null, true, []],
    'binary glob skips uppercase peer' => ['module_*', 'GLOB', 'BINARY', null, false, [5, 6, 2, 3, 4, 8, 7, 9, 10]],
    'nocase glob range still residual filters uppercase peer' => ['module_*', 'GLOB', 'NOCASE', null, false, [5, 6, 2, 3, 4, 8, 7, 9, 10]],
    'glob latin range returns encoded e acute peers' => ['module_[À-ÿ]*', 'GLOB', 'BINARY', null, false, [8, 7]],
    'glob greek range returns omega row' => ['module_[Α-ω]*', 'GLOB', 'BINARY', null, false, [9]],
    'glob emoji prefix returns utf16le emoji row' => ['module_😀*', 'GLOB', 'BINARY', null, false, [10]],
    'leading wildcard like has no source cursor matches' => ['%alpha', 'LIKE', 'NOCASE', null, false, []],
    'leading class glob has no source cursor matches' => ['[Mm]odule_*', 'GLOB', 'BINARY', null, false, []],
];

foreach ($matchCases as $name => [$pattern, $operator, $collation, $escape, $caseSensitive, $expectedRowids]) {
    $tests['encoding collation source next82 matched rows ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $operator, $collation, $escape, $caseSensitive, $expectedRowids): void {
        $t->same($expectedRowids, array_column($cursor($pattern, $operator, $collation, $escape, $caseSensitive)->matchedRows(), 'rowid'));
    };
}

$tests['encoding collation source next82 matched rows preserve payload and source encoding'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('module_😀%', 'LIKE', 'BINARY', null, true)->matchedRows();
    $t->same('module_😀_cache', $rows[0]['payload']['key_name']);
    $t->same('UTF-16LE', $rows[0]['textEncoding']);
    $t->same('eager', $rows[0]['payload']['load_policy']);
};

$tests['encoding collation source next82 application setting scan maps generic columns'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('module_100%_enabled', 'UTF-16LE'), 'text_encoding' => 2, 'load_policy' => 'eager'],
        ['setting_id' => 2, 'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('Module_100%_Enabled', 'UTF-8'), 'text_encoding' => 1, 'load_policy' => 'lazy'],
        ['setting_id' => 3, 'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('module_100x_enabled', 'UTF-16BE'), 'text_encoding' => 3, 'load_policy' => 'eager'],
    ];

    $matched = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan($rows, 'module\_100\%%', 'LIKE', 'NOCASE', '\\');
    $t->same([1, 2], array_column($matched, 'rowid'));
    $t->same(['UTF-16LE', 'UTF-8'], array_column($matched, 'textEncoding'));
};

$tests['encoding collation source next82 encoder rejects malformed utf8'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationSourceCursor::encodeText("module_\xc3", 'UTF-16LE'));
};

$tests['encoding collation source next82 rejects malformed utf8 source bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor([['keyBytes' => "module_\xc3", 'textEncoding' => 1, 'rowid' => 1, 'payload' => []]], 'module%'));
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
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteEncodingCollationSourceCursor($entries, 'p%', 'LIKE', 'APP_LOCALE'));
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
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan([['setting_id' => 1, 'key_name_bytes' => 'module']], 'p%'));
};

return $tests;
