<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc249 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current249 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-16LE'],
    ['option_id' => 2, 'option_name' => 'plugin_cache  ', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 3, 'option_name' => 'plugin_cache ', 'text_encoding' => 'UTF-8'],
    ['option_id' => 4, 'option_name' => 'plugin_cache_more', 'text_encoding' => 'UTF-16LE'],
    ['option_id' => 5, 'option_name' => 'Plugin_Cache', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 6, 'option_name' => 'theme_cache', 'text_encoding' => 'UTF-8'],
    ['option_id' => 7, 'option_name' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 8, 'option_name' => null],
    ['option_id' => 9, 'option_name_bytes' => $enc249('plugin_cache', 'UTF-16LE'), 'text_encoding' => 2],
];

$next249 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache ', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 2, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 3, 'option_name' => 'plugin_cache  ', 'text_encoding' => 'UTF-16LE'],
    ['option_id' => 4, 'option_name' => 'plugin_cache_more', 'text_encoding' => 'UTF-16LE'],
    ['option_id' => 5, 'option_name' => 'Plugin_Cache', 'text_encoding' => 'UTF-16BE'],
    ['option_id' => 9, 'option_name_bytes' => $enc249('plugin_cache', 'UTF-16BE'), 'text_encoding' => 3],
    ['option_id' => 10, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-8'],
    ['option_id' => 11, 'option_name' => false],
];

$plan249 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@248',
    string $nextSource = 'main.wp_options@249',
    int $currentCookie = 248,
    int $nextCookie = 249,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan::wordpressRtrimLikeSourcePlan(
    $current ?? $current249,
    $next ?? $next249,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt249 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases249 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-next249'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE RTRIM LIKE ? ESCAPE ? /* RTRIM range, LIKE residual */'],
    'pattern hex' => ['patternHex', '706c7567696e215f6361636865'],
    'pattern tokens' => ['patternTokenHex', ['70', '6c', '75', '67', '69', '6e', '21', '5f', '63', '61', '63', '68', '65']],
    'pattern characters' => ['patternCharacters', 13],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'RTRIM'],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix hex' => ['prefixHex', '706c7567696e5f6361636865'],
    'prefix tokens' => ['prefixTokenHex', ['70', '6c', '75', '67', '69', '6e', '5f', '63', '61', '63', '68', '65']],
    'prefix characters' => ['prefixCharacters', 12],
    'prefix ascii' => ['prefixIsAscii', true],
    'has wildcard false' => ['hasWildcard', false],
    'range lower' => ['rtrimRangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rtrimRangeUpperBound', 'plugin_cachf'],
    'range lower hex' => ['rtrimRangeLowerHex', '706c7567696e5f6361636865'],
    'range upper hex' => ['rtrimRangeUpperHex', '706c7567696e5f6361636866'],
    'current source' => ['currentSource', 'main.wp_options@248'],
    'next source' => ['nextSource', 'main.wp_options@249'],
    'current cookie' => ['currentSchemaCookie', 248],
    'next cookie' => ['nextSchemaCookie', 249],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 9, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 9, 10, 4]],
    'retained candidates' => ['retainedCandidateRowids', [1, 2, 3, 9, 4]],
    'exited candidates' => ['exitedCandidateRowids', []],
    'entered candidates' => ['enteredCandidateRowids', [10]],
    'current matched' => ['currentMatchedRowids', [1, 9]],
    'next matched' => ['nextMatchedRowids', [2, 9, 10]],
    'retained matched' => ['retainedMatchedRowids', [9]],
    'exited matched' => ['exitedMatchedRowids', [1]],
    'entered matched' => ['enteredMatchedRowids', [2, 10]],
    'current rejected' => ['currentRtrimResidualRejectedRowids', [2, 3, 4]],
    'next rejected' => ['nextRtrimResidualRejectedRowids', [1, 3, 4]],
    'changed bytes' => ['changedEncodedBytesRowids', [1, 2, 3, 9]],
    'changed encodings' => ['changedEncodingRowids', [1, 3, 9]],
    'changed residual' => ['changedResidualRowids', [1, 2]],
    'current name one' => ['currentNames.1', 'plugin_cache'],
    'current name two' => ['currentNames.2', 'plugin_cache  '],
    'next name one' => ['nextNames.1', 'plugin_cache '],
    'next name ten' => ['nextNames.10', 'plugin_cache'],
    'current key one' => ['currentKeyBytesHex.1', '70006c007500670069006e005f0063006100630068006500'],
    'next key one' => ['nextKeyBytesHex.1', '0070006c007500670069006e005f006300610063006800650020'],
    'current encoding one' => ['currentEncodings.1', 'UTF-16LE'],
    'next encoding one' => ['nextEncodings.1', 'UTF-16BE'],
    'current residual one' => ['currentResidualMatches.1', true],
    'next residual one' => ['nextResidualMatches.1', false],
    'current residual two' => ['currentResidualMatches.2', false],
    'next residual two' => ['nextResidualMatches.2', true],
    'current position one' => ['currentPositions.1', 0],
    'next position ten' => ['nextPositions.10', 6],
    'rtrim admits padded' => ['rtrimRangeMayAdmitPaddedKeys', true],
    'like ignores rtrim' => ['likeResidualDoesNotUseRtrimCollation', true],
    'escaped underscore literal' => ['escapedUnderscoreIsLiteralPrefix', true],
    'mixed utf flag' => ['utf16LeAndBeKeysCompareAfterDecode', true],
    'blob null flag' => ['blobAndNullStayOutsideEncodedCursor', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason candidate' => ['invalidationReasons.2', 'candidate-rowset'],
    'reason matched' => ['invalidationReasons.3', 'matched-rowset'],
    'reason residual' => ['invalidationReasons.4', 'rtrim-like-residual'],
    'reason bytes' => ['invalidationReasons.5', 'encoded-bytes'],
    'reason encoding' => ['invalidationReasons.6', 'text-encoding'],
    'dependency cursor' => ['dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-collation-range'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next249'],
];

foreach ($cases249 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source next249 ' . $name] = static function (TestRunner $t) use ($plan249, $valueAt249, $path, $expected): void {
        $t->same($expected, $valueAt249($plan249(), $path));
    };
}

$tests['encoding collation affinity like current source next249 stable exact cursor reusable'] = static function (TestRunner $t) use ($plan249): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_cache', 'text_encoding' => 'UTF-16LE'],
        ['option_id' => 2, 'option_name' => 'plugin_cache  ', 'text_encoding' => 'UTF-16BE'],
    ];
    $stable = $plan249(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 249, nextCookie: 249);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
    $t->same([1, 2], $stable['currentCandidateRowids']);
    $t->same([1], $stable['currentMatchedRowids']);
    $t->same([2], $stable['currentRtrimResidualRejectedRowids']);
};

$tests['encoding collation affinity like current source next249 wildcard absorbs trailing spaces'] = static function (TestRunner $t) use ($plan249): void {
    $wild = $plan249(pattern: 'plugin!_cache%', escape: '!');
    $t->same([1, 2, 3, 9, 4], $wild['currentMatchedRowids']);
    $t->same([1, 2, 3, 9, 10, 4], $wild['nextMatchedRowids']);
    $t->same([], $wild['currentRtrimResidualRejectedRowids']);
    $t->same([], $wild['nextRtrimResidualRejectedRowids']);
};

$tests['encoding collation affinity like current source next249 binary case remains outside rtrim range'] = static function (TestRunner $t) use ($plan249): void {
    $plan = $plan249();
    $t->same(false, in_array(5, $plan['currentCandidateRowids'], true));
    $t->same(false, in_array(5, $plan['nextCandidateRowids'], true));
};

$tests['encoding collation affinity like current source next249 direct like proves rtrim contrast'] = static function (TestRunner $t): void {
    $t->same(true, SQLiteDatabase::likeMatches('plugin_cache', 'plugin!_cache', '!'));
    $t->same(false, SQLiteDatabase::likeMatches('plugin_cache ', 'plugin!_cache', '!'));
    $t->same(true, SQLiteDatabase::likeMatches('plugin_cache ', 'plugin!_cache%', '!'));
};

$tests['encoding collation affinity like current source next249 skips blob null but keeps scalar false outside range'] = static function (TestRunner $t) use ($plan249): void {
    $plan = $plan249();
    $t->same(false, in_array(7, $plan['currentCandidateRowids'], true));
    $t->same(false, in_array(8, $plan['currentCandidateRowids'], true));
    $t->same(false, in_array(11, $plan['nextCandidateRowids'], true));
};

$tests['encoding collation affinity like current source next249 rejects multi character escape'] = static function (TestRunner $t) use ($current249, $next249): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan::wordpressRtrimLikeSourcePlan($current249, $next249, 'plugin!!_cache', '!!'));
};

$tests['encoding collation affinity like current source next249 rejects missing option name'] = static function (TestRunner $t) use ($next249): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan::wordpressRtrimLikeSourcePlan([['option_id' => 1]], $next249));
};

$tests['encoding collation affinity like current source next249 rejects invalid encoded bytes'] = static function (TestRunner $t) use ($next249): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan::wordpressRtrimLikeSourcePlan([['option_id' => 1, 'option_name_bytes' => 'p', 'text_encoding' => 2]], $next249));
};

$tests['encoding collation affinity like current source next249 rejects non scalar option name'] = static function (TestRunner $t) use ($next249): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan::wordpressRtrimLikeSourcePlan([['option_id' => 1, 'option_name' => ['plugin']]], $next249));
};

$tests['encoding collation affinity like current source next249 note fields stay explicit'] = static function (TestRunner $t) use ($plan249): void {
    $plan = $plan249();
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
    $t->true(str_contains($plan['non_overlap'], 'RTRIM-collation LIKE range admission'));
    $t->true(str_contains($plan['non_overlap'], 'next245 dangling ESCAPE'));
};

return $tests;
