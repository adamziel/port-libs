<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BlockList
{
    public const MIN_BLOCK_SIZE = 128 << 10;
    public const MAX_BLOCK_SIZE = 16 << 20;
    public const DESIRED_PER_FILE_BLOCKS = 2000;
    public const EMPTY_FILE_HASH_HEX = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /**
     * @return list<Block>
     */
    public function fromBytes(string $bytes, ?int $blockSize = null): array
    {
        $blockSize ??= self::blockSizeForFileSize(strlen($bytes));

        if ($blockSize <= 0) {
            throw new \InvalidArgumentException('Block size must be positive');
        }

        $blocks = [];
        $offset = 0;
        $length = strlen($bytes);

        if ($length === 0) {
            return [new Block(0, 0, self::EMPTY_FILE_HASH_HEX)];
        }

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
        $nextOffset = 0;
        foreach ($expected as $block) {
            if (!$block instanceof Block || $block->offset !== $nextOffset) {
                return false;
            }

            $chunk = substr($bytes, $block->offset, $block->size);
            if (strlen($chunk) !== $block->size || hash('sha256', $chunk) !== strtolower($block->hashHex)) {
                return false;
            }

            $nextOffset += $block->size;
        }

        return $nextOffset === strlen($bytes);
    }

    public static function blockSizeForFileSize(int $fileSize, ?int $currentBlockSize = null): int
    {
        if ($fileSize < 0) {
            throw new \InvalidArgumentException('File size must not be negative');
        }
        if ($currentBlockSize !== null && $currentBlockSize < 0) {
            throw new \InvalidArgumentException('Current block size must not be negative');
        }

        $selected = self::MIN_BLOCK_SIZE;
        for ($blockSize = self::MIN_BLOCK_SIZE; $blockSize <= self::MAX_BLOCK_SIZE; $blockSize *= 2) {
            $selected = $blockSize;
            if ($fileSize < self::DESIRED_PER_FILE_BLOCKS * $blockSize) {
                break;
            }
        }

        if ($currentBlockSize !== null) {
            $currentBlockSize = max($currentBlockSize, self::MIN_BLOCK_SIZE);
            if ($selected > $currentBlockSize && intdiv($selected, $currentBlockSize) <= 2) {
                return $currentBlockSize;
            }
            if ($currentBlockSize > $selected && intdiv($currentBlockSize, $selected) <= 2) {
                return $currentBlockSize;
            }
        }

        return $selected;
    }

    /**
     * @param list<Block> $blocks
     */
    public function hashBlocks(array $blocks): string
    {
        $hashBytes = '';
        foreach ($blocks as $block) {
            if (!$block instanceof Block) {
                throw new \InvalidArgumentException('Expected only Block instances');
            }

            $decoded = hex2bin($block->hashHex);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Block hash must be hexadecimal');
            }

            $hashBytes .= $decoded;
        }

        return hash('sha256', $hashBytes);
    }

    public function validateBytes(string $bytes, string $hashHex): bool
    {
        if ($hashHex === '') {
            return true;
        }

        return hash_equals(strtolower($hashHex), hash('sha256', $bytes));
    }
}
