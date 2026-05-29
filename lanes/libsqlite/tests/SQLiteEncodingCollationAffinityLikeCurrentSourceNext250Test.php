<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc250 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current250 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_cache  '],
    ['option_id' => 3, 'option_name' => "plugin_cache\t"],
    ['option_id' => 4, 'option_name_bytes' => $enc250('Plugin_Cache', 'UTF-16LE'), 'text_encoding' => 2],
    ['option_id' => 5, 'option_name_bytes' => $enc250('Plugin_Cache  ', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 6, 'option_name' => 'plugin_cache_extra'],
    ['option_id' => 7, 'option_name' => 'theme_cache'],
    ['option_id' => 8, 'option_name' => 404],
    ['option_id' => 9, 'option_name' => 404.0],
    ['option_id' => 10, 'option_name' => true],
    ['option_id' => 11, 'option_name' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 12, 'option_name' => null],
];

$next250 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache  '],
    ['option_id' => 2, 'option_name' => 'plugin_cache'],
    ['option_id' => 3, 'option_name' => "plugin_cache\t"],
    ['option_id' => 4, 'option_name_bytes' => $enc250('Plugin_Cache', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 5, 'option_name_bytes' => $enc250('Plugin_Cache', 'UTF-16LE'), 'text_encoding' => 2],
    ['option_id' => 6, 'option_name' => 'plugin_cache_extra'],
    ['option_id' => 7, 'option_name' => 'theme_cache'],
    ['option_id' => 8, 'option_name' => '404'],
    ['option_id' => 9, 'option_name' => 404.0],
    ['option_id' => 10, 'option_name' => false],
    ['option_id' => 13, 'option_name' => 'plugin_cache_new'],
    ['option_id' => 14, 'option_name' => 'plugin_cache_new  '],
];

$plan250 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache',
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@249',
    string $nextSource = 'main.wp_options@250',
    int $currentCookie = 249,
    int $nextCookie = 250,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressRtrimLikeResidualSourcePlan(
    $current ?? $current250,
    $next ?? $next250,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt250 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases250 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-next250'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE RTRIM LIKE ? ESCAPE ? /* RTRIM key never trims LIKE residual */'],
    'pattern' => ['pattern', 'plugin!_cache'],
    'pattern hex' => ['patternHex', '706c7567696e215f6361636865'],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'RTRIM'],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix hex' => ['prefixHex', '706c7567696e5f6361636865'],
    'prefix chars' => ['prefixCharacters', 12],
    'prefix ascii' => ['prefixIsAscii', true],
    'binary lower' => ['binaryRange.lowerInclusive', 'plugin_cache'],
    'binary upper' => ['binaryRange.upperBound', 'plugin_cachf'],
    'nocase lower' => ['noCaseRange.lowerInclusive', 'plugin_cache'],
    'nocase upper' => ['noCaseRange.upperBound', 'plugin_cachf'],
    'rtrim peer flag' => ['rtrimIndexMayFindTrailingSpacePeers', true],
    'raw residual flag' => ['likeResidualUsesRawTextBeforeRtrimCollation', true],
    'tab flag' => ['tabIsNotRtrimSpace', true],
    'ascii fold flag' => ['asciiNoCaseLikeStillFoldsAscii', true],
    'current source' => ['currentSource', 'main.wp_options@249'],
    'next source' => ['nextSource', 'main.wp_options@250'],
    'current cookie' => ['currentSchemaCookie', 249],
    'next cookie' => ['nextSchemaCookie', 250],
    'current candidates' => ['currentCandidateRowids', [10, 8, 9, 4, 5, 1, 2, 3, 6, 7]],
    'next candidates' => ['nextCandidateRowids', [10, 8, 9, 4, 5, 2, 1, 3, 6, 13, 14, 7]],
    'current matched' => ['currentMatchedRowids', [4, 1]],
    'next matched' => ['nextMatchedRowids', [4, 5, 2]],
    'current rtrim peers rejected' => ['currentRtrimPeerRejectedRowids', [5, 2]],
    'next rtrim peers rejected' => ['nextRtrimPeerRejectedRowids', [1]],
    'retained' => ['retainedRowids', [4]],
    'entered' => ['enteredRowids', [5, 2]],
    'exited' => ['exitedRowids', [1]],
    'changed raw' => ['changedRawLikeTextRowids', [1, 2, 5, 10]],
    'changed raw bytes' => ['changedRawLikeBytesRowids', [1, 2, 5, 10]],
    'changed rtrim key' => ['changedRtrimKeyRowids', [10]],
    'changed encoding' => ['changedEncodingRowids', [4, 5]],
    'changed storage' => ['changedStorageClassRowids', [8]],
    'changed truth' => ['changedResidualTruthRowids', [1, 2, 5]],
    'current raw row2' => ['currentRawText.2', 'plugin_cache  '],
    'next raw row2' => ['nextRawText.2', 'plugin_cache'],
    'current raw hex row2' => ['currentRawHex.2', '706c7567696e5f63616368652020'],
    'next raw hex row2' => ['nextRawHex.2', '706c7567696e5f6361636865'],
    'current rtrim row2' => ['currentRtrimKeys.2', 'plugin_cache'],
    'next rtrim row1' => ['nextRtrimKeys.1', 'plugin_cache'],
    'current tab rtrim not trimmed' => ['currentRtrimKeys.3', "plugin_cache\t"],
    'current encoding row4' => ['currentEncodings.4', 'UTF-16LE'],
    'next encoding row4' => ['nextEncodings.4', 'UTF-16BE'],
    'current storage row8' => ['currentStorage.8', 'integer'],
    'next storage row8' => ['nextStorage.8', 'text'],
    'trace bool text' => ['currentTrace.0.likeText', '1'],
    'trace real text' => ['currentTrace.1.likeText', '404'],
    'trace integer text' => ['currentTrace.2.likeText', '404'],
    'trace rtrim peer row5' => ['currentTrace.4.residualMatch', false],
    'trace matched row1' => ['currentTrace.5.residualMatch', true],
    'next trace false text' => ['nextTrace.0.likeText', '0'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason raw' => ['invalidationReasons.2', 'raw-like-text'],
    'reason bytes' => ['invalidationReasons.3', 'raw-like-bytes'],
    'reason rtrim key' => ['invalidationReasons.4', 'rtrim-collation-key'],
    'reason encoding' => ['invalidationReasons.5', 'text-encoding'],
    'reason storage' => ['invalidationReasons.6', 'storage-class'],
    'reason truth' => ['invalidationReasons.7', 'residual-truth'],
    'reason rowset' => ['invalidationReasons.8', 'matched-rowset'],
    'reason peers' => ['invalidationReasons.9', 'rtrim-peer-rejections'],
    'dependency tokenizer' => ['dependencies.0', 'sqlite-like-escape-tokenizer'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-collation-key'],
    'dependency residual' => ['dependencies.2', 'sqlite-like-residual-raw-text'],
    'dependency utf' => ['dependencies.3', 'sqlite-mixed-utf-source-decoder'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-next250'],
];

foreach ($cases250 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source next250 ' . $name] = static function (TestRunner $t) use ($plan250, $valueAt250, $path, $expected): void {
        $t->same($expected, $valueAt250($plan250(), $path));
    };
}

$tests['encoding collation affinity like current source next250 stable cursor reusable'] = static function (TestRunner $t) use ($current250, $plan250): void {
    $stable = $plan250(current: $current250, next: $current250, currentSource: 'same', nextSource: 'same', currentCookie: 250, nextCookie: 250);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source next250 wildcard admits trailing spaces after raw prefix'] = static function (TestRunner $t) use ($plan250): void {
    $plan = $plan250(pattern: 'plugin!_cache%', escape: '!');
    $t->same([4, 5, 1, 2, 3, 6], $plan['currentMatchedRowids']);
    $t->same([], $plan['currentRtrimPeerRejectedRowids']);
};

$tests['encoding collation affinity like current source next250 rtrim equality differs from like residual'] = static function (TestRunner $t): void {
    $t->same(0, PortLibs\LibSqlite\SQLiteAffinityComparison::compare('plugin_cache  ', 'plugin_cache', 'TEXT', 'TEXT', 'RTRIM'));
    $t->same(false, SQLiteDatabase::likeMatches('plugin_cache  ', 'plugin!_cache', '!', false));
    $t->same(true, SQLiteDatabase::likeMatches('Plugin_Cache', 'plugin!_cache', '!', false));
};

$tests['encoding collation affinity like current source next250 case sensitive binary residual rejects uppercase'] = static function (TestRunner $t) use ($plan250): void {
    $plan = $plan250(pattern: 'plugin!_cache', escape: '!', caseSensitive: true);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same(false, $plan['asciiNoCaseLikeStillFoldsAscii']);
    $t->same(false, $plan['currentTrace'][3]['residualMatch']);
};

$tests['encoding collation affinity like current source next250 numeric text affinity participates'] = static function (TestRunner $t) use ($plan250): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 404],
        ['option_id' => 2, 'option_name' => 404.0],
        ['option_id' => 3, 'option_name' => false],
    ];
    $plan = $plan250(current: $rows, next: $rows, pattern: '404', escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1, 2], $plan['currentMatchedRowids']);
    $t->same('integer', $plan['currentStorage'][1]);
    $t->same('real', $plan['currentStorage'][2]);
};

$tests['encoding collation affinity like current source next250 blob and null stay outside candidates'] = static function (TestRunner $t) use ($plan250): void {
    $rows = [
        ['option_id' => 1, 'option_name' => new SQLiteBlobValue('plugin_cache')],
        ['option_id' => 2, 'option_name' => null],
    ];
    $plan = $plan250(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([], $plan['currentCandidateRowids']);
    $t->same([], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source next250 rejects malformed utf8 string'] = static function (TestRunner $t) use ($next250): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressRtrimLikeResidualSourcePlan([['option_id' => 1, 'option_name' => "plugin_cache\xc3"]], $next250, 'plugin%'));
};

$tests['encoding collation affinity like current source next250 rejects missing option name'] = static function (TestRunner $t) use ($next250): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressRtrimLikeResidualSourcePlan([['option_id' => 1]], $next250, 'plugin%'));
};

$tests['encoding collation affinity like current source next250 rejects array option name'] = static function (TestRunner $t) use ($next250): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressRtrimLikeResidualSourcePlan([['option_id' => 1, 'option_name' => ['plugin']]], $next250, 'plugin%'));
};

$tests['encoding collation affinity like current source next250 rejects invalid byte encoding'] = static function (TestRunner $t) use ($next250): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressRtrimLikeResidualSourcePlan([['option_id' => 1, 'option_name_bytes' => 'plugin', 'text_encoding' => 9]], $next250, 'plugin%'));
};

return $tests;
