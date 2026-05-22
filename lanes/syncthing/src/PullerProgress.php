<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullerProgress
{
    public function __construct(
        public readonly int $total = 0,
        public readonly int $reused = 0,
        public readonly int $copiedFromOrigin = 0,
        public readonly int $copiedFromOriginShifted = 0,
        public readonly int $copiedFromElsewhere = 0,
        public readonly int $pulled = 0,
        public readonly int $pulling = 0,
        public readonly int $bytesDone = 0,
        public readonly int $bytesTotal = 0,
    ) {
        foreach ([
            $this->total,
            $this->reused,
            $this->copiedFromOrigin,
            $this->copiedFromOriginShifted,
            $this->copiedFromElsewhere,
            $this->pulled,
            $this->pulling,
            $this->bytesDone,
            $this->bytesTotal,
        ] as $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Puller progress values must not be negative');
            }
        }
    }

    public static function fromCounters(
        FileInfo $file,
        int $reused = 0,
        int $copyTotal = 0,
        int $pullTotal = 0,
        int $copyNeeded = 0,
        int $pullNeeded = 0,
        int $copiedFromOrigin = 0,
        int $copiedFromOriginShifted = 0,
    ): self {
        foreach ([$reused, $copyTotal, $pullTotal, $copyNeeded, $pullNeeded, $copiedFromOrigin, $copiedFromOriginShifted] as $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Puller progress counters must not be negative');
            }
        }
        if ($copyNeeded > $copyTotal || $pullNeeded > $pullTotal) {
            throw new \InvalidArgumentException('Needed counters must not exceed total counters');
        }

        $total = $reused + $copyTotal + $pullTotal;
        $done = $total - $copyNeeded - $pullNeeded;
        $copiedFromElsewhere = $copyTotal - $copyNeeded - $copiedFromOrigin;
        if ($copiedFromElsewhere < 0) {
            throw new \InvalidArgumentException('Copied-from-origin cannot exceed completed copy work');
        }

        return new self(
            total: $total,
            reused: $reused,
            copiedFromOrigin: $copiedFromOrigin,
            copiedFromOriginShifted: $copiedFromOriginShifted,
            copiedFromElsewhere: $copiedFromElsewhere,
            pulled: $pullTotal - $pullNeeded,
            pulling: $pullNeeded,
            bytesDone: self::blocksToSize($done, count($file->blocks), $file->blockSize(), $file->size),
            bytesTotal: self::blocksToSize($total, count($file->blocks), $file->blockSize(), $file->size),
        );
    }

    public static function fromAvailable(FileInfo $file, int $availableBlocks): self
    {
        if ($availableBlocks < 0) {
            throw new \InvalidArgumentException('Available block count must not be negative');
        }

        $total = count($file->blocks);
        $done = min($availableBlocks, $total);

        return self::fromCounters(
            file: $file,
            pullTotal: $total,
            pullNeeded: $total - $done,
        );
    }

    public static function blocksToSize(int $blocks, int $blocksInFile, int $blockSize, int $fileSize): int
    {
        foreach ([$blocks, $blocksInFile, $blockSize, $fileSize] as $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Block sizing values must not be negative');
            }
        }
        if ($blocksInFile === 0) {
            return 0;
        }
        if ($blockSize <= 0) {
            throw new \InvalidArgumentException('Block size must be positive');
        }

        $shortBlockEstimate = $blockSize - ($fileSize % $blockSize);

        return $blocks * $blockSize - intdiv($shortBlockEstimate * $blocks, $blocksInFile);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'reused' => $this->reused,
            'copiedFromOrigin' => $this->copiedFromOrigin,
            'copiedFromOriginShifted' => $this->copiedFromOriginShifted,
            'copiedFromElsewhere' => $this->copiedFromElsewhere,
            'pulled' => $this->pulled,
            'pulling' => $this->pulling,
            'bytesDone' => $this->bytesDone,
            'bytesTotal' => $this->bytesTotal,
        ];
    }
}
