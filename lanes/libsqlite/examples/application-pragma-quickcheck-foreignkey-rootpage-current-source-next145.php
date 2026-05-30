<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'PortLibs\\LibSqlite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$header = static function (int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 9), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows = [
    ['table', 'wp_archive_option_names', 'wp_archive_option_names', 6, 'CREATE TABLE wp_archive_option_names(name text primary key)'],
    ['index', 'wp_archive_option_names_name', 'wp_archive_option_names', 7, 'CREATE INDEX wp_archive_option_names_name ON wp_archive_option_names(name)'],
    ['table', 'wp_archive_options', 'wp_archive_options', 8, 'CREATE TABLE wp_archive_options(option_id integer primary key, option_name text)'],
];
$database = static function (array $entries, int $largestRootPage) use ($pageSize, $header, $putPointer, $schemaCell, $schemaRows): string {
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($entries as $entry) {
        $pointerMap = $putPointer($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $header($largestRootPage),
        ),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize),
        4 => SQLiteTableLeafPage::assemble([], $pageSize),
        5 => SQLiteIndexLeafPage::assemble([], $pageSize),
        6 => SQLiteTableLeafPage::assemble([], $pageSize),
        7 => SQLiteIndexLeafPage::assemble([], $pageSize),
        8 => SQLiteTableLeafPage::assemble([], $pageSize),
        9 => SQLiteTableLeafPage::assemble([], $pageSize),
    ];
    ksort($pages);

    return implode('', $pages);
};

$current = $database([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 5);
$next = $database([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 8);

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, 'CREATE ' . strtoupper($type) . ' ' . $name, $root);
$catalog = new SQLiteAttachedSchemaCatalog([]);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('table', 'wp_archive_option_names', 'wp_archive_option_names', 6),
    $record('table', 'wp_archive_options', 'wp_archive_options', 8),
]);
$schemas = static function (int $missing): array {
    $rows = [['rowid' => 1, 'option_name' => 'legacy_siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $rows[] = ['rowid' => 'archive-missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'archive' => [
            'tables' => [
                'wp_archive_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
                'wp_archive_options' => $rows,
            ],
            'foreignKeys' => [
                ['id' => 145, 'table' => 'wp_archive_options', 'parent' => 'wp_archive_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page = SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan::page(
    $current,
    $schemas(2),
    $catalog,
    $next,
    $schemas(0),
    $catalog,
    "SELECT * FROM archive.pragma_foreign_key_check('wp_archive_options')",
    'PRAGMA "archive".quick_check(wp_archive_options)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key_violations'] !== 2 || $page['current']['quick_check_errors'] !== 1 || $page['rows'][1]['target_source'] !== 'pragma-schema' || $page['current_source']['quick_check_sql'] !== 'pragma "archive".quick_check(wp_archive_options)') {
        fwrite(STDERR, "application-pragma-quickcheck-foreignkey-rootpage-current-source-next145 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-quickcheck-foreignkey-rootpage-current-source-next145 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-quickcheck-foreignkey-rootpage-current-source-next145',
    'applicationUse' => 'Gate attached Application archive imports on a resumable quick_check plus schema-qualified pragma_foreign_key_check rootpage stream.',
    'status' => $page['status'],
    'quickCheckSql' => $page['current_source']['quick_check_sql'],
    'current' => $page['current'],
    'next' => $page['next_counts'],
    'foreignKeyTarget' => [
        'schema' => $page['rows'][1]['target_schema'],
        'table' => $page['rows'][1]['target'],
        'source' => $page['rows'][1]['target_source'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
