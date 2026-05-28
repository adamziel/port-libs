<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage64 = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry64 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    if ($pageNumber === 1) {
        return;
    }

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

$fixture64 = static function (int $maxTruncatedPages = 3) use ($makeFirstPage64, $putPointerMapEntry64): SQLiteOverflowVacuumTruncatePlan {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage64($pageSize, $pageCount, 0, 0);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry64($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 42 : 5) : $releasedPages[$index - 1];
        $putPointerMapEntry64(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [9001],
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_doing_cron', 9001]],
            ],
        ],
        $maxTruncatedPages,
        true,
    );
};

$tests = [];

$transitionCases = [
    'transition page numbers stay in released order' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->pointerMapVacuumTransitions(), 'page_number'),
    'transition statuses split surviving and truncated pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->pointerMapVacuumTransitions(), 'status'),
    'surviving freed pointer map pages helper' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->survivingFreedPointerMapPages(),
    'truncated freed pointer map pages helper' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatedFreedPointerMapPages(),
    'current entries remain free for every released page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->pointerMapVacuumTransitions(), 'current_type_name'),
    'next entries remain free only for survivors' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->pointerMapVacuumTransitions(), 'next_type_name'),
    'truncated entries mark removed pages only' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->pointerMapVacuumTransitions(), 'truncated_type_name'),
    'surviving current parents are cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_map(static fn (array $row): ?int => $row['current']['parent_page_number'] ?? null, array_slice($plan->pointerMapVacuumTransitions(), 0, 2)),
    'surviving next parents are cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_map(static fn (array $row): ?int => $row['next']['parent_page_number'] ?? null, array_slice($plan->pointerMapVacuumTransitions(), 0, 2)),
    'truncated pages have no next entries' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_map(static fn (array $row): mixed => $row['next'], array_slice($plan->pointerMapVacuumTransitions(), 2)),
    'truncated pages retain pointer map page evidence' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_values(array_unique(array_map(static fn (array $row): int => $row['truncated']['pointer_map_page'], array_slice($plan->pointerMapVacuumTransitions(), 2)))),
    'summary exposes transition pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->toArray()['pointer_map_vacuum_transitions'], 'page_number'),
    'summary exposes transition statuses' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->toArray()['pointer_map_vacuum_transitions'], 'status'),
    'summary exposes surviving helper' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->toArray()['surviving_freed_pointer_map_pages'],
    'summary exposes truncated helper' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->toArray()['truncated_freed_pointer_map_pages'],
    'truncate plan keeps original truncated pointer map order' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->truncatePlan->truncatedPointerMapEntries, 'page_number'),
    'current next boundary final page count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalDatabasePageCount(),
    'current next boundary first freelist trunk' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalFirstFreelistTrunkPage(),
    'current next boundary freelist count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalFreelistPageCount(),
    'next database can still read surviving page 306 pointer map' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->nextDatabase->pointerMapEntryForPage(306)->typeName(),
    'next database can still read surviving page 307 pointer map' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->nextDatabase->pointerMapEntryForPage(307)->typeName(),
    'next database rejects truncated page 308 pointer map read' => static function (SQLiteOverflowVacuumTruncatePlan $plan): string {
        try {
            $plan->nextDatabase->pointerMapEntryForPage(308);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    },
    'boundary pointer map entry records final surviving page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatePlan->boundaryPointerMapEntry['page_number'] ?? null,
    'boundary pointer map entry remains free' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatePlan->boundaryPointerMapEntry['type_name'] ?? null,
];

$expected = [
    'transition page numbers stay in released order' => [306, 307, 308, 309, 310],
    'transition statuses split surviving and truncated pages' => ['survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'surviving freed pointer map pages helper' => [306, 307],
    'truncated freed pointer map pages helper' => [308, 309, 310],
    'current entries remain free for every released page' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'next entries remain free only for survivors' => ['free-page', 'free-page', null, null, null],
    'truncated entries mark removed pages only' => [null, null, 'free-page', 'free-page', 'free-page'],
    'surviving current parents are cleared' => [0, 0],
    'surviving next parents are cleared' => [0, 0],
    'truncated pages have no next entries' => [null, null, null],
    'truncated pages retain pointer map page evidence' => [208],
    'summary exposes transition pages' => [306, 307, 308, 309, 310],
    'summary exposes transition statuses' => ['survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'summary exposes surviving helper' => [306, 307],
    'summary exposes truncated helper' => [308, 309, 310],
    'truncate plan keeps original truncated pointer map order' => [310, 309, 308],
    'current next boundary final page count' => 307,
    'current next boundary first freelist trunk' => 306,
    'current next boundary freelist count' => 2,
    'next database can still read surviving page 306 pointer map' => 'free-page',
    'next database can still read surviving page 307 pointer map' => 'free-page',
    'next database rejects truncated page 308 pointer map read' => 'SQLite page 308 is not present in the database image',
    'boundary pointer map entry records final surviving page' => 307,
    'boundary pointer map entry remains free' => 'free-page',
];

foreach ($transitionCases as $name => $callback) {
    $tests['btree pointermap vacuum current next64 ' . $name] = static function (TestRunner $t) use ($fixture64, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture64()));
    };
}

foreach (range(1, 31) as $index) {
    $tests['btree pointermap vacuum current next64 repeated invariant ' . $index] = static function (TestRunner $t) use ($fixture64, $index): void {
        $plan = $fixture64($index % 2 === 0 ? 3 : 4);
        $summary = $plan->toArray();
        $transitionRows = $summary['pointer_map_vacuum_transitions'];
        $survivors = $summary['surviving_freed_pointer_map_pages'];
        $truncated = $summary['truncated_freed_pointer_map_pages'];

        $t->same([306, 307, 308, 309, 310], array_column($transitionRows, 'page_number'));
        $t->same($plan->releasedOverflowPages(), array_merge($survivors, $truncated));
        $t->same(array_fill(0, count($survivors), 'survives-as-free-page'), array_slice(array_column($transitionRows, 'status'), 0, count($survivors)));
        $t->same(array_fill(0, count($truncated), 'truncated-from-database'), array_slice(array_column($transitionRows, 'status'), count($survivors)));
        $t->same($plan->finalDatabasePageCount(), max($survivors));
        $t->same($truncated, $plan->truncatedFreedPointerMapPages());
        $t->same($survivors, $plan->survivingFreedPointerMapPages());
        $t->same($truncated, array_values(array_filter(array_column($transitionRows, 'page_number'), static fn (int $pageNumber): bool => $pageNumber > $plan->finalDatabasePageCount())));
    };
}

$tests['btree pointermap vacuum current next64 full tail truncation has no surviving pointer map rows'] = static function (TestRunner $t) use ($fixture64): void {
    $plan = $fixture64(8);

    $t->same([], $plan->survivingFreedPointerMapPages());
    $t->same([306, 307, 308, 309, 310], $plan->truncatedFreedPointerMapPages());
    $t->same(array_fill(0, 5, 'truncated-from-database'), array_column($plan->pointerMapVacuumTransitions(), 'status'));
    $t->same(305, $plan->finalDatabasePageCount());
    $t->same(null, $plan->truncatePlan->boundaryPointerMapEntry);
};

$tests['btree pointermap vacuum current next64 rejects zero truncation limit'] = static function (TestRunner $t) use ($fixture64): void {
    $t->throws(InvalidArgumentException::class, static function () use ($fixture64): void {
        $fixture64(0);
    });
};

return $tests;
