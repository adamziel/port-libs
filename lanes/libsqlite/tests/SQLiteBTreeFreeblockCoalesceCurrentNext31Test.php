<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockCoalescePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;

$pageWithCurrentNextFragments = static function (
    int $fragmentedBytes = 6,
    int $secondOffset = 413,
    int $thirdOffset = 428,
    int $firstSize = 12,
    int $secondSize = 12,
    int $thirdSize = 16,
    string $pageType = "\x0d",
): string {
    $pageSize = 512;
    $page = str_repeat("\xff", $pageSize);
    $page[0] = $pageType;
    $page = substr_replace($page, pack('n', 400), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 384), 5, 2);
    $page[7] = chr($fragmentedBytes);
    $page = substr_replace($page, pack('n', 500), 8, 2);
    $page = substr_replace($page, str_repeat('A', 8), 500, 8);
    $page = substr_replace($page, pack('n', $secondOffset) . pack('n', $firstSize), 400, 4);
    $page = substr_replace($page, pack('n', $thirdOffset) . pack('n', $secondSize), $secondOffset, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', $thirdSize), $thirdOffset, 4);

    return $page;
};

$fixture = static fn (): SQLiteBTreeFreeblockCoalescePlan => SQLiteBTreeFreeblockCoalescePlan::fromPage(
    7,
    $pageWithCurrentNextFragments(),
);

$coalescedHeader = static fn (SQLiteBTreeFreeblockCoalescePlan $plan): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan->pageImage, 512);

$cases = [
    'returns coalesce plan class' => static fn (): mixed => get_class($fixture()),
    'reports bounded action' => static fn (): mixed => $fixture()->toArray()['action'],
    'keeps page number' => static fn (): mixed => $fixture()->pageNumber,
    'keeps table leaf page type' => static fn (): mixed => $fixture()->pageType,
    'keeps fragmented bytes before' => static fn (): mixed => $fixture()->fragmentedBytesBefore,
    'reduces fragmented bytes after' => static fn (): mixed => $fixture()->fragmentedBytesAfter,
    'reports total coalesced fragment bytes' => static fn (): mixed => $fixture()->coalescedFragmentBytes,
    'reports two current next fragment gaps' => static fn (): mixed => count($fixture()->coalescedFragments),
    'first gap starts at first block end' => static fn (): mixed => $fixture()->coalescedFragments[0]['current_end_offset'],
    'first gap points to next block' => static fn (): mixed => $fixture()->coalescedFragments[0]['next_offset'],
    'first gap byte count' => static fn (): mixed => $fixture()->coalescedFragments[0]['fragment_bytes'],
    'second gap starts at merged block end' => static fn (): mixed => $fixture()->coalescedFragments[1]['current_end_offset'],
    'second gap points to third block' => static fn (): mixed => $fixture()->coalescedFragments[1]['next_offset'],
    'second gap byte count' => static fn (): mixed => $fixture()->coalescedFragments[1]['fragment_bytes'],
    'before has three freeblocks' => static fn (): mixed => count($fixture()->beforeFreeblocks),
    'after has one freeblock' => static fn (): mixed => count($fixture()->afterFreeblocks),
    'before first offset' => static fn (): mixed => $fixture()->beforeFreeblocks[0]['offset'],
    'before first end offset' => static fn (): mixed => $fixture()->beforeFreeblocks[0]['end_offset'],
    'before second offset' => static fn (): mixed => $fixture()->beforeFreeblocks[1]['offset'],
    'before second end offset' => static fn (): mixed => $fixture()->beforeFreeblocks[1]['end_offset'],
    'before third offset' => static fn (): mixed => $fixture()->beforeFreeblocks[2]['offset'],
    'before third end offset' => static fn (): mixed => $fixture()->beforeFreeblocks[2]['end_offset'],
    'after first offset' => static fn (): mixed => $fixture()->afterFreeblocks[0]['offset'],
    'after first size absorbs gaps' => static fn (): mixed => $fixture()->afterFreeblocks[0]['size'],
    'after first end offset reaches third end' => static fn (): mixed => $fixture()->afterFreeblocks[0]['end_offset'],
    'after first next offset is null' => static fn (): mixed => $fixture()->afterFreeblocks[0]['next_offset'],
    'toArray freeblock count before' => static fn (): mixed => $fixture()->toArray()['freeblock_count_before'],
    'toArray freeblock count after' => static fn (): mixed => $fixture()->toArray()['freeblock_count_after'],
    'toArray coalesced bytes' => static fn (): mixed => $fixture()->toArray()['coalesced_fragment_bytes'],
    'toArray updated page number' => static fn (): mixed => $fixture()->toArray()['updated_page_numbers'],
    'pageImages carries rewritten page' => static fn (): mixed => array_keys($fixture()->pageImages()),
    'pageImages page matches plan image' => static fn (): mixed => $fixture()->pageImages()[7] === $fixture()->pageImage,
    'rewritten header fragmented byte count' => static fn (): mixed => $coalescedHeader($fixture())->fragmentedFreeBytes,
    'rewritten header first freeblock offset' => static fn (): mixed => $coalescedHeader($fixture())->firstFreeblockOffset,
    'rewritten header keeps cell count' => static fn (): mixed => $coalescedHeader($fixture())->cellCount,
    'rewritten header keeps cell content start' => static fn (): mixed => $coalescedHeader($fixture())->cellContentAreaStart,
    'rewritten freeblocks parse as one' => static fn (): mixed => count($coalescedHeader($fixture())->freeblocks($fixture()->pageImage)),
    'rewritten freeblock size parses' => static fn (): mixed => $coalescedHeader($fixture())->freeblocks($fixture()->pageImage)[0]->size,
    'rewritten freeblock next is null' => static fn (): mixed => $coalescedHeader($fixture())->freeblocks($fixture()->pageImage)[0]->nextOffset,
    'rewritten fragment report has no current next gaps' => static fn (): mixed => $coalescedHeader($fixture())->freeblockFragmentReport($fixture()->pageImage)['current_next_fragment_bytes'],
    'rewritten integrity remains ok' => static fn (): mixed => $coalescedHeader($fixture())->freeblockIntegrityReport($fixture()->pageImage)['status'],
    'rewritten free space preserved' => static fn (): mixed => $coalescedHeader($fixture())->freeSpaceBytes($fixture()->pageImage),
    'clear option zeros merged freeblock payload' => static fn (): mixed => SQLiteBTreePageHeader::parsePage(SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(), clearCoalescedFragments: true)->pageImage, 512)->freeblockSecureDeleteReport(SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(), clearCoalescedFragments: true)->pageImage)['secure_delete_payload_zeroed'],
    'index leaf page type is accepted' => static fn (): mixed => SQLiteBTreeFreeblockCoalescePlan::fromPage(8, $pageWithCurrentNextFragments(pageType: "\x0a"))->pageType,
    'index leaf coalesces to one block' => static fn (): mixed => count(SQLiteBTreeFreeblockCoalescePlan::fromPage(8, $pageWithCurrentNextFragments(pageType: "\x0a"))->afterFreeblocks),
    'one byte fragment coalesces' => static fn (): mixed => SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(1, 413, 425))->coalescedFragmentBytes,
    'two byte fragment coalesces' => static fn (): mixed => SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(2, 414, 426))->coalescedFragmentBytes,
    'three byte fragment coalesces' => static fn (): mixed => SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(3, 415, 427))->coalescedFragmentBytes,
    'unrelated header fragments remain after coalesce' => static fn (): mixed => SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(9))->fragmentedBytesAfter,
    'throws on page zero' => static function () use ($pageWithCurrentNextFragments): string {
        try {
            SQLiteBTreeFreeblockCoalescePlan::fromPage(0, $pageWithCurrentNextFragments());
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'throws on complete page mismatch' => static function () use ($pageWithCurrentNextFragments): string {
        try {
            SQLiteBTreeFreeblockCoalescePlan::fromPage(7, substr($pageWithCurrentNextFragments(), 1));
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'throws on single freeblock page' => static function () use ($pageWithCurrentNextFragments): string {
        $page = $pageWithCurrentNextFragments();
        $page = substr_replace($page, pack('n', 0) . pack('n', 12), 400, 4);
        try {
            SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $page);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'throws when no current next fragment exists' => static function () use ($pageWithCurrentNextFragments): string {
        try {
            SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(0, 412, 424));
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'throws when fragments exceed header count' => static function () use ($pageWithCurrentNextFragments): string {
        try {
            SQLiteBTreeFreeblockCoalescePlan::fromPage(7, $pageWithCurrentNextFragments(2));
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
];

$expected = [
    'returns coalesce plan class' => SQLiteBTreeFreeblockCoalescePlan::class,
    'reports bounded action' => 'btree-freeblock-coalesce-current-next',
    'keeps page number' => 7,
    'keeps table leaf page type' => 'table-leaf',
    'keeps fragmented bytes before' => 6,
    'reduces fragmented bytes after' => 2,
    'reports total coalesced fragment bytes' => 4,
    'reports two current next fragment gaps' => 2,
    'first gap starts at first block end' => 412,
    'first gap points to next block' => 413,
    'first gap byte count' => 1,
    'second gap starts at merged block end' => 425,
    'second gap points to third block' => 428,
    'second gap byte count' => 3,
    'before has three freeblocks' => 3,
    'after has one freeblock' => 1,
    'before first offset' => 400,
    'before first end offset' => 412,
    'before second offset' => 413,
    'before second end offset' => 425,
    'before third offset' => 428,
    'before third end offset' => 444,
    'after first offset' => 400,
    'after first size absorbs gaps' => 44,
    'after first end offset reaches third end' => 444,
    'after first next offset is null' => null,
    'toArray freeblock count before' => 3,
    'toArray freeblock count after' => 1,
    'toArray coalesced bytes' => 4,
    'toArray updated page number' => [7],
    'pageImages carries rewritten page' => [7],
    'pageImages page matches plan image' => true,
    'rewritten header fragmented byte count' => 2,
    'rewritten header first freeblock offset' => 400,
    'rewritten header keeps cell count' => 1,
    'rewritten header keeps cell content start' => 384,
    'rewritten freeblocks parse as one' => 1,
    'rewritten freeblock size parses' => 44,
    'rewritten freeblock next is null' => null,
    'rewritten fragment report has no current next gaps' => 0,
    'rewritten integrity remains ok' => 'ok',
    'rewritten free space preserved' => 420,
    'clear option zeros merged freeblock payload' => true,
    'index leaf page type is accepted' => 'index-leaf',
    'index leaf coalesces to one block' => 1,
    'one byte fragment coalesces' => 1,
    'two byte fragment coalesces' => 2,
    'three byte fragment coalesces' => 3,
    'unrelated header fragments remain after coalesce' => 5,
    'throws on page zero' => 'SQLite freeblock coalesce page number must be positive',
    'throws on complete page mismatch' => 'SQLite freeblock coalesce requires a complete page image',
    'throws on single freeblock page' => 'SQLite freeblock coalesce requires at least two freeblocks',
    'throws when no current next fragment exists' => 'SQLite freeblock coalesce found no current/next fragments',
    'throws when fragments exceed header count' => 'SQLite freeblock coalesce fragments exceed header fragmented-byte count',
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree freeblock coalesce current next31 ' . $name] = static function (TestRunner $t) use ($read, $expected, $name): void {
        $t->same($expected[$name], $read());
    };
}

return $tests;
