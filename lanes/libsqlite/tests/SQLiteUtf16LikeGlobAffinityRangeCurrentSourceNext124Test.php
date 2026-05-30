<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan;

$tests = [];

$bytes = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['option_id' => 1, 'option_value' => 'autoload:yes'],
    ['option_id' => 2, 'option_value' => 10],
    ['option_id' => 3, 'option_value' => 10.5],
    ['option_id' => 4, 'option_value' => true],
    ['option_id' => 5, 'option_value' => false],
    ['option_id' => 6, 'option_value' => 'plugin_α:enabled'],
    ['option_id' => 7, 'option_value' => 'plugin_β:disabled'],
    ['option_id' => 8, 'option_value' => 'cache:%literal'],
    ['option_id' => 9, 'option_value' => 'emoji:😀:enabled'],
    ['option_id' => 10, 'option_value' => 'AUTOLOAD:UPPER'],
    ['option_id' => 11, 'option_value' => null],
];

$nextRows = [
    ['option_id' => 1, 'option_value' => 'autoload:yes-v2'],
    ['option_id' => 2, 'option_value' => '10'],
    ['option_id' => 3, 'option_value' => 10.5],
    ['option_id' => 4, 'option_value' => false],
    ['option_id' => 5, 'option_value' => false],
    ['option_id' => 6, 'option_value' => 'plugin_α:enabled'],
    ['option_id' => 7, 'option_value' => 'plugin_γ:enabled'],
    ['option_id' => 8, 'option_value' => 'cache:%literal'],
    ['option_id' => 9, 'option_value' => 'emoji:😀:enabled'],
    ['option_id' => 10, 'option_value' => 'AUTOLOAD:UPPER'],
    ['option_id' => 13, 'option_value' => 'autoload:fresh'],
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
    string $currentSource = 'main.wp_options',
    string $nextSource = 'main.wp_options',
    int $currentCookie = 1240,
    int $nextCookie = 1241,
): array => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'option_value',
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
    'decoded autoload pattern' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'decodedPattern', 'autoload:%'],
    'autoload pattern encoding' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternEncoding', 'UTF-16LE'],
    'autoload pattern bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternBytesHex', '6100750074006f006c006f00610064003a002500'],
    'autoload utf16be canonical bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternUtf16BeHex', '006100750074006f006c006f00610064003a0025'],
    'autoload binary range lower' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'range.lowerInclusive', 'autoload:'],
    'autoload residual current rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [1]],
    'autoload residual next rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [13, 1]],
    'autoload retained rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'retainedRowids', [1]],
    'autoload entered rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'enteredRowids', [13]],
    'autoload changed text' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'changedTextRowids', [1]],
    'autoload changed bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'changedBytesRowids', [1]],
    'autoload invalidated' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'cursorInvalidated', true],
    'autoload reason schema cookie' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.0', 'schema-cookie'],
    'autoload reason text affinity' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.1', 'text-affinity'],
    'autoload reason encoded bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.2', 'encoded-bytes'],
    'autoload reason rowset' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.3', 'matched-rowset'],
    'nocase range lower text' => ['autoload:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'range.lowerInclusive', 'autoload:'],
    'nocase range upper text' => ['autoload:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'range.upperBound', 'autoload;'],
    'nocase range lower utf16le bytes' => ['autoload:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'rangeUtf16LeHex.lowerInclusive', '6100750074006f006c006f00610064003a00'],
    'nocase range upper utf16be bytes' => ['autoload:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'rangeUtf16BeHex.upperBound', '006100750074006f006c006f00610064003b'],
    'nocase current includes upper' => ['autoload:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'currentRowids', [10, 1]],
    'nocase next includes fresh' => ['autoload:%', 'UTF-16BE', 'LIKE', 'TEXT', 'NOCASE', null, null, false, 'nextRowids', [13, 10, 1]],
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
    'greek alpha current' => ['plugin_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [6]],
    'greek alpha next' => ['plugin_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [6]],
    'greek beta exits' => ['plugin_β:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'exitedRowids', [7]],
    'greek gamma enters' => ['plugin_γ:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'enteredRowids', [7]],
    'emoji current' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [9]],
    'emoji pattern utf16le' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternUtf16LeHex', '65006d006f006a0069003a003dd800de3a002500'],
    'glob autoload decoded' => ['autoload:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'decodedPattern', 'autoload:*'],
    'glob autoload range lower' => ['autoload:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'range.lowerInclusive', 'autoload:'],
    'glob autoload range upper' => ['autoload:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'range.upperBound', 'autoload;'],
    'glob autoload current rowids' => ['autoload:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [1]],
    'glob autoload next rowids' => ['autoload:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [13, 1]],
    'glob greek class current' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [6, 7]],
    'glob greek class next' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [6]],
    'glob gamma next' => ['plugin_[γ]:*', 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'nextRowids', [7]],
    'glob emoji current' => ['emoji:😀:*', 'UTF-16BE', 'GLOB', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [9]],
    'source switch reason first' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'invalidationReasons.0', 'source-name', 'main.wp_options', 'temp.wp_options'],
    'source switch next source' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'nextSource', 'temp.wp_options', 'main.wp_options', 'temp.wp_options'],
    'reusable current rowids' => ['plugin_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'currentRowids', [6], 'main.wp_options', 'main.wp_options', 1240, 1240],
    'reusable flag stays true' => ['plugin_α:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'cursorReusable', true, 'main.wp_options', 'main.wp_options', 1240, 1240],
    'dependency pattern decode' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'dependencies.0', 'sqlite-utf16-like-glob-pattern-decode'],
    'dependency affinity range' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'dependencies.1', 'sqlite-like-glob-affinity-range'],
    'dependency current source' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'dependencies.2', 'sqlite-current-source-nextoneTwoFour'],
    'pattern source marker' => ['autoload:%', 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', null, null, true, 'patternSource', 'decoded-utf16-pattern-bytes'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 like glob affinity range current source nextOneTwoFour ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$pattern, $patternEncoding, $operator, $affinity, $collation, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[10] ?? 'main.wp_options';
        $nextSource = $case[11] ?? 'main.wp_options';
        $currentCookie = $case[12] ?? 1240;
        $nextCookie = $case[13] ?? 1241;
        $t->same($expected, $valueAt($plan($pattern, $patternEncoding, $operator, $affinity, $collation, $escape, $escapeEncoding, $caseSensitiveLike, $currentSource, $nextSource, $currentCookie, $nextCookie), $path));
    };
}

$tests['utf16 like glob affinity range current source nextOneTwoFour accepts utf8 pattern bytes'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $plan = SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', $bytes('autoload:%', 'UTF-8'), 'UTF-8');
    $t->same([1], $plan['currentRowids']);
};

$tests['utf16 like glob affinity range current source nextOneTwoFour accepts utf16 keyword alias'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $plan = SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', $bytes('autoload:%', 'UTF-16LE'), 'UTF-16');
    $t->same('UTF-16LE', $plan['patternEncoding']);
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects invalid encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', $bytes('autoload:%', 'UTF-16LE'), 'UTF-32'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects malformed utf16 pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects malformed utf16 escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', $bytes('cache:!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects multi-character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', $bytes('cache:!!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'TEXT', 'BINARY', $bytes('!!', 'UTF-16LE'), 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'option_value', $bytes('autoload:*', 'UTF-16LE'), 'UTF-16LE', 'GLOB', 'TEXT', 'BINARY', $bytes('!', 'UTF-16LE'), 'UTF-16LE'));
};

$tests['utf16 like glob affinity range current source nextOneTwoFour rejects malformed row text after decode'] = static function (TestRunner $t) use ($bytes, $nextRows): void {
    $badRows = [['option_id' => 12, 'option_value' => "bad\xc3"]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($badRows, $nextRows, 'option_value', $bytes('bad%', 'UTF-16LE'), 'UTF-16LE'));
};

return $tests;
