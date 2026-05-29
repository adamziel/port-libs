<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage175 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 0), 32, 4);
    $page = substr_replace($page, pack('N', 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry175 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$overflowPage175 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database175 = static function () use ($makeFirstPage175, $putPointerMapEntry175, $overflowPage175): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage175(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next175', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage175(107, 'A');
    $pages[107] = $overflowPage175(108, 'B');
    $pages[108] = $overflowPage175(109, 'C');
    $pages[109] = $overflowPage175(110, 'D');
    $pages[110] = $overflowPage175(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry175($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan175 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database175): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database175();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext175(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next175-current-source-admission-fence-', 40),
        3,
        true,
    );
};

$message175 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases175 = [
    'action label' => static fn (): mixed => $plan175()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan175()->admissionSummary()['status'],
    'admission errors' => static fn (): mixed => $plan175()->admissionErrors(),
    'admitted pages' => static fn (): mixed => $plan175()->admittedCurrentSourcePages(),
    'rejected pages' => static fn (): mixed => $plan175()->rejectedCurrentSourcePages(),
    'pointer map rewrite pages' => static fn (): mixed => $plan175()->pointerMapRewritePages(),
    'stale next fence pages' => static fn (): mixed => $plan175()->staleNextPointerFencePages(),
    'base action label' => static fn (): mixed => $plan175()->basePlan->toArray()['action'],
    'base rewritten pages' => static fn (): mixed => $plan175()->basePlan->rewrittenCurrentSourceNextPages(),
    'base truncated pages' => static fn (): mixed => $plan175()->basePlan->truncatedTailPages(),
    'row page numbers' => static fn (): mixed => array_column($plan175()->admissionRows(), 'page_number'),
    'row statuses' => static fn (): mixed => array_column($plan175()->admissionRows(), 'status'),
    'row admissions' => static fn (): mixed => array_column($plan175()->admissionRows(), 'admission'),
    'source next pages' => static fn (): mixed => array_column($plan175()->admissionRows(), 'source_next_page'),
    'final next pages' => static fn (): mixed => array_column($plan175()->admissionRows(), 'final_next_page'),
    'next pointer rewritten flags' => static fn (): mixed => array_column($plan175()->admissionRows(), 'next_pointer_rewritten'),
    'tail pointer flags' => static fn (): mixed => array_column($plan175()->admissionRows(), 'final_next_points_at_rejected_tail'),
    'pointer rewrite flags' => static fn (): mixed => array_column($plan175()->admissionRows(), 'pointer_map_rewrite_required'),
    'stable leaf hash flags' => static fn (): mixed => array_column($plan175()->admissionRows(), 'stable_leaf_hash_preserved'),
    'stable leaf freeblock flags' => static fn (): mixed => array_column($plan175()->admissionRows(), 'stable_leaf_freeblocks_preserved'),
    'summary admitted pages' => static fn (): mixed => $plan175()->admissionSummary()['admitted_current_source_pages'],
    'summary rejected pages' => static fn (): mixed => $plan175()->admissionSummary()['rejected_current_source_pages'],
    'summary rewrite pages' => static fn (): mixed => $plan175()->admissionSummary()['pointer_map_rewrite_pages'],
    'summary fence pages' => static fn (): mixed => $plan175()->admissionSummary()['stale_next_pointer_fence_pages'],
    'summary dependencies' => static fn (): mixed => $plan175()->admissionSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan175()->admissionSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan175()->admissionSummary()['non_overlap'], 'does not repeat next173 transition rows'),
    'wide admitted pages' => static fn (): mixed => $plan175(null, 6)->admittedCurrentSourcePages(),
    'wide rejected pages' => static fn (): mixed => $plan175(null, 6)->rejectedCurrentSourcePages(),
    'wide fence pages' => static fn (): mixed => $plan175(null, 6)->staleNextPointerFencePages(),
    'wide errors' => static fn (): mixed => $plan175(null, 6)->admissionErrors(),
    'too small replacement rejected' => static fn (): mixed => $message175(static fn () => $plan175(str_repeat('small', 20))),
    'empty replacement rejected' => static fn (): mixed => $message175(static fn () => $plan175('')),
];

$expected175 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next175',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next175-ready',
    'admission errors' => [],
    'admitted pages' => [3, 106, 107, 108, 109],
    'rejected pages' => [110],
    'pointer map rewrite pages' => [110],
    'stale next fence pages' => [109, 110],
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next173',
    'base rewritten pages' => [109, 110],
    'base truncated pages' => [110],
    'row page numbers' => [3, 106, 107, 108, 109, 110],
    'row statuses' => ['stable-leaf-freeblock', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail-page'],
    'row admissions' => ['admit-final-page', 'admit-final-page', 'admit-final-page', 'admit-final-page', 'admit-final-page', 'reject-truncated-current-source-page'],
    'source next pages' => [null, 107, 108, 109, 110, 0],
    'final next pages' => [null, 107, 108, 109, 0, null],
    'next pointer rewritten flags' => [false, false, false, false, true, true],
    'tail pointer flags' => [false, false, false, false, false, false],
    'pointer rewrite flags' => [false, false, false, false, false, true],
    'stable leaf hash flags' => [true, null, null, null, null, null],
    'stable leaf freeblock flags' => [true, null, null, null, null, null],
    'summary admitted pages' => [3, 106, 107, 108, 109],
    'summary rejected pages' => [110],
    'summary rewrite pages' => [110],
    'summary fence pages' => [109, 110],
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next173', 'sqlite-current-source-next175'],
    'dependency closure' => 'no new support component needed; next175 reuses native current-source transition rows, b-tree freeblock page images, overflow next-pointer decoding, and auto-vacuum pointer-map metadata',
    'non overlap' => true,
    'wide admitted pages' => [3, 106, 107, 108, 109],
    'wide rejected pages' => [110],
    'wide fence pages' => [109, 110],
    'wide errors' => [],
    'too small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases175 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next175 ' . $name] = static function (TestRunner $t) use ($callback, $expected175, $name): void {
        $t->same($expected175[$name], $callback());
    };
}

foreach (range(1, 50) as $index) {
    $tests['btree vacuum pointermap freeblock current source next175 admission invariant ' . $index] = static function (TestRunner $t) use ($plan175): void {
        $plan = $plan175();
        $rows = $plan->admissionRows();

        $t->same([], $plan->admissionErrors());
        $t->same([3, 106, 107, 108, 109], $plan->admittedCurrentSourcePages());
        $t->same([110], $plan->rejectedCurrentSourcePages());
        $t->same([109, 110], $plan->staleNextPointerFencePages());
        $t->same('reject-truncated-current-source-page', $rows[5]['admission']);
        $t->same(false, $rows[4]['final_next_points_at_rejected_tail']);
        $t->same(true, $rows[4]['stale_next_pointer_fenced']);
        $t->same(true, $rows[5]['pointer_map_rewrite_required']);
    };
}

return $tests;
