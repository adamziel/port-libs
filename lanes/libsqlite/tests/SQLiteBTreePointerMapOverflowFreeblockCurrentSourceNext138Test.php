<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage138 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeFragmentedLeaf138 = static function (string $pageType = "\x0d"): string {
    $page = str_repeat("\xdd", 512);
    $page[0] = $pageType;
    $page = substr_replace($page, pack('n', 392), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 376), 5, 2);
    $page[7] = chr(9);
    $page = substr_replace($page, pack('n', 500), 8, 2);
    $page = substr_replace($page, str_repeat('W', 8), 500, 8);
    $page = substr_replace($page, pack('n', 407) . pack('n', 10), 392, 4);
    $page = substr_replace($page, pack('n', 421) . pack('n', 13), 407, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 18), 421, 4);

    return $page;
};

$putPointerMapEntry138 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database138 = static function (string $pageType = "\x0d") use ($makeFirstPage138, $makeFragmentedLeaf138, $putPointerMapEntry138): SQLiteDatabase {
    $pages = array_fill(1, 6, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage138(6);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $makeFragmentedLeaf138($pageType);
    $pages[5] = pack('N', 6) . str_repeat('A', 508);
    $pages[6] = pack('N', 0) . str_repeat('B', 508);

    $putPointerMapEntry138($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry138($pages, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry138($pages, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$currentChains138 = static fn (): array => [[
    'source' => 'wp_options-current-source-large-autoload-value',
    'first_page' => 5,
    'overflow_payload_bytes' => 700,
]];

$deleteResults138 = static fn (): array => [[
    'source' => 'wp_options-delete-large-autoload-value',
    'obsolete_overflow_page_numbers' => [5, 6],
    'rowids' => [13801],
]];

$payload138 = str_repeat('next138-replacement-wp-option-', 27);

$plan138 = static function (
    bool $secureDelete = true,
    bool $clearCoalescedFragments = true,
    string $pageType = "\x0d",
) use ($database138, $currentChains138, $deleteResults138, $payload138): SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan {
    return SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::currentSourceOverflowFreeblockFromDeleteResults(
        $database138($pageType),
        3,
        $currentChains138(),
        $deleteResults138(),
        3,
        $payload138,
        $secureDelete,
        $clearCoalescedFragments,
    );
};

$throwsMessage138 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$currentRows138 = static fn (): array => $plan138()->currentSourceRows();
$transitionRows138 = static fn (): array => $plan138()->transitionRows();
$afterHeader138 = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan138()->databaseAfterAllocation->page(3), 512);

$cases138 = [
    'action label' => static fn (): mixed => $plan138()->toArray()['action'],
    'leaf page number' => static fn (): mixed => $plan138()->toArray()['leaf_page'],
    'leaf page type' => static fn (): mixed => $plan138()->coalescePlan->pageType,
    'fragmented bytes before' => static fn (): mixed => $plan138()->coalescePlan->fragmentedBytesBefore,
    'fragmented bytes after' => static fn (): mixed => $plan138()->coalescePlan->fragmentedBytesAfter,
    'coalesced fragment bytes' => static fn (): mixed => $plan138()->coalescePlan->coalescedFragmentBytes,
    'freeblocks before' => static fn (): mixed => array_column($plan138()->coalescePlan->beforeFreeblocks, 'offset'),
    'freeblock after offset' => static fn (): mixed => $plan138()->coalescePlan->afterFreeblocks[0]['offset'],
    'freeblock after size' => static fn (): mixed => $plan138()->coalescePlan->afterFreeblocks[0]['size'],
    'current source row pages' => static fn (): mixed => array_column($currentRows138(), 'page_number'),
    'current source chain positions' => static fn (): mixed => array_column($currentRows138(), 'chain_position'),
    'current source next pages' => static fn (): mixed => array_column($currentRows138(), 'current_next_page'),
    'current source terminal flags' => static fn (): mixed => array_column($currentRows138(), 'current_terminal'),
    'current source payload bytes' => static fn (): mixed => array_column($currentRows138(), 'current_payload_bytes'),
    'current source pointer types' => static fn (): mixed => array_column($currentRows138(), 'current_pointer_map_type'),
    'current source pointer parents' => static fn (): mixed => array_column($currentRows138(), 'current_pointer_map_parent'),
    'current source names' => static fn (): mixed => array_column($currentRows138(), 'source'),
    'released pages' => static fn (): mixed => $plan138()->releasedOverflowPages(),
    'release source name' => static fn (): mixed => $plan138()->releasePlan->sources[0]['source'],
    'release source pages' => static fn (): mixed => $plan138()->releasePlan->sources[0]['pages'],
    'release freed pages' => static fn (): mixed => $plan138()->releasePlan->freePlan->freedPageNumbers,
    'release leaf pages' => static fn (): mixed => $plan138()->releasePlan->freePlan->leafPageNumbers,
    'release trunk pages' => static fn (): mixed => $plan138()->releasePlan->freePlan->newTrunkPageNumbers,
    'release pointer types' => static fn (): mixed => array_column($plan138()->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'release cleared pages' => static fn (): mixed => $plan138()->releasePlan->freePlan->clearedPageNumbers,
    'after release freelist pages' => static fn (): mixed => $plan138()->databaseAfterRelease->freelistPageNumbers(),
    'after release page five type' => static fn (): mixed => $plan138()->databaseAfterRelease->pointerMapEntryForPage(5)->typeName(),
    'after release page six type' => static fn (): mixed => $plan138()->databaseAfterRelease->pointerMapEntryForPage(6)->typeName(),
    'allocated pages' => static fn (): mixed => $plan138()->allocatedOverflowPages(),
    'reused current source pages' => static fn (): mixed => $plan138()->reusedCurrentSourcePages(),
    'allocation step sources' => static fn (): mixed => array_column($plan138()->allocationPlan->allocationSteps(), 'source'),
    'allocation step trunks' => static fn (): mixed => array_column($plan138()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation pointer types' => static fn (): mixed => array_column($plan138()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan138()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'transition row pages' => static fn (): mixed => array_column($transitionRows138(), 'page_number'),
    'transition current positions' => static fn (): mixed => array_column($transitionRows138(), 'current_chain_position'),
    'transition replacement positions' => static fn (): mixed => array_column($transitionRows138(), 'replacement_chain_position'),
    'transition current next pages' => static fn (): mixed => array_column($transitionRows138(), 'current_next_page'),
    'transition replacement next pages' => static fn (): mixed => array_column($transitionRows138(), 'replacement_next_page'),
    'transition allocation sources' => static fn (): mixed => array_column($transitionRows138(), 'allocation_source'),
    'transition release sources' => static fn (): mixed => array_column($transitionRows138(), 'release_source'),
    'transition free pointer types' => static fn (): mixed => array_column($transitionRows138(), 'free_pointer_map_type'),
    'transition next pointer types' => static fn (): mixed => array_column($transitionRows138(), 'next_pointer_map_type'),
    'transition next pointer parents' => static fn (): mixed => array_column($transitionRows138(), 'next_pointer_map_parent'),
    'transition payload prefixes' => static fn (): mixed => array_column($transitionRows138(), 'payload_prefix'),
    'final page six next pointer' => static fn (): mixed => unpack('N', substr($plan138()->databaseAfterAllocation->page(6), 0, 4))[1],
    'final page five next pointer' => static fn (): mixed => unpack('N', substr($plan138()->databaseAfterAllocation->page(5), 0, 4))[1],
    'final page six pointer parent' => static fn (): mixed => $plan138()->databaseAfterAllocation->pointerMapEntryForPage(6)->parentPageNumber,
    'final page five pointer parent' => static fn (): mixed => $plan138()->databaseAfterAllocation->pointerMapEntryForPage(5)->parentPageNumber,
    'final freelist pages' => static fn (): mixed => $plan138()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan138()->databaseAfterAllocation->header->freelistPageCount,
    'final leaf fragment status' => static fn (): mixed => $afterHeader138()->freeblockFragmentReport($plan138()->databaseAfterAllocation->page(3))['status'],
    'final leaf current next fragments' => static fn (): mixed => $afterHeader138()->freeblockFragmentReport($plan138()->databaseAfterAllocation->page(3))['current_next_fragment_bytes'],
    'final leaf secure delete zeroed' => static fn (): mixed => $afterHeader138()->freeblockSecureDeleteReport($plan138()->databaseAfterAllocation->page(3))['secure_delete_payload_zeroed'],
    'page image keys' => static fn (): mixed => array_keys($plan138()->pageImages()),
    'overflow image keys' => static fn (): mixed => array_keys($plan138()->overflowPageImages()),
    'summary current rows' => static fn (): mixed => array_column($plan138()->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary transition rows' => static fn (): mixed => array_column($plan138()->toArray()['btree_pointermap_overflow_freeblock_current_source_next138'], 'page_number'),
    'summary updated pages' => static fn (): mixed => $plan138()->toArray()['updated_page_numbers'],
    'summary reused current source pages' => static fn (): mixed => $plan138()->toArray()['reused_current_source_pages'],
    'header from page image freelist count' => static fn (): mixed => SQLiteHeader::parse($plan138()->pageImages()[1])->freelistPageCount,
    'index leaf page type accepted' => static fn (): mixed => $plan138(true, true, "\x0a")->coalescePlan->pageType,
    'without secure delete keeps current terminal payload until allocation overwrites first byte' => static fn (): mixed => substr($plan138(false)->databaseAfterRelease->page(6), 4, 1),
    'without clear leaves coalesced fragment bytes' => static fn (): mixed => strpos($plan138(true, false)->databaseAfterAllocation->page(3), str_repeat("\xdd", 4)) !== false,
    'empty payload rejected' => static fn () => $throwsMessage138(static fn () => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::currentSourceOverflowFreeblockFromDeleteResults($database138(), 3, $currentChains138(), $deleteResults138(), 3, '')),
    'bad parent rejected' => static fn () => $throwsMessage138(static fn () => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::currentSourceOverflowFreeblockFromDeleteResults($database138(), 3, $currentChains138(), $deleteResults138(), 1, 'abc')),
    'trailing current chain rejected' => static function () use ($database138, $currentChains138, $deleteResults138, $payload138, $throwsMessage138): mixed {
        $chains = $currentChains138();
        $chains[0]['overflow_payload_bytes'] = 508;

        return $throwsMessage138(static fn () => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::currentSourceOverflowFreeblockFromDeleteResults($database138(), 3, $chains, $deleteResults138(), 3, $payload138));
    },
];

$expected138 = [
    'action label' => 'btree-pointermap-overflow-freeblock-current-source-next138',
    'leaf page number' => 3,
    'leaf page type' => 'table-leaf',
    'fragmented bytes before' => 9,
    'fragmented bytes after' => 8,
    'coalesced fragment bytes' => 1,
    'freeblocks before' => [392, 407, 421],
    'freeblock after offset' => 392,
    'freeblock after size' => 10,
    'current source row pages' => [5, 6],
    'current source chain positions' => [0, 1],
    'current source next pages' => [6, 0],
    'current source terminal flags' => [false, true],
    'current source payload bytes' => [508, 192],
    'current source pointer types' => ['first-overflow-page', 'overflow-page'],
    'current source pointer parents' => [3, 5],
    'current source names' => ['wp_options-current-source-large-autoload-value', 'wp_options-current-source-large-autoload-value'],
    'released pages' => [5, 6],
    'release source name' => 'wp_options-delete-large-autoload-value',
    'release source pages' => [5, 6],
    'release freed pages' => [5, 6],
    'release leaf pages' => [6],
    'release trunk pages' => [5],
    'release pointer types' => ['free-page', 'free-page'],
    'release cleared pages' => [6],
    'after release freelist pages' => [5, 6],
    'after release page five type' => 'free-page',
    'after release page six type' => 'free-page',
    'allocated pages' => [6, 5],
    'reused current source pages' => [6, 5],
    'allocation step sources' => ['freelist-leaf', 'freelist-trunk'],
    'allocation step trunks' => [5, 5],
    'allocation pointer types' => ['first-overflow-page', 'overflow-page'],
    'allocation pointer parents' => [3, 6],
    'transition row pages' => [6, 5],
    'transition current positions' => [1, 0],
    'transition replacement positions' => [0, 1],
    'transition current next pages' => [0, 6],
    'transition replacement next pages' => [5, 0],
    'transition allocation sources' => ['freelist-leaf', 'freelist-trunk'],
    'transition release sources' => ['wp_options-delete-large-autoload-value', 'wp_options-delete-large-autoload-value'],
    'transition free pointer types' => ['free-page', 'free-page'],
    'transition next pointer types' => ['first-overflow-page', 'overflow-page'],
    'transition next pointer parents' => [3, 6],
    'transition payload prefixes' => ['next138-replacem', 'n-next138-replac'],
    'final page six next pointer' => 5,
    'final page five next pointer' => 0,
    'final page six pointer parent' => 3,
    'final page five pointer parent' => 6,
    'final freelist pages' => [],
    'final freelist count' => 0,
    'final leaf fragment status' => 'ok',
    'final leaf current next fragments' => 0,
    'final leaf secure delete zeroed' => true,
    'page image keys' => [1, 2, 3, 5, 6],
    'overflow image keys' => [6, 5],
    'summary current rows' => [5, 6],
    'summary transition rows' => [6, 5],
    'summary updated pages' => [1, 2, 3, 5, 6],
    'summary reused current source pages' => [6, 5],
    'header from page image freelist count' => 0,
    'index leaf page type accepted' => 'index-leaf',
    'without secure delete keeps current terminal payload until allocation overwrites first byte' => 'B',
    'without clear leaves coalesced fragment bytes' => true,
    'empty payload rejected' => 'SQLite b-tree pointer-map overflow freeblock next138 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree pointer-map overflow freeblock next138 parent b-tree page must be at page 2 or later',
    'trailing current chain rejected' => 'SQLite overflow chain has trailing pages beyond the expected payload length',
];

$tests = [];

foreach ($cases138 as $name => $callback) {
    $tests['btree pointermap overflow freeblock current source next138 ' . $name] = static function (TestRunner $t) use ($callback, $expected138, $name): void {
        $t->same($expected138[$name], $callback());
    };
}

foreach (range(1, 22) as $index) {
    $tests['btree pointermap overflow freeblock current source next138 invariant ' . $index] = static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138();

        $t->same([5, 6], array_column($plan->currentSourceRows(), 'page_number'));
        $t->same([6, 0], array_column($plan->currentSourceRows(), 'current_next_page'));
        $t->same([6, 5], $plan->allocatedOverflowPages());
        $t->same([6, 5], $plan->reusedCurrentSourcePages());
        $t->same([0, 6], array_column($plan->transitionRows(), 'current_next_page'));
        $t->same([5, 0], array_column($plan->transitionRows(), 'replacement_next_page'));
        $t->same(['free-page', 'free-page'], array_column($plan->transitionRows(), 'free_pointer_map_type'));
        $t->same(['first-overflow-page', 'overflow-page'], array_column($plan->transitionRows(), 'next_pointer_map_type'));
        $t->same([], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
