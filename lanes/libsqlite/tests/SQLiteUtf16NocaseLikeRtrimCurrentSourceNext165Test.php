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
    int $currentSchemaCookie = 11,
    int $nextSchemaCookie = 11,
) use ($currentRows, $nextRows, $enc, $code): array {
    return SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumePlan(
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
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSixFive'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'case insensitive like' => ['caseSensitiveLike', false],
    'ascii nocase only' => ['asciiNocaseOnly', true],
    'rtrim trims only space' => ['rtrimTrimsOnlyAsciiSpace', true],
    'normalizes prepared pattern bytes' => ['normalizesPreparedPatternBytes', true],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSixTwo'],
    'current source stable' => ['currentSource', 'stable'],
    'next source stable' => ['nextSource', 'stable'],
    'current schema cookie stable' => ['currentSchemaCookie', 11],
    'next schema cookie stable' => ['nextSchemaCookie', 11],
    'current pattern decoded' => ['currentPattern', 'plugin\\_cache%'],
    'next pattern decoded' => ['nextPattern', 'plugin\\_cache%'],
    'same decoded pattern' => ['sameDecodedPattern', true],
    'current escape decoded' => ['currentEscape', '\\'],
    'next escape decoded' => ['nextEscape', '\\'],
    'same decoded escape' => ['sameDecodedEscape', true],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current range lower' => ['currentRange.lowerInclusive', 'plugin_cache'],
    'next range upper' => ['nextRange.upperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'last token key' => ['lastYielded.key', 'plugin_cache_alpha'],
    'last token rowid' => ['lastYielded.rowid', 2],
    'current matches' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next matches' => ['nextMatchedRowids', [7, 1, 2, 3, 8, 4]],
    'current key row one' => ['currentMatchedKeys.1', 'plugin_cache'],
    'current key row four keeps tab' => ['currentMatchedKeys.4', "plugin_cache_tab\t"],
    'next key row seven folds ascii' => ['nextMatchedKeys.7', 'plugin_cache_gamma'],
    'next key row nine absent unicode nocase' => ['nextMatchedKeys.9', null],
    'current after token' => ['currentAfterTokenRowids', [3, 4]],
    'next after token' => ['nextAfterTokenRowids', [3, 8, 7, 4]],
    'next before token' => ['nextBeforeOrAtTokenRowids', [1, 2]],
    'retained after token' => ['retainedAfterTokenRowids', [3, 4]],
    'entered after token' => ['enteredAfterTokenRowids', [8, 7]],
    'exited after token' => ['exitedAfterTokenRowids', []],
    'new before token empty' => ['newBeforeTokenRowids', []],
    'moved before token empty' => ['retainedMovedAcrossTokenRowids', []],
    'byte reasons' => ['byteReprepareReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes']],
    'semantic rowset reasons' => ['semanticInvalidationReasons', ['candidate-rowset', 'matched-rowset']],
    'base invalidation rowset bytes' => ['baseInvalidationReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes', 'candidate-rowset', 'matched-rowset']],
    'resume reasons empty' => ['resumeReasons', []],
    'must not reprepare' => ['mustReprepareBeforeResume', false],
    'safe resume' => ['safeToResumeFromToken', true],
    'resume rowids' => ['resumePlanRowids', [3, 8, 7, 4]],
    'resume mode' => ['resumePlanMode', 'continue-after-last-yielded-key-rowid'],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-pattern-normalization'],
    'dependency cursor' => ['dependencies.1', 'sqlite-nocase-like-rtrim-resume-cursor'],
    'dependency current source' => ['dependencies.2', 'sqlite-current-source-nextoneSixFive'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSixFive ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSixFive source change forces reprepare from range start'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentSource: 'main.wp_options@164', nextSource: 'main.wp_options@165');
    $t->same(['source-name', 'candidate-rowset', 'matched-rowset'], $result['semanticInvalidationReasons']);
    $t->same(['semantic-invalidation'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
    $t->same(false, $result['safeToResumeFromToken']);
    $t->same($result['nextMatchedRowids'], $result['resumePlanRowids']);
    $t->same('reprepare-from-range-start', $result['resumePlanMode']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive schema cookie change forces reprepare'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentSchemaCookie: 164, nextSchemaCookie: 165);
    $t->same(['schema-cookie', 'candidate-rowset', 'matched-rowset'], $result['semanticInvalidationReasons']);
    $t->same(['semantic-invalidation'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive new row before token forces reprepare'] = static function (TestRunner $t) use ($plan, $currentRows, $nextRows, $row): void {
    $next = array_merge($nextRows, [$row(10, 'plugin_cache_aaa', 'UTF-16LE')]);
    $result = $plan($currentRows, $next);
    $t->same([1, 10, 2], $result['nextBeforeOrAtTokenRowids']);
    $t->same([10], $result['newBeforeTokenRowids']);
    $t->same(['entered-before-token'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
    $t->same($result['nextMatchedRowids'], $result['resumePlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive retained row moved across token forces reprepare'] = static function (TestRunner $t) use ($plan, $currentRows, $row): void {
    $next = [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row(3, 'plugin_cache_aaa', 'UTF-16LE'),
        $row(4, 'plugin_cache_tab' . "\t", 'UTF-16BE'),
    ];
    $result = $plan($currentRows, $next);
    $t->same([3], $result['retainedMovedAcrossTokenRowids']);
    $t->same(['retained-moved-across-token'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive missing token forces range start'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(token: null);
    $t->same(null, $result['lastYielded']);
    $t->same(['no-yield-token'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
    $t->same($result['nextMatchedRowids'], $result['resumePlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive malformed next text forces reprepare'] = static function (TestRunner $t) use ($plan, $nextRows, $bad): void {
    $next = array_merge($nextRows, [$bad(11, "\x00\xd8", 2)]);
    $result = $plan(next: $next);
    $t->same([11], $result['nextMalformedRowids']);
    $t->same(['semantic-invalidation', 'malformed-text'], $result['resumeReasons']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['nextErrors'][11]);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive decoded pattern change forces semantic reprepare'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(nextPattern: 'plugin\\_cache');
    $t->same(false, $result['sameDecodedPattern']);
    $t->same(true, in_array('pattern-text', $result['semanticInvalidationReasons'], true));
    $t->same(['semantic-invalidation'], $result['resumeReasons']);
    $t->same(true, $result['mustReprepareBeforeResume']);
};

$tests['utf16 nocase like rtrim current source nextOneSixFive invalid token shape throws'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc, $code): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumePlan(
        $currentRows,
        $nextRows,
        $enc('plugin\\_cache%', 'UTF-8'),
        $code('UTF-8'),
        $enc('plugin\\_cache%', 'UTF-8'),
        $code('UTF-8'),
        lastYielded: ['key' => 'plugin_cache'],
    ));
};

return $tests;
