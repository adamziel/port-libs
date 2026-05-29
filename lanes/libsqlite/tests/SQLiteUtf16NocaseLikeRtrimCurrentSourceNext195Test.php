<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc195 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row195 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc195($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current195 = [
    $row195(1, 'plugin_%_cache', 'UTF-16LE'),
    $row195(2, 'Plugin_%_Cache  ', 'UTF-16BE'),
    $row195(3, 'plugin_%_cache_alpha', 'UTF-16LE'),
    $row195(4, 'plugin_%_cache_beta  ', 'UTF-16BE'),
    $row195(5, "plugin_%_cache\t", 'UTF-16LE'),
    $row195(6, 'plugin_%_cachd', 'UTF-16BE'),
    $row195(7, 'plugin_a_cache', 'UTF-16LE'),
    $row195(8, 'theme_%_cache', 'UTF-16BE'),
];
$next195 = [
    $row195(1, 'plugin_%_cache', 'UTF-16BE'),
    $row195(2, 'Plugin_%_Cache', 'UTF-16LE'),
    $row195(3, 'plugin_%_cache', 'UTF-16LE'),
    $row195(4, 'plugin_%_cache_beta', 'UTF-16BE'),
    $row195(5, "plugin_%_cache\t", 'UTF-16LE'),
    $row195(6, 'plugin_%_cachd', 'UTF-16BE'),
    $row195(9, 'PLUGIN_%_CACHE', 'UTF-8'),
    $row195(10, 'plugin_%_cache_zeta', 'UTF-16LE'),
];

$plan195 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_!%!_cache',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@194',
    string $nextSource = 'main.wp_options@195',
    int $currentCookie = 194,
    int $nextCookie = 195,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedLiteralTailPlan(
    $current ?? $current195,
    $next ?? $next195,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt195 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases195 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next195'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escaped literal tail */'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-next183'],
    'pattern' => ['pattern', 'plugin!_!%!_cache'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.wp_options@194'],
    'next source' => ['nextSource', 'main.wp_options@195'],
    'current cookie' => ['currentSchemaCookie', 194],
    'next cookie' => ['nextSchemaCookie', 195],
    'prefix' => ['prefix', 'plugin_%_cache'],
    'prefix characters' => ['prefixCharacters', 14],
    'prefix ascii' => ['prefixIsAscii', true],
    'range lower' => ['rangeLowerInclusive', 'plugin_%_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_%_cachf'],
    'index usable' => ['indexUsable', true],
    'uses prefix cursor' => ['usesPrefixRangeCursor', true],
    'no full scan fallback' => ['usesFullScanFallback', false],
    'current candidates' => ['currentCandidateRowids', [1, 2, 5, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 9, 5, 4, 10]],
    'candidate retained' => ['candidateRetainedRowids', [1, 2, 5, 3, 4]],
    'candidate exited' => ['candidateExitedRowids', []],
    'candidate entered' => ['candidateEnteredRowids', [9, 10]],
    'current matched' => ['currentMatchedRowids', [1, 2]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 9]],
    'matched retained' => ['matchedRetainedRowids', [1, 2]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [3, 9]],
    'current false positives' => ['currentRangeFalsePositiveRowids', [5, 3, 4]],
    'next false positives' => ['nextRangeFalsePositiveRowids', [5, 4, 10]],
    'false retained' => ['falsePositiveRetainedRowids', [5, 4]],
    'false exited' => ['falsePositiveExitedRowids', [3]],
    'false entered' => ['falsePositiveEnteredRowids', [10]],
    'false promoted' => ['falsePositivePromotedRowids', [3]],
    'matched demoted empty' => ['matchedDemotedToFalsePositiveRowids', []],
    'current rtrim two' => ['currentRtrimTexts.2', 'Plugin_%_Cache'],
    'current rtrim five keeps tab' => ['currentRtrimTexts.5', "plugin_%_cache\t"],
    'next rtrim four' => ['nextRtrimTexts.4', 'plugin_%_cache_beta'],
    'current nocase two' => ['currentNocaseKeys.2', 'plugin_%_cache'],
    'next nocase nine' => ['nextNocaseKeys.9', 'plugin_%_cache'],
    'current matched text one' => ['currentMatchedTexts.1', 'plugin_%_cache'],
    'next matched text nine' => ['nextMatchedTexts.9', 'PLUGIN_%_CACHE'],
    'changed rtrim' => ['changedRtrimRowids', [3]],
    'changed nocase' => ['changedNocaseKeyRowids', [3]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3, 4]],
    'literal reason false rowset' => ['literalTailInvalidationReasons.0', 'range-residual-false-positive-rowset'],
    'literal reason promoted' => ['literalTailInvalidationReasons.1', 'false-positive-promoted-to-match'],
    'base reason source' => ['baseInvalidationReasons.0', 'source-name'],
    'base reason schema' => ['baseInvalidationReasons.1', 'schema-cookie'],
    'base reason decoded text' => ['baseInvalidationReasons.2', 'decoded-text'],
    'base reason rtrim' => ['baseInvalidationReasons.3', 'rtrim-expression'],
    'base reason nocase' => ['baseInvalidationReasons.4', 'nocase-key'],
    'base reason bytes' => ['baseInvalidationReasons.5', 'encoded-bytes'],
    'base reason matched' => ['baseInvalidationReasons.6', 'matched-rowset'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'must recheck residual' => ['mustRecheckResidualForRangeCandidates', true],
    'rtrim ascii only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency escaped range' => ['dependencies.1', 'sqlite-like-escaped-literal-prefix-range'],
    'dependency residual' => ['dependencies.2', 'sqlite-rtrim-residual-match'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next195'],
];

foreach ($cases195 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next195 ' . $name] = static function (TestRunner $t) use ($plan195, $valueAt195, $path, $expected): void {
        $t->same($expected, $valueAt195($plan195(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next195 stable exact literal cursor is reusable'] = static function (TestRunner $t) use ($row195): void {
    $rows = [
        $row195(1, 'plugin_%_cache', 'UTF-16LE'),
        $row195(2, 'PLUGIN_%_CACHE  ', 'UTF-16BE'),
        $row195(3, 'plugin_%_cache_alpha', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedLiteralTailPlan(
        $rows,
        $rows,
        'plugin!_!%!_cache',
        '!',
        'stable',
        'stable',
        9,
        9,
    );
    $t->same([1, 2, 3], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([3], $result['currentRangeFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next195 demoted exact row forces residual restart'] = static function (TestRunner $t) use ($row195): void {
    $current = [
        $row195(1, 'plugin_%_cache', 'UTF-16LE'),
        $row195(2, 'PLUGIN_%_CACHE', 'UTF-16BE'),
    ];
    $next = [
        $row195(1, 'plugin_%_cache_more', 'UTF-16LE'),
        $row195(2, 'PLUGIN_%_CACHE', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedLiteralTailPlan($current, $next, 'plugin!_!%!_cache', '!', 'same', 'same', 1, 1);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([2], $result['nextMatchedRowids']);
    $t->same([1], $result['matchedDemotedToFalsePositiveRowids']);
    $t->same(['range-residual-false-positive-rowset', 'match-demoted-to-false-positive'], $result['literalTailInvalidationReasons']);
    $t->same(true, $result['cursorInvalidated']);
};

$tests['utf16 nocase like rtrim current source next195 changed escape disables escaped literal semantics'] = static function (TestRunner $t) use ($plan195): void {
    $result = $plan195(pattern: 'plugin#_#%#_cache', escape: '#');
    $t->same('plugin_%_cache', $result['prefix']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2, 3, 9], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source next195 unescaped wildcard broadens range'] = static function (TestRunner $t) use ($plan195): void {
    $result = $plan195(pattern: 'plugin_%_cache', escape: null);
    $t->same('plugin', $result['prefix']);
    $t->same('plugin', $result['rangeLowerInclusive']);
    $t->same('plugio', $result['rangeUpperBound']);
    $t->same([6, 1, 2, 5, 3, 4, 7], $result['currentCandidateRowids']);
    $t->same([6, 1, 2, 3, 9, 5, 4, 10], $result['nextCandidateRowids']);
};

$tests['utf16 nocase like rtrim current source next195 malformed row is isolated'] = static function (TestRunner $t) use ($current195, $next195): void {
    $badCurrent = array_merge($current195, [['option_id' => 11, 'option_name_bytes' => "\x00\xd8", 'text_encoding' => 2]]);
    $badNext = array_merge($next195, [['option_id' => 12, 'option_name_bytes' => "x\0y", 'text_encoding' => 2]]);
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedLiteralTailPlan($badCurrent, $badNext);
    $t->same([11], $result['currentMalformedRowids']);
    $t->same([12], $result['nextMalformedRowids']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['currentErrors'][11]);
    $t->same('SQLite encoding source UTF-16 text payload has an odd byte length', $result['nextErrors'][12]);
    $t->same(true, in_array('malformed-text', $result['baseInvalidationReasons'], true));
};

return $tests;
