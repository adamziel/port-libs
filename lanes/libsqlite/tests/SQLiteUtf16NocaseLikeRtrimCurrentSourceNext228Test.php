<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc228 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId228 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row228 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc228($name, $encoding),
    'text_encoding' => $encodingId228($encoding),
];
$bad228 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current228 = [
    $row228(1, 'Plugin_Cache', 'UTF-16LE'),
    $row228(2, 'plugin_cache  ', 'UTF-16LE'),
    $row228(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row228(4, 'plugin_cache_beta', 'UTF-16LE'),
    $row228(5, "plugin_cache\t", 'UTF-16LE'),
    $row228(6, 'plugin-cache', 'UTF-16LE'),
    $row228(7, 'theme_cache', 'UTF-16LE'),
];
$nextTwoTwoEight = [
    $row228(1, 'Plugin_Cache', 'UTF-16BE'),
    $row228(2, 'plugin_cache  ', 'UTF-16BE'),
    $row228(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row228(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row228(5, "plugin_cache\t", 'UTF-16BE'),
    $row228(6, 'plugin-cache', 'UTF-16BE'),
    $row228(7, 'theme_cache', 'UTF-16BE'),
];

$plan228 = static fn (
    ?array $current = null,
    ?array $next = null,
    int|string $currentEncoding = 'UTF-16LE',
    int|string $nextEncoding = 'UTF-16BE',
    int|string $preparedEncoding = 'UTF-16LE',
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 228,
    int $nextCookie = 228,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameHeaderEncodingFencePlan(
    $current ?? $current228,
    $next ?? $nextTwoTwoEight,
    'plugin!_cache%',
    '!',
    $currentEncoding,
    $nextEncoding,
    $preparedEncoding,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt228 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases228 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoEight'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoOneOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* database text-encoding fence */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 228],
    'next cookie' => ['nextSchemaCookie', 228],
    'current database encoding' => ['currentDatabaseEncoding', 'UTF-16LE'],
    'next database encoding' => ['nextDatabaseEncoding', 'UTF-16BE'],
    'prepared encoding' => ['preparedEncoding', 'UTF-16LE'],
    'header changed' => ['headerEncodingChanged', true],
    'prepared matches current' => ['preparedEncodingMatchesCurrentHeader', true],
    'prepared matches next' => ['preparedEncodingMatchesNextHeader', false],
    'logical rowset stable' => ['logicalRowsetStable', true],
    'base byte order reusable' => ['baseByteOrderOnlyRefreshReusable', true],
    'base cursor reusable' => ['baseCursorReusable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 5, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 5, 3, 4]],
    'current matched' => ['currentMatchedRowids', [1, 2, 5, 3, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 5, 3, 4]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 3, 4, 5]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', []],
    'byte order rowids' => ['byteOrderOnlyRowids', [1, 2, 3, 4, 5, 6, 7]],
    'encoding changed rowids' => ['encodingChangedRowids', [1, 2, 3, 4, 5, 6, 7]],
    'decoded changed rowids' => ['decodedRtrimTextChangedRowids', []],
    'current rtrim row two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'next rtrim row two' => ['nextRtrimTexts.2', 'plugin_cache'],
    'tab not trimmed' => ['nextRtrimTexts.5', "plugin_cache\t"],
    'current nocase row one' => ['currentNocaseKeys.1', 'plugin_cache'],
    'next nocase row one' => ['nextNocaseKeys.1', 'plugin_cache'],
    'current malformed empty' => ['currentMalformedRowids', []],
    'next malformed empty' => ['nextMalformedRowids', []],
    'base reason' => ['baseInvalidationReasons', ['byte-order-only-refresh']],
    'invalidated' => ['cursorInvalidated', true],
    'reusable false' => ['cursorReusable', false],
    'must reprepare header' => ['mustReprepareForHeaderEncoding', true],
    'must reprepare statement' => ['mustRepreparePreparedUtf16Statement', true],
    'retain rows not cursor' => ['canRetainRowsetButNotPreparedCursor', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'residual after rtrim' => ['residualCheckedAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency header' => ['dependencies.1', 'sqlite-database-text-encoding-header'],
    'dependency statement' => ['dependencies.2', 'sqlite-prepared-statement-encoding-fence'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoEight'],
];

foreach ($cases228 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoEight ' . $name] = static function (TestRunner $t) use ($plan228, $valueAt228, $path, $expected): void {
        $t->same($expected, $valueAt228($plan228(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoEight invalidates stable rowset on header encoding switch'] = static function (TestRunner $t) use ($plan228): void {
    $t->same([
        'byte-order-only-refresh',
        'database-text-encoding',
        'prepared-encoding-stale',
        'logical-rowset-stable-header-fence',
    ], $plan228()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoEight stable header can reuse byte order refresh'] = static function (TestRunner $t) use ($plan228): void {
    $result = $plan228(currentEncoding: 'UTF-16LE', nextEncoding: 'UTF-16LE', preparedEncoding: 'UTF-16LE');
    $t->same(['byte-order-only-refresh'], $result['invalidationReasons']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same(false, $result['mustReprepareForHeaderEncoding']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoEight prepared utf16be cursor survives utf16be header'] = static function (TestRunner $t) use ($plan228): void {
    $result = $plan228(currentEncoding: 'UTF-16BE', nextEncoding: 'UTF-16BE', preparedEncoding: 'UTF-16BE');
    $t->same(false, $result['headerEncodingChanged']);
    $t->same(true, $result['preparedEncodingMatchesNextHeader']);
    $t->same(false, $result['mustRepreparePreparedUtf16Statement']);
    $t->same(false, $result['cursorInvalidated']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoEight rowset change still invalidates with same header'] = static function (TestRunner $t) use ($row228, $plan228): void {
    $current = [
        $row228(1, 'plugin_cache', 'UTF-16LE'),
        $row228(2, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $next = [
        $row228(1, 'plugin_cache', 'UTF-16LE'),
        $row228(2, 'plugin_cache_alpha', 'UTF-16LE'),
        $row228(3, 'plugin_cache_beta', 'UTF-16LE'),
    ];
    $result = $plan228($current, $next, 'UTF-16LE', 'UTF-16LE', 'UTF-16LE');
    $t->same(false, $result['headerEncodingChanged']);
    $t->same(false, $result['logicalRowsetStable']);
    $t->same(true, $result['cursorInvalidated']);
    $t->same(['candidate-rowset', 'matched-rowset'], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoEight malformed text prevents logical rowset reuse'] = static function (TestRunner $t) use ($bad228, $row228, $plan228): void {
    $current = [
        $row228(1, 'plugin_cache', 'UTF-16LE'),
        $bad228(2, "\x00\xd8", 2),
    ];
    $next = [
        $row228(1, 'plugin_cache', 'UTF-16BE'),
        $bad228(2, "\xd8\x00", 3),
    ];
    $result = $plan228($current, $next);
    $t->same([2], $result['currentMalformedRowids']);
    $t->same([2], $result['nextMalformedRowids']);
    $t->same(false, $result['logicalRowsetStable']);
    $t->same(true, in_array('malformed-text', $result['invalidationReasons'], true));
    $t->same(true, in_array('database-text-encoding', $result['invalidationReasons'], true));
};

return $tests;
