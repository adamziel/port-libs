<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan;

$tests = [];

$textRow = static function (int $id, string $value, string $encoding = 'UTF-8'): array {
    return [
        'option_id' => $id,
        'option_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$patternBytes = static fn (string $pattern, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($pattern, $encoding);

$currentRows = [
    $textRow(1, 'autoload:yes', 'UTF-8'),
    $textRow(2, 'autoload:no', 'UTF-16LE'),
    $textRow(3, 'cache:%literal', 'UTF-16BE'),
    $textRow(4, 'plugin_α:enabled', 'UTF-16LE'),
    $textRow(5, 'plugin_β:disabled', 'UTF-16BE'),
    $textRow(6, 'emoji:😀:enabled', 'UTF-16LE'),
    ['option_id' => 7, 'option_value' => 10],
    ['option_id' => 8, 'option_value' => 10.5],
    ['option_id' => 9, 'option_value' => true],
    ['option_id' => 10, 'option_value' => null],
    ['option_id' => 11, 'option_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$nextRows = [
    $textRow(1, 'autoload:yes', 'UTF-16LE'),
    $textRow(2, 'autoload:no-v2', 'UTF-16LE'),
    $textRow(3, 'cache:%literal', 'UTF-16LE'),
    $textRow(4, 'plugin_α:enabled', 'UTF-16BE'),
    $textRow(5, 'plugin_γ:enabled', 'UTF-16BE'),
    $textRow(6, 'emoji:😀:enabled', 'UTF-16LE'),
    ['option_id' => 7, 'option_value' => '10'],
    ['option_id' => 8, 'option_value' => 10.5],
    ['option_id' => 9, 'option_value' => false],
    ['option_id' => 10, 'option_value' => null],
    $textRow(12, 'autoload:fresh', 'UTF-16BE'),
    ['option_id' => 13, 'option_value_bytes' => "\xd8\x00", 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern,
    string $patternEncoding = 'UTF-16LE',
    string $operator = 'LIKE',
    ?string $escape = null,
    ?string $escapeEncoding = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.wp_options',
    string $nextSource = 'main.wp_options',
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
    'autoload decoded pattern' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'decodedPattern', 'autoload:%'],
    'autoload pattern encoding' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'patternEncoding', 'UTF-16LE'],
    'autoload pattern bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'patternBytesHex', '6100750074006f006c006f00610064003a002500'],
    'autoload current rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [1, 2]],
    'autoload next rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextRowids', [1, 2, 12]],
    'autoload retained rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'retainedRowids', [1, 2]],
    'autoload entered rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'enteredRowids', [12]],
    'autoload changed encoding' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'changedEncodingRowids', [1]],
    'autoload changed bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'changedBytesRowids', [1, 2]],
    'autoload invalidated' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'cursorInvalidated', true],
    'autoload reason encoding' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.0', 'text-encoding'],
    'autoload reason bytes' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.1', 'value-bytes'],
    'autoload reason rowset' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.2', 'matched-rowset'],
    'autoload reason malformed' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.3', 'malformed-text'],
    'autoload current malformed rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentMalformedRowids', [11]],
    'autoload next malformed rowids' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextMalformedRowids', [13]],
    'autoload first current encoding' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentMatchedRows.0.textEncoding', 'UTF-8'],
    'autoload first next encoding' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextMatchedRows.0.textEncoding', 'UTF-16LE'],
    'autoload fresh text' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextMatchedRows.2.textValue', 'autoload:fresh'],
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
    'case insensitive ascii current' => ['AUTOLOAD:%', 'UTF-16LE', 'LIKE', null, null, false, 'currentRowids', [1, 2]],
    'case sensitive ascii current empty' => ['AUTOLOAD:%', 'UTF-16LE', 'LIKE', null, null, true, 'currentRowids', []],
    'glob autoload current' => ['autoload:*', 'UTF-16LE', 'GLOB', null, null, false, 'currentRowids', [1, 2]],
    'glob autoload next' => ['autoload:*', 'UTF-16LE', 'GLOB', null, null, false, 'nextRowids', [1, 2, 12]],
    'glob greek class current' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', null, null, false, 'currentRowids', [4, 5]],
    'glob greek class next' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', null, null, false, 'nextRowids', [4]],
    'glob greek gamma next' => ['plugin_[γ]:*', 'UTF-16LE', 'GLOB', null, null, false, 'nextRowids', [5]],
    'glob emoji current' => ['emoji:😀:*', 'UTF-16BE', 'GLOB', null, null, false, 'currentRowids', [6]],
    'glob emoji pattern encoding' => ['emoji:😀:*', 'UTF-16BE', 'GLOB', null, null, false, 'patternEncoding', 'UTF-16BE'],
    'source switch reason' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'invalidationReasons.0', 'source-name', 'main.wp_options', 'temp.wp_options'],
    'source switch next source' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'nextSource', 'temp.wp_options', 'main.wp_options', 'temp.wp_options'],
    'dependency decode' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency affinity' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'dependencies.1', 'sqlite-like-glob-affinity'],
    'dependency marker' => ['autoload:%', 'UTF-16LE', 'LIKE', null, null, false, 'dependencies.2', 'sqlite-current-source-nextoneOneFour'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 pattern like glob affinity current source nextOneOneFour ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $patternEncoding, $operator, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[8] ?? 'main.wp_options';
        $nextSource = $case[9] ?? 'main.wp_options';
        $value = $plan($pattern, $patternEncoding, $operator, $escape, $escapeEncoding, $caseSensitiveLike, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 pattern like glob affinity current source nextOneOneFour accepts utf8 pattern encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $plan = SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('autoload:%', 'UTF-8'), 'UTF-8');
    $t->same([1, 2], $plan['currentRowids']);
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour accepts utf16 keyword alias'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $plan = SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('autoload:%', 'UTF-16LE'), 'UTF-16');
    $t->same('UTF-16LE', $plan['patternEncoding']);
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('autoload:%', 'UTF-16LE'), 'UTF-16LE', 'REGEXP'));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('autoload:*', 'UTF-16LE'), 'UTF-16LE', 'GLOB', $patternBytes('!', 'UTF-16LE')));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects multi character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('cache:!!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', $patternBytes('!!', 'UTF-16LE')));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects invalid pattern encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('autoload:%', 'UTF-16LE'), 'UTF-32'));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects malformed utf16 pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 pattern like glob affinity current source nextOneOneFour rejects malformed utf16 escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $patternBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $patternBytes('cache:!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', "\x00\xd8", 'UTF-16LE'));
};

return $tests;
