<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, 'plugin_cache_alpha  ', 3),
    $row(3, 'plugin_cache_beta', 2),
    $row(4, "plugin_cache_tab\t", 3),
    $row(5, 'plugin_other', 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 3),
    $row(2, 'plugin_cache_alpha', 2),
    $row(3, 'plugin_cache_beta  ', 2),
    $row(4, "plugin_cache_tab\t", 3),
    $row(7, 'PLUGIN_CACHE_GAMMA', 2),
    $row(8, 'plugin_cache_early', 1),
];

$plan = static function (
    ?array $current = null,
    ?array $next = null,
    string $currentToken = 'plugin_cache_alpha  ',
    int $currentTokenEncoding = 2,
    int $currentTokenRowid = 2,
    string $nextToken = 'plugin_cache_alpha',
    int $nextTokenEncoding = 3,
    int $nextTokenRowid = 2,
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 11,
    int $nextCookie = 11,
) use ($currentRows, $nextRows, $enc): array {
    return SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan::keyValueRowKeyResumeTokenPlan(
        $current ?? $currentRows,
        $next ?? $nextRows,
        $enc('plugin\\_cache%', 2),
        2,
        $enc('plugin\\_cache%', 3),
        3,
        $enc($currentToken, $currentTokenEncoding),
        $currentTokenEncoding,
        $currentTokenRowid,
        $enc($nextToken, $nextTokenEncoding),
        $nextTokenEncoding,
        $nextTokenRowid,
        $enc('\\', 2),
        2,
        $enc('\\', 3),
        3,
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
    'status' => ['status', 'utf16-nocase-like-rtrim-resume-token-current-source-nextoneSevenZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ?'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSixFive'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'ascii nocase only' => ['asciiNocaseOnly', true],
    'rtrim trims ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 11],
    'next cookie' => ['nextSchemaCookie', 11],
    'current token key decoded lower rtrim' => ['currentTokenKey', 'plugin_cache_alpha'],
    'next token key decoded lower rtrim' => ['nextTokenKey', 'plugin_cache_alpha'],
    'current token rowid' => ['currentTokenRowid', 2],
    'next token rowid' => ['nextTokenRowid', 2],
    'current token encoding' => ['currentTokenEncoding', 'UTF-16LE'],
    'next token encoding' => ['nextTokenEncoding', 'UTF-16BE'],
    'current token bytes' => ['currentTokenBytesHex', '70006c007500670069006e005f00630061006300680065005f0061006c0070006800610020002000'],
    'next token bytes' => ['nextTokenBytesHex', '0070006c007500670069006e005f00630061006300680065005f0061006c007000680061'],
    'same decoded token' => ['sameDecodedToken', true],
    'base resume reasons empty' => ['baseResumeReasons', []],
    'token byte reasons' => ['tokenByteReasons', ['token-key-encoding', 'token-key-bytes']],
    'token semantic reasons empty' => ['tokenSemanticReasons', []],
    'resume reasons empty' => ['resumeReasons', []],
    'byte only token reprepare' => ['byteOnlyTokenReprepare', true],
    'must not reprepare' => ['mustReprepareBeforeResume', false],
    'safe to resume' => ['safeToResumeFromToken', true],
    'resume mode' => ['resumePlanMode', 'continue-after-decoded-token-key-rowid'],
    'current matched rowids' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next matched rowids' => ['nextMatchedRowids', [7, 1, 2, 3, 8, 4]],
    'next after token rowids' => ['nextAfterTokenRowids', [3, 8, 7, 4]],
    'resume rowids' => ['resumePlanRowids', [3, 8, 7, 4]],
    'next before token' => ['nextBeforeOrAtTokenRowids', [1, 2]],
    'entered after token' => ['enteredAfterTokenRowids', [8, 7]],
    'new before token empty' => ['newBeforeTokenRowids', []],
    'base byte reasons' => ['byteReprepareReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes']],
    'base invalidation reasons' => ['baseInvalidationReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes', 'candidate-rowset', 'matched-rowset']],
    'semantic invalidation reasons' => ['semanticInvalidationReasons', ['candidate-rowset', 'matched-rowset']],
    'dependency token decode' => ['dependencies.0', 'sqlite-utf16-resume-token-decode'],
    'dependency cursor' => ['dependencies.1', 'sqlite-nocase-like-rtrim-resume-cursor'],
    'dependency current source' => ['dependencies.2', 'sqlite-current-source-nextoneSevenZero'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim resume token current source nextOneSevenZero ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim resume token current source nextOneSevenZero token text change forces range restart'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(nextToken: 'plugin_cache_beta', nextTokenEncoding: 2, nextTokenRowid: 3);
    $t->same(['token-key-bytes', 'token-rowid'], $result['tokenByteReasons']);
    $t->same(['token-key-text', 'token-rowid'], $result['tokenSemanticReasons']);
    $t->same(['token-key-text', 'token-rowid'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
    $t->same('reprepare-from-range-start', $result['resumePlanMode']);
    $t->same($result['nextMatchedRowids'], $result['resumePlanRowids']);
};

$tests['utf16 nocase like rtrim resume token current source nextOneSevenZero source change still forces range restart'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentSource: 'main.wp_options@169', nextSource: 'main.wp_options@170');
    $t->same(['semantic-invalidation'], $result['baseResumeReasons']);
    $t->same(['semantic-invalidation'], $result['resumeReasons']);
    $t->same(false, $result['byteOnlyTokenReprepare']);
    $t->same(true, $result['mustReprepareBeforeResume']);
};

$tests['utf16 nocase like rtrim resume token current source nextOneSevenZero row entering before token forces range restart'] = static function (TestRunner $t) use ($plan, $currentRows, $nextRows, $row): void {
    $next = array_merge($nextRows, [$row(9, 'plugin_cache_aaa', 2)]);
    $result = $plan($currentRows, $next);
    $t->same([1, 9, 2], $result['nextBeforeOrAtTokenRowids']);
    $t->same([9], $result['newBeforeTokenRowids']);
    $t->same(['entered-before-token'], $result['baseResumeReasons']);
    $t->same(['entered-before-token'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
};

$tests['utf16 nocase like rtrim resume token current source nextOneSevenZero ascii case token normalizes before compare'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentToken: 'PLUGIN_CACHE_ALPHA  ', currentTokenEncoding: 2, nextToken: 'plugin_cache_alpha', nextTokenEncoding: 2);
    $t->same('plugin_cache_alpha', $result['currentTokenKey']);
    $t->same('plugin_cache_alpha', $result['nextTokenKey']);
    $t->same([], $result['tokenSemanticReasons']);
    $t->same(true, $result['safeToResumeFromToken']);
};

$tests['utf16 nocase like rtrim resume token current source nextOneSevenZero malformed current token throws'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan::keyValueRowKeyResumeTokenPlan(
        $currentRows,
        $nextRows,
        $enc('plugin\\_cache%', 2),
        2,
        $enc('plugin\\_cache%', 2),
        2,
        "p\0l",
        2,
        2,
        $enc('plugin_cache_alpha', 2),
        2,
        2,
    ));
};

$tests['utf16 nocase like rtrim resume token current source nextOneSevenZero malformed next row forces reprepare'] = static function (TestRunner $t) use ($plan, $nextRows, $bad): void {
    $next = array_merge($nextRows, [$bad(10, "\x00\xd8", 2)]);
    $result = $plan(next: $next);
    $t->same([10], $result['nextMalformedRowids']);
    $t->same(['semantic-invalidation', 'malformed-text'], $result['baseResumeReasons']);
    $t->same(['semantic-invalidation', 'malformed-text'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['nextErrors'][10]);
};

return $tests;
