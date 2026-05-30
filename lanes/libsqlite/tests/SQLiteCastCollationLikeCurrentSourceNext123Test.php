<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastCollationLikeCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_rate', 'option_value' => '4.5ms', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_limit', 'option_value' => '42 widgets', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_zero', 'option_value' => 'off', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin:blob  '), 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN:CACHE', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_spaces', 'option_value' => 'plugin:cache  ', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'plugin_tab', 'option_value' => "plugin:cache\t", 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_rate', 'option_value' => '5.5ms', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_limit', 'option_value' => '42 widgets', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_zero', 'option_value' => 'off', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin:blob'), 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN:CACHE', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_spaces', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'plugin_tab', 'option_value' => "plugin:cache\t", 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
    ['option_id' => 10, 'option_name' => 'plugin_added', 'option_value' => '49', 'autoload' => 'yes'],
];

$textPlan = static fn (): array => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan(
    $currentRows,
    $nextRows,
    'TEXT',
    'plugin:%',
    'LIKE',
    'RTRIM',
    null,
    false,
    17,
    18,
);

$integerPlan = static fn (): array => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan(
    $currentRows,
    $nextRows,
    'INTEGER',
    '4*',
    'GLOB',
    'BINARY',
);

$blobPlan = static fn (): array => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan(
    $currentRows,
    $nextRows,
    'BLOB',
    'PLUGIN:%',
    'LIKE',
    'NOCASE',
    null,
    false,
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

$textCases = [
    'operator' => ['operator', 'LIKE'],
    'cast target' => ['castTarget', 'TEXT'],
    'collation' => ['collation', 'RTRIM'],
    'pattern' => ['pattern', 'plugin:%'],
    'case insensitive like' => ['caseSensitiveLike', false],
    'schema cookie current' => ['currentSchemaCookie', 17],
    'schema cookie next' => ['nextSchemaCookie', 18],
    'current matched rowids' => ['currentRowids', [5, 6, 7, 8]],
    'next matched rowids' => ['nextRowids', [5, 6, 7, 8]],
    'changed cast rowids include updated and inserted rows' => ['changedCastRowids', [2, 5, 7, 10]],
    'changed match rowids include inserted row only' => ['changedMatchRowids', [10]],
    'not reusable after schema and cast changes' => ['reusable', false],
    'reason schema cookie' => ['invalidationReasons.0', 'schema-cookie'],
    'reason cast result' => ['invalidationReasons.1', 'cast-result'],
    'reason residual match' => ['invalidationReasons.2', 'residual-match'],
    'dependency cast' => ['dependencies.0', 'sqlite-select-cast-expression'],
    'dependency residual' => ['dependencies.1', 'sqlite-like-glob-residual'],
    'dependency collation' => ['dependencies.2', 'sqlite-collation-comparison'],
    'current blob cast storage is text' => ['currentTrace.4.castStorage', 'text'],
    'current blob text retains spaces' => ['currentTrace.4.castText', 'plugin:blob  '],
    'current blob rtrim key trims spaces' => ['currentTrace.4.collationKey', 'plugin:blob'],
    'current tab rtrim key keeps tab' => ['currentTrace.7.collationKey', "plugin:cache\t"],
    'next inserted text is tracked' => ['nextTrace.9.castText', '49'],
    'next inserted row does not match text pattern' => ['nextTrace.9.matched', false],
];

foreach ($textCases as $name => [$path, $expected]) {
    $tests['cast collation like current source next123 text ' . $name] = static function (TestRunner $t) use ($textPlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($textPlan(), $path));
    };
}

$integerCases = [
    'operator' => ['operator', 'GLOB'],
    'cast target' => ['castTarget', 'INTEGER'],
    'current rowids' => ['currentRowids', [2, 3]],
    'next rowids' => ['nextRowids', [3, 10]],
    'entered rowids' => ['enteredRowids', [10]],
    'exited rowids' => ['exitedRowids', [2]],
    'changed cast rowids' => ['changedCastRowids', [2, 10]],
    'changed match rowids' => ['changedMatchRowids', [2, 10]],
    'current rate integer prefix' => ['currentTrace.1.castValue', 4],
    'next rate integer prefix' => ['nextTrace.1.castValue', 5],
    'off casts to zero' => ['currentTrace.3.castValue', 0],
    'blob non numeric casts to zero' => ['currentTrace.4.castValue', 0],
    'null remains null cast' => ['currentTrace.8.castStorage', 'null'],
    'null text is empty residual' => ['currentTrace.8.castText', ''],
    'added value casts to integer' => ['nextTrace.9.castValue', 49],
    'added value matches glob' => ['nextTrace.9.matched', true],
];

foreach ($integerCases as $name => [$path, $expected]) {
    $tests['cast collation like current source next123 integer ' . $name] = static function (TestRunner $t) use ($integerPlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($integerPlan(), $path));
    };
}

$blobCases = [
    'cast target' => ['castTarget', 'BLOB'],
    'collation' => ['collation', 'NOCASE'],
    'current rowids' => ['currentRowids', [5, 6, 7, 8]],
    'next rowids' => ['nextRowids', [5, 6, 7, 8]],
    'blob cast storage is blob' => ['currentTrace.4.castStorage', 'blob'],
    'blob cast hex retains trailing spaces' => ['currentTrace.4.castTextHex', '706C7567696E3A626C6F622020'],
    'nocase collation lowers uppercase' => ['currentTrace.5.collationKey', 'plugin:cache'],
    'rtrim not used by nocase leaves spaces' => ['currentTrace.6.collationKey', 'plugin:cache  '],
    'next shortened blob cast changes' => ['nextTrace.4.castTextHex', '706C7567696E3A626C6F62'],
    'blob plan changes cast rows' => ['changedCastRowids', [2, 5, 7, 10]],
    'blob plan only inserted row changes match set' => ['changedMatchRowids', [10]],
    'blob plan records matched text change' => ['invalidationReasons.2', 'matched-text'],
];

foreach ($blobCases as $name => [$path, $expected]) {
    $tests['cast collation like current source next123 blob ' . $name] = static function (TestRunner $t) use ($blobPlan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($blobPlan(), $path));
    };
}

$tests['cast collation like current source next123 stable sources are reusable'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', 'plugin:%', 'LIKE', 'NOCASE');
    $t->same(true, $plan['reusable']);
};

$tests['cast collation like current source next123 stable sources have no reasons'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', 'plugin:%', 'LIKE', 'NOCASE');
    $t->same([], $plan['invalidationReasons']);
};

$tests['cast collation like current source next123 stable sources keep matched rowids'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', 'plugin:%', 'LIKE', 'NOCASE');
    $t->same([5, 7, 8], $plan['currentRowids']);
};

$tests['cast collation like current source next123 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', '%', 'REGEXP'));
};

$tests['cast collation like current source next123 rejects glob escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', '*', 'GLOB', 'BINARY', '\\'));
};

$tests['cast collation like current source next123 rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', '%', 'LIKE', 'WP_LOCALE'));
};

$tests['cast collation like current source next123 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT); DROP TABLE wp_options; --', '%'));
};

$tests['cast collation like current source next123 rejects missing option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan([['option_value' => 'plugin']], [], 'TEXT', '%'));
};

$tests['cast collation like current source next123 rejects missing option value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan([['option_id' => 1]], [], 'TEXT', '%'));
};

$tests['cast collation like current source next123 rejects non integer option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan([['option_id' => '1', 'option_value' => 'plugin']], [], 'TEXT', '%'));
};

$tests['cast collation like current source next123 rejects multi-byte escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'TEXT', 'plugin!_%', 'LIKE', 'BINARY', '!!'));
};

$tests['cast collation like current source next123 accepts decimal type target'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($currentRows, $currentRows, 'DECIMAL(10, 2)', '4*', 'GLOB');
    $t->same([2, 3], $plan['currentRowids']);
};

$tests['cast collation like current source next123 escaped LIKE pattern matches literal underscore'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_value' => 'plugin_key'], ['option_id' => 2, 'option_value' => 'pluginXkey']];
    $plan = SQLiteCastCollationLikeCurrentSourceNextPlan::optionRowValueCastScan($rows, $rows, 'TEXT', 'plugin!_key', 'LIKE', 'BINARY', '!');
    $t->same([1], $plan['currentRowids']);
};

return $tests;
