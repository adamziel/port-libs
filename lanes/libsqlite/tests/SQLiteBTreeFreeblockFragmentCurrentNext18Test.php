<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;

$pageWithFreeblocks = static function (int $fragmentedBytes, int $secondOffset = 415, int $firstSize = 12): string {
    $pageSize = 512;
    $page = str_repeat("\0", $pageSize);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 400), 1, 2);
    $page = substr_replace($page, pack('n', 0), 3, 2);
    $page = substr_replace($page, pack('n', 380), 5, 2);
    $page[7] = chr($fragmentedBytes);
    $page = substr_replace($page, pack('n', $secondOffset) . pack('n', $firstSize), 400, 4);
    $page = substr_replace($page, pack('n', 430) . pack('n', 10), $secondOffset, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 16), 430, 4);

    return $page;
};

$fixture = static fn (int $fragmentedBytes = 3, int $secondOffset = 415, int $firstSize = 12): array => [
    SQLiteBTreePageHeader::parsePage($pageWithFreeblocks($fragmentedBytes, $secondOffset, $firstSize), 512),
    $pageWithFreeblocks($fragmentedBytes, $secondOffset, $firstSize),
];

$cases = [
    'parses fragmented current-next freeblock chain' => static fn (array $fx): mixed => count($fx[0]->freeblocks($fx[1])),
    'keeps first freeblock offset' => static fn (array $fx): mixed => $fx[0]->freeblocks($fx[1])[0]->offset,
    'keeps first freeblock size' => static fn (array $fx): mixed => $fx[0]->freeblocks($fx[1])[0]->size,
    'keeps first freeblock next offset' => static fn (array $fx): mixed => $fx[0]->freeblocks($fx[1])[0]->nextOffset,
    'keeps second freeblock offset after fragment gap' => static fn (array $fx): mixed => $fx[0]->freeblocks($fx[1])[1]->offset,
    'keeps second freeblock next offset' => static fn (array $fx): mixed => $fx[0]->freeblocks($fx[1])[1]->nextOffset,
    'keeps third freeblock offset' => static fn (array $fx): mixed => $fx[0]->freeblocks($fx[1])[2]->offset,
    'reports freeblock integrity ok' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['status'],
    'reports three freeblocks in integrity' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['freeblock_count'],
    'reports total freeblock bytes' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['freeblock_bytes'],
    'reports free space including fragmented current-next gap' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['free_space_bytes'],
    'free space helper includes fragmented current-next gap' => static fn (array $fx): mixed => $fx[0]->freeSpaceBytes($fx[1]),
    'first freeblock array end offset' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['freeblocks'][0]['end_offset'],
    'second freeblock array offset' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['freeblocks'][1]['offset'],
    'second freeblock array size' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['freeblocks'][1]['size'],
    'third freeblock array has null next offset' => static fn (array $fx): mixed => $fx[0]->freeblockIntegrityReport($fx[1])['freeblocks'][2]['next_offset'],
    'reports header fragmented bytes' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['fragmented_free_bytes'],
    'reports current-next fragment status' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['status'],
    'reports current-next fragment bytes' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['current_next_fragment_bytes'],
    'reports no unaccounted fragments' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['unaccounted_fragment_bytes'],
    'reports one current-next fragment' => static fn (array $fx): mixed => count($fx[0]->freeblockFragmentReport($fx[1])['current_next_fragments']),
    'reports fragment current offset' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['current_next_fragments'][0]['current_offset'],
    'reports fragment current end offset' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['current_next_fragments'][0]['current_end_offset'],
    'reports fragment next offset' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['current_next_fragments'][0]['next_offset'],
    'reports fragment byte width' => static fn (array $fx): mixed => $fx[0]->freeblockFragmentReport($fx[1])['current_next_fragments'][0]['fragment_bytes'],
    'allows adjacent current-next freeblocks' => static fn (): mixed => count($fixture(0, 412)[0]->freeblocks($fixture(0, 412)[1])),
    'adjacent current-next report is ok' => static fn (): mixed => $fixture(0, 412)[0]->freeblockFragmentReport($fixture(0, 412)[1])['status'],
    'adjacent current-next has no fragment rows' => static fn (): mixed => count($fixture(0, 412)[0]->freeblockFragmentReport($fixture(0, 412)[1])['current_next_fragments']),
    'adjacent current-next freeblocks have zero fragment bytes' => static fn (): mixed => $fixture(0, 412)[0]->freeblockFragmentReport($fixture(0, 412)[1])['current_next_fragment_bytes'],
    'allows one-byte current-next fragment' => static fn (): mixed => $fixture(1, 413)[0]->freeblockFragmentReport($fixture(1, 413)[1])['current_next_fragment_bytes'],
    'one-byte current-next fragment row width' => static fn (): mixed => $fixture(1, 413)[0]->freeblockFragmentReport($fixture(1, 413)[1])['current_next_fragments'][0]['fragment_bytes'],
    'one-byte current-next fragment has no unaccounted bytes' => static fn (): mixed => $fixture(1, 413)[0]->freeblockFragmentReport($fixture(1, 413)[1])['unaccounted_fragment_bytes'],
    'allows two-byte current-next fragment' => static fn (): mixed => $fixture(2, 414)[0]->freeblockFragmentReport($fixture(2, 414)[1])['current_next_fragment_bytes'],
    'two-byte current-next fragment row width' => static fn (): mixed => $fixture(2, 414)[0]->freeblockFragmentReport($fixture(2, 414)[1])['current_next_fragments'][0]['fragment_bytes'],
    'two-byte current-next fragment has no unaccounted bytes' => static fn (): mixed => $fixture(2, 414)[0]->freeblockFragmentReport($fixture(2, 414)[1])['unaccounted_fragment_bytes'],
    'allows three-byte current-next fragment' => static fn (): mixed => $fixture(3, 415)[0]->freeblockFragmentReport($fixture(3, 415)[1])['current_next_fragment_bytes'],
    'three-byte current-next fragment row width' => static fn (): mixed => $fixture(3, 415)[0]->freeblockFragmentReport($fixture(3, 415)[1])['current_next_fragments'][0]['fragment_bytes'],
    'reports unaccounted fragment bytes separately' => static fn (): mixed => $fixture(5, 415)[0]->freeblockFragmentReport($fixture(5, 415)[1])['unaccounted_fragment_bytes'],
    'unaccounted fragment report remains ok' => static fn (): mixed => $fixture(5, 415)[0]->freeblockFragmentReport($fixture(5, 415)[1])['status'],
    'detects current-next fragments above header count' => static fn (): mixed => $fixture(2, 415)[0]->freeblockFragmentReport($fixture(2, 415)[1])['status'],
    'surfaces fragment overrun error' => static fn (): mixed => $fixture(2, 415)[0]->freeblockFragmentReport($fixture(2, 415)[1])['error'],
    'still rejects overlapping current-next freeblocks' => static function () use ($fixture): string {
        try {
            $fixture(0, 411)[0]->freeblocks($fixture(0, 411)[1]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'integrity report rejects overlapping current-next freeblocks' => static fn (): mixed => $fixture(0, 411)[0]->freeblockIntegrityReport($fixture(0, 411)[1])['status'],
    'secure-delete report accepts fragmented freeblock chain' => static fn (array $fx): mixed => $fx[0]->freeblockSecureDeleteReport($fx[1])['status'],
    'secure-delete report includes fragmented-chain freeblocks' => static fn (array $fx): mixed => $fx[0]->freeblockSecureDeleteReport($fx[1])['freeblock_count'],
    'secure-delete first payload offset survives fragment parsing' => static fn (array $fx): mixed => $fx[0]->freeblockSecureDeleteReport($fx[1])['freeblocks'][0]['payload_offset'],
    'secure-delete second payload size survives fragment parsing' => static fn (array $fx): mixed => $fx[0]->freeblockSecureDeleteReport($fx[1])['freeblocks'][1]['payload_size'],
    'secure-delete third payload size survives fragment parsing' => static fn (array $fx): mixed => $fx[0]->freeblockSecureDeleteReport($fx[1])['freeblocks'][2]['payload_size'],
];

$expected = [
    'parses fragmented current-next freeblock chain' => 3,
    'keeps first freeblock offset' => 400,
    'keeps first freeblock size' => 12,
    'keeps first freeblock next offset' => 415,
    'keeps second freeblock offset after fragment gap' => 415,
    'keeps second freeblock next offset' => 430,
    'keeps third freeblock offset' => 430,
    'reports freeblock integrity ok' => 'ok',
    'reports three freeblocks in integrity' => 3,
    'reports total freeblock bytes' => 38,
    'reports free space including fragmented current-next gap' => 413,
    'free space helper includes fragmented current-next gap' => 413,
    'first freeblock array end offset' => 412,
    'second freeblock array offset' => 415,
    'second freeblock array size' => 10,
    'third freeblock array has null next offset' => null,
    'reports header fragmented bytes' => 3,
    'reports current-next fragment status' => 'ok',
    'reports current-next fragment bytes' => 3,
    'reports no unaccounted fragments' => 0,
    'reports one current-next fragment' => 1,
    'reports fragment current offset' => 400,
    'reports fragment current end offset' => 412,
    'reports fragment next offset' => 415,
    'reports fragment byte width' => 3,
    'allows adjacent current-next freeblocks' => 3,
    'adjacent current-next report is ok' => 'ok',
    'adjacent current-next has no fragment rows' => 0,
    'adjacent current-next freeblocks have zero fragment bytes' => 0,
    'allows one-byte current-next fragment' => 1,
    'one-byte current-next fragment row width' => 1,
    'one-byte current-next fragment has no unaccounted bytes' => 0,
    'allows two-byte current-next fragment' => 2,
    'two-byte current-next fragment row width' => 2,
    'two-byte current-next fragment has no unaccounted bytes' => 0,
    'allows three-byte current-next fragment' => 3,
    'three-byte current-next fragment row width' => 3,
    'reports unaccounted fragment bytes separately' => 2,
    'unaccounted fragment report remains ok' => 'ok',
    'detects current-next fragments above header count' => 'corrupt',
    'surfaces fragment overrun error' => 'SQLite b-tree current/next freeblock fragments exceed the page fragmented-byte count',
    'still rejects overlapping current-next freeblocks' => 'SQLite b-tree freeblock chain is not in ascending non-overlapping order',
    'integrity report rejects overlapping current-next freeblocks' => 'corrupt',
    'secure-delete report accepts fragmented freeblock chain' => 'ok',
    'secure-delete report includes fragmented-chain freeblocks' => 3,
    'secure-delete first payload offset survives fragment parsing' => 404,
    'secure-delete second payload size survives fragment parsing' => 6,
    'secure-delete third payload size survives fragment parsing' => 12,
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree freeblock current-next18 ' . $name] = static function (TestRunner $t) use ($fixture, $read, $expected, $name): void {
        $t->same($expected[$name], $read($fixture()));
    };
}

return $tests;
