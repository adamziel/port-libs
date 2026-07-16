<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;

$pageSize = 512;
$pageCount = 75;

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$makeDatabase = static function (bool $validPointers = false) use ($pageSize, $pageCount, $putPointerMapEntry): string {
    $header = str_repeat("\0", $pageSize);
    $header = substr_replace($header, "SQLite format 3\0", 0, 16);
    $header = substr_replace($header, pack('n', $pageSize), 16, 2);
    $header[18] = "\x01";
    $header[19] = "\x01";
    $header = substr_replace($header, pack('N', $pageCount), 28, 4);
    $header = substr_replace($header, pack('N', 3), 52, 4);
    $header = substr_replace($header, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $pointerMap = $putPointerMapEntry(
            $pointerMap,
            $pageNumber,
            SQLitePointerMapEntry::BTREE_PAGE,
            $validPointers ? 3 : 0,
        );
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$schemas = static function (): array {
    $autoloadRows = [];
    for ($i = 1; $i <= 9; $i++) {
        $autoloadRows[] = ['rowid' => 'autoload-' . $i, 'option_name' => 'missing_' . $i, 'autoload' => 'yes'];
    }
    $autoloadRows[] = ['rowid' => 'autoload-null', 'option_name' => null, 'autoload' => 'yes'];

    $transientRows = [];
    for ($i = 1; $i <= 4; $i++) {
        $transientRows[] = ['rowid' => 'transient-' . $i, 'option_name' => 'transient_' . $i];
    }

    $postmetaRows = [];
    for ($i = 1; $i <= 6; $i++) {
        $postmetaRows[] = ['rowid' => $i, 'post_id' => 1000 + $i];
    }

    return [
        'temp' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $autoloadRows,
                'wp_transient_options' => $transientRows,
            ],
            'foreignKeys' => [
                ['id' => 7, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
                ['id' => 8, 'table' => 'wp_transient_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'binary']]],
            ],
        ],
        'main' => [
            'tables' => [
                'wp_posts' => [['rowid' => 1, 'ID' => 1]],
                'wp_postmeta' => $postmetaRows,
            ],
            'foreignKeys' => [
                ['id' => 4, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
            ],
        ],
    ];
};

$database = $makeDatabase();
$schemasValue = $schemas();

$tempPage0 = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 0, 73);
$tempPage1 = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 73, 73);
$tempSmall = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 70, 6);
$mainPage = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA main.foreign_key_check("wp_postmeta")', 0, 73);
$quickTemp = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 0, 73, 'PRAGMA quick_check');
$collectTemp = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::collectForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)');

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'temp page0 status' => [$tempPage0, 'status', 'ok'],
    'temp page0 offset' => [$tempPage0, 'offset', 0],
    'temp page0 limit current next73' => [$tempPage0, 'limit', 73],
    'temp page0 count current next73' => [$tempPage0, 'count', 73],
    'temp page0 total targeted' => [$tempPage0, 'total', 81],
    'temp page0 next offset' => [$tempPage0, 'next_offset', 73],
    'temp page0 incomplete' => [$tempPage0, 'complete', false],
    'temp page0 pointer count' => [$tempPage0, 'current.pointer_map', 72],
    'temp page0 foreign key count only target table' => [$tempPage0, 'current.foreign_key', 9],
    'temp page0 first source' => [$tempPage0, 'rows.0.source', 'pointer_map'],
    'temp page0 first page' => [$tempPage0, 'rows.0.page', 4],
    'temp page0 first pointer map page' => [$tempPage0, 'rows.0.pointer_map_page', 2],
    'temp page0 twentieth page' => [$tempPage0, 'rows.19.page', 23],
    'temp page0 fortieth page' => [$tempPage0, 'rows.39.page', 43],
    'temp page0 sixtieth page' => [$tempPage0, 'rows.59.page', 63],
    'temp page0 last pointer source' => [$tempPage0, 'rows.71.source', 'pointer_map'],
    'temp page0 last pointer page' => [$tempPage0, 'rows.71.page', 75],
    'temp page0 first fk source' => [$tempPage0, 'rows.72.source', 'foreign_key'],
    'temp page0 first fk kind' => [$tempPage0, 'rows.72.kind', 'foreign_key_check'],
    'temp page0 first fk schema' => [$tempPage0, 'rows.72.schema', 'temp'],
    'temp page0 first fk table' => [$tempPage0, 'rows.72.table', 'wp_options'],
    'temp page0 first fk rowid' => [$tempPage0, 'rows.72.rowid', 'autoload-1'],
    'temp page0 first fk parent' => [$tempPage0, 'rows.72.parent', 'wp_option_names'],
    'temp page0 first fk fkid' => [$tempPage0, 'rows.72.fkid', 7],
    'temp page0 first fk page null' => [$tempPage0, 'rows.72.page', null],
    'temp page0 first fk pointer map null' => [$tempPage0, 'rows.72.pointer_map_page', null],
    'temp page0 first fk message' => [$tempPage0, 'rows.72.message', 'foreign key mismatch in temp.wp_options rowid autoload-1 references wp_option_names fkid 7'],
    'temp page1 status' => [$tempPage1, 'status', 'ok'],
    'temp page1 offset' => [$tempPage1, 'offset', 73],
    'temp page1 count remaining' => [$tempPage1, 'count', 8],
    'temp page1 total stable' => [$tempPage1, 'total', 81],
    'temp page1 complete' => [$tempPage1, 'complete', true],
    'temp page1 next offset null' => [$tempPage1, 'next_offset', null],
    'temp page1 first row source' => [$tempPage1, 'rows.0.source', 'foreign_key'],
    'temp page1 first rowid continues' => [$tempPage1, 'rows.0.rowid', 'autoload-2'],
    'temp page1 fifth rowid' => [$tempPage1, 'rows.4.rowid', 'autoload-6'],
    'temp page1 final source' => [$tempPage1, 'rows.7.source', 'foreign_key'],
    'temp page1 final schema' => [$tempPage1, 'rows.7.schema', 'temp'],
    'temp page1 final table' => [$tempPage1, 'rows.7.table', 'wp_options'],
    'temp page1 final rowid' => [$tempPage1, 'rows.7.rowid', 'autoload-9'],
    'temp page1 final message' => [$tempPage1, 'rows.7.message', 'foreign key mismatch in temp.wp_options rowid autoload-9 references wp_option_names fkid 7'],
    'temp small offset' => [$tempSmall, 'offset', 70],
    'temp small count' => [$tempSmall, 'count', 6],
    'temp small next offset' => [$tempSmall, 'next_offset', 76],
    'temp small first pointer page' => [$tempSmall, 'rows.0.page', 74],
    'temp small second pointer page' => [$tempSmall, 'rows.1.page', 75],
    'temp small crosses into fk source' => [$tempSmall, 'rows.2.source', 'foreign_key'],
    'temp small crosses into fk rowid' => [$tempSmall, 'rows.2.rowid', 'autoload-1'],
    'temp small last rowid' => [$tempSmall, 'rows.5.rowid', 'autoload-4'],
    'main status' => [$mainPage, 'status', 'ok'],
    'main count limited to page' => [$mainPage, 'count', 73],
    'main total targeted' => [$mainPage, 'total', 78],
    'main next offset' => [$mainPage, 'next_offset', 73],
    'main pointer count' => [$mainPage, 'current.pointer_map', 72],
    'main foreign key count only main target' => [$mainPage, 'current.foreign_key', 6],
    'main first fk schema' => [$mainPage, 'rows.72.schema', 'main'],
    'main first fk table' => [$mainPage, 'rows.72.table', 'wp_postmeta'],
    'main first fk rowid' => [$mainPage, 'rows.72.rowid', 1],
    'main first fk parent' => [$mainPage, 'rows.72.parent', 'wp_posts'],
    'main first fk fkid' => [$mainPage, 'rows.72.fkid', 4],
    'main first fk message' => [$mainPage, 'rows.72.message', 'foreign key mismatch in main.wp_postmeta rowid 1 references wp_posts fkid 4'],
    'quick temp status' => [$quickTemp, 'status', 'ok'],
    'quick temp count' => [$quickTemp, 'count', 9],
    'quick temp total skips pointer map' => [$quickTemp, 'total', 9],
    'quick temp complete' => [$quickTemp, 'complete', true],
    'quick temp pointer zero' => [$quickTemp, 'current.pointer_map', 0],
    'quick temp foreign key target count' => [$quickTemp, 'current.foreign_key', 9],
    'quick temp first source fk' => [$quickTemp, 'rows.0.source', 'foreign_key'],
    'quick temp final rowid' => [$quickTemp, 'rows.8.rowid', 'autoload-9'],
    'collect temp count' => [$collectTemp, 'count', 81],
    'collect temp last pointer page' => [$collectTemp, '71.page', 75],
    'collect temp first fk rowid' => [$collectTemp, '72.rowid', 'autoload-1'],
    'collect temp final rowid' => [$collectTemp, '80.rowid', 'autoload-9'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity foreign key current next73 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity foreign key current next73 collect matches page concatenation'] = static function (TestRunner $t) use ($collectTemp, $tempPage0, $tempPage1): void {
    $t->same($collectTemp(), array_merge($tempPage0()['rows'], $tempPage1()['rows']));
};

$tests['pragma integrity foreign key current next73 target excludes sibling temp table'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_transient_options)', 0, 90);
    $t->same(['total' => 76, 'foreign_key' => 4, 'first_fk' => 'transient-1'], ['total' => $page['total'], 'foreign_key' => $page['current']['foreign_key'], 'first_fk' => $page['rows'][72]['rowid']]);
};

$tests['pragma integrity foreign key current next73 quoted target identifier is unquoted'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, "PRAGMA temp.foreign_key_check('wp_options')", 80, 4);
    $t->same(['count' => 1, 'rowid' => 'autoload-9', 'complete' => true], ['count' => $page['count'], 'rowid' => $page['rows'][0]['rowid'], 'complete' => $page['complete']]);
};

$tests['pragma integrity foreign key current next73 clean pointers still target foreign keys'] = static function (TestRunner $t) use ($makeDatabase, $schemasValue): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($makeDatabase(true), $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 0, 73);
    $t->same(['total' => 9, 'pointer_map' => 0, 'foreign_key' => 9, 'first_source' => 'foreign_key'], ['total' => $page['total'], 'pointer_map' => $page['current']['pointer_map'], 'foreign_key' => $page['current']['foreign_key'], 'first_source' => $page['rows'][0]['source']]);
};

$tests['pragma integrity foreign key current next73 tail offset returns empty complete page'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 81, 73);
    $t->same(['count' => 0, 'total' => 81, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity foreign key current next73 rejects non foreign key pragma'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA foreign_keys', 0, 73));
};

$tests['pragma integrity foreign key current next73 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', -1, 73));
};

$tests['pragma integrity foreign key current next73 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 0, 0));
};

$tests['pragma integrity foreign key current next73 propagates integrity parser guard'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemasValue, 'PRAGMA temp.foreign_key_check(wp_options)', 0, 73, 'PRAGMA foreign_key_check'));
};

return $tests;
