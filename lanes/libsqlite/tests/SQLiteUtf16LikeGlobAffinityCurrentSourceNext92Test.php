<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan;

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

$currentRows = [
    $textRow(1, 'autoload:yes', 'UTF-8'),
    $textRow(2, 'autoload:no', 'UTF-16LE'),
    $textRow(3, 'serialized:plugin_alpha', 'UTF-16BE'),
    $textRow(4, 'cache:%literal', 'UTF-16LE'),
    $textRow(5, 'emoji:😀:enabled', 'UTF-16BE'),
    ['option_id' => 6, 'option_value' => 42],
    ['option_id' => 7, 'option_value' => 4.5],
    ['option_id' => 8, 'option_value' => true],
    ['option_id' => 9, 'option_value' => null],
    $textRow(10, 'legacy:delete', 'UTF-8'),
    ['option_id' => 12, 'option_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$nextRows = [
    $textRow(1, 'autoload:yes', 'UTF-16LE'),
    $textRow(2, 'autoload:no', 'UTF-16LE'),
    $textRow(3, 'serialized:plugin_alpha_v2', 'UTF-16BE'),
    $textRow(4, 'cache:%literal', 'UTF-16BE'),
    $textRow(5, 'emoji:😀:enabled', 'UTF-16BE'),
    ['option_id' => 6, 'option_value' => '42'],
    ['option_id' => 7, 'option_value' => 4.5],
    ['option_id' => 8, 'option_value' => false],
    ['option_id' => 9, 'option_value' => null],
    $textRow(11, 'autoload:fresh', 'UTF-16BE'),
    ['option_id' => 13, 'option_value_bytes' => "\xd8\x00", 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern = 'autoload:%',
    string $operator = 'LIKE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.wp_options',
    string $nextSource = 'main.wp_options',
): array => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
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
    'records operator' => ['autoload:%', 'LIKE', null, false, 'operator', 'LIKE'],
    'records pattern' => ['autoload:%', 'LIKE', null, false, 'pattern', 'autoload:%'],
    'records case flag' => ['autoload:%', 'LIKE', null, false, 'caseSensitiveLike', false],
    'records source current' => ['autoload:%', 'LIKE', null, false, 'currentSource', 'main.wp_options'],
    'records source next' => ['autoload:%', 'LIKE', null, false, 'nextSource', 'main.wp_options'],
    'autoload current rowids' => ['autoload:%', 'LIKE', null, false, 'currentRowids', [1, 2]],
    'autoload next rowids' => ['autoload:%', 'LIKE', null, false, 'nextRowids', [1, 2, 11]],
    'autoload retained rowids' => ['autoload:%', 'LIKE', null, false, 'retainedRowids', [1, 2]],
    'autoload entered rowids' => ['autoload:%', 'LIKE', null, false, 'enteredRowids', [11]],
    'autoload exited rowids empty' => ['autoload:%', 'LIKE', null, false, 'exitedRowids', []],
    'autoload changed encoding' => ['autoload:%', 'LIKE', null, false, 'changedEncodingRowids', [1]],
    'autoload changed bytes' => ['autoload:%', 'LIKE', null, false, 'changedBytesRowids', [1]],
    'autoload changed storage empty' => ['autoload:%', 'LIKE', null, false, 'changedStorageRowids', []],
    'autoload current malformed rowids' => ['autoload:%', 'LIKE', null, false, 'currentMalformedRowids', [12]],
    'autoload next malformed rowids' => ['autoload:%', 'LIKE', null, false, 'nextMalformedRowids', [13]],
    'autoload invalidates' => ['autoload:%', 'LIKE', null, false, 'cursorInvalidated', true],
    'autoload not reusable' => ['autoload:%', 'LIKE', null, false, 'cursorReusable', false],
    'autoload reason encoding' => ['autoload:%', 'LIKE', null, false, 'invalidationReasons.0', 'text-encoding'],
    'autoload reason bytes' => ['autoload:%', 'LIKE', null, false, 'invalidationReasons.1', 'value-bytes'],
    'autoload reason rowset' => ['autoload:%', 'LIKE', null, false, 'invalidationReasons.2', 'matched-rowset'],
    'autoload reason malformed' => ['autoload:%', 'LIKE', null, false, 'invalidationReasons.3', 'malformed-text'],
    'autoload first current text' => ['autoload:%', 'LIKE', null, false, 'currentMatchedRows.0.textValue', 'autoload:yes'],
    'autoload first current encoding' => ['autoload:%', 'LIKE', null, false, 'currentMatchedRows.0.textEncoding', 'UTF-8'],
    'autoload next first encoding' => ['autoload:%', 'LIKE', null, false, 'nextMatchedRows.0.textEncoding', 'UTF-16LE'],
    'autoload next fresh encoding' => ['autoload:%', 'LIKE', null, false, 'nextMatchedRows.2.textEncoding', 'UTF-16BE'],
    'autoload current storage text' => ['autoload:%', 'LIKE', null, false, 'currentMatchedRows.0.storage', 'text'],
    'autoload next bytes changed to utf16le' => ['autoload:%', 'LIKE', null, false, 'nextMatchedRows.0.bytesHex', '6100750074006f006c006f00610064003a00790065007300'],
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
    'glob autoload current' => ['autoload:*', 'GLOB', null, false, 'currentRowids', [1, 2]],
    'glob autoload next' => ['autoload:*', 'GLOB', null, false, 'nextRowids', [1, 2, 11]],
    'glob serialized next' => ['serialized:plugin_*', 'GLOB', null, false, 'nextRowids', [3]],
    'glob emoji current' => ['emoji:😀:*', 'GLOB', null, false, 'currentRowids', [5]],
    'source change reason first' => ['autoload:%', 'LIKE', null, false, 'invalidationReasons.0', 'source-name', 'main.wp_options', 'temp.wp_options'],
    'source change records next source' => ['autoload:%', 'LIKE', null, false, 'nextSource', 'temp.wp_options', 'main.wp_options', 'temp.wp_options'],
    'stable emoji reusable false from malformed only' => ['emoji:😀:%', 'LIKE', null, false, 'cursorReusable', false],
    'dependency utf16 decode' => ['autoload:%', 'LIKE', null, false, 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency affinity' => ['autoload:%', 'LIKE', null, false, 'dependencies.1', 'sqlite-like-glob-affinity'],
    'dependency marker' => ['autoload:%', 'LIKE', null, false, 'dependencies.2', 'sqlite-current-source-nextnineTwo'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 like glob affinity current source nextNineTwo ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[6] ?? 'main.wp_options';
        $nextSource = $case[7] ?? 'main.wp_options';
        $value = $plan($pattern, $operator, $escape, $caseSensitiveLike, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 like glob affinity current source nextNineTwo rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($currentRows, $nextRows, 'x', 'REGEXP'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($currentRows, $nextRows, 'x*', 'GLOB', '!'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects missing option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan([['option_value' => 'autoload:yes']], $nextRows, 'autoload:%'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects missing value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1]], $nextRows, 'autoload:%'));
};

$tests['utf16 like glob affinity current source nextNineTwo rejects nonscalar value'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1, 'option_value' => ['autoload']]], $nextRows, 'autoload:%'));
};

return $tests;
