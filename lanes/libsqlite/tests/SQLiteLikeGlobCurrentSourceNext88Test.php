<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteLikeGlobCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name' => $name,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'load_policy' => $load_policy,
    ];
};

$currentRows = [
    $row(1, 'Module_Alpha', 'UTF-8', 'no'),
    $row(2, 'module_alpha', 'UTF-16LE'),
    $row(3, 'module_beta', 'UTF-16BE'),
    $row(4, 'module_100%_enabled', 'UTF-16LE'),
    $row(5, 'module_100x_enabled', 'UTF-16BE'),
    $row(6, 'module_éclair', 'UTF-8'),
    $row(7, 'module_😀_cache', 'UTF-16LE'),
    $row(8, 'bundle_alpha', 'UTF-8'),
    $row(9, 'module_old', 'UTF-8'),
    $row(10, 'module_beta ', 'UTF-8'),
];

$nextRows = [
    $row(1, 'Module_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'module_alpha', 'UTF-16LE'),
    $row(3, 'module_beta', 'UTF-8'),
    $row(4, 'module_100%_enabled', 'UTF-16BE'),
    $row(5, 'module_100x_enabled', 'UTF-16BE'),
    $row(6, 'module_éclair', 'UTF-8'),
    $row(7, 'module_😀_cache_v2', 'UTF-16LE'),
    $row(8, 'bundle_alpha', 'UTF-8'),
    $row(11, 'module_new', 'UTF-16BE'),
    $row(12, 'Module_Éclair', 'UTF-16LE', 'no'),
];

$statement = static function (
    string $pattern = 'module%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $source = 'main.app_settings@cookie10',
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
    'source switch status requires reprepare' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'status', 'reprepare-required'],
    'source switch boolean requires reprepare' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'reprepareRequired', true],
    'source switch records source reason first' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'reprepareReasons.0', 'source-name'],
    'source switch records text encoding reason' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'reprepareReasons.1', 'text-encoding'],
    'source switch records key byte reason' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'reprepareReasons.2', 'key-bytes'],
    'source switch records matched rowset reason' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'reprepareReasons.3', 'matched-rowset'],
    'current rowids use prepared pattern' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'current.rowids', [4, 5, 1, 2, 3, 10, 9, 6, 7]],
    'next rowids use next prepared pattern' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'next.rowids', [4, 5, 1, 2, 3, 11, 12, 6, 7]],
    'retained rowids preserve current cursor order' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'retainedRowids', [4, 5, 1, 2, 3, 6, 7]],
    'exited rowids expose stale cursor members' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'exitedRowids', [10, 9]],
    'entered rowids expose next cursor members' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'enteredRowids', [11, 12]],
    'changed encoding rowids include rebuilt source keys' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'changedEncodingRowids', [1, 3, 4]],
    'changed byte rowids include renames and encodings' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'changedBytesRowids', [1, 3, 4, 7]],
    'current LIKE range is nocase prefix' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'current.range.lowerInclusive', 'module'],
    'next LIKE range has same prefix upper bound' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'next.range.upperBound', 'modulf'],
    'current row one encoding is utf8' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'current.encodings.1', 'UTF-8'],
    'next row one encoding is utf16le' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'next.encodings.1', 'UTF-16LE'],
    'current bytes expose utf16 percent row' => [$statement('module\_100\%%', escape: '\\'), $statement('module\_100\%%', escape: '\\', source: 'main.app_settings@cookie11'), 'current.bytesHex.4', '6d006f00640075006c0065005f0031003000300025005f0065006e00610062006c0065006400'],
    'next bytes expose utf16be percent row' => [$statement('module\_100\%%', escape: '\\'), $statement('module\_100\%%', escape: '\\', source: 'main.app_settings@cookie11'), 'next.bytesHex.4', '006d006f00640075006c0065005f0031003000300025005f0065006e00610062006c00650064'],
    'escaped percent rowset is retained' => [$statement('module\_100\%%', escape: '\\'), $statement('module\_100\%%', escape: '\\', source: 'main.app_settings@cookie11'), 'retainedRowids', [4]],
    'escaped percent has no entered rows' => [$statement('module\_100\%%', escape: '\\'), $statement('module\_100\%%', escape: '\\', source: 'main.app_settings@cookie11'), 'enteredRowids', []],
    'escaped percent still invalidates on source' => [$statement('module\_100\%%', escape: '\\'), $statement('module\_100\%%', escape: '\\', source: 'main.app_settings@cookie11'), 'reprepareReasons.0', 'source-name'],
    'escape change requires reprepare' => [$statement('module\_100\%%', escape: '\\'), $statement('module#_100#%%', escape: '#'), 'reprepareReasons.0', 'pattern'],
    'escape change records escape reason' => [$statement('module\_100\%%', escape: '\\'), $statement('module#_100#%%', escape: '#'), 'reprepareReasons.1', 'escape'],
    'operator change requires reprepare' => [$statement('module%', 'LIKE'), $statement('module_*', 'GLOB'), 'reprepareReasons.0', 'operator'],
    'operator change records pattern reason' => [$statement('module%', 'LIKE'), $statement('module_*', 'GLOB'), 'reprepareReasons.1', 'pattern'],
    'glob current rowids are case sensitive' => [$statement('module_*', 'GLOB', 'BINARY'), $statement('module_*', 'GLOB', 'BINARY'), 'current.rowids', [4, 5, 2, 3, 10, 9, 6, 7]],
    'glob next rowids skip uppercase module' => [$statement('module_*', 'GLOB', 'BINARY'), $statement('module_*', 'GLOB', 'BINARY'), 'next.rowids', [4, 5, 2, 3, 11, 6, 7]],
    'glob range lower is literal prefix' => [$statement('module_*', 'GLOB', 'BINARY'), $statement('module_*', 'GLOB', 'BINARY'), 'current.range.lowerInclusive', 'module_'],
    'glob range upper is next literal prefix' => [$statement('module_*', 'GLOB', 'BINARY'), $statement('module_*', 'GLOB', 'BINARY'), 'current.range.upperBound', 'module`'],
    'collation change records collation reason' => [$statement('module%', collation: 'NOCASE'), $statement('module%', collation: 'BINARY'), 'reprepareReasons.0', 'collation'],
    'case sensitive like change records pragma reason' => [$statement('module%', collation: 'BINARY', caseSensitiveLike: false), $statement('module%', collation: 'BINARY', caseSensitiveLike: true), 'reprepareReasons.0', 'case-sensitive-like'],
    'case sensitive like next skips uppercase source row' => [$statement('module%', collation: 'BINARY', caseSensitiveLike: false), $statement('module%', collation: 'BINARY', caseSensitiveLike: true), 'next.rowids', [4, 5, 2, 3, 11, 6, 7]],
    'pattern change records pattern reason' => [$statement('module%'), $statement('bundle%'), 'reprepareReasons.0', 'pattern'],
    'bundle next rowset stays retained' => [$statement('bundle%'), $statement('bundle%'), 'retainedRowids', [8]],
    'stable bundle cursor is reusable' => [$statement('bundle%', source: 'stable'), $statement('bundle%', source: 'stable'), 'status', 'cursor-reusable'],
    'stable bundle cursor has no reasons' => [$statement('bundle%', source: 'stable'), $statement('bundle%', source: 'stable'), 'reprepareReasons', []],
    'leading wildcard has null range' => [$statement('%module'), $statement('%module'), 'current.range', null],
    'leading wildcard has empty current rowids' => [$statement('%module'), $statement('%module'), 'current.rowids', []],
    'leading wildcard source change still requires reprepare' => [$statement('%module'), $statement('%module', source: 'next'), 'reprepareReasons', ['source-name']],
    'emoji glob retained after rename' => [$statement('module_😀*', 'GLOB', 'BINARY'), $statement('module_😀*', 'GLOB', 'BINARY'), 'retainedRowids', [7]],
    'emoji glob byte change requires reprepare' => [$statement('module_😀*', 'GLOB', 'BINARY'), $statement('module_😀*', 'GLOB', 'BINARY'), 'reprepareReasons', ['key-bytes']],
    'latin glob current row is e acute' => [$statement('module_[À-ÿ]*', 'GLOB', 'BINARY'), $statement('module_[À-ÿ]*', 'GLOB', 'BINARY'), 'current.rowids', [6]],
    'latin glob next row is e acute' => [$statement('module_[À-ÿ]*', 'GLOB', 'BINARY'), $statement('module_[À-ÿ]*', 'GLOB', 'BINARY'), 'next.rowids', [6]],
    'dependency records current source next guard' => [$statement(), $statement(source: 'main.app_settings@cookie11'), 'dependencies.2', 'sqlite-current-source-next-statement-reprepare'],
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

$tests['like glob current source next88 stable identical module source reuses cursor'] = static function (TestRunner $t) use ($row, $statement, $plan): void {
    $rows = [
        $row(1, 'module_alpha', 'UTF-8'),
        $row(2, 'module_beta', 'UTF-16LE'),
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
    $bad['collation'] = 'APP_LOCALE';
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
    $next = [['setting_id' => 1, 'key_name_bytes' => "module_\xc3", 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan($statement(), $statement(), $currentRows, $next));
};

$tests['like glob current source next88 rejects missing rowid'] = static function (TestRunner $t) use ($statement, $plan, $nextRows): void {
    $current = [['key_name_bytes' => 'module', 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan($statement(), $statement(), $current, $nextRows));
};

return $tests;
