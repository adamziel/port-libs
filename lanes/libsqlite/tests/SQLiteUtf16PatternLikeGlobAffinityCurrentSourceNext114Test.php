<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan;

$tests = [];

$textRow = static function (int $id, string $value, string $encoding = 'UTF-8'): array {
    return [
        'setting_id' => $id,
        'key_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$patternBytes = static fn (string $pattern, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($pattern, $encoding);

$currentRows = [
    $textRow(1, 'loadflag:yes', 'UTF-8'),
    $textRow(2, 'loadflag:no', 'UTF-16LE'),
    $textRow(3, 'cache:%literal', 'UTF-16BE'),
    $textRow(4, 'plugin_α:enabled', 'UTF-16LE'),
    $textRow(5, 'plugin_β:disabled', 'UTF-16BE'),
    $textRow(6, 'emoji:😀:enabled', 'UTF-16LE'),
    ['setting_id' => 7, 'key_value' => 10],
    ['setting_id' => 8, 'key_value' => 10.5],
    ['setting_id' => 9, 'key_value' => true],
    ['setting_id' => 10, 'key_value' => null],
    ['setting_id' => 11, 'key_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$nextRows = [
    $textRow(1, 'loadflag:yes', 'UTF-16LE'),
    $textRow(2, 'loadflag:no-v2', 'UTF-16LE'),
    $textRow(3, 'cache:%literal', 'UTF-16LE'),
    $textRow(4, 'plugin_α:enabled', 'UTF-16BE'),
    $textRow(5, 'plugin_γ:enabled', 'UTF-16BE'),
    $textRow(6, 'emoji:😀:enabled', 'UTF-16LE'),
    ['setting_id' => 7, 'key_value' => '10'],
    ['setting_id' => 8, 'key_value' => 10.5],
    ['setting_id' => 9, 'key_value' => false],
    ['setting_id' => 10, 'key_value' => null],
    $textRow(12, 'loadflag:fresh', 'UTF-16BE'),
    ['setting_id' => 13, 'key_value_bytes' => "\xd8\x00", 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern,
    string $patternEncoding = 'UTF-16LE',
    string $operator = 'LIKE',
    ?string $escape = null,
    ?string $escapeEncoding = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
): array => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    $patternBytes($pattern, $patternEncoding),
    $patternEncoding,
    $operator,
    $escape === null ? null : $patternBytes($escape, $escapeEncoding ?? $patternEncoding),
    $escapeEncoding,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'loadflag decoded pattern' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'decodedPattern', 'loadflag:%'],
    'loadflag pattern encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'patternEncoding', 'UTF-16LE'],
    'loadflag pattern bytes' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'patternBytesHex', '6c006f006100640066006c00610067003a002500'],
    'loadflag current rowids' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [1, 2]],
    'loadflag next rowids' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', [1, 2, 12]],
    'loadflag retained rowids' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'retainedRowids', [1, 2]],
    'loadflag entered rowids' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'enteredRowids', [12]],
    'loadflag changed encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'changedEncodingRowids', [1]],
    'loadflag changed bytes' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'changedBytesRowids', [1, 2]],
    'loadflag invalidated' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'cursorInvalidated', true],
    'loadflag reason encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.0', 'text-encoding'],
    'loadflag reason bytes' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.1', 'value-bytes'],
    'loadflag reason rowset' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.2', 'matched-rowset'],
    'loadflag reason malformed' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.3', 'malformed-text'],
    'loadflag current malformed rowids' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentMalformedRowids', [11]],
    'loadflag next malformed rowids' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextMalformedRowids', [13]],
    'loadflag first current encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentMatchedRows.0.textEncoding', 'UTF-8'],
    'loadflag first next encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextMatchedRows.0.textEncoding', 'UTF-16LE'],
    'loadflag fresh text' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextMatchedRows.2.textValue', 'loadflag:fresh'],
    'literal percent escape decoded' => ['cache:!%%', 'UTF-16BE', 'LIKE', '!', 'UTF-16BE', false, 'decodedEscape', '!'],
    'literal percent escape encoding' => ['cache:!%%', 'UTF-16BE', 'LIKE', '!', 'UTF-16BE', false, 'escapeEncoding', 'UTF-16BE'],
    'literal percent current row' => ['cache:!%%', 'UTF-16BE', 'LIKE', '!', 'UTF-16BE', false, 'currentRowids', [3]],
    'literal percent next row' => ['cache:!%%', 'UTF-16BE', 'LIKE', '!', 'UTF-16BE', false, 'nextRowids', [3]],
    'literal percent changed encoding' => ['cache:!%%', 'UTF-16BE', 'LIKE', '!', 'UTF-16BE', false, 'changedEncodingRowids', [3]],
    'literal percent changed bytes' => ['cache:!%%', 'UTF-16BE', 'LIKE', '!', 'UTF-16BE', false, 'changedBytesRowids', [3]],
    'greek alpha utf16be current' => ['plugin_α:%', 'UTF-16BE', 'LIKE', null, null, false, 'currentRowids', [4]],
    'greek alpha utf16be next' => ['plugin_α:%', 'UTF-16BE', 'LIKE', null, null, false, 'nextRowids', [4]],
    'greek alpha current text' => ['plugin_α:%', 'UTF-16BE', 'LIKE', null, null, false, 'currentMatchedRows.0.textValue', 'plugin_α:enabled'],
    'greek beta only current' => ['plugin_β:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [5]],
    'greek beta exits next' => ['plugin_β:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', []],
    'greek gamma enters next' => ['plugin_γ:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', [5]],
    'emoji current like' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [6]],
    'emoji next like' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', [6]],
    'emoji pattern bytes' => ['emoji:😀:%', 'UTF-16LE', 'LIKE', null, null, false, 'patternBytesHex', '65006d006f006a0069003a003dd800de3a002500'],
    'numeric integer current' => ['10%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [7, 8]],
    'numeric integer next' => ['10%', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', [7, 8]],
    'numeric changed storage' => ['10', 'UTF-16LE', 'LIKE', null, null, false, 'changedStorageRowids', [7]],
    'numeric changed bytes' => ['10', 'UTF-16LE', 'LIKE', null, null, false, 'changedBytesRowids', [7]],
    'bool true current' => ['1', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [9]],
    'bool false next no true' => ['1', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', []],
    'null empty current' => ['', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [10]],
    'null empty next' => ['', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', [10]],
    'case insensitive ascii current' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [1, 2]],
    'case sensitive ascii current empty' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', null, null, true, 'currentRowids', []],
    'glob loadflag current' => ['loadflag:*', 'UTF-16LE', 'GLOB', null, null, false, 'currentRowids', [1, 2]],
    'glob loadflag next' => ['loadflag:*', 'UTF-16LE', 'GLOB', null, null, false, 'nextRowids', [1, 2, 12]],
    'glob greek class current' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', null, null, false, 'currentRowids', [4, 5]],
    'glob greek class next' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', null, null, false, 'nextRowids', [4]],
    'glob greek gamma next' => ['plugin_[γ]:*', 'UTF-16LE', 'GLOB', null, null, false, 'nextRowids', [5]],
    'glob emoji current' => ['emoji:😀:*', 'UTF-16BE', 'GLOB', null, null, false, 'currentRowids', [6]],
    'glob emoji pattern encoding' => ['emoji:😀:*', 'UTF-16BE', 'GLOB', null, null, false, 'patternEncoding', 'UTF-16BE'],
    'source switch reason' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.0', 'source-name', 'main.app_settings', 'temp.app_settings'],
    'source switch next source' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextSource', 'temp.app_settings', 'main.app_settings', 'temp.app_settings'],
    'dependency decode' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency affinity' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'dependencies.1', 'sqlite-like-glob-affinity'],
    'dependency marker' => ['loadflag:%', 'UTF-16LE', 'LIKE', null, null, false, 'dependencies.2', 'sqlite-current-source-nextoneOneFour'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 pattern like glob affinity current source nextOneOneFour ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $patternEncoding, $operator, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[8] ?? 'main.app_settings';
        $nextSource = $case[9] ?? 'main.app_settings';
        $value = $plan($pattern, $patternEncoding, $operator, $escape, $escapeEncoding, $caseSensitiveLike, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 pattern like glob affinity current source nextOneOneFour accepts utf8 pattern encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $plan = SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('loadflag:%', 'UTF-8'), 'UTF-8');
    $t->same([1, 2], $plan['currentRowids']);
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour accepts utf16 keyword alias'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $plan = SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('loadflag:%', 'UTF-16LE'), 'UTF-16');
    $t->same('UTF-16LE', $plan['patternEncoding']);
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('loadflag:%', 'UTF-16LE'), 'UTF-16LE', 'REGEXP'));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('loadflag:*', 'UTF-16LE'), 'UTF-16LE', 'GLOB', $patternBytes('!', 'UTF-16LE')));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects multi character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('cache:!!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', $patternBytes('!!', 'UTF-16LE')));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects invalid pattern encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('loadflag:%', 'UTF-16LE'), 'UTF-32'));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects malformed utf16 pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects malformed utf16 escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('cache:!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', "\x00\xd8", 'UTF-16LE'));
};

return $tests;
