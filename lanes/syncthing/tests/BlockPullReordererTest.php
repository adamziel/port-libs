<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockPullReorderer;
use PortLibs\Syncthing\DeviceId;

return [
    'maps upstream block chunk table cases' => static function (TestRunner $t): void {
        $blocks = syncthing_block_pull_blocks(1, 2, 3);

        $t->same([[1, 2, 3]], syncthing_block_pull_chunk_offsets($blocks, 1));
        $t->same([[1, 2], [3]], syncthing_block_pull_chunk_offsets($blocks, 2));
        $t->same([[1], [2], [3]], syncthing_block_pull_chunk_offsets($blocks, 3));
        $t->same([[1], [2], [3]], syncthing_block_pull_chunk_offsets($blocks, 4));
        $t->same([[1, 2, 3]], syncthing_block_pull_chunk_offsets($blocks, 0));
        $t->same([], syncthing_block_pull_chunk_offsets([], 1));
        $t->same([[]], syncthing_block_pull_chunk_offsets([], 0));
        $t->throws(InvalidArgumentException::class, static fn () => BlockPullReorderer::chunk($blocks, -1));
    },
    'maps upstream in-order block pull ordering' => static function (TestRunner $t): void {
        $blocks = syncthing_block_pull_blocks(1, 2, 3);
        $ordered = BlockPullReorderer::reorder(
            $blocks,
            BlockPullReorderer::ORDER_IN_ORDER,
            syncthing_block_pull_device(1),
        );

        $t->same([1, 2, 3], syncthing_block_pull_offsets($ordered));
    },
    'maps upstream standard block pull reorderer device chunks' => static function (TestRunner $t): void {
        $first = syncthing_block_pull_device(1);
        $middle = syncthing_block_pull_device(2);
        $last = syncthing_block_pull_device(3);
        $keepOrder = static fn (array $indexes): array => $indexes;

        $front = BlockPullReorderer::standard(
            syncthing_block_pull_blocks(1, 2, 3),
            $first,
            [$middle, $last],
            $keepOrder,
        );
        $back = BlockPullReorderer::standard(
            syncthing_block_pull_blocks(1, 2, 3),
            $last,
            [$first, $middle],
            $keepOrder,
        );
        $fewBlocks = BlockPullReorderer::standard(
            syncthing_block_pull_blocks(1),
            $last,
            [$first, $middle],
            $keepOrder,
        );
        $moreThanOneBlock = BlockPullReorderer::standard(
            syncthing_block_pull_blocks(1, 2, 3, 4),
            $middle,
            [$first],
            $keepOrder,
        );

        $t->same([1, 2, 3], syncthing_block_pull_offsets($front));
        $t->same([3, 1, 2], syncthing_block_pull_offsets($back));
        $t->same([1], syncthing_block_pull_offsets($fewBlocks));
        $t->same([3, 4, 1, 2], syncthing_block_pull_offsets($moreThanOneBlock));
        $t->same([], BlockPullReorderer::standard([], $first, [$middle], $keepOrder));
    },
    'maps random order and invalid shuffle callback guards' => static function (TestRunner $t): void {
        $blocks = syncthing_block_pull_blocks(1, 2, 3);
        $reversed = BlockPullReorderer::random($blocks, static fn (array $indexes): array => array_reverse($indexes));

        $t->same([3, 2, 1], syncthing_block_pull_offsets($reversed));
        $t->same([3, 1, 2], syncthing_block_pull_offsets(BlockPullReorderer::reorder(
            $blocks,
            'unsupported-order',
            syncthing_block_pull_device(3),
            [syncthing_block_pull_device(1), syncthing_block_pull_device(2)],
            static fn (array $indexes): array => $indexes,
        )));
        $t->throws(UnexpectedValueException::class, static fn () => BlockPullReorderer::random(
            $blocks,
            static fn (array $indexes): array => [0, 0, 2],
        ));
    },
];

/**
 * @return list<Block>
 */
function syncthing_block_pull_blocks(int ...$offsets): array
{
    $blocks = [];
    foreach ($offsets as $offset) {
        $blocks[] = new Block($offset, 1, hash('sha256', 'block-' . $offset));
    }

    return $blocks;
}

/**
 * @param list<Block> $blocks
 *
 * @return list<int>
 */
function syncthing_block_pull_offsets(array $blocks): array
{
    return array_map(static fn (Block $block): int => $block->offset, $blocks);
}

/**
 * @param list<Block> $blocks
 *
 * @return list<list<int>>
 */
function syncthing_block_pull_chunk_offsets(array $blocks, int $partCount): array
{
    return array_map(
        static fn (array $chunk): array => syncthing_block_pull_offsets($chunk),
        BlockPullReorderer::chunk($blocks, $partCount),
    );
}

function syncthing_block_pull_device(int $byte): DeviceId
{
    return DeviceId::fromBytes(str_repeat(chr($byte), DeviceId::LENGTH));
}
