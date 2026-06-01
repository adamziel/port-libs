<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWal
{
    /**
     * @param list<SQLiteWalFrame> $frames
     */
    public function __construct(
        public readonly SQLiteWalHeader $header,
        public readonly array $frames,
    ) {
    }

    public static function parse(string $bytes, ?int $databasePageSize = null): self
    {
        $header = SQLiteWalHeader::parse($bytes);
        $pageSize = $header->pageSize !== 0 ? $header->pageSize : $databasePageSize;
        if ($pageSize === null || $pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL parser requires a page size from the WAL header or database header');
        }
        if (strlen($bytes) < 32) {
            throw new \InvalidArgumentException('SQLite WAL file is truncated before the frame area');
        }

        $frameSize = 24 + $pageSize;
        $frameBytes = strlen($bytes) - 32;
        if ($frameBytes % $frameSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL file has a truncated frame');
        }

        $frames = [];
        for ($offset = 32, $index = 1; $offset < strlen($bytes); $offset += $frameSize, $index++) {
            $frame = SQLiteWalFrame::parse($index, substr($bytes, $offset, $frameSize), $pageSize);
            if ($frame->salt1 !== $header->salt1 || $frame->salt2 !== $header->salt2) {
                throw new \InvalidArgumentException('SQLite WAL frame salt does not match the WAL header');
            }
            $frames[] = $frame;
        }

        return new self($header, $frames);
    }

    public static function fromFile(string $path, ?int $databasePageSize = null): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite WAL file: {$path}");
        }

        return self::parse($bytes, $databasePageSize);
    }

    public function frameCount(): int
    {
        return count($this->frames);
    }

    public function lastCommitFrame(): ?SQLiteWalFrame
    {
        for ($index = count($this->frames) - 1; $index >= 0; $index--) {
            if ($this->frames[$index]->isCommitFrame()) {
                return $this->frames[$index];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function pageImagesThroughLastCommit(): array
    {
        $lastCommitFrame = $this->lastCommitFrame();
        if ($lastCommitFrame === null) {
            return [];
        }

        $pageImages = [];
        foreach ($this->frames as $frame) {
            $pageImages[$frame->pageNumber] = $frame->pageImage;
            if ($frame->index === $lastCommitFrame->index) {
                break;
            }
        }

        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $lastCommitFrame = $this->lastCommitFrame();

        return [
            'header' => $this->header->toArray(),
            'frame_count' => $this->frameCount(),
            'committed_page_numbers' => array_keys($this->pageImagesThroughLastCommit()),
            'last_commit_frame' => $lastCommitFrame?->toArray(),
        ];
    }
}
