<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage143 = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry143 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$database143 = static function () use ($makeFirstPage143, $putPointerMapEntry143): SQLiteDatabase {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage143($pageSize, $pageCount);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry143($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
        $putPointerMapEntry143(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan143 = static function (int $maxTruncatedPages = 3, ?string $payload = null) use ($database143): SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan {
    return SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan::fromCurrentSourceDeleteResults(
        $database143(),
        [
            [
                'source' => 'wp-options-autoload-current-source-overflow-next143',
                'first_page' => 306,
                'overflow_payload_bytes' => 1524,
            ],
            [
                'source' => 'wp-options-option-name-current-source-overflow-next143',
                'first_page' => 309,
                'overflow_payload_bytes' => 1016,
            ],
        ],
        [
            [
                'source' => 'wp_options-autoload-delete-tail-overflow-next143',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [14301],
            ],
            [
                'source' => 'wp_options-name-index-delete-tail-overflow-next143',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_timeout_next143', 14301]],
            ],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next143-wp-option-replacement-', 26),
        42,
        true,
    );
};

$throwsMessage143 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases143 = [
    'action label' => static fn (): mixed => $plan143()->toArray()['action'],
    'base action label' => static fn (): mixed => $plan143()->basePlan->toArray()['action'],
    'current source pages' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'page_number'),
    'current source labels' => static fn (): mixed => array_values(array_unique(array_column($plan143()->currentSourceRows(), 'source'))),
    'current chain indexes' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'chain_index'),
    'current chain positions' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'chain_position'),
    'current next pages' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'current_next_page'),
    'current terminal flags' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'current_terminal'),
    'current payload bytes' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'current_payload_bytes'),
    'current pointer types' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'current_pointer_map_type'),
    'current pointer parents' => static fn (): mixed => array_column($plan143()->currentSourceRows(), 'current_pointer_map_parent'),
    'released overflow pages' => static fn (): mixed => $plan143()->toArray()['released_overflow_pages'],
    'truncated current source pages' => static fn (): mixed => $plan143()->toArray()['truncated_current_source_pages'],
    'surviving current source pages' => static fn (): mixed => $plan143()->toArray()['surviving_current_source_pages'],
    'allocated overflow pages' => static fn (): mixed => $plan143()->toArray()['allocated_overflow_pages'],
    'reused current source pages' => static fn (): mixed => $plan143()->reusedCurrentSourcePages(),
    'rejected truncated current source pages' => static fn (): mixed => $plan143()->rejectedTruncatedCurrentSourcePages(),
    'replacement row pages' => static fn (): mixed => array_column($plan143()->replacementRows(), 'page_number'),
    'replacement row positions' => static fn (): mixed => array_column($plan143()->replacementRows(), 'replacement_chain_position'),
    'replacement row current flags' => static fn (): mixed => array_column($plan143()->replacementRows(), 'was_current_source_page'),
    'replacement row current sources' => static fn (): mixed => array_column($plan143()->replacementRows(), 'current_source'),
    'replacement row current positions' => static fn (): mixed => array_column($plan143()->replacementRows(), 'current_chain_position'),
    'replacement row current next pages' => static fn (): mixed => array_column($plan143()->replacementRows(), 'current_next_page'),
    'replacement row vacuum statuses' => static fn (): mixed => array_column($plan143()->replacementRows(), 'vacuum_status'),
    'replacement next pages' => static fn (): mixed => $plan143()->replacementChainNextPages(),
    'replacement pointer types' => static fn (): mixed => array_column($plan143()->replacementRows(), 'replacement_pointer_map_type'),
    'replacement pointer parents' => static fn (): mixed => array_column($plan143()->replacementRows(), 'replacement_pointer_map_parent'),
    'replacement payload prefixes' => static fn (): mixed => array_column($plan143()->replacementRows(), 'payload_prefix'),
    'final page count' => static fn (): mixed => $plan143()->toArray()['final_database_page_count'],
    'final freelist pages' => static fn (): mixed => $plan143()->toArray()['final_freelist_page_numbers'],
    'summary current rows' => static fn (): mixed => array_column($plan143()->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary replacement rows' => static fn (): mixed => array_column($plan143()->toArray()['btree_freelist_vacuum_overflow_current_source_next143'], 'page_number'),
    'summary replacement next pages' => static fn (): mixed => $plan143()->toArray()['replacement_chain_next_pages'],
    'summary updated pages' => static fn (): mixed => $plan143()->toArray()['updated_page_numbers'],
    'base attempted truncated reuses' => static fn (): mixed => $plan143()->basePlan->attemptedTruncatedReuses(),
    'truncated page read rejected' => static function () use ($plan143): string {
        try {
            $plan143()->basePlan->databaseAfterAllocation->page(308);
        } catch (Throwable) {
            return 'omitted';
        }

        return 'present';
    },
];

$expected143 = [
    'action label' => 'btree-freelist-vacuum-overflow-current-source-next143',
    'base action label' => 'btree-freelist-vacuum-pointermap-current-source-next139',
    'current source pages' => [306, 307, 308, 309, 310],
    'current source labels' => ['wp-options-autoload-current-source-overflow-next143', 'wp-options-option-name-current-source-overflow-next143'],
    'current chain indexes' => [0, 0, 0, 1, 1],
    'current chain positions' => [0, 1, 2, 0, 1],
    'current next pages' => [307, 308, 0, 310, 0],
    'current terminal flags' => [false, false, true, false, true],
    'current payload bytes' => [508, 508, 508, 508, 508],
    'current pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'current pointer parents' => [42, 306, 307, 4, 309],
    'released overflow pages' => [306, 307, 308, 309, 310],
    'truncated current source pages' => [308, 309, 310],
    'surviving current source pages' => [306, 307],
    'allocated overflow pages' => [307, 306],
    'reused current source pages' => [307, 306],
    'rejected truncated current source pages' => [308, 309, 310],
    'replacement row pages' => [307, 306],
    'replacement row positions' => [0, 1],
    'replacement row current flags' => [true, true],
    'replacement row current sources' => ['wp-options-autoload-current-source-overflow-next143', 'wp-options-autoload-current-source-overflow-next143'],
    'replacement row current positions' => [1, 0],
    'replacement row current next pages' => [308, 307],
    'replacement row vacuum statuses' => ['survives-as-free-page', 'survives-as-free-page'],
    'replacement next pages' => [306, 0],
    'replacement pointer types' => ['first-overflow-page', 'overflow-page'],
    'replacement pointer parents' => [42, 307],
    'replacement payload prefixes' => ['next143-wp-optio', 't-next143-wp-opt'],
    'final page count' => 307,
    'final freelist pages' => [],
    'summary current rows' => [306, 307, 308, 309, 310],
    'summary replacement rows' => [307, 306],
    'summary replacement next pages' => [306, 0],
    'summary updated pages' => [1, 208, 306, 307],
    'base attempted truncated reuses' => [],
    'truncated page read rejected' => 'omitted',
];

$tests = [];

foreach ($cases143 as $name => $callback) {
    $tests['btree freelist vacuum overflow current source next143 ' . $name] = static function (TestRunner $t) use ($callback, $expected143, $name): void {
        $t->same($expected143[$name], $callback());
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree freelist vacuum overflow current source next143 invariant ' . $index] = static function (TestRunner $t) use ($plan143): void {
        $plan = $plan143();

        $t->same([306, 307, 308, 309, 310], array_column($plan->currentSourceRows(), 'page_number'));
        $t->same([307, 306], array_column($plan->replacementRows(), 'page_number'));
        $t->same([308, 309, 310], $plan->rejectedTruncatedCurrentSourcePages());
        $t->same([], array_values(array_intersect($plan->reusedCurrentSourcePages(), $plan->rejectedTruncatedCurrentSourcePages())));
        $t->same([306, 0], $plan->replacementChainNextPages());
        $t->same('first-overflow-page', $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(307)->typeName());
        $t->same('overflow-page', $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(306)->typeName());
        $t->same(42, $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(307)->parentPageNumber);
        $t->same(307, $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(306)->parentPageNumber);
    };
}

$tests['btree freelist vacuum overflow current source next143 rejects empty current source rows'] = static function (TestRunner $t) use ($plan143): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan::fromBasePlan($plan143()->basePlan, []));
};

$tests['btree freelist vacuum overflow current source next143 rejects malformed current row'] = static function (TestRunner $t) use ($plan143, $throwsMessage143): void {
    $t->same(
        'SQLite b-tree freelist vacuum overflow next143 current-source row page must be an integer',
        $throwsMessage143(static fn () => SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan::fromBasePlan($plan143()->basePlan, [['page_number' => '306']])),
    );
};

$tests['btree freelist vacuum overflow current source next143 rejects short chain length'] = static function (TestRunner $t) use ($database143, $throwsMessage143): void {
    $t->same(
        'SQLite overflow chain has trailing pages beyond the expected payload length',
        $throwsMessage143(static fn () => SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan::fromCurrentSourceDeleteResults(
            $database143(),
            [['source' => 'short', 'first_page' => 306, 'overflow_payload_bytes' => 508]],
            [['obsolete_overflow_page_numbers' => [306]]],
            1,
            str_repeat('replacement', 80),
            42,
            true,
        )),
    );
};

return $tests;
