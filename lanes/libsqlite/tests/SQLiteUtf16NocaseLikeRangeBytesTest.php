<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
        'autoload' => $autoload,
    ];
};

$currentRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE'),
    $row(2, 'plugin_beta', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE', 'UTF-16LE'),
    $row(4, 'plugin_delta', 'UTF-16BE', 'no'),
    $row(5, 'plugin_éclair', 'UTF-16LE'),
    $row(6, 'theme_alpha', 'UTF-16LE'),
    $row(7, 'plugin_Zulu', 'UTF-16BE'),
    $row(8, 'Plugin_Final', 'UTF-16LE'),
];

$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16BE'),
    $row(2, 'plugin_beta', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-16LE'),
    $row(4, 'plugin_delta', 'UTF-16BE', 'no'),
    $row(5, 'plugin_éclair', 'UTF-16LE'),
    $row(7, 'plugin_Zulu', 'UTF-16LE'),
    $row(8, 'Plugin_Final_v2', 'UTF-16LE'),
    $row(9, 'PLUGIN_added', 'UTF-16BE'),
    $row(10, 'theme_beta', 'UTF-16BE'),
];

$plan = static fn (
    string $pattern = 'plugin%',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.wp_options@cookie125',
    string $nextSource = 'main.wp_options@cookie126',
    int $currentSchemaCookie = 125,
    int $nextSchemaCookie = 126,
    int $currentCollationVersion = 4,
    int $nextCollationVersion = 5,
    string $currentDatabaseEncoding = 'UTF-16LE',
    string $nextDatabaseEncoding = 'UTF-16BE',
    ?array $current = null,
    ?array $next = null,
): array => SQLiteUtf16NocaseLikeCurrentSourceNextPlan::keyValueRowKeyLikePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $currentCollationVersion,
    $nextCollationVersion,
    $currentDatabaseEncoding,
    $nextDatabaseEncoding,
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
    'records like operator' => ['operator', 'LIKE'],
    'records nocase collation' => ['collation', 'NOCASE'],
    'records pattern' => ['pattern', 'plugin%'],
    'records current source' => ['currentSource', 'main.wp_options@cookie125'],
    'records next source' => ['nextSource', 'main.wp_options@cookie126'],
    'records current schema cookie' => ['currentSchemaCookie', 125],
    'records next schema cookie' => ['nextSchemaCookie', 126],
    'records current collation version' => ['currentCollationVersion', 4],
    'records next collation version' => ['nextCollationVersion', 5],
    'records current database encoding' => ['currentDatabaseEncoding', 'UTF-16LE'],
    'records next database encoding' => ['nextDatabaseEncoding', 'UTF-16BE'],
    'index usable for ascii nocase prefix' => ['indexUsable', true],
    'rejected reason is clear' => ['rejectedReason', null],
    'prefix is lowercased from pattern' => ['prefix', 'plugin'],
    'prefix length is characters' => ['prefixCharacters', 6],
    'prefix is ascii' => ['prefixIsAscii', true],
    'range lower is ascii lower prefix' => ['range.lowerInclusive', 'plugin'],
    'range upper is ascii successor' => ['range.upperBound', 'plugio'],
    'current lower range bytes are utf16le' => ['currentRangeBytesHex.lowerInclusive', '70006c007500670069006e00'],
    'current upper range bytes are utf16le' => ['currentRangeBytesHex.upperBound', '70006c007500670069006f00'],
    'next lower range bytes are utf16be' => ['nextRangeBytesHex.lowerInclusive', '0070006c007500670069006e'],
    'next upper range bytes are utf16be' => ['nextRangeBytesHex.upperBound', '0070006c007500670069006f'],
    'range bytes changed after encoding switch' => ['rangeBytesChanged', true],
    'cursor invalidates' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'source reason is first' => ['invalidationReasons.0', 'source-name'],
    'schema reason is second' => ['invalidationReasons.1', 'schema-cookie'],
    'collation version reason is third' => ['invalidationReasons.2', 'collation-version'],
    'range bytes reason is fourth' => ['invalidationReasons.3', 'range-bytes'],
    'encoding reason is fifth' => ['invalidationReasons.4', 'text-encoding'],
    'key bytes reason is sixth' => ['invalidationReasons.5', 'key-bytes'],
    'matched rowset reason is seventh' => ['invalidationReasons.6', 'matched-rowset'],
    'current rowids sort by nocase decoded text' => ['currentRowids', [1, 2, 3, 4, 8, 7, 5]],
    'next rowids include uppercase added row through nocase like' => ['nextRowids', [9, 1, 2, 3, 4, 8, 7, 5]],
    'retained rowids preserve current nocase order' => ['retainedRowids', [1, 2, 3, 4, 8, 7, 5]],
    'entered rowids expose next source addition' => ['enteredRowids', [9]],
    'exited rowids are empty' => ['exitedRowids', []],
    'changed encoding rowids include rewritten alpha and zulu' => ['changedEncodingRowids', [1, 7]],
    'changed bytes rowids include case and suffix rewrites' => ['changedBytesRowids', [1, 3, 7, 8]],
    'current first rowid is mixed case alpha' => ['currentFirstRowid', 1],
    'next first rowid is added uppercase row' => ['nextFirstRowid', 9],
    'current last rowid is e acute' => ['currentLastRowid', 5],
    'next last rowid is e acute' => ['nextLastRowid', 5],
    'current key map decodes utf16be beta' => ['currentKeys.2', 'plugin_beta'],
    'current key map decodes uppercase cache' => ['currentKeys.3', 'PLUGIN_CACHE'],
    'next key map preserves uppercase added text' => ['nextKeys.9', 'PLUGIN_added'],
    'current encoding map records utf16be row' => ['currentEncodings.2', 'UTF-16BE'],
    'next encoding map records rewritten alpha as utf16be' => ['nextEncodings.1', 'UTF-16BE'],
    'current bytes map records utf16le alpha' => ['currentBytesHex.1', '50006c007500670069006e005f0041006c00700068006100'],
    'next bytes map records utf16be alpha' => ['nextBytesHex.1', '0070006c007500670069006e005f0061006c007000680061'],
    'dependency records current source next slice' => ['dependencies.2', 'sqlite-utf16-nocase-current-source-nextoneTwoSix'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like range bytes ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$stableRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE'),
    $row(2, 'plugin_beta', 'UTF-16BE'),
    $row(3, 'theme_alpha', 'UTF-16LE'),
];

$stableCases = [
    'stable cursor reusable' => ['cursorReusable', true],
    'stable cursor not invalidated' => ['cursorInvalidated', false],
    'stable reasons empty' => ['invalidationReasons', []],
    'stable rowids retained' => ['retainedRowids', [1, 2]],
    'stable range bytes unchanged' => ['rangeBytesChanged', false],
    'stable changed bytes empty' => ['changedBytesRowids', []],
    'stable changed encoding empty' => ['changedEncodingRowids', []],
];

foreach ($stableCases as $name => [$path, $expected]) {
    $tests['utf16 nocase like range bytes ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $stableRows, $path, $expected): void {
        $stable = $plan('plugin%', null, false, 'stable', 'stable', 7, 7, 2, 2, 'UTF-16LE', 'UTF-16LE', $stableRows, $stableRows);
        $t->same($expected, $valueAt($stable, $path));
    };
}

$tests['utf16 nocase like range bytes rejects non utf16 text encoding'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', null, false, 'stable', 'stable', 1, 1, 1, 1, 'UTF-16LE', 'UTF-16LE', [['option_id' => 1, 'option_name_bytes' => 'plugin_alpha', 'text_encoding' => 1]], $nextRows));
};

$tests['utf16 nocase like range bytes rejects missing option bytes'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', null, false, 'stable', 'stable', 1, 1, 1, 1, 'UTF-16LE', 'UTF-16LE', [['option_id' => 1, 'text_encoding' => 2]], $nextRows));
};

$tests['utf16 nocase like range bytes rejects non integer rowid'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', null, false, 'stable', 'stable', 1, 1, 1, 1, 'UTF-16LE', 'UTF-16LE', [['option_id' => '1', 'option_name_bytes' => 'p', 'text_encoding' => 2]], $nextRows));
};

$tests['utf16 nocase like range bytes rejects malformed utf16 bytes'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $bad = [['option_id' => 1, 'option_name_bytes' => "\x3d\xd8", 'text_encoding' => 2]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', null, false, 'stable', 'stable', 1, 1, 1, 1, 'UTF-16LE', 'UTF-16LE', $bad, $nextRows));
};

$tests['utf16 nocase like range bytes rejects non ascii nocase prefix range'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('éclair%', null, false);
    $t->same(false, $result['indexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $result['rejectedReason']);
};

$tests['utf16 nocase like range bytes rejects case sensitive like on nocase index'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin%', null, true);
    $t->same(false, $result['indexUsable']);
    $t->same('case_sensitive_like_requires_binary_index', $result['rejectedReason']);
};

$tests['utf16 nocase like range bytes rejects bad escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', 'xx'));
};

$tests['utf16 nocase like range bytes rejects utf8 database encoding'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', null, false, 'stable', 'stable', 1, 1, 1, 1, 'UTF-8', 'UTF-16LE'));
};

return $tests;
