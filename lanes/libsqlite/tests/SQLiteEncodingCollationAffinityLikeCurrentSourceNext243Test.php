<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current243 = [
    ['setting_id' => 1, 'key_name' => 'cache_plain', 'key_value' => 'cache_hit'],
    ['setting_id' => 2, 'key_name' => 'cache_space', 'key_value' => 'cache_hit   '],
    ['setting_id' => 3, 'key_name' => 'cache_tab', 'key_value' => "cache_hit\t"],
    ['setting_id' => 4, 'key_name' => 'cache_upper', 'key_value' => 'CACHE_HIT'],
    ['setting_id' => 5, 'key_name' => 'cache_exact_prefix_spaces', 'key_value' => 'cache_   '],
    ['setting_id' => 6, 'key_name' => 'cache_numeric', 'key_value' => 404],
    ['setting_id' => 7, 'key_name' => 'cache_real', 'key_value' => 404.0],
    ['setting_id' => 8, 'key_name' => 'cache_bool', 'key_value' => true],
    ['setting_id' => 9, 'key_name' => 'cache_blob', 'key_value' => new SQLiteBlobValue('cache_hit')],
    ['setting_id' => 10, 'key_name' => 'cache_null', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'cache_unicode_lower', 'key_value' => 'cache_é'],
    ['setting_id' => 12, 'key_name' => 'cache_unicode_upper', 'key_value' => 'CACHE_É'],
];

$nextTwoFourThree = [
    ['setting_id' => 1, 'key_name' => 'cache_plain', 'key_value' => 'cache_hit'],
    ['setting_id' => 2, 'key_name' => 'cache_space_trimmed', 'key_value' => 'cache_hit'],
    ['setting_id' => 3, 'key_name' => 'cache_tab', 'key_value' => "cache_hit\t"],
    ['setting_id' => 4, 'key_name' => 'cache_upper_changed', 'key_value' => 'cache_hit'],
    ['setting_id' => 5, 'key_name' => 'cache_exact_prefix_spaces', 'key_value' => 'cache_   '],
    ['setting_id' => 6, 'key_name' => 'cache_numeric_promoted', 'key_value' => '404'],
    ['setting_id' => 7, 'key_name' => 'cache_real_changed', 'key_value' => 404.25],
    ['setting_id' => 8, 'key_name' => 'cache_bool', 'key_value' => false],
    ['setting_id' => 9, 'key_name' => 'cache_blob', 'key_value' => new SQLiteBlobValue('cache_hit')],
    ['setting_id' => 10, 'key_name' => 'cache_null', 'key_value' => null],
    ['setting_id' => 11, 'key_name' => 'cache_unicode_lower', 'key_value' => 'cache_é'],
    ['setting_id' => 12, 'key_name' => 'cache_unicode_upper', 'key_value' => 'CACHE_É'],
    ['setting_id' => 13, 'key_name' => 'cache_new_space', 'key_value' => 'cache_new   '],
];

$plan243 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'cache_%',
    ?string $escape = null,
    string $collation = 'RTRIM',
    bool $caseSensitive = false,
    string $currentSource = 'main.app_settings@242',
    string $nextSource = 'main.app_settings@243',
    int $currentCookie = 242,
    int $nextCookie = 243,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan(
    $current ?? $current243,
    $next ?? $nextTwoFourThree,
    $pattern,
    $escape,
    $collation,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt243 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases243 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'key_value COLLATE RTRIM LIKE ? /* RTRIM collation does not trim LIKE residual */'],
    'pattern' => ['pattern', 'cache_%'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'RTRIM'],
    'case flag' => ['caseSensitiveLike', false],
    'current source' => ['currentSource', 'main.app_settings@242'],
    'next source' => ['nextSource', 'main.app_settings@243'],
    'current cookie' => ['currentSchemaCookie', 242],
    'next cookie' => ['nextSchemaCookie', 243],
    'prefix' => ['prefix', 'cache'],
    'prefix chars' => ['prefixCharacters', 5],
    'prefix ascii' => ['prefixIsAscii', true],
    'rtrim index rejected' => ['indexUsable', false],
    'rtrim rejected reason' => ['rangeRejectedReason', 'default_like_requires_nocase_index'],
    'range lower' => ['rangeLowerInclusive', null],
    'range upper' => ['rangeUpperBound', null],
    'current matched' => ['currentMatchedRowids', [4, 12, 5, 1, 2, 3, 11]],
    'next matched' => ['nextMatchedRowids', [12, 5, 1, 2, 4, 3, 13, 11]],
    'retained' => ['retainedRowids', [4, 12, 5, 1, 2, 3, 11]],
    'entered' => ['enteredRowids', [13]],
    'exited' => ['exitedRowids', []],
    'current candidates' => ['currentRtrimPrefixCandidateRowids', [5, 1, 2, 3, 11]],
    'next candidates' => ['nextRtrimPrefixCandidateRowids', [5, 1, 2, 4, 3, 13, 11]],
    'current unknown' => ['currentUnknownRowids', [9, 10]],
    'next unknown' => ['nextUnknownRowids', [9, 10]],
    'changed storage' => ['changedStorageRowids', [6]],
    'changed text' => ['changedLikeTextRowids', [2, 4, 7, 8]],
    'changed rtrim' => ['changedRtrimKeyRowids', [4, 7, 8]],
    'changed residual' => ['changedResidualRowids', []],
    'trace uppercase text' => ['currentTrace.3.likeText', 'CACHE_HIT'],
    'trace uppercase key' => ['currentTrace.3.collationKey', 'CACHE_HIT'],
    'trace trailing spaces text' => ['currentTrace.7.likeText', 'cache_hit   '],
    'trace trailing spaces key' => ['currentTrace.7.rtrimKey', 'cache_hit'],
    'trace tab key keeps tab' => ['currentTrace.8.rtrimKey', "cache_hit\t"],
    'trace unicode lower hex' => ['currentTrace.9.likeTextHex', '63616368655FC3A9'],
    'next new space key' => ['nextTrace.9.rtrimKey', 'cache_new'],
    'rtrim key flag' => ['rtrimCollationTrimsSpacesForKeyOnly', true],
    'residual spaces flag' => ['likeResidualSeesTrailingSpaces', true],
    'residual rtrim flag' => ['likeResidualDoesNotUseRtrimEquality', true],
    'nocase flag' => ['nocaseLikeFoldsAsciiOnly', true],
    'affinity flag' => ['textAffinityBeforeLike', true],
    'unknown flag' => ['nullAndBlobLikeAreUnknown', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason storage' => ['invalidationReasons.2', 'storage-class'],
    'reason text' => ['invalidationReasons.3', 'like-text'],
    'reason rtrim' => ['invalidationReasons.4', 'rtrim-key'],
    'reason candidates' => ['invalidationReasons.5', 'rtrim-prefix-candidates'],
    'reason matched' => ['invalidationReasons.6', 'matched-rowset'],
    'dependency range' => ['dependencies.0', 'sqlite-like-collation-prefix-range'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-collation-key'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity-like'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFourThree'],
];

foreach ($cases243 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourThree ' . $name] = static function (TestRunner $t) use ($plan243, $valueAt243, $path, $expected): void {
        $t->same($expected, $valueAt243($plan243(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourThree stable cursor is reusable'] = static function (TestRunner $t) use ($current243, $plan243): void {
    $stable = $plan243(current: $current243, next: $current243, currentSource: 'same', nextSource: 'same', currentCookie: 243, nextCookie: 243);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourThree binary case sensitive excludes uppercase'] = static function (TestRunner $t) use ($plan243): void {
    $binary = $plan243(collation: 'BINARY', caseSensitive: true);
    $t->same('BINARY', $binary['collation']);
    $t->same(true, $binary['indexUsable']);
    $t->same([5, 1, 3, 2, 11], $binary['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourThree nocase index is usable for default like'] = static function (TestRunner $t) use ($plan243): void {
    $nocase = $plan243(collation: 'NOCASE');
    $t->same('NOCASE', $nocase['collation']);
    $t->same(true, $nocase['indexUsable']);
    $t->same('cache', $nocase['rangeLowerInclusive']);
    $t->same('cachf', $nocase['rangeUpperBound']);
};

$tests['encoding collation affinity like current source nextTwoFourThree trailing spaces are visible to residual exact match'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'cache_hit'],
        ['setting_id' => 2, 'key_value' => 'cache_hit   '],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan($rows, $rows, 'cache_hit', null, 'RTRIM', false, 'same', 'same', 1, 1);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same([1, 2], $plan['currentRtrimPrefixCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourThree tab is not trimmed by rtrim key'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'cache_hit '],
        ['setting_id' => 2, 'key_value' => "cache_hit\t"],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan($rows, $rows, 'cache_hit', null, 'RTRIM', false, 'same', 'same', 1, 1);
    $t->same([1, 2], $plan['currentRtrimPrefixCandidateRowids']);
    $t->same([], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourThree numeric text affinity can be matched'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 404],
        ['setting_id' => 2, 'key_value' => 404.0],
        ['setting_id' => 3, 'key_value' => false],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan($rows, $rows, '404%', null, 'NOCASE', false, 'same', 'same', 1, 1);
    $t->same([1, 2], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourThree blob and null stay unknown'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => new SQLiteBlobValue('cache_hit')],
        ['setting_id' => 2, 'key_value' => null],
        ['setting_id' => 3, 'key_value' => 'cache_hit'],
    ];
    $plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan($rows, $rows, 'cache_%', null, 'RTRIM', false, 'same', 'same', 1, 1);
    $t->same([3], $plan['currentMatchedRowids']);
    $t->same([1, 2], $plan['currentUnknownRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourThree rejects missing key value'] = static function (TestRunner $t) use ($nextTwoFourThree): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan([['setting_id' => 1]], $nextTwoFourThree));
};

$tests['encoding collation affinity like current source nextTwoFourThree rejects array key value'] = static function (TestRunner $t) use ($nextTwoFourThree): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan([['setting_id' => 1, 'key_value' => ['cache']]], $nextTwoFourThree));
};

$tests['encoding collation affinity like current source nextTwoFourThree rejects unsupported collation'] = static function (TestRunner $t) use ($current243, $nextTwoFourThree): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimLikeResidualPlan($current243, $nextTwoFourThree, 'cache_%', null, 'UNICODE'));
};

return $tests;
