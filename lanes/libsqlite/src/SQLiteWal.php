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
     * @return list<array{first_frame:int,last_frame:int,database_page_count:int,page_numbers:list<int>}>
     */
    public function committedTransactions(): array
    {
        $transactions = [];
        $firstFrame = null;
        $pageNumbers = [];

        foreach ($this->frames as $frame) {
            $firstFrame ??= $frame->index;
            $pageNumbers[$frame->pageNumber] = true;

            if (!$frame->isCommitFrame()) {
                continue;
            }

            $orderedPageNumbers = array_keys($pageNumbers);
            sort($orderedPageNumbers, SORT_NUMERIC);
            $transactions[] = [
                'first_frame' => $firstFrame,
                'last_frame' => $frame->index,
                'database_page_count' => $frame->databasePageCountAfterCommit,
                'page_numbers' => $orderedPageNumbers,
            ];
            $firstFrame = null;
            $pageNumbers = [];
        }

        return $transactions;
    }

    public function uncommittedFrameCount(): int
    {
        $lastCommitFrame = $this->lastCommitFrame();

        return $lastCommitFrame === null
            ? count($this->frames)
            : count($this->frames) - $lastCommitFrame->index;
    }

    public function checkpointDatabaseImage(string $databaseBytes): string
    {
        $lastCommitFrame = $this->lastCommitFrame();
        if ($lastCommitFrame === null) {
            return $databaseBytes;
        }

        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint requires a database image aligned to the page size');
        }

        $databasePageCount = $lastCommitFrame->databasePageCountAfterCommit;
        $checkpointBytes = substr($databaseBytes . str_repeat("\0", max(0, ($databasePageCount * $pageSize) - strlen($databaseBytes))), 0, $databasePageCount * $pageSize);
        foreach ($this->pageImagesThroughLastCommit() as $pageNumber => $pageImage) {
            if ($pageNumber > $databasePageCount) {
                continue;
            }
            $checkpointBytes = substr_replace($checkpointBytes, $pageImage, ($pageNumber - 1) * $pageSize, $pageSize);
        }

        return $checkpointBytes;
    }

    /**
     * @return array{database_page_count:int,final_database_bytes:int,last_commit_frame:int|null,frames:list<array{frame_index:int,page_number:int,database_offset:int,applied:bool,reason:string}>}
     */
    public function checkpointPlan(string $databaseBytes): array
    {
        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint plan requires a database image aligned to the page size');
        }

        $lastCommitFrame = $this->lastCommitFrame();
        if ($lastCommitFrame === null) {
            return [
                'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
                'final_database_bytes' => strlen($databaseBytes),
                'last_commit_frame' => null,
                'frames' => [],
            ];
        }

        $lastCommittedFrameByPage = [];
        foreach ($this->frames as $frame) {
            if ($frame->index > $lastCommitFrame->index) {
                break;
            }
            $lastCommittedFrameByPage[$frame->pageNumber] = $frame->index;
        }

        $frames = [];
        foreach ($this->frames as $frame) {
            if ($frame->index > $lastCommitFrame->index) {
                $reason = 'after_last_commit';
                $applied = false;
            } elseif ($frame->pageNumber > $lastCommitFrame->databasePageCountAfterCommit) {
                $reason = 'beyond_committed_database_size';
                $applied = false;
            } elseif ($lastCommittedFrameByPage[$frame->pageNumber] !== $frame->index) {
                $reason = 'superseded_by_later_committed_frame';
                $applied = false;
            } else {
                $reason = 'checkpointed_to_database';
                $applied = true;
            }

            $frames[] = [
                'frame_index' => $frame->index,
                'page_number' => $frame->pageNumber,
                'database_offset' => ($frame->pageNumber - 1) * $pageSize,
                'applied' => $applied,
                'reason' => $reason,
            ];
        }

        return [
            'database_page_count' => $lastCommitFrame->databasePageCountAfterCommit,
            'final_database_bytes' => $lastCommitFrame->databasePageCountAfterCommit * $pageSize,
            'last_commit_frame' => $lastCommitFrame->index,
            'frames' => $frames,
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string}
     */
    public function readerPageImage(string $databaseBytes, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader page numbers are one-based');
        }

        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader requires a database image aligned to the page size');
        }

        $lastCommitFrame = $this->lastCommitFrame();
        $databasePageCount = $lastCommitFrame?->databasePageCountAfterCommit ?? intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader page {$pageNumber} is beyond the committed database size");
        }

        $frame = $this->lastCommittedFrameForPage($pageNumber);
        if ($frame !== null) {
            return [
                'page_number' => $pageNumber,
                'source' => 'wal',
                'frame_index' => $frame->index,
                'database_offset' => ($pageNumber - 1) * $pageSize,
                'image' => $frame->pageImage,
            ];
        }

        $offset = ($pageNumber - 1) * $pageSize;
        $image = substr($databaseBytes, $offset, $pageSize);
        if (strlen($image) !== $pageSize) {
            throw new \OutOfBoundsException("SQLite WAL reader base page {$pageNumber} is missing from the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => $image,
        ];
    }

    /**
     * @return list<array{page_number:int,source:string,frame_index:int|null,database_offset:int}>
     */
    public function readerPageMap(string $databaseBytes): array
    {
        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader requires a database image aligned to the page size');
        }

        $lastCommitFrame = $this->lastCommitFrame();
        $databasePageCount = $lastCommitFrame?->databasePageCountAfterCommit ?? intdiv(strlen($databaseBytes), $pageSize);
        $map = [];
        for ($pageNumber = 1; $pageNumber <= $databasePageCount; $pageNumber++) {
            $entry = $this->readerPageImage($databaseBytes, $pageNumber);
            unset($entry['image']);
            $map[] = $entry;
        }

        return $map;
    }

    private function lastCommittedFrameForPage(int $pageNumber): ?SQLiteWalFrame
    {
        $lastCommitFrame = $this->lastCommitFrame();
        if ($lastCommitFrame === null) {
            return null;
        }

        for ($index = $lastCommitFrame->index - 1; $index >= 0; $index--) {
            $frame = $this->frames[$index];
            if ($frame->pageNumber === $pageNumber) {
                return $frame;
            }
        }

        return null;
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
            'committed_transactions' => $this->committedTransactions(),
            'uncommitted_frame_count' => $this->uncommittedFrameCount(),
            'committed_page_numbers' => array_keys($this->pageImagesThroughLastCommit()),
            'last_commit_frame' => $lastCommitFrame?->toArray(),
        ];
    }
}
