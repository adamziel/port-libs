<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$firstPage83 = static function (int $pageSize, int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 42), 52, 4);

    return $page;
};

$putPointerMap83 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture83 = static function (int $payloadLength = 1190) use ($firstPage83, $putPointerMap83): array {
    $pageSize = 512;
    $pageCount = 211;
    $currentPayload = str_repeat('C', $payloadLength);
    $nextPayload = str_repeat('N', $payloadLength);
    $chainPages = [209, 210, 211];
    $currentPages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $nextPages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $currentPages[1] = $firstPage83($pageSize, $pageCount);
    $nextPages[1] = $firstPage83($pageSize, $pageCount);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMap83($currentPages, $pageNumber, $type, $parent, $pageSize);
        $putPointerMap83($nextPages, $pageNumber, $type, $parent, $pageSize);
    }

    foreach (SQLiteOverflowPage::encodeChainAtPages($currentPayload, $chainPages, $pageSize) as $pageNumber => $pageImage) {
        $currentPages[$pageNumber] = $pageImage;
    }
    foreach (SQLiteOverflowPage::encodeChainAtPages($nextPayload, $chainPages, $pageSize) as $pageNumber => $pageImage) {
        $nextPages[$pageNumber] = $pageImage;
    }

    foreach ($chainPages as $index => $pageNumber) {
        $type = $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE;
        $currentParent = $index === 0 ? 42 : $chainPages[$index - 1];
        $nextParent = $index === 0 ? 44 : $chainPages[$index - 1];
        $putPointerMap83($currentPages, $pageNumber, $type, $currentParent, $pageSize);
        $putPointerMap83($nextPages, $pageNumber, $type, $nextParent, $pageSize);
    }

    $plan = SQLiteBTreeOverflowCurrentSourcePlan::compareCurrentAndNext(
        SQLiteDatabase::fromBytes(implode('', $currentPages)),
        SQLiteDatabase::fromBytes(implode('', $nextPages)),
        209,
        $payloadLength,
        42,
    );

    return [$plan, $currentPayload, $nextPayload];
};

$tests = [];

$cases = [
    'action name' => static fn (array $fx): mixed => $fx[0]->toArray()['action'],
    'first overflow page' => static fn (array $fx): mixed => $fx[0]->firstOverflowPage,
    'payload length' => static fn (array $fx): mixed => $fx[0]->overflowPayloadLength,
    'owning btree page' => static fn (array $fx): mixed => $fx[0]->owningBtreePageNumber,
    'current chain pages' => static fn (array $fx): mixed => array_column($fx[0]->currentChainLinks, 'current_page'),
    'next chain pages' => static fn (array $fx): mixed => array_column($fx[0]->nextChainLinks, 'current_page'),
    'current first next page' => static fn (array $fx): mixed => $fx[0]->currentChainLinks[0]['next_page'],
    'current second next page' => static fn (array $fx): mixed => $fx[0]->currentChainLinks[1]['next_page'],
    'current terminal next page' => static fn (array $fx): mixed => $fx[0]->currentChainLinks[2]['next_page'],
    'current terminal marker' => static fn (array $fx): mixed => $fx[0]->currentChainLinks[2]['terminal'],
    'current payload preserved' => static fn (array $fx): mixed => $fx[0]->currentPayload,
    'next payload differs' => static fn (array $fx): mixed => $fx[0]->nextPayload,
    'next source difference flag' => static fn (array $fx): mixed => $fx[0]->nextSourceDiffers,
    'current pointer map owns chain' => static fn (array $fx): mixed => $fx[0]->currentPointerMapOwnsChain,
    'current pointer map pages' => static fn (array $fx): mixed => array_column($fx[0]->currentPointerMapEntries, 'page_number'),
    'current pointer map page number' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[0]->currentPointerMapEntries, 'pointer_map_page'))),
    'current first pointer type' => static fn (array $fx): mixed => $fx[0]->currentPointerMapEntries[0]['type_name'],
    'current second pointer type' => static fn (array $fx): mixed => $fx[0]->currentPointerMapEntries[1]['type_name'],
    'current third pointer type' => static fn (array $fx): mixed => $fx[0]->currentPointerMapEntries[2]['type_name'],
    'current first parent' => static fn (array $fx): mixed => $fx[0]->currentPointerMapEntries[0]['parent_page_number'],
    'current second parent' => static fn (array $fx): mixed => $fx[0]->currentPointerMapEntries[1]['parent_page_number'],
    'current third parent' => static fn (array $fx): mixed => $fx[0]->currentPointerMapEntries[2]['parent_page_number'],
    'next first parent changed' => static fn (array $fx): mixed => $fx[0]->nextPointerMapEntries[0]['parent_page_number'],
    'summary current chain pages' => static fn (array $fx): mixed => $fx[0]->toArray()['current_chain_pages'],
    'summary next chain pages' => static fn (array $fx): mixed => $fx[0]->toArray()['next_chain_pages'],
    'summary current payload sha1' => static fn (array $fx): mixed => $fx[0]->toArray()['current_payload_sha1'],
    'summary next payload sha1' => static fn (array $fx): mixed => $fx[0]->toArray()['next_payload_sha1'],
    'summary current pointer owner flag' => static fn (array $fx): mixed => $fx[0]->toArray()['current_pointer_map_owns_chain'],
    'summary next source differs flag' => static fn (array $fx): mixed => $fx[0]->toArray()['next_source_differs'],
    'summary current pointer entries count' => static fn (array $fx): mixed => count($fx[0]->toArray()['current_pointer_map_entries']),
    'summary next pointer entries count' => static fn (array $fx): mixed => count($fx[0]->toArray()['next_pointer_map_entries']),
];

$expected = [
    'action name' => 'btree-autovacuum-pointermap-overflow-current-source-next83',
    'first overflow page' => 209,
    'payload length' => 1190,
    'owning btree page' => 42,
    'current chain pages' => [209, 210, 211],
    'next chain pages' => [209, 210, 211],
    'current first next page' => 210,
    'current second next page' => 211,
    'current terminal next page' => 0,
    'current terminal marker' => true,
    'current payload preserved' => str_repeat('C', 1190),
    'next payload differs' => str_repeat('N', 1190),
    'next source difference flag' => true,
    'current pointer map owns chain' => true,
    'current pointer map pages' => [209, 210, 211],
    'current pointer map page number' => [208],
    'current first pointer type' => 'first-overflow-page',
    'current second pointer type' => 'overflow-page',
    'current third pointer type' => 'overflow-page',
    'current first parent' => 42,
    'current second parent' => 209,
    'current third parent' => 210,
    'next first parent changed' => 44,
    'summary current chain pages' => [209, 210, 211],
    'summary next chain pages' => [209, 210, 211],
    'summary current payload sha1' => sha1(str_repeat('C', 1190)),
    'summary next payload sha1' => sha1(str_repeat('N', 1190)),
    'summary current pointer owner flag' => true,
    'summary next source differs flag' => true,
    'summary current pointer entries count' => 3,
    'summary next pointer entries count' => 3,
];

foreach ($cases as $name => $callback) {
    $tests['btree autovacuum pointermap overflow current source next83 ' . $name] = static function (TestRunner $t) use ($fixture83, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture83()));
    };
}

foreach (range(1, 18) as $index) {
    $tests['btree autovacuum pointermap overflow current source next83 varied payload ' . $index] = static function (TestRunner $t) use ($fixture83, $index): void {
        $length = 1025 + $index;
        [$plan, $currentPayload, $nextPayload] = $fixture83($length);

        $t->same($length, $plan->overflowPayloadLength);
        $t->same($currentPayload, $plan->currentPayload);
        $t->same($nextPayload, $plan->nextPayload);
        $t->same(true, $plan->nextSourceDiffers);
        $t->same([209, 210, 211], array_column($plan->currentChainLinks, 'current_page'));
        $t->same([42, 209, 210], array_column($plan->currentPointerMapEntries, 'parent_page_number'));
    };
}

$tests['btree autovacuum pointermap overflow current source next83 rejects stale owner'] = static function (TestRunner $t) use ($firstPage83, $putPointerMap83): void {
    $pageSize = 512;
    $pages = array_fill(1, 211, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage83($pageSize, 211);
    foreach (SQLiteOverflowPage::encodeChainAtPages(str_repeat('x', 1025), [209, 210, 211], $pageSize) as $pageNumber => $pageImage) {
        $pages[$pageNumber] = $pageImage;
    }
    foreach ([209 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 43], 210 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 209], 211 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 210]] as $pageNumber => [$type, $parent]) {
        $putPointerMap83($pages, $pageNumber, $type, $parent, $pageSize);
    }
    $database = SQLiteDatabase::fromBytes(implode('', $pages));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowCurrentSourcePlan::compareCurrentAndNext($database, $database, 209, 1025, 42));
};

return $tests;
