<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BlockList
{
    /**
     * @return list<Block>
     */
    public function fromBytes(string $bytes, int $blockSize = 131072): array
    {
        if ($blockSize <= 0) {
            throw new \InvalidArgumentException('Block size must be positive');
        }

        $blocks = [];
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $chunk = substr($bytes, $offset, $blockSize);
            $blocks[] = new Block($offset, strlen($chunk), hash('sha256', $chunk));
            $offset += strlen($chunk);
        }

        return $blocks;
    }

    /**
     * @param list<Block> $expected
     */
    public function verify(string $bytes, array $expected): bool
    {
        foreach ($expected as $block) {
            $chunk = substr($bytes, $block->offset, $block->size);
            if (strlen($chunk) !== $block->size || hash('sha256', $chunk) !== $block->hashHex) {
                return false;
            }
        }

        return true;
    }
}

