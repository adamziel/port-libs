<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Module_Cache  ', 2),
    $row(2, 'module_cache', 3),
    $row(3, 'module_cache_shadow', 2),
    $row(4, 'MODULE_CACHE_TRANSIENT  ', 3),
    $row(5, 'module_other', 2),
    ['setting_id' => 6, 'key_name_bytes' => "p\0x", 'text_encoding' => 2],
];
$nextRows = [
    $row(1, 'module_cache', 2),
    $row(2, 'Module_Cache  ', 2),
    $row(3, 'module_cache_shadow  ', 2),
    $row(4, 'MODULE_CACHE_TRANSIENT', 3),
    $row(7, 'module_cache_new  ', 3),
    ['setting_id' => 8, 'key_name_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@160',
    string $nextSource = 'main.app_settings@161',
    int $currentCookie = 160,
    int $nextCookie = 161,
    string $currentCollation = 'NOCASE/RTRIM@160',
    string $nextCollation = 'NOCASE/RTRIM@161',
    string $currentLike = 'like@160',
    string $nextLike = 'like@161',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyGenerationPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    'module!_cache%',
    '!',
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
    $currentCollation,
    $nextCollation,
    $currentLike,
    $nextLike,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSixOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'module!_cache%'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.app_settings@160'],
    'next source' => ['nextSource', 'main.app_settings@161'],
    'current schema cookie' => ['currentSchemaCookie', 160],
    'next schema cookie' => ['nextSchemaCookie', 161],
    'current collation generation' => ['currentCollationGeneration', 'NOCASE/RTRIM@160'],
    'next collation generation' => ['nextCollationGeneration', 'NOCASE/RTRIM@161'],
    'current like generation' => ['currentLikeGeneration', 'like@160'],
    'next like generation' => ['nextLikeGeneration', 'like@161'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneFiveEight'],
    'index usable' => ['indexUsable', true],
    'prefix' => ['prefix', 'module_cache'],
    'range lower' => ['range.lowerInclusive', 'module_cache'],
    'range upper' => ['range.upperBound', 'module_cachf'],
    'current order rowids' => ['currentOrderRowids', [1, 2, 3, 4, 5]],
    'next order rowids' => ['nextOrderRowids', [1, 2, 7, 3, 4]],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 7, 3, 4]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 7, 3, 4]],
    'retained matched' => ['retainedMatchedRowids', [1, 2, 3, 4]],
    'entered matched' => ['enteredMatchedRowids', [7]],
    'exited matched' => ['exitedMatchedRowids', []],
    'current malformed' => ['currentMalformedRowids', [6]],
    'next malformed' => ['nextMalformedRowids', [8]],
    'odd length error' => ['currentErrors.6', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'surrogate error' => ['nextErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row one current rtrim' => ['currentRtrimTexts.1', 'Module_Cache'],
    'row one next rtrim' => ['nextRtrimTexts.1', 'module_cache'],
    'row three next rtrim' => ['nextRtrimTexts.3', 'module_cache_shadow'],
    'row four current nocase' => ['currentNocaseKeys.4', 'module_cache_transient'],
    'row two next encoding' => ['nextEncodings.2', 'UTF-16LE'],
    'row two current encoding' => ['currentEncodings.2', 'UTF-16BE'],
    'retained changed rtrim' => ['retainedChangedRtrimRowids', [1, 2]],
    'retained changed nocase' => ['retainedChangedNocaseRowids', []],
    'retained changed encoding' => ['retainedChangedEncodingRowids', [2]],
    'retained changed bytes' => ['retainedChangedBytesRowids', [1, 2, 3, 4]],
    'same source false' => ['sameSourceToken', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reprepare required' => ['reprepareRequired', true],
    'source may not reuse statement' => ['currentSourceMayReuseStatement', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason decoded text' => ['invalidationReasons.2', 'decoded-text'],
    'reason rtrim expression' => ['invalidationReasons.3', 'rtrim-expression'],
    'reason text encoding' => ['invalidationReasons.4', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.5', 'encoded-bytes'],
    'reason malformed' => ['invalidationReasons.6', 'malformed-text'],
    'reason candidate rowset' => ['invalidationReasons.7', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.8', 'matched-rowset'],
    'reason collation generation' => ['invalidationReasons.9', 'collation-generation'],
    'reason like generation' => ['invalidationReasons.10', 'like-generation'],
    'reason retained rtrim' => ['invalidationReasons.11', 'retained-rtrim-key'],
    'reason retained encoding' => ['invalidationReasons.12', 'retained-encoding'],
    'reason retained bytes' => ['invalidationReasons.13', 'retained-bytes'],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency like range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency generation' => ['dependencies.3', 'sqlite-collation-generation'],
    'dependency source nextOneSixOne' => ['dependencies.4', 'sqlite-current-source-nextoneSixOne'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSixOne ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSixOne stable same generation reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [
        $row(1, 'module_cache  ', 2),
        $row(2, 'module_cache_shadow', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyGenerationPlan(
        $rows,
        $rows,
        'module!_cache%',
        '!',
        'stable',
        'stable',
        9,
        9,
        'NOCASE/RTRIM@stable',
        'NOCASE/RTRIM@stable',
        'like@stable',
        'like@stable',
    );
    $t->same([1, 2], $result['retainedMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['sameSourceToken']);
    $t->same(true, $result['cursorReusable']);
    $t->same(false, $result['reprepareRequired']);
};

$tests['utf16 nocase like rtrim current source nextOneSixOne same rows collation generation invalidates'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_cache  ', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyGenerationPlan(
        $rows,
        $rows,
        'module!_cache%',
        '!',
        'stable',
        'stable',
        9,
        9,
        'NOCASE/RTRIM@a',
        'NOCASE/RTRIM@b',
        'like@stable',
        'like@stable',
    );
    $t->same(['collation-generation'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
    $t->same(true, $result['reprepareRequired']);
};

$tests['utf16 nocase like rtrim current source nextOneSixOne same rows like generation invalidates'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_cache  ', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyGenerationPlan(
        $rows,
        $rows,
        'module!_cache%',
        '!',
        'stable',
        'stable',
        9,
        9,
        'NOCASE/RTRIM@stable',
        'NOCASE/RTRIM@stable',
        'like@a',
        'like@b',
    );
    $t->same(['like-generation'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

return $tests;
