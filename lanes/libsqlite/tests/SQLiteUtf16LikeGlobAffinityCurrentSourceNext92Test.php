<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan;

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

$currentRows = [
    $textRow(1, 'load_policy:yes', 'UTF-8'),
    $textRow(2, 'load_policy:no', 'UTF-16LE'),
    $textRow(3, 'serialized:plugin_alpha', 'UTF-16BE'),
    $textRow(4, 'cache:%literal', 'UTF-16LE'),
    $textRow(5, 'emoji:😀:enabled', 'UTF-16BE'),
    ['setting_id' => 6, 'key_value' => 42],
    ['setting_id' => 7, 'key_value' => 4.5],
    ['setting_id' => 8, 'key_value' => true],
    ['setting_id' => 9, 'key_value' => null],
    $textRow(10, 'legacy:delete', 'UTF-8'),
    ['setting_id' => 12, 'key_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$nextRows = [
    $textRow(1, 'load_policy:yes', 'UTF-16LE'),
    $textRow(2, 'load_policy:no', 'UTF-16LE'),
    $textRow(3, 'serialized:plugin_alpha_v2', 'UTF-16BE'),
    $textRow(4, 'cache:%literal', 'UTF-16BE'),
    $textRow(5, 'emoji:😀:enabled', 'UTF-16BE'),
    ['setting_id' => 6, 'key_value' => '42'],
    ['setting_id' => 7, 'key_value' => 4.5],
    ['setting_id' => 8, 'key_value' => false],
    ['setting_id' => 9, 'key_value' => null],
    $textRow(11, 'load_policy:fresh', 'UTF-16BE'),
    ['setting_id' => 13, 'key_value_bytes' => "\xd8\x00", 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern = 'load_policy:%',
    string $operator = 'LIKE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
): array => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    $pattern,
    $operator,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'records operator' => ['load_policy:%', 'LIKE', null, false, 'operator', 'LIKE'],
    'records pattern' => ['load_policy:%', 'LIKE', null, false, 'pattern', 'load_policy:%'],
    'records case flag' => ['load_policy:%', 'LIKE', null, false, 'caseSensitiveLike', false],
    'records source current' => ['load_policy:%', 'LIKE', null, false, 'currentSource', 'main.app_settings'],
    'records source next' => ['load_policy:%', 'LIKE', null, false, 'nextSource', 'main.app_settings'],
    'load_policy current rowids' => ['load_policy:%', 'LIKE', null, false, 'currentRowids', [1, 2]],
    'load_policy next rowids' => ['load_policy:%', 'LIKE', null, false, 'nextRowids', [1, 2, 11]],
    'load_policy retained rowids' => ['load_policy:%', 'LIKE', null, false, 'retainedRowids', [1, 2]],
    'load_policy entered rowids' => ['load_policy:%', 'LIKE', null, false, 'enteredRowids', [11]],
    'load_policy exited rowids empty' => ['load_policy:%', 'LIKE', null, false, 'exitedRowids', []],
    'load_policy changed encoding' => ['load_policy:%', 'LIKE', null, false, 'changedEncodingRowids', [1]],
    'load_policy changed bytes' => ['load_policy:%', 'LIKE', null, false, 'changedBytesRowids', [1]],
    'load_policy changed storage empty' => ['load_policy:%', 'LIKE', null, false, 'changedStorageRowids', []],
    'load_policy current malformed rowids' => ['load_policy:%', 'LIKE', null, false, 'currentMalformedRowids', [12]],
    'load_policy next malformed rowids' => ['load_policy:%', 'LIKE', null, false, 'nextMalformedRowids', [13]],
    'load_policy invalidates' => ['load_policy:%', 'LIKE', null, false, 'cursorInvalidated', true],
    'load_policy not reusable' => ['load_policy:%', 'LIKE', null, false, 'cursorReusable', false],
    'load_policy reason encoding' => ['load_policy:%', 'LIKE', null, false, 'invalidationReasons.0', 'text-encoding'],
    'load_policy reason bytes' => ['load_policy:%', 'LIKE', null, false, 'invalidationReasons.1', 'value-bytes'],
    'load_policy reason rowset' => ['load_policy:%', 'LIKE', null, false, 'invalidationReasons.2', 'matched-rowset'],
    'load_policy reason malformed' => ['load_policy:%', 'LIKE', null, false, 'invalidationReasons.3', 'malformed-text'],
    'load_policy first current text' => ['load_policy:%', 'LIKE', null, false, 'currentMatchedRows.0.textValue', 'load_policy:yes'],
    'load_policy first current encoding' => ['load_policy:%', 'LIKE', null, false, 'currentMatchedRows.0.textEncoding', 'UTF-8'],
    'load_policy next first encoding' => ['load_policy:%', 'LIKE', null, false, 'nextMatchedRows.0.textEncoding', 'UTF-16LE'],
    'load_policy next fresh encoding' => ['load_policy:%', 'LIKE', null, false, 'nextMatchedRows.2.textEncoding', 'UTF-16BE'],
    'load_policy current storage text' => ['load_policy:%', 'LIKE', null, false, 'currentMatchedRows.0.storage', 'text'],
    'load_policy next bytes changed to utf16le' => ['load_policy:%', 'LIKE', null, false, 'nextMatchedRows.0.bytesHex', '6c006f00610064005f0070006f006c006900630079003a00790065007300'],
    'escaped literal percent row current' => ['cache:!%%', 'LIKE', '!', false, 'currentRowids', [4]],
    'escaped literal percent row next' => ['cache:!%%', 'LIKE', '!', false, 'nextRowids', [4]],
    'escaped literal percent escape stored' => ['cache:!%%', 'LIKE', '!', false, 'escape', '!'],
    'escaped literal percent changed encoding' => ['cache:!%%', 'LIKE', '!', false, 'changedEncodingRowids', [4]],
    'escaped literal percent changed bytes' => ['cache:!%%', 'LIKE', '!', false, 'changedBytesRowids', [4]],
    'serialized current row' => ['serialized:plugin_%', 'LIKE', null, false, 'currentRowids', [3]],
    'serialized next row' => ['serialized:plugin_%', 'LIKE', null, false, 'nextRowids', [3]],
    'serialized changed bytes' => ['serialized:plugin_%', 'LIKE', null, false, 'changedBytesRowids', [3]],
    'emoji current row' => ['emoji:😀:%', 'LIKE', null, false, 'currentRowids', [5]],
    'emoji next row' => ['emoji:😀:%', 'LIKE', null, false, 'nextRowids', [5]],
    'emoji current text value' => ['emoji:😀:%', 'LIKE', null, false, 'currentMatchedRows.0.textValue', 'emoji:😀:enabled'],
    'numeric int like current' => ['4%', 'LIKE', null, false, 'currentRowids', [6, 7]],
    'numeric int like next' => ['4%', 'LIKE', null, false, 'nextRowids', [6, 7]],
    'numeric changed storage' => ['4%', 'LIKE', null, false, 'changedStorageRowids', [6]],
    'numeric changed bytes tracks scalar textification' => ['4%', 'LIKE', null, false, 'changedBytesRowids', [6]],
    'bool current one' => ['1', 'LIKE', null, false, 'currentRowids', [8]],
    'bool next one empty' => ['1', 'LIKE', null, false, 'nextRowids', []],
    'null matches empty current' => ['', 'LIKE', null, false, 'currentRowids', [9]],
    'null matches empty next' => ['', 'LIKE', null, false, 'nextRowids', [9]],
    'glob load_policy current' => ['load_policy:*', 'GLOB', null, false, 'currentRowids', [1, 2]],
    'glob load_policy next' => ['load_policy:*', 'GLOB', null, false, 'nextRowids', [1, 2, 11]],
    'glob serialized next' => ['serialized:plugin_*', 'GLOB', null, false, 'nextRowids', [3]],
    'glob emoji current' => ['emoji:😀:*', 'GLOB', null, false, 'currentRowids', [5]],
    'source change reason first' => ['load_policy:%', 'LIKE', null, false, 'invalidationReasons.0', 'source-name', 'main.app_settings', 'temp.app_settings'],
    'source change records next source' => ['load_policy:%', 'LIKE', null, false, 'nextSource', 'temp.app_settings', 'main.app_settings', 'temp.app_settings'],
    'stable emoji reusable false from malformed only' => ['emoji:😀:%', 'LIKE', null, false, 'cursorReusable', false],
    'dependency utf16 decode' => ['load_policy:%', 'LIKE', null, false, 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency affinity' => ['load_policy:%', 'LIKE', null, false, 'dependencies.1', 'sqlite-like-glob-affinity'],
    'dependency marker' => ['load_policy:%', 'LIKE', null, false, 'dependencies.2', 'sqlite-current-source-nextnineTwo'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 like glob affinity current source nextNineTwo ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[6] ?? 'main.app_settings';
        $nextSource = $case[7] ?? 'main.app_settings';
        $value = $plan($pattern, $operator, $escape, $caseSensitiveLike, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 like glob affinity current source nextNineTwo rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'x', 'REGEXP'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'x*', 'GLOB', '!'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects missing setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'load_policy:yes']], $nextRows, 'load_policy:%'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects missing value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], $nextRows, 'load_policy:%'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects nonscalar value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1, 'key_value' => ['load_policy']]], $nextRows, 'load_policy:%'));
};

return $tests;
