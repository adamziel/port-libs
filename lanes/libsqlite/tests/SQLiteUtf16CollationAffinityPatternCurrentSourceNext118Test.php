<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan;

$tests = [];

$textRow = static function (int $id, string $value, string $encoding = 'UTF-8', mixed $raw = null): array {
    return [
        'setting_id' => $id,
        'key_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'key_value' => $raw,
    ];
};
$scalarRow = static fn (int $id, mixed $value): array => ['setting_id' => $id, 'key_value' => $value];
$bytes = static fn (string $value, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding);

$currentRows = [
    $textRow(1, 'loadflag:yes', 'UTF-8'),
    $textRow(2, 'Loadflag:No', 'UTF-16LE'),
    $textRow(3, 'loadflag:trail ', 'UTF-16LE'),
    $textRow(4, 'cache:%literal', 'UTF-16BE'),
    $textRow(5, 'plugin_α:enabled', 'UTF-16LE'),
    $textRow(6, 'plugin_β:disabled', 'UTF-16BE'),
    $scalarRow(7, 10),
    $scalarRow(8, 10.5),
    $scalarRow(9, true),
    $scalarRow(10, null),
    ['setting_id' => 12, 'key_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$nextRows = [
    $textRow(1, 'loadflag:yes', 'UTF-16LE'),
    $textRow(2, 'Loadflag:No', 'UTF-16BE'),
    $textRow(3, 'loadflag:trail', 'UTF-16LE'),
    $textRow(4, 'cache:%literal', 'UTF-16LE'),
    $textRow(5, 'plugin_α:enabled', 'UTF-16BE'),
    $textRow(6, 'plugin_γ:enabled', 'UTF-16BE'),
    $scalarRow(7, '10'),
    $scalarRow(8, 10.5),
    $scalarRow(9, false),
    $scalarRow(10, null),
    $textRow(13, 'loadflag:fresh', 'UTF-16BE'),
    ['setting_id' => 14, 'key_value_bytes' => "\xd8\x00", 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern,
    string $patternEncoding = 'UTF-16LE',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    ?string $escapeEncoding = null,
    bool $caseSensitiveLike = false,
    string $currentRangeEncoding = 'UTF-16LE',
    string $nextRangeEncoding = 'UTF-16BE',
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
): array => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    $bytes($pattern, $patternEncoding),
    $patternEncoding,
    $operator,
    $collation,
    $escape === null ? null : $bytes($escape, $escapeEncoding ?? $patternEncoding),
    $escapeEncoding,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentRangeEncoding,
    $nextRangeEncoding,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records decoded pattern' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'decodedPattern', 'loadflag:%'],
    'records pattern bytes' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'patternBytesHex', '6c006f006100640066006c00610067003a002500'],
    'records collation' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'collation', 'NOCASE'],
    'records operator' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'operator', 'LIKE'],
    'records current range encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'currentRangeEncoding', 'UTF-16LE'],
    'records next range encoding' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextRangeEncoding', 'UTF-16BE'],
    'nocase like index usable' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'rangePlan.indexUsable', true],
    'nocase like lower folds ascii prefix' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'rangePlan.range.lowerInclusive', 'loadflag:'],
    'nocase like upper bound folds ascii prefix' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'rangePlan.range.upperBound', 'loadflag;'],
    'nocase current rowids include mixed case' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'currentRowids', [1, 2, 3]],
    'nocase next rowids include fresh' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextRowids', [1, 2, 3, 13]],
    'nocase retained rowids' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'retainedRowids', [1, 2, 3]],
    'nocase entered rowids' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'enteredRowids', [13]],
    'nocase changed encoding rowids' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'changedEncodingRowids', [1, 2]],
    'nocase changed bytes rowids' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'changedBytesRowids', [1, 2, 3]],
    'nocase malformed current rowids' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'currentMalformedRowids', [12]],
    'nocase malformed next rowids' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextMalformedRowids', [14]],
    'nocase current range lower bytes utf16le' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'currentRangeBytesHex.lowerInclusive', '6c006f006100640066006c00610067003a00'],
    'nocase next range lower bytes utf16be' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextRangeBytesHex.lowerInclusive', '006c006f006100640066006c00610067003a'],
    'nocase next range upper bytes utf16be' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextRangeBytesHex.upperBound', '006c006f006100640066006c00610067003b'],
    'range encoding invalidates' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'invalidationReasons.4', 'range-encoding'],
    'range bytes invalidates' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'invalidationReasons.5', 'range-bytes'],
    'binary default like rejected for nocase mode' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'BINARY', null, null, false, 'rangePlan.rejectedReason', 'default_like_requires_nocase_index'],
    'binary default like has no range bytes' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'BINARY', null, null, false, 'currentRangeBytesHex.lowerInclusive', null],
    'binary case sensitive like index usable' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'BINARY', null, null, true, 'rangePlan.indexUsable', true],
    'binary case sensitive excludes mixed case' => ['LOADFLAG:%', 'UTF-16LE', 'LIKE', 'BINARY', null, null, true, 'currentRowids', []],
    'rtrim default like rejected' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'RTRIM', null, null, false, 'rangePlan.rejectedReason', 'default_like_requires_nocase_index'],
    'unicode nocase prefix rejected for range' => ['plugin!_α:%', 'UTF-16LE', 'LIKE', 'NOCASE', '!', 'UTF-16LE', false, 'rangePlan.rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'unicode nocase still matches current alpha' => ['plugin!_α:%', 'UTF-16LE', 'LIKE', 'NOCASE', '!', 'UTF-16LE', false, 'currentRowids', [5]],
    'unicode nocase still matches next alpha' => ['plugin!_α:%', 'UTF-16LE', 'LIKE', 'NOCASE', '!', 'UTF-16LE', false, 'nextRowids', [5]],
    'literal percent escape decoded' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'NOCASE', '!', 'UTF-16BE', false, 'decodedEscape', '!'],
    'literal percent escape bytes' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'NOCASE', '!', 'UTF-16BE', false, 'escapeBytesHex', '0021'],
    'literal percent row current' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'NOCASE', '!', 'UTF-16BE', false, 'currentRowids', [4]],
    'literal percent row next' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'NOCASE', '!', 'UTF-16BE', false, 'nextRowids', [4]],
    'literal percent prefix contains percent' => ['cache:!%%', 'UTF-16BE', 'LIKE', 'NOCASE', '!', 'UTF-16BE', false, 'rangePlan.prefix', 'cache:%'],
    'glob records operator' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', 'BINARY', null, null, false, 'rangePlan.operator', 'GLOB'],
    'glob binary class current' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', 'BINARY', null, null, false, 'currentRowids', [5, 6]],
    'glob binary class next' => ['plugin_[αβ]:*', 'UTF-16LE', 'GLOB', 'BINARY', null, null, false, 'nextRowids', [5]],
    'glob binary prefix range usable' => ['plugin_*', 'UTF-16LE', 'GLOB', 'BINARY', null, null, false, 'rangePlan.indexUsable', true],
    'glob binary range lower' => ['plugin_*', 'UTF-16LE', 'GLOB', 'BINARY', null, null, false, 'rangePlan.range.lowerInclusive', 'plugin_'],
    'glob binary range upper' => ['plugin_*', 'UTF-16LE', 'GLOB', 'BINARY', null, null, false, 'rangePlan.range.upperBound', 'plugin`'],
    'glob nocase rejected for index but residual matches' => ['plugin_*', 'UTF-16LE', 'GLOB', 'NOCASE', null, null, false, 'rangePlan.rejectedReason', 'glob_requires_binary_index'],
    'glob nocase rowids still case sensitive residual' => ['plugin_*', 'UTF-16LE', 'GLOB', 'NOCASE', null, null, false, 'currentRowids', [5, 6]],
    'numeric affinity current rowids' => ['10%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'currentRowids', [7, 8]],
    'numeric affinity next rowids' => ['10%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextRowids', [7, 8]],
    'numeric storage changed' => ['10', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'changedStorageRowids', [7]],
    'bool true current rowid' => ['1', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'currentRowids', [9]],
    'bool false exits next' => ['1', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'nextRowids', []],
    'source switch reason first' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'invalidationReasons.0', 'source-name', 'main.app_settings', 'temp.app_settings'],
    'dependency pattern decode' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency collation nextOneOneEight' => ['loadflag:%', 'UTF-16LE', 'LIKE', 'NOCASE', null, null, false, 'dependencies.2', 'sqlite-collation-range-current-source-nextoneOneEight'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 collation affinity pattern current source nextOneOneEight ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$pattern, $patternEncoding, $operator, $collation, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[9] ?? 'main.app_settings';
        $nextSource = $case[10] ?? 'main.app_settings';
        $t->same($expected, $valueAt($plan($pattern, $patternEncoding, $operator, $collation, $escape, $escapeEncoding, $caseSensitiveLike, 'UTF-16LE', 'UTF-16BE', $currentSource, $nextSource), $path));
    };
}

$stableRows = [
    $textRow(1, 'loadflag:yes', 'UTF-16LE'),
    $textRow(2, 'theme:yes', 'UTF-16LE'),
];

$tests['utf16 collation affinity pattern current source nextOneOneEight stable reusable with same range encoding'] = static function (TestRunner $t) use ($bytes, $stableRows): void {
    $plan = SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan(
        $stableRows,
        $stableRows,
        $bytes('loadflag:%', 'UTF-16LE'),
        'UTF-16LE',
        'LIKE',
        'NOCASE',
        null,
        null,
        false,
        'stable',
        'stable',
        'UTF-16LE',
        'UTF-16LE',
    );
    $t->same(true, $plan['cursorReusable']);
    $t->same([], $plan['invalidationReasons']);
};

$tests['utf16 collation affinity pattern current source nextOneOneEight rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $bytes('loadflag:%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'UNICODE'));
};

$tests['utf16 collation affinity pattern current source nextOneOneEight rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $bytes('loadflag:%', 'UTF-16LE'), 'UTF-16LE', 'REGEXP'));
};

$tests['utf16 collation affinity pattern current source nextOneOneEight rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $bytes('plugin:*', 'UTF-16LE'), 'UTF-16LE', 'GLOB', 'BINARY', $bytes('!', 'UTF-16LE')));
};

$tests['utf16 collation affinity pattern current source nextOneOneEight rejects malformed pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 collation affinity pattern current source nextOneOneEight rejects malformed escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, $bytes('cache:!%%', 'UTF-16LE'), 'UTF-16LE', 'LIKE', 'NOCASE', "\x00\xd8", 'UTF-16LE'));
};

return $tests;
