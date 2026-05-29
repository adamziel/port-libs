<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan;

$tests = [];

$row = static fn (int $id, string $name, string $encoding = 'UTF-16LE', string $storage = 'text'): array => [
    'option_id' => $id,
    'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
    'storage_class' => $storage,
];
$bad = static fn (int $id, string $bytes, int $encoding = 2): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE', 'UTF-16LE'),
    $row(4, 'plugin_cache_extra', 'UTF-16BE'),
    $row(5, 'plugin_cache_BLOB', 'UTF-16LE', 'blob'),
    $row(6, 'plugin_caché', 'UTF-16LE'),
    $row(7, 'plugin_éclair', 'UTF-16BE'),
    $row(8, 'plugin_😀_cache', 'UTF-16LE'),
    $row(9, 'theme_cache', 'UTF-16LE'),
    $bad(10, "p\x00l\x00u\x00g\x00i\x00n\x00_"),
];

$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE', 'UTF-16LE'),
    $row(4, 'plugin_cache_extra_v2', 'UTF-16BE'),
    $row(5, 'plugin_cache_blob', 'UTF-16LE'),
    $row(6, 'plugin_caché', 'UTF-16BE'),
    $row(7, 'Plugin_Éclair', 'UTF-16BE'),
    $row(8, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $row(11, 'plugin_cache_new', 'UTF-16LE'),
    $row(12, 'PLUGIN_cache_new', 'UTF-16BE'),
    $bad(13, "\x3d\xd8"),
];

$plan = static fn (
    string $pattern = 'plugin_cache*',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@147',
    string $nextSource = 'main.wp_options@148',
    string $currentEncoding = 'UTF-16LE',
    string $nextEncoding = 'UTF-16BE',
): array => SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $currentSource,
    $nextSource,
    $currentEncoding,
    $nextEncoding,
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
    'operator recorded' => ['operator', 'GLOB'],
    'collation recorded' => ['collation', 'NOCASE'],
    'glob residual is case sensitive' => ['globResidualCaseSensitive', true],
    'text affinity recorded' => ['affinity', 'TEXT'],
    'pattern recorded' => ['pattern', 'plugin_cache*'],
    'prefix preserves source case' => ['prefix', 'plugin_cache'],
    'prefix folded for cursor' => ['prefixFolded', 'plugin_cache'],
    'range lower folded' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper folded' => ['range.upperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current source' => ['currentSource', 'main.wp_options@147'],
    'next source' => ['nextSource', 'main.wp_options@148'],
    'current database encoding' => ['currentDatabaseEncoding', 'UTF-16LE'],
    'next database encoding' => ['nextDatabaseEncoding', 'UTF-16BE'],
    'current range lower bytes' => ['currentRangeBytesHex.lowerInclusive', '70006c007500670069006e005f0063006100630068006500'],
    'current range upper bytes' => ['currentRangeBytesHex.upperBound', '70006c007500670069006e005f0063006100630068006600'],
    'next range lower bytes' => ['nextRangeBytesHex.lowerInclusive', '0070006c007500670069006e005f00630061006300680065'],
    'next range upper bytes' => ['nextRangeBytesHex.upperBound', '0070006c007500670069006e005f00630061006300680066'],
    'range bytes changed' => ['rangeBytesChanged', true],
    'current candidates use nocase cursor' => ['currentCandidateRowids', [1, 2, 3, 5, 4]],
    'next candidates use nocase cursor' => ['nextCandidateRowids', [1, 2, 3, 5, 4, 11, 12]],
    'current matches keep only case sensitive glob rows' => ['currentRowids', [2, 5, 4]],
    'next matches keep only lowercase residual rows' => ['nextRowids', [1, 2, 5, 4, 11]],
    'retained rows' => ['retainedRowids', [2, 5, 4]],
    'entered rows' => ['enteredRowids', [1, 11]],
    'exited rows empty' => ['exitedRowids', []],
    'current residual rejects uppercase rows' => ['currentResidualRejectedRowids', [1, 3]],
    'next residual rejects uppercase rows' => ['nextResidualRejectedRowids', [3, 12]],
    'current malformed rowids' => ['currentMalformedRowids', [10]],
    'next malformed rowids' => ['nextMalformedRowids', [13]],
    'current malformed error' => ['currentErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['nextErrors.13', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current folded key row one' => ['currentKeys.1', 'plugin_cache'],
    'current text preserves row one case' => ['currentText.1', 'Plugin_Cache'],
    'next folded key row twelve' => ['nextKeys.12', 'plugin_cache_new'],
    'next text preserves row twelve case' => ['nextText.12', 'PLUGIN_cache_new'],
    'current row one bytes' => ['currentBytesHex.1', '50006c007500670069006e005f0043006100630068006500'],
    'next row one bytes' => ['nextBytesHex.1', '0070006c007500670069006e005f00630061006300680065'],
    'current row two encoding' => ['currentEncodings.2', 'UTF-16BE'],
    'next row eleven encoding' => ['nextEncodings.11', 'UTF-16LE'],
    'current blob storage' => ['currentStorage.5', 'blob'],
    'next text storage after affinity source change' => ['nextStorage.5', 'text'],
    'retained text changes' => ['retainedTextChangedRowids', [1, 4, 5]],
    'retained storage changes' => ['retainedStorageChangedRowids', [5]],
    'retained encoding changes' => ['retainedEncodingChangedRowids', [1]],
    'retained bytes changes' => ['retainedBytesChangedRowids', [1, 4, 5]],
    'first current step rowid' => ['currentPlanSteps.0.rowid', 1],
    'first current step residual false' => ['currentPlanSteps.0.residualMatch', false],
    'second current step residual true' => ['currentPlanSteps.1.residualMatch', true],
    'next uppercase step rowid' => ['nextPlanSteps.6.rowid', 12],
    'next uppercase residual false' => ['nextPlanSteps.6.residualMatch', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason malformed' => ['invalidationReasons.1', 'malformed-text'],
    'reason range bytes' => ['invalidationReasons.2', 'range-bytes'],
    'reason candidate' => ['invalidationReasons.3', 'candidate-rowset'],
    'reason matched' => ['invalidationReasons.4', 'matched-rowset'],
    'reason text' => ['invalidationReasons.5', 'text-value'],
    'reason storage' => ['invalidationReasons.6', 'storage-class'],
    'reason encoding' => ['invalidationReasons.7', 'text-encoding'],
    'reason bytes' => ['invalidationReasons.8', 'encoded-bytes'],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-glob-prefix-range'],
    'dependency nocase cursor' => ['dependencies.2', 'sqlite-nocase-collation-source-cursor'],
    'dependency residual' => ['dependencies.3', 'sqlite-glob-case-sensitive-residual'],
    'dependency marker' => ['dependencies.4', 'sqlite-current-source-next148'],
    'dependency closure note' => ['dependency_closure', 'no new support component needed; reuses native UTF-16 decoding, TEXT affinity casting, NOCASE source cursor keys, and case-sensitive GLOB residual matching'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase glob affinity current source next148 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase glob affinity current source next148 uppercase pattern folds range but residual keeps uppercase'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('PLUGIN_cache*');
    $t->same('PLUGIN_cache', $result['prefix']);
    $t->same('plugin_cache', $result['prefixFolded']);
    $t->same([1, 2, 3, 5, 4], $result['currentCandidateRowids']);
    $t->same([], $result['currentRowids']);
    $t->same([1, 2, 3, 5, 4], $result['currentResidualRejectedRowids']);
    $t->same([12], $result['nextRowids']);
};

$tests['utf16 nocase glob affinity current source next148 unicode suffix stays residual only'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin_é*');
    $t->same(['lowerInclusive' => 'plugin_é', 'upperBound' => 'plugin_ê'], $result['range']);
    $t->same([7], $result['currentCandidateRowids']);
    $t->same([7], $result['currentRowids']);
    $t->same([], $result['nextCandidateRowids']);
    $t->same([], $result['nextRowids']);
    $t->same([], $result['nextResidualRejectedRowids']);
};

$tests['utf16 nocase glob affinity current source next148 leading class has no prefix range'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('[Pp]lugin_cache*');
    $t->same(null, $result['range']);
    $t->same(false, $result['indexUsable']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentRowids']);
    $t->same('no-prefix-range', $result['invalidationReasons'][2]);
};

$tests['utf16 nocase glob affinity current source next148 stable exact cursor is reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [
        $row(1, 'plugin_cache', 'UTF-16LE'),
        $row(2, 'Plugin_Cache', 'UTF-16LE'),
        $row(3, 'theme_cache', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan($rows, $rows, 'plugin_cache*', 'stable', 'stable', 'UTF-16LE', 'UTF-16LE');
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentRowids']);
    $t->same([2], $result['currentResidualRejectedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->true($result['cursorReusable']);
};

$tests['utf16 nocase glob affinity current source next148 malformed error change invalidates only malformed text'] = static function (TestRunner $t) use ($row, $bad): void {
    $current = [$row(1, 'plugin_cache'), $bad(9, "\xff")];
    $next = [$row(1, 'plugin_cache'), $bad(9, "\x3d\xd8")];
    $result = SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan($current, $next, 'plugin_cache*', 'stable', 'stable', 'UTF-16LE', 'UTF-16LE');
    $t->same([1], $result['currentRowids']);
    $t->same([1], $result['nextRowids']);
    $t->same(['malformed-text'], $result['invalidationReasons']);
};

$tests['utf16 nocase glob affinity current source next148 rejects non integer option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan([['option_id' => '1', 'option_name_bytes' => 'x', 'text_encoding' => 2]], $nextRows, 'plugin*'));
};

$tests['utf16 nocase glob affinity current source next148 rejects missing option bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan([['option_id' => 1, 'text_encoding' => 2]], $nextRows, 'plugin*'));
};

$tests['utf16 nocase glob affinity current source next148 rejects non utf16 row encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan([['option_id' => 1, 'option_name_bytes' => 'x', 'text_encoding' => 1]], $nextRows, 'plugin*'));
};

$tests['utf16 nocase glob affinity current source next148 rejects unsupported storage class'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText('plugin_cache', 'UTF-16LE'), 'text_encoding' => 2, 'storage_class' => 'integer']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan::wordpressOptionNameGlobPlan($rows, $rows, 'p*'));
};

$tests['utf16 nocase glob affinity current source next148 rejects non utf16 database encoding'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin*', null, null, 'stable', 'stable', 'UTF-8', 'UTF-16LE'));
};

return $tests;
