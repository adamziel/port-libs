<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'module_alpha', 'UTF-8'),
    $row(2, 'Module_Alpha', 'UTF-16LE'),
    $row(3, 'module_beta ', 'UTF-16BE'),
    $row(4, "module_beta\t", 'UTF-8'),
    $row(5, 'module_100', 'UTF-16LE'),
    $row(6, '042', 'UTF-8'),
    $row(7, 'module_Éclair', 'UTF-16BE'),
    $row(8, 'module_éclair', 'UTF-16LE'),
    $row(9, 'theme_alpha', 'UTF-8'),
    $row(10, 'module_😀 ', 'UTF-16LE'),
    $bad(11, "p\x00l", 2),
];
$nextRows = [
    $row(1, 'module_alpha', 'UTF-16LE'),
    $row(2, 'module_Alpha', 'UTF-16LE'),
    $row(3, 'module_beta', 'UTF-16BE'),
    $row(4, "module_beta\t", 'UTF-8'),
    $row(5, '100', 'UTF-16LE'),
    $row(6, '42', 'UTF-8'),
    $row(7, 'module_Éclair', 'UTF-16BE'),
    $row(8, 'module_éclair_extra', 'UTF-16LE'),
    $row(10, 'module_😀', 'UTF-16BE'),
    $row(12, 'module_fresh', 'UTF-8'),
    $bad(13, "\x3d\xd8", 2),
];

$plan = static fn (
    string $pattern = 'module_*',
    string $affinity = 'TEXT',
    string $collation = 'NOCASE',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@141',
    string $nextSource = 'main.app_settings@142',
    int $currentCookie = 141,
    int $nextCookie = 142,
): array => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan(
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
    'operator' => ['module_*', 'TEXT', 'NOCASE', 'operator', 'GLOB'],
    'pattern' => ['module_*', 'TEXT', 'NOCASE', 'pattern', 'module_*'],
    'affinity' => ['module_*', 'TEXT', 'NOCASE', 'affinity', 'TEXT'],
    'collation nocase' => ['module_*', 'TEXT', 'NOCASE', 'collation', 'NOCASE'],
    'range lower' => ['module_*', 'TEXT', 'NOCASE', 'range.lowerInclusive', 'module_'],
    'range upper' => ['module_*', 'TEXT', 'NOCASE', 'range.upperBound', 'module`'],
    'nocase range not usable for glob' => ['module_*', 'TEXT', 'NOCASE', 'rangeUsable', false],
    'residual scan enabled' => ['module_*', 'TEXT', 'NOCASE', 'residualScan', true],
    'fallback reason' => ['module_*', 'TEXT', 'NOCASE', 'fallbackReason', 'glob-range-requires-binary-collation'],
    'glob bytewise residual marker' => ['module_*', 'TEXT', 'NOCASE', 'globResidualUsesBytewiseText', true],
    'nocase ordering marker' => ['module_*', 'TEXT', 'NOCASE', 'nocaseOnlyOrdersIndexKeys', true],
    'rtrim ordering marker false' => ['module_*', 'TEXT', 'NOCASE', 'rtrimOnlyTrimsIndexKeys', false],
    'current source' => ['module_*', 'TEXT', 'NOCASE', 'currentSource', 'main.app_settings@141'],
    'next source' => ['module_*', 'TEXT', 'NOCASE', 'nextSource', 'main.app_settings@142'],
    'current cookie' => ['module_*', 'TEXT', 'NOCASE', 'currentSchemaCookie', 141],
    'next cookie' => ['module_*', 'TEXT', 'NOCASE', 'nextSchemaCookie', 142],
    'current order rowids nocase' => ['module_*', 'TEXT', 'NOCASE', 'currentOrderRowids', [6, 5, 1, 2, 4, 3, 7, 8, 10, 9]],
    'next order rowids nocase' => ['module_*', 'TEXT', 'NOCASE', 'nextOrderRowids', [5, 6, 1, 2, 3, 4, 12, 7, 8, 10]],
    'current candidates are residual full scan' => ['module_*', 'TEXT', 'NOCASE', 'currentCandidateRowids', [6, 5, 1, 2, 4, 3, 7, 8, 10, 9]],
    'next candidates are residual full scan' => ['module_*', 'TEXT', 'NOCASE', 'nextCandidateRowids', [5, 6, 1, 2, 3, 4, 12, 7, 8, 10]],
    'current matches are bytewise lower module only' => ['module_*', 'TEXT', 'NOCASE', 'currentRowids', [5, 1, 4, 3, 7, 8, 10]],
    'next matches include repaired alpha and fresh' => ['module_*', 'TEXT', 'NOCASE', 'nextRowids', [1, 2, 3, 4, 12, 7, 8, 10]],
    'current residual rejects uppercase and non prefix' => ['module_*', 'TEXT', 'NOCASE', 'currentResidualRejectedRowids', [6, 2, 9]],
    'next residual rejects numeric and non prefix' => ['module_*', 'TEXT', 'NOCASE', 'nextResidualRejectedRowids', [5, 6]],
    'retained rowids' => ['module_*', 'TEXT', 'NOCASE', 'retainedRowids', [1, 4, 3, 7, 8, 10]],
    'entered rowids' => ['module_*', 'TEXT', 'NOCASE', 'enteredRowids', [2, 12]],
    'exited rowids' => ['module_*', 'TEXT', 'NOCASE', 'exitedRowids', [5]],
    'current malformed rowids' => ['module_*', 'TEXT', 'NOCASE', 'currentMalformedRowids', [11]],
    'next malformed rowids' => ['module_*', 'TEXT', 'NOCASE', 'nextMalformedRowids', [13]],
    'current malformed error' => ['module_*', 'TEXT', 'NOCASE', 'currentErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['module_*', 'TEXT', 'NOCASE', 'nextErrors.13', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed affinity rowids' => ['module_*', 'TEXT', 'NOCASE', 'changedAffinityValueRowids', [2, 3, 5, 6, 8, 9, 10, 12]],
    'changed storage rowids' => ['module_*', 'TEXT', 'NOCASE', 'changedStorageRowids', [9, 12]],
    'changed collation rowids' => ['module_*', 'TEXT', 'NOCASE', 'changedCollationKeyRowids', [3, 5, 6, 8, 9, 10, 12]],
    'changed bytes rowids' => ['module_*', 'TEXT', 'NOCASE', 'changedBytesRowids', [1, 2, 3, 5, 6, 8, 9, 10, 12]],
    'changed encoding rowids' => ['module_*', 'TEXT', 'NOCASE', 'changedEncodingRowids', [1, 9, 10, 12]],
    'cursor invalidated' => ['module_*', 'TEXT', 'NOCASE', 'cursorInvalidated', true],
    'cursor not reusable' => ['module_*', 'TEXT', 'NOCASE', 'cursorReusable', false],
    'reason source' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.1', 'schema-cookie'],
    'reason range fallback' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.2', 'glob-range-requires-binary-collation'],
    'reason malformed' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.3', 'malformed-text'],
    'reason affinity' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.4', 'affinity-value'],
    'reason storage' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.5', 'storage-class'],
    'reason key' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.6', 'collation-key'],
    'reason bytes' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.7', 'encoded-bytes'],
    'reason encoding' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.8', 'text-encoding'],
    'reason candidate' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.9', 'candidate-rowset'],
    'reason matched' => ['module_*', 'TEXT', 'NOCASE', 'invalidationReasons.10', 'matched-rowset'],
    'trace uppercase residual false' => ['module_*', 'TEXT', 'NOCASE', 'currentTrace.3.matched', false],
    'trace uppercase next residual true after lowercase p' => ['module_*', 'TEXT', 'NOCASE', 'nextTrace.3.matched', true],
    'trace rtrim space remains text' => ['module_*', 'TEXT', 'NOCASE', 'currentTrace.5.affinityText', 'module_beta '],
    'trace emoji utf16le bytes' => ['module_*', 'TEXT', 'NOCASE', 'currentTrace.8.bytesHex', '6d006f00640075006c0065005f003dd800de2000'],
    'dependency decoder' => ['module_*', 'TEXT', 'NOCASE', 'dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency affinity' => ['module_*', 'TEXT', 'NOCASE', 'dependencies.1', 'sqlite-affinity-comparison'],
    'dependency glob' => ['module_*', 'TEXT', 'NOCASE', 'dependencies.2', 'sqlite-glob-bytewise-residual'],
    'dependency current source' => ['module_*', 'TEXT', 'NOCASE', 'dependencies.3', 'sqlite-current-source-next142'],
    'rtrim collation recorded' => ['module_beta', 'TEXT', 'RTRIM', 'collation', 'RTRIM'],
    'rtrim marker' => ['module_beta', 'TEXT', 'RTRIM', 'rtrimOnlyTrimsIndexKeys', true],
    'rtrim range also not usable' => ['module_beta', 'TEXT', 'RTRIM', 'rangeUsable', false],
    'rtrim current matches unpadded none' => ['module_beta', 'TEXT', 'RTRIM', 'currentRowids', []],
    'rtrim next matches repaired exact' => ['module_beta', 'TEXT', 'RTRIM', 'nextRowids', [3]],
    'rtrim current false positives include padded space only' => ['module_beta', 'TEXT', 'RTRIM', 'currentResidualRejectedRowids', [6, 2, 5, 1, 3, 4, 7, 8, 10, 9]],
    'binary range usable' => ['module_*', 'TEXT', 'BINARY', 'rangeUsable', true],
    'binary residual disabled' => ['module_*', 'TEXT', 'BINARY', 'residualScan', false],
    'binary current candidates' => ['module_*', 'TEXT', 'BINARY', 'currentCandidateRowids', [5, 1, 4, 3, 7, 8, 10]],
    'binary current residual rejects none' => ['module_*', 'TEXT', 'BINARY', 'currentResidualRejectedRowids', []],
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
    $rows = [$row(1, 'module_alpha', 'UTF-8'), $row(2, 'module_beta', 'UTF-16LE')];
    $plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, 'module_*', 'TEXT', 'BINARY', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['nocase rtrim glob affinity current source next142 stable nocase records fallback'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'module_alpha', 'UTF-8'), $row(2, 'Module_Beta', 'UTF-16LE')];
    $plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, 'module_*', 'TEXT', 'NOCASE', 'stable', 'stable', 7, 7);
    $t->same([1], $plan['currentRowids']);
    $t->same(['glob-range-requires-binary-collation'], $plan['invalidationReasons']);
    $t->same(false, $plan['cursorReusable']);
};

$tests['nocase rtrim glob affinity current source next142 rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $currentRows, 'module_*', 'TEXT', 'UNICODE'));
};

$tests['nocase rtrim glob affinity current source next142 rejects missing setting id'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['key_name_bytes' => 'module', 'text_encoding' => 1]], [], 'module_*'));
};

$tests['nocase rtrim glob affinity current source next142 rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['setting_id' => 1, 'text_encoding' => 1]], [], 'module_*'));
};

$tests['nocase rtrim glob affinity current source next142 rejects missing encoding'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['setting_id' => 1, 'key_name_bytes' => 'module']], [], 'module_*'));
};

$tests['nocase rtrim glob affinity current source next142 records unsupported encoding as malformed row'] = static function (TestRunner $t): void {
    $plan = SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan::keyValueRowKeyPlan([['setting_id' => 1, 'key_name_bytes' => 'module', 'text_encoding' => 9]], [], 'module_*');
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite NOCASE/RTRIM GLOB current-source next142 encoding must be UTF-8, UTF-16LE, or UTF-16BE', $plan['currentErrors'][1]);
};

return $tests;
