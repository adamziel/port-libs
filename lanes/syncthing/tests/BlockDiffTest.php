<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockDiff;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream folder_sendrecv TestDiff table' => static function (TestRunner $t): void {
        $cases = [
            ['contents', 'contents', 1024, []],
            ['', '', 1024, []],
            ['contents', 'contents', 3, []],
            ['contents', 'cantents', 3, [[0, 3]]],
            ['contents', 'contants', 3, [[3, 3]]],
            ['contents', 'cantants', 3, [[0, 3], [3, 3]]],
            ['contents', '', 3, [[0, 0]]],
            ['', 'contents', 3, [[0, 3], [3, 3], [6, 2]]],
            ['con', 'contents', 3, [[3, 3], [6, 2]]],
            ['contents', 'con', 3, []],
            ['contents', 'cont', 3, [[3, 1]]],
            ['cont', 'contents', 3, [[3, 3], [6, 2]]],
        ];

        $blockList = new BlockList();
        foreach ($cases as $index => [$sourceBytes, $targetBytes, $blockSize, $expectedNeed]) {
            $diff = BlockDiff::between(
                $blockList->fromBytes($sourceBytes, $blockSize),
                $blockList->fromBytes($targetBytes, $blockSize),
            );

            $t->same($expectedNeed, syncthing_block_diff_ranges($diff->need), 'Incorrect diff need ranges for case ' . $index);
        }
    },
    'maps upstream folder_sendrecv TestDiffEmpty boundaries' => static function (TestRunner $t): void {
        $cases = [
            [[], [], 0, 0],
            [[new Block(3, 1, hash('sha256', 'source'))], [], 0, 0],
            [[], [new Block(3, 1, hash('sha256', 'target'))], 0, 1],
        ];

        foreach ($cases as [$source, $target, $expectedHave, $expectedNeed]) {
            $diff = BlockDiff::between($source, $target);
            $t->same($expectedHave, count($diff->have));
            $t->same($expectedNeed, count($diff->need));
        }
    },
    'plans WordPress media requests only for changed target blocks' => static function (TestRunner $t): void {
        $blockList = new BlockList();
        $localBlocks = $blockList->fromBytes(
            str_repeat('a', BlockList::MIN_BLOCK_SIZE)
            . str_repeat('b', BlockList::MIN_BLOCK_SIZE)
            . str_repeat('c', BlockList::MIN_BLOCK_SIZE),
            BlockList::MIN_BLOCK_SIZE,
        );
        $targetBlocks = $blockList->fromBytes(
            str_repeat('a', BlockList::MIN_BLOCK_SIZE)
            . str_repeat('d', BlockList::MIN_BLOCK_SIZE)
            . str_repeat('c', BlockList::MIN_BLOCK_SIZE),
            BlockList::MIN_BLOCK_SIZE,
        );
        $diff = BlockDiff::between($localBlocks, $targetBlocks);

        $target = new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            version: VersionVector::fromCounters([202 => 2]),
            size: 3 * BlockList::MIN_BLOCK_SIZE,
            rawBlockSize: BlockList::MIN_BLOCK_SIZE,
            blocks: $targetBlocks,
        );
        $tracker = new RemoteDownloadProgressTracker([
            'wordpress-media' => ['playground-peer'],
        ]);

        $plans = [];
        foreach ($diff->need as $requestOffset => $block) {
            $plans[] = $tracker->planBlockRequest(
                'wordpress-media',
                $target,
                $block,
                completeDeviceIds: ['playground-peer'],
                requestId: 9000 + $requestOffset,
            )?->toArray();
        }

        $t->same([[BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE]], syncthing_block_diff_ranges($diff->need));
        $t->same(2, count($diff->have));
        $t->same([
            [
                'device' => 'playground-peer',
                'folder' => 'wordpress-media',
                'name' => 'wp-content/uploads/2026/hero.jpg',
                'blockNo' => 1,
                'offset' => BlockList::MIN_BLOCK_SIZE,
                'size' => BlockList::MIN_BLOCK_SIZE,
                'hashHex' => $targetBlocks[1]->hashHex,
                'fromTemporary' => false,
            ],
        ], $plans);
    },
    'rejects non-block inputs' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => BlockDiff::between(['not-a-block'], []));
        $t->throws(InvalidArgumentException::class, static fn () => new BlockDiff([new Block(0, 1, hash('sha256', 'ok'))], ['bad']));
    },
];

/**
 * @param list<Block> $blocks
 *
 * @return list<array{0:int, 1:int}>
 */
function syncthing_block_diff_ranges(array $blocks): array
{
    return array_map(static fn (Block $block): array => [$block->offset, $block->size], $blocks);
}
