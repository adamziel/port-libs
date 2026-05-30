<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteLikeGlobCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name' => $name,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'autoload' => $autoload,
    ];
};

$currentRows = [
    $row(1, 'Plugin_Alpha', 'UTF-8', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-16BE'),
    $row(4, 'plugin_100%_enabled', 'UTF-16LE'),
    $row(5, 'plugin_100x_enabled', 'UTF-16BE'),
    $row(6, 'plugin_éclair', 'UTF-8'),
    $row(7, 'plugin_😀_cache', 'UTF-16LE'),
    $row(8, 'theme_alpha', 'UTF-8'),
    $row(9, 'plugin_old', 'UTF-8'),
    $row(10, 'plugin_beta ', 'UTF-8'),
];

$nextRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-8'),
    $row(4, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(5, 'plugin_100x_enabled', 'UTF-16BE'),
    $row(6, 'plugin_éclair', 'UTF-8'),
    $row(7, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $row(8, 'theme_alpha', 'UTF-8'),
    $row(11, 'plugin_new', 'UTF-16BE'),
    $row(12, 'Plugin_Éclair', 'UTF-16LE', 'no'),
];

$statement = static function (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $source = 'main.wp_options@cookie10',
): array {
    return [
        'source' => $source,
        'operator' => $operator,
        'pattern' => $pattern,
        'collation' => $collation,
        'escape' => $escape,
        'caseSensitiveLike' => $caseSensitiveLike,
    ];
};

$plan = static fn (array $currentStatement, array $nextStatement, ?array $current = null, ?array $next = null): array => SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $currentStatement,
    $nextStatement,
);

$cases = [
    'source switch status requires reprepare' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'status', 'reprepare-required'],
    'source switch boolean requires reprepare' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'reprepareRequired', true],
    'source switch records source reason first' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'reprepareReasons.0', 'source-name'],
    'source switch records text encoding reason' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'reprepareReasons.1', 'text-encoding'],
    'source switch records key byte reason' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'reprepareReasons.2', 'key-bytes'],
    'source switch records matched rowset reason' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'reprepareReasons.3', 'matched-rowset'],
    'current rowids use prepared pattern' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'current.rowids', [4, 5, 1, 2, 3, 10, 9, 6, 7]],
    'next rowids use next prepared pattern' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'next.rowids', [4, 5, 1, 2, 3, 11, 12, 6, 7]],
    'retained rowids preserve current cursor order' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'retainedRowids', [4, 5, 1, 2, 3, 6, 7]],
    'exited rowids expose stale cursor members' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'exitedRowids', [10, 9]],
    'entered rowids expose next cursor members' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'enteredRowids', [11, 12]],
    'changed encoding rowids include rebuilt source keys' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'changedEncodingRowids', [1, 3, 4]],
    'changed byte rowids include renames and encodings' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'changedBytesRowids', [1, 3, 4, 7]],
    'current LIKE range is nocase prefix' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'current.range.lowerInclusive', 'plugin'],
    'next LIKE range has same prefix upper bound' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'next.range.upperBound', 'plugio'],
    'current row one encoding is utf8' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'current.encodings.1', 'UTF-8'],
    'next row one encoding is utf16le' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'next.encodings.1', 'UTF-16LE'],
    'current bytes expose utf16 percent row' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin\_100\%%', escape: '\\', source: 'main.wp_options@cookie11'), 'current.bytesHex.4', '70006c007500670069006e005f0031003000300025005f0065006e00610062006c0065006400'],
    'next bytes expose utf16be percent row' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin\_100\%%', escape: '\\', source: 'main.wp_options@cookie11'), 'next.bytesHex.4', '0070006c007500670069006e005f0031003000300025005f0065006e00610062006c00650064'],
    'escaped percent rowset is retained' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin\_100\%%', escape: '\\', source: 'main.wp_options@cookie11'), 'retainedRowids', [4]],
    'escaped percent has no entered rows' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin\_100\%%', escape: '\\', source: 'main.wp_options@cookie11'), 'enteredRowids', []],
    'escaped percent still invalidates on source' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin\_100\%%', escape: '\\', source: 'main.wp_options@cookie11'), 'reprepareReasons.0', 'source-name'],
    'escape change requires reprepare' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin#_100#%%', escape: '#'), 'reprepareReasons.0', 'pattern'],
    'escape change records escape reason' => [$statement('plugin\_100\%%', escape: '\\'), $statement('plugin#_100#%%', escape: '#'), 'reprepareReasons.1', 'escape'],
    'operator change requires reprepare' => [$statement('plugin%', 'LIKE'), $statement('plugin_*', 'GLOB'), 'reprepareReasons.0', 'operator'],
    'operator change records pattern reason' => [$statement('plugin%', 'LIKE'), $statement('plugin_*', 'GLOB'), 'reprepareReasons.1', 'pattern'],
    'glob current rowids are case sensitive' => [$statement('plugin_*', 'GLOB', 'BINARY'), $statement('plugin_*', 'GLOB', 'BINARY'), 'current.rowids', [4, 5, 2, 3, 10, 9, 6, 7]],
    'glob next rowids skip uppercase plugin' => [$statement('plugin_*', 'GLOB', 'BINARY'), $statement('plugin_*', 'GLOB', 'BINARY'), 'next.rowids', [4, 5, 2, 3, 11, 6, 7]],
    'glob range lower is literal prefix' => [$statement('plugin_*', 'GLOB', 'BINARY'), $statement('plugin_*', 'GLOB', 'BINARY'), 'current.range.lowerInclusive', 'plugin_'],
    'glob range upper is next literal prefix' => [$statement('plugin_*', 'GLOB', 'BINARY'), $statement('plugin_*', 'GLOB', 'BINARY'), 'current.range.upperBound', 'plugin`'],
    'collation change records collation reason' => [$statement('plugin%', collation: 'NOCASE'), $statement('plugin%', collation: 'BINARY'), 'reprepareReasons.0', 'collation'],
    'case sensitive like change records pragma reason' => [$statement('plugin%', collation: 'BINARY', caseSensitiveLike: false), $statement('plugin%', collation: 'BINARY', caseSensitiveLike: true), 'reprepareReasons.0', 'case-sensitive-like'],
    'case sensitive like next skips uppercase source row' => [$statement('plugin%', collation: 'BINARY', caseSensitiveLike: false), $statement('plugin%', collation: 'BINARY', caseSensitiveLike: true), 'next.rowids', [4, 5, 2, 3, 11, 6, 7]],
    'pattern change records pattern reason' => [$statement('plugin%'), $statement('theme%'), 'reprepareReasons.0', 'pattern'],
    'theme next rowset stays retained' => [$statement('theme%'), $statement('theme%'), 'retainedRowids', [8]],
    'stable theme cursor is reusable' => [$statement('theme%', source: 'stable'), $statement('theme%', source: 'stable'), 'status', 'cursor-reusable'],
    'stable theme cursor has no reasons' => [$statement('theme%', source: 'stable'), $statement('theme%', source: 'stable'), 'reprepareReasons', []],
    'leading wildcard has null range' => [$statement('%plugin'), $statement('%plugin'), 'current.range', null],
    'leading wildcard has empty current rowids' => [$statement('%plugin'), $statement('%plugin'), 'current.rowids', []],
    'leading wildcard source change still requires reprepare' => [$statement('%plugin'), $statement('%plugin', source: 'next'), 'reprepareReasons', ['source-name']],
    'emoji glob retained after rename' => [$statement('plugin_😀*', 'GLOB', 'BINARY'), $statement('plugin_😀*', 'GLOB', 'BINARY'), 'retainedRowids', [7]],
    'emoji glob byte change requires reprepare' => [$statement('plugin_😀*', 'GLOB', 'BINARY'), $statement('plugin_😀*', 'GLOB', 'BINARY'), 'reprepareReasons', ['key-bytes']],
    'latin glob current row is e acute' => [$statement('plugin_[À-ÿ]*', 'GLOB', 'BINARY'), $statement('plugin_[À-ÿ]*', 'GLOB', 'BINARY'), 'current.rowids', [6]],
    'latin glob next row is e acute' => [$statement('plugin_[À-ÿ]*', 'GLOB', 'BINARY'), $statement('plugin_[À-ÿ]*', 'GLOB', 'BINARY'), 'next.rowids', [6]],
    'dependency records current source next guard' => [$statement(), $statement(source: 'main.wp_options@cookie11'), 'dependencies.2', 'sqlite-current-source-next-statement-reprepare'],
];

foreach ($cases as $name => [$currentStatement, $nextStatement, $path, $expected]) {
    $tests['like glob current source next88 ' . $name] = static function (TestRunner $t) use ($plan, $currentStatement, $nextStatement, $path, $expected): void {
        $value = $plan($currentStatement, $nextStatement);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['like glob current source next88 stable identical plugin source reuses cursor'] = static function (TestRunner $t) use ($row, $statement, $plan): void {
    $rows = [
        $row(1, 'plugin_alpha', 'UTF-8'),
        $row(2, 'plugin_beta', 'UTF-16LE'),
    ];
    $result = $plan($statement(source: 'stable'), $statement(source: 'stable'), $rows, $rows);
    $t->same('cursor-reusable', $result['status']);
    $t->same([1, 2], $result['retainedRowids']);
    $t->same([], $result['reprepareReasons']);
};

$tests['like glob current source next88 rejects unsupported operator'] = static function (TestRunner $t) use ($statement, $plan): void {
    $bad = $statement();
    $bad['operator'] = 'REGEXP';
    $t->throws(InvalidArgumentException::class, static fn () => $plan($bad, $statement()));
};

$tests['like glob current source next88 rejects unsupported collation'] = static function (TestRunner $t) use ($statement, $plan): void {
    $bad = $statement();
    $bad['collation'] = 'WP_LOCALE';
    $t->throws(InvalidArgumentException::class, static fn () => $plan($bad, $statement()));
};

$tests['like glob current source next88 rejects malformed escape'] = static function (TestRunner $t) use ($statement, $plan): void {
    $bad = $statement(escape: 'xx');
    $t->throws(InvalidArgumentException::class, static fn () => $plan($bad, $statement()));
};

$tests['like glob current source next88 rejects missing source'] = static function (TestRunner $t) use ($statement, $plan): void {
    $bad = $statement();
    unset($bad['source']);
    $t->throws(InvalidArgumentException::class, static fn () => $plan($bad, $statement()));
};

$tests['like glob current source next88 rejects malformed next utf8 bytes'] = static function (TestRunner $t) use ($statement, $plan, $currentRows): void {
    $next = [['option_id' => 1, 'option_name_bytes' => "plugin_\xc3", 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan($statement(), $statement(), $currentRows, $next));
};

$tests['like glob current source next88 rejects missing rowid'] = static function (TestRunner $t) use ($statement, $plan, $nextRows): void {
    $current = [['option_name_bytes' => 'plugin', 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan($statement(), $statement(), $current, $nextRows));
};

return $tests;
