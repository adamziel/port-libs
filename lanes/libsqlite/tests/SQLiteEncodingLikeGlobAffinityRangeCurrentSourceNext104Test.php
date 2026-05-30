<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'wp_plugin_alpha', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'wp_plugin_beta', 'option_value' => 'plugin:beta'],
    ['option_id' => 3, 'option_name' => 'wp_plugin_cache', 'option_value' => 'Plugin:Cache'],
    ['option_id' => 4, 'option_name' => 'wp_theme_alpha', 'option_value' => 'theme:alpha'],
    ['option_id' => 5, 'option_name' => 'wp_option_42', 'option_value' => 42],
    ['option_id' => 6, 'option_name' => 'wp_option_4_5', 'option_value' => 4.5],
    ['option_id' => 7, 'option_name' => 'wp_bool_yes', 'option_value' => true],
    ['option_id' => 8, 'option_name' => 'wp_null', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'wp_plugin_literal', 'option_value' => 'plugin:%literal'],
    ['option_id' => 10, 'option_name' => 'wp_plugin_emoji', 'option_value' => 'plugin:😀'],
    ['option_id' => 11, 'option_name' => 'wp_plugin_accent', 'option_value' => 'plugin:éclair'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'wp_plugin_alpha', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'wp_plugin_beta', 'option_value' => 'plugin:beta2'],
    ['option_id' => 3, 'option_name' => 'wp_plugin_cache', 'option_value' => 'Plugin:Cache'],
    ['option_id' => 4, 'option_name' => 'wp_theme_alpha', 'option_value' => 'theme:alpha'],
    ['option_id' => 5, 'option_name' => 'wp_option_42', 'option_value' => '42'],
    ['option_id' => 6, 'option_name' => 'wp_option_4_5', 'option_value' => 4.5],
    ['option_id' => 7, 'option_name' => 'wp_bool_yes', 'option_value' => false],
    ['option_id' => 8, 'option_name' => 'wp_null', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'wp_plugin_literal', 'option_value' => 'plugin:%literal'],
    ['option_id' => 10, 'option_name' => 'wp_plugin_emoji', 'option_value' => 'plugin:😀'],
    ['option_id' => 11, 'option_name' => 'wp_plugin_accent', 'option_value' => 'plugin:éclair'],
    ['option_id' => 12, 'option_name' => 'wp_plugin_new', 'option_value' => 'plugin:fresh'],
];

$plan = static fn (
    string $pattern = 'plugin:%',
    string $operator = 'LIKE',
    string $affinity = 'TEXT',
    string $collation = 'BINARY',
    ?string $escape = null,
    bool $caseSensitiveLike = true,
    string $currentSource = 'main.wp_options',
    string $nextSource = 'main.wp_options',
    int $currentSchemaCookie = 1,
    int $nextSchemaCookie = 1,
): array => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'option_value',
    $pattern,
    $operator,
    $affinity,
    $collation,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
);

$cases = [
    'records operator' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'operator', 'LIKE'],
    'records pattern' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'pattern', 'plugin:%'],
    'records column' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'column', 'option_value'],
    'records affinity' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'affinity', 'TEXT'],
    'records collation' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'collation', 'BINARY'],
    'records case sensitive flag' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'caseSensitiveLike', true],
    'like range is usable' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'rangeUsable', true],
    'like range lower is prefix' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'range.lowerInclusive', 'plugin:'],
    'like range upper increments colon' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'range.upperBound', 'plugin;'],
    'current like rowids' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'currentRowids', [9, 1, 2, 11, 10]],
    'next like rowids include changed and new plugin rows' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'nextRowids', [9, 1, 2, 12, 11, 10]],
    'retained like rowids' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'retainedRowids', [9, 1, 2, 11, 10]],
    'entered like rowids' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'enteredRowids', [12]],
    'exited like rowids empty' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'exitedRowids', []],
    'changed plugin beta text' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'changedTextRowids', [2]],
    'changed plugin beta bytes' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'changedBytesRowids', [2]],
    'like changed storage empty for text rows' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'changedStorageRowids', []],
    'like range class unchanged' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'changedRangeClassRowids', []],
    'like current literal text' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'currentTexts.9', 'plugin:%literal'],
    'like next fresh text' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'nextTexts.12', 'plugin:fresh'],
    'like current range class' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'currentRangeClasses.1', 'in-range'],
    'like next range class' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'nextRangeClasses.12', 'in-range'],
    'like current bytes use utf16le' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'currentBytesHex.1', '70006c007500670069006e003a0061006c00700068006100'],
    'like invalidates' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'cursorInvalidated', true],
    'like not reusable' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'cursorReusable', false],
    'like reason text affinity' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.0', 'text-affinity'],
    'like reason encoded bytes' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.1', 'encoded-bytes'],
    'like reason matched rowset' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.2', 'matched-rowset'],
    'escaped percent current row' => ['plugin:!%%', 'LIKE', 'TEXT', 'BINARY', '!', true, 'currentRowids', [9]],
    'escaped percent next row' => ['plugin:!%%', 'LIKE', 'TEXT', 'BINARY', '!', true, 'nextRowids', [9]],
    'escaped percent keeps escape' => ['plugin:!%%', 'LIKE', 'TEXT', 'BINARY', '!', true, 'escape', '!'],
    'glob plugin current rows' => ['plugin:*', 'GLOB', 'TEXT', 'BINARY', null, true, 'currentRowids', [9, 1, 2, 11, 10]],
    'glob plugin next rows' => ['plugin:*', 'GLOB', 'TEXT', 'BINARY', null, true, 'nextRowids', [9, 1, 2, 12, 11, 10]],
    'glob range lower' => ['plugin:*', 'GLOB', 'TEXT', 'BINARY', null, true, 'range.lowerInclusive', 'plugin:'],
    'glob range upper' => ['plugin:*', 'GLOB', 'TEXT', 'BINARY', null, true, 'range.upperBound', 'plugin;'],
    'glob class accented row' => ['plugin:[À-ÿ]*', 'GLOB', 'TEXT', 'BINARY', null, true, 'currentRowids', [11]],
    'glob class accented range lower' => ['plugin:[À-ÿ]*', 'GLOB', 'TEXT', 'BINARY', null, true, 'range.lowerInclusive', 'plugin:'],
    'glob class emoji row' => ['plugin:😀', 'GLOB', 'TEXT', 'BINARY', null, true, 'currentRowids', [10]],
    'numeric affinity current numeric rowids' => ['4%', 'LIKE', 'NUMERIC', 'BINARY', null, true, 'currentRowids', [6, 5]],
    'numeric affinity next numeric rowids' => ['4%', 'LIKE', 'NUMERIC', 'BINARY', null, true, 'nextRowids', [6, 5]],
    'numeric affinity detects storage change' => ['4%', 'LIKE', 'NUMERIC', 'BINARY', null, true, 'changedStorageRowids', [5]],
    'numeric affinity detects encoded bytes stable textification' => ['4%', 'LIKE', 'NUMERIC', 'BINARY', null, true, 'changedBytesRowids', []],
    'boolean true current row' => ['1', 'LIKE', 'TEXT', 'BINARY', null, true, 'currentRowids', [7]],
    'boolean true next exits' => ['1', 'LIKE', 'TEXT', 'BINARY', null, true, 'exitedRowids', [7]],
    'source change reason first' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.0', 'source-name', 'main.wp_options', 'temp.wp_options'],
    'schema cookie reason second after source' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.1', 'schema-cookie', 'main.wp_options', 'temp.wp_options', 10, 11],
    'dependency range marker' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'dependencies.0', 'sqlite-like-glob-affinity-range'],
    'dependency current source marker' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'dependencies.1', 'sqlite-current-source-next104'],
];

foreach ($cases as $name => $case) {
    $tests['encoding like glob affinity range current source next104 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $affinity, $collation, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[8] ?? 'main.wp_options';
        $nextSource = $case[9] ?? 'main.wp_options';
        $currentSchemaCookie = $case[10] ?? 1;
        $nextSchemaCookie = $case[11] ?? 1;
        $value = $plan($pattern, $operator, $affinity, $collation, $escape, $caseSensitiveLike, $currentSource, $nextSource, $currentSchemaCookie, $nextSchemaCookie);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['encoding like glob affinity range current source next104 stable identical sources reusable'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'option_value', 'plugin:%');
    $t->same(true, $plan['cursorReusable']);
    $t->same([], $plan['invalidationReasons']);
};

$tests['encoding like glob affinity range current source next104 leading wildcard is residual only'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'option_value', '%alpha');
    $t->same(false, $plan['rangeUsable']);
    $t->same('residual-only', $plan['currentRows'][0]['rangeClass']);
};

$tests['encoding like glob affinity range current source next104 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'option_value', 'x', 'REGEXP'));
};

$tests['encoding like glob affinity range current source next104 rejects glob escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'option_value', 'plugin:*', 'GLOB', 'TEXT', 'BINARY', '!'));
};

$tests['encoding like glob affinity range current source next104 rejects missing column'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'missing_value', 'plugin:%'));
};

$tests['encoding like glob affinity range current source next104 rejects nonscalar value'] = static function (TestRunner $t) use ($currentRows): void {
    $rows = $currentRows;
    $rows[] = ['option_id' => 20, 'option_value' => ['plugin']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'option_value', 'plugin:%'));
};

$tests['encoding like glob affinity range current source next104 rejects malformed utf8'] = static function (TestRunner $t) use ($currentRows): void {
    $rows = $currentRows;
    $rows[] = ['option_id' => 21, 'option_value' => "plugin:\xc3"];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'option_value', 'plugin:%'));
};

return $tests;
