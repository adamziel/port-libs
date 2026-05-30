<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;

$tests = [];

$current246 = [
    ['option_id' => 1, 'option_name' => 'plugin_literal_percent', 'option_value' => 'plugin_%enabled'],
    ['option_id' => 2, 'option_name' => 'plugin_wild_percent', 'option_value' => 'plugin_cacheenabled'],
    ['option_id' => 3, 'option_name' => 'plugin_literal_hash', 'option_value' => 'plugin#_enabled'],
    ['option_id' => 4, 'option_name' => 'plugin_literal_bang', 'option_value' => 'plugin!_enabled'],
    ['option_id' => 5, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_%ENABLED'],
    ['option_id' => 6, 'option_name' => 'plugin_spaces', 'option_value' => 'plugin_%enabled  '],
    ['option_id' => 7, 'option_name' => 'plugin_int_escape', 'option_value' => 1],
    ['option_id' => 8, 'option_name' => 'plugin_null', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_%enabled')],
    ['option_id' => 10, 'option_name' => 'plugin_bad_text', 'option_value' => "plugin_\xffenabled"],
];

$nextTwoFourSix = [
    ['option_id' => 1, 'option_name' => 'plugin_literal_percent', 'option_value' => 'plugin_%enabled'],
    ['option_id' => 2, 'option_name' => 'plugin_wild_percent', 'option_value' => 'plugin_cacheenabled'],
    ['option_id' => 3, 'option_name' => 'plugin_literal_hash', 'option_value' => 'plugin#_enabled'],
    ['option_id' => 4, 'option_name' => 'plugin_literal_bang', 'option_value' => 'plugin!_enabled'],
    ['option_id' => 5, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_%ENABLED'],
    ['option_id' => 6, 'option_name' => 'plugin_spaces', 'option_value' => 'plugin_%enabled'],
    ['option_id' => 7, 'option_name' => 'plugin_int_escape', 'option_value' => '1'],
    ['option_id' => 8, 'option_name' => 'plugin_null', 'option_value' => null],
    ['option_id' => 9, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_%enabled')],
    ['option_id' => 10, 'option_name' => 'plugin_bad_text', 'option_value' => "plugin_\xffenabled"],
    ['option_id' => 11, 'option_name' => 'plugin_new_literal', 'option_value' => 'plugin_%enabled_extra'],
    ['option_id' => 12, 'option_name' => 'plugin_new_upper', 'option_value' => 'Plugin_%Enabled_extra'],
];

$plan246 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_%',
    mixed $currentEscape = '!',
    mixed $nextEscape = '#',
    string $collation = 'NOCASE',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@245',
    string $nextSource = 'main.wp_options@246',
    int $currentCookie = 245,
    int $nextCookie = 246,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationDynamicEscapeLikePlan(
    $current ?? $current246,
    $next ?? $nextTwoFourSix,
    $pattern,
    $currentEscape,
    $nextEscape,
    $collation,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt246 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases246 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_value COLLATE NOCASE LIKE ? ESCAPE dynamic_escape /* ESCAPE text affinity current-source fence */'],
    'pattern' => ['pattern', 'plugin!_%'],
    'pattern hex' => ['patternHex', '706C7567696E215F25'],
    'collation' => ['collation', 'NOCASE'],
    'case flag' => ['caseSensitiveLike', false],
    'current escape text' => ['currentEscape.escape', '!'],
    'current escape hex' => ['currentEscape.escapeHex', '21'],
    'current escape storage' => ['currentEscape.storage', 'text'],
    'current escape known' => ['currentEscape.unknown', false],
    'next escape text' => ['nextEscape.escape', '#'],
    'next escape hex' => ['nextEscape.escapeHex', '23'],
    'next escape storage' => ['nextEscape.storage', 'text'],
    'next escape known' => ['nextEscape.unknown', false],
    'current source' => ['currentSource', 'main.wp_options@245'],
    'next source' => ['nextSource', 'main.wp_options@246'],
    'current cookie' => ['currentSchemaCookie', 245],
    'next cookie' => ['nextSchemaCookie', 246],
    'current matched' => ['currentMatchedRowids', [1, 5, 6, 2]],
    'next matched' => ['nextMatchedRowids', [4]],
    'retained' => ['retainedRowids', []],
    'entered' => ['enteredRowids', [4]],
    'exited' => ['exitedRowids', [1, 5, 6, 2]],
    'current unknown' => ['currentUnknownRowids', [8, 9]],
    'next unknown' => ['nextUnknownRowids', [8, 9]],
    'current malformed' => ['currentMalformedRowids', [10]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'changed storage' => ['changedStorageRowids', [7, 11, 12]],
    'changed text' => ['changedLikeTextRowids', [6, 11, 12]],
    'changed key' => ['changedCollationKeyRowids', [6, 11, 12]],
    'changed residual' => ['changedResidualRowids', [1, 2, 4, 5, 6, 11, 12]],
    'trace upper key' => ['currentTrace.4.collationKey', 'plugin_%enabled'],
    'trace literal text' => ['currentTrace.3.likeText', 'plugin_%enabled'],
    'trace spaces text' => ['currentTrace.5.likeText', 'plugin_%enabled  '],
    'trace spaces next text' => ['nextTrace.3.likeText', 'plugin_%enabled'],
    'current error malformed' => ['currentErrors.10', 'SQLite dynamic ESCAPE LIKE nextTwoFourSix option_value text is malformed UTF-8'],
    'next error malformed' => ['nextErrors.10', 'SQLite dynamic ESCAPE LIKE nextTwoFourSix option_value text is malformed UTF-8'],
    'affinity flag' => ['dynamicEscapeUsesTextAffinity', true],
    'escape length flag' => ['escapeMustBeOneSqlCharacter', true],
    'rebind flag' => ['escapeRebindInvalidatesCursor', true],
    'null escape flag' => ['nullEscapeMakesLikeUnknown', true],
    'blob escape flag' => ['blobEscapeIsNotTextAffinityInput', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason escape' => ['invalidationReasons.2', 'escape-affinity'],
    'reason storage' => ['invalidationReasons.3', 'storage-class'],
    'reason text' => ['invalidationReasons.4', 'like-text'],
    'reason key' => ['invalidationReasons.5', 'collation-key'],
    'reason residual' => ['invalidationReasons.6', 'residual-result'],
    'reason matched' => ['invalidationReasons.7', 'matched-rowset'],
    'reason malformed' => ['invalidationReasons.8', 'malformed-text'],
    'dependency escape' => ['dependencies.0', 'sqlite-like-dynamic-escape-affinity'],
    'dependency residual' => ['dependencies.1', 'sqlite-like-residual'],
    'dependency nocase' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFourSix'],
];

foreach ($cases246 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourSix ' . $name] = static function (TestRunner $t) use ($plan246, $valueAt246, $path, $expected): void {
        $t->same($expected, $valueAt246($plan246(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourSix stable cursor reusable'] = static function (TestRunner $t) use ($current246, $plan246): void {
    $rows = array_values(array_filter($current246, static fn (array $row): bool => ($row['option_id'] ?? null) !== 10));
    $stable = $plan246(current: $rows, next: $rows, currentEscape: '!', nextEscape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 246, nextCookie: 246);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
    $t->same([1, 5, 6, 2], $stable['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSix numeric escape is text affinity'] = static function (TestRunner $t) use ($plan246): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'cache_%hit'],
        ['option_id' => 2, 'option_value' => 'cache1_hit'],
        ['option_id' => 3, 'option_value' => 'cache__hit'],
    ];
    $plan = $plan246(current: $rows, next: $rows, pattern: 'cache1_%', currentEscape: 1, nextEscape: 1, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same('integer', $plan['currentEscape']['storage']);
    $t->same('31', $plan['currentEscape']['escapeHex']);
    $t->same([1, 3], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSix boolean escape can quote percent'] = static function (TestRunner $t) use ($plan246): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'cache%hit'],
        ['option_id' => 2, 'option_value' => 'cacheXhit'],
    ];
    $plan = $plan246(current: $rows, next: $rows, pattern: 'cache1%%', currentEscape: true, nextEscape: true, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same('integer', $plan['currentEscape']['storage']);
    $t->same([1], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSix null escape makes all rows unknown'] = static function (TestRunner $t) use ($plan246): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_%enabled'],
        ['option_id' => 2, 'option_value' => 'plugin_cacheenabled'],
    ];
    $plan = $plan246(current: $rows, next: $rows, currentEscape: null, nextEscape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1, 2], $plan['currentUnknownRowids']);
    $t->same([], $plan['currentMatchedRowids']);
    $t->same(true, $plan['currentEscape']['unknown']);
};

$tests['encoding collation affinity like current source nextTwoFourSix blob escape reports malformed escape'] = static function (TestRunner $t) use ($plan246, $current246): void {
    $plan = $plan246(current: $current246, next: $current246, currentEscape: new SQLiteBlobValue('!'), nextEscape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same('blob', $plan['currentEscape']['storage']);
    $t->same('21', $plan['currentEscape']['escapeHex']);
    $t->same('SQLite dynamic ESCAPE LIKE nextTwoFourSix current ESCAPE is BLOB, not text', $plan['currentEscape']['error']);
    $t->same(true, in_array('escape-malformed', $plan['invalidationReasons'], true));
};

$tests['encoding collation affinity like current source nextTwoFourSix multi character escape reports malformed escape'] = static function (TestRunner $t) use ($plan246, $current246): void {
    $plan = $plan246(current: $current246, next: $current246, currentEscape: '!!', nextEscape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same('2121', $plan['currentEscape']['escapeHex']);
    $t->same('SQLite dynamic ESCAPE LIKE nextTwoFourSix current ESCAPE must be one SQLite character after affinity', $plan['currentEscape']['error']);
    $t->same(true, in_array('escape-malformed', $plan['invalidationReasons'], true));
};

$tests['encoding collation affinity like current source nextTwoFourSix multibyte escape is one sqlite character'] = static function (TestRunner $t) use ($plan246): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin_%enabled'],
        ['option_id' => 2, 'option_value' => 'pluginé_enabled'],
    ];
    $plan = $plan246(current: $rows, next: $rows, pattern: 'pluginé_%', currentEscape: 'é', nextEscape: 'é', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same('C3A9', $plan['currentEscape']['escapeHex']);
    $t->same([1], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSix case sensitive excludes uppercase'] = static function (TestRunner $t) use ($plan246): void {
    $plan = $plan246(caseSensitive: true, currentEscape: '!', nextEscape: '!');
    $t->same('NOCASE', $plan['collation']);
    $t->same([1, 6, 2], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourSix binary collation sort key preserves case'] = static function (TestRunner $t) use ($plan246): void {
    $plan = $plan246(collation: 'BINARY', currentEscape: '!', nextEscape: '!');
    $t->same('BINARY', $plan['collation']);
    $t->same('PLUGIN_%ENABLED', $plan['currentTrace'][1]['collationKey']);
};

$tests['encoding collation affinity like current source nextTwoFourSix rejects invalid collation'] = static function (TestRunner $t) use ($plan246): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan246(collation: 'UNICODE'));
};

$tests['encoding collation affinity like current source nextTwoFourSix rejects missing option value'] = static function (TestRunner $t) use ($plan246): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan246(current: [['option_id' => 1]], next: []));
};

$tests['encoding collation affinity like current source nextTwoFourSix rejects nonscalar option value'] = static function (TestRunner $t) use ($plan246): void {
    $plan = $plan246(current: [['option_id' => 1, 'option_value' => ['bad']]], next: []);
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite dynamic ESCAPE LIKE nextTwoFourSix option_value must be scalar text-affinity input', $plan['currentErrors'][1]);
};

return $tests;
