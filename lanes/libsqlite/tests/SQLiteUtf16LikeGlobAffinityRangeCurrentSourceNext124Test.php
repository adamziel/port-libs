<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan;

$tests = [];

$bytes = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['setting_id' => 1, 'key_value' => 'load-policy:yes'],
    ['setting_id' => 2, 'key_value' => 10],
    ['setting_id' => 3, 'key_value' => 10.5],
    ['setting_id' => 4, 'key_value' => true],
    ['setting_id' => 5, 'key_value' => false],
    ['setting_id' => 6, 'key_value' => 'module_α:enabled'],
    ['setting_id' => 7, 'key_value' => 'module_β:disabled'],
    ['setting_id' => 8, 'key_value' => 'cache:%literal'],
    ['setting_id' => 9, 'key_value' => 'emoji:😀:enabled'],
    ['setting_id' => 10, 'key_value' => 'LOAD-POLICY:UPPER'],
    ['setting_id' => 11, 'key_value' => null],
];

$nextRows = [
    ['setting_id' => 1, 'key_value' => 'load-policy:yes-v2'],
    ['setting_id' => 2, 'key_value' => '10'],
    ['setting_id' => 3, 'key_value' => 10.5],
    ['setting_id' => 4, 'key_value' => false],
    ['setting_id' => 5, 'key_value' => false],
    ['setting_id' => 6, 'key_value' => 'module_α:enabled'],
    ['setting_id' => 7, 'key_value' => 'module_γ:enabled'],
    ['setting_id' => 8, 'key_value' => 'cache:%literal'],
    ['setting_id' => 9, 'key_value' => 'emoji:😀:enabled'],
    ['setting_id' => 10, 'key_value' => 'LOAD-POLICY:UPPER'],
    ['setting_id' => 13, 'key_value' => 'load-policy:fresh'],
];

$plan = static fn (
    string $pattern,
    string $patternEncoding = 'UTF-16LE',
    string $operator = 'LIKE',
    string $affinity = 'TEXT',
    string $collation = 'BINARY',
    ?string $escape = null,
    ?string $escapeEncoding = null,
    bool $caseSensitiveLike = true,
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
    int $currentCookie = 1240,
    int $nextCookie = 1241,
): array => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'key_value',
    $bytes($pattern, $patternEncoding),
    $patternEncoding,
    $operator,
    $affinity,
    $collation,
    $escape === null ? null : $bytes($escape, $escapeEncoding ?? $patternEncoding),
    $escapeEncoding,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'decoded load-policy pattern' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'decodedPattern', 'load-policy:%'],
    'load-policy pattern encoding' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternEncoding', 'UTF-16LE'],
    'load-policy pattern bytes' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternBytesHex', '6c006f00610064002d0070006f006c006900630079003a002500'],
    'load-policy utf16be canonical bytes' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternUtf16BeHex', '006c006f00610064002d0070006f006c006900630079003a0025'],
    'load-policy binary range lower' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'range.lowerInclusive', 'load-policy:'],
    'load-policy residual current rowids' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [1]],
    'load-policy residual next rowids' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [13, 1]],
    'load-policy retained rowids' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'retainedRowids', [1]],
    'load-policy entered rowids' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'enteredRowids', [13]],
    'load-policy changed text' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'changedTextRowids', [1]],
    'load-policy changed bytes' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'changedBytesRowids', [1]],
    'load-policy invalidated' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'cursorInvalidated', true],
    'load-policy reason schema cookie' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.0', 'schema-cookie'],
    'load-policy reason text affinity' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.1', 'text-affinity'],
    'load-policy reason encoded bytes' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.2', 'encoded-bytes'],
    'load-policy reason rowset' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.3', 'matched-rowset'],
    'nocase range lower text' => ['load-policy:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'range.lowerInclusive', 'load-policy:'],
    'nocase range upper text' => ['load-policy:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'range.upperBound', 'load-policy;'],
    'nocase range lower utf16le bytes' => ['load-policy:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'rangeUtf16LeHex.lowerInclusive', '6c006f00610064002d0070006f006c006900630079003a00'],
    'nocase range upper utf16be bytes' => ['load-policy:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'rangeUtf16BeHex.upperBound', '006c006f00610064002d0070006f006c006900630079003b'],
    'nocase current includes upper' => ['load-policy:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'currentRowids', [10, 1]],
    'nocase next includes fresh' => ['load-policy:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'nextRowids', [13, 10, 1]],
    'numeric prefix current' => ['10%', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'currentRowids', [2, 3]],
    'numeric prefix next' => ['10%', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'nextRowids', [2, 3]],
    'numeric equality changed storage' => ['10', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'changedStorageRowids', [2]],
    'numeric equality changed bytes' => ['10', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'changedBytesRowids', []],
    'true current one' => ['1', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'currentRowids', [4]],
    'true next exited' => ['1', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'nextRowids', []],
    'false current zero' => ['0', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'currentRowids', [5]],
    'false next zero' => ['0', 'UTF-16LE', 'LIKE', 'NUMERIC', 'BINARY', null, null, true, 'nextRowids', [4, 5]],
    'escaped percent decoded' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'TEXT', 'BINARY', '!', 'UTF-16BE', true, 'decodedEscape', '!'],
    'escaped percent encoding' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'TEXT', 'BINARY', '!', 'UTF-16BE', true, 'escapeEncoding', 'UTF-16BE'],
    'escaped percent bytes' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'TEXT', 'BINARY', '!', 'UTF-16BE', true, 'escapeBytesHex', '0021'],
    'escaped percent current row' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'TEXT', 'BINARY', '!', 'UTF-16BE', true, 'currentRowids', [8]],
    'escaped percent next row' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'TEXT', 'BINARY', '!', 'UTF-16BE', true, 'nextRowids', [8]],
    'greek alpha current' => ['module_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [6]],
    'greek alpha next' => ['module_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [6]],
    'greek beta exits' => ['module_β:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'exitedRowids', [7]],
    'greek gamma enters' => ['module_γ:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'enteredRowids', [7]],
    'emoji current' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [9]],
    'emoji pattern utf16le' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternUtf16LeHex', '65006d006f006a0069003a003dd800de3a002500'],
    'glob load-policy decoded' => ['load-policy:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'decodedPattern', 'load-policy:*'],
    'glob load-policy range lower' => ['load-policy:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'range.lowerInclusive', 'load-policy:'],
    'glob load-policy range upper' => ['load-policy:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'range.upperBound', 'load-policy;'],
    'glob load-policy current rowids' => ['load-policy:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [1]],
    'glob load-policy next rowids' => ['load-policy:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [13, 1]],
    'glob greek class current' => ['module_[αβ]:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [6, 7]],
    'glob greek class next' => ['module_[αβ]:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [6]],
    'glob gamma next' => ['module_[γ]:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [7]],
    'glob emoji current' => ['emoji:😀:*', 'UTF-16BE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [9]],
    'source switch reason first' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.0', 'source-name', 'main.app_settings', 'temp.app_settings'],
    'source switch next source' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'nextSource', 'temp.app_settings', 'main.app_settings', 'temp.app_settings'],
    'reusable current rowids' => ['module_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [6], 'main.app_settings', 'main.app_settings', 1240, 1240],
    'reusable flag stays true' => ['module_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'cursorReusable', true, 'main.app_settings', 'main.app_settings', 1240, 1240],
    'dependency pattern decode' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'dependencies.0', 'sqlite-utf16-like-glob-pattern-decode'],
    'dependency affinity range' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'dependencies.1', 'sqlite-like-glob-affinity-range'],
    'dependency current source' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'dependencies.2', 'sqlite-current-source-nextoneTwoFour'],
    'pattern source marker' => ['load-policy:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternSource', 'decoded-utf16-pattern-bytes'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 like glob affinity range current source nextOneTwoFour ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$pattern, $patternEncoding, $operator, $affinity, $collation, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[10] ?? 'main.app_settings';
        $nextSource = $case[11] ?? 'main.app_settings';
        $currentCookie = $case[12] ?? 1240;
        $nextCookie = $case[13] ?? 1241;
        $t->same($expected, $valueAt($plan($pattern, $patternEncoding, $operator, $affinity, $collation, $escape, $escapeEncoding, $caseSensitiveLike, $currentSource, $nextSource, $currentCookie, $nextCookie), $path));
    };
}

$tests['utf16 like glob affinity range current source nextOneTwoFour accepts utf8 pattern bytes'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $plan = SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $bytes('load-policy:%', 'UTF-8'), 'UTF-8');
    $t->same([1], $plan['currentRowids']);
};

$tests['utf16 like glob affinity range current source nextOneTwoFour accepts utf16 keyword alias'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $plan = SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $bytes('load-policy:%', 'UTF-16LE'), 'UTF-16');
    $t->same('UTF-16LE', $plan['patternEncoding']);
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects invalid encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $bytes('load-policy:%', 'UTF-16LE'), 'UTF-32'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects malformed utf16 pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects malformed utf16 escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $bytes('cache:!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects multi-character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $bytes('cache:!!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', $bytes('!!', 'UTF-16LE'), 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', $bytes('load-policy:*', 'UTF-16LE'), 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', $bytes('!', 'UTF-16LE'), 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects malformed row text after decode'] = static function (TestRunner $t) use ($bytes, $nextRows): void {
    $badRows = [['setting_id' => 12, 'key_value' => "bad\xc3"]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($badRows, $nextRows, 'key_value', $bytes('bad%', 'UTF-16LE'), 'UTF-16LE'));
};

return $tests;
