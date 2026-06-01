<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimNocaseCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE ', 'UTF-8'),
    $row(4, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row(5, 'plugin_cache' . "\xc2\xa0", 'UTF-16BE'),
    $row(6, 'plugin_éclair ', 'UTF-16LE'),
    $row(7, 'PLUGIN_ÉCLAIR ', 'UTF-16BE'),
    $row(8, 'theme_cache ', 'UTF-16LE'),
    $bad(9, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];

$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, 'PLUGIN_CACHE ', 'UTF-8'),
    $row(4, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row(5, 'plugin_cache ', 'UTF-16LE'),
    $row(6, 'plugin_éclair', 'UTF-16BE'),
    $row(7, 'PLUGIN_ÉCLAIR ', 'UTF-16BE'),
    $row(10, 'PLUGIN_CACHE', 'UTF-16LE'),
    $bad(11, "\x3d\xd8", 2),
];

$plan = static fn (
    string $probe = 'plugin_cache',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@131',
    string $nextSource = 'main.app_settings@132',
): array => SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $probe,
    $currentSource,
    $nextSource,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records current source' => ['plugin_cache', 'currentSource', 'main.app_settings@131'],
    'records next source' => ['plugin_cache', 'nextSource', 'main.app_settings@132'],
    'probe key trims and folds ascii' => ['Plugin_Cache   ', 'probeKey', 'plugin_cache'],
    'current matched rowids' => ['plugin_cache', 'currentRowids', [1, 2, 3]],
    'next matched rowids include repaired nbsp and new row' => ['plugin_cache', 'nextRowids', [1, 2, 3, 5, 10]],
    'retained rowids' => ['plugin_cache', 'retainedRowids', [1, 2, 3]],
    'entered rowids' => ['plugin_cache', 'enteredRowids', [5, 10]],
    'exited rowids empty' => ['plugin_cache', 'exitedRowids', []],
    'retained encoding changes include endian switches only' => ['plugin_cache', 'retainedEncodingChangedRowids', [1, 2]],
    'retained bytes changes include trimmed and endian switched peers' => ['plugin_cache', 'retainedBytesChangedRowids', [1, 2]],
    'retained comparison keys unchanged for cache peers' => ['plugin_cache', 'retainedComparisonKeyChangedRowids', []],
    'source changed' => ['plugin_cache', 'sourceChanged', true],
    'reprepare required' => ['plugin_cache', 'reprepareRequired', true],
    'reason source' => ['plugin_cache', 'reprepareReasons.0', 'source-name'],
    'reason malformed' => ['plugin_cache', 'reprepareReasons.1', 'malformed-text'],
    'reason matched rowset' => ['plugin_cache', 'reprepareReasons.2', 'matched-rowset'],
    'reason text encoding' => ['plugin_cache', 'reprepareReasons.3', 'text-encoding'],
    'reason key bytes' => ['plugin_cache', 'reprepareReasons.4', 'key-bytes'],
    'current malformed rows' => ['plugin_cache', 'currentMalformedRowids', [9]],
    'next malformed rows' => ['plugin_cache', 'nextMalformedRowids', [11]],
    'current malformed error' => ['plugin_cache', 'currentErrors.9', 'SQLite UTF-16 RTRIM NOCASE text payload has an odd byte length'],
    'next malformed error' => ['plugin_cache', 'nextErrors.11', 'SQLite UTF-16 RTRIM NOCASE text payload ends with a high surrogate'],
    'repaired malformed row' => ['plugin_cache', 'repairedRowids', [9]],
    'new malformed row' => ['plugin_cache', 'newlyMalformedRowids', [11]],
    'tab current key remains distinct' => ['plugin_cache', 'currentComparisonKeys.4', "plugin_cache\t"],
    'nbsp current key remains distinct' => ['plugin_cache', 'currentComparisonKeys.5', "plugin_cache\xc2\xa0"],
    'repaired nbsp next key matches' => ['plugin_cache', 'nextComparisonKeys.5', 'plugin_cache'],
    'current order leaves tab after cache peers' => ['plugin_cache', 'currentOrderRowids', [1, 2, 3, 4, 5, 7, 6, 8]],
    'next order includes repaired row and new row' => ['plugin_cache', 'nextOrderRowids', [1, 2, 3, 5, 10, 4, 7, 6]],
    'current row one encoding' => ['plugin_cache', 'currentEncodings.1', 'UTF-16LE'],
    'next row one encoding' => ['plugin_cache', 'nextEncodings.1', 'UTF-16BE'],
    'current row two encoding' => ['plugin_cache', 'currentEncodings.2', 'UTF-16BE'],
    'next row two encoding' => ['plugin_cache', 'nextEncodings.2', 'UTF-16LE'],
    'current row three remains utf8' => ['plugin_cache', 'currentEncodings.3', 'UTF-8'],
    'next row three remains utf8' => ['plugin_cache', 'nextEncodings.3', 'UTF-8'],
    'current row one bytes include le spaces' => ['plugin_cache', 'currentBytesHex.1', '50006c007500670069006e005f0043006100630068006500200020002000'],
    'next row one bytes switch be no spaces' => ['plugin_cache', 'nextBytesHex.1', '0050006c007500670069006e005f00430061006300680065'],
    'current row two bytes be exact' => ['plugin_cache', 'currentBytesHex.2', '0070006c007500670069006e005f00630061006300680065'],
    'next row two bytes le padded' => ['plugin_cache', 'nextBytesHex.2', '70006c007500670069006e005f006300610063006800650020002000'],
    'ascii nocase does not fold e acute lower from upper probe' => ['plugin_Éclair', 'currentRowids', [7]],
    'ascii nocase lower e acute retained after trim/endian change' => ['plugin_éclair', 'retainedRowids', [6]],
    'eclair encoding change retained' => ['plugin_éclair', 'retainedEncodingChangedRowids', [6]],
    'eclair byte change retained' => ['plugin_éclair', 'retainedBytesChangedRowids', [6]],
    'eclair key unchanged after space trim' => ['plugin_éclair', 'retainedComparisonKeyChangedRowids', []],
    'theme exits next' => ['theme_cache', 'exitedRowids', [8]],
    'theme no next matches' => ['theme_cache', 'nextRowids', []],
    'tab probe matches tab only' => ["plugin_cache\t", 'currentRowids', [4]],
    'nbsp probe matches current nbsp only' => ["plugin_cache\xc2\xa0", 'currentRowids', [5]],
    'dependencies include utf16 decode' => ['plugin_cache', 'dependencies.0', 'sqlite-utf16-decode'],
    'dependencies include rtrim expression' => ['plugin_cache', 'dependencies.1', 'sqlite-rtrim-expression'],
    'dependencies include nocase collation' => ['plugin_cache', 'dependencies.2', 'sqlite-nocase-collation'],
    'dependencies include current source byte invalidation' => ['plugin_cache', 'dependencies.3', 'sqlite-current-source-byte-invalidation'],
];

foreach ($cases as $name => [$probe, $path, $expected]) {
    $tests['utf16 rtrim nocase current source nextOneThreeTwo ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $probe, $path, $expected): void {
        $t->same($expected, $valueAt($plan($probe), $path));
    };
}

$tests['utf16 rtrim nocase current source nextOneThreeTwo invalidates retained rowids for byte changes only'] = static function (TestRunner $t) use ($row, $plan): void {
    $current = [$row(1, 'Plugin_Cache   ', 'UTF-16LE'), $row(2, 'plugin_cache', 'UTF-16BE')];
    $next = [$row(1, 'Plugin_Cache', 'UTF-16BE'), $row(2, 'plugin_cache  ', 'UTF-16LE')];
    $result = $plan('plugin_cache', $current, $next, 'stable', 'stable');
    $t->same([1, 2], $result['currentRowids']);
    $t->same([1, 2], $result['nextRowids']);
    $t->same([1, 2], $result['retainedEncodingChangedRowids']);
    $t->same([1, 2], $result['retainedBytesChangedRowids']);
    $t->same([], $result['retainedComparisonKeyChangedRowids']);
    $t->same(['text-encoding', 'key-bytes'], $result['reprepareReasons']);
    $t->same(true, $result['reprepareRequired']);
};

$tests['utf16 rtrim nocase current source nextOneThreeTwo invalidates retained rowids for comparison key change'] = static function (TestRunner $t) use ($row, $plan): void {
    $current = [$row(1, 'plugin_cache ', 'UTF-16LE')];
    $next = [$row(1, "plugin_cache\t", 'UTF-16LE')];
    $result = $plan('plugin_cache', $current, $next, 'stable', 'stable');
    $t->same([1], $result['currentRowids']);
    $t->same([], $result['nextRowids']);
    $t->same([], $result['retainedComparisonKeyChangedRowids']);
    $t->same(['matched-rowset'], $result['reprepareReasons']);

    $tab = $plan("plugin_cache\t", $current, $next, 'stable', 'stable');
    $t->same([], $tab['currentRowids']);
    $t->same([1], $tab['nextRowids']);
    $t->same(['matched-rowset'], $tab['reprepareReasons']);
};

$tests['utf16 rtrim nocase current source nextOneThreeTwo unchanged retained source stays reusable'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'Plugin_Cache   ', 'UTF-16LE'), $row(2, 'plugin_cache', 'UTF-16BE')];
    $result = $plan('plugin_cache', $rows, $rows, 'stable', 'stable');
    $t->same([1, 2], $result['currentRowids']);
    $t->same([1, 2], $result['nextRowids']);
    $t->same([], $result['retainedEncodingChangedRowids']);
    $t->same([], $result['retainedBytesChangedRowids']);
    $t->same([], $result['reprepareReasons']);
    $t->same(false, $result['reprepareRequired']);
};

$tests['utf16 rtrim nocase current source nextOneThreeTwo rejects non integer setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext([['setting_id' => '1', 'key_name_bytes' => 'x', 'text_encoding' => 1]], $nextRows, 'plugin_cache'));
};

return $tests;
