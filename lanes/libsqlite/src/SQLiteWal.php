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
        public readonly bool $checksumsValidated = false,
    ) {
    }

    public static function parse(string $bytes, ?int $databasePageSize = null, bool $validateChecksums = false): self
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

        $checksumSeed = [0, 0];
        if ($validateChecksums) {
            $checksumSeed = self::checksumPair(substr($bytes, 0, 24), $header->usesLittleEndianChecksums());
            if ($checksumSeed !== [$header->checksum1, $header->checksum2]) {
                throw new \InvalidArgumentException('SQLite WAL header checksum does not match the header content');
            }
        }

        $frames = [];
        for ($offset = 32, $index = 1; $offset < strlen($bytes); $offset += $frameSize, $index++) {
            $frameBytesForParse = substr($bytes, $offset, $frameSize);
            $frame = SQLiteWalFrame::parse($index, $frameBytesForParse, $pageSize);
            if ($frame->salt1 !== $header->salt1 || $frame->salt2 !== $header->salt2) {
                throw new \InvalidArgumentException('SQLite WAL frame salt does not match the WAL header');
            }
            if ($validateChecksums) {
                $checksumSeed = self::checksumPair(substr($frameBytesForParse, 0, 8) . $frame->pageImage, $header->usesLittleEndianChecksums(), $checksumSeed[0], $checksumSeed[1]);
                if ($checksumSeed !== [$frame->checksum1, $frame->checksum2]) {
                    throw new \InvalidArgumentException("SQLite WAL frame {$index} checksum does not match the frame content");
                }
            }
            $frames[] = $frame;
        }

        return new self($header, $frames, $validateChecksums);
    }

    public static function fromFile(string $path, ?int $databasePageSize = null, bool $validateChecksums = false): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite WAL file: {$path}");
        }

        return self::parse($bytes, $databasePageSize, $validateChecksums);
    }

    /**
     * @return array{0:int,1:int}
     */
    public static function checksumPair(string $bytes, bool $littleEndian, int $seed1 = 0, int $seed2 = 0): array
    {
        if ((strlen($bytes) % 8) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checksum input must contain whole 64-bit checksum words');
        }

        $s1 = $seed1 & 0xffffffff;
        $s2 = $seed2 & 0xffffffff;
        $format = $littleEndian ? 'V*' : 'N*';
        /** @var list<int> $words */
        $words = array_values(unpack($format, $bytes));
        $count = count($words);
        for ($index = 0; $index < $count; $index += 2) {
            $s1 = ($s1 + ($words[$index] & 0xffffffff) + $s2) & 0xffffffff;
            $s2 = ($s2 + ($words[$index + 1] & 0xffffffff) + $s1) & 0xffffffff;
        }

        return [$s1, $s2];
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
            'checksums_validated' => $this->checksumsValidated,
            'committed_page_numbers' => array_keys($this->pageImagesThroughLastCommit()),
            'last_commit_frame' => $lastCommitFrame?->toArray(),
        ];
    }
}
