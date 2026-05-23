<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullWorkPlan
{
    /**
     * @param list<Block> $sameIndexHave
     * @param list<Block> $remainingBlocks
     * @param list<Block> $reusedBlocks
     * @param list<int> $reusedBlockIndexes
     * @param list<Block> $sparseSkippedBlocks
     * @param list<Block> $copyFromOriginBlocks
     * @param list<Block> $copyFromElsewhereBlocks
     * @param list<Block> $pullBlocks
     * @param list<Block> $localAvailableBlocks
     */
    public function __construct(
        public readonly FileInfo $file,
        public readonly array $sameIndexHave,
        public readonly array $remainingBlocks,
        public readonly array $reusedBlocks,
        public readonly array $reusedBlockIndexes,
        public readonly array $sparseSkippedBlocks,
        public readonly array $copyFromOriginBlocks,
        public readonly array $copyFromElsewhereBlocks,
        public readonly array $pullBlocks,
        public readonly array $localAvailableBlocks,
    ) {
        foreach ([
            $this->sameIndexHave,
            $this->remainingBlocks,
            $this->reusedBlocks,
            $this->sparseSkippedBlocks,
            $this->copyFromOriginBlocks,
            $this->copyFromElsewhereBlocks,
            $this->pullBlocks,
            $this->localAvailableBlocks,
        ] as $blocks) {
            self::assertBlocks($blocks);
        }
        foreach ($this->reusedBlockIndexes as $index) {
            if (!is_int($index) || $index < 0) {
                throw new \InvalidArgumentException('Reused block indexes must be non-negative integers');
            }
        }
    }

    /**
     * Maps the planning part of Syncthing's `handleFile` and `copierRoutine`:
     * target blocks already present in the temp file are reused first; the
     * remaining target blocks are skipped when sparse zero blocks need no
     * write, copied from local data when possible, or left for network pulls.
     *
     * @param list<Block> $temporaryBlocks blocks scanned from `.syncthing.<name>.tmp`
     * @param list<Block> $localBlocks blocks known from other local indexed files
     */
    public static function forFile(
        FileInfo $target,
        ?FileInfo $current = null,
        array $temporaryBlocks = [],
        array $localBlocks = [],
        bool $receiveEncrypted = false,
        bool $sparseFiles = true,
    ): self {
        self::assertBlocks($temporaryBlocks);
        self::assertBlocks($localBlocks);

        $currentBlocks = $current?->blocks ?? [];
        $sameIndexHave = BlockDiff::between($currentBlocks, $target->blocks)->have;

        $remainingBlocks = [];
        $reusedBlocks = [];
        $reusedIndexes = [];
        $tempReusableKeys = [];

        if (!$receiveEncrypted && $temporaryBlocks !== []) {
            foreach (BlockDiff::between($temporaryBlocks, $target->blocks)->have as $block) {
                $tempReusableKeys[self::blockIdentity($block)] = true;
            }
        }

        foreach ($target->blocks as $index => $block) {
            if (isset($tempReusableKeys[self::blockIdentity($block)])) {
                $reusedBlocks[] = $block;
                $reusedIndexes[] = $index;
                continue;
            }

            $remainingBlocks[] = $block;
        }

        $currentHashes = self::hashSet($currentBlocks);
        $localHashes = self::hashSet($localBlocks);
        $sparseSkipped = [];
        $copyFromOrigin = [];
        $copyFromElsewhere = [];
        $pull = [];
        $localAvailable = [];

        foreach ($remainingBlocks as $block) {
            $hash = self::hashIdentity($block);
            if ($sparseFiles && $reusedIndexes === [] && $block->isAllZeroes()) {
                $sparseSkipped[] = $block;
                $localAvailable[] = $block;
                continue;
            }

            if (isset($currentHashes[$hash])) {
                $copyFromOrigin[] = $block;
                $localAvailable[] = $block;
                continue;
            }

            if (isset($localHashes[$hash])) {
                $copyFromElsewhere[] = $block;
                $localAvailable[] = $block;
                continue;
            }

            $pull[] = $block;
        }

        return new self(
            file: $target,
            sameIndexHave: $sameIndexHave,
            remainingBlocks: $remainingBlocks,
            reusedBlocks: $reusedBlocks,
            reusedBlockIndexes: $reusedIndexes,
            sparseSkippedBlocks: $sparseSkipped,
            copyFromOriginBlocks: $copyFromOrigin,
            copyFromElsewhereBlocks: $copyFromElsewhere,
            pullBlocks: $pull,
            localAvailableBlocks: $localAvailable,
        );
    }

    public function progressAfterLocalWork(): PullerProgress
    {
        $originLike = count($this->copyFromOriginBlocks) + count($this->sparseSkippedBlocks);

        return PullerProgress::fromCounters(
            file: $this->file,
            reused: count($this->reusedBlocks),
            copyTotal: count($this->sparseSkippedBlocks) + count($this->copyFromOriginBlocks) + count($this->copyFromElsewhereBlocks),
            pullTotal: count($this->pullBlocks),
            copyNeeded: 0,
            pullNeeded: count($this->pullBlocks),
            copiedFromOrigin: $originLike,
        );
    }

    /**
     * @return list<int>
     */
    public function availableBlockIndexesAfterLocalWork(): array
    {
        $indexes = $this->reusedBlockIndexes;
        foreach ($this->localAvailableBlocks as $block) {
            $indexes[] = intdiv($block->offset, $this->file->blockSize());
        }

        return $indexes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sameIndexHave' => self::ranges($this->sameIndexHave),
            'reusedBlockIndexes' => $this->reusedBlockIndexes,
            'remaining' => self::ranges($this->remainingBlocks),
            'sparseSkipped' => self::ranges($this->sparseSkippedBlocks),
            'copyFromOrigin' => self::ranges($this->copyFromOriginBlocks),
            'copyFromElsewhere' => self::ranges($this->copyFromElsewhereBlocks),
            'pull' => self::ranges($this->pullBlocks),
            'availableBlockIndexesAfterLocalWork' => $this->availableBlockIndexesAfterLocalWork(),
            'progressAfterLocalWork' => $this->progressAfterLocalWork()->toArray(),
        ];
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

    /**
     * @param list<Block> $blocks
     *
     * @return array<string, true>
     */
    private static function hashSet(array $blocks): array
    {
        $set = [];
        foreach ($blocks as $block) {
            $set[self::hashIdentity($block)] = true;
        }

        return $set;
    }

    private static function blockIdentity(Block $block): string
    {
        return $block->offset . '/' . $block->size . '/' . self::hashIdentity($block);
    }

    private static function hashIdentity(Block $block): string
    {
        return strtolower($block->hashHex);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<array{0:int, 1:int}>
     */
    private static function ranges(array $blocks): array
    {
        return array_map(static fn (Block $block): array => [$block->offset, $block->size], $blocks);
    }
}
