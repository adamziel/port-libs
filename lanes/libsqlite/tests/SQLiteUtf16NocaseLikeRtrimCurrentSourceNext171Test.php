<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};
$row = static fn (int $id, string $name, string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $code($encoding),
];
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache  ', 'UTF-8'),
    $row(4, 'plugin_cache_extra', 'UTF-16LE'),
    $row(5, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    $row(6, 'plugin_other', 'UTF-16LE'),
    $bad(7, "\x00\xd8", 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-8'),
    $row(4, 'plugin_cache_extra', 'UTF-16LE'),
    $row(5, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    $row(8, 'PLUGIN_CACHE', 'UTF-16LE'),
    $row(9, 'plugin_cache_alpha', 'UTF-16BE'),
    $bad(10, "\xd8\x00", 3),
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache', 'rowid' => 2],
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@170',
    string $nextSource = 'main.wp_options@171',
    int $currentCookie = 170,
    int $nextCookie = 171,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDuplicateKeyReplayPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $token,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next171'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'case sensitive flag' => ['caseSensitiveLike', false],
    'ascii nocase' => ['asciiNocaseOnly', true],
    'rtrim spaces only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['currentSource', 'main.wp_options@170'],
    'next source' => ['nextSource', 'main.wp_options@171'],
    'current cookie' => ['currentSchemaCookie', 170],
    'next cookie' => ['nextSchemaCookie', 171],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix ascii' => ['prefixIsAscii', true],
    'index usable' => ['indexUsable', true],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'token key' => ['lastYielded.key', 'plugin_cache'],
    'token rowid' => ['lastYielded.rowid', 2],
    'current matches sorted by duplicate key rowid' => ['currentMatchedRowids', [1, 2, 3, 4, 5]],
    'next matches sorted by duplicate key rowid' => ['nextMatchedRowids', [1, 2, 3, 8, 9, 4, 5]],
    'current key one' => ['currentMatchedKeys.1', 'plugin_cache'],
    'current key five keeps tab' => ['currentMatchedKeys.5', "plugin_cache_tab\t"],
    'next key eight folds ascii only' => ['nextMatchedKeys.8', 'plugin_cache'],
    'row one current encoding' => ['currentMatchedEncodings.1', 'UTF-16LE'],
    'row one next encoding' => ['nextMatchedEncodings.1', 'UTF-16BE'],
    'current after token uses rowid tiebreaker' => ['currentAfterTokenRowids', [3, 4, 5]],
    'next after token uses rowid tiebreaker' => ['nextAfterTokenRowids', [3, 8, 9, 4, 5]],
    'next before token includes duplicate rowids through token' => ['nextBeforeOrAtTokenRowids', [1, 2]],
    'duplicate key rowids' => ['duplicateRtrimNocaseKeys.plugin_cache', [1, 2, 3, 8]],
    'changed key rows' => ['changedKeyRowids', []],
    'changed encoding rows' => ['changedEncodingRowids', [1]],
    'changed bytes rows' => ['changedBytesRowids', [1, 2, 3]],
    'current malformed' => ['currentMalformedRowids', [7]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'reason source' => ['replayInvalidationReasons.0', 'source-name'],
    'reason schema' => ['replayInvalidationReasons.1', 'schema-cookie'],
    'reason matched rowset' => ['replayInvalidationReasons.2', 'matched-rowset'],
    'reason malformed' => ['replayInvalidationReasons.3', 'malformed-text'],
    'reason encoding changed' => ['replayInvalidationReasons.4', 'encoding-changed'],
    'reason bytes changed' => ['replayInvalidationReasons.5', 'bytes-changed'],
    'reason duplicate key' => ['replayInvalidationReasons.6', 'duplicate-rtrim-nocase-key'],
    'must reprepare' => ['mustReprepareBeforeReplay', true],
    'not safe replay' => ['safeToReplayFromToken', false],
    'replay rowids restart' => ['replayPlanRowids', [1, 2, 3, 8, 9, 4, 5]],
    'replay mode restart' => ['replayPlanMode', 'reprepare-from-range-start'],
    'token rowid tie breaker' => ['tokenIncludesRowidTieBreaker', true],
    'token byte fingerprint' => ['tokenIncludesByteFingerprint', true],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency replay' => ['dependencies.2', 'sqlite-nocase-like-duplicate-key-replay'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next171'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next171 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next171 stable duplicate keys can continue after key rowid token'] = static function (TestRunner $t) use ($row): void {
    $rows = [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
        $row(3, 'PLUGIN_CACHE', 'UTF-8'),
        $row(4, 'plugin_cache_extra', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDuplicateKeyReplayPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        ['key' => 'plugin_cache', 'rowid' => 2],
        'stable',
        'stable',
        171,
        171,
    );
    $t->same(['plugin_cache' => [1, 2, 3]], $result['duplicateRtrimNocaseKeys']);
    $t->same([], $result['changedEncodingRowids']);
    $t->same(['duplicate-rtrim-nocase-key'], $result['replayInvalidationReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
    $t->same([1, 2, 3, 4], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source next171 unique keys can safely replay after token'] = static function (TestRunner $t) use ($row): void {
    $rows = [
        $row(1, 'plugin_cache_alpha', 'UTF-16LE'),
        $row(2, 'plugin_cache_beta', 'UTF-16BE'),
        $row(3, 'plugin_cache_gamma', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDuplicateKeyReplayPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        ['key' => 'plugin_cache_alpha', 'rowid' => 1],
        'stable',
        'stable',
        171,
        171,
    );
    $t->same([], $result['replayInvalidationReasons']);
    $t->same(false, $result['mustReprepareBeforeReplay']);
    $t->same(true, $result['safeToReplayFromToken']);
    $t->same([2, 3], $result['replayPlanRowids']);
    $t->same('continue-after-key-rowid-token', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source next171 leading wildcard falls back to full residual replay'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(pattern: '%cache', escape: null);
    $t->same(false, $result['indexUsable']);
    $t->same(null, $result['range']);
    $t->true(in_array('full-scan-like-residual', $result['replayInvalidationReasons'], true));
    $t->same([1, 2, 3, 8], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source next171 entered before token is unsafe'] = static function (TestRunner $t) use ($plan, $nextRows, $row): void {
    $next = array_merge($nextRows, [$row(0, 'plugin_cache', 'UTF-16LE')]);
    $result = $plan(next: $next);
    $t->same([0, 1, 2], $result['nextBeforeOrAtTokenRowids']);
    $t->true(in_array('entered-before-token', $result['replayInvalidationReasons'], true));
    $t->same(true, $result['mustReprepareBeforeReplay']);
};

$tests['utf16 nocase like rtrim current source next171 malformed error text is exposed'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan();
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['currentErrors'][7]);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['nextErrors'][10]);
};

$tests['utf16 nocase like rtrim current source next171 rejects invalid token'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDuplicateKeyReplayPlan(
        $currentRows,
        $nextRows,
        'plugin%',
        null,
        ['key' => 'plugin_cache'],
    ));
};

return $tests;
