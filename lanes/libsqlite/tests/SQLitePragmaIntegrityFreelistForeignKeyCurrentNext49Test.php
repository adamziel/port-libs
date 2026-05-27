<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityFreelistForeignKeyPreflight;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;

$headerPage = static function (int $pageCount, int $firstFreelist, int $freelistCount, int $largestRoot = 1) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRoot), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$makeDatabase = static function (?callable $mutatePointerMap = null, ?callable $mutateHeader = null) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::FREE_PAGE, 0);
    $pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    if ($mutatePointerMap !== null) {
        $pointerMap = $mutatePointerMap($pointerMap, $putPointerMapEntry);
    }

    $header = $headerPage(4, 3, 2);
    if ($mutateHeader !== null) {
        $header = $mutateHeader($header);
    }

    return implode('', [
        $header,
        $pointerMap,
        SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize),
        str_repeat("\0", $pageSize),
    ]);
};

$schemasWithViolation = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1],
            ],
            'wp_postmeta' => [
                ['rowid' => 10, 'post_id' => 1],
                ['rowid' => 11, 'post_id' => 99],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
            ],
            'wp_options' => [
                ['rowid' => 20, 'option_name' => 'siteurl'],
                ['rowid' => 21, 'option_name' => 'missing_plugin'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
        ],
    ],
];

$schemasClean = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1],
            ],
            'wp_postmeta' => [
                ['rowid' => 10, 'post_id' => 1],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
                ['rowid' => 2, 'name' => 'missing_plugin'],
            ],
            'wp_options' => [
                ['rowid' => 20, 'option_name' => 'siteurl'],
                ['rowid' => 21, 'option_name' => 'missing_plugin'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
        ],
    ],
];

$cleanPlan = static fn (): array => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA integrity_check', $makeDatabase(), $schemasClean);
$fkPlan = static fn (): array => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA integrity_check', $makeDatabase(), $schemasWithViolation);
$freelistPlan = static fn (): array => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA integrity_check', $makeDatabase(
    static fn (string $pointerMap, callable $put): string => $put($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 3)
), $schemasClean);
$bothPlan = static fn (): array => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA integrity_check(1)', $makeDatabase(
    static fn (string $pointerMap, callable $put): string => $put($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 3)
), $schemasWithViolation);
$badHeaderPlan = static fn (): array => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA quick_check=3', $makeDatabase(null, static fn (string $header): string => substr_replace($header, pack('N', 9), 36, 4)), $schemasClean);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'clean status ready' => [$cleanPlan, 'status', 'ready'],
    'clean next ready' => [$cleanPlan, 'next.ready', true],
    'clean next blocking empty' => [$cleanPlan, 'next.blocking.count', 0],
    'clean integrity pragma name' => [$cleanPlan, 'integrity.pragma', 'integrity_check'],
    'clean integrity limit' => [$cleanPlan, 'integrity.limit', 100],
    'clean integrity row ok' => [$cleanPlan, 'integrity.rows.0.integrity_check', 'ok'],
    'clean integrity errors empty' => [$cleanPlan, 'integrity.errors.count', 0],
    'clean freelist page count' => [$cleanPlan, 'freelist.page_count', 4],
    'clean freelist count' => [$cleanPlan, 'freelist.freelist_count', 2],
    'clean freelist first trunk' => [$cleanPlan, 'freelist.first_trunk', 3],
    'clean freelist auto vacuum' => [$cleanPlan, 'freelist.auto_vacuum', 'full'],
    'clean freelist status ok' => [$cleanPlan, 'freelist.status', 'ok'],
    'clean freelist errors empty' => [$cleanPlan, 'freelist.integrity_errors.count', 0],
    'clean foreign key status' => [$cleanPlan, 'foreign_keys.status', 'ok'],
    'clean foreign key pragma' => [$cleanPlan, 'foreign_keys.pragma', 'foreign_key_check'],
    'clean foreign key schemas count' => [$cleanPlan, 'foreign_keys.schemas.count', 2],
    'clean foreign key first schema main' => [$cleanPlan, 'foreign_keys.schemas.0', 'main'],
    'clean foreign key rows empty' => [$cleanPlan, 'foreign_keys.rows.count', 0],
    'clean current integrity count' => [$cleanPlan, 'current.integrity_errors', 0],
    'clean current foreign key count' => [$cleanPlan, 'current.foreign_key_violations', 0],
    'clean current freelist count' => [$cleanPlan, 'current.freelist_count', 2],
    'fk status blocked' => [$fkPlan, 'status', 'blocked'],
    'fk next not ready' => [$fkPlan, 'next.ready', false],
    'fk next only foreign key blocker count' => [$fkPlan, 'next.blocking.count', 1],
    'fk next foreign key blocker' => [$fkPlan, 'next.blocking.0', 'foreign_key_check'],
    'fk integrity stays clean' => [$fkPlan, 'current.integrity_errors', 0],
    'fk violation count' => [$fkPlan, 'current.foreign_key_violations', 2],
    'fk first row schema temp' => [$fkPlan, 'foreign_keys.rows.0.schema', 'temp'],
    'fk first row table' => [$fkPlan, 'foreign_keys.rows.0.table', 'wp_options'],
    'fk first rowid' => [$fkPlan, 'foreign_keys.rows.0.rowid', 21],
    'fk first parent' => [$fkPlan, 'foreign_keys.rows.0.parent', 'wp_option_names'],
    'fk first id' => [$fkPlan, 'foreign_keys.rows.0.fkid', 0],
    'fk second row schema main' => [$fkPlan, 'foreign_keys.rows.1.schema', 'main'],
    'fk second row table' => [$fkPlan, 'foreign_keys.rows.1.table', 'wp_postmeta'],
    'fk second rowid' => [$fkPlan, 'foreign_keys.rows.1.rowid', 11],
    'fk second parent' => [$fkPlan, 'foreign_keys.rows.1.parent', 'wp_posts'],
    'freelist status blocked' => [$freelistPlan, 'status', 'blocked'],
    'freelist next not ready' => [$freelistPlan, 'next.ready', false],
    'freelist blocker count' => [$freelistPlan, 'next.blocking.count', 1],
    'freelist blocker integrity' => [$freelistPlan, 'next.blocking.0', 'integrity_check'],
    'freelist current integrity count' => [$freelistPlan, 'current.integrity_errors', 1],
    'freelist current foreign key count' => [$freelistPlan, 'current.foreign_key_violations', 0],
    'freelist status field blocked' => [$freelistPlan, 'freelist.status', 'blocked'],
    'freelist error count' => [$freelistPlan, 'freelist.integrity_errors.count', 1],
    'freelist first error' => [$freelistPlan, 'freelist.integrity_errors.0', 'freelist page 4 pointer-map type btree-page does not match expected free-page'],
    'both status blocked' => [$bothPlan, 'status', 'blocked'],
    'both blockers count' => [$bothPlan, 'next.blocking.count', 2],
    'both first blocker integrity' => [$bothPlan, 'next.blocking.0', 'integrity_check'],
    'both second blocker fk' => [$bothPlan, 'next.blocking.1', 'foreign_key_check'],
    'both integrity limited' => [$bothPlan, 'current.integrity_errors', 1],
    'both foreign key still counted' => [$bothPlan, 'current.foreign_key_violations', 2],
    'quick pragma name preserved' => [$badHeaderPlan, 'integrity.pragma', 'quick_check'],
    'quick parsed limit preserved' => [$badHeaderPlan, 'integrity.limit', 3],
    'quick header mismatch blocks' => [$badHeaderPlan, 'status', 'blocked'],
    'quick freelist status blocks count mismatch text' => [$badHeaderPlan, 'freelist.status', 'blocked'],
    'quick current integrity count' => [$badHeaderPlan, 'current.integrity_errors', 1],
    'quick first integrity error' => [$badHeaderPlan, 'integrity.errors.0', 'freelist header count 9 does not match reachable freelist page count 2'],
    'quick no foreign key violations' => [$badHeaderPlan, 'current.foreign_key_violations', 0],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity freelist foreignkey current next49 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity freelist foreignkey current next49 rejects malformed integrity sql'] = static function (TestRunner $t) use ($makeDatabase, $schemasClean): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA foreign_key_check', $makeDatabase(), $schemasClean));
};

$tests['pragma integrity freelist foreignkey current next49 rejects invalid database bytes'] = static function (TestRunner $t) use ($schemasClean): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityFreelistForeignKeyPreflight::plan('PRAGMA integrity_check', 'not sqlite', $schemasClean));
};

return $tests;
