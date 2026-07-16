<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastNocaseCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'Plugin_Cache'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin_cache_old'],
    ['setting_id' => 3, 'key_name' => 'template', 'key_value' => 'PLUGIN_CACHE_EXTRA'],
    ['setting_id' => 4, 'key_name' => 'stylesheet', 'key_value' => 'plugin-cache'],
    ['setting_id' => 5, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('PLUGIN_CACHE_BLOB')],
    ['setting_id' => 6, 'key_name' => 'retry_count', 'key_value' => '42 widgets'],
    ['setting_id' => 7, 'key_name' => 'numeric_rate', 'key_value' => '4.5ms'],
    ['setting_id' => 8, 'key_name' => 'accented', 'key_value' => 'Éclair_plugin'],
    ['setting_id' => 9, 'key_name' => 'accented_lower', 'key_value' => 'éclair_plugin'],
    ['setting_id' => 10, 'key_name' => 'null_value', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'boolean_value', 'key_value' => true],
    ['setting_id' => 12, 'key_name' => 'space_value', 'key_value' => 'Plugin_Cache '],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin_cache_old'],
    ['setting_id' => 3, 'key_name' => 'template', 'key_value' => 'PLUGIN_CACHE_EXTRA'],
    ['setting_id' => 4, 'key_name' => 'stylesheet', 'key_value' => 'plugin-cache'],
    ['setting_id' => 5, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin_cache_blob')],
    ['setting_id' => 6, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 7, 'key_name' => 'numeric_rate', 'key_value' => '5.5ms'],
    ['setting_id' => 8, 'key_name' => 'accented', 'key_value' => 'éclair_plugin'],
    ['setting_id' => 9, 'key_name' => 'accented_lower', 'key_value' => 'éclair_plugin'],
    ['setting_id' => 10, 'key_name' => 'null_value', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'boolean_value', 'key_value' => false],
    ['setting_id' => 12, 'key_name' => 'space_value', 'key_value' => 'Plugin_Cache'],
    ['setting_id' => 13, 'key_name' => 'fresh', 'key_value' => 'PLUGIN_CACHE_NEW'],
];

$plan = static fn (
    string $castTarget = 'TEXT',
    string $pattern = 'plugin\\_cache%',
    ?string $escape = '\\',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@128',
    string $nextSource = 'main.app_settings@129',
    int $currentCookie = 128,
    int $nextCookie = 129,
): array => SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $castTarget,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'operator' => ['TEXT', 'plugin\\_cache%', '\\', 'operator', 'LIKE'],
    'collation' => ['TEXT', 'plugin\\_cache%', '\\', 'collation', 'NOCASE'],
    'case insensitive like' => ['TEXT', 'plugin\\_cache%', '\\', 'caseSensitiveLike', false],
    'cast target' => ['TEXT', 'plugin\\_cache%', '\\', 'castTarget', 'TEXT'],
    'pattern prefix' => ['TEXT', 'plugin\\_cache%', '\\', 'patternPrefix', 'plugin_cache'],
    'prefix ascii' => ['TEXT', 'plugin\\_cache%', '\\', 'prefixIsAscii', true],
    'range lower' => ['TEXT', 'plugin\\_cache%', '\\', 'range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['TEXT', 'plugin\\_cache%', '\\', 'range.upperBound', 'plugin_cachf'],
    'index usable' => ['TEXT', 'plugin\\_cache%', '\\', 'indexUsable', true],
    'residual scan' => ['TEXT', 'plugin\\_cache%', '\\', 'residualScan', true],
    'nocase ascii only marker' => ['TEXT', 'plugin\\_cache%', '\\', 'nocaseIsAsciiOnly', true],
    'current source' => ['TEXT', 'plugin\\_cache%', '\\', 'currentSource', 'main.app_settings@128'],
    'next source' => ['TEXT', 'plugin\\_cache%', '\\', 'nextSource', 'main.app_settings@129'],
    'current cookie' => ['TEXT', 'plugin\\_cache%', '\\', 'currentSchemaCookie', 128],
    'next cookie' => ['TEXT', 'plugin\\_cache%', '\\', 'nextSchemaCookie', 129],
    'current candidate rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'currentCandidateRowids', [1, 2, 3, 5, 12]],
    'next candidate rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'nextCandidateRowids', [1, 2, 3, 5, 12, 13]],
    'current rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'currentRowids', [1, 2, 3, 5, 12]],
    'next rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'nextRowids', [1, 2, 3, 5, 12, 13]],
    'retained rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'retainedRowids', [1, 2, 3, 5, 12]],
    'entered rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'enteredRowids', [13]],
    'exited rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'exitedRowids', []],
    'changed cast rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'changedCastRowids', [1, 5, 6, 7, 8, 11, 12, 13]],
    'changed nocase rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'changedNocaseKeyRowids', [6, 7, 8, 11, 12, 13]],
    'changed candidate rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'changedCandidateRowids', [13]],
    'changed match rowids' => ['TEXT', 'plugin\\_cache%', '\\', 'changedMatchRowids', [13]],
    'invalidated' => ['TEXT', 'plugin\\_cache%', '\\', 'cursorInvalidated', true],
    'not reusable' => ['TEXT', 'plugin\\_cache%', '\\', 'cursorReusable', false],
    'reason source' => ['TEXT', 'plugin\\_cache%', '\\', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['TEXT', 'plugin\\_cache%', '\\', 'invalidationReasons.1', 'schema-cookie'],
    'reason cast' => ['TEXT', 'plugin\\_cache%', '\\', 'invalidationReasons.2', 'cast-result'],
    'reason nocase' => ['TEXT', 'plugin\\_cache%', '\\', 'invalidationReasons.3', 'nocase-key'],
    'reason candidate' => ['TEXT', 'plugin\\_cache%', '\\', 'invalidationReasons.4', 'candidate-rowset'],
    'reason matched' => ['TEXT', 'plugin\\_cache%', '\\', 'invalidationReasons.5', 'matched-rowset'],
    'trace upper cast text' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.0.castText', 'Plugin_Cache'],
    'trace upper nocase key' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.0.nocaseKey', 'plugin_cache'],
    'trace blob storage' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.4.originalStorage', 'blob'],
    'trace blob casts to text' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.4.castStorage', 'text'],
    'trace blob nocase key' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.4.nocaseKey', 'plugin_cache_blob'],
    'trace hyphen candidate false' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.3.candidate', false],
    'trace hyphen match false' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.3.matched', false],
    'trace space matches wildcard' => ['TEXT', 'plugin\\_cache%', '\\', 'currentTrace.11.matched', true],
    'trace next fresh candidate' => ['TEXT', 'plugin\\_cache%', '\\', 'nextTrace.12.candidate', true],
    'trace next fresh matches' => ['TEXT', 'plugin\\_cache%', '\\', 'nextTrace.12.matched', true],
    'integer current candidates' => ['INTEGER', '4%', null, 'currentCandidateRowids', [6, 7]],
    'integer next candidates' => ['INTEGER', '4%', null, 'nextCandidateRowids', [6]],
    'integer current rowids' => ['INTEGER', '4%', null, 'currentRowids', [6, 7]],
    'integer next rowids' => ['INTEGER', '4%', null, 'nextRowids', [6]],
    'integer changed match rowids' => ['INTEGER', '4%', null, 'changedMatchRowids', [7, 13]],
    'real current rowids' => ['REAL', '4%', null, 'currentRowids', [6, 7]],
    'real next rowids' => ['REAL', '4%', null, 'nextRowids', [6]],
    'numeric false rowids current' => ['NUMERIC', '0', null, 'currentRowids', [1, 2, 3, 4, 5, 8, 9, 12]],
    'numeric false rowids next' => ['NUMERIC', '0', null, 'nextRowids', [1, 2, 3, 4, 5, 8, 9, 11, 12, 13]],
    'accent uppercase prefix candidates only uppercase' => ['TEXT', 'Éclair%', null, 'currentCandidateRowids', [8]],
    'accent uppercase prefix matches only uppercase' => ['TEXT', 'Éclair%', null, 'currentRowids', [8]],
    'accent lowercase prefix candidates only lowercase' => ['TEXT', 'éclair%', null, 'currentCandidateRowids', [9]],
    'accent lowercase prefix matches only lowercase' => ['TEXT', 'éclair%', null, 'currentRowids', [9]],
    'dependency cast' => ['TEXT', 'plugin\\_cache%', '\\', 'dependencies.0', 'sqlite-select-cast-expression'],
    'dependency range' => ['TEXT', 'plugin\\_cache%', '\\', 'dependencies.1', 'sqlite-nocase-like-prefix-range'],
    'dependency residual' => ['TEXT', 'plugin\\_cache%', '\\', 'dependencies.2', 'sqlite-like-nocase-residual'],
    'dependency source' => ['TEXT', 'plugin\\_cache%', '\\', 'dependencies.3', 'sqlite-current-source-next129'],
];

foreach ($cases as $name => [$castTarget, $pattern, $escape, $path, $expected]) {
    $tests['cast nocase current source next129 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $castTarget, $pattern, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($castTarget, $pattern, $escape), $path));
    };
}

$tests['cast nocase current source next129 stable sources are reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'Plugin_Cache'],
        ['setting_id' => 2, 'key_value' => 'PLUGIN_CACHE_EXTRA'],
    ];
    $plan = SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'plugin\\_cache%', '\\', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast nocase current source next129 leading wildcard has no range'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', '%cache%', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentCandidateRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast nocase current source next129 escaped wildcard stays literal prefix'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'Plugin_Cache'],
        ['setting_id' => 2, 'key_value' => 'PluginXCache'],
    ];
    $plan = SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'plugin\\_cache%', '\\', 'stable', 'stable', 7, 7);
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['currentCandidateRowids']);
};

$tests['cast nocase current source next129 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT); DROP TABLE app_settings; --', 'plugin%'));
};

$tests['cast nocase current source next129 rejects missing setting id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'plugin']], [], 'TEXT', 'plugin%'));
};

$tests['cast nocase current source next129 rejects missing setting value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], [], 'TEXT', 'plugin%'));
};

$tests['cast nocase current source next129 rejects non integer setting id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => '1', 'key_value' => 'plugin']], [], 'TEXT', 'plugin%'));
};

$tests['cast nocase current source next129 rejects multi byte escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastNocaseCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin!!_%', '!!'));
};

return $tests;
