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
$row = static function (int $id, string $name, string $encoding) use ($enc, $code): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $enc($name, $encoding),
        'text_encoding' => $code($encoding),
    ];
};

$currentRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    $row(4, 'plugin_cache_delta', 'UTF-16BE'),
    $row(5, 'plugin_cache_zeta', 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache_zulu', 'UTF-16LE'),
    $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    $row(4, 'plugin_cache_delta', 'UTF-16BE'),
    $row(6, 'plugin_cache_gamma', 'UTF-16BE'),
];

$plan = static function (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache_alpha', 'rowid' => 2],
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 12,
    int $nextCookie = 12,
) use ($currentRows, $nextRows, $enc, $code): array {
    return SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameYieldTokenPlan(
        $current ?? $currentRows,
        $next ?? $nextRows,
        $enc('plugin\\_cache%', 'UTF-16LE'),
        $code('UTF-16LE'),
        $enc('plugin\\_cache%', 'UTF-16BE'),
        $code('UTF-16BE'),
        $enc('\\', 'UTF-16LE'),
        $code('UTF-16LE'),
        $enc('\\', 'UTF-16BE'),
        $code('UTF-16BE'),
        $token,
        $currentSource,
        $nextSource,
        $currentCookie,
        $nextCookie,
    );
};

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
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSevenTwo'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSixFive'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 12],
    'next cookie' => ['nextSchemaCookie', 12],
    'token key' => ['lastYielded.key', 'plugin_cache_alpha'],
    'token rowid' => ['lastYielded.rowid', 2],
    'current token key' => ['currentTokenRow.key', 'plugin_cache_alpha'],
    'next token key moved' => ['nextTokenRow.key', 'plugin_cache_zulu'],
    'current matches' => ['currentMatchedRowids', [1, 2, 3, 4, 5]],
    'next matches' => ['nextMatchedRowids', [1, 3, 4, 6, 2]],
    'current after token' => ['currentAfterTokenRowids', [3, 4, 5]],
    'next after token includes yielded rowid' => ['nextAfterTokenRowids', [3, 4, 6, 2]],
    'next before token' => ['nextBeforeOrAtTokenRowids', [1]],
    'yielded reentered' => ['yieldedReenteredAfterToken', true],
    'yielded not missing' => ['yieldedMissingInNext', false],
    'yield reason duplicate' => ['yieldTokenReasons.0', 'yielded-token-reentered-after-token'],
    'yield reason key' => ['yieldTokenReasons.1', 'yielded-key-changed'],
    'base reasons' => ['baseResumeReasons', []],
    'resume duplicate reason' => ['resumeReasons.0', 'yielded-token-reentered-after-token'],
    'must reprepare' => ['mustReprepareBeforeResume', true],
    'unsafe resume' => ['safeToResumeFromToken', false],
    'resume rowids' => ['resumePlanRowids', [1, 3, 4, 6, 2]],
    'resume mode' => ['resumePlanMode', 'reprepare-from-range-start'],
    'duplicate guard' => ['avoidsDuplicateYieldOfTokenRowid', true],
    'rtrim space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency normalization' => ['dependencies.0', 'sqlite-utf16-pattern-normalization'],
    'dependency token' => ['dependencies.2', 'sqlite-current-source-yield-token'],
    'dependency closure' => ['dependency_closure', 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE/RTRIM resume ordering, and adds yielded-token duplicate prevention diagnostics'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSevenTwo ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSevenTwo stable token does not reprepare'] = static function (TestRunner $t) use ($plan, $row): void {
    $rows = [
        $row(1, 'plugin_cache', 'UTF-16LE'),
        $row(2, 'plugin_cache_alpha  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    ];
    $result = $plan($rows, $rows);
    $t->same(false, $result['yieldedReenteredAfterToken']);
    $t->same([], $result['yieldTokenReasons']);
    $t->same(false, $result['mustReprepareBeforeResume']);
    $t->same(true, $result['safeToResumeFromToken']);
    $t->same([3], $result['resumePlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenTwo same key case and space mutation remains after token safe'] = static function (TestRunner $t) use ($plan, $row): void {
    $current = [
        $row(1, 'plugin_cache', 'UTF-16LE'),
        $row(2, 'plugin_cache_alpha  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    ];
    $next = [
        $row(1, 'plugin_cache', 'UTF-16BE'),
        $row(2, 'PLUGIN_CACHE_ALPHA', 'UTF-16LE'),
        $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    ];
    $result = $plan($current, $next);
    $t->same('plugin_cache_alpha', $result['nextTokenRow']['key']);
    $t->same(false, $result['yieldedReenteredAfterToken']);
    $t->same([], $result['yieldTokenReasons']);
    $t->same(false, $result['mustReprepareBeforeResume']);
    $t->same([3], $result['resumePlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenTwo row before token forces base reprepare'] = static function (TestRunner $t) use ($plan, $nextRows, $row): void {
    $next = array_merge($nextRows, [$row(7, 'plugin_cache_aaa', 'UTF-16LE')]);
    $result = $plan(next: $next);
    $t->same([1, 7], $result['nextBeforeOrAtTokenRowids']);
    $t->same(['entered-before-token', 'yielded-token-reentered-after-token'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenTwo yielded row exiting is recorded but can continue'] = static function (TestRunner $t) use ($plan, $currentRows, $row): void {
    $next = [
        $row(1, 'plugin_cache', 'UTF-16BE'),
        $row(3, 'plugin_cache_beta', 'UTF-16LE'),
        $row(4, 'plugin_cache_delta', 'UTF-16BE'),
    ];
    $result = $plan($currentRows, $next);
    $t->same(true, $result['yieldedMissingInNext']);
    $t->same(['yielded-row-exited'], $result['yieldTokenReasons']);
    $t->same(false, in_array('yielded-token-reentered-after-token', $result['resumeReasons'], true));
    $t->same([3, 4], $result['resumePlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenTwo no token still uses range start reprepare'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(token: null);
    $t->same(null, $result['lastYielded']);
    $t->same(false, $result['yieldedReenteredAfterToken']);
    $t->same(['no-yield-token'], $result['baseResumeReasons']);
    $t->same('reprepare-from-range-start', $result['resumePlanMode']);
};

return $tests;
