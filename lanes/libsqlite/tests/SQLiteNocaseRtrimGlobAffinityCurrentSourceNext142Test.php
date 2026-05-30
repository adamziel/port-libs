<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan;

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
    $row(1, 'plugin_alpha', 'UTF-8'),
    $row(2, 'Plugin_Alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta ', 'UTF-16BE'),
    $row(4, "plugin_beta\t", 'UTF-8'),
    $row(5, 'plugin_100', 'UTF-16LE'),
    $row(6, '042', 'UTF-8'),
    $row(7, 'plugin_Éclair', 'UTF-16BE'),
    $row(8, 'plugin_éclair', 'UTF-16LE'),
    $row(9, 'theme_alpha', 'UTF-8'),
    $row(10, 'plugin_😀 ', 'UTF-16LE'),
    $bad(11, "p\x00l", 2),
];
$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'plugin_Alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-16BE'),
    $row(4, "plugin_beta\t", 'UTF-8'),
    $row(5, '100', 'UTF-16LE'),
    $row(6, '42', 'UTF-8'),
    $row(7, 'plugin_Éclair', 'UTF-16BE'),
    $row(8, 'plugin_éclair_extra', 'UTF-16LE'),
    $row(10, 'plugin_😀', 'UTF-16BE'),
    $row(12, 'plugin_fresh', 'UTF-8'),
    $bad(13, "\x3d\xd8", 2),
];

$plan = static fn (
    string $pattern = 'plugin_*',
    string $affinity = 'TEXT',
    string $collation = 'NOCASE',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@141',
    string $nextSource = 'main.wp_options@142',
    int $currentCookie = 141,
    int $nextCookie = 142,
): array => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $affinity,
    $collation,
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
    'operator' => ['plugin_*', 'TEXT', 'NOCASE', 'operator', 'GLOB'],
    'pattern' => ['plugin_*', 'TEXT', 'NOCASE', 'pattern', 'plugin_*'],
    'affinity' => ['plugin_*', 'TEXT', 'NOCASE', 'affinity', 'TEXT'],
    'collation nocase' => ['plugin_*', 'TEXT', 'NOCASE', 'collation', 'NOCASE'],
    'range lower' => ['plugin_*', 'TEXT', 'NOCASE', 'range.lowerInclusive', 'plugin_'],
    'range upper' => ['plugin_*', 'TEXT', 'NOCASE', 'range.upperBound', 'plugin`'],
    'nocase range not usable for glob' => ['plugin_*', 'TEXT', 'NOCASE', 'rangeUsable', false],
    'residual scan enabled' => ['plugin_*', 'TEXT', 'NOCASE', 'residualScan', true],
    'fallback reason' => ['plugin_*', 'TEXT', 'NOCASE', 'fallbackReason', 'glob-range-requires-binary-collation'],
    'glob bytewise residual marker' => ['plugin_*', 'TEXT', 'NOCASE', 'globResidualUsesBytewiseText', true],
    'nocase ordering marker' => ['plugin_*', 'TEXT', 'NOCASE', 'nocaseOnlyOrdersIndexKeys', true],
    'rtrim ordering marker false' => ['plugin_*', 'TEXT', 'NOCASE', 'rtrimOnlyTrimsIndexKeys', false],
    'current source' => ['plugin_*', 'TEXT', 'NOCASE', 'currentSource', 'main.wp_options@141'],
    'next source' => ['plugin_*', 'TEXT', 'NOCASE', 'nextSource', 'main.wp_options@142'],
    'current cookie' => ['plugin_*', 'TEXT', 'NOCASE', 'currentSchemaCookie', 141],
    'next cookie' => ['plugin_*', 'TEXT', 'NOCASE', 'nextSchemaCookie', 142],
    'current order rowids nocase' => ['plugin_*', 'TEXT', 'NOCASE', 'currentOrderRowids', [6, 5, 1, 2, 4, 3, 7, 8, 10, 9]],
    'next order rowids nocase' => ['plugin_*', 'TEXT', 'NOCASE', 'nextOrderRowids', [5, 6, 1, 2, 3, 4, 12, 7, 8, 10]],
    'current candidates are residual full scan' => ['plugin_*', 'TEXT', 'NOCASE', 'currentCandidateRowids', [6, 5, 1, 2, 4, 3, 7, 8, 10, 9]],
    'next candidates are residual full scan' => ['plugin_*', 'TEXT', 'NOCASE', 'nextCandidateRowids', [5, 6, 1, 2, 3, 4, 12, 7, 8, 10]],
    'current matches are bytewise lower plugin only' => ['plugin_*', 'TEXT', 'NOCASE', 'currentRowids', [5, 1, 4, 3, 7, 8, 10]],
    'next matches include repaired alpha and fresh' => ['plugin_*', 'TEXT', 'NOCASE', 'nextRowids', [1, 2, 3, 4, 12, 7, 8, 10]],
    'current residual rejects uppercase and non prefix' => ['plugin_*', 'TEXT', 'NOCASE', 'currentResidualRejectedRowids', [6, 2, 9]],
    'next residual rejects numeric and non prefix' => ['plugin_*', 'TEXT', 'NOCASE', 'nextResidualRejectedRowids', [5, 6]],
    'retained rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'retainedRowids', [1, 4, 3, 7, 8, 10]],
    'entered rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'enteredRowids', [2, 12]],
    'exited rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'exitedRowids', [5]],
    'current malformed rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'currentMalformedRowids', [11]],
    'next malformed rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'nextMalformedRowids', [13]],
    'current malformed error' => ['plugin_*', 'TEXT', 'NOCASE', 'currentErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin_*', 'TEXT', 'NOCASE', 'nextErrors.13', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed affinity rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedAffinityValueRowids', [2, 3, 5, 6, 8, 9, 10, 12]],
    'changed storage rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedStorageRowids', [9, 12]],
    'changed collation rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedCollationKeyRowids', [3, 5, 6, 8, 9, 10, 12]],
    'changed bytes rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedBytesRowids', [1, 2, 3, 5, 6, 8, 9, 10, 12]],
    'changed encoding rowids' => ['plugin_*', 'TEXT', 'NOCASE', 'changedEncodingRowids', [1, 9, 10, 12]],
    'cursor invalidated' => ['plugin_*', 'TEXT', 'NOCASE', 'cursorInvalidated', true],
    'cursor not reusable' => ['plugin_*', 'TEXT', 'NOCASE', 'cursorReusable', false],
    'reason source' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.1', 'schema-cookie'],
    'reason range fallback' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.2', 'glob-range-requires-binary-collation'],
    'reason malformed' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.3', 'malformed-text'],
    'reason affinity' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.4', 'affinity-value'],
    'reason storage' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.5', 'storage-class'],
    'reason key' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.6', 'collation-key'],
    'reason bytes' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.7', 'encoded-bytes'],
    'reason encoding' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.8', 'text-encoding'],
    'reason candidate' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.9', 'candidate-rowset'],
    'reason matched' => ['plugin_*', 'TEXT', 'NOCASE', 'invalidationReasons.10', 'matched-rowset'],
    'trace uppercase residual false' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.3.matched', false],
    'trace uppercase next residual true after lowercase p' => ['plugin_*', 'TEXT', 'NOCASE', 'nextTrace.3.matched', true],
    'trace rtrim space remains text' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.5.affinityText', 'plugin_beta '],
    'trace emoji utf16le bytes' => ['plugin_*', 'TEXT', 'NOCASE', 'currentTrace.8.bytesHex', '70006c007500670069006e005f003dd800de2000'],
    'dependency decoder' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency affinity' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.1', 'sqlite-affinity-comparison'],
    'dependency glob' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.2', 'sqlite-glob-bytewise-residual'],
    'dependency current source' => ['plugin_*', 'TEXT', 'NOCASE', 'dependencies.3', 'sqlite-current-source-next142'],
    'rtrim collation recorded' => ['plugin_beta', 'TEXT', 'RTRIM', 'collation', 'RTRIM'],
    'rtrim marker' => ['plugin_beta', 'TEXT', 'RTRIM', 'rtrimOnlyTrimsIndexKeys', true],
    'rtrim range also not usable' => ['plugin_beta', 'TEXT', 'RTRIM', 'rangeUsable', false],
    'rtrim current matches unpadded none' => ['plugin_beta', 'TEXT', 'RTRIM', 'currentRowids', []],
    'rtrim next matches repaired exact' => ['plugin_beta', 'TEXT', 'RTRIM', 'nextRowids', [3]],
    'rtrim current false positives include padded space only' => ['plugin_beta', 'TEXT', 'RTRIM', 'currentResidualRejectedRowids', [6, 2, 5, 1, 3, 4, 7, 8, 10, 9]],
    'binary range usable' => ['plugin_*', 'TEXT', 'BINARY', 'rangeUsable', true],
    'binary residual disabled' => ['plugin_*', 'TEXT', 'BINARY', 'residualScan', false],
    'binary current candidates' => ['plugin_*', 'TEXT', 'BINARY', 'currentCandidateRowids', [5, 1, 4, 3, 7, 8, 10]],
    'binary current residual rejects none' => ['plugin_*', 'TEXT', 'BINARY', 'currentResidualRejectedRowids', []],
    'numeric current casts leading zero to integer' => ['4*', 'NUMERIC', 'BINARY', 'currentTrace.0.affinityValue', 42],
    'numeric current storage integer' => ['4*', 'NUMERIC', 'BINARY', 'currentTrace.0.storage', 'integer'],
    'numeric current rowids' => ['4*', 'NUMERIC', 'BINARY', 'currentRowids', [6]],
    'numeric next rowids' => ['4*', 'NUMERIC', 'BINARY', 'nextRowids', [6]],
    'leading class has no range' => ['[Pp]lugin_*', 'TEXT', 'NOCASE', 'range', null],
    'leading class fallback' => ['[Pp]lugin_*', 'TEXT', 'NOCASE', 'fallbackReason', 'no-prefix-range'],
];

foreach ($cases as $name => [$pattern, $affinity, $collation, $path, $expected]) {
    $tests['nocase rtrim glob affinity current source next142 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $affinity, $collation, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $affinity, $collation), $path));
    };
}

$tests['nocase rtrim glob affinity current source next142 stable binary rows reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_alpha', 'UTF-8'), $row(2, 'plugin_beta', 'UTF-16LE')];
    $plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan($rows, $rows, 'plugin_*', 'TEXT', 'BINARY', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['nocase rtrim glob affinity current source next142 stable nocase records fallback'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_alpha', 'UTF-8'), $row(2, 'Plugin_Beta', 'UTF-16LE')];
    $plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan($rows, $rows, 'plugin_*', 'TEXT', 'NOCASE', 'stable', 'stable', 7, 7);
    $t->same([1], $plan['currentRowids']);
    $t->same(['glob-range-requires-binary-collation'], $plan['invalidationReasons']);
    $t->same(false, $plan['cursorReusable']);
};

$tests['nocase rtrim glob affinity current source next142 rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan($currentRows, $currentRows, 'plugin_*', 'TEXT', 'UNICODE'));
};

$tests['nocase rtrim glob affinity current source next142 rejects missing option id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan([['option_name_bytes' => 'plugin', 'text_encoding' => 1]], [], 'plugin_*'));
};

$tests['nocase rtrim glob affinity current source next142 rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'text_encoding' => 1]], [], 'plugin_*'));
};

$tests['nocase rtrim glob affinity current source next142 rejects missing encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'option_name_bytes' => 'plugin']], [], 'plugin_*'));
};

$tests['nocase rtrim glob affinity current source next142 records unsupported encoding as malformed row'] = static function (TestRunner $t): void {
    $plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'option_name_bytes' => 'plugin', 'text_encoding' => 9]], [], 'plugin_*');
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite NOCASE/RTRIM GLOB current-source next142 encoding must be UTF-8, UTF-16LE, or UTF-16BE', $plan['currentErrors'][1]);
};

return $tests;
