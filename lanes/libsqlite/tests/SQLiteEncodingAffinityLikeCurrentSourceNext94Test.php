<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test'],
    ['setting_id' => 2, 'key_name' => 'public_flag', 'key_value' => 1],
    ['setting_id' => 3, 'key_name' => 'retry_count', 'key_value' => 10],
    ['setting_id' => 4, 'key_name' => 'float_threshold', 'key_value' => 10.5],
    ['setting_id' => 5, 'key_name' => 'module_alpha', 'key_value' => 'module_Alpha'],
    ['setting_id' => 6, 'key_name' => 'module_percent', 'key_value' => 'module_100%_enabled'],
    ['setting_id' => 7, 'key_name' => 'module_wild', 'key_value' => 'module_100x_enabled'],
    ['setting_id' => 8, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module_blob')],
    ['setting_id' => 9, 'key_name' => 'unicode_name', 'key_value' => 'module_éclair'],
    ['setting_id' => 10, 'key_name' => 'emoji_name', 'key_value' => 'module_😀_cache'],
    ['setting_id' => 11, 'key_name' => 'null_value', 'key_value' => null],
    ['setting_id' => 12, 'key_name' => 'service_alpha', 'key_value' => 'service_alpha'],
    ['setting_id' => 13, 'key_name' => 'module_removed', 'key_value' => 'module_removed'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test'],
    ['setting_id' => 2, 'key_name' => 'public_flag', 'key_value' => '1'],
    ['setting_id' => 3, 'key_name' => 'retry_count', 'key_value' => 10],
    ['setting_id' => 4, 'key_name' => 'float_threshold', 'key_value' => '10.5'],
    ['setting_id' => 5, 'key_name' => 'module_alpha', 'key_value' => 'module_alpha'],
    ['setting_id' => 6, 'key_name' => 'module_percent', 'key_value' => 'module_100%_enabled'],
    ['setting_id' => 7, 'key_name' => 'module_wild', 'key_value' => 'module_100x_enabled'],
    ['setting_id' => 8, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module_blob')],
    ['setting_id' => 9, 'key_name' => 'unicode_name', 'key_value' => 'module_éclair'],
    ['setting_id' => 10, 'key_name' => 'emoji_name', 'key_value' => 'module_😀_cache_v2'],
    ['setting_id' => 11, 'key_name' => 'null_value', 'key_value' => null],
    ['setting_id' => 12, 'key_name' => 'service_alpha', 'key_value' => 'service_alpha'],
    ['setting_id' => 14, 'key_name' => 'module_new', 'key_value' => 'module_new'],
    ['setting_id' => 15, 'key_name' => 'numeric_new', 'key_value' => true],
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    int|string $currentEncoding = 'UTF-16LE',
    int|string $nextEncoding = 'UTF-16BE',
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
    int $currentSchemaCookie = 41,
    int $nextSchemaCookie = 42,
): array => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'key_value',
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
    'records operator' => ['module%', 'LIKE', 'NOCASE', null, false, 'operator', 'LIKE'],
    'records pattern' => ['module%', 'LIKE', 'NOCASE', null, false, 'pattern', 'module%'],
    'records column' => ['module%', 'LIKE', 'NOCASE', null, false, 'column', 'key_value'],
    'records collation' => ['module%', 'LIKE', 'NOCASE', null, false, 'collation', 'NOCASE'],
    'records case flag' => ['module%', 'LIKE', 'NOCASE', null, false, 'caseSensitiveLike', false],
    'range lower is folded module' => ['module%', 'LIKE', 'NOCASE', null, false, 'range.lowerInclusive', 'module'],
    'range upper is modulf' => ['module%', 'LIKE', 'NOCASE', null, false, 'range.upperBound', 'modulf'],
    'current source retained' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentSource', 'main.app_settings'],
    'next source retained' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextSource', 'main.app_settings'],
    'current schema cookie retained' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentSchemaCookie', 41],
    'next schema cookie retained' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextSchemaCookie', 42],
    'current scan encoding retained' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentEncoding', 'UTF-16LE'],
    'next scan encoding retained' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextEncoding', 'UTF-16BE'],
    'current rowset excludes blob and null' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentRowids', [6, 7, 5, 13, 9, 10]],
    'next rowset includes new module rows' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextRowids', [6, 7, 5, 14, 9, 10]],
    'retained rowset' => ['module%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [6, 7, 5, 9, 10]],
    'exited rowset' => ['module%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', [13]],
    'entered rowset' => ['module%', 'LIKE', 'NOCASE', null, false, 'enteredRowids', [14]],
    'changed text rowids' => ['module%', 'LIKE', 'NOCASE', null, false, 'changedTextRowids', [5, 10]],
    'changed storage rowids empty for module text rows' => ['module%', 'LIKE', 'NOCASE', null, false, 'changedStorageRowids', []],
    'changed encoding rowids include retained module rows' => ['module%', 'LIKE', 'NOCASE', null, false, 'changedEncodingRowids', [6, 7, 5, 9, 10]],
    'changed bytes rowids include text and encoding changes' => ['module%', 'LIKE', 'NOCASE', null, false, 'changedBytesRowids', [6, 7, 5, 9, 10]],
    'current text map preserves uppercase source' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentTexts.5', 'module_Alpha'],
    'next text map preserves lower source' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextTexts.5', 'module_alpha'],
    'current storage map is text' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentStorage.6', 'text'],
    'next storage map is text' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextStorage.6', 'text'],
    'current bytes are utf16le' => ['module%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.6', '6d006f00640075006c0065005f0031003000300025005f0065006e00610062006c0065006400'],
    'next bytes are utf16be' => ['module%', 'LIKE', 'NOCASE', null, false, 'nextBytesHex.6', '006d006f00640075006c0065005f0031003000300025005f0065006e00610062006c00650064'],
    'cursor invalidated' => ['module%', 'LIKE', 'NOCASE', null, false, 'cursorInvalidated', true],
    'cursor not reusable' => ['module%', 'LIKE', 'NOCASE', null, false, 'cursorReusable', false],
    'reason schema cookie' => ['module%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'schema-cookie'],
    'reason scan encoding' => ['module%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.1', 'scan-encoding'],
    'reason text affinity' => ['module%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.2', 'text-affinity'],
    'reason text encoding' => ['module%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.3', 'text-encoding'],
    'reason encoded bytes' => ['module%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.4', 'encoded-bytes'],
    'reason matched rowset' => ['module%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.5', 'matched-rowset'],
    'escaped like records escape' => ['module!_100!%%', 'LIKE', 'NOCASE', '!', false, 'escape', '!'],
    'escaped like current literal percent row' => ['module!_100!%%', 'LIKE', 'NOCASE', '!', false, 'currentRowids', [6]],
    'escaped like next literal percent row' => ['module!_100!%%', 'LIKE', 'NOCASE', '!', false, 'nextRowids', [6]],
    'escaped like retains literal percent row' => ['module!_100!%%', 'LIKE', 'NOCASE', '!', false, 'retainedRowids', [6]],
    'escaped like no rowset delta' => ['module!_100!%%', 'LIKE', 'NOCASE', '!', false, 'enteredRowids', []],
    'glob records operator' => ['module_*', 'GLOB', 'BINARY', null, true, 'operator', 'GLOB'],
    'glob range lower' => ['module_*', 'GLOB', 'BINARY', null, true, 'range.lowerInclusive', 'module_'],
    'glob range upper' => ['module_*', 'GLOB', 'BINARY', null, true, 'range.upperBound', 'module`'],
    'glob current rowset is case sensitive' => ['module_*', 'GLOB', 'BINARY', null, true, 'currentRowids', [6, 7, 5, 13, 9, 10]],
    'glob next rowset is case sensitive' => ['module_*', 'GLOB', 'BINARY', null, true, 'nextRowids', [6, 7, 5, 14, 9, 10]],
    'glob entered lower alpha after current uppercase miss' => ['module_*', 'GLOB', 'BINARY', null, true, 'enteredRowids', [14]],
    'glob exited removed row' => ['module_*', 'GLOB', 'BINARY', null, true, 'exitedRowids', [13]],
    'unicode glob latin rowset current' => ['module_[À-ÿ]*', 'GLOB', 'BINARY', null, true, 'currentRowids', [9]],
    'unicode glob latin rowset next' => ['module_[À-ÿ]*', 'GLOB', 'BINARY', null, true, 'nextRowids', [9]],
    'emoji like current row' => ['module_😀%', 'LIKE', 'BINARY', null, true, 'currentRowids', [10]],
    'emoji like next row remains after suffix change' => ['module_😀%', 'LIKE', 'BINARY', null, true, 'nextRowids', [10]],
    'emoji like changed text' => ['module_😀%', 'LIKE', 'BINARY', null, true, 'changedTextRowids', [10]],
    'numeric like current rowset' => ['1%', 'LIKE', 'BINARY', null, true, 'currentRowids', [2, 3, 4]],
    'numeric like next rowset includes bool new' => ['1%', 'LIKE', 'BINARY', null, true, 'nextRowids', [2, 15, 3, 4]],
    'numeric like storage class changes' => ['1%', 'LIKE', 'BINARY', null, true, 'changedStorageRowids', [2, 4]],
    'numeric like text values unchanged after affinity' => ['1%', 'LIKE', 'BINARY', null, true, 'changedTextRowids', []],
    'numeric like current storage integer' => ['1%', 'LIKE', 'BINARY', null, true, 'currentStorage.2', 'integer'],
    'numeric like next storage text' => ['1%', 'LIKE', 'BINARY', null, true, 'nextStorage.2', 'text'],
    'stable service is reusable' => ['service%', 'LIKE', 'NOCASE', null, false, 'cursorReusable', true, 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'main.app_settings', 41, 41],
    'stable service no invalidation' => ['service%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons', [], 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'main.app_settings', 41, 41],
    'source switch reason first' => ['service%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'source-name', 'UTF-16LE', 'UTF-16LE', 'main.app_settings', 'temp.app_settings', 41, 41],
    'dependencies include text affinity' => ['module%', 'LIKE', 'NOCASE', null, false, 'dependencies.0', 'sqlite-text-affinity'],
    'dependencies include slice marker' => ['module%', 'LIKE', 'NOCASE', null, false, 'dependencies.1', 'sqlite-like-glob-current-source-next94'],
];

foreach ($cases as $name => $case) {
    $tests['encoding affinity like current source next94 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        [$pattern, $operator, $collation, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentEncoding = $case[7] ?? 'UTF-16LE';
        $nextEncoding = $case[8] ?? 'UTF-16BE';
        $currentSource = $case[9] ?? 'main.app_settings';
        $nextSource = $case[10] ?? 'main.app_settings';
        $currentSchemaCookie = $case[11] ?? 41;
        $nextSchemaCookie = $case[12] ?? 42;
        $t->same($expected, $valueAt($plan($pattern, $operator, $collation, $escape, $caseSensitiveLike, $currentEncoding, $nextEncoding, $currentSource, $nextSource, $currentSchemaCookie, $nextSchemaCookie), $path));
    };
}

$tests['encoding affinity like current source next94 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', 'module%', 'REGEXP'));
};

$tests['encoding affinity like current source next94 rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', 'module_*', 'GLOB', 'BINARY', '\\'));
};

$tests['encoding affinity like current source next94 rejects missing column'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1, 'key_name' => 'base_url']], $nextRows, 'key_value', 'b%'));
};

$tests['encoding affinity like current source next94 rejects malformed utf8 text before encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $current = [['setting_id' => 1, 'key_name' => 'broken', 'key_value' => "module_\xc3"]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan($current, $nextRows, 'key_value', 'module%'));
};

$tests['encoding affinity like current source next94 rejects unsupported encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $nextRows, 'key_value', 'module%', 'LIKE', 'NOCASE', null, false, 4));
};

return $tests;
