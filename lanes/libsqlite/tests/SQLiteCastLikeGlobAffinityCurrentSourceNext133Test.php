<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCastLikeGlobAffinityCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin:beta'],
    ['option_id' => 3, 'option_name' => 'template', 'option_value' => 'Plugin:Beta'],
    ['option_id' => 4, 'option_name' => 'stylesheet', 'option_value' => 'plugin:%literal'],
    ['option_id' => 5, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin:blob')],
    ['option_id' => 6, 'option_name' => 'retry_count', 'option_value' => '42 widgets'],
    ['option_id' => 7, 'option_name' => 'decimal_rate', 'option_value' => '4.5ms'],
    ['option_id' => 8, 'option_name' => 'true_flag', 'option_value' => true],
    ['option_id' => 9, 'option_name' => 'false_flag', 'option_value' => false],
    ['option_id' => 10, 'option_name' => 'null_flag', 'option_value' => null],
    ['option_id' => 11, 'option_name' => 'unicode', 'option_value' => 'plugin:éclair'],
    ['option_id' => 12, 'option_name' => 'emoji', 'option_value' => 'plugin:😀'],
    ['option_id' => 13, 'option_name' => 'theme', 'option_value' => 'theme:alpha'],
    ['option_id' => 14, 'option_name' => 'text_zero', 'option_value' => '0plugin'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'plugin:alpha'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'plugin:beta2'],
    ['option_id' => 3, 'option_name' => 'template', 'option_value' => 'Plugin:Beta'],
    ['option_id' => 4, 'option_name' => 'stylesheet', 'option_value' => 'plugin:%literal'],
    ['option_id' => 5, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('plugin:blob2')],
    ['option_id' => 6, 'option_name' => 'retry_count', 'option_value' => 42],
    ['option_id' => 7, 'option_name' => 'decimal_rate', 'option_value' => '5.5ms'],
    ['option_id' => 8, 'option_name' => 'true_flag', 'option_value' => false],
    ['option_id' => 9, 'option_name' => 'false_flag', 'option_value' => true],
    ['option_id' => 10, 'option_name' => 'null_flag', 'option_value' => null],
    ['option_id' => 11, 'option_name' => 'unicode', 'option_value' => 'plugin:éclair2'],
    ['option_id' => 12, 'option_name' => 'emoji', 'option_value' => 'plugin:😀'],
    ['option_id' => 15, 'option_name' => 'fresh', 'option_value' => 'plugin:fresh'],
    ['option_id' => 14, 'option_name' => 'text_zero', 'option_value' => '0plugin'],
];

$plan = static fn (
    string $castTarget = 'TEXT',
    string $pattern = 'plugin:%',
    string $operator = 'LIKE',
    ?string $escape = null,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@132',
    string $nextSource = 'main.wp_options@133',
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
    'current source' => ['TEXT', 'plugin:%', 'LIKE', null, 'currentSource', 'main.wp_options@132'],
    'next source' => ['TEXT', 'plugin:%', 'LIKE', null, 'nextSource', 'main.wp_options@133'],
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
        ['option_id' => 1, 'option_value' => 'plugin:alpha'],
        ['option_id' => 2, 'option_value' => new SQLiteBlobValue('plugin:blob')],
    ];
    $plan = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', 'plugin:%', 'LIKE', null, 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['cast like glob affinity current source next133 stable leading wildcard keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_value' => 'plugin:alpha']];
    $plan = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '%alpha', 'LIKE', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast like glob affinity current source next133 stable glob leading class keeps no prefix reason'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_value' => 'plugin:alpha']];
    $plan = SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'TEXT', '[Pp]lugin:*', 'GLOB', null, 'stable', 'stable', 7, 7);
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['cast like glob affinity current source next133 rejects malformed cast target'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT); DROP TABLE wp_options; --', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin:%', 'REGEXP'));
};

$tests['cast like glob affinity current source next133 rejects glob escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin:*', 'GLOB', '!'));
};

$tests['cast like glob affinity current source next133 rejects missing option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['option_value' => 'plugin']], [], 'TEXT', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects missing option value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['option_id' => 1]], [], 'TEXT', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects non integer option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['option_id' => '1', 'option_value' => 'plugin']], [], 'TEXT', 'plugin:%'));
};

$tests['cast like glob affinity current source next133 rejects multi byte escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCastLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows, $currentRows, 'TEXT', 'plugin!!:%', 'LIKE', '!!'));
};

return $tests;
