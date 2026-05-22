<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BlockDiff
{
    /**
     * @param list<Block> $have target blocks already present at the same block index
     * @param list<Block> $need target blocks still required to transform source into target
     */
    public function __construct(
        public readonly array $have,
        public readonly array $need,
    ) {
        self::assertBlocks($this->have);
        self::assertBlocks($this->need);
    }

    /**
     * Maps Syncthing's `blockDiff(src, tgt)`: both block lists must come from
     * the same block size, and matching is index-based rather than content
     * search.
     *
     * @param list<Block> $source
     * @param list<Block> $target
     */
    public static function between(array $source, array $target): self
    {
        self::assertBlocks($source);
        self::assertBlocks($target);

        if ($target === []) {
            return new self([], []);
        }

        if ($source === []) {
            return new self([], array_values($target));
        }

        $have = [];
        $need = [];

        foreach ($target as $index => $targetBlock) {
            if ($index >= count($source)) {
                return new self($have, array_merge($need, array_slice($target, $index)));
            }

            if (self::hashBytesEqual($targetBlock, $source[$index])) {
                $have[] = $targetBlock;
            } else {
                $need[] = $targetBlock;
            }
        }

        return new self($have, $need);
    }

    private static function hashBytesEqual(Block $left, Block $right): bool
    {
        $leftBytes = hex2bin($left->hashHex);
        $rightBytes = hex2bin($right->hashHex);
        if ($leftBytes === false || $rightBytes === false) {
            return false;
        }

        return hash_equals($leftBytes, $rightBytes);
    }

    /**
     * @param array<int, mixed> $blocks
     */
    private static function assertBlocks(array $blocks): void
    {
        foreach ($blocks as $block) {
            if (!$block instanceof Block) {
                throw new \InvalidArgumentException('Expected only Block instances');
            }
        }
    }
}
