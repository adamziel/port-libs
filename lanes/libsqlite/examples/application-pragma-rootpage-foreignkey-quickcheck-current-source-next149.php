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
    $page = substr_replace($page, pack('N', 8), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_option_names_name', 'wp_option_names', 7, 'CREATE UNIQUE INDEX wp_option_names_name ON wp_option_names(name)'],
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
    ];
    ksort($pages);

    return implode('', $pages);
};

$current = $database([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 6],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 4);
$next = $database([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
], 7);

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, 'CREATE ' . strtoupper($type) . ' ' . $name, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 6),
]);
$schemas = static function (int $missing): array {
    $options = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $options[] = ['rowid' => 'autoload-missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $options,
            ],
            'foreignKeys' => [
                ['id' => 149, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$page = SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan::page(
    $current,
    $schemas(4),
    $catalog,
    $next,
    $schemas(0),
    $catalog,
    'PRAGMA foreign_key_check(wp_options)',
    'PRAGMA quick_check(2)',
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['quick_check_errors'] !== 2 || $page['current']['foreign_key_violations'] !== 4 || $page['delta']['total'] !== -6) {
        fwrite(STDERR, "application-pragma-rootpage-foreignkey-quickcheck-current-source-next149 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-rootpage-foreignkey-quickcheck-current-source-next149 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-rootpage-foreignkey-quickcheck-current-source-next149',
    'applicationUse' => 'Run bounded PRAGMA quick_check(N) rootpage diagnostics before a full foreign_key_check during copied wp_options import repair.',
    'status' => $page['status'],
    'quickCheckLimit' => $page['current_source']['quick_check_limit'],
    'current' => $page['current'],
    'next' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
