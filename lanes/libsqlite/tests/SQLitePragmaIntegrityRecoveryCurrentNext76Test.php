<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityRecoveryCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];
$pageSize = 512;

$headerPage = static function (int $pageCount, int $largestRootPage = 3) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$database = static function (array $pages, array $pointerMapEntries, int $largestRootPage = 3) use ($headerPage, $putPointerMapEntry, $pageSize): string {
    $pageCount = max(array_keys($pages + [3 => true]));
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($pointerMapEntries as [$pageNumber, $type, $parent]) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $type, $parent);
    }
    $images = [
        1 => $headerPage($pageCount, $largestRootPage),
        2 => $pointerMap,
    ];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $images[$pageNumber] = $pages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($images);

    return implode('', $images);
};

$tableLeafDatabase = static function (array $rowIds, int $pointerType = SQLitePointerMapEntry::ROOT_PAGE, int $pointerParent = 0) use ($database, $pageSize): string {
    return $database([
        3 => SQLiteTableLeafPage::assemble(array_map(
            static fn (int $rowId): string => SQLiteTableLeafCell::encode($rowId, "option-{$rowId}"),
            $rowIds,
        ), $pageSize),
    ], [
        [3, $pointerType, $pointerParent],
    ]);
};

$withBadWriteVersion = static fn (string $bytes): string => substr_replace($bytes, "\x09", 18, 1);

$schemasBad = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'id' => 1, 'post_parent' => null],
                ['rowid' => 7, 'id' => 7, 'post_parent' => 404],
                ['rowid' => 8, 'id' => 8, 'post_parent' => 1],
            ],
            'wp_terms' => [
                ['rowid' => 9, 'parent' => 77],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_posts', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_parent', 'parent' => 'id', 'affinity' => 'integer']]],
            ['id' => 1, 'table' => 'wp_terms', 'parent' => 'wp_terms', 'columns' => [['child' => 'parent', 'parent' => 'rowid', 'affinity' => 'integer']]],
        ],
    ],
];
$schemasPartiallyFixed = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'id' => 1, 'post_parent' => null],
                ['rowid' => 7, 'id' => 7, 'post_parent' => 404],
                ['rowid' => 8, 'id' => 8, 'post_parent' => 1],
            ],
            'wp_terms' => [
                ['rowid' => 77, 'parent' => null],
                ['rowid' => 9, 'parent' => 77],
            ],
        ],
        'foreignKeys' => $schemasBad['main']['foreignKeys'],
    ],
];
$schemasClean = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'id' => 1, 'post_parent' => null],
                ['rowid' => 7, 'id' => 7, 'post_parent' => 1],
                ['rowid' => 8, 'id' => 8, 'post_parent' => 1],
            ],
            'wp_terms' => [
                ['rowid' => 77, 'parent' => null],
                ['rowid' => 9, 'parent' => 77],
            ],
        ],
        'foreignKeys' => $schemasBad['main']['foreignKeys'],
    ],
];

$clean = $tableLeafDatabase([1, 2, 3]);
$badOrder = $tableLeafDatabase([1, 9, 2]);
$badPointer = $tableLeafDatabase([1, 2, 3], SQLitePointerMapEntry::BTREE_PAGE, 9);
$badHeaderAndOrder = $withBadWriteVersion($badOrder);
$badHeaderOnly = $withBadWriteVersion($clean);

$operations = [
    ['op' => 'write', 'path' => '/wp-content/database/.ht.sqlite', 'reason' => 'apply_hot_journal_page_image'],
    ['op' => 'sync', 'path' => '/wp-content/database/.ht.sqlite', 'reason' => 'sync_database_after_recovery'],
];

$resolved = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($badOrder, $clean, [], [], 'PRAGMA integrity_check', $operations);
$partial = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($badHeaderAndOrder, $badHeaderOnly, [], [], 'PRAGMA integrity_check', $operations);
$introduced = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($clean, $badPointer, [], [], 'PRAGMA integrity_check', $operations);
$preserved = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($badOrder, $badOrder, [], [], 'PRAGMA integrity_check', $operations);
$cleanPlan = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($clean, $clean, [], [], 'PRAGMA integrity_check', $operations);
$fkResolved = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($clean, $clean, $schemasBad, $schemasClean, 'PRAGMA integrity_check', $operations);
$fkPartial = static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($clean, $clean, $schemasBad, $schemasPartiallyFixed, 'PRAGMA integrity_check', $operations);

$valueAt = static function (array $value, string $path): mixed {
    $cursor = $value;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        throw new RuntimeException("Missing path {$path}");
    }

    return $cursor;
};

$cases = [
    'resolved status' => [$resolved, 'status', 'recovery_resolved_integrity_findings'],
    'resolved reason' => [$resolved, 'reason', 'current_dirty_database_next_recovered_integrity_snapshot'],
    'resolved integrity sql' => [$resolved, 'integrity_sql', 'PRAGMA integrity_check'],
    'resolved current total' => [$resolved, 'current.total', 1],
    'resolved next total' => [$resolved, 'next.total', 0],
    'resolved count' => [$resolved, 'resolved_count', 1],
    'resolved persisting count' => [$resolved, 'persisting_count', 0],
    'resolved introduced count' => [$resolved, 'introduced_count', 0],
    'resolved does not block commit' => [$resolved, 'must_block_commit', false],
    'resolved source btree count' => [$resolved, 'current.counts.btree', 1],
    'resolved next btree count' => [$resolved, 'next.counts.btree', 0],
    'resolved row source' => [$resolved, 'resolved.0.source', 'btree'],
    'resolved row page' => [$resolved, 'resolved.0.page', 3],
    'resolved row pointer map page' => [$resolved, 'resolved.0.pointer_map_page', 2],
    'resolved message' => [$resolved, 'resolved.0.message', 'btree page 3: table leaf rowid 2 is not greater than previous rowid 9'],
    'resolved current messages' => [$resolved, 'current.messages.0', 'btree page 3: table leaf rowid 2 is not greater than previous rowid 9'],
    'resolved operations preserved' => [$resolved, 'recovery_operations.0.reason', 'apply_hot_journal_page_image'],
    'resolved dependencies include gate' => [static fn (): mixed => in_array('sqlite-recovery-current-next-integrity-gate', $resolved()['dependencies'], true), '', true],
    'partial status' => [$partial, 'status', 'recovery_partially_resolved_integrity_findings'],
    'partial current total' => [$partial, 'current.total', 2],
    'partial next total' => [$partial, 'next.total', 1],
    'partial resolved count' => [$partial, 'resolved_count', 1],
    'partial persisting count' => [$partial, 'persisting_count', 1],
    'partial introduced count' => [$partial, 'introduced_count', 0],
    'partial blocks commit' => [$partial, 'must_block_commit', true],
    'partial resolved source' => [$partial, 'resolved.0.source', 'btree'],
    'partial persisting source' => [$partial, 'persisting.0.source', 'header'],
    'partial persisting message' => [$partial, 'persisting.0.message', 'invalid schema write version 9'],
    'introduced status' => [$introduced, 'status', 'recovery_introduced_integrity_findings'],
    'introduced current total' => [$introduced, 'current.total', 0],
    'introduced next total' => [$introduced, 'next.total', 2],
    'introduced count' => [$introduced, 'introduced_count', 2],
    'introduced blocks commit' => [$introduced, 'must_block_commit', true],
    'introduced row source' => [$introduced, 'introduced.0.source', 'pointer_map'],
    'introduced row page' => [$introduced, 'introduced.0.page', 3],
    'introduced next pointer map count' => [$introduced, 'next.counts.pointer_map', 2],
    'preserved status' => [$preserved, 'status', 'recovery_preserved_integrity_findings'],
    'preserved current total' => [$preserved, 'current.total', 1],
    'preserved next total' => [$preserved, 'next.total', 1],
    'preserved resolved count' => [$preserved, 'resolved_count', 0],
    'preserved persisting count' => [$preserved, 'persisting_count', 1],
    'preserved blocks commit' => [$preserved, 'must_block_commit', true],
    'clean status' => [$cleanPlan, 'status', 'recovery_integrity_clean'],
    'clean current total' => [$cleanPlan, 'current.total', 0],
    'clean next total' => [$cleanPlan, 'next.total', 0],
    'clean block commit false' => [$cleanPlan, 'must_block_commit', false],
    'foreign key resolved status' => [$fkResolved, 'status', 'recovery_resolved_integrity_findings'],
    'foreign key current total' => [$fkResolved, 'current.total', 2],
    'foreign key next total' => [$fkResolved, 'next.total', 0],
    'foreign key resolved count' => [$fkResolved, 'resolved_count', 2],
    'foreign key source count' => [$fkResolved, 'current.counts.foreign_key', 2],
    'foreign key first table' => [$fkResolved, 'resolved.0.table', 'wp_posts'],
    'foreign key second table' => [$fkResolved, 'resolved.1.table', 'wp_terms'],
    'foreign key first message' => [$fkResolved, 'resolved.0.message', 'foreign key mismatch in main.wp_posts rowid 7 references wp_posts fkid 0'],
    'foreign key partial status' => [$fkPartial, 'status', 'recovery_partially_resolved_integrity_findings'],
    'foreign key partial resolved' => [$fkPartial, 'resolved_count', 1],
    'foreign key partial persisting' => [$fkPartial, 'persisting_count', 1],
    'foreign key partial blocks' => [$fkPartial, 'must_block_commit', true],
    'quick check skips btree and stays clean' => [static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($badOrder, $badOrder, [], [], 'PRAGMA quick_check'), 'status', 'recovery_integrity_clean'],
    'quick check metadata preserved' => [static fn (): array => SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($badOrder, $badOrder, [], [], 'PRAGMA quick_check'), 'integrity_sql', 'PRAGMA quick_check'],
];

foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity recovery current next76 ' . $name] = static function (TestRunner $t) use ($callback, $path, $expected, $valueAt): void {
        $value = $path === '' ? $callback() : $valueAt($callback(), $path);
        $t->same($expected, $value);
    };
}

$tests['pragma integrity recovery current next76 propagates integrity parser guard'] = static function (TestRunner $t) use ($clean): void {
    try {
        SQLitePragmaIntegrityRecoveryCurrentNextPlan::compare($clean, $clean, [], [], 'PRAGMA table_info(wp_options)');
    } catch (InvalidArgumentException $exception) {
        $t->same('Unsupported SQLite integrity PRAGMA SQL', $exception->getMessage());
        return;
    }

    $t->fail('unsupported pragma should be rejected');
};

return $tests;
