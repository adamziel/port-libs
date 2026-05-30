<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_value' => 'cache  ', 'key_pattern' => 'cache', 'key_escape' => '!'],
    ['setting_id' => 2, 'key_value' => 'cache', 'key_pattern' => 'cache', 'key_escape' => '!'],
    ['setting_id' => 3, 'key_value' => "cache\t", 'key_pattern' => 'cache', 'key_escape' => '!'],
    ['setting_id' => 4, 'key_value' => 42, 'key_pattern' => '4_', 'key_escape' => '!'],
    ['setting_id' => 5, 'key_value' => 4.5, 'key_pattern' => '4._', 'key_escape' => '!'],
    ['setting_id' => 6, 'key_value' => true, 'key_pattern' => '1', 'key_escape' => '!'],
    ['setting_id' => 7, 'key_value' => false, 'key_pattern' => '0', 'key_escape' => '!'],
    ['setting_id' => 8, 'key_value' => new SQLiteBlobValue('plugin:blob'), 'key_pattern' => 'plugin:%', 'key_escape' => '!'],
    ['setting_id' => 9, 'key_value' => 'plugin_100%_enabled  ', 'key_pattern' => 'plugin!_100!%!_enabled', 'key_escape' => '!'],
    ['setting_id' => 10, 'key_value' => 'Plugin_Cache', 'key_pattern' => 'plugin!_cache', 'key_escape' => '!'],
    ['setting_id' => 11, 'key_value' => 'emoji_😀  ', 'key_pattern' => 'emoji_*', 'key_escape' => '!'],
    ['setting_id' => 12, 'key_value' => null, 'key_pattern' => '%', 'key_escape' => '!'],
    ['setting_id' => 13, 'key_value' => "plugin:\xc3", 'key_pattern' => 'plugin:%', 'key_escape' => '!'],
];

$nextRows = [
    ['setting_id' => 1, 'key_value' => 'cache', 'key_pattern' => 'cache', 'key_escape' => '!'],
    ['setting_id' => 2, 'key_value' => 'cache  ', 'key_pattern' => 'cache%', 'key_escape' => '!'],
    ['setting_id' => 3, 'key_value' => "cache\t", 'key_pattern' => "cache\t", 'key_escape' => '!'],
    ['setting_id' => 4, 'key_value' => 420, 'key_pattern' => '42_', 'key_escape' => '!'],
    ['setting_id' => 5, 'key_value' => 4.5, 'key_pattern' => '4._', 'key_escape' => '!'],
    ['setting_id' => 6, 'key_value' => true, 'key_pattern' => '1', 'key_escape' => '!'],
    ['setting_id' => 7, 'key_value' => false, 'key_pattern' => '1', 'key_escape' => '!'],
    ['setting_id' => 8, 'key_value' => new SQLiteBlobValue('plugin:blob  '), 'key_pattern' => 'plugin:%', 'key_escape' => '!'],
    ['setting_id' => 9, 'key_value' => 'plugin_100%_enabled', 'key_pattern' => 'plugin!_100!%!_enabled', 'key_escape' => '!'],
    ['setting_id' => 10, 'key_value' => 'Plugin_Cache', 'key_pattern' => 'Plugin!_Cache', 'key_escape' => '!'],
    ['setting_id' => 11, 'key_value' => 'emoji_😀', 'key_pattern' => 'emoji_*', 'key_escape' => '!'],
    ['setting_id' => 14, 'key_value' => 'new_cache', 'key_pattern' => 'new_%', 'key_escape' => '!'],
    ['setting_id' => 15, 'key_value' => 'bad_escape', 'key_pattern' => 'bad%', 'key_escape' => '!!'],
];

$plan = static fn (
    string $operator = 'LIKE',
    ?string $escapeColumn = 'key_escape',
    ?array $current = null,
    ?array $next = null,
    string|int $currentEncoding = 'UTF-16LE',
    string|int $nextEncoding = 'UTF-16BE',
): array => SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    'key_value',
    'key_pattern',
    $operator,
    $escapeColumn,
    'main.app_settings@143',
    'main.app_settings@144',
    143,
    144,
    $currentEncoding,
    $nextEncoding,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'encoding-rtrim-like-glob-affinity-current-source-next144'],
    'operator' => ['operator', 'LIKE'],
    'value column' => ['valueColumn', 'key_value'],
    'pattern column' => ['patternColumn', 'key_pattern'],
    'escape column' => ['escapeColumn', 'key_escape'],
    'collation' => ['collation', 'RTRIM'],
    'case sensitive like' => ['caseSensitiveLike', true],
    'current source' => ['currentSource', 'main.app_settings@143'],
    'next source' => ['nextSource', 'main.app_settings@144'],
    'current encoding' => ['currentEncoding', 'UTF-16LE'],
    'next encoding' => ['nextEncoding', 'UTF-16BE'],
    'current order rowids' => ['currentOrderRowids', [7, 6, 5, 4, 10, 1, 2, 3, 11, 8, 9]],
    'next order rowids' => ['nextOrderRowids', [7, 6, 5, 4, 10, 1, 2, 3, 11, 14, 8, 9]],
    'current matched rowids' => ['currentMatchedRowids', [7, 6, 5, 4, 2, 8]],
    'next matched rowids' => ['nextMatchedRowids', [6, 5, 4, 10, 1, 2, 3, 14, 8, 9]],
    'retained matched' => ['retainedMatchedRowids', [6, 5, 4, 2, 8]],
    'entered matched' => ['enteredMatchedRowids', [10, 1, 3, 14, 9]],
    'exited matched' => ['exitedMatchedRowids', [7]],
    'current false rowids' => ['currentFalseRowids', [10, 1, 3, 11, 9]],
    'next false rowids' => ['nextFalseRowids', [7, 11]],
    'current null rowids' => ['currentNullRowids', [12]],
    'current malformed rowids' => ['currentMalformedRowids', [13]],
    'next malformed rowids' => ['nextMalformedRowids', [15]],
    'malformed value error' => ['currentErrors.13', 'SQLite RTRIM affinity current-source next144 value requires well-formed UTF-8 before encoding'],
    'malformed escape error' => ['nextErrors.15', 'SQLite RTRIM affinity current-source next144 LIKE ESCAPE must be a single character after text affinity'],
    'row one current value' => ['currentValues.1', 'cache  '],
    'row one next value' => ['nextValues.1', 'cache'],
    'row two next pattern' => ['nextPatterns.2', 'cache%'],
    'row three next pattern tab' => ['nextPatterns.3', "cache\t"],
    'row eight storage blob' => ['currentValueStorage.8', 'blob'],
    'row four storage integer' => ['currentValueStorage.4', 'integer'],
    'row five storage real' => ['currentValueStorage.5', 'real'],
    'row six storage integer' => ['currentValueStorage.6', 'integer'],
    'row one rtrim key' => ['currentRtrimKeys.1', 'cache'],
    'row three rtrim key keeps tab' => ['currentRtrimKeys.3', "cache\t"],
    'row eight next rtrim key' => ['nextRtrimKeys.8', 'plugin:blob'],
    'row one utf16le bytes' => ['currentValueBytesHex.1', '6300610063006800650020002000'],
    'row one utf16be bytes' => ['nextValueBytesHex.1', '00630061006300680065'],
    'row eleven emoji current bytes' => ['currentValueBytesHex.11', '65006d006f006a0069005f003dd800de20002000'],
    'row eleven emoji next bytes' => ['nextValueBytesHex.11', '0065006d006f006a0069005fd83dde00'],
    'row nine pattern bytes' => ['nextPatternBytesHex.9', '0070006c007500670069006e0021005f003100300030002100250021005f0065006e00610062006c00650064'],
    'row seven residual changed false' => ['nextResidualMatches.7', false],
    'row ten residual becomes true' => ['nextResidualMatches.10', true],
    'changed value rowids' => ['changedValueRowids', [1, 2, 4, 8, 9, 11]],
    'changed pattern rowids' => ['changedPatternRowids', [2, 3, 4, 7, 10]],
    'changed rtrim rowids' => ['changedRtrimKeyRowids', [4]],
    'changed bytes rowids' => ['changedBytesRowids', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]],
    'changed residual rowids' => ['changedResidualRowids', [1, 3, 7, 9, 10]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason scan encoding' => ['invalidationReasons.2', 'scan-encoding'],
    'reason value affinity' => ['invalidationReasons.3', 'value-affinity'],
    'reason pattern affinity' => ['invalidationReasons.4', 'pattern-affinity'],
    'reason rtrim key' => ['invalidationReasons.5', 'rtrim-key'],
    'reason encoded bytes' => ['invalidationReasons.6', 'encoded-bytes'],
    'reason residual' => ['invalidationReasons.7', 'residual-result'],
    'reason malformed' => ['invalidationReasons.8', 'malformed-text'],
    'reason matched' => ['invalidationReasons.9', 'matched-rowset'],
    'dependency affinity' => ['dependencies.0', 'sqlite-text-affinity'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-collation'],
    'dependency dynamic' => ['dependencies.2', 'sqlite-like-glob-dynamic-pattern'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next144'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['encoding rtrim like glob affinity current source next144 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['encoding rtrim like glob affinity current source next144 stable identical rows reusable'] = static function (TestRunner $t) use ($plan): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'cache  ', 'key_pattern' => 'cache', 'key_escape' => '!'],
        ['setting_id' => 2, 'key_value' => 42, 'key_pattern' => '4_', 'key_escape' => '!'],
    ];
    $result = SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'key_value', 'key_pattern', 'LIKE', 'key_escape', 'stable', 'stable', 7, 7, 'UTF-8', 'UTF-8');
    $t->same([2, 1], $result['currentOrderRowids']);
    $t->same([2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['encoding rtrim like glob affinity current source next144 glob dynamic patterns'] = static function (TestRunner $t) use ($plan): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'plugin:blob  ', 'key_pattern' => 'plugin:*'],
        ['setting_id' => 2, 'key_value' => 'emoji_😀', 'key_pattern' => 'emoji_?'],
        ['setting_id' => 3, 'key_value' => 'cache', 'key_pattern' => 'plugin*'],
    ];
    $result = $plan('GLOB', null, $rows, $rows, 'UTF-8', 'UTF-8');
    $t->same('GLOB', $result['operator']);
    $t->same([3, 2, 1], $result['currentOrderRowids']);
    $t->same([2, 1], $result['currentMatchedRowids']);
    $t->same([3], $result['currentFalseRowids']);
};

$tests['encoding rtrim like glob affinity current source next144 rejects glob escape'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('GLOB', 'key_escape'));
};

$tests['encoding rtrim like glob affinity current source next144 rejects unsupported operator'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('REGEXP'));
};

$tests['encoding rtrim like glob affinity current source next144 rejects missing pattern column'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => 'cache']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'key_value', 'key_pattern'));
};

$tests['encoding rtrim like glob affinity current source next144 records array value as malformed'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => ['cache'], 'key_pattern' => '%']];
    $result = SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'key_value', 'key_pattern');
    $t->same([1], $result['currentMalformedRowids']);
    $t->same('SQLite RTRIM affinity current-source next144 value must be scalar text-affinity input', $result['currentErrors'][1]);
};

$tests['encoding rtrim like glob affinity current source next144 rejects invalid encoding'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('LIKE', 'key_escape', null, null, 'UTF-32', 'UTF-8'));
};

return $tests;
