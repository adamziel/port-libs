<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'extension_alpha', 'key_value' => 'plugin:alpha'],
    ['setting_id' => 2, 'key_name' => 'extension_beta', 'key_value' => 'plugin:beta'],
    ['setting_id' => 3, 'key_name' => 'extension_cache', 'key_value' => 'Plugin:Cache'],
    ['setting_id' => 4, 'key_name' => 'theme_alpha', 'key_value' => 'theme:alpha'],
    ['setting_id' => 5, 'key_name' => 'setting_42', 'key_value' => 42],
    ['setting_id' => 6, 'key_name' => 'setting_4_5', 'key_value' => 4.5],
    ['setting_id' => 7, 'key_name' => 'flag_yes', 'key_value' => true],
    ['setting_id' => 8, 'key_name' => 'null_entry', 'key_value' => null],
    ['setting_id' => 9, 'key_name' => 'extension_literal', 'key_value' => 'plugin:%literal'],
    ['setting_id' => 10, 'key_name' => 'extension_emoji', 'key_value' => 'plugin:😀'],
    ['setting_id' => 11, 'key_name' => 'extension_accent', 'key_value' => 'plugin:éclair'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'extension_alpha', 'key_value' => 'plugin:alpha'],
    ['setting_id' => 2, 'key_name' => 'extension_beta', 'key_value' => 'plugin:beta2'],
    ['setting_id' => 3, 'key_name' => 'extension_cache', 'key_value' => 'Plugin:Cache'],
    ['setting_id' => 4, 'key_name' => 'theme_alpha', 'key_value' => 'theme:alpha'],
    ['setting_id' => 5, 'key_name' => 'setting_42', 'key_value' => '42'],
    ['setting_id' => 6, 'key_name' => 'setting_4_5', 'key_value' => 4.5],
    ['setting_id' => 7, 'key_name' => 'flag_yes', 'key_value' => false],
    ['setting_id' => 8, 'key_name' => 'null_entry', 'key_value' => null],
    ['setting_id' => 9, 'key_name' => 'extension_literal', 'key_value' => 'plugin:%literal'],
    ['setting_id' => 10, 'key_name' => 'extension_emoji', 'key_value' => 'plugin:😀'],
    ['setting_id' => 11, 'key_name' => 'extension_accent', 'key_value' => 'plugin:éclair'],
    ['setting_id' => 12, 'key_name' => 'extension_new', 'key_value' => 'plugin:fresh'],
];

$plan = static fn (
    string $pattern = 'plugin:%',
    string $operator = 'LIKE',
    string $affinity = 'TEXT',
    string $collation = 'BINARY',
    ?string $escape = null,
    bool $caseSensitiveLike = true,
    string $currentSource = 'main.app_settings',
    string $nextSource = 'main.app_settings',
    int $currentSchemaCookie = 1,
    int $nextSchemaCookie = 1,
): array => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'key_value',
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
    'records column' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'column', 'key_value'],
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
    'source change reason first' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.0', 'source-name', 'main.app_settings', 'temp.app_settings'],
    'schema cookie reason second after source' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'invalidationReasons.1', 'schema-cookie', 'main.app_settings', 'temp.app_settings', 10, 11],
    'dependency range marker' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'dependencies.0', 'sqlite-like-glob-affinity-range'],
    'dependency current source marker' => ['plugin:%', 'LIKE', 'TEXT', 'BINARY', null, true, 'dependencies.1', 'sqlite-current-source-next104'],
];

foreach ($cases as $name => $case) {
    $tests['encoding like glob affinity range current source next104 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $affinity, $collation, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[8] ?? 'main.app_settings';
        $nextSource = $case[9] ?? 'main.app_settings';
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
    $plan = SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'key_value', 'plugin:%');
    $t->same(true, $plan['cursorReusable']);
    $t->same([], $plan['invalidationReasons']);
};

$tests['encoding like glob affinity range current source next104 leading wildcard is residual only'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'key_value', '%alpha');
    $t->same(false, $plan['rangeUsable']);
    $t->same('residual-only', $plan['currentRows'][0]['rangeClass']);
};

$tests['encoding like glob affinity range current source next104 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'key_value', 'x', 'REGEXP'));
};

$tests['encoding like glob affinity range current source next104 rejects glob escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'key_value', 'plugin:*', 'GLOB', 'TEXT', 'BINARY', '!'));
};

$tests['encoding like glob affinity range current source next104 rejects missing column'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'missing_value', 'plugin:%'));
};

$tests['encoding like glob affinity range current source next104 rejects nonscalar value'] = static function (TestRunner $t) use ($currentRows): void {
    $rows = $currentRows;
    $rows[] = ['setting_id' => 20, 'key_value' => ['plugin']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'key_value', 'plugin:%'));
};

$tests['encoding like glob affinity range current source next104 rejects malformed utf8'] = static function (TestRunner $t) use ($currentRows): void {
    $rows = $currentRows;
    $rows[] = ['setting_id' => 21, 'key_value' => "plugin:\xc3"];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'key_value', 'plugin:%'));
};

return $tests;
