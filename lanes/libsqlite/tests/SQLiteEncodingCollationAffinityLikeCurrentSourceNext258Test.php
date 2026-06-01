<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current258 = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'PLUGIN_cache'],
    ['setting_id' => 3, 'key_name' => 'Plugin_Cache'],
    ['setting_id' => 4, 'key_name' => 'plugin_%literal'],
    ['setting_id' => 5, 'key_name' => 'PLUGIN_%literal'],
    ['setting_id' => 6, 'key_name' => 'plugin_alpha'],
    ['setting_id' => 7, 'key_name' => 'PLUGIN_Ä'],
    ['setting_id' => 8, 'key_name' => null],
    ['setting_id' => 9, 'key_name' => new SQLiteBlobValue('PLUGIN_blob')],
    ['setting_id' => 10, 'key_name' => 123],
];

$nextTwoFiveEight = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache'],
    ['setting_id' => 2, 'key_name' => 'PLUGIN_cache'],
    ['setting_id' => 3, 'key_name' => 'Plugin_Cache'],
    ['setting_id' => 4, 'key_name' => 'plugin_%literal'],
    ['setting_id' => 5, 'key_name' => 'PLUGIN_%literal'],
    ['setting_id' => 6, 'key_name' => 'plugin_alpha'],
    ['setting_id' => 7, 'key_name' => 'PLUGIN_Ä'],
    ['setting_id' => 11, 'key_name' => 'PLUGIN_new'],
    ['setting_id' => 12, 'key_name' => true],
];

$plan258 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'PLUGIN!_%',
    ?string $escape = '!',
    bool $currentCaseSensitive = false,
    bool $nextCaseSensitive = true,
    string $currentSource = 'main.app_settings@257',
    string $nextSource = 'main.app_settings@258',
    int $currentCookie = 257,
    int $nextCookie = 258,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan(
    $current ?? $current258,
    $next ?? $nextTwoFiveEight,
    $pattern,
    $escape,
    $currentCaseSensitive,
    $nextCaseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt258 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases258 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'key_name LIKE ? ESCAPE ? /* case_sensitive_like current-source fence */'],
    'pattern' => ['pattern', 'PLUGIN!_%'],
    'pattern hex' => ['patternHex', '504c5547494e215f25'],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'prefix' => ['prefix', 'PLUGIN_'],
    'prefix hex' => ['prefixHex', '504c5547494e5f'],
    'prefix chars' => ['prefixCharacters', 7],
    'binary lower' => ['binaryRange.lowerInclusive', 'PLUGIN_'],
    'binary upper' => ['binaryRange.upperBound', 'PLUGIN`'],
    'nocase lower' => ['noCaseRange.lowerInclusive', 'plugin_'],
    'nocase upper' => ['noCaseRange.upperBound', 'plugin`'],
    'current case flag' => ['currentCaseSensitiveLike', false],
    'next case flag' => ['nextCaseSensitiveLike', true],
    'current collation' => ['currentCollation', 'NOCASE'],
    'next collation' => ['nextCollation', 'BINARY'],
    'function flag' => ['caseSensitiveLikeChangesFunctionSemantics', true],
    'token flag' => ['caseSensitiveLikeDoesNotChangePatternTokens', true],
    'cursor flag' => ['caseSensitiveLikeInvalidatesPreparedLikeCursor', true],
    'ascii flag' => ['asciiNoCaseFoldsOnlyWhenPragmaIsOff', true],
    'escape flag' => ['escapedUnderscoreRemainsLiteralInBothModes', true],
    'glob flag' => ['globSemanticsUnaffectedByCaseSensitiveLike', true],
    'current source' => ['currentSource', 'main.app_settings@257'],
    'next source' => ['nextSource', 'main.app_settings@258'],
    'current cookie' => ['currentSchemaCookie', 257],
    'next cookie' => ['nextSchemaCookie', 258],
    'current candidates' => ['currentCandidateRowids', [10, 4, 5, 6, 1, 2, 3, 7]],
    'next candidates' => ['nextCandidateRowids', [12, 5, 2, 11, 7, 3, 4, 6, 1]],
    'current matched' => ['currentMatchedRowids', [4, 5, 6, 1, 2, 3, 7]],
    'next matched' => ['nextMatchedRowids', [5, 2, 11, 7]],
    'retained' => ['retainedMatchedRowids', [5, 2, 7]],
    'exited' => ['exitedMatchedRowids', [4, 6, 1, 3]],
    'entered' => ['enteredMatchedRowids', [11]],
    'current unknowns' => ['currentUnknownRowids', [8, 9]],
    'next unknowns' => ['nextUnknownRowids', []],
    'changed truth' => ['changedPredicateTruthRowids', [1, 3, 4, 6]],
    'changed text' => ['changedValueTextRowids', []],
    'changed storage' => ['changedStorageClassRowids', []],
    'current predicate row1' => ['currentPredicateResults.1', true],
    'next predicate row1' => ['nextPredicateResults.1', false],
    'current predicate row5' => ['currentPredicateResults.5', true],
    'next predicate row5' => ['nextPredicateResults.5', true],
    'current text row7' => ['currentValueText.7', 'PLUGIN_Ä'],
    'next text row11' => ['nextValueText.11', 'PLUGIN_new'],
    'current hex row5' => ['currentValueHex.5', '504c5547494e5f256c69746572616c'],
    'next hex row7' => ['nextValueHex.7', '504c5547494e5fc384'],
    'current storage row10' => ['currentStorageClasses.10', 'integer'],
    'next storage row12' => ['nextStorageClasses.12', 'integer'],
    'current sort row1' => ['currentSortKeys.1', 'plugin_cache'],
    'next sort row1' => ['nextSortKeys.1', 'plugin_cache'],
    'current glob probe' => ['currentGlobProbeRowids', [5, 2, 7]],
    'next glob probe' => ['nextGlobProbeRowids', [5, 2, 11, 7]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason pragma' => ['invalidationReasons.2', 'case-sensitive-like'],
    'reason rowset' => ['invalidationReasons.3', 'matched-rowset'],
    'reason truth' => ['invalidationReasons.4', 'predicate-truth'],
    'dependency pragma' => ['dependencies.0', 'sqlite-like-case-sensitive-pragma'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveEight'],
];

foreach ($cases258 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveEight ' . $name] = static function (TestRunner $t) use ($plan258, $valueAt258, $path, $expected): void {
        $t->same($expected, $valueAt258($plan258(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveEight stable nocase cursor reusable'] = static function (TestRunner $t) use ($current258, $plan258): void {
    $stable = $plan258(current: $current258, next: $current258, currentCaseSensitive: false, nextCaseSensitive: false, currentSource: 'same', nextSource: 'same', currentCookie: 258, nextCookie: 258);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([4, 5, 6, 1, 2, 3, 7], $stable['currentMatchedRowids']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveEight stable binary cursor reusable'] = static function (TestRunner $t) use ($current258, $plan258): void {
    $stable = $plan258(current: $current258, next: $current258, currentCaseSensitive: true, nextCaseSensitive: true, currentSource: 'same', nextSource: 'same', currentCookie: 258, nextCookie: 258);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([5, 2, 7], $stable['currentMatchedRowids']);
    $t->same([5, 2, 7], $stable['currentGlobProbeRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveEight omitted escape wildcard expands'] = static function (TestRunner $t) use ($current258, $plan258): void {
    $plan = $plan258(current: $current258, next: $current258, pattern: 'PLUGIN_%', escape: null, currentCaseSensitive: false, nextCaseSensitive: true, currentSource: 'same', nextSource: 'same', currentCookie: 258, nextCookie: 258);
    $t->same([4, 5, 6, 1, 2, 3, 7], $plan['currentMatchedRowids']);
    $t->same([5, 2, 7], $plan['nextMatchedRowids']);
    $t->same(['case-sensitive-like', 'matched-rowset', 'predicate-truth'], $plan['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveEight numeric and boolean key names use text affinity'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => 123],
        ['setting_id' => 2, 'key_name' => true],
        ['setting_id' => 3, 'key_name' => false],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan($rows, $rows, '1%', null, false, true, 'same', 'same', 1, 1);
    $t->same([2, 1], $plan['currentMatchedRowids']);
    $t->same([2, 1], $plan['nextMatchedRowids']);
    $t->same('integer', $plan['currentStorageClasses'][1]);
    $t->same('integer', $plan['currentStorageClasses'][2]);
};

$tests['encoding collation affinity like current source nextTwoFiveEight rejects invalid escape length'] = static function (TestRunner $t) use ($current258, $nextTwoFiveEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan($current258, $nextTwoFiveEight, 'PLUGIN!!_%', '!!'));
};

$tests['encoding collation affinity like current source nextTwoFiveEight rejects missing key name'] = static function (TestRunner $t) use ($nextTwoFiveEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan([['setting_id' => 1]], $nextTwoFiveEight));
};

$tests['encoding collation affinity like current source nextTwoFiveEight rejects array key name'] = static function (TestRunner $t) use ($nextTwoFiveEight): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationCaseSensitiveLikeTransitionPlan([['setting_id' => 1, 'key_name' => ['PLUGIN']]], $nextTwoFiveEight));
};

return $tests;
