<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingAffinityLikeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'like_pattern' => 'https:%', 'like_escape' => null],
    ['option_id' => 2, 'option_name' => 'retry_count', 'option_value' => 10, 'like_pattern' => '1%', 'like_escape' => null],
    ['option_id' => 3, 'option_name' => 'float_threshold', 'option_value' => 10.5, 'like_pattern' => 10, 'like_escape' => null],
    ['option_id' => 4, 'option_name' => 'plugin_percent', 'option_value' => 'plugin_100%_enabled', 'like_pattern' => 'plugin!_100!%%', 'like_escape' => '!'],
    ['option_id' => 5, 'option_name' => 'plugin_wild', 'option_value' => 'plugin_100x_enabled', 'like_pattern' => 'plugin!_100!%%', 'like_escape' => '!'],
    ['option_id' => 6, 'option_name' => 'plugin_alpha', 'option_value' => 'Plugin_Alpha', 'like_pattern' => 'plugin%', 'like_escape' => null],
    ['option_id' => 7, 'option_name' => 'plugin_emoji', 'option_value' => 'plugin_😀_cache', 'like_pattern' => 'plugin_😀%', 'like_escape' => null],
    ['option_id' => 8, 'option_name' => 'plugin_blob_value', 'option_value' => new SQLiteBlobValue('plugin_blob'), 'like_pattern' => 'plugin%', 'like_escape' => null],
    ['option_id' => 9, 'option_name' => 'plugin_blob_pattern', 'option_value' => 'plugin_blob', 'like_pattern' => new SQLiteBlobValue('plugin%'), 'like_escape' => null],
    ['option_id' => 10, 'option_name' => 'null_value', 'option_value' => null, 'like_pattern' => '%', 'like_escape' => null],
    ['option_id' => 11, 'option_name' => 'null_pattern', 'option_value' => 'plugin_null', 'like_pattern' => null, 'like_escape' => null],
    ['option_id' => 12, 'option_name' => 'theme_alpha', 'option_value' => 'theme_alpha', 'like_pattern' => 'theme%', 'like_escape' => null],
    ['option_id' => 13, 'option_name' => 'old_plugin', 'option_value' => 'plugin_removed', 'like_pattern' => 'plugin%', 'like_escape' => null],
    ['option_id' => 16, 'option_name' => 'bool_enabled', 'option_value' => true, 'like_pattern' => '1', 'like_escape' => null],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'like_pattern' => 'https:%', 'like_escape' => null],
    ['option_id' => 2, 'option_name' => 'retry_count', 'option_value' => '10', 'like_pattern' => '1%', 'like_escape' => null],
    ['option_id' => 3, 'option_name' => 'float_threshold', 'option_value' => 10.5, 'like_pattern' => '10%', 'like_escape' => null],
    ['option_id' => 4, 'option_name' => 'plugin_percent', 'option_value' => 'plugin_100%_enabled', 'like_pattern' => 'plugin#_100#%%', 'like_escape' => '#'],
    ['option_id' => 5, 'option_name' => 'plugin_wild', 'option_value' => 'plugin_100x_enabled', 'like_pattern' => 'plugin_100_%', 'like_escape' => null],
    ['option_id' => 6, 'option_name' => 'plugin_alpha', 'option_value' => 'Plugin_Alpha', 'like_pattern' => 'Plugin%', 'like_escape' => null],
    ['option_id' => 7, 'option_name' => 'plugin_emoji', 'option_value' => 'plugin_😀_cache_v2', 'like_pattern' => 'plugin_😀%', 'like_escape' => null],
    ['option_id' => 8, 'option_name' => 'plugin_blob_value', 'option_value' => new SQLiteBlobValue('plugin_blob'), 'like_pattern' => 'plugin%', 'like_escape' => null],
    ['option_id' => 9, 'option_name' => 'plugin_blob_pattern', 'option_value' => 'plugin_blob', 'like_pattern' => new SQLiteBlobValue('plugin%'), 'like_escape' => null],
    ['option_id' => 10, 'option_name' => 'null_value', 'option_value' => null, 'like_pattern' => '%', 'like_escape' => null],
    ['option_id' => 11, 'option_name' => 'null_pattern', 'option_value' => 'plugin_null', 'like_pattern' => null, 'like_escape' => null],
    ['option_id' => 12, 'option_name' => 'theme_alpha', 'option_value' => 'theme_alpha', 'like_pattern' => 'theme%', 'like_escape' => null],
    ['option_id' => 14, 'option_name' => 'new_plugin', 'option_value' => 'plugin_new', 'like_pattern' => 'plugin%', 'like_escape' => null],
    ['option_id' => 15, 'option_name' => 'numeric_new', 'option_value' => true, 'like_pattern' => 1, 'like_escape' => null],
    ['option_id' => 16, 'option_name' => 'bool_enabled', 'option_value' => true, 'like_pattern' => '1%', 'like_escape' => null],
];

$plan = static fn (
    bool $caseSensitiveLike = false,
    int|string $currentEncoding = 'UTF-16LE',
    int|string $nextEncoding = 'UTF-16BE',
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
    int $currentSchemaCookie = 99,
    int $nextSchemaCookie = 100,
): array => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan(
    $currentRows,
    $nextRows,
    'option_value',
    'like_pattern',
    'like_escape',
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
    'records operator' => ['operator', 'LIKE'],
    'records value column' => ['valueColumn', 'option_value'],
    'records pattern column' => ['patternColumn', 'like_pattern'],
    'records escape column' => ['escapeColumn', 'like_escape'],
    'records case flag' => ['caseSensitiveLike', false],
    'records current source' => ['currentSource', 'main.app_settings'],
    'records next source' => ['nextSource', 'main.app_settings'],
    'records current cookie' => ['currentSchemaCookie', 99],
    'records next cookie' => ['nextSchemaCookie', 100],
    'records current encoding' => ['currentEncoding', 'UTF-16LE'],
    'records next encoding' => ['nextEncoding', 'UTF-16BE'],
    'current rowids sorted by coerced text' => ['currentRowids', [16, 2, 6, 1, 4, 13, 7, 12]],
    'next rowids sorted by coerced text' => ['nextRowids', [15, 16, 2, 3, 6, 1, 4, 5, 14, 7, 12]],
    'retained rowids' => ['retainedRowids', [16, 2, 6, 1, 4, 7, 12]],
    'exited rowids' => ['exitedRowids', [13]],
    'entered rowids' => ['enteredRowids', [15, 3, 5, 14]],
    'changed value rowids' => ['changedValueRowids', [7]],
    'changed pattern rowids' => ['changedPatternRowids', [16, 6, 4]],
    'changed escape rowids' => ['changedEscapeRowids', [4]],
    'changed storage rowids' => ['changedStorageRowids', [2]],
    'changed encoding rowids' => ['changedEncodingRowids', [16, 2, 6, 1, 4, 7, 12]],
    'changed bytes rowids' => ['changedBytesRowids', [16, 2, 6, 1, 4, 7, 12]],
    'current bool coerces to text one' => ['currentTexts.16', '1'],
    'next bool coerces to text one' => ['nextTexts.15', '1'],
    'current integer coerces for LIKE' => ['currentTexts.2', '10'],
    'next text stays text for LIKE' => ['nextTexts.2', '10'],
    'current float nonmatch absent' => ['currentTexts.3', null],
    'next float matches dynamic pattern' => ['nextTexts.3', '10.5'],
    'current numeric pattern coerces to text' => ['currentPatterns.16', '1'],
    'next numeric pattern coerces to text' => ['nextPatterns.15', '1'],
    'current escaped pattern retained' => ['currentPatterns.4', 'plugin!_100!%%'],
    'next escaped pattern retained' => ['nextPatterns.4', 'plugin#_100#%%'],
    'current escape retained' => ['currentEscapes.4', '!'],
    'next escape retained' => ['nextEscapes.4', '#'],
    'current integer storage recorded' => ['currentStorage.2', 'integer'],
    'next integer text storage recorded' => ['nextStorage.2', 'text'],
    'current numeric pattern storage recorded' => ['currentPatternStorage.16', 'text'],
    'next numeric pattern storage recorded' => ['nextPatternStorage.15', 'integer'],
    'current utf16le bytes for bool' => ['currentBytesHex.16', '3100'],
    'next utf16be bytes for bool' => ['nextBytesHex.16', '0031'],
    'next utf16be pattern bytes for numeric' => ['nextPatternBytesHex.15', '0031'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason schema cookie' => ['invalidationReasons.0', 'schema-cookie'],
    'reason scan encoding' => ['invalidationReasons.1', 'scan-encoding'],
    'reason storage class' => ['invalidationReasons.2', 'storage-class'],
    'reason text affinity' => ['invalidationReasons.3', 'text-affinity'],
    'reason pattern affinity' => ['invalidationReasons.4', 'pattern-affinity'],
    'reason escape affinity' => ['invalidationReasons.5', 'escape-affinity'],
    'reason text encoding' => ['invalidationReasons.6', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.7', 'encoded-bytes'],
    'reason matched rowset' => ['invalidationReasons.8', 'matched-rowset'],
    'dependencies include text affinity' => ['dependencies.0', 'sqlite-text-affinity'],
    'dependencies include next99 marker' => ['dependencies.1', 'sqlite-like-dynamic-pattern-current-source-next99'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['encoding affinity dynamic like current source next99 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        if ($expected === null) {
            $t->same(false, array_key_exists('3', $valueAt($plan(), 'currentTexts')));
            return;
        }
        $actual = $valueAt($plan(), $path);
        $t->same($expected, $actual);
    };
}

$tests['encoding affinity dynamic like current source next99 stable cursor reusable'] = static function (TestRunner $t) use ($currentRows): void {
    $stable = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan(
        $currentRows,
        $currentRows,
        'option_value',
        'like_pattern',
        'like_escape',
        false,
        'UTF-16LE',
        'UTF-16LE',
        'main.app_settings',
        'main.app_settings',
        99,
        99,
    );
    $t->same(true, $stable['cursorReusable']);
};

$tests['encoding affinity dynamic like current source next99 source switch invalidates first'] = static function (TestRunner $t) use ($currentRows): void {
    $switched = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan(
        $currentRows,
        $currentRows,
        'option_value',
        'like_pattern',
        'like_escape',
        false,
        'UTF-16LE',
        'UTF-16LE',
        'main.app_settings',
        'temp.app_settings',
        99,
        99,
    );
    $t->same('source-name', $switched['invalidationReasons'][0]);
};

$tests['encoding affinity dynamic like current source next99 case sensitive like excludes plugin alpha'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $caseSensitive = SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan($currentRows, $nextRows, 'option_value', 'like_pattern', 'like_escape', true);
    $t->same(false, in_array(6, $caseSensitive['currentRowids'], true));
};

$tests['encoding affinity dynamic like current source next99 rejects missing pattern column'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan([['option_id' => 1, 'option_value' => 'x']], $nextRows, 'option_value', 'like_pattern'));
};

$tests['encoding affinity dynamic like current source next99 rejects malformed pattern'] = static function (TestRunner $t) use ($nextRows): void {
    $current = [['option_id' => 1, 'option_value' => 'plugin_alpha', 'like_pattern' => "plugin_\xc3%"]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan($current, $nextRows, 'option_value', 'like_pattern'));
};

$tests['encoding affinity dynamic like current source next99 rejects multi char escape after affinity'] = static function (TestRunner $t) use ($nextRows): void {
    $current = [['option_id' => 1, 'option_value' => 'plugin_100%', 'like_pattern' => 'plugin!!_100!!%%', 'like_escape' => '!!']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingAffinityLikeCurrentSourceNextPlan::keyValueRowValueDynamicPatternPlan($current, $nextRows, 'option_value', 'like_pattern', 'like_escape'));
};

return $tests;
