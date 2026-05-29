<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage126 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry126 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$makeOverflowPage126 = static fn (?int $nextPage, string $payload): string => substr_replace(
    str_pad(pack('N', $nextPage ?? 0) . $payload, 512, "\0"),
    pack('N', $nextPage ?? 0),
    0,
    4,
);

$databaseFixture126 = static function () use ($makeFirstPage126, $putPointerMapEntry126, $makeOverflowPage126): SQLiteDatabase {
    $pages = array_fill(1, 8, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage126(8, 8, 1);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[5] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $makeOverflowPage126(7, str_repeat('R', 508));
    $pages[7] = $makeOverflowPage126(null, str_repeat('S', 192));
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

    $putPointerMapEntry126($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry126($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry126($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry126($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry126($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry126($pages, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$rootImage126 = static fn (): array => [
    6 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 1, 1, 'published'])),
    ]),
];

$fixture126 = static function (string $objectType = 'table', bool $secureDelete = false) use ($databaseFixture126, $rootImage126): SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan {
    return SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan::fromOverflowChains(
        $databaseFixture126(),
        [[
            'source' => 'wp-option-delete-rootpage-overflow-chain',
            'first_page' => 6,
            'overflow_payload_bytes' => 700,
            'rowids' => [12601],
        ]],
        $objectType,
        $objectType === 'table' ? 'wp_import_stage_next126' : 'wp_import_stage_next126_status',
        'wp_import_stage_next126',
        $objectType === 'table'
            ? 'CREATE TABLE wp_import_stage_next126 (ID INTEGER PRIMARY KEY, post_id INTEGER, meta_id INTEGER, status TEXT)'
            : 'CREATE INDEX wp_import_stage_next126_status ON wp_import_stage_next126(status)',
        $rootImage126(),
        $secureDelete,
    );
};

$throwsMessage126 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows126 = static fn (SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan $plan): array => $plan->rootpageRows;

$cases126 = [
    'action label' => static fn (): mixed => $fixture126()->toArray()['action'],
    'schema object type' => static fn (): mixed => $fixture126()->toArray()['schema_object']['type'],
    'schema object name' => static fn (): mixed => $fixture126()->toArray()['schema_object']['name'],
    'schema table name' => static fn (): mixed => $fixture126()->toArray()['schema_object']['table_name'],
    'schema sql' => static fn (): mixed => $fixture126()->toArray()['schema_object']['sql'],
    'released overflow pages' => static fn (): mixed => $fixture126()->reusePlan->releasedOverflowPages(),
    'root page numbers' => static fn (): mixed => $fixture126()->rootPageNumbers(),
    'reuse plan root pointer type' => static fn (): mixed => array_column($fixture126()->reusePlan->reuseRows, 'reuse_pointer_map_type'),
    'reuse plan root pointer parent' => static fn (): mixed => array_column($fixture126()->reusePlan->reuseRows, 'reuse_pointer_map_parent'),
    'reuse plan keeps second overflow free' => static fn (): mixed => $fixture126()->reusePlan->databaseAfterReuse->pointerMapEntryForPage(7)->typeName(),
    'final root page pointer-map type' => static fn (): mixed => $fixture126()->reusePlan->databaseAfterReuse->pointerMapEntryForPage(6)->typeName(),
    'final root page pointer-map parent' => static fn (): mixed => $fixture126()->reusePlan->databaseAfterReuse->pointerMapEntryForPage(6)->parentPageNumber,
    'final root page type byte' => static fn (): mixed => ord($fixture126()->reusePlan->databaseAfterReuse->page(6)[0]),
    'final root page cell count' => static fn (): mixed => $fixture126()->reusePlan->databaseAfterReuse->pageHeader(6)->cellCount,
    'freelist after one root reuse' => static fn (): mixed => $fixture126()->reusePlan->databaseAfterReuse->freelistPageNumbers(),
    'root row object type' => static fn (): mixed => array_column($rows126($fixture126()), 'object_type'),
    'root row object name' => static fn (): mixed => array_column($rows126($fixture126()), 'object_name'),
    'root row table name' => static fn (): mixed => array_column($rows126($fixture126()), 'table_name'),
    'root row schema rootpage' => static fn (): mixed => array_column($rows126($fixture126()), 'schema_rootpage'),
    'root row release source' => static fn (): mixed => array_column($rows126($fixture126()), 'release_source'),
    'root row allocation source' => static fn (): mixed => array_column($rows126($fixture126()), 'allocation_source'),
    'root row current state' => static fn (): mixed => array_column($rows126($fixture126()), 'current_source_state'),
    'root row free state' => static fn (): mixed => array_column($rows126($fixture126()), 'free_source_state'),
    'root row next state' => static fn (): mixed => array_column($rows126($fixture126()), 'next_source_state'),
    'root row before pointer type' => static fn (): mixed => array_column($rows126($fixture126()), 'before_pointer_map_type'),
    'root row before pointer parent' => static fn (): mixed => array_column($rows126($fixture126()), 'before_pointer_map_parent'),
    'root row free pointer type' => static fn (): mixed => array_column($rows126($fixture126()), 'free_pointer_map_type'),
    'root row free pointer parent' => static fn (): mixed => array_column($rows126($fixture126()), 'free_pointer_map_parent'),
    'root row root pointer type' => static fn (): mixed => array_column($rows126($fixture126()), 'root_pointer_map_type'),
    'root row root pointer parent' => static fn (): mixed => array_column($rows126($fixture126()), 'root_pointer_map_parent'),
    'root row materialized image' => static fn (): mixed => array_column($rows126($fixture126()), 'materialized_with_supplied_image'),
    'root row page type byte' => static fn (): mixed => array_column($rows126($fixture126()), 'root_page_type_byte'),
    'index object accepted' => static fn (): mixed => array_column($rows126($fixture126('index')), 'object_type'),
    'index object name' => static fn (): mixed => array_column($rows126($fixture126('index')), 'object_name'),
    'index sql' => static fn (): mixed => array_column($rows126($fixture126('index')), 'sql'),
    'secure delete records obsolete overflow clear' => static fn (): mixed => $fixture126('table', true)->reusePlan->releasePlan->freePlan->clearedPageNumbers,
    'page images include root and surviving freelist' => static fn (): mixed => array_keys($fixture126()->pageImages()),
    'summary root rows' => static fn (): mixed => array_column($fixture126()->toArray()['btree_overflow_freelist_rootpage_current_source_next126'], 'schema_rootpage'),
    'summary updated pages' => static fn (): mixed => $fixture126()->toArray()['updated_page_numbers'],
    'integrity check reports missing schema rewrite boundary' => static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $fixture126()->reusePlan->databaseAfterReuse)['rows'],
    'bad object type rejected' => static fn () => $throwsMessage126(static fn () => SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan::fromOverflowChains($databaseFixture126(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 'view', 'v', 'v', 'CREATE VIEW v AS SELECT 1', $rootImage126())),
    'empty name rejected' => static fn () => $throwsMessage126(static fn () => SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan::fromOverflowChains($databaseFixture126(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 'table', '', 't', 'CREATE TABLE t(x)', $rootImage126())),
    'empty root image rejected' => static fn () => $throwsMessage126(static fn () => SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan::fromOverflowChains($databaseFixture126(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 'table', 't', 't', 'CREATE TABLE t(x)', [])),
    'non reused root image rejected' => static fn () => $throwsMessage126(static fn () => SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan::fromOverflowChains($databaseFixture126(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 'table', 't', 't', 'CREATE TABLE t(x)', [7 => SQLiteTableLeafPage::assemble([])])),
];

$expected126 = [
    'action label' => 'btree-overflow-freelist-rootpage-current-source-next126',
    'schema object type' => 'table',
    'schema object name' => 'wp_import_stage_next126',
    'schema table name' => 'wp_import_stage_next126',
    'schema sql' => 'CREATE TABLE wp_import_stage_next126 (ID INTEGER PRIMARY KEY, post_id INTEGER, meta_id INTEGER, status TEXT)',
    'released overflow pages' => [6, 7],
    'root page numbers' => [6],
    'reuse plan root pointer type' => ['root-page'],
    'reuse plan root pointer parent' => [0],
    'reuse plan keeps second overflow free' => 'free-page',
    'final root page pointer-map type' => 'root-page',
    'final root page pointer-map parent' => 0,
    'final root page type byte' => 13,
    'final root page cell count' => 1,
    'freelist after one root reuse' => [8, 7],
    'root row object type' => ['table'],
    'root row object name' => ['wp_import_stage_next126'],
    'root row table name' => ['wp_import_stage_next126'],
    'root row schema rootpage' => [6],
    'root row release source' => ['wp-option-delete-rootpage-overflow-chain'],
    'root row allocation source' => ['freelist-leaf'],
    'root row current state' => ['obsolete-overflow-page'],
    'root row free state' => ['freelist-page'],
    'root row next state' => ['schema-rootpage'],
    'root row before pointer type' => ['first-overflow-page'],
    'root row before pointer parent' => [3],
    'root row free pointer type' => ['free-page'],
    'root row free pointer parent' => [0],
    'root row root pointer type' => ['root-page'],
    'root row root pointer parent' => [0],
    'root row materialized image' => [true],
    'root row page type byte' => [13],
    'index object accepted' => ['index'],
    'index object name' => ['wp_import_stage_next126_status'],
    'index sql' => ['CREATE INDEX wp_import_stage_next126_status ON wp_import_stage_next126(status)'],
    'secure delete records obsolete overflow clear' => [6, 7],
    'page images include root and surviving freelist' => [1, 2, 6, 8],
    'summary root rows' => [6],
    'summary updated pages' => [1, 2, 6, 8],
    'integrity check reports missing schema rewrite boundary' => [['integrity_check' => 'pointer-map type root-page for page 6 does not match expected btree-page']],
    'bad object type rejected' => 'SQLite overflow freelist rootpage object type must be table or index',
    'empty name rejected' => 'SQLite overflow freelist rootpage object name must be non-empty',
    'empty root image rejected' => 'SQLite overflow freelist rootpage reuse requires at least one root page image',
    'non reused root image rejected' => 'SQLite allocated page image was not part of the allocation plan',
];

$tests = [];

foreach ($cases126 as $name => $callback) {
    $tests['btree overflow freelist rootpage current source next126 ' . $name] = static function (TestRunner $t) use ($callback, $expected126, $name): void {
        $t->same($expected126[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree overflow freelist rootpage current source next126 invariant ' . $index] = static function (TestRunner $t) use ($fixture126, $rows126, $index): void {
        $objectType = $index % 3 === 0 ? 'index' : 'table';
        $plan = $fixture126($objectType);
        $rows = $rows126($plan);

        $t->same([6, 7], $plan->reusePlan->releasedOverflowPages());
        $t->same([6], $plan->rootPageNumbers());
        $t->same([8, 7], $plan->reusePlan->databaseAfterReuse->freelistPageNumbers());
        $t->same(['first-overflow-page'], array_column($rows, 'before_pointer_map_type'));
        $t->same(['free-page'], array_column($rows, 'free_pointer_map_type'));
        $t->same(['root-page'], array_column($rows, 'root_pointer_map_type'));
        $t->same([0], array_column($rows, 'root_pointer_map_parent'));
        $t->same([$objectType], array_column($rows, 'object_type'));
        $t->same(['schema-rootpage'], array_column($rows, 'next_source_state'));
        $t->same([13], array_column($rows, 'root_page_type_byte'));
    };
}

return $tests;
