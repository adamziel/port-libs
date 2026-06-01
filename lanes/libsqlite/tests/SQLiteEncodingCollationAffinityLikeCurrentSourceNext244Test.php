<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$row244 = static fn (int $id, string $name, string $encoding = 'UTF-8'): array => [
    'setting_id' => $id,
    'key_name' => $name,
    'text_encoding' => $encoding,
];

$current244 = [
    $row244(1, 'plugin_café_main', 'UTF-16LE'),
    $row244(2, 'PLUGIN_café_aux', 'UTF-16BE'),
    $row244(3, 'plugin_cafÉ_caps', 'UTF-8'),
    $row244(4, 'PLUGIN_CAFÉ_MAIN', 'UTF-16LE'),
    $row244(5, 'plugin_café ', 'UTF-16BE'),
    $row244(6, 'plugin_café  ', 'UTF-8'),
    $row244(7, 'theme_café_main', 'UTF-16LE'),
    ['setting_id' => 8, 'key_name' => new SQLiteBlobValue('plugin_café_blob'), 'text_encoding' => 'UTF-8'],
    ['setting_id' => 9, 'key_name' => null, 'text_encoding' => 'UTF-8'],
    ['setting_id' => 10, 'key_name' => 42, 'text_encoding' => 'UTF-8'],
];

$nextTwoFourFour = [
    $row244(1, 'plugin_café_main', 'UTF-16BE'),
    $row244(2, 'PLUGIN_café_aux', 'UTF-16LE'),
    $row244(3, 'plugin_cafÉ_caps', 'UTF-8'),
    $row244(4, 'PLUGIN_CAFÉ_MAIN', 'UTF-16BE'),
    $row244(5, 'plugin_café', 'UTF-16LE'),
    $row244(6, 'plugin_café   ', 'UTF-16BE'),
    $row244(11, 'PLUGIN_café_new', 'UTF-16LE'),
    $row244(12, 'plugin_café_archive', 'UTF-8'),
    ['setting_id' => 13, 'key_name' => false, 'text_encoding' => 'UTF-8'],
];

$plan244 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_%café%',
    ?string $escape = '!',
    bool $caseSensitive = false,
    string $collation = 'NOCASE',
    string $currentSource = 'main.app_settings@243',
    string $nextSource = 'main.app_settings@244',
    int $currentCookie = 243,
    int $nextCookie = 244,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan(
    $current ?? $current244,
    $next ?? $nextTwoFourFour,
    $pattern,
    $escape,
    $caseSensitive,
    $collation,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt244 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases244 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFourFour'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'key_name COLLATE NOCASE LIKE ? ESCAPE ? /* mixed UTF source cursor */'],
    'pattern' => ['pattern', 'plugin!_%café%'],
    'pattern hex' => ['patternHex', '706c7567696e215f25636166c3a925'],
    'escape' => ['escape', '!'],
    'escape hex' => ['escapeHex', '21'],
    'case flag' => ['caseSensitiveLike', false],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', 'plugin_'],
    'prefix hex' => ['prefixHex', '706c7567696e5f'],
    'prefix chars' => ['prefixCharacters', 7],
    'prefix ascii false' => ['prefixIsAscii', true],
    'has wildcard' => ['hasWildcard', true],
    'binary lower' => ['binaryRange.lowerInclusive', 'plugin_'],
    'binary upper' => ['binaryRange.upperBound', 'plugin`'],
    'nocase lower' => ['noCaseRange.lowerInclusive', 'plugin_'],
    'nocase upper' => ['noCaseRange.upperBound', 'plugin`'],
    'current source' => ['currentSource', 'main.app_settings@243'],
    'next source' => ['nextSource', 'main.app_settings@244'],
    'current cookie' => ['currentSchemaCookie', 243],
    'next cookie' => ['nextSchemaCookie', 244],
    'current candidates' => ['currentCandidateRowids', [3, 4, 5, 6, 2, 1]],
    'next candidates' => ['nextCandidateRowids', [3, 4, 5, 6, 12, 2, 1, 11]],
    'current matched' => ['currentMatchedRowids', [5, 6, 2, 1]],
    'next matched' => ['nextMatchedRowids', [5, 6, 12, 2, 1, 11]],
    'current rejected' => ['currentResidualRejectedRowids', [3, 4]],
    'next rejected' => ['nextResidualRejectedRowids', [3, 4]],
    'retained' => ['retainedRowids', [5, 6, 2, 1]],
    'exited' => ['exitedRowids', []],
    'entered' => ['enteredRowids', [12, 11]],
    'changed bytes' => ['changedEncodedBytesRowids', [5, 6, 2, 1]],
    'changed encodings' => ['changedEncodingRowids', [5, 6, 2, 1]],
    'current name 1' => ['currentNames.1', 'plugin_café_main'],
    'current name 2 ascii nocase' => ['currentNames.2', 'PLUGIN_café_aux'],
    'next name 12' => ['nextNames.12', 'plugin_café_archive'],
    'current hex utf16le' => ['currentKeyBytesHex.1', '70006c007500670069006e005f00630061006600e9005f006d00610069006e00'],
    'next hex utf16be' => ['nextKeyBytesHex.1', '0070006c007500670069006e005f00630061006600e9005f006d00610069006e'],
    'current encoding 1' => ['currentEncodings.1', 'UTF-16LE'],
    'next encoding 1' => ['nextEncodings.1', 'UTF-16BE'],
    'accent flag' => ['asciiNoCaseDoesNotFoldAccents', true],
    'utf16 flag' => ['utf16LeAndBeKeysCompareAfterDecode', true],
    'rtrim flag' => ['likeIgnoresRtrimCollationForResidual', true],
    'affinity skip flag' => ['blobAndNullStayOutsideTextAffinityScan', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason bytes' => ['invalidationReasons.3', 'encoded-bytes'],
    'reason encoding' => ['invalidationReasons.4', 'text-encoding'],
    'dependency cursor' => ['dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency collation' => ['dependencies.2', 'sqlite-nocase-ascii-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFourFour'],
];

foreach ($cases244 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFourFour ' . $name] = static function (TestRunner $t) use ($plan244, $valueAt244, $path, $expected): void {
        $t->same($expected, $valueAt244($plan244(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFourFour stable cursor reusable'] = static function (TestRunner $t) use ($current244, $plan244): void {
    $stable = $plan244(current: $current244, next: $current244, currentSource: 'same', nextSource: 'same', currentCookie: 244, nextCookie: 244);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourFour case sensitive excludes uppercase ascii prefix'] = static function (TestRunner $t) use ($plan244): void {
    $case = $plan244(caseSensitive: true, collation: 'BINARY');
    $t->same([5, 6, 1], $case['currentMatchedRowids']);
    $t->same([5, 6, 12, 1], $case['nextMatchedRowids']);
    $t->same('BINARY', $case['collation']);
};

$tests['encoding collation affinity like current source nextTwoFourFour uppercase accent is not nocase folded'] = static function (TestRunner $t) use ($plan244): void {
    $plan = $plan244(pattern: 'plugin!_%cafÉ%', escape: '!');
    $t->same([3, 4], $plan['currentMatchedRowids']);
    $t->same([3, 4], $plan['nextMatchedRowids']);
    $t->same(false, in_array(1, $plan['currentMatchedRowids'], true));
};

$tests['encoding collation affinity like current source nextTwoFourFour escaped underscore differs from wildcard underscore'] = static function (TestRunner $t) use ($plan244): void {
    $rows = [
        $row = ['setting_id' => 1, 'key_name' => 'plugin_café_main', 'text_encoding' => 'UTF-8'],
        ['setting_id' => 2, 'key_name' => 'plugin-café-main', 'text_encoding' => 'UTF-8'],
    ];
    $literal = $plan244(current: $rows, next: $rows, pattern: 'plugin!_%café%', escape: '!', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $wild = $plan244(current: $rows, next: $rows, pattern: 'plugin_%café%', escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1], $literal['currentMatchedRowids']);
    $t->same([2, 1], $wild['currentMatchedRowids']);
    $t->same('plugin_café_main', $row['key_name']);
};

$tests['encoding collation affinity like current source nextTwoFourFour rtrim collation range still keeps like residual significant spaces'] = static function (TestRunner $t) use ($plan244): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => 'plugin_café', 'text_encoding' => 'UTF-8'],
        ['setting_id' => 2, 'key_name' => 'plugin_café ', 'text_encoding' => 'UTF-8'],
        ['setting_id' => 3, 'key_name' => 'plugin_café  ', 'text_encoding' => 'UTF-8'],
    ];
    $plan = $plan244(current: $rows, next: $rows, pattern: 'plugin!_café ', escape: '!', collation: 'RTRIM', currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([], $plan['currentCandidateRowids']);
    $t->same([], $plan['currentMatchedRowids']);
    $t->same([], $plan['currentResidualRejectedRowids']);
    $t->same([], $plan['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFourFour scalar text affinity encodes integers and booleans'] = static function (TestRunner $t) use ($plan244): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => 42, 'text_encoding' => 'UTF-16LE'],
        ['setting_id' => 2, 'key_name' => true, 'text_encoding' => 'UTF-16BE'],
        ['setting_id' => 3, 'key_name' => false, 'text_encoding' => 'UTF-8'],
    ];
    $num = $plan244(current: $rows, next: $rows, pattern: '4_', escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $bool = $plan244(current: $rows, next: $rows, pattern: '1', escape: null, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1], $num['currentMatchedRowids']);
    $t->same([2], $bool['currentMatchedRowids']);
    $t->same('34003200', $num['currentKeyBytesHex'][1]);
};

$tests['encoding collation affinity like current source nextTwoFourFour blob and null stay outside scan'] = static function (TestRunner $t) use ($plan244): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => new SQLiteBlobValue('plugin_café_blob'), 'text_encoding' => 'UTF-8'],
        ['setting_id' => 2, 'key_name' => null, 'text_encoding' => 'UTF-8'],
    ];
    $plan = $plan244(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoFourFour explicit byte rows are decoded'] = static function (TestRunner $t) use ($plan244): void {
    $rows = [
        ['setting_id' => 1, 'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('plugin_café_bytes', 'UTF-16BE'), 'text_encoding' => 3],
    ];
    $plan = $plan244(current: $rows, next: $rows, currentSource: 'same', nextSource: 'same', currentCookie: 1, nextCookie: 1);
    $t->same([1], $plan['currentMatchedRowids']);
    $t->same('UTF-16BE', $plan['currentEncodings'][1]);
};

$tests['encoding collation affinity like current source nextTwoFourFour direct like proves ascii only accent behavior'] = static function (TestRunner $t): void {
    $t->same(true, SQLiteDatabase::likeMatches('PLUGIN_café_aux', 'plugin!_%café%', '!'));
    $t->same(false, SQLiteDatabase::likeMatches('PLUGIN_CAFÉ_MAIN', 'plugin!_%café%', '!'));
};

$tests['encoding collation affinity like current source nextTwoFourFour rejects missing key name'] = static function (TestRunner $t) use ($nextTwoFourFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan([['setting_id' => 1]], $nextTwoFourFour, 'plugin%'));
};

$tests['encoding collation affinity like current source nextTwoFourFour rejects array key name'] = static function (TestRunner $t) use ($nextTwoFourFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan([['setting_id' => 1, 'key_name' => ['plugin']]], $nextTwoFourFour, 'plugin%'));
};

$tests['encoding collation affinity like current source nextTwoFourFour rejects invalid encoding'] = static function (TestRunner $t) use ($nextTwoFourFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan([['setting_id' => 1, 'key_name' => 'plugin', 'text_encoding' => 'UTF-32']], $nextTwoFourFour, 'plugin%'));
};

$tests['encoding collation affinity like current source nextTwoFourFour rejects invalid collation'] = static function (TestRunner $t) use ($current244, $nextTwoFourFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan($current244, $nextTwoFourFour, 'plugin%', null, false, 'UNICODE'));
};

$tests['encoding collation affinity like current source nextTwoFourFour rejects multi character escape'] = static function (TestRunner $t) use ($current244, $nextTwoFourFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationUtf16KeyNameLikePlan($current244, $nextTwoFourFour, 'plugin!!_%', '!!'));
};

return $tests;
