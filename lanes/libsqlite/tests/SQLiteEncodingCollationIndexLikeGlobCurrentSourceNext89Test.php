<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$makeRow = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name' => $name,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'autoload' => $autoload,
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
    string $currentSource = 'main.wp_options',
    string $nextSource = 'main.wp_options',
    int $currentSchemaCookie = 21,
    int $nextSchemaCookie = 22,
    int $currentCollationVersion = 3,
    int $nextCollationVersion = 4,
): array => SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::keyValueRowKeyIndexPlan(
    $currentRows,
    $nextRows,
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $currentCollationVersion,
    $nextCollationVersion,
);

$cases = [
    'like records operator' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'operator', 'LIKE'],
    'like records pattern' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'pattern', 'plugin%'],
    'like records collation' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'collation', 'NOCASE'],
    'like records case flag' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'caseSensitiveLike', false],
    'like is index usable' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'indexUsable', true],
    'like has no rejected reason' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'rejectedReason', null],
    'like prefix is plugin' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'prefix', 'plugin'],
    'like prefix character count' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'prefixCharacters', 6],
    'like range lower is folded' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'range.lowerInclusive', 'plugin'],
    'like range upper is next prefix' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'range.upperBound', 'plugio'],
    'like source stable records current source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSource', 'main.wp_options'],
    'like source stable records next source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSource', 'main.wp_options'],
    'like schema current cookie' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSchemaCookie', 21],
    'like schema next cookie' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSchemaCookie', 22],
    'like collation current version' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentCollationVersion', 3],
    'like collation next version' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextCollationVersion', 4],
    'like cursor invalidates' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'cursorInvalidated', true],
    'like cursor is not reusable' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'cursorReusable', false],
    'like invalidates by schema cookie' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'schema-cookie'],
    'like invalidates by collation version' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.1', 'collation-version'],
    'like invalidates by text encoding' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.2', 'text-encoding'],
    'like invalidates by key bytes' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.3', 'key-bytes'],
    'like invalidates by matched rowset' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.4', 'matched-rowset'],
    'like current rowids include stale rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRowids', [4, 5, 1, 2, 3, 10, 9, 6, 7]],
    'like next rowids include new rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRowids', [4, 5, 1, 2, 3, 11, 12, 6, 7]],
    'like retained rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [4, 5, 1, 2, 3, 6, 7]],
    'like exited rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', [10, 9]],
    'like entered rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'enteredRowids', [11, 12]],
    'like changed encoding rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedEncodingRowids', [1, 3, 4]],
    'like changed bytes rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'changedBytesRowids', [1, 3, 4, 7]],
    'like current encoding map' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentEncodings.4', 'UTF-16LE'],
    'like next encoding map' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextEncodings.4', 'UTF-16BE'],
    'like current bytes are utf16le' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.4', '70006c007500670069006e005f0031003000300025005f0065006e00610062006c0065006400'],
    'like next bytes are utf16be' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextBytesHex.4', '0070006c007500670069006e005f0031003000300025005f0065006e00610062006c00650064'],
    'like dependency records slice marker' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'dependencies.2', 'sqlite-index-current-source-next89'],
    'escaped like records escape character' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'escape', '!'],
    'escaped like prefix keeps literal wildcard bytes' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'prefix', 'plugin_100%'],
    'escaped like prefix character count' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'prefixCharacters', 11],
    'escaped like lower range' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'range.lowerInclusive', 'plugin_100%'],
    'escaped like upper range' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'range.upperBound', 'plugin_100&'],
    'escaped like current literal percent row' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'currentRowids', [4]],
    'escaped like next literal percent row' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'nextRowids', [4]],
    'escaped like has no rowset delta' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'enteredRowids', []],
    'escaped like still detects encoding change' => ['plugin!_100!%%', 'LIKE', 'NOCASE', '!', false, 'changedEncodingRowids', [4]],
    'case-sensitive like uses binary collation' => ['plugin%', 'LIKE', 'BINARY', null, true, 'collation', 'BINARY'],
    'case-sensitive like current rowids skip uppercase' => ['plugin%', 'LIKE', 'BINARY', null, true, 'currentRowids', [4, 5, 2, 3, 10, 9, 6, 7]],
    'case-sensitive like next rowids skip uppercase' => ['plugin%', 'LIKE', 'BINARY', null, true, 'nextRowids', [4, 5, 2, 3, 11, 6, 7]],
    'case-sensitive like entered rows' => ['plugin%', 'LIKE', 'BINARY', null, true, 'enteredRowids', [11]],
    'case-sensitive like exited rows' => ['plugin%', 'LIKE', 'BINARY', null, true, 'exitedRowids', [10, 9]],
    'default like binary rejects index' => ['plugin%', 'LIKE', 'BINARY', null, false, 'rejectedReason', 'default_like_requires_nocase_index'],
    'default like binary has no range' => ['plugin%', 'LIKE', 'BINARY', null, false, 'range', null],
    'default like unicode prefix rejects nocase range' => ['plugin-é%', 'LIKE', 'NOCASE', null, false, 'rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'default like unicode prefix not usable' => ['plugin-é%', 'LIKE', 'NOCASE', null, false, 'indexUsable', false],
    'leading wildcard like has no prefix' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'prefix', ''],
    'leading wildcard like rejected' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'rejectedReason', 'no_fixed_prefix'],
    'glob records operator' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'operator', 'GLOB'],
    'glob range lower' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'range.lowerInclusive', 'plugin_'],
    'glob range upper' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'range.upperBound', 'plugin`'],
    'glob current rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'currentRowids', [4, 5, 2, 3, 10, 9, 6, 7]],
    'glob next rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'nextRowids', [4, 5, 2, 3, 11, 6, 7]],
    'glob retained rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'retainedRowids', [4, 5, 2, 3, 6, 7]],
    'glob entered rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'enteredRowids', [11]],
    'glob latin class current rowids' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'currentRowids', [6]],
    'glob latin class next rowids ignore uppercase plugin prefix' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'nextRowids', [6]],
    'glob emoji current rowids' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'currentRowids', [7]],
    'glob emoji next rowids' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'nextRowids', [7]],
    'glob emoji changed bytes' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'changedBytesRowids', [7]],
    'same source stable theme reusable' => ['theme%', 'LIKE', 'NOCASE', null, false, 'cursorReusable', true, 'main.wp_options', 'main.wp_options', 21, 21, 3, 3],
    'same source stable theme no invalidation' => ['theme%', 'LIKE', 'NOCASE', null, false, 'cursorInvalidated', false, 'main.wp_options', 'main.wp_options', 21, 21, 3, 3],
    'same source stable theme reasons empty' => ['theme%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons', [], 'main.wp_options', 'main.wp_options', 21, 21, 3, 3],
    'source name change reason' => ['theme%', 'LIKE', 'NOCASE', null, false, 'invalidationReasons.0', 'source-name', 'main.wp_options', 'temp.wp_options', 21, 21, 3, 3],
];

foreach ($cases as $name => $case) {
    $tests['encoding collation index like glob current source next89 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $collation, $escape, $caseSensitiveLike, $path, $expected] = $case;
        $currentSource = $case[7] ?? 'main.wp_options';
        $nextSource = $case[8] ?? 'main.wp_options';
        $currentSchemaCookie = $case[9] ?? 21;
        $nextSchemaCookie = $case[10] ?? 22;
        $currentCollationVersion = $case[11] ?? 3;
        $nextCollationVersion = $case[12] ?? 4;
        $value = $plan($pattern, $operator, $collation, $escape, $caseSensitiveLike, $currentSource, $nextSource, $currentSchemaCookie, $nextSchemaCookie, $currentCollationVersion, $nextCollationVersion);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['encoding collation index like glob current source next89 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::keyValueRowKeyIndexPlan($currentRows, $nextRows, 'plugin%', 'REGEXP'));
};

$tests['encoding collation index like glob current source next89 rejects glob escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::keyValueRowKeyIndexPlan($currentRows, $nextRows, 'plugin_*', 'GLOB', 'BINARY', '\\'));
};

$tests['encoding collation index like glob current source next89 rejects malformed next bytes'] = static function (TestRunner $t) use ($currentRows): void {
    $nextRows = [['option_id' => 1, 'option_name_bytes' => "plugin_\xc3", 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::keyValueRowKeyIndexPlan($currentRows, $nextRows, 'plugin%'));
};

$tests['encoding collation index like glob current source next89 rejects missing option id'] = static function (TestRunner $t) use ($nextRows): void {
    $currentRows = [['option_name_bytes' => 'plugin', 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::keyValueRowKeyIndexPlan($currentRows, $nextRows, 'plugin%'));
};

return $tests;
