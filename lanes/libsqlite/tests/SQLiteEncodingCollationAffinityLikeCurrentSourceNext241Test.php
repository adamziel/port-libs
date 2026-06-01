<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current241 = [
    ['setting_id' => 1, 'key_name' => "app_cache\0timeout"],
    ['setting_id' => 2, 'key_name' => "APP_CACHE\0TIMEOUT"],
    ['setting_id' => 3, 'key_name' => "app_cache\0timeout_old"],
    ['setting_id' => 4, 'key_name' => "app_cache_timeout"],
    ['setting_id' => 5, 'key_name' => "app_cache\0"],
    ['setting_id' => 6, 'key_name' => "app_cache\xc3timeout"],
    ['setting_id' => 7, 'key_name' => "app_cache\xc3timeout_old"],
    ['setting_id' => 8, 'key_name' => "app_cacheétimeout"],
    ['setting_id' => 9, 'key_name' => 404],
    ['setting_id' => 10, 'key_name' => true],
    ['setting_id' => 11, 'key_name' => new SQLiteBlobValue("app_cache\0timeout")],
    ['setting_id' => 12, 'key_name' => null],
];

$nextTwoFourOne = [
    ['setting_id' => 1, 'key_name' => "app_cache\0timeout_v2"],
    ['setting_id' => 2, 'key_name' => "APP_CACHE\0TIMEOUT"],
    ['setting_id' => 3, 'key_name' => "app_cache\0timeout_old"],
    ['setting_id' => 4, 'key_name' => "app_cache_timeout"],
    ['setting_id' => 5, 'key_name' => "app_cache\0"],
    ['setting_id' => 6, 'key_name' => "app_cache\xc3timeout"],
    ['setting_id' => 7, 'key_name' => "app_cache\xc3timeout_old"],
    ['setting_id' => 8, 'key_name' => "app_cacheétimeout"],
    ['setting_id' => 13, 'key_name' => "app_cache\0timeout_new"],
    ['setting_id' => 14, 'key_name' => "APP_CACHE\0TIMEOUT_ARCHIVE"],
    ['setting_id' => 15, 'key_name' => false],
];

$plan241 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = "app!_cache\0timeout%",
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.app_settings@240',
    string $nextSource = 'main.app_settings@241',
    int $currentCookie = 240,
    int $nextCookie = 241,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyByteAwareLikePlan(
    $current ?? $current241,
    $next ?? $nextTwoFourOne,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt241 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases241 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'key_name COLLATE NOCASE LIKE ? ESCAPE ? /* byte-aware residual cursor */'],
    'pattern hex preserves nul' => ['patternHex', '617070215f63616368650074696d656f757425'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix preserves nul' => ['prefix', "app_cache\0timeout"],
    'prefix hex' => ['prefixHex', '6170705f63616368650074696d656f7574'],
    'prefix chars counts nul' => ['prefixCharacters', 17],
    'prefix ascii' => ['prefixIsAscii', true],
    'has wildcard' => ['hasWildcard', true],
    'range lower' => ['range.lowerInclusive', "app_cache\0timeout"],
    'range upper' => ['range.upperBound', "app_cache\0timeouu"],
    'current source' => ['currentSource', 'main.app_settings@240'],
    'next source' => ['nextSource', 'main.app_settings@241'],
    'current cookie' => ['currentSchemaCookie', 240],
    'next cookie' => ['nextSchemaCookie', 241],
    'current candidates' => ['currentCandidateRowids', [2, 1, 3]],
    'next candidates' => ['nextCandidateRowids', [2, 14, 13, 3, 1]],
    'current matched' => ['currentMatchedRowids', [2, 1, 3]],
    'next matched' => ['nextMatchedRowids', [2, 14, 13, 3, 1]],
    'current residual rejected' => ['currentResidualRejectedRowids', []],
    'next residual rejected' => ['nextResidualRejectedRowids', []],
    'retained' => ['retainedRowids', [2, 1, 3]],
    'exited' => ['exitedRowids', []],
    'entered' => ['enteredRowids', [14, 13]],
    'changed name bytes' => ['changedNameBytesRowids', [1]],
    'current uppercase name' => ['currentNames.2', "APP_CACHE\0TIMEOUT"],
    'current nul name' => ['currentNames.1', "app_cache\0timeout"],
    'next archive name' => ['nextNames.14', "APP_CACHE\0TIMEOUT_ARCHIVE"],
    'current nul hex' => ['currentNameHex.1', '6170705f63616368650074696d656f7574'],
    'next changed hex' => ['nextNameHex.1', '6170705f63616368650074696d656f75745f7632'],
    'current token nul' => ['currentTokenHex.1', ['61', '70', '70', '5f', '63', '61', '63', '68', '65', '00', '74', '69', '6d', '65', '6f', '75', '74']],
    'next token count changed' => ['nextTokenCounts.1', 20],
    'current storage text' => ['currentStorage.1', 'text'],
    'next storage text' => ['nextStorage.13', 'text'],
    'current malformed rowids' => ['currentMalformedRowids', [6, 7]],
    'next malformed rowids' => ['nextMalformedRowids', [6, 7]],
    'current malformed hex' => ['currentMalformedHex.6', '6170705f6361636865c374696d656f7574'],
    'next malformed hex' => ['nextMalformedHex.7', '6170705f6361636865c374696d656f75745f6f6c64'],
    'nul flag' => ['nulByteIsNotTerminator', true],
    'malformed flag' => ['malformedUtf8FallsBackToByteTokens', true],
    'blob flag' => ['blobAffinityDoesNotParticipate', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['invalidationReasons.2', 'malformed-text'],
    'reason rowset' => ['invalidationReasons.3', 'matched-rowset'],
    'reason bytes' => ['invalidationReasons.4', 'key-name-bytes'],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-byte-tokenizer'],
    'dependency affinity' => ['dependencies.1', 'sqlite-text-affinity'],
    'dependency collation' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFourOne'],
];

foreach ($cases241 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourOne ' . $name] = static function (TestRunner $t) use ($plan241, $valueAt241, $path, $expected): void {
        $t->same($expected, $valueAt241($plan241(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourOne stable cursor reusable despite nul'] = static function (TestRunner $t) use ($plan241): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => "app_cache\0timeout"],
        ['setting_id' => 2, 'key_name' => "app_cache\0timeout_old"],
    ];
    $stable = $plan241(current: $rows, next: $rows, currentSource: 'stable', nextSource: 'stable', currentCookie: 241, nextCookie: 241);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourOne case sensitive excludes uppercase nul rows'] = static function (TestRunner $t) use ($plan241): void {
    $case = $plan241(caseSensitive: true);
    $t->same([1, 3], $case['currentMatchedRowids']);
    $t->same([13, 3, 1], $case['nextMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourOne escaped underscore rejects unescaped underscore candidates'] = static function (TestRunner $t) use ($plan241): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => "app_cache\0timeout"],
        ['setting_id' => 2, 'key_name' => "appxcache\0timeout"],
        ['setting_id' => 3, 'key_name' => "app_cache\0timeout_extra"],
    ];
    $literal = $plan241(current: $rows, next: $rows, pattern: "app!_cache\0timeout", escape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $wild = $plan241(current: $rows, next: $rows, pattern: "app_cache\0timeout", escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1], $literal['currentMatchedRowids']);
    $t->same([1, 2], $wild['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourOne malformed byte participates when pattern is byte-identical'] = static function (TestRunner $t) use ($plan241): void {
    $plan = $plan241(pattern: "app!_cache\xc3timeout%", escape: '!');
    $t->same([6, 7], $plan['currentMatchedRowids']);
    $t->same('6170705f6361636865c374696d656f7574', $plan['currentNameHex'][6]);
};

$tests['encoding collation affinity like current source nextTwoFourOne numeric and bool text affinity can match byte prefix'] = static function (TestRunner $t) use ($plan241): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => 404],
        ['setting_id' => 2, 'key_name' => 405],
        ['setting_id' => 3, 'key_name' => true],
        ['setting_id' => 4, 'key_name' => false],
    ];
    $numeric = $plan241(current: $rows, next: $rows, pattern: '40_', escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $bool = $plan241(current: $rows, next: $rows, pattern: '0', escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1, 2], $numeric['currentMatchedRowids']);
    $t->same([4], $bool['currentMatchedRowids']);
    $t->same('integer', $numeric['currentStorage'][1]);
};

$tests['encoding collation affinity like current source nextTwoFourOne blob and null stay outside text affinity scan'] = static function (TestRunner $t) use ($plan241): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => new SQLiteBlobValue("app_cache\0timeout")],
        ['setting_id' => 2, 'key_name' => null],
    ];
    $plan = $plan241(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourOne direct like keeps nul as character'] = static function (TestRunner $t): void {
    $t->same(true, SQLiteDatabase::likeMatches("app_cache\0timeout", "app!_cache\0timeout%", '!'));
    $t->same(false, SQLiteDatabase::likeMatches("app_cache", "app!_cache\0timeout%", '!'));
};

$tests['encoding collation affinity like current source nextTwoFourOne rejects missing key name'] = static function (TestRunner $t) use ($nextTwoFourOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyByteAwareLikePlan([['setting_id' => 1]], $nextTwoFourOne, 'app%'));
};

$tests['encoding collation affinity like current source nextTwoFourOne rejects non scalar key name'] = static function (TestRunner $t) use ($nextTwoFourOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyByteAwareLikePlan([['setting_id' => 1, 'key_name' => ['app']]], $nextTwoFourOne, 'app%'));
};

$tests['encoding collation affinity like current source nextTwoFourOne rejects multi character escape'] = static function (TestRunner $t) use ($current241, $nextTwoFourOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyByteAwareLikePlan($current241, $nextTwoFourOne, 'app!!_%', '!!'));
};

return $tests;
