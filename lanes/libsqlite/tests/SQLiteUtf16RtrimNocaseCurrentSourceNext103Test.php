<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimNocaseCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteUtf16CollationAffinityCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
        'load_policy' => $load_policy,
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'load_policy' => 'yes',
];

$currentRows = [
    $row(1, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE ', 'UTF-8', 'no'),
    $row(4, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row(5, 'plugin_cache' . "\xc2\xa0", 'UTF-16BE'),
    $row(6, 'plugin_éclair ', 'UTF-16LE'),
    $row(7, 'PLUGIN_ÉCLAIR ', 'UTF-16BE'),
    $row(8, 'plugin_😀 ', 'UTF-16LE'),
    $row(9, 'theme_cache ', 'UTF-16LE'),
    $bad(10, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
    $bad(11, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 3),
];

$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, 'PLUGIN_CACHE ', 'UTF-8', 'no'),
    $row(4, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row(5, 'plugin_cache', 'UTF-16LE'),
    $row(6, 'plugin_éclair ', 'UTF-16LE'),
    $row(7, 'PLUGIN_ÉCLAIR ', 'UTF-16BE'),
    $row(8, 'plugin_😀 ', 'UTF-16BE'),
    $row(10, 'plugin_cache   ', 'UTF-16LE'),
    $bad(11, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 3),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00\x3d\xd8", 2),
];

$plan = static fn (
    string $probe = 'plugin_cache',
    string $currentSource = 'main.app_settings@cookie102',
    string $nextSource = 'main.app_settings@cookie103',
): array => SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext(
    $currentRows,
    $nextRows,
    $probe,
    $currentSource,
    $nextSource,
);

$cases = [
    'records current source' => ['plugin_cache', 'currentSource', 'main.app_settings@cookie102'],
    'records next source' => ['plugin_cache', 'nextSource', 'main.app_settings@cookie103'],
    'records probe text' => ['plugin_cache', 'probe', 'plugin_cache'],
    'records ascii rtrim nocase probe key' => ['Plugin_Cache   ', 'probeKey', 'plugin_cache'],
    'source changed is true' => ['plugin_cache', 'sourceChanged', true],
    'reprepare required on source and rowset change' => ['plugin_cache', 'reprepareRequired', true],
    'first reprepare reason is source' => ['plugin_cache', 'reprepareReasons.0', 'source-name'],
    'second reprepare reason is malformed text' => ['plugin_cache', 'reprepareReasons.1', 'malformed-text'],
    'third reprepare reason is matched rowset' => ['plugin_cache', 'reprepareReasons.2', 'matched-rowset'],
    'current rowids trim spaces and fold ascii' => ['plugin_cache', 'currentRowids', [1, 2, 3]],
    'next rowids include repaired nbsp row and new row' => ['plugin_cache', 'nextRowids', [1, 2, 3, 5, 10]],
    'retained rowids stay matched' => ['plugin_cache', 'retainedRowids', [1, 2, 3]],
    'entered rowids include repaired and new rows' => ['plugin_cache', 'enteredRowids', [5, 10]],
    'exited rowids empty for cache probe' => ['plugin_cache', 'exitedRowids', []],
    'tab is not rtrimmed by SQLite RTRIM expression' => ['plugin_cache', 'currentComparisonKeys.4', "plugin_cache\t"],
    'nbsp is not rtrimmed by SQLite RTRIM expression' => ['plugin_cache', 'currentComparisonKeys.5', "plugin_cache\xc2\xa0"],
    'space padded cache current key trims' => ['plugin_cache', 'currentComparisonKeys.1', 'plugin_cache'],
    'uppercase cache current key folds ascii' => ['plugin_cache', 'currentComparisonKeys.3', 'plugin_cache'],
    'next repaired nbsp row key now matches' => ['plugin_cache', 'nextComparisonKeys.5', 'plugin_cache'],
    'current order starts cache peers by rowid' => ['plugin_cache', 'currentOrderRowids', [1, 2, 3, 4, 5, 7, 6, 8, 9]],
    'next order includes repaired row among cache peers' => ['plugin_cache', 'nextOrderRowids', [1, 2, 3, 5, 10, 4, 7, 6, 8]],
    'current malformed rows' => ['plugin_cache', 'currentMalformedRowids', [10, 11]],
    'next malformed rows' => ['plugin_cache', 'nextMalformedRowids', [11, 12]],
    'repaired malformed rowid' => ['plugin_cache', 'repairedRowids', [10]],
    'newly malformed rowid' => ['plugin_cache', 'newlyMalformedRowids', [12]],
    'odd utf16le current error' => ['plugin_cache', 'currentErrors.10', 'SQLite UTF-16 RTRIM NOCASE text payload has an odd byte length'],
    'high surrogate next error' => ['plugin_cache', 'nextErrors.12', 'SQLite UTF-16 RTRIM NOCASE text payload ends with a high surrogate'],
    'be high surrogate error preserved' => ['plugin_cache', 'currentErrors.11', 'SQLite UTF-16 RTRIM NOCASE text payload ends with a high surrogate'],
    'current encodes utf8 row' => ['plugin_cache', 'currentEncodings.3', 'UTF-8'],
    'current encodes utf16le row' => ['plugin_cache', 'currentEncodings.1', 'UTF-16LE'],
    'current encodes utf16be row' => ['plugin_cache', 'currentEncodings.2', 'UTF-16BE'],
    'next row one bytes switch to utf16be' => ['plugin_cache', 'nextEncodings.1', 'UTF-16BE'],
    'current bytes preserve utf16le padded cache' => ['plugin_cache', 'currentBytesHex.1', '50006c007500670069006e005f0043006100630068006500200020002000'],
    'next bytes preserve utf16be cache' => ['plugin_cache', 'nextBytesHex.1', '0050006c007500670069006e005f00430061006300680065'],
    'eclair lowercase probe matches lowercase only because nocase is ascii' => ['plugin_éclair', 'currentRowids', [6]],
    'eclair uppercase probe matches uppercase only because nocase is ascii' => ['plugin_Éclair', 'currentRowids', [7]],
    'eclair lowercase retained' => ['plugin_éclair', 'retainedRowids', [6]],
    'eclair uppercase retained' => ['plugin_Éclair', 'retainedRowids', [7]],
    'emoji probe trims ascii space' => ['plugin_😀', 'currentRowids', [8]],
    'emoji row retained after encoding switch' => ['plugin_😀', 'retainedRowids', [8]],
    'theme probe exits in next source' => ['theme_cache', 'exitedRowids', [9]],
    'theme probe has empty next rows' => ['theme_cache', 'nextRowids', []],
    'tab probe matches tab row when probe includes tab' => ["plugin_cache\t", 'currentRowids', [4]],
    'nbsp probe matches nbsp row when probe includes nbsp' => ["plugin_cache\xc2\xa0", 'currentRowids', [5]],
    'dependencies include utf16 decode' => ['plugin_cache', 'dependencies.0', 'sqlite-utf16-decode'],
    'dependencies include rtrim expression' => ['plugin_cache', 'dependencies.1', 'sqlite-rtrim-expression'],
    'dependencies include nocase collation' => ['plugin_cache', 'dependencies.2', 'sqlite-nocase-collation'],
];

foreach ($cases as $name => [$probe, $path, $expected]) {
    $tests['utf16 rtrim nocase current source nextOneZeroThree ' . $name] = static function (TestRunner $t) use ($plan, $probe, $path, $expected): void {
        $value = $plan($probe);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 rtrim nocase current source nextOneZeroThree stable source only rowset reasons'] = static function (TestRunner $t) use ($plan): void {
    $t->same(['malformed-text', 'matched-rowset', 'text-encoding', 'key-bytes'], $plan('plugin_cache', 'stable', 'stable')['reprepareReasons']);
};

$tests['utf16 rtrim nocase current source nextOneZeroThree stable unchanged eclair source has no reprepare'] = static function (TestRunner $t) use ($plan): void {
    $stable = SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext(
        [
            ['setting_id' => 6, 'key_name_bytes' => SQLiteUtf16CollationAffinityCursor::encodeText('plugin_éclair ', 'UTF-16LE'), 'text_encoding' => 2],
            ['setting_id' => 7, 'key_name_bytes' => SQLiteUtf16CollationAffinityCursor::encodeText('PLUGIN_ÉCLAIR ', 'UTF-16BE'), 'text_encoding' => 3],
        ],
        [
            ['setting_id' => 6, 'key_name_bytes' => SQLiteUtf16CollationAffinityCursor::encodeText('plugin_éclair ', 'UTF-16LE'), 'text_encoding' => 2],
            ['setting_id' => 7, 'key_name_bytes' => SQLiteUtf16CollationAffinityCursor::encodeText('PLUGIN_ÉCLAIR ', 'UTF-16BE'), 'text_encoding' => 3],
        ],
        'plugin_éclair',
        'stable',
        'stable',
    );
    $t->same(false, $stable['sourceChanged']);
    $t->same(false, $stable['reprepareRequired']);
};

$tests['utf16 rtrim nocase current source nextOneZeroThree rejects missing bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext([['setting_id' => 1, 'text_encoding' => 2]], $nextRows, 'plugin_cache'));
};

$tests['utf16 rtrim nocase current source nextOneZeroThree rejects missing encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext([['setting_id' => 1, 'key_name_bytes' => 'x']], $nextRows, 'plugin_cache'));
};

$tests['utf16 rtrim nocase current source nextOneZeroThree records unsupported encoding as malformed row'] = static function (TestRunner $t) use ($nextRows): void {
    $plan = SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext([['setting_id' => 1, 'key_name_bytes' => 'x', 'text_encoding' => 4]], $nextRows, 'plugin_cache');
    $t->same('SQLite UTF-16 RTRIM NOCASE text encoding must be UTF-8, UTF-16LE, or UTF-16BE', $plan['currentErrors'][1]);
};

return $tests;
