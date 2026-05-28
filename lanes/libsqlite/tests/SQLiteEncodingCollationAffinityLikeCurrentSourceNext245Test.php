<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan;

$tests = [];

$current245 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 3, 'option_name' => 'plugin_cache!'],
    ['option_id' => 4, 'option_name' => 'plugin_cache_more'],
    ['option_id' => 5, 'option_name' => 'pluginXcache'],
    ['option_id' => 6, 'option_name' => 'theme_cache'],
    ['option_id' => 7, 'option_name' => 404],
    ['option_id' => 8, 'option_name' => new SQLiteBlobValue('plugin_cache')],
    ['option_id' => 9, 'option_name' => null],
    ['option_id' => 10, 'option_name' => 'plugin_cache' . chr(0xc3)],
];

$next245 = [
    ['option_id' => 1, 'option_name' => 'plugin_cache2'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache'],
    ['option_id' => 3, 'option_name' => 'plugin_cache!'],
    ['option_id' => 4, 'option_name' => 'plugin_cache_more'],
    ['option_id' => 5, 'option_name' => 'pluginXcache'],
    ['option_id' => 10, 'option_name' => 'plugin_cache' . chr(0xc3)],
    ['option_id' => 11, 'option_name' => 'PLUGIN_CACHE'],
    ['option_id' => 12, 'option_name' => 'plugin_cache_new'],
    ['option_id' => 13, 'option_name' => false],
];

$plan245 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache!',
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@244',
    string $nextSource = 'main.wp_options@245',
    int $currentCookie = 244,
    int $nextCookie = 245,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan::wordpressDanglingEscapeLikePlan(
    $current ?? $current245,
    $next ?? $next245,
    $pattern,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt245 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases245 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-next245'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE NOCASE LIKE ? ESCAPE ? /* dangling ESCAPE residual */'],
    'pattern hex' => ['patternHex', '706c7567696e215f636163686521'],
    'pattern tokens' => ['patternTokenHex', ['70', '6c', '75', '67', '69', '6e', '21', '5f', '63', '61', '63', '68', '65', '21']],
    'pattern characters' => ['patternCharacters', 14],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix hex' => ['prefixHex', '706c7567696e5f6361636865'],
    'prefix tokens' => ['prefixTokenHex', ['70', '6c', '75', '67', '69', '6e', '5f', '63', '61', '63', '68', '65']],
    'prefix characters' => ['prefixCharacters', 12],
    'prefix ascii' => ['prefixIsAscii', true],
    'has wildcard false' => ['hasWildcard', false],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'range lower hex' => ['rangeLowerInclusiveHex', '706c7567696e5f6361636865'],
    'range upper hex' => ['rangeUpperBoundHex', '706c7567696e5f6361636866'],
    'current source' => ['currentSource', 'main.wp_options@244'],
    'next source' => ['nextSource', 'main.wp_options@245'],
    'current cookie' => ['currentSchemaCookie', 244],
    'next cookie' => ['nextSchemaCookie', 245],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4, 10]],
    'next candidates' => ['nextCandidateRowids', [2, 11, 3, 1, 4, 12, 10]],
    'retained candidates' => ['retainedCandidateRowids', [1, 2, 3, 4, 10]],
    'exited candidates' => ['exitedCandidateRowids', []],
    'entered candidates' => ['enteredCandidateRowids', [11, 12]],
    'current matched empty' => ['currentMatchedRowids', []],
    'next matched empty' => ['nextMatchedRowids', []],
    'current rejected' => ['currentResidualRejectedRowids', [1, 2, 3, 4, 10]],
    'next rejected' => ['nextResidualRejectedRowids', [2, 11, 3, 1, 4, 12, 10]],
    'changed bytes' => ['changedNameBytesRowids', [1]],
    'changed storage empty' => ['changedStorageRowids', []],
    'current unknown' => ['currentUnknownRowids', [8, 9]],
    'next unknown' => ['nextUnknownRowids', []],
    'current malformed' => ['currentMalformedRowids', [10]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'malformed hex' => ['currentMalformedHex.10', '706c7567696e5f6361636865c3'],
    'current name one' => ['currentNames.1', 'plugin_cache'],
    'current name three' => ['currentNames.3', 'plugin_cache!'],
    'next name eleven' => ['nextNames.11', 'PLUGIN_CACHE'],
    'current one hex' => ['currentNameHex.1', '706c7567696e5f6361636865'],
    'next one changed hex' => ['nextNameHex.1', '706c7567696e5f636163686532'],
    'current token one' => ['currentTokenHex.1', ['70', '6c', '75', '67', '69', '6e', '5f', '63', '61', '63', '68', '65']],
    'next token twelve' => ['nextTokenHex.12', ['70', '6c', '75', '67', '69', '6e', '5f', '63', '61', '63', '68', '65', '5f', '6e', '65', '77']],
    'current token count three' => ['currentTokenCounts.3', 13],
    'next token count twelve' => ['nextTokenCounts.12', 16],
    'current storage one' => ['currentStorage.1', 'text'],
    'next storage eleven' => ['nextStorage.11', 'text'],
    'dangling flag' => ['danglingEscapeMakesResidualFalse', true],
    'range residual flag' => ['rangeMayAdmitResidualRejectedRows', true],
    'escaped underscore flag' => ['escapedUnderscoreIsPrefixLiteral', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'unknown flag' => ['blobAndNullRemainUnknown', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['invalidationReasons.2', 'malformed-text'],
    'reason candidate' => ['invalidationReasons.3', 'candidate-rowset'],
    'reason bytes' => ['invalidationReasons.4', 'option-name-bytes'],
    'reason residual' => ['invalidationReasons.5', 'dangling-escape-residual'],
    'dependency residual' => ['dependencies.0', 'sqlite-like-dangling-escape-residual'],
    'dependency range' => ['dependencies.1', 'sqlite-like-escape-prefix-range'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next245'],
];

foreach ($cases245 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source next245 ' . $name] = static function (TestRunner $t) use ($plan245, $valueAt245, $path, $expected): void {
        $t->same($expected, $valueAt245($plan245(), $path));
    };
}

$tests['encoding collation affinity like current source next245 stable rejected cursor still invalidated by dangling residual'] = static function (TestRunner $t) use ($current245, $plan245): void {
    $stable = $plan245(current: $current245, next: $current245, currentSource: 'same', nextSource: 'same', currentCookie: 245, nextCookie: 245);
    $t->same(true, $stable['cursorInvalidated']);
    $t->same(false, $stable['cursorReusable']);
    $t->same(['malformed-text', 'dangling-escape-residual'], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source next245 clean stable cursor still reports residual invalidation'] = static function (TestRunner $t) use ($plan245): void {
    $rows = [
        ['option_id' => 1, 'option_name' => 'plugin_cache'],
        ['option_id' => 2, 'option_name' => 'plugin_cache_extra'],
    ];
    $stable = $plan245(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 245, nextCookie: 245);
    $t->same(['dangling-escape-residual'], $stable['invalidationReasons']);
    $t->same([1, 2], $stable['currentResidualRejectedRowids']);
};

$tests['encoding collation affinity like current source next245 completed escaped suffix can match literal bang'] = static function (TestRunner $t) use ($current245, $next245, $plan245): void {
    $complete = $plan245(current: $current245, next: $next245, pattern: 'plugin!_cache!!', escape: '!');
    $t->same([3], $complete['currentMatchedRowids']);
    $t->same([3], $complete['nextMatchedRowids']);
    $t->same(false, in_array('dangling-escape-residual', $complete['invalidationReasons'], true));
};

$tests['encoding collation affinity like current source next245 case sensitive keeps uppercase outside range'] = static function (TestRunner $t) use ($plan245): void {
    $case = $plan245(caseSensitive: true);
    $t->same('BINARY', $case['collation']);
    $t->same([1, 3, 4, 10], $case['currentCandidateRowids']);
    $t->same([3, 1, 4, 12, 10], $case['nextCandidateRowids']);
};

$tests['encoding collation affinity like current source next245 non dangling wildcard proves contrast'] = static function (TestRunner $t) use ($plan245): void {
    $wild = $plan245(pattern: 'plugin!_cache%', escape: '!');
    $t->same([1, 2, 3, 4, 10], $wild['currentMatchedRowids']);
    $t->same([2, 11, 3, 1, 4, 12, 10], $wild['nextMatchedRowids']);
    $t->same([], $wild['currentResidualRejectedRowids']);
};

$tests['encoding collation affinity like current source next245 blob null and scalar affinity split'] = static function (TestRunner $t) use ($plan245): void {
    $rows = [
        ['option_id' => 1, 'option_name' => new SQLiteBlobValue('plugin_cache')],
        ['option_id' => 2, 'option_name' => null],
        ['option_id' => 3, 'option_name' => 404],
        ['option_id' => 4, 'option_name' => 'plugin_cache'],
    ];
    $plan = $plan245(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1, 2], $plan['currentUnknownRowids']);
    $t->same([4], $plan['currentCandidateRowids']);
    $t->same('text', $plan['currentStorage'][4]);
};

$tests['encoding collation affinity like current source next245 direct dangling like returns false'] = static function (TestRunner $t): void {
    $t->same(false, SQLiteDatabase::likeMatches('plugin_cache', 'plugin!_cache!', '!'));
    $t->same(true, SQLiteDatabase::likeMatches('plugin_cache!', 'plugin!_cache!!', '!'));
};

$tests['encoding collation affinity like current source next245 rejects multi character escape'] = static function (TestRunner $t) use ($current245, $next245): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan::wordpressDanglingEscapeLikePlan($current245, $next245, 'plugin!!_cache!!', '!!'));
};

$tests['encoding collation affinity like current source next245 rejects missing option name'] = static function (TestRunner $t) use ($next245): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan::wordpressDanglingEscapeLikePlan([['option_id' => 1]], $next245));
};

$tests['encoding collation affinity like current source next245 rejects non scalar option name'] = static function (TestRunner $t) use ($next245): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan::wordpressDanglingEscapeLikePlan([['option_id' => 1, 'option_name' => ['plugin']]], $next245));
};

$tests['encoding collation affinity like current source next245 note fields stay explicit'] = static function (TestRunner $t) use ($plan245): void {
    $plan = $plan245();
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
    $t->true(str_contains($plan['non_overlap'], 'dangling ESCAPE LIKE residual'));
    $t->true(str_contains($plan['non_overlap'], 'next242 embedded-NUL'));
};

return $tests;
