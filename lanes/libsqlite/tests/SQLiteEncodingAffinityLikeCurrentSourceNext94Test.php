<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'blog_public', 'option_value' => 1],
    ['option_id' => 3, 'option_name' => 'retry_count', 'option_value' => 10],
    ['option_id' => 4, 'option_name' => 'float_threshold', 'option_value' => 10.5],
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'option_value' => 'plugin_Alpha'],
    ['option_id' => 6, 'option_name' => 'plugin_percent', 'option_value' => 'plugin_100%_enabled'],
    ['option_id' => 7, 'option_name' => 'plugin_wild', 'option_value' => 'plugin_100x_enabled'],
    ['option_id' => 8, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 9, 'option_name' => 'unicode_name', 'option_value' => 'plugin_éclair'],
    ['option_id' => 10, 'option_name' => 'emoji_name', 'option_value' => 'plugin_😀_cache'],
    ['option_id' => 11, 'option_name' => 'null_value', 'option_value' => null],
    ['option_id' => 12, 'option_name' => 'theme_alpha', 'option_value' => 'theme_alpha'],
    ['option_id' => 13, 'option_name' => 'plugin_removed', 'option_value' => 'plugin_removed'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'blog_public', 'option_value' => '1'],
    ['option_id' => 3, 'option_name' => 'retry_count', 'option_value' => 10],
    ['option_id' => 4, 'option_name' => 'float_threshold', 'option_value' => '10.5'],
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'option_value' => 'plugin_alpha'],
    ['option_id' => 6, 'option_name' => 'plugin_percent', 'option_value' => 'plugin_100%_enabled'],
    ['option_id' => 7, 'option_name' => 'plugin_wild', 'option_value' => 'plugin_100x_enabled'],
    ['option_id' => 8, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
    ['option_id' => 9, 'option_name' => 'unicode_name', 'option_value' => 'plugin_éclair'],
    ['option_id' => 10, 'option_name' => 'emoji_name', 'option_value' => 'plugin_😀_cache_v2'],
    ['option_id' => 11, 'option_name' => 'null_value', 'option_value' => null],
    ['option_id' => 12, 'option_name' => 'theme_alpha', 'option_value' => 'theme_alpha'],
    ['option_id' => 14, 'option_name' => 'plugin_new', 'option_value' => 'plugin_new'],
    ['option_id' => 15, 'option_name' => 'numeric_new', 'option_value' => true],
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    int|string $currentEncoding = 'UTF-16LE',
    int|string $nextEncoding = 'UTF-16BE',
    string $currentSource = 'main.wp_options',
    string $nextSource = 'main.wp_options',
    int $currentSchemaCookie = 41,
    int $nextSchemaCookie = 42,
): array => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows,
    $nextRows,
    'option_value',
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitiveLike,
    $currentEncoding,
    $nextEncoding,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
);

$valueAt = static function (array $plan, string $path): mixed {
    $value = $plan;
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records operator' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'operator', 'LIKE'],
    'records pattern' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'pattern', 'plugin%'],
    'records column' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'column', 'option_value'],
    'records collation' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'collation', 'NOCASE'],
    'records case flag' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'caseSensitiveLike', false],
    'range lower is folded plugin' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'range.lowerInclusive', 'plugin'],
    'range upper is plugio' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'range.upperBound', 'plugio'],
    'current source retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSource', 'main.wp_options'],
    'next source retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSource', 'main.wp_options'],
    'current schema cookie retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSchemaCookie', 41],
    'next schema cookie retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSchemaCookie', 42],
    'current scan encoding retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentEncoding', 'UTF-16LE'],
    'next scan encoding retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextEncoding', 'UTF-16BE'],
    'current rowset excludes blob and null' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRowids', [6, 7, 5, 13, 9, 10]],
    'next rowset includes new plugin rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRowids', [6, 7, 5, 14, 9, 10]],
    'retained rowset' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [6, 7, 5, 9, 10]],
    'exited rowset' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', [13]],
    'entered rowset' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'enteredRowids', [14]],
    'changed text rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedTextRowids', [5, 10]],
    'changed storage rowids empty for plugin text rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedStorageRowids', []],
    'changed encoding rowids include retained plugin rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedEncodingRowids', [6, 7, 5, 9, 10]],
    'changed bytes rowids include text and encoding changes' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedBytesRowids', [6, 7, 5, 9, 10]],
    'current text map preserves uppercase source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentTexts.5', 'plugin_Alpha'],
    'next text map preserves lower source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextTexts.5', 'plugin_alpha'],
    'current storage map is text' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentStorage.6', 'text'],
    'next storage map is text' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextStorage.6', 'text'],
    'current bytes are utf16le' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.6', '70006c007500670069006e005f0031003000300025005f0065006e00610062006c0065006400'],
    'next bytes are utf16be' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextBytesHex.6', '0070006c007500670069006e005f0031003000300025005f0065006e00610062006c00650064'],
    'cursor invalidated' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'cursorInvalidated', true],
    'cursor not reusable' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'cursorReusable', false],
    'reason schema cookie' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'schema-cookie'],
    'reason scan encoding' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.1', 'scan-encoding'],
    'reason text affinity' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.2', 'text-affinity'],
    'reason text encoding' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.3', 'text-encoding'],
    'reason encoded bytes' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.4', 'encoded-bytes'],
    'reason matched rowset' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.5', 'matched-rowset'],
    'escaped like records escape' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'escape', '!'],
    'escaped like current literal percent row' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'currentRowids', [6]],
    'escaped like next literal percent row' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'nextRowids', [6]],
    'escaped like retains literal percent row' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'retainedRowids', [6]],
    'escaped like no rowset delta' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'enteredRowids', []],
    'glob records operator' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'operator', 'GLOB'],
    'glob range lower' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'range.lowerInclusive', 'plugin_'],
    'glob range upper' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'range.upperBound', 'plugin`'],
    'glob current rowset is case sensitive' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'currentRowids', [6, 7, 5, 13, 9, 10]],
    'glob next rowset is case sensitive' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'nextRowids', [6, 7, 5, 14, 9, 10]],
    'glob entered lower alpha after current uppercase miss' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'enteredRowids', [14]],
    'glob exited removed row' => ['plugin_*', 'GLOB', 'BINARY', null, true, 'exitedRowids', [13]],
    'unicode glob latin rowset current' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, true, 'currentRowids', [9]],
    'unicode glob latin rowset next' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, true, 'nextRowids', [9]],
    'emoji like current row' => ['plugin_😀%', 'LIKE', 'BINARY', null, true, 'currentRowids', [10]],
    'emoji like next row remains after suffix change' => ['plugin_😀%', 'LIKE', 'BINARY', null, true, 'nextRowids', [10]],
    'emoji like changed text' => ['plugin_😀%', 'LIKE', 'BINARY', null, true, 'changedTextRowids', [10]],
    'numeric like current rowset' => ['1%', 'LIKE', 'BINARY', null, true, 'currentRowids', [2, 3, 4]],
    'numeric like next rowset includes bool new' => ['1%', 'LIKE', 'BINARY', null, true, 'nextRowids', [2, 15, 3, 4]],
    'numeric like storage class changes' => ['1%', 'LIKE', 'BINARY', null, true, 'changedStorageRowids', [2, 4]],
    'numeric like text values unchanged after affinity' => ['1%', 'LIKE', 'BINARY', null, true, 'changedTextRowids', []],
    'numeric like current storage integer' => ['1%', 'LIKE', 'BINARY', null, true, 'currentStorage.2', 'integer'],
    'numeric like next storage text' => ['1%', 'LIKE', 'BINARY', null, true, 'nextStorage.2', 'text'],
    'stable theme is reusable' => ['theme%', 'LIKE', 'NOCASE', null, false, 'cursorReusable', true, 'UTF-16LE', 'UTF-16LE', 'main.wp_options', 'main.wp_options', 41, 41],
    'stable theme no invalidation' => ['theme%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons', [], 'UTF-16LE', 'UTF-16LE', 'main.wp_options', 'main.wp_options', 41, 41],
    'source switch reason first' => ['theme%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'source-name', 'UTF-16LE', 'UTF-16LE', 'main.wp_options', 'temp.wp_options', 41, 41],
    'dependencies include text affinity' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'dependencies.0', 'sqlite-text-affinity'],
    'dependencies include slice marker' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'dependencies.1', 'sqlite-like-glob-current-source-next94'],
];

foreach ($cases as $name => $case) {
    $tests['encoding affinity like current source next94 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$pattern, $operator, $collation, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentEncoding = $case[7] ?? 'UTF-16LE';
        $nextEncoding = $case[8] ?? 'UTF-16BE';
        $currentSource = $case[9] ?? 'main.wp_options';
        $nextSource = $case[10] ?? 'main.wp_options';
        $currentSchemaCookie = $case[11] ?? 41;
        $nextSchemaCookie = $case[12] ?? 42;
        $t->same($expected, $valueAt($plan($pattern, $operator, $collation, $escape, $caseSensitiveLike, $currentEncoding, $nextEncoding, $currentSource, $nextSource, $currentSchemaCookie, $nextSchemaCookie), $path));
    };
}

$tests['encoding affinity like current source next94 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan($currentRows, $nextRows, 'option_value', 'plugin%', 'REGEXP'));
};

$tests['encoding affinity like current source next94 rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan($currentRows, $nextRows, 'option_value', 'plugin_*', 'GLOB', 'BINARY', '\\'));
};

$tests['encoding affinity like current source next94 rejects missing column'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1, 'option_name' => 'siteurl']], $nextRows, 'option_value', 's%'));
};

$tests['encoding affinity like current source next94 rejects malformed utf8 text before encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $current = [['option_id' => 1, 'option_name' => 'broken', 'option_value' => "plugin_\xc3"]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan($current, $nextRows, 'option_value', 'plugin%'));
};

$tests['encoding affinity like current source next94 rejects unsupported encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan($currentRows, $nextRows, 'option_value', 'plugin%', 'LIKE', 'NOCASE', null, false, 4));
};

return $tests;
