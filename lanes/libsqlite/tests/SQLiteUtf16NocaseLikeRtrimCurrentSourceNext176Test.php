<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc176 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row176 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc176($name, $encoding),
    'text_encoding' => $encoding,
];
$bad176 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current176 = [
    $row176(1, 'Plugin_Cache', 2),
    $row176(2, 'plugin_cache  ', 3),
    $row176(4, 'plugin_cache_extra', 2),
    $row176(6, 'theme_cache', 2),
    $row176(7, 'plugin_cache_old', 3),
];
$nextOneSevenSix = [
    $row176(1, 'Plugin_Cache  ', 3),
    $row176(2, 'plugin_cache', 2),
    $row176(3, 'PLUGIN_CACHE   ', 2),
    $row176(4, 'plugin_cache_extra', 2),
    $row176(5, 'plugin_cache_extra  ', 3),
    $row176(6, 'theme_cache', 2),
    $row176(8, 'plugin_cache_zeta', 2),
    $bad176(12, "\x00\xd8", 2),
];

$plan176 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache', 'rowid' => 2],
    int $pageSize = 3,
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@175',
    string $nextSource = 'main.wp_options@176',
    int $currentCookie = 175,
    int $nextCookie = 176,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePeerYieldPlan(
    $current ?? $current176,
    $next ?? $nextOneSevenSix,
    $pattern,
    $escape,
    $token,
    $pageSize,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt176 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases176 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSevenSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case-sensitive flag' => ['caseSensitiveLike', false],
    'ascii nocase' => ['asciiNocaseOnly', true],
    'rtrim ascii space' => ['rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['currentSource', 'main.wp_options@175'],
    'next source' => ['nextSource', 'main.wp_options@176'],
    'current cookie' => ['currentSchemaCookie', 175],
    'next cookie' => ['nextSchemaCookie', 176],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current order' => ['currentOrderRowids', [1, 2, 4, 7, 6]],
    'next order rowid tie' => ['nextOrderRowids', [1, 2, 3, 4, 5, 8, 6]],
    'current matched' => ['currentMatchedRowids', [1, 2, 4, 7]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 4, 5, 8]],
    'current keys row one' => ['currentMatchedKeys.1', 'plugin_cache'],
    'next keys row three' => ['nextMatchedKeys.3', 'plugin_cache'],
    'next keys row five' => ['nextMatchedKeys.5', 'plugin_cache_extra'],
    'current peer cache' => ['currentDuplicatePeerGroups.plugin_cache.rowids', [1, 2]],
    'next peer cache' => ['nextDuplicatePeerGroups.plugin_cache.rowids', [1, 2, 3]],
    'next peer extra' => ['nextDuplicatePeerGroups.plugin_cache_extra.rowids', [4, 5]],
    'peer cache changed current' => ['peerGroupChanges.plugin_cache.currentRowids', [1, 2]],
    'peer cache changed next' => ['peerGroupChanges.plugin_cache.nextRowids', [1, 2, 3]],
    'peer extra changed current' => ['peerGroupChanges.plugin_cache_extra.currentRowids', []],
    'peer extra changed next' => ['peerGroupChanges.plugin_cache_extra.nextRowids', [4, 5]],
    'token key' => ['lastYielded.key', 'plugin_cache'],
    'token rowid' => ['lastYielded.rowid', 2],
    'after token rowids' => ['nextAfterTokenRowids', [3, 4, 5, 8]],
    'page size' => ['pageSize', 3],
    'yielded rowids' => ['yieldedRowids', [3, 4, 5]],
    'deferred rowids' => ['deferredRowids', [8]],
    'high water key' => ['highWaterToken.key', 'plugin_cache_extra'],
    'high water rowid' => ['highWaterToken.rowid', 5],
    'has more' => ['hasMore', true],
    'token straddles cache before' => ['peerGroupsStraddlingToken.plugin_cache.beforeOrAt', [1, 2]],
    'token straddles cache after' => ['peerGroupsStraddlingToken.plugin_cache.after', [3]],
    'yield does not straddle peer' => ['peerGroupsStraddlingYieldPage', []],
    'rowid tiebreaker' => ['usesRowidTieBreaker', true],
    'unsafe inside peer' => ['safeToResumeInsidePeerGroup', false],
    'safe high water' => ['safeToPersistHighWaterToken', true],
    'current malformed empty' => ['currentMalformedRowids', []],
    'next malformed' => ['nextMalformedRowids', [12]],
    'next malformed error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason matched rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason peer order' => ['invalidationReasons.3', 'peer-group-rowid-order'],
    'reason token straddle' => ['invalidationReasons.4', 'peer-group-straddles-resume-token'],
    'reason malformed' => ['invalidationReasons.5', 'malformed-text'],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency tiebreaker' => ['dependencies.2', 'sqlite-nocase-rowid-tiebreaker'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneSevenSix'],
];

foreach ($cases176 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSevenSix ' . $name] = static function (TestRunner $t) use ($plan176, $valueAt176, $path, $expected): void {
        $t->same($expected, $valueAt176($plan176(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSevenSix stable peer rows can continue'] = static function (TestRunner $t) use ($row176): void {
    $current = [
        $row176(1, 'Plugin_Cache  ', 2),
        $row176(2, 'plugin_cache', 3),
        $row176(4, 'plugin_cache_extra', 2),
    ];
    $next = [
        $row176(1, 'Plugin_Cache', 3),
        $row176(2, 'plugin_cache  ', 2),
        $row176(4, 'plugin_cache_extra', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePeerYieldPlan($current, $next, 'plugin!_cache%', '!', ['key' => 'plugin_cache', 'rowid' => 2], 2, 'stable', 'stable', 8, 8);
    $t->same([4], $result['nextAfterTokenRowids']);
    $t->same([4], $result['yieldedRowids']);
    $t->same([], $result['peerGroupChanges']);
    $t->same([], $result['peerGroupsStraddlingToken']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenSix yield page can straddle a duplicate peer'] = static function (TestRunner $t) use ($row176): void {
    $rows = [
        $row176(1, 'plugin_cache', 2),
        $row176(2, 'PLUGIN_CACHE ', 3),
        $row176(3, 'Plugin_Cache  ', 2),
        $row176(4, 'plugin_cache_extra', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePeerYieldPlan($rows, $rows, 'plugin!_cache%', '!', null, 2, 'stable', 'stable', 9, 9);
    $t->same([1, 2], $result['yieldedRowids']);
    $t->same([3, 4], $result['deferredRowids']);
    $t->same(['key' => 'plugin_cache', 'rowid' => 2], $result['highWaterToken']);
    $t->same(['yielded' => [1, 2], 'deferred' => [3]], $result['peerGroupsStraddlingYieldPage']['plugin_cache']);
    $t->same(false, $result['safeToPersistHighWaterToken']);
    $t->same(true, $result['safeToResumeInsidePeerGroup']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenSix unicode prefix disables range'] = static function (TestRunner $t) use ($row176): void {
    $rows = [
        $row176(1, 'éclair_cache', 2),
        $row176(2, 'Éclair_Cache  ', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePeerYieldPlan($rows, $rows, 'éclair%', null, null, 5, 'stable', 'stable', 10, 10);
    $t->same(false, $result['indexUsable']);
    $t->same(['unusable-nocase-prefix-range'], $result['invalidationReasons']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same(null, $result['highWaterToken']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenSix rejects malformed token'] = static function (TestRunner $t) use ($plan176): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan176(token: ['key' => 'plugin_cache']));
};

$tests['utf16 nocase like rtrim current source nextOneSevenSix rejects zero page'] = static function (TestRunner $t) use ($plan176): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan176(pageSize: 0));
};

$tests['utf16 nocase like rtrim current source nextOneSevenSix rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePeerYieldPlan(
        [['option_id' => 1, 'text_encoding' => 2]],
        [],
        'plugin%',
    ));
};

return $tests;
