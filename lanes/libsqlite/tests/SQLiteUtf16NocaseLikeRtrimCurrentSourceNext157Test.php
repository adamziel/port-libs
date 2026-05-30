<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad UTF-16 test encoding'),
        },
    ];
};
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra ', 'UTF-16LE'),
    $row(4, "plugin_cache\t", 'UTF-16BE'),
    $row(5, "plugin_cache\xc2\xa0", 'UTF-16LE'),
    $row(6, 'plugin_%_cache', 'UTF-16BE'),
    $row(7, 'PLUGIN_zeta ', 'UTF-16LE'),
    $row(8, 'plugin_Éclair ', 'UTF-16BE'),
    $row(9, 'plugin_éclair ', 'UTF-16LE'),
    $row(10, 'theme_cache', 'UTF-16LE'),
    $row(11, 'plug', 'UTF-16BE'),
    $bad(12, "\x3d\xd8", 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra_v2 ', 'UTF-16LE'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, "plugin_cache\xc2\xa0", 'UTF-16LE'),
    $row(6, 'plugin_%_cache ', 'UTF-16BE'),
    $row(7, 'PLUGIN_zeta', 'UTF-16BE'),
    $row(8, 'plugin_Éclair ', 'UTF-16BE'),
    $row(9, 'plugin_éclair ', 'UTF-16BE'),
    $row(13, 'plugin_cache_new ', 'UTF-16LE'),
    $bad(14, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern = 'plugin\_%',
    ?string $escape = '\\',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@156',
    string $nextSource = 'main.wp_options@157',
    int $currentCookie = 156,
    int $nextCookie = 157,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan(
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
    'operator' => ['operator', 'LIKE'],
    'expression marks utf16 source' => ['expression', 'rtrim(option_name) COLLATE NOCASE /* UTF-16 source */'],
    'collation' => ['collation', 'NOCASE'],
    'source encoding' => ['sourceTextEncoding', 'UTF-16'],
    'accepted encoding le' => ['acceptedTextEncodings.0', 'UTF-16LE'],
    'accepted encoding be' => ['acceptedTextEncodings.1', 'UTF-16BE'],
    'byte order sensitive' => ['utf16ByteOrderSensitive', true],
    'pattern' => ['pattern', 'plugin\_%'],
    'escape' => ['escape', '\\'],
    'range lower' => ['range.lowerInclusive', 'plugin_'],
    'range upper' => ['range.upperBound', 'plugin`'],
    'like prefix' => ['likePlan.prefix', 'plugin_'],
    'like prefix chars' => ['likePlan.prefixCharacters', 7],
    'like ascii prefix' => ['likePlan.prefixIsAscii', true],
    'like index usable' => ['likePlan.indexUsable', true],
    'range usable' => ['rangeUsable', true],
    'index key' => ['indexKey', 'ascii_lower(rtrim(option_name, space))'],
    'residual rtrim text' => ['residualUsesRtrimText', true],
    'nocase ascii marker' => ['nocaseIsAsciiOnly', true],
    'rtrim ascii space marker' => ['rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['currentSource', 'main.wp_options@156'],
    'next source' => ['nextSource', 'main.wp_options@157'],
    'current cookie' => ['currentSchemaCookie', 156],
    'next cookie' => ['nextSchemaCookie', 157],
    'current order rowids' => ['currentOrderRowids', [11, 6, 1, 2, 4, 3, 5, 7, 8, 9, 10]],
    'next order rowids' => ['nextOrderRowids', [6, 1, 2, 4, 3, 13, 5, 7, 8, 9]],
    'current candidates' => ['currentCandidateRowids', [6, 1, 2, 4, 3, 5, 7, 8, 9]],
    'next candidates' => ['nextCandidateRowids', [6, 1, 2, 4, 3, 13, 5, 7, 8, 9]],
    'current matched' => ['currentMatchedRowids', [6, 1, 2, 4, 3, 5, 7, 8, 9]],
    'next matched' => ['nextMatchedRowids', [6, 1, 2, 4, 3, 13, 5, 7, 8, 9]],
    'entered matched' => ['enteredMatchedRowids', [13]],
    'exited matched' => ['exitedMatchedRowids', []],
    'retained matched' => ['retainedMatchedRowids', [6, 1, 2, 4, 3, 5, 7, 8, 9]],
    'false positives current' => ['currentFalsePositiveRowids', []],
    'false positives next' => ['nextFalsePositiveRowids', []],
    'current key trims and folds' => ['currentKeys.1', 'plugin_cache'],
    'next key keeps row two same after spaces' => ['nextKeys.2', 'plugin_cache'],
    'tab not rtrimmed' => ['currentRtrimTexts.4', "plugin_cache\t"],
    'nbsp not rtrimmed' => ['currentRtrimTexts.5', "plugin_cache\xc2\xa0"],
    'ascii nocase does not fold upper e acute' => ['currentKeys.8', 'plugin_Éclair'],
    'ascii nocase lower e acute key' => ['currentKeys.9', 'plugin_éclair'],
    'current encoding row one' => ['currentEncodings.1', 'UTF-16LE'],
    'next encoding row one' => ['nextEncodings.1', 'UTF-16BE'],
    'current byte order row one' => ['currentByteOrders.1', 'little'],
    'next byte order row one' => ['nextByteOrders.1', 'big'],
    'current byte order row two' => ['currentByteOrders.2', 'big'],
    'next byte order row two' => ['nextByteOrders.2', 'big'],
    'changed text' => ['changedTextRowids', [1, 2, 3, 6, 7, 10, 11, 13]],
    'changed rtrim' => ['changedRtrimRowids', [3, 10, 11, 13]],
    'changed key' => ['changedNocaseRtrimKeyRowids', [3, 10, 11, 13]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3, 4, 6, 7, 9, 10, 11, 13]],
    'changed encoding' => ['changedEncodingRowids', [1, 4, 7, 9, 10, 11, 13]],
    'changed byte order' => ['changedByteOrderRowids', [1, 4, 7, 9, 10, 11, 13]],
    'current malformed rowids' => ['currentMalformedRowids', [12]],
    'next malformed rowids' => ['nextMalformedRowids', [14]],
    'current malformed error' => ['currentErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['invalidationReasons.2', 'malformed-text'],
    'reason text' => ['invalidationReasons.3', 'text-value'],
    'reason rtrim' => ['invalidationReasons.4', 'rtrim-value'],
    'reason key' => ['invalidationReasons.5', 'nocase-rtrim-key'],
    'reason bytes' => ['invalidationReasons.6', 'encoded-bytes'],
    'reason encoding' => ['invalidationReasons.7', 'text-encoding'],
    'reason candidate' => ['invalidationReasons.8', 'candidate-rowset'],
    'reason matched' => ['invalidationReasons.9', 'matched-rowset'],
    'reason byte order' => ['invalidationReasons.10', 'utf16-byte-order'],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-source-decode'],
    'dependency like' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-index'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneFiveSeven'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneFiveSeven ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneFiveSeven escaped percent literal'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin\_\%\_cache%', '\\');
    $t->same([6], $result['currentMatchedRowids']);
    $t->same([6], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven single wildcard zeta'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin\_z___', '\\');
    $t->same([7], $result['currentMatchedRowids']);
    $t->same([7], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven unicode keys remain distinct'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan();
    $t->same('plugin_Éclair', $result['currentKeys'][8]);
    $t->same('plugin_éclair', $result['currentKeys'][9]);
    $t->same(false, $result['currentKeys'][8] === $result['currentKeys'][9]);
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven no prefix is not reusable'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('%cache%', null);
    $t->same(null, $result['range']);
    $t->same('no_fixed_prefix', $result['likePlan']['rejectedReason']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven stable byte order reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'Plugin_Cache   ', 'UTF-16LE'), $row(2, 'plugin_cache', 'UTF-16BE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan($rows, $rows, 'plugin\_%', '\\', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['changedByteOrderRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven stable byte order change invalidates'] = static function (TestRunner $t) use ($row): void {
    $current = [$row(1, 'Plugin_Cache   ', 'UTF-16LE')];
    $next = [$row(1, 'Plugin_Cache   ', 'UTF-16BE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan($current, $next, 'plugin\_%', '\\', 'stable', 'stable', 7, 7);
    $t->same([1], $result['changedByteOrderRowids']);
    $t->same(['encoded-bytes', 'text-encoding', 'utf16-byte-order'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven rejects utf8 rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'option_name_bytes' => 'plugin', 'text_encoding' => 1]], [], 'plugin%'));
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven rejects missing text encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'option_name_bytes' => 'plugin']], [], 'plugin%'));
};

$tests['utf16 nocase like rtrim current source nextOneFiveSeven rejects bad escape'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan($currentRows, [], 'plugin%', 'xx'));
};

return $tests;
