<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current237 = [
    ['option_id' => 1, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'Plugin_%Beta', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_false_percent', 'option_value' => 'pluginX%gamma', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_false_missing_percent', 'option_value' => 'plugin_delta', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_number', 'option_value' => 12.5, 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'plugin_int_literal', 'option_value' => 'plugin_%123', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_%blob'), 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'plugin_bad_text', 'option_value' => "\xffplugin_%bad", 'autoload' => 'no'],
    ['option_id' => 10, 'option_name' => 'theme_literal', 'option_value' => 'theme_%alpha', 'autoload' => 'yes'],
];

$nextTwoThreeSeven = [
    ['option_id' => 1, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_upper', 'option_value' => 'Plugin_%Beta', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_false_percent', 'option_value' => 'plugin_%gamma', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_false_missing_percent', 'option_value' => 'plugin_delta', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_number', 'option_value' => 'plugin_%12.5', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_int_literal', 'option_value' => 'plugin_%123 ', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_%blob'), 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'plugin_bad_text', 'option_value' => "\xffplugin_%bad", 'autoload' => 'no'],
    ['option_id' => 11, 'option_name' => 'plugin_added', 'option_value' => 'plugin_%added', 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'plugin_case_false', 'option_value' => 'PLUGIN_%CASE', 'autoload' => 'yes'],
];

$plan237 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_%!%%',
    ?string $escape = '!',
    string $collation = 'NOCASE',
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.wp_options@236',
    string $nextSource = 'main.wp_options@237',
    int $currentCookie = 236,
    int $nextCookie = 237,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan(
    $current ?? $current237,
    $next ?? $nextTwoThreeSeven,
    $pattern,
    $escape,
    $collation,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt237 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$rowById237 = static function (array $trace, int $rowid): array {
    foreach ($trace as $row) {
        if ($row['rowid'] === $rowid) {
            return $row;
        }
    }

    throw new RuntimeException("Missing nextTwoThreeSeven trace row {$rowid}");
};

$cases237 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoThreeSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_value LIKE ? ESCAPE ? COLLATE NOCASE /* text affinity before residual */'],
    'pattern' => ['pattern', 'plugin!_%!%%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive flag' => ['caseSensitiveLike', false],
    'current source' => ['currentSource', 'main.wp_options@236'],
    'next source' => ['nextSource', 'main.wp_options@237'],
    'current cookie' => ['currentSchemaCookie', 236],
    'next cookie' => ['nextSchemaCookie', 237],
    'prefix' => ['prefix', 'plugin_'],
    'prefix chars' => ['prefixCharacters', 7],
    'prefix ascii' => ['prefixIsAscii', true],
    'index usable' => ['indexUsable', true],
    'rejected reason' => ['rangeRejectedReason', null],
    'range lower' => ['rangeLowerInclusive', 'plugin_'],
    'range upper' => ['rangeUpperBound', 'plugin`'],
    'current candidates' => ['currentCandidateRowids', [6, 1, 2, 4]],
    'next candidates' => ['nextCandidateRowids', [5, 6, 11, 1, 2, 12, 3, 4]],
    'current matched' => ['currentMatchedRowids', [6, 1, 2]],
    'next matched' => ['nextMatchedRowids', [5, 6, 11, 1, 2, 12, 3]],
    'retained' => ['retainedRowids', [6, 1, 2]],
    'entered' => ['enteredRowids', [5, 11, 12, 3]],
    'exited' => ['exitedRowids', []],
    'current false positives' => ['currentFalsePositiveRowids', [4]],
    'next false positives' => ['nextFalsePositiveRowids', [4]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [9]],
    'current malformed message' => ['currentErrors.9', 'SQLite encoding collation affinity LIKE nextTwoThreeSeven text value is malformed UTF-8'],
    'next malformed message' => ['nextErrors.9', 'SQLite encoding collation affinity LIKE nextTwoThreeSeven text value is malformed UTF-8'],
    'changed storage' => ['changedStorageRowids', [5, 10, 11, 12]],
    'changed text' => ['changedLikeTextRowids', [3, 5, 6, 10, 11, 12]],
    'changed key' => ['changedCollationKeyRowids', [3, 5, 6, 10, 11, 12]],
    'changed residual' => ['changedResidualRowids', [3, 5, 10, 11, 12]],
    'escape underscore flag' => ['escapeTreatsUnderscoreAsLiteral', true],
    'escape percent flag' => ['escapeTreatsPercentAsLiteralUntilTrailingWildcard', true],
    'text affinity flag' => ['textAffinityBeforeLike', true],
    'null unknown flag' => ['nullLikeResultIsUnknown', true],
    'nocase ascii flag' => ['nocaseFoldsAsciiOnly', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'dependency range' => ['dependencies.0', 'sqlite-like-escape-prefix-range'],
    'dependency affinity' => ['dependencies.1', 'sqlite-text-affinity-like'],
    'dependency collation' => ['dependencies.2', 'sqlite-like-nocase-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoThreeSeven'],
    'dependency closure' => ['dependency_closure', 'no new support component needed; reuses LIKE ESCAPE prefix planning, scalar text-affinity conversion, ASCII NOCASE collation keys, and current-source invalidation diagnostics'],
];

foreach ($cases237 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoThreeSeven ' . $name] = static function (TestRunner $t) use ($plan237, $valueAt237, $path, $expected): void {
        $t->same($expected, $valueAt237($plan237(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoThreeSeven invalidation reason order'] = static function (TestRunner $t) use ($plan237): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'storage-class',
        'affinity-text',
        'collation-key',
        'range-membership',
        'residual-result',
        'matched-rowset',
        'malformed-text',
    ], $plan237()['invalidationReasons']);
};

$traceCases237 = [
    'current one text' => ['currentTrace', 1, 'likeText', 'plugin_%alpha'],
    'current one key' => ['currentTrace', 1, 'collationKey', 'plugin_%alpha'],
    'current two nocase key' => ['currentTrace', 2, 'collationKey', 'plugin_%beta'],
    'current three not candidate' => ['currentTrace', 3, 'inRange', false],
    'current four not candidate' => ['currentTrace', 4, 'residualMatch', false],
    'current five numeric affinity' => ['currentTrace', 5, 'likeText', '12.5'],
    'current seven null text' => ['currentTrace', 7, 'likeText', null],
    'current seven residual unknown' => ['currentTrace', 7, 'residualMatch', null],
    'current eight blob text' => ['currentTrace', 8, 'likeText', null],
    'next five text affinity rewrite' => ['nextTrace', 5, 'likeText', 'plugin_%12.5'],
    'next six trailing space still matches' => ['nextTrace', 6, 'matched', true],
    'next twelve ascii nocase match' => ['nextTrace', 12, 'collationKey', 'plugin_%case'],
];

foreach ($traceCases237 as $name => [$traceKey, $rowid, $field, $expected]) {
    $tests['encoding collation affinity like current source nextTwoThreeSeven trace ' . $name] = static function (TestRunner $t) use ($plan237, $rowById237, $traceKey, $rowid, $field, $expected): void {
        $t->same($expected, $rowById237($plan237()[$traceKey], $rowid)[$field]);
    };
}

$tests['encoding collation affinity like current source nextTwoThreeSeven stable cursor is reusable'] = static function (TestRunner $t) use ($plan237): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_%alpha'],
        ['option_id' => 2, 'option_value' => 'Plugin_%Beta'],
        ['option_id' => 3, 'option_value' => 'plugin_delta'],
    ];
    $plan = $plan237($rows, $rows, 'plugin!_%!%%', '!', 'NOCASE', false, 'same', 'same', 7, 7);

    $t->same(true, $plan['cursorReusable']);
    $t->same(false, $plan['cursorInvalidated']);
    $t->same([], $plan['invalidationReasons']);
    $t->same([1, 2], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSeven escaped wildcard differs from unescaped wildcard'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_%literal'],
        ['option_id' => 2, 'option_value' => 'pluginX%literal'],
    ];
    $escaped = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($rows, $rows, 'plugin!_%!%%', '!', 'NOCASE', false, 'same', 'same', 1, 1);
    $unescaped = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($rows, $rows, 'plugin_%!%%', '!', 'NOCASE', false, 'same', 'same', 1, 1);

    $t->same([1], $escaped['currentMatchedRowids']);
    $t->same([1, 2], $unescaped['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSeven case sensitive like rejects nocase range'] = static function (TestRunner $t) use ($current237, $nextTwoThreeSeven, $plan237): void {
    $plan = $plan237($current237, $nextTwoThreeSeven, 'plugin!_%!%%', '!', 'NOCASE', true);

    $t->same(false, $plan['indexUsable']);
    $t->same('case_sensitive_like_requires_binary_index', $plan['rangeRejectedReason']);
    $t->same([], $plan['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSeven binary case sensitive range keeps uppercase residual out'] = static function (TestRunner $t) use ($current237, $nextTwoThreeSeven, $plan237): void {
    $plan = $plan237($current237, $nextTwoThreeSeven, 'plugin!_%!%%', '!', 'BINARY', true);

    $t->same(true, $plan['indexUsable']);
    $t->same([6, 1], $plan['currentMatchedRowids']);
    $t->same([5, 6, 11, 1, 3], $plan['nextMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoThreeSeven rejects multi character escape'] = static function (TestRunner $t) use ($current237): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($current237, [], '%', '!!'));
};

$tests['encoding collation affinity like current source nextTwoThreeSeven rejects bad collation'] = static function (TestRunner $t) use ($current237): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($current237, [], '%', '!', 'UNICODE'));
};

$tests['encoding collation affinity like current source nextTwoThreeSeven records nonscalar value as malformed'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_value' => ['plugin_%bad']]];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($rows, [], '%', null, 'BINARY', true, 'same', 'same', 1, 1);

    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite encoding collation affinity LIKE nextTwoThreeSeven rows require scalar option_value', $plan['currentErrors'][1]);
};

$tests['encoding collation affinity like current source nextTwoThreeSeven rejects missing option id'] = static function (TestRunner $t): void {
    $rows = [['option_value' => 'plugin_%bad']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($rows, []));
};

$tests['encoding collation affinity like current source nextTwoThreeSeven rejects missing option value'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::optionRowValueEscapePlan($rows, []));
};

return $tests;
