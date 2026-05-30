<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastLikeGlobAffinityCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin:alpha'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin:beta'],
    ['setting_id' => 3, 'key_name' => 'template', 'key_value' => 'Plugin:Beta'],
    ['setting_id' => 4, 'key_name' => 'stylesheet', 'key_value' => 'plugin:%literal'],
    ['setting_id' => 5, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin:blob')],
    ['setting_id' => 6, 'key_name' => 'retry_count', 'key_value' => '42 widgets'],
    ['setting_id' => 7, 'key_name' => 'decimal_rate', 'key_value' => '4.5ms'],
    ['setting_id' => 8, 'key_name' => 'true_flag', 'key_value' => true],
    ['setting_id' => 9, 'key_name' => 'false_flag', 'key_value' => false],
    ['setting_id' => 10, 'key_name' => 'null_flag', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'plugin:éclair'],
    ['setting_id' => 12, 'key_name' => 'emoji', 'key_value' => 'plugin:😀'],
    ['setting_id' => 13, 'key_name' => 'theme', 'key_value' => 'theme:alpha'],
    ['setting_id' => 14, 'key_name' => 'text_zero', 'key_value' => '0plugin'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'service_url', 'key_value' => 'plugin:alpha'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'plugin:beta2'],
    ['setting_id' => 3, 'key_name' => 'template', 'key_value' => 'Plugin:Beta'],
    ['setting_id' => 4, 'key_name' => 'stylesheet', 'key_value' => 'plugin:%literal'],
    ['setting_id' => 5, 'key_name' => 'active_modules', 'key_value' => new SQLiteBlobValue('plugin:blob2')],
    ['setting_id' => 6, 'key_name' => 'retry_count', 'key_value' => 42],
    ['setting_id' => 7, 'key_name' => 'decimal_rate', 'key_value' => '5.5ms'],
    ['setting_id' => 8, 'key_name' => 'true_flag', 'key_value' => false],
    ['setting_id' => 9, 'key_name' => 'false_flag', 'key_value' => true],
    ['setting_id' => 10, 'key_name' => 'null_flag', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'unicode', 'key_value' => 'plugin:éclair2'],
    ['setting_id' => 12, 'key_name' => 'emoji', 'key_value' => 'plugin:😀'],
    ['setting_id' => 15, 'key_name' => 'fresh', 'key_value' => 'plugin:fresh'],
    ['setting_id' => 14, 'key_name' => 'text_zero', 'key_value' => '0plugin'],
];

$plan = static fn (
    string $castTarget = 'TEXT',
    string $pattern = 'plugin:%',
    string $operator = 'LIKE',
    ?string $escape = null,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@132',
    string $nextSource = 'main.app_settings@133',
    int $currentCookie = 132,
    int $nextCookie = 133,
): array => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $castTarget,
    $pattern,
    $operator,
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
    'operator like' => ['TEXT', 'plugin:%', 'LIKE', null, 'operator', 'LIKE'],
    'collation binary' => ['TEXT', 'plugin:%', 'LIKE', null, 'collation', 'BINARY'],
    'cast target text' => ['TEXT', 'plugin:%', 'LIKE', null, 'castTarget', 'TEXT'],
    'pattern like' => ['TEXT', 'plugin:%', 'LIKE', null, 'pattern', 'plugin:%'],
    'range lower like' => ['TEXT', 'plugin:%', 'LIKE', null, 'range.lowerInclusive', 'plugin:'],
    'range upper like' => ['TEXT', 'plugin:%', 'LIKE', null, 'range.upperBound', 'plugin;'],
    'index usable like' => ['TEXT', 'plugin:%', 'LIKE', null, 'indexUsable', true],
    'residual scan like' => ['TEXT', 'plugin:%', 'LIKE', null, 'residualScan', true],
    'current source' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentSource', 'main.app_settings@132'],
    'next source' => ['TEXT', 'plugin:%', 'LIKE', null, 'nextSource', 'main.app_settings@133'],
    'current schema cookie' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentSchemaCookie', 132],
    'next schema cookie' => ['TEXT', 'plugin:%', 'LIKE', null, 'nextSchemaCookie', 133],
    'current text candidates' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentCandidateRowids', [1, 2, 4, 5, 11, 12]],
    'next text candidates' => ['TEXT', 'plugin:%', 'LIKE', null, 'nextCandidateRowids', [1, 2, 4, 5, 11, 12, 15]],
    'current text rowids' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentRowids', [1, 2, 4, 5, 11, 12]],
    'next text rowids' => ['TEXT', 'plugin:%', 'LIKE', null, 'nextRowids', [1, 2, 4, 5, 11, 12, 15]],
    'retained text rowids' => ['TEXT', 'plugin:%', 'LIKE', null, 'retainedRowids', [1, 2, 4, 5, 11, 12]],
    'entered text rowids' => ['TEXT', 'plugin:%', 'LIKE', null, 'enteredRowids', [15]],
    'exited text rowids' => ['TEXT', 'plugin:%', 'LIKE', null, 'exitedRowids', []],
    'changed cast rowids text' => ['TEXT', 'plugin:%', 'LIKE', null, 'changedCastRowids', [2, 5, 6, 7, 8, 9, 11, 13, 15]],
    'changed text rowids text' => ['TEXT', 'plugin:%', 'LIKE', null, 'changedTextRowids', [2, 5, 6, 7, 8, 9, 11, 13, 15]],
    'changed bytes rowids text' => ['TEXT', 'plugin:%', 'LIKE', null, 'changedBytesRowids', [2, 5, 6, 7, 8, 9, 11, 13, 15]],
    'changed candidate rowids text' => ['TEXT', 'plugin:%', 'LIKE', null, 'changedCandidateRowids', [13, 15]],
    'changed match rowids text' => ['TEXT', 'plugin:%', 'LIKE', null, 'changedMatchRowids', [13, 15]],
    'invalidated text' => ['TEXT', 'plugin:%', 'LIKE', null, 'cursorInvalidated', true],
    'not reusable text' => ['TEXT', 'plugin:%', 'LIKE', null, 'cursorReusable', false],
    'reason source' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.1', 'schema-cookie'],
    'reason cast' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.2', 'cast-result'],
    'reason text affinity' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.3', 'text-affinity'],
    'reason encoded bytes' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.4', 'encoded-bytes'],
    'reason candidate' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.5', 'candidate-rowset'],
    'reason matched' => ['TEXT', 'plugin:%', 'LIKE', null, 'invalidationReasons.6', 'matched-rowset'],
    'trace uppercase binary candidate false' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentTrace.2.candidate', false],
    'trace blob original storage' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentTrace.4.originalStorage', 'blob'],
    'trace blob cast storage' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentTrace.4.castStorage', 'text'],
    'trace blob cast text' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentTrace.4.castText', 'plugin:blob'],
    'trace unicode hex' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentTrace.10.castTextHex', '706C7567696E3AC3A9636C616972'],
    'escaped percent row' => ['TEXT', 'plugin:!%%', 'LIKE', '!', 'currentRowids', [4]],
    'escaped percent candidate' => ['TEXT', 'plugin:!%%', 'LIKE', '!', 'currentCandidateRowids', [4]],
    'escaped percent records escape' => ['TEXT', 'plugin:!%%', 'LIKE', '!', 'escape', '!'],
    'integer current rowids' => ['INTEGER', '4%', 'LIKE', null, 'currentRowids', [6, 7]],
    'integer next rowids' => ['INTEGER', '4%', 'LIKE', null, 'nextRowids', [6]],
    'integer cast storage current' => ['INTEGER', '4%', 'LIKE', null, 'currentTrace.5.castStorage', 'integer'],
    'integer cast value current' => ['INTEGER', '4%', 'LIKE', null, 'currentTrace.5.castValue', 42],
    'real current rowids' => ['REAL', '4%', 'LIKE', null, 'currentRowids', [6, 7]],
    'real next rowids' => ['REAL', '4%', 'LIKE', null, 'nextRowids', [6]],
    'numeric false current zero rowids' => ['NUMERIC', '0', 'LIKE', null, 'currentRowids', [1, 2, 3, 4, 5, 9, 11, 12, 13, 14]],
    'numeric false exits changed false and theme' => ['NUMERIC', '0', 'LIKE', null, 'exitedRowids', [9, 13]],
    'numeric true enters from false change' => ['NUMERIC', '1', 'LIKE', null, 'enteredRowids', [9]],
    'operator glob' => ['TEXT', 'plugin:*', 'GLOB', null, 'operator', 'GLOB'],
    'range lower glob' => ['TEXT', 'plugin:*', 'GLOB', null, 'range.lowerInclusive', 'plugin:'],
    'range upper glob' => ['TEXT', 'plugin:*', 'GLOB', null, 'range.upperBound', 'plugin;'],
    'glob text rowids' => ['TEXT', 'plugin:*', 'GLOB', null, 'currentRowids', [1, 2, 4, 5, 11, 12]],
    'glob next rowids' => ['TEXT', 'plugin:*', 'GLOB', null, 'nextRowids', [1, 2, 4, 5, 11, 12, 15]],
    'glob class unicode row' => ['TEXT', 'plugin:[À-ÿ]*', 'GLOB', null, 'currentRowids', [11]],
    'glob emoji literal row' => ['TEXT', 'plugin:😀', 'GLOB', null, 'currentRowids', [12]],
    'glob integer rowids' => ['INTEGER', '4*', 'GLOB', null, 'currentRowids', [6, 7]],
    'glob integer next rowids' => ['INTEGER', '4*', 'GLOB', null, 'nextRowids', [6]],
    'leading like wildcard no range' => ['TEXT', '%alpha', 'LIKE', null, 'range', null],
    'leading like wildcard no candidates' => ['TEXT', '%alpha', 'LIKE', null, 'currentCandidateRowids', []],
    'leading like wildcard reason' => ['TEXT', '%alpha', 'LIKE', null, 'invalidationReasons.2', 'no-prefix-range'],
    'leading glob class no range' => ['TEXT', '[Pp]lugin:*', 'GLOB', null, 'range', null],
    'leading glob class no candidates' => ['TEXT', '[Pp]lugin:*', 'GLOB', null, 'currentCandidateRowids', []],
    'dependency cast' => ['TEXT', 'plugin:%', 'LIKE', null, 'dependencies.0', 'sqlite-select-cast-expression'],
    'dependency range' => ['TEXT', 'plugin:%', 'LIKE', null, 'dependencies.1', 'sqlite-binary-like-glob-prefix-range'],
    'dependency residual' => ['TEXT', 'plugin:%', 'LIKE', null, 'dependencies.2', 'sqlite-like-glob-text-affinity-residual'],
    'dependency current source' => ['TEXT', 'plugin:%', 'LIKE', null, 'dependencies.3', 'sqlite-current-source-next133'],
];

foreach ($cases as $name => [$castTarget, $pattern, $operator, $escape, $path, $expected]) {
    $tests['cast like glob affinity current source next133 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $castTarget, $pattern, $operator, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($castTarget, $pattern, $operator, $escape), $path));
    };
}

$tests['cast like glob affinity current source next133 stable sources are reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'plugin:alpha'],
        ['setting_id' => 2, 'key_value' => new SQLiteBlobValue('plugin:blob')],
    ];
    $plan = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'plugin:%', 'LIKE', null, 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast like glob affinity current source next133 stable leading wildcard keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => 'plugin:alpha']];
    $plan = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '%alpha', 'LIKE', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast like glob affinity current source next133 stable glob leading class keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value' => 'plugin:alpha']];
    $plan = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '[Pp]lugin:*', 'GLOB', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast like glob affinity current source next133 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT); DROP TABLE app_settings; --', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin:%', 'REGEXP'));
};

$tests['cast like glob affinity current source next133 rejects glob escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin:*', 'GLOB', '!'));
};

$tests['cast like glob affinity current source next133 rejects missing setting id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'plugin']], [], 'TEXT', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects missing setting value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], [], 'TEXT', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects non integer setting id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => '1', 'key_value' => 'plugin']], [], 'TEXT', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects multi byte escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin!!:%', 'LIKE', '!!'));
};

return $tests;
