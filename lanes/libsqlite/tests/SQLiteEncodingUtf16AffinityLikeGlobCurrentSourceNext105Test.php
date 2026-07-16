<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'glob_pattern' => 'https://*'],
    ['setting_id' => 2, 'key_name' => 'retry_count', 'key_value' => 10, 'glob_pattern' => '1*'],
    ['setting_id' => 3, 'key_name' => 'float_threshold', 'key_value' => 10.5, 'glob_pattern' => '10.[0-4]'],
    ['setting_id' => 4, 'key_name' => 'plugin_alpha', 'key_value' => 'Plugin_Alpha', 'glob_pattern' => 'Plugin_*'],
    ['setting_id' => 5, 'key_name' => 'plugin_lower', 'key_value' => 'plugin_alpha', 'glob_pattern' => 'plugin_[a-z]*'],
    ['setting_id' => 6, 'key_name' => 'plugin_latin', 'key_value' => 'plugin_Éclair', 'glob_pattern' => 'plugin_[À-ÿ]*'],
    ['setting_id' => 7, 'key_name' => 'plugin_emoji', 'key_value' => 'plugin_😀_cache', 'glob_pattern' => 'plugin_😀*'],
    ['setting_id' => 8, 'key_name' => 'plugin_blob_value', 'key_value' => new SQLiteBlobValue('plugin_blob'), 'glob_pattern' => 'plugin_*'],
    ['setting_id' => 9, 'key_name' => 'plugin_blob_pattern', 'key_value' => 'plugin_blob', 'glob_pattern' => new SQLiteBlobValue('plugin_*')],
    ['setting_id' => 10, 'key_name' => 'null_value', 'key_value' => null, 'glob_pattern' => '*'],
    ['setting_id' => 11, 'key_name' => 'null_pattern', 'key_value' => 'plugin_null', 'glob_pattern' => null],
    ['setting_id' => 12, 'key_name' => 'bool_enabled', 'key_value' => true, 'glob_pattern' => '[01]'],
    ['setting_id' => 13, 'key_name' => 'old_plugin', 'key_value' => 'plugin_removed', 'glob_pattern' => 'plugin_*'],
    ['setting_id' => 14, 'key_name' => 'uppercase_skip', 'key_value' => 'plugin_upper', 'glob_pattern' => 'Plugin_*'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'glob_pattern' => 'https://*'],
    ['setting_id' => 2, 'key_name' => 'retry_count', 'key_value' => '10', 'glob_pattern' => '1[0-9]'],
    ['setting_id' => 3, 'key_name' => 'float_threshold', 'key_value' => 10.5, 'glob_pattern' => '10.[0-9]'],
    ['setting_id' => 4, 'key_name' => 'plugin_alpha', 'key_value' => 'Plugin_Alpha', 'glob_pattern' => 'Plugin_[A-Z]*'],
    ['setting_id' => 5, 'key_name' => 'plugin_lower', 'key_value' => 'plugin_alpha_v2', 'glob_pattern' => 'plugin_[a-z]*'],
    ['setting_id' => 6, 'key_name' => 'plugin_latin', 'key_value' => 'plugin_Éclair', 'glob_pattern' => 'plugin_[À-ÿ]*'],
    ['setting_id' => 7, 'key_name' => 'plugin_emoji', 'key_value' => 'plugin_😀_cache_v2', 'glob_pattern' => 'plugin_😀*'],
    ['setting_id' => 8, 'key_name' => 'plugin_blob_value', 'key_value' => new SQLiteBlobValue('plugin_blob'), 'glob_pattern' => 'plugin_*'],
    ['setting_id' => 9, 'key_name' => 'plugin_blob_pattern', 'key_value' => 'plugin_blob', 'glob_pattern' => new SQLiteBlobValue('plugin_*')],
    ['setting_id' => 10, 'key_name' => 'null_value', 'key_value' => null, 'glob_pattern' => '*'],
    ['setting_id' => 11, 'key_name' => 'null_pattern', 'key_value' => 'plugin_null', 'glob_pattern' => null],
    ['setting_id' => 12, 'key_name' => 'bool_enabled', 'key_value' => false, 'glob_pattern' => '[01]'],
    ['setting_id' => 15, 'key_name' => 'new_plugin', 'key_value' => 'plugin_new', 'glob_pattern' => 'plugin_*'],
    ['setting_id' => 16, 'key_name' => 'numeric_new', 'key_value' => true, 'glob_pattern' => 1],
    ['setting_id' => 17, 'key_name' => 'latin_lower_new', 'key_value' => 'plugin_éclair', 'glob_pattern' => 'plugin_[À-ÿ]*'],
];

$plan = static fn (
    int|string $currentEncoding = 'UTF-16LE',
    int|string $nextEncoding = 'UTF-16BE',
    string $currentSource = 'main.app_settings@cookie104',
    string $nextSource = 'main.app_settings@cookie105',
    int $currentSchemaCookie = 104,
    int $nextSchemaCookie = 105,
): array => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan(
    $currentRows,
    $nextRows,
    'key_value',
    'glob_pattern',
    'GLOB',
    null,
    false,
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
    'records operator' => ['operator', 'GLOB'],
    'records value column' => ['valueColumn', 'key_value'],
    'records pattern column' => ['patternColumn', 'glob_pattern'],
    'records no escape column' => ['escapeColumn', null],
    'records current source' => ['currentSource', 'main.app_settings@cookie104'],
    'records next source' => ['nextSource', 'main.app_settings@cookie105'],
    'records current cookie' => ['currentSchemaCookie', 104],
    'records next cookie' => ['nextSchemaCookie', 105],
    'records current encoding' => ['currentEncoding', 'UTF-16LE'],
    'records next encoding' => ['nextEncoding', 'UTF-16BE'],
    'current rowids sorted by glob text affinity' => ['currentRowids', [12, 2, 4, 1, 5, 13, 6, 7]],
    'next rowids sorted by glob text affinity' => ['nextRowids', [12, 16, 2, 3, 4, 1, 5, 15, 6, 17, 7]],
    'retained rowids preserve current order' => ['retainedRowids', [12, 2, 4, 1, 5, 6, 7]],
    'exited rowids expose deleted match' => ['exitedRowids', [13]],
    'entered rowids expose newly matched rows' => ['enteredRowids', [16, 3, 15, 17]],
    'changed value rowids' => ['changedValueRowids', [12, 5, 7]],
    'changed pattern rowids' => ['changedPatternRowids', [2, 4]],
    'changed storage rowids' => ['changedStorageRowids', [2]],
    'changed encoding rowids' => ['changedEncodingRowids', [12, 2, 4, 1, 5, 6, 7]],
    'changed bytes rowids' => ['changedBytesRowids', [12, 2, 4, 1, 5, 6, 7]],
    'current bool coerces to one' => ['currentTexts.12', '1'],
    'next bool coerces to zero' => ['nextTexts.12', '0'],
    'next numeric pattern coerces to one' => ['nextPatterns.16', '1'],
    'current integer coerces before glob' => ['currentTexts.2', '10'],
    'next text stays text before glob' => ['nextTexts.2', '10'],
    'current float nonmatch absent' => ['currentTexts.3', null],
    'next float matches changed bracket glob' => ['nextTexts.3', '10.5'],
    'current uppercase glob is case sensitive' => ['currentTexts.4', 'Plugin_Alpha'],
    'current lowercase glob retains lower row' => ['currentTexts.5', 'plugin_alpha'],
    'next lowercase row text changed' => ['nextTexts.5', 'plugin_alpha_v2'],
    'latin uppercase retained by unicode glob class' => ['currentTexts.6', 'plugin_Éclair'],
    'latin lowercase enters through same class' => ['nextTexts.17', 'plugin_éclair'],
    'emoji row retains and changes bytes' => ['nextTexts.7', 'plugin_😀_cache_v2'],
    'blob value is skipped' => ['currentTexts.8', null],
    'blob pattern is skipped' => ['currentTexts.9', null],
    'null value is skipped' => ['currentTexts.10', null],
    'null pattern is skipped' => ['currentTexts.11', null],
    'current bool storage recorded' => ['currentStorage.12', 'integer'],
    'next bool storage recorded' => ['nextStorage.12', 'integer'],
    'current integer storage recorded' => ['currentStorage.2', 'integer'],
    'next text storage recorded' => ['nextStorage.2', 'text'],
    'next numeric pattern storage recorded' => ['nextPatternStorage.16', 'integer'],
    'current utf16le bool bytes' => ['currentBytesHex.12', '3100'],
    'next utf16be bool bytes' => ['nextBytesHex.12', '0030'],
    'next utf16be pattern bytes for numeric one' => ['nextPatternBytesHex.16', '0031'],
    'next utf16be emoji bytes include surrogate pair' => ['nextBytesHex.7', '0070006c007500670069006e005fd83dde00005f00630061006300680065005f00760032'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source name' => ['invalidationReasons.0', 'source-name'],
    'reason schema cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason scan encoding' => ['invalidationReasons.2', 'scan-encoding'],
    'reason storage class' => ['invalidationReasons.3', 'storage-class'],
    'reason text affinity' => ['invalidationReasons.4', 'text-affinity'],
    'reason pattern affinity' => ['invalidationReasons.5', 'pattern-affinity'],
    'reason text encoding' => ['invalidationReasons.6', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.7', 'encoded-bytes'],
    'reason matched rowset' => ['invalidationReasons.8', 'matched-rowset'],
    'dependency includes text affinity' => ['dependencies.0', 'sqlite-text-affinity'],
    'dependency includes dynamic glob marker' => ['dependencies.1', 'sqlite-glob-dynamic-pattern-current-source-next105'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['encoding utf16 affinity like glob current source next105 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        if ($path === 'escapeColumn') {
            $t->same(null, $valueAt($plan(), $path));
            return;
        }
        if ($expected === null) {
            $parts = explode('.', $path);
            $last = array_pop($parts);
            $value = $valueAt($plan(), implode('.', $parts));
            $t->same(false, array_key_exists((string) $last, $value));
            return;
        }
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['encoding utf16 affinity like glob current source next105 stable cursor reusable'] = static function (TestRunner $t) use ($currentRows): void {
    $stable = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan(
        $currentRows,
        $currentRows,
        'key_value',
        'glob_pattern',
        'GLOB',
        null,
        false,
        'UTF-16LE',
        'UTF-16LE',
        'main.app_settings',
        'main.app_settings',
        104,
        104,
    );
    $t->same(true, $stable['cursorReusable']);
};

$tests['encoding utf16 affinity like glob current source next105 source switch invalidates first'] = static function (TestRunner $t) use ($currentRows): void {
    $switched = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan(
        $currentRows,
        $currentRows,
        'key_value',
        'glob_pattern',
        'GLOB',
        null,
        false,
        'UTF-16LE',
        'UTF-16LE',
        'main.app_settings',
        'temp.app_settings',
        104,
        104,
    );
    $t->same('source-name', $switched['invalidationReasons'][0]);
};

$tests['encoding utf16 affinity like glob current source next105 rejects glob escape column'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan($currentRows, $nextRows, 'key_value', 'glob_pattern', 'GLOB', 'glob_escape'));
};

$tests['encoding utf16 affinity like glob current source next105 rejects missing pattern column'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan([['setting_id' => 1, 'key_value' => 'x']], $nextRows, 'key_value', 'glob_pattern', 'GLOB'));
};

$tests['encoding utf16 affinity like glob current source next105 rejects malformed pattern'] = static function (TestRunner $t) use ($nextRows): void {
    $current = [['setting_id' => 1, 'key_value' => 'plugin_alpha', 'glob_pattern' => "plugin_\xc3*"]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan($current, $nextRows, 'key_value', 'glob_pattern', 'GLOB'));
};

$tests['encoding utf16 affinity like glob current source next105 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicLikeGlobPlan($currentRows, $nextRows, 'key_value', 'glob_pattern', 'REGEXP'));
};

return $tests;
