<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteEncodingLikeGlobSourceSwitchPlan;

$tests = [];

$makeRow = static function (int $id, string $name, string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name' => $name,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'load_policy' => $load_policy,
    ];
};

$currentRows = [
    $makeRow(1, 'Plugin_Alpha', 'UTF-8', 'no'),
    $makeRow(2, 'plugin_alpha', 'UTF-16LE'),
    $makeRow(3, 'plugin_beta', 'UTF-16BE'),
    $makeRow(4, 'plugin_100%_enabled', 'UTF-16LE'),
    $makeRow(5, 'plugin_100x_enabled', 'UTF-16BE'),
    $makeRow(6, 'plugin_éclair', 'UTF-8'),
    $makeRow(7, 'plugin_😀_cache', 'UTF-16LE'),
    $makeRow(8, 'theme_alpha', 'UTF-8'),
    $makeRow(9, 'plugin_old', 'UTF-8'),
    $makeRow(10, 'plugin_beta ', 'UTF-8'),
];

$nextRows = [
    $makeRow(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $makeRow(2, 'plugin_alpha', 'UTF-16LE'),
    $makeRow(3, 'plugin_beta', 'UTF-8'),
    $makeRow(4, 'plugin_100%_enabled', 'UTF-16BE'),
    $makeRow(5, 'plugin_100x_enabled', 'UTF-16BE'),
    $makeRow(6, 'plugin_éclair', 'UTF-8'),
    $makeRow(7, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $makeRow(8, 'theme_alpha', 'UTF-8'),
    $makeRow(11, 'plugin_new', 'UTF-16BE'),
    $makeRow(12, 'Plugin_Éclair', 'UTF-16LE', 'no'),
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.app_settings@cookie10',
    string $nextSource = 'main.app_settings@cookie11',
): array => SQLiteEncodingLikeGlobSourceSwitchPlan::keyValueRowKeySourceSwitch(
    $currentRows,
    $nextRows,
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'like nocase reports current source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSource', 'main.app_settings@cookie10'],
    'like nocase reports next source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSource', 'main.app_settings@cookie11'],
    'like nocase source changes' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'sourceChanged', true],
    'like nocase invalidates cursor' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'cursorInvalidated', true],
    'like nocase invalidates by source name' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'source-name'],
    'like nocase invalidates by encoding' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.1', 'text-encoding'],
    'like nocase invalidates by bytes' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.2', 'key-bytes'],
    'like nocase invalidates by rowset' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.3', 'matched-rowset'],
    'like nocase current rowids include old row' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRowids', [4, 5, 1, 2, 3, 10, 9, 6, 7]],
    'like nocase next rowids include new rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRowids', [4, 5, 1, 2, 3, 11, 12, 6, 7]],
    'like nocase retained rowids preserve current order' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [4, 5, 1, 2, 3, 6, 7]],
    'like nocase exited rowids expose stale current rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', [10, 9]],
    'like nocase entered rowids expose next source rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'enteredRowids', [11, 12]],
    'like nocase changed encodings include rebuilt rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedEncodingRowids', [1, 3, 4]],
    'like nocase changed bytes include encoding and value changes' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedBytesRowids', [1, 3, 4, 7]],
    'like nocase current row one encoding utf8' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentEncodings.1', 'UTF-8'],
    'like nocase next row one encoding utf16le' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextEncodings.1', 'UTF-16LE'],
    'like nocase current row four bytes are utf16le' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.4', '70006c007500670069006e005f0031003000300025005f0065006e00610062006c0065006400'],
    'like nocase next row four bytes are utf16be' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextBytesHex.4', '0070006c007500670069006e005f0031003000300025005f0065006e00610062006c00650064'],
    'like nocase dependency includes source invalidation' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'dependencies.2', 'sqlite-current-next-source-invalidation'],
    'escaped literal percent current rowids' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'currentRowids', [4]],
    'escaped literal percent next rowids' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'nextRowids', [4]],
    'escaped literal percent retained' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'retainedRowids', [4]],
    'escaped literal percent has no entered rows' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'enteredRowids', []],
    'escaped literal percent still sees encoding change' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'changedEncodingRowids', [4]],
    'case sensitive binary skips uppercase current plugin' => ['plugin%', 'LIKE', 'BINARY', null, true, 'currentRowids', [4, 5, 2, 3, 10, 9, 6, 7]],
    'case sensitive binary skips uppercase next plugin' => ['plugin%', 'LIKE', 'BINARY', null, true, 'nextRowids', [4, 5, 2, 3, 11, 6, 7]],
    'case sensitive binary exited old beta space and old row' => ['plugin%', 'LIKE', 'BINARY', null, true, 'exitedRowids', [10, 9]],
    'case sensitive binary entered new row only' => ['plugin%', 'LIKE', 'BINARY', null, true, 'enteredRowids', [11]],
    'case sensitive binary collation recorded' => ['plugin%', 'LIKE', 'BINARY', null, true, 'collation', 'BINARY'],
    'case sensitive binary flag recorded' => ['plugin%', 'LIKE', 'BINARY', null, true, 'caseSensitiveLike', true],
    'glob binary current rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'currentRowids', [4, 5, 2, 3, 10, 9, 6, 7]],
    'glob binary next rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'nextRowids', [4, 5, 2, 3, 11, 6, 7]],
    'glob binary retained rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'retainedRowids', [4, 5, 2, 3, 6, 7]],
    'glob binary exited rows' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'exitedRowids', [10, 9]],
    'glob binary entered rows' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'enteredRowids', [11]],
    'glob binary operator recorded' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'operator', 'GLOB'],
    'glob latin range current rowids' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'currentRowids', [6]],
    'glob latin range next rowids stay scoped to lowercase prefix' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'nextRowids', [6]],
    'glob latin range has no binary-prefix entered rows' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'enteredRowids', []],
    'glob emoji current rowid' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'currentRowids', [7]],
    'glob emoji next renamed row remains matched' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'nextRowids', [7]],
    'glob emoji retained after bytes changed' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'retainedRowids', [7]],
    'glob emoji changed bytes detects rename' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'changedBytesRowids', [7]],
    'same source unchanged rowset still invalidates on bytes' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'sourceChanged', false, 'stable', 'stable'],
    'same source unchanged rowset has byte reason' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'invalidationReasons', ['key-bytes'], 'stable', 'stable'],
    'same source literal theme unaffected is valid' => ['theme%', 'LIKE', 'NOCASE', null, false, 'cursorInvalidated', false, 'stable', 'stable'],
    'same source literal theme retains row eight' => ['theme%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [8], 'stable', 'stable'],
    'same source literal theme has no reasons' => ['theme%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons', [], 'stable', 'stable'],
    'leading wildcard has no source cursor rowsets' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'currentRowids', []],
    'leading wildcard invalidates from source only' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'invalidationReasons', ['source-name']],
    'leading class glob has no rowsets' => ['[Pp]lugin_*', 'GLOB', 'BINARY', null, false, 'nextRowids', []],
    'new source with no rowsets still invalidates on source' => ['[Pp]lugin_*', 'GLOB', 'BINARY', null, false, 'cursorInvalidated', true],
];

foreach ($cases as $name => $case) {
    $tests['encoding like glob source current next86 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $collation, $escape, $caseSensitive, $path, $expected] = $case;
        $currentSource = $case[7] ?? 'main.app_settings@cookie10';
        $nextSource = $case[8] ?? 'main.app_settings@cookie11';
        $value = $plan($pattern, $operator, $collation, $escape, $caseSensitive, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['encoding like glob source current next86 rejects malformed next source bytes'] = static function (TestRunner $t) use ($currentRows): void {
    $next = [['setting_id' => 1, 'key_name_bytes' => "plugin_\xc3", 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobSourceSwitchPlan::keyValueRowKeySourceSwitch($currentRows, $next, 'plugin%'));
};

$tests['encoding like glob source current next86 rejects missing current rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $current = [['key_name_bytes' => 'plugin', 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobSourceSwitchPlan::keyValueRowKeySourceSwitch($current, $nextRows, 'plugin%'));
};

return $tests;
