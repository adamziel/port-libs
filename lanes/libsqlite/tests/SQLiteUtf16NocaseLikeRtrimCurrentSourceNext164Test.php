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
    $row(5, 'module_cache_static', 2),
    $row(6, 'module_other', 2),
    ['setting_id' => 7, 'key_name_bytes' => "p\0x", 'text_encoding' => 2],
];
$nextRows = [
    $row(1, 'module_cache', 2),
    $row(2, 'Module_Cache  ', 2),
    $row(3, 'module_cache_shadow', 2),
    $row(4, 'MODULE_CACHE_TRANSIENT', 3),
    $row(5, 'module_cache_static', 2),
    $row(8, 'module_cache_new  ', 3),
    ['setting_id' => 9, 'key_name_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@163',
    string $nextSource = 'main.app_settings@164',
    int $currentCookie = 163,
    int $nextCookie = 164,
    string $currentCollation = 'NOCASE/RTRIM@163',
    string $nextCollation = 'NOCASE/RTRIM@164',
    string $currentLike = 'like@163',
    string $nextLike = 'like@164',
    string $currentStatement = 'select-rtrim-nocase-like@163',
    string $nextStatement = 'select-rtrim-nocase-like@164',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPlan(
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
    $currentStatement,
    $nextStatement,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSixFour'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'module!_cache%'],
    'escape' => ['escape', '!'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSixOne'],
    'current source' => ['currentSource', 'main.app_settings@163'],
    'next source' => ['nextSource', 'main.app_settings@164'],
    'current cookie' => ['currentSchemaCookie', 163],
    'next cookie' => ['nextSchemaCookie', 164],
    'current collation generation' => ['currentCollationGeneration', 'NOCASE/RTRIM@163'],
    'next collation generation' => ['nextCollationGeneration', 'NOCASE/RTRIM@164'],
    'current like generation' => ['currentLikeGeneration', 'like@163'],
    'next like generation' => ['nextLikeGeneration', 'like@164'],
    'current statement token' => ['currentPreparedStatement', 'select-rtrim-nocase-like@163'],
    'next statement token' => ['nextPreparedStatement', 'select-rtrim-nocase-like@164'],
    'index usable' => ['indexUsable', true],
    'prefix' => ['prefix', 'module_cache'],
    'range lower' => ['range.lowerInclusive', 'module_cache'],
    'range upper' => ['range.upperBound', 'module_cachf'],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 5, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 8, 3, 5, 4]],
    'retained candidates' => ['retainedCandidateRowids', [1, 2, 3, 5, 4]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 5, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 8, 3, 5, 4]],
    'retained matched' => ['retainedMatchedRowids', [1, 2, 3, 5, 4]],
    'entered matched' => ['enteredMatchedRowids', [8]],
    'exited matched' => ['exitedMatchedRowids', []],
    'stable retained row' => ['yieldStableRetainedRowids', [3, 5]],
    'recheck retained row' => ['yieldRecheckRetainedRowids', [1, 2, 4]],
    'skipped current rowids' => ['yieldSkippedCurrentRowids', []],
    'new next rowids' => ['yieldNewNextRowids', [8]],
    'current malformed' => ['currentMalformedRowids', [7]],
    'next malformed' => ['nextMalformedRowids', [9]],
    'row one current rtrim' => ['currentRtrimTexts.1', 'Module_Cache'],
    'row one next rtrim' => ['nextRtrimTexts.1', 'module_cache'],
    'row three stable rtrim' => ['nextRtrimTexts.3', 'module_cache_shadow'],
    'row five stable nocase' => ['nextNocaseKeys.5', 'module_cache_static'],
    'row two current encoding' => ['currentEncodings.2', 'UTF-16BE'],
    'row two next encoding' => ['nextEncodings.2', 'UTF-16LE'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'yield resume unsafe' => ['yieldResumeSafe', false],
    'yield requires reprepare' => ['yieldResumeRequiresReprepare', true],
    'yield requires residual recheck' => ['yieldResumeRequiresResidualRecheck', true],
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
    'reason retained rtrim key' => ['invalidationReasons.11', 'retained-rtrim-key'],
    'reason retained encoding' => ['invalidationReasons.12', 'retained-encoding'],
    'reason retained bytes' => ['invalidationReasons.13', 'retained-bytes'],
    'reason prepared token' => ['invalidationReasons.14', 'prepared-statement-token'],
    'reason statement fingerprint' => ['invalidationReasons.15', 'statement-fingerprint'],
    'reason retained recheck' => ['invalidationReasons.16', 'yield-retained-row-recheck'],
    'reason candidate position' => ['invalidationReasons.17', 'yield-candidate-position'],
    'reason output position' => ['invalidationReasons.18', 'yield-output-position'],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency like range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency nextOneSixOne' => ['dependencies.3', 'sqlite-current-source-nextoneSixOne'],
    'dependency nextOneSixFour' => ['dependencies.4', 'sqlite-yield-current-source-nextoneSixFour'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSixFour ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSixFour fingerprints are sha256 sized'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan();
    $t->same(64, strlen($result['rangeFingerprint']));
    $t->same(64, strlen($result['currentStatementFingerprint']));
    $t->same(64, strlen($result['nextStatementFingerprint']));
    $t->same(64, strlen($result['yieldResumeKeyFingerprints'][3]));
};

$tests['utf16 nocase like rtrim current source nextOneSixFour stable same statement can resume'] = static function (TestRunner $t) use ($row): void {
    $rows = [
        $row(1, 'module_cache  ', 2),
        $row(2, 'module_cache_shadow', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPlan(
        $rows,
        $rows,
        'module!_cache%',
        '!',
        'stable',
        'stable',
        11,
        11,
        'NOCASE/RTRIM@stable',
        'NOCASE/RTRIM@stable',
        'like@stable',
        'like@stable',
        'stmt@stable',
        'stmt@stable',
    );
    $t->same([1, 2], $result['yieldStableRetainedRowids']);
    $t->same([], $result['yieldRecheckRetainedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['yieldResumeSafe']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFour same rows statement token invalidates'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_cache  ', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPlan(
        $rows,
        $rows,
        'module!_cache%',
        '!',
        'stable',
        'stable',
        11,
        11,
        'NOCASE/RTRIM@stable',
        'NOCASE/RTRIM@stable',
        'like@stable',
        'like@stable',
        'stmt@a',
        'stmt@b',
    );
    $t->same(['prepared-statement-token', 'statement-fingerprint'], $result['invalidationReasons']);
    $t->same(false, $result['yieldResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFour same rows like generation invalidates only generation and fingerprint'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_cache  ', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPlan(
        $rows,
        $rows,
        'module!_cache%',
        '!',
        'stable',
        'stable',
        11,
        11,
        'NOCASE/RTRIM@stable',
        'NOCASE/RTRIM@stable',
        'like@a',
        'like@b',
        'stmt@stable',
        'stmt@stable',
    );
    $t->same(['like-generation', 'statement-fingerprint'], $result['invalidationReasons']);
    $t->same([1], $result['yieldStableRetainedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFour malformed retained rows never resume safe'] = static function (TestRunner $t) use ($row): void {
    $current = [$row(1, 'module_cache', 2), ['setting_id' => 2, 'key_name_bytes' => "p\0x", 'text_encoding' => 2]];
    $next = [$row(1, 'module_cache', 2), ['setting_id' => 3, 'key_name_bytes' => "\x00\xd8", 'text_encoding' => 2]];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPlan(
        $current,
        $next,
        'module%',
        null,
        'stable',
        'stable',
        11,
        11,
        'NOCASE/RTRIM@stable',
        'NOCASE/RTRIM@stable',
        'like@stable',
        'like@stable',
        'stmt@stable',
        'stmt@stable',
    );
    $t->same([1], $result['yieldStableRetainedRowids']);
    $t->same([2], $result['currentMalformedRowids']);
    $t->same([3], $result['nextMalformedRowids']);
    $t->same(false, $result['yieldResumeSafe']);
    $t->true(in_array('malformed-text', $result['invalidationReasons'], true));
};

$tests['utf16 nocase like rtrim current source nextOneSixFour rejects invalid escape'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPlan([], [], 'module%', '!!'));
};

return $tests;
