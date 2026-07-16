<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['setting_id' => 1, 'key_name_bytes' => $enc('Module_Cache  ', 2), 'text_encoding' => 2],
    ['setting_id' => 2, 'key_name_bytes' => $enc('module_cache', 3), 'text_encoding' => 3],
    ['setting_id' => 3, 'key_name_bytes' => $enc("module_cache\t", 2), 'text_encoding' => 2],
    ['setting_id' => 4, 'key_name_bytes' => $enc('module_config_extra', 1), 'text_encoding' => 1],
    ['setting_id' => 5, 'key_name_bytes' => $enc('module_Æther', 2), 'text_encoding' => 2],
    ['setting_id' => 6, 'key_name_bytes' => $enc('module_æther', 2), 'text_encoding' => 2],
    ['setting_id' => 7, 'key_name_bytes' => $enc('plugout_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 8, 'key_name_bytes' => $enc('MODULE_TRANSIENT  ', 3), 'text_encoding' => 3],
    ['setting_id' => 9, 'key_name_bytes' => $enc('module:100%_cache  ', 2), 'text_encoding' => 2],
    ['setting_id' => 10, 'key_name_bytes' => "p\0x", 'text_encoding' => 2],
    ['setting_id' => 11, 'key_name_bytes' => "\x00\xd8A\0", 'text_encoding' => 2],
];

$nextRows = [
    ['setting_id' => 1, 'key_name_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 2, 'key_name_bytes' => $enc('Module_Cache  ', 3), 'text_encoding' => 3],
    ['setting_id' => 3, 'key_name_bytes' => $enc("module_cache\t", 2), 'text_encoding' => 2],
    ['setting_id' => 4, 'key_name_bytes' => $enc('module_config_extra_v2', 1), 'text_encoding' => 1],
    ['setting_id' => 5, 'key_name_bytes' => $enc('module_Æther', 3), 'text_encoding' => 3],
    ['setting_id' => 6, 'key_name_bytes' => $enc('module_æther  ', 2), 'text_encoding' => 2],
    ['setting_id' => 7, 'key_name_bytes' => $enc('plugout_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 8, 'key_name_bytes' => $enc('MODULE_TRANSIENT', 3), 'text_encoding' => 3],
    ['setting_id' => 9, 'key_name_bytes' => $enc('module:100%_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 12, 'key_name_bytes' => $enc('module_new_option  ', 3), 'text_encoding' => 3],
    ['setting_id' => 13, 'key_name_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$plan = static fn (
    string $pattern = 'module!_%',
    ?string $escape = '!',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@157',
    string $nextSource = 'main.app_settings@158',
    int $currentCookie = 157,
    int $nextCookie = 158,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourceDeltaPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneFiveEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'module!_%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'prefix' => ['prefix', 'module_'],
    'prefix characters' => ['prefixCharacters', 7],
    'prefix ascii' => ['prefixIsAscii', true],
    'index usable' => ['indexUsable', true],
    'no rejected reason' => ['rejectedReason', null],
    'range lower' => ['range.lowerInclusive', 'module_'],
    'range upper' => ['range.upperBound', 'module`'],
    'current source' => ['currentSource', 'main.app_settings@157'],
    'next source' => ['nextSource', 'main.app_settings@158'],
    'current cookie' => ['currentSchemaCookie', 157],
    'next cookie' => ['nextSchemaCookie', 158],
    'current order rowids' => ['currentOrderRowids', [9, 1, 2, 3, 4, 8, 5, 6, 7]],
    'next order rowids' => ['nextOrderRowids', [9, 1, 2, 3, 4, 12, 8, 5, 6, 7]],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4, 8, 5, 6]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 4, 12, 8, 5, 6]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4, 8, 5, 6]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 4, 12, 8, 5, 6]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'retained matched' => ['retainedMatchedRowids', [1, 2, 3, 4, 8, 5, 6]],
    'entered matched' => ['enteredMatchedRowids', [12]],
    'exited matched' => ['exitedMatchedRowids', []],
    'current malformed' => ['currentMalformedRowids', [10, 11]],
    'next malformed' => ['nextMalformedRowids', [13]],
    'odd length error' => ['currentErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'surrogate error' => ['currentErrors.11', 'SQLite encoding source UTF-16 text payload has an unpaired high surrogate'],
    'ending surrogate error' => ['nextErrors.13', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row one current text' => ['currentTexts.1', 'Module_Cache  '],
    'row one next text' => ['nextTexts.1', 'module_cache'],
    'row one current rtrim' => ['currentRtrimTexts.1', 'Module_Cache'],
    'row three rtrim keeps tab' => ['currentRtrimTexts.3', "module_cache\t"],
    'row eight next rtrim' => ['nextRtrimTexts.8', 'MODULE_TRANSIENT'],
    'row one nocase key' => ['currentNocaseKeys.1', 'module_cache'],
    'row five nocase ascii only' => ['currentNocaseKeys.5', 'module_Æther'],
    'row six nocase keeps lowercase ae' => ['currentNocaseKeys.6', 'module_æther'],
    'row one current encoding' => ['currentEncodings.1', 'UTF-16LE'],
    'row two current encoding' => ['currentEncodings.2', 'UTF-16BE'],
    'row four current encoding' => ['currentEncodings.4', 'UTF-8'],
    'row one current bytes' => ['currentBytesHex.1', '4d006f00640075006c0065005f004300610063006800650020002000'],
    'row two next bytes' => ['nextBytesHex.2', '004d006f00640075006c0065005f0043006100630068006500200020'],
    'row twelve next bytes' => ['nextBytesHex.12', '006d006f00640075006c0065005f006e00650077005f006f007000740069006f006e00200020'],
    'row one residual' => ['currentResidualMatches.1', true],
    'row five residual unicode uppercase' => ['currentResidualMatches.5', true],
    'row six residual unicode lowercase' => ['currentResidualMatches.6', true],
    'changed text rowids' => ['changedTextRowids', [1, 2, 4, 6, 8, 9]],
    'changed rtrim rowids' => ['changedRtrimRowids', [1, 2, 4]],
    'changed nocase rowids' => ['changedNocaseKeyRowids', [4]],
    'changed encoding rowids' => ['changedEncodingRowids', [5]],
    'changed bytes rowids' => ['changedBytesRowids', [1, 2, 4, 5, 6, 8, 9]],
    'changed residual rowids' => ['changedResidualRowids', []],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason decoded text' => ['invalidationReasons.2', 'decoded-text'],
    'reason rtrim expression' => ['invalidationReasons.3', 'rtrim-expression'],
    'reason nocase key' => ['invalidationReasons.4', 'nocase-key'],
    'reason text encoding' => ['invalidationReasons.5', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.6', 'encoded-bytes'],
    'reason malformed' => ['invalidationReasons.7', 'malformed-text'],
    'reason candidate rowset' => ['invalidationReasons.8', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.9', 'matched-rowset'],
    'rtrim ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency nocase like' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneFiveEight'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneFiveEight ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneFiveEight stable identical rows reusable'] = static function (TestRunner $t) use ($enc): void {
    $rows = [
        ['setting_id' => 1, 'key_name_bytes' => $enc('Module_Cache  ', 2), 'text_encoding' => 2],
        ['setting_id' => 2, 'key_name_bytes' => $enc('module_user', 3), 'text_encoding' => 3],
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourceDeltaPlan($rows, $rows, 'module!_%', '!', 'stable', 'stable', 9, 9);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveEight non ascii prefix rejects nocase range'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('æther%', null);
    $t->same(false, $result['indexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $result['rejectedReason']);
    $t->same(null, $result['range']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same('no-nocase-prefix-range', $result['invalidationReasons'][2]);
};

$tests['utf16 nocase like rtrim current source nextOneFiveEight escaped percent literal'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('module:100!%!_%');
    $t->same('module:100%_', $result['prefix']);
    $t->same('module:100%_', $result['range']['lowerInclusive']);
    $t->same([9], $result['currentMatchedRowids']);
    $t->same([9], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveEight rejects invalid escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('module%', '!!'));
};

$tests['utf16 nocase like rtrim current source nextOneFiveEight rejects missing option bytes'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'text_encoding' => 2]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourceDeltaPlan($rows, $rows, 'module%'));
};

$tests['utf16 nocase like rtrim current source nextOneFiveEight rejects bad encoding id'] = static function (TestRunner $t) use ($enc): void {
    $rows = [['setting_id' => 1, 'key_name_bytes' => $enc('module_cache', 2), 'text_encoding' => 4]];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourceDeltaPlan($rows, $rows, 'module%');
    $t->same([1], $result['currentMalformedRowids']);
    $t->same('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE', $result['currentErrors'][1]);
};

return $tests;
