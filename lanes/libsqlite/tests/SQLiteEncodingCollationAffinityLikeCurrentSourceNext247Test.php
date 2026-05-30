<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc247 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current247 = [
    ['option_id' => 1, 'option_name_bytes' => $enc247('plugin_café_main', 'UTF-16LE'), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name_bytes' => $enc247('PLUGIN_CAFÉ_MAIN', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 3, 'option_name' => 'plugin_café_extra'],
    ['option_id' => 4, 'option_name' => 'plugin_cafe_plain'],
    ['option_id' => 5, 'option_name' => 'plugin_cafÉ_main'],
    ['option_id' => 6, 'option_name' => 'PLUGIN_CAFÉ_AUX'],
    ['option_id' => 7, 'option_name' => 404],
    ['option_id' => 8, 'option_name' => 404.25],
    ['option_id' => 9, 'option_name' => true],
    ['option_id' => 10, 'option_name' => new SQLiteBlobValue('plugin_café_blob')],
    ['option_id' => 11, 'option_name' => null],
];

$nextTwoFourSeven = [
    ['option_id' => 1, 'option_name_bytes' => $enc247('plugin_café_main_v2', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 2, 'option_name_bytes' => $enc247('PLUGIN_CAFÉ_MAIN', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 3, 'option_name_bytes' => $enc247('plugin_café_extra', 'UTF-16LE'), 'text_encoding' => 2],
    ['option_id' => 4, 'option_name' => 'plugin_cafe_plain'],
    ['option_id' => 5, 'option_name' => 'plugin_cafÉ_main'],
    ['option_id' => 6, 'option_name' => 'PLUGIN_CAFÉ_AUX'],
    ['option_id' => 12, 'option_name' => 'plugin_café_new'],
    ['option_id' => 13, 'option_name' => 'PLUGIN_CAFÉ_NEW'],
    ['option_id' => 7, 'option_name' => '404'],
    ['option_id' => 8, 'option_name' => 404.25],
    ['option_id' => 9, 'option_name' => false],
];

$plan247 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_café%',
    ?string $escape = '!',
    string $collation = 'NOCASE',
    bool $caseSensitive = false,
    string $currentSource = 'main.app_settings@246',
    string $nextSource = 'main.app_settings@247',
    int $currentCookie = 246,
    int $nextCookie = 247,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan(
    $current ?? $current247,
    $next ?? $nextTwoFourSeven,
    $pattern,
    $escape,
    $collation,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt247 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases247 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII prefix keeps residual authoritative */'],
    'pattern' => ['pattern', 'plugin!_café%'],
    'pattern hex' => ['patternHex', '706c7567696e215f636166c3a925'],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', 'plugin_café'],
    'prefix hex' => ['prefixHex', '706c7567696e5f636166c3a9'],
    'prefix chars' => ['prefixCharacters', 11],
    'prefix ascii false' => ['prefixIsAscii', false],
    'has wildcard' => ['hasWildcard', true],
    'binary range lower' => ['binaryRange.lowerInclusive', 'plugin_café'],
    'binary range upper' => ['binaryRange.upperBound', 'plugin_cafê'],
    'nocase range lower' => ['noCaseRange.lowerInclusive', 'plugin_café'],
    'nocase range upper' => ['noCaseRange.upperBound', 'plugin_cafê'],
    'index unusable' => ['indexUsable', false],
    'range reason' => ['rangeRejectedReason', 'non_ascii_prefix_requires_residual_scan'],
    'range lower null' => ['rangeLowerInclusive', null],
    'range upper null' => ['rangeUpperBound', null],
    'current source' => ['currentSource', 'main.app_settings@246'],
    'next source' => ['nextSource', 'main.app_settings@247'],
    'current cookie' => ['currentSchemaCookie', 246],
    'next cookie' => ['nextSchemaCookie', 247],
    'current candidates' => ['currentCandidateRowids', [9, 7, 8, 6, 2, 4, 5, 3, 1]],
    'next candidates' => ['nextCandidateRowids', [9, 7, 8, 6, 2, 13, 4, 5, 3, 1, 12]],
    'current matched' => ['currentMatchedRowids', [3, 1]],
    'next matched' => ['nextMatchedRowids', [3, 1, 12]],
    'current rejected' => ['currentResidualRejectedRowids', [9, 7, 8, 6, 2, 4, 5]],
    'next rejected' => ['nextResidualRejectedRowids', [9, 7, 8, 6, 2, 13, 4, 5]],
    'retained' => ['retainedRowids', [3, 1]],
    'exited' => ['exitedRowids', []],
    'entered' => ['enteredRowids', [12]],
    'changed text' => ['changedLikeTextRowids', [1]],
    'changed bytes' => ['changedEncodedBytesRowids', [1]],
    'changed encoding' => ['changedEncodingRowids', [3, 1]],
    'changed storage' => ['changedStorageClassRowids', []],
    'current row1 name' => ['currentNames.1', 'plugin_café_main'],
    'next row1 name' => ['nextNames.1', 'plugin_café_main_v2'],
    'current row2 uppercase absent' => ['currentNames.2', null],
    'next row13 uppercase absent' => ['nextNames.13', null],
    'accent uppercase rejected absent' => ['currentNames.5', null],
    'current row1 hex' => ['currentNameHex.1', '706c7567696e5f636166c3a95f6d61696e'],
    'next row1 hex' => ['nextNameHex.1', '706c7567696e5f636166c3a95f6d61696e5f7632'],
    'current encoding row1' => ['currentEncodings.1', 'UTF-16LE'],
    'next encoding row1' => ['nextEncodings.1', 'UTF-16BE'],
    'next encoding row3' => ['nextEncodings.3', 'UTF-16LE'],
    'storage row7 absent' => ['currentStorage.7', null],
    'trace bool text' => ['currentTrace.0.likeText', '1'],
    'trace integer text' => ['currentTrace.1.likeText', '404'],
    'trace real text' => ['currentTrace.2.likeText', '404.25'],
    'trace utf16 upper' => ['currentTrace.4.textEncoding', 'UTF-16BE'],
    'trace rejected plain accentless' => ['currentTrace.5.likeText', 'plugin_cafe_plain'],
    'next trace false text' => ['nextTrace.0.likeText', '0'],
    'ascii fold flag' => ['asciiNoCaseFoldsOnlyAscii', true],
    'non ascii range flag' => ['nonAsciiPrefixDisablesNoCaseRange', true],
    'unicode residual flag' => ['unicodeLikeResidualRemainsCaseSensitiveForAccents', true],
    'utf16 flag' => ['utf16LeAndBeDecodeBeforeLike', true],
    'affinity flag' => ['numericTextAffinityRunsBeforeLike', true],
    'blob flag' => ['blobAndNullStayOutsideLike', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason text' => ['invalidationReasons.2', 'like-text'],
    'reason bytes' => ['invalidationReasons.3', 'encoded-bytes'],
    'reason encoding' => ['invalidationReasons.4', 'text-encoding'],
    'reason rowset' => ['invalidationReasons.5', 'matched-rowset'],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-escape-tokenizer'],
    'dependency utf' => ['dependencies.1', 'sqlite-mixed-utf-source-decoder'],
    'dependency nocase' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency affinity' => ['dependencies.3', 'sqlite-text-affinity-like'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoFourSeven'],
];

foreach ($cases247 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourSeven ' . $name] = static function (TestRunner $t) use ($plan247, $valueAt247, $path, $expected): void {
        $t->same($expected, $valueAt247($plan247(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourSeven stable cursor reusable'] = static function (TestRunner $t) use ($current247, $plan247): void {
    $stable = $plan247(current: $current247, next: $current247, currentSource: 'same', nextSource: 'same', currentCookie: 247, nextCookie: 247);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourSeven ascii nocase prefix can use range'] = static function (TestRunner $t) use ($plan247): void {
    $ascii = $plan247(pattern: 'plugin!_cache%', escape: '!', collation: 'NOCASE');
    $t->same(true, $ascii['prefixIsAscii']);
    $t->same(true, $ascii['indexUsable']);
    $t->same('plugin_cache', $ascii['rangeLowerInclusive']);
    $t->same('plugin_cachf', $ascii['rangeUpperBound']);
};

$tests['encoding collation affinity like current source nextTwoFourSeven case sensitive binary admits non ascii range'] = static function (TestRunner $t) use ($plan247): void {
    $case = $plan247(collation: 'BINARY', caseSensitive: true);
    $t->same(false, $case['indexUsable']);
    $t->same('non_ascii_prefix_requires_residual_scan', $case['rangeRejectedReason']);
    $t->same([3, 1], $case['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSeven nocase does not fold accented capital e'] = static function (TestRunner $t) use ($plan247): void {
    $plan = $plan247(pattern: 'plugin!_café%', escape: '!');
    $t->same(false, in_array(5, $plan['currentMatchedRowids'], true));
    $t->same(false, SQLiteDatabase::likeMatches('PLUGIN_CAFÉ_MAIN', 'plugin!_café%', '!', false));
    $t->same(false, SQLiteDatabase::likeMatches('plugin_cafÉ_main', 'plugin!_café%', '!', false));
    $t->same(true, SQLiteDatabase::likeMatches('PLUGIN_café_MAIN', 'plugin!_café%', '!', false));
};

$tests['encoding collation affinity like current source nextTwoFourSeven escaped accent prefix is literal'] = static function (TestRunner $t) use ($plan247): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_café_main'],
        ['option_id' => 2, 'option_name' => 'plugin_caf%_main'],
    ];
    $plan = $plan247(current: $rows, next: $rows, pattern: 'plugin!_caf!%%', escape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([2], $plan['currentMatchedRowids']);
    $t->same('plugin_caf%', $plan['prefix']);
};

$tests['encoding collation affinity like current source nextTwoFourSeven numeric text affinity participates in name scan'] = static function (TestRunner $t) use ($plan247): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 404],
        ['option_id' => 2, 'option_name' => 404.25],
        ['option_id' => 3, 'option_name' => false],
    ];
    $plan = $plan247(current: $rows, next: $rows, pattern: '404%', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1, 2], $plan['currentMatchedRowids']);
    $t->same('integer', $plan['currentStorage'][1]);
    $t->same('real', $plan['currentStorage'][2]);
};

$tests['encoding collation affinity like current source nextTwoFourSeven blob and null are not candidates'] = static function (TestRunner $t) use ($plan247): void {
    $rows = [
        ['option_id' => 1, 'option_name' => new SQLiteBlobValue('plugin_café_main')],
        ['option_id' => 2, 'option_name' => null],
    ];
    $plan = $plan247(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([], $plan['currentCandidateRowids']);
    $t->same([], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSeven rejects malformed utf8 string'] = static function (TestRunner $t) use ($nextTwoFourSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan([['option_id' => 1, 'option_name' => "plugin_caf\xc3"]], $nextTwoFourSeven, 'plugin!_café%', '!'));
};

$tests['encoding collation affinity like current source nextTwoFourSeven rejects missing option name'] = static function (TestRunner $t) use ($nextTwoFourSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan([['option_id' => 1]], $nextTwoFourSeven, 'plugin_café%'));
};

$tests['encoding collation affinity like current source nextTwoFourSeven rejects array option name'] = static function (TestRunner $t) use ($nextTwoFourSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan([['option_id' => 1, 'option_name' => ['plugin']]], $nextTwoFourSeven, 'plugin_café%'));
};

$tests['encoding collation affinity like current source nextTwoFourSeven rejects invalid byte encoding'] = static function (TestRunner $t) use ($nextTwoFourSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan([['option_id' => 1, 'option_name_bytes' => 'plugin', 'text_encoding' => 9]], $nextTwoFourSeven, 'plugin%'));
};

$tests['encoding collation affinity like current source nextTwoFourSeven rejects unsupported collation'] = static function (TestRunner $t) use ($current247, $nextTwoFourSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUnicodeNoCaseLikePlan($current247, $nextTwoFourSeven, 'plugin%', null, 'UNICODE'));
};

return $tests;
