<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'plugin_alpha   ', 'UTF-8'),
    $row(2, 'Plugin_Beta', 'UTF-16LE'),
    $row(3, 'plugin_beta ', 'UTF-16BE'),
    $row(4, "plugin_beta\t", 'UTF-8'),
    $row(5, 'plugin_cache%literal', 'UTF-16LE'),
    $row(6, 'plugin_cache_literal ', 'UTF-16BE'),
    $row(7, 'plugin_Éclair ', 'UTF-16LE'),
    $row(8, 'PLUGIN_zeta', 'UTF-8'),
    $row(9, 'theme_plugin', 'UTF-8'),
    $row(10, 'plugin_😀  ', 'UTF-16LE'),
    $row(11, 'plug', 'UTF-8'),
    $bad(12, "p\x00l", 2),
];
$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'plugin_beta ', 'UTF-16LE'),
    $row(3, 'plugin_Beta\t', 'UTF-16BE'),
    $row(4, "plugin_beta\t", 'UTF-8'),
    $row(5, 'plugin_cache%literal ', 'UTF-16LE'),
    $row(6, 'plugin_cache_literal ', 'UTF-16BE'),
    $row(7, 'plugin_éclair ', 'UTF-16LE'),
    $row(8, 'PLUGIN_zeta   ', 'UTF-16BE'),
    $row(10, 'plugin_😀', 'UTF-16LE'),
    $row(13, 'plugin_fresh ', 'UTF-8'),
    $bad(14, "\x3d\xd8", 2),
];

$plan = static fn (
    string $pattern = 'plugin\_%',
    ?string $escape = '\\',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@145',
    string $nextSource = 'main.app_settings@146',
    int $currentCookie = 145,
    int $nextCookie = 146,
): array => SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'operator' => ['plugin\_%', '\\', 'operator', 'LIKE'],
    'expression' => ['plugin\_%', '\\', 'expression', 'rtrim(option_name) COLLATE NOCASE'],
    'collation' => ['plugin\_%', '\\', 'collation', 'NOCASE'],
    'escape' => ['plugin\_%', '\\', 'escape', '\\'],
    'range lower' => ['plugin\_%', '\\', 'range.lowerInclusive', 'plugin_'],
    'range upper' => ['plugin\_%', '\\', 'range.upperBound', 'plugin`'],
    'like plan prefix' => ['plugin\_%', '\\', 'likePlan.prefix', 'plugin_'],
    'like plan prefix chars' => ['plugin\_%', '\\', 'likePlan.prefixCharacters', 7],
    'like plan ascii' => ['plugin\_%', '\\', 'likePlan.prefixIsAscii', true],
    'like plan index usable' => ['plugin\_%', '\\', 'likePlan.indexUsable', true],
    'range usable' => ['plugin\_%', '\\', 'rangeUsable', true],
    'index key' => ['plugin\_%', '\\', 'indexKey', 'ascii_lower(rtrim(option_name, space))'],
    'residual rtrim marker' => ['plugin\_%', '\\', 'residualUsesRtrimText', true],
    'nocase ascii marker' => ['plugin\_%', '\\', 'nocaseIsAsciiOnly', true],
    'rtrim ascii space marker' => ['plugin\_%', '\\', 'rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['plugin\_%', '\\', 'currentSource', 'main.app_settings@145'],
    'next source' => ['plugin\_%', '\\', 'nextSource', 'main.app_settings@146'],
    'current cookie' => ['plugin\_%', '\\', 'currentSchemaCookie', 145],
    'next cookie' => ['plugin\_%', '\\', 'nextSchemaCookie', 146],
    'current order rowids' => ['plugin\_%', '\\', 'currentOrderRowids', [11, 1, 2, 3, 4, 5, 6, 8, 7, 10, 9]],
    'next order rowids' => ['plugin\_%', '\\', 'nextOrderRowids', [1, 2, 4, 3, 5, 6, 13, 8, 7, 10]],
    'current candidate rowids' => ['plugin\_%', '\\', 'currentCandidateRowids', [1, 2, 3, 4, 5, 6, 8, 7, 10]],
    'next candidate rowids' => ['plugin\_%', '\\', 'nextCandidateRowids', [1, 2, 4, 3, 5, 6, 13, 8, 7, 10]],
    'current matched rowids' => ['plugin\_%', '\\', 'currentMatchedRowids', [1, 2, 3, 4, 5, 6, 8, 7, 10]],
    'next matched rowids' => ['plugin\_%', '\\', 'nextMatchedRowids', [1, 2, 4, 3, 5, 6, 13, 8, 7, 10]],
    'current false positives none' => ['plugin\_%', '\\', 'currentFalsePositiveRowids', []],
    'next false positives none' => ['plugin\_%', '\\', 'nextFalsePositiveRowids', []],
    'retained matched rowids' => ['plugin\_%', '\\', 'retainedMatchedRowids', [1, 2, 3, 4, 5, 6, 8, 7, 10]],
    'entered matched rowids' => ['plugin\_%', '\\', 'enteredMatchedRowids', [13]],
    'exited matched rowids' => ['plugin\_%', '\\', 'exitedMatchedRowids', []],
    'current rtrim alpha' => ['plugin\_%', '\\', 'currentRtrimTexts.1', 'plugin_alpha'],
    'next rtrim zeta' => ['plugin\_%', '\\', 'nextRtrimTexts.8', 'PLUGIN_zeta'],
    'current key beta uppercase folds' => ['plugin\_%', '\\', 'currentKeys.2', 'plugin_beta'],
    'next key zeta uppercase folds' => ['plugin\_%', '\\', 'nextKeys.8', 'plugin_zeta'],
    'current text tab not trimmed' => ['plugin\_%', '\\', 'currentRtrimTexts.4', "plugin_beta\t"],
    'next text tab not trimmed' => ['plugin\_%', '\\', 'nextRtrimTexts.3', 'plugin_Beta\t'],
    'current utf16le encoding' => ['plugin\_%', '\\', 'currentEncodings.2', 'UTF-16LE'],
    'current utf16be encoding' => ['plugin\_%', '\\', 'currentEncodings.3', 'UTF-16BE'],
    'next fresh encoding' => ['plugin\_%', '\\', 'nextEncodings.13', 'UTF-8'],
    'current malformed rowids' => ['plugin\_%', '\\', 'currentMalformedRowids', [12]],
    'next malformed rowids' => ['plugin\_%', '\\', 'nextMalformedRowids', [14]],
    'current malformed error' => ['plugin\_%', '\\', 'currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin\_%', '\\', 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed text rowids' => ['plugin\_%', '\\', 'changedTextRowids', [1, 2, 3, 5, 7, 8, 9, 10, 11, 13]],
    'changed rtrim rowids' => ['plugin\_%', '\\', 'changedRtrimRowids', [2, 3, 7, 9, 11, 13]],
    'changed key rowids' => ['plugin\_%', '\\', 'changedNocaseRtrimKeyRowids', [3, 7, 9, 11, 13]],
    'changed bytes rowids' => ['plugin\_%', '\\', 'changedBytesRowids', [1, 2, 3, 5, 7, 8, 9, 10, 11, 13]],
    'changed encoding rowids' => ['plugin\_%', '\\', 'changedEncodingRowids', [1, 8, 9, 11, 13]],
    'cursor invalidated' => ['plugin\_%', '\\', 'cursorInvalidated', true],
    'cursor not reusable' => ['plugin\_%', '\\', 'cursorReusable', false],
    'reason source' => ['plugin\_%', '\\', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['plugin\_%', '\\', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['plugin\_%', '\\', 'invalidationReasons.2', 'malformed-text'],
    'reason text' => ['plugin\_%', '\\', 'invalidationReasons.3', 'text-value'],
    'reason rtrim' => ['plugin\_%', '\\', 'invalidationReasons.4', 'rtrim-value'],
    'reason key' => ['plugin\_%', '\\', 'invalidationReasons.5', 'nocase-rtrim-key'],
    'reason bytes' => ['plugin\_%', '\\', 'invalidationReasons.6', 'encoded-bytes'],
    'reason encoding' => ['plugin\_%', '\\', 'invalidationReasons.7', 'text-encoding'],
    'reason candidate' => ['plugin\_%', '\\', 'invalidationReasons.8', 'candidate-rowset'],
    'reason matched' => ['plugin\_%', '\\', 'invalidationReasons.9', 'matched-rowset'],
    'dependency decoder' => ['plugin\_%', '\\', 'dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency like' => ['plugin\_%', '\\', 'dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['plugin\_%', '\\', 'dependencies.2', 'sqlite-rtrim-expression-index'],
    'dependency current source' => ['plugin\_%', '\\', 'dependencies.3', 'sqlite-current-source-next146'],
    'escaped percent range lower' => ['plugin\_cache\%%', '\\', 'range.lowerInclusive', 'plugin_cache%'],
    'escaped percent range upper' => ['plugin\_cache\%%', '\\', 'range.upperBound', 'plugin_cache&'],
    'escaped percent current rowids' => ['plugin\_cache\%%', '\\', 'currentMatchedRowids', [5]],
    'escaped percent next rowids' => ['plugin\_cache\%%', '\\', 'nextMatchedRowids', [5]],
    'escaped percent underscore false positive avoided' => ['plugin\_cache\%%', '\\', 'currentFalsePositiveRowids', []],
    'single underscore zeta current rowids' => ['plugin\_z___', '\\', 'currentMatchedRowids', [8]],
    'single underscore zeta next rowids' => ['plugin\_z___', '\\', 'nextMatchedRowids', [8]],
    'single underscore fresh next rowids' => ['plugin\_f____', '\\', 'nextMatchedRowids', [13]],
    'no prefix rejected range' => ['%plugin%', null, 'range', null],
    'no prefix rejected reason' => ['%plugin%', null, 'likePlan.rejectedReason', 'no_fixed_prefix'],
    'no prefix cursor reason' => ['%plugin%', null, 'invalidationReasons.2', 'no_fixed_prefix'],
];

foreach ($cases as $name => [$pattern, $escape, $path, $expected]) {
    $tests['nocase like rtrim current source next146 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $escape), $path));
    };
}

$tests['nocase like rtrim current source next146 stable rows reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_alpha   ', 'UTF-8'), $row(2, 'Plugin_Beta', 'UTF-16LE')];
    $plan = SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, 'plugin\_%', '\\', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentMatchedRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['nocase like rtrim current source next146 stable no-prefix still not reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_alpha   ', 'UTF-8'), $row(2, 'theme_plugin', 'UTF-16LE')];
    $plan = SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, '%plugin%', null, 'stable', 'stable', 7, 7);
    $t->same([], $plan['currentMatchedRowids']);
    $t->same(['no_fixed_prefix'], $plan['invalidationReasons']);
    $t->same(false, $plan['cursorReusable']);
};

$tests['nocase like rtrim current source next146 rejects missing option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan([['option_name_bytes' => 'plugin', 'text_encoding' => 1]], [], 'plugin%'));
};

$tests['nocase like rtrim current source next146 rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'text_encoding' => 1]], [], 'plugin%'));
};

$tests['nocase like rtrim current source next146 rejects missing encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name_bytes' => 'plugin']], [], 'plugin%'));
};

$tests['nocase like rtrim current source next146 records unsupported encoding as malformed'] = static function (TestRunner $t): void {
    $plan = SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name_bytes' => 'plugin', 'text_encoding' => 9]], [], 'plugin%');
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE', $plan['currentErrors'][1]);
};

$tests['nocase like rtrim current source next146 rejects bad escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, [], 'plugin%', 'xx'));
};

return $tests;
