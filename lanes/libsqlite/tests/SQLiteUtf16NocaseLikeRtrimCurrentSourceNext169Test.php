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
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache_alpha  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    $row(5, 'plugin_other', 'UTF-16LE'),
    $row(6, 'plugin_Éclair', 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(3, 'plugin_cache_beta  ', 'UTF-16LE'),
    $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    $row(7, 'PLUGIN_CACHE_GAMMA', 'UTF-16LE'),
    $row(8, 'plugin_cache_early', 'UTF-8'),
    $row(9, 'plugin_éclair', 'UTF-16BE'),
];

$plan = static function (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache_alpha', 'rowid' => 2],
    int $pageSize = 3,
    string $currentPattern = 'plugin\\_cache%',
    string $currentPatternEncoding = 'UTF-16LE',
    string $nextPattern = 'plugin\\_cache%',
    string $nextPatternEncoding = 'UTF-16BE',
    ?string $currentEscape = '\\',
    string $currentEscapeEncoding = 'UTF-16LE',
    ?string $nextEscape = '\\',
    string $nextEscapeEncoding = 'UTF-16BE',
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentSchemaCookie = 169,
    int $nextSchemaCookie = 169,
) use ($currentRows, $nextRows, $enc, $code): array {
    return SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameYieldReplayPlan(
        $current ?? $currentRows,
        $next ?? $nextRows,
        $enc($currentPattern, $currentPatternEncoding),
        $code($currentPatternEncoding),
        $enc($nextPattern, $nextPatternEncoding),
        $code($nextPatternEncoding),
        $currentEscape === null ? null : $enc($currentEscape, $currentEscapeEncoding),
        $code($currentEscapeEncoding),
        $nextEscape === null ? null : $enc($nextEscape, $nextEscapeEncoding),
        $code($nextEscapeEncoding),
        $token,
        $pageSize,
        $currentSource,
        $nextSource,
        $currentSchemaCookie,
        $nextSchemaCookie,
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
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next169'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'case insensitive like' => ['caseSensitiveLike', false],
    'ascii nocase only' => ['asciiNocaseOnly', true],
    'rtrim trims only space' => ['rtrimTrimsOnlyAsciiSpace', true],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-next165'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 169],
    'next cookie' => ['nextSchemaCookie', 169],
    'current pattern' => ['currentPattern', 'plugin\\_cache%'],
    'next pattern' => ['nextPattern', 'plugin\\_cache%'],
    'same pattern' => ['sameDecodedPattern', true],
    'current escape' => ['currentEscape', '\\'],
    'next escape' => ['nextEscape', '\\'],
    'same escape' => ['sameDecodedEscape', true],
    'current range lower' => ['currentRange.lowerInclusive', 'plugin_cache'],
    'next range upper' => ['nextRange.upperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'last token key' => ['lastYielded.key', 'plugin_cache_alpha'],
    'last token rowid' => ['lastYielded.rowid', 2],
    'page size' => ['pageSize', 3],
    'current matches' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next matches' => ['nextMatchedRowids', [7, 1, 2, 3, 8, 4]],
    'current after token' => ['currentAfterTokenRowids', [3, 4]],
    'next after token' => ['nextAfterTokenRowids', [3, 8, 7, 4]],
    'resume mode' => ['resumePlanMode', 'continue-after-last-yielded-key-rowid'],
    'resume rowids' => ['resumePlanRowids', [3, 8, 7, 4]],
    'yield mode' => ['yieldMode', 'continue-yield-page'],
    'yielded rowids' => ['yieldedRowids', [3, 8, 7]],
    'yielded key row three' => ['yieldedKeys.3', 'plugin_cache_beta'],
    'yielded key row eight' => ['yieldedKeys.8', 'plugin_cache_early'],
    'yielded key row seven' => ['yieldedKeys.7', 'plugin_cache_gamma'],
    'deferred rowids' => ['deferredRowids', [4]],
    'deferred key tab preserved' => ['deferredKeys.4', "plugin_cache_tab\t"],
    'high water key' => ['highWaterToken.key', 'plugin_cache_gamma'],
    'high water rowid' => ['highWaterToken.rowid', 7],
    'has more' => ['hasMore', true],
    'previously yielded rowids' => ['previouslyYieldedRowids', [1, 2]],
    'no duplicate rowids' => ['wouldDuplicateRowids', []],
    'no stale retained before token' => ['staleRetainedBeforeTokenRowids', []],
    'new before token empty' => ['newBeforeTokenRowids', []],
    'moved across token empty' => ['retainedMovedAcrossTokenRowids', []],
    'byte reasons preserved' => ['byteReprepareReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes']],
    'semantic rowset reasons preserved' => ['semanticInvalidationReasons', ['candidate-rowset', 'matched-rowset']],
    'base reasons preserved' => ['baseInvalidationReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes', 'candidate-rowset', 'matched-rowset']],
    'resume reasons empty' => ['resumeReasons', []],
    'restart reasons empty' => ['restartReasons', []],
    'must not restart' => ['mustRestartBeforeYield', false],
    'safe continue yield' => ['safeToContinueYield', true],
    'dependency normalization' => ['dependencies.0', 'sqlite-utf16-pattern-normalization'],
    'dependency resume cursor' => ['dependencies.1', 'sqlite-nocase-like-rtrim-resume-cursor'],
    'dependency high water' => ['dependencies.2', 'sqlite-yield-high-water-token'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next169'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next169 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next169 source change restarts from range start'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentSource: 'main.wp_options@168', nextSource: 'main.wp_options@169');
    $t->same('restart-then-yield-page', $result['yieldMode']);
    $t->same(['semantic-invalidation'], $result['resumeReasons']);
    $t->same(['semantic-invalidation', 'would-duplicate-yield'], $result['restartReasons']);
    $t->same([1, 2], $result['wouldDuplicateRowids']);
    $t->same(true, $result['mustRestartBeforeYield']);
    $t->same(false, $result['safeToContinueYield']);
    $t->same([7, 1, 2], $result['yieldedRowids']);
    $t->same(['key' => 'plugin_cache_alpha', 'rowid' => 2], $result['highWaterToken']);
};

$tests['utf16 nocase like rtrim current source next169 page size one yields one high water token'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(pageSize: 1);
    $t->same([3], $result['yieldedRowids']);
    $t->same([8, 7, 4], $result['deferredRowids']);
    $t->same(['key' => 'plugin_cache_beta', 'rowid' => 3], $result['highWaterToken']);
    $t->same(true, $result['hasMore']);
};

$tests['utf16 nocase like rtrim current source next169 large page drains cursor'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(pageSize: 10);
    $t->same([3, 8, 7, 4], $result['yieldedRowids']);
    $t->same([], $result['deferredRowids']);
    $t->same(['key' => "plugin_cache_tab\t", 'rowid' => 4], $result['highWaterToken']);
    $t->same(false, $result['hasMore']);
};

$tests['utf16 nocase like rtrim current source next169 null token restarts from range start'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(token: null);
    $t->same(null, $result['lastYielded']);
    $t->same(['no-yield-token'], $result['restartReasons']);
    $t->same(true, $result['mustRestartBeforeYield']);
    $t->same([7, 1, 2], $result['yieldedRowids']);
};

$tests['utf16 nocase like rtrim current source next169 entered before token restarts before yielding'] = static function (TestRunner $t) use ($plan, $currentRows, $nextRows, $row): void {
    $next = array_merge($nextRows, [$row(10, 'plugin_cache_aaa', 'UTF-16LE')]);
    $result = $plan($currentRows, $next);
    $t->same([10], $result['newBeforeTokenRowids']);
    $t->same(['entered-before-token', 'would-duplicate-yield'], $result['restartReasons']);
    $t->same([1, 10, 2], $result['previouslyYieldedRowids']);
    $t->same('restart-then-yield-page', $result['yieldMode']);
    $t->same([7, 1, 10], $result['yieldedRowids']);
};

$tests['utf16 nocase like rtrim current source next169 retained moved across token restarts'] = static function (TestRunner $t) use ($plan, $currentRows, $row): void {
    $next = [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row(3, 'plugin_cache_aaa', 'UTF-16LE'),
        $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    ];
    $result = $plan($currentRows, $next);
    $t->same([3], $result['retainedMovedAcrossTokenRowids']);
    $t->same(['retained-moved-across-token', 'would-duplicate-yield', 'retained-row-became-before-token'], $result['restartReasons']);
    $t->same([3], $result['staleRetainedBeforeTokenRowids']);
    $t->same([1, 3, 2], $result['yieldedRowids']);
};

$tests['utf16 nocase like rtrim current source next169 malformed next text preserves errors'] = static function (TestRunner $t) use ($plan, $nextRows, $bad): void {
    $next = array_merge($nextRows, [$bad(11, "\x00\xd8", 2)]);
    $result = $plan(next: $next);
    $t->same([11], $result['nextMalformedRowids']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['nextErrors'][11]);
    $t->same(['semantic-invalidation', 'malformed-text', 'would-duplicate-yield'], $result['restartReasons']);
    $t->same(true, $result['mustRestartBeforeYield']);
};

$tests['utf16 nocase like rtrim current source next169 unicode pattern keeps range but ascii nocase only'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentPattern: 'plugin\\_éclair%', nextPattern: 'plugin\\_éclair%');
    $t->same(true, $result['currentIndexUsable']);
    $t->same(true, $result['nextIndexUsable']);
    $t->same('plugin_éclair', $result['currentRange']['lowerInclusive']);
    $t->same([9], $result['yieldedRowids']);
    $t->same(['key' => 'plugin_éclair', 'rowid' => 9], $result['highWaterToken']);
};

$tests['utf16 nocase like rtrim current source next169 rejects zero page size'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(pageSize: 0));
};

$tests['utf16 nocase like rtrim current source next169 rejects bad token shape'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(token: ['key' => 'plugin_cache']));
};

return $tests;
