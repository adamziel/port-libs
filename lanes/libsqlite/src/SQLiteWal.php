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
     * @return array{status:string,reason:string,valid_frame_count:int,total_frame_slots:int,first_invalid_frame:int|null,recovery_end_offset:int,valid_wal_bytes:string,wal:SQLiteWal,last_commit_frame:int|null,last_commit_page_count:int|null,uncommitted_frame_count:int,can_checkpoint:bool,checkpoint_database_bytes:string|null,checkpoint_database_page_count:int|null,dependencies:list<string>}
     */
    public static function checksumRecoveryBoundary(string $bytes, string $databaseBytes = '', ?int $databasePageSize = null): array
    {
        $header = SQLiteWalHeader::parse($bytes);
        $pageSize = $header->pageSize !== 0 ? $header->pageSize : $databasePageSize;
        if ($pageSize === null || $pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL recovery requires a page size from the WAL header or database header');
        }
        if (strlen($bytes) < 32) {
            throw new \InvalidArgumentException('SQLite WAL file is truncated before the frame area');
        }

        $headerChecksum = self::checksumPair(substr($bytes, 0, 24), $header->usesLittleEndianChecksums());
        if ($headerChecksum !== [$header->checksum1, $header->checksum2]) {
            $emptyWalBytes = self::headerBytes($header);
            $wal = new self($header, [], true);

            return [
                'status' => 'corrupt',
                'reason' => 'header_checksum_mismatch',
                'valid_frame_count' => 0,
                'total_frame_slots' => 0,
                'first_invalid_frame' => 0,
                'recovery_end_offset' => 32,
                'valid_wal_bytes' => $emptyWalBytes,
                'wal' => $wal,
                'last_commit_frame' => null,
                'last_commit_page_count' => null,
                'uncommitted_frame_count' => 0,
                'can_checkpoint' => false,
                'checkpoint_database_bytes' => null,
                'checkpoint_database_page_count' => null,
                'dependencies' => ['sqlite-wal-checksum-recovery-boundary'],
            ];
        }

        $frameSize = 24 + $pageSize;
        $availableFrameBytes = max(0, strlen($bytes) - 32);
        $completeFrameSlots = intdiv($availableFrameBytes, $frameSize);
        $hasTruncatedTail = ($availableFrameBytes % $frameSize) !== 0;
        $checksumSeed = $headerChecksum;
        $frames = [];
        $validFrameCount = 0;
        $firstInvalidFrame = null;
        $reason = $hasTruncatedTail && $completeFrameSlots === 0 ? 'truncated_frame_tail' : 'all_frames_valid';

        for ($offset = 32, $index = 1; $index <= $completeFrameSlots; $offset += $frameSize, $index++) {
            $frameBytesForParse = substr($bytes, $offset, $frameSize);
            $frame = SQLiteWalFrame::parse($index, $frameBytesForParse, $pageSize);
            if ($frame->salt1 !== $header->salt1 || $frame->salt2 !== $header->salt2) {
                $firstInvalidFrame = $index;
                $reason = 'frame_salt_mismatch';
                break;
            }

            $checksumSeed = self::checksumPair(substr($frameBytesForParse, 0, 8) . $frame->pageImage, $header->usesLittleEndianChecksums(), $checksumSeed[0], $checksumSeed[1]);
            if ($checksumSeed !== [$frame->checksum1, $frame->checksum2]) {
                $firstInvalidFrame = $index;
                $reason = 'frame_checksum_mismatch';
                break;
            }

            $frames[] = $frame;
            $validFrameCount = $index;
        }

        if ($firstInvalidFrame === null && $hasTruncatedTail) {
            $firstInvalidFrame = $completeFrameSlots + 1;
            $reason = 'truncated_frame_tail';
        }

        $recoveryEndOffset = 32 + ($validFrameCount * $frameSize);
        $validWalBytes = substr($bytes, 0, $recoveryEndOffset);
        $wal = new self($header, $frames, true);
        $lastCommitFrame = $wal->lastCommitFrame();
        $checkpointDatabaseBytes = null;
        $checkpointDatabasePageCount = null;
        if ($databaseBytes !== '' && $lastCommitFrame !== null) {
            $checkpointDatabaseBytes = $wal->checkpointDatabaseImage($databaseBytes);
            $checkpointDatabasePageCount = intdiv(strlen($checkpointDatabaseBytes), $pageSize);
        }

        return [
            'status' => $firstInvalidFrame === null ? 'valid' : 'recovered_prefix',
            'reason' => $reason,
            'valid_frame_count' => $validFrameCount,
            'total_frame_slots' => $completeFrameSlots + ($hasTruncatedTail ? 1 : 0),
            'first_invalid_frame' => $firstInvalidFrame,
            'recovery_end_offset' => $recoveryEndOffset,
            'valid_wal_bytes' => $validWalBytes,
            'wal' => $wal,
            'last_commit_frame' => $lastCommitFrame?->index,
            'last_commit_page_count' => $lastCommitFrame?->databasePageCountAfterCommit,
            'uncommitted_frame_count' => $wal->uncommittedFrameCount(),
            'can_checkpoint' => $lastCommitFrame !== null,
            'checkpoint_database_bytes' => $checkpointDatabaseBytes,
            'checkpoint_database_page_count' => $checkpointDatabasePageCount,
            'dependencies' => ['sqlite-wal-checksum-recovery-boundary'],
        ];
    }

    /**
     * @return array{status:string,reason:string,valid_frame_count:int,committed_frame_count:int,total_frame_slots:int,first_invalid_frame:int|null,recovery_end_offset:int,committed_end_offset:int,valid_wal_bytes:string,committed_wal_bytes:string,wal:SQLiteWal,committed_wal:SQLiteWal,last_commit_frame:int|null,last_commit_page_count:int|null,uncommitted_frame_count:int,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,can_checkpoint:bool,checkpoint_database_bytes:string|null,checkpoint_database_page_count:int|null,dependencies:list<string>}
     */
    public static function transactionRecoveryBoundary(string $bytes, string $databaseBytes = '', ?int $databasePageSize = null): array
    {
        $boundary = self::checksumRecoveryBoundary($bytes, $databaseBytes, $databasePageSize);
        $wal = $boundary['wal'];
        $lastCommitFrame = $wal->lastCommitFrame();
        $committedFrameCount = $lastCommitFrame?->index ?? 0;
        $pageSize = $wal->header->pageSize !== 0
            ? $wal->header->pageSize
            : ($databasePageSize ?? ($databaseBytes !== '' ? SQLiteHeader::parse($databaseBytes)->pageSize : null));
        if ($pageSize === null || $pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL transaction recovery requires a page size from the WAL header or database header');
        }

        $committedEndOffset = 32 + ($committedFrameCount * (24 + $pageSize));
        $committedWalBytes = substr($boundary['valid_wal_bytes'], 0, $committedEndOffset);
        $committedWal = $committedFrameCount === 0
            ? new self($wal->header, [], true)
            : self::parse($committedWalBytes, $databasePageSize, true);
        $checkpointDatabaseBytes = null;
        $checkpointDatabasePageCount = null;
        if ($databaseBytes !== '' && $lastCommitFrame !== null) {
            $checkpointDatabaseBytes = $committedWal->checkpointDatabaseImage($databaseBytes);
            $checkpointDatabasePageCount = intdiv(strlen($checkpointDatabaseBytes), $pageSize);
        }

        $discardedValidTail = $boundary['valid_frame_count'] - $committedFrameCount;
        $discardedCorruptTail = max(0, $boundary['total_frame_slots'] - $boundary['valid_frame_count']);
        $reason = $boundary['reason'];
        if ($lastCommitFrame === null && $boundary['valid_frame_count'] > 0) {
            $reason = 'no_committed_transaction_in_valid_prefix';
        } elseif ($discardedValidTail > 0 && $discardedCorruptTail > 0) {
            $reason = 'uncommitted_valid_tail_before_corrupt_frame';
        } elseif ($discardedValidTail > 0) {
            $reason = 'uncommitted_valid_tail_after_last_commit';
        } elseif ($discardedCorruptTail > 0) {
            $reason = 'corrupt_tail_after_committed_prefix';
        }

        $status = $boundary['status'] === 'corrupt'
            ? 'corrupt'
            : ($reason === 'all_frames_valid' ? 'valid' : 'recovered_committed_prefix');

        return [
            'status' => $status,
            'reason' => $reason,
            'valid_frame_count' => $boundary['valid_frame_count'],
            'committed_frame_count' => $committedFrameCount,
            'total_frame_slots' => $boundary['total_frame_slots'],
            'first_invalid_frame' => $boundary['first_invalid_frame'],
            'recovery_end_offset' => $boundary['recovery_end_offset'],
            'committed_end_offset' => $committedEndOffset,
            'valid_wal_bytes' => $boundary['valid_wal_bytes'],
            'committed_wal_bytes' => $committedWalBytes,
            'wal' => $wal,
            'committed_wal' => $committedWal,
            'last_commit_frame' => $boundary['last_commit_frame'],
            'last_commit_page_count' => $boundary['last_commit_page_count'],
            'uncommitted_frame_count' => $committedWal->uncommittedFrameCount(),
            'discarded_valid_tail_frame_count' => $discardedValidTail,
            'discarded_corrupt_tail_frame_count' => $discardedCorruptTail,
            'can_checkpoint' => $lastCommitFrame !== null,
            'checkpoint_database_bytes' => $checkpointDatabaseBytes,
            'checkpoint_database_page_count' => $checkpointDatabasePageCount,
            'dependencies' => ['sqlite-wal-checksum-recovery-boundary', 'sqlite-wal-transaction-recovery-boundary'],
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,valid_frame_count:int,committed_frame_count:int,total_frame_slots:int,first_invalid_frame:int|null,current_reader_end_frame:int,next_reader_end_frame:int,committed_end_offset:int,recovery_end_offset:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,dependencies:list<string>}
     */
    public static function corruptRecoveryCurrentNextBoundary(
        string $bytes,
        string $databaseBytes,
        array $pageNumbers,
        ?int $databasePageSize = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL corrupt recovery current/next boundary requires at least one page number');
        }

        $boundary = self::transactionRecoveryBoundary($bytes, $databaseBytes, $databasePageSize);
        $currentWal = $boundary['wal'];
        $nextWal = $boundary['committed_wal'];
        $nextDatabaseBytes = $boundary['checkpoint_database_bytes'] ?? $databaseBytes;
        $currentEndFrame = $boundary['valid_frame_count'];
        $nextEndFrame = $boundary['committed_frame_count'];

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL corrupt recovery current/next pages must be integers');
            }
            $current[] = self::safeReaderVisibility($currentWal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextEndFrame === 0
                ? self::databasePageVisibilityOrError($nextDatabaseBytes, $currentWal->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $nextDatabaseBytes, $pageNumber, $nextEndFrame);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);

        return [
            'status' => $boundary['status'],
            'reason' => $boundary['reason'],
            'valid_frame_count' => $boundary['valid_frame_count'],
            'committed_frame_count' => $boundary['committed_frame_count'],
            'total_frame_slots' => $boundary['total_frame_slots'],
            'first_invalid_frame' => $boundary['first_invalid_frame'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'committed_end_offset' => $boundary['committed_end_offset'],
            'recovery_end_offset' => $boundary['recovery_end_offset'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'images_match' => $currentImages === $nextImages,
            'next_uses_checkpoint_database' => $boundary['checkpoint_database_bytes'] !== null,
            'discarded_valid_tail_frame_count' => $boundary['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $boundary['discarded_corrupt_tail_frame_count'],
            'dependencies' => array_values(array_unique(array_merge(
                $boundary['dependencies'],
                ['sqlite-wal-corrupt-recovery-current-next-boundary']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,salt_changed:bool,current_salt:array{0:int,1:int},next_salt:array{0:int,1:int},current:array<string,mixed>,next:array<string,mixed>,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_valid_frame_count:int,next_valid_frame_count:int,current_committed_frame_count:int,next_committed_frame_count:int,next_discarded_corrupt_tail_frame_count:int,next_uses_checkpoint_database:bool,images_changed:bool,dependencies:list<string>}
     */
    public static function checksumSaltRecoveryCurrentNext(
        string $currentWalBytes,
        string $nextWalBytes,
        string $databaseBytes,
        array $pageNumbers,
        ?int $databasePageSize = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checksum salt recovery requires at least one page number');
        }

        $currentBoundary = self::transactionRecoveryBoundary($currentWalBytes, $databaseBytes, $databasePageSize);
        $currentWal = $currentBoundary['committed_wal'];
        $currentDatabaseBytes = $currentBoundary['checkpoint_database_bytes'] ?? $databaseBytes;
        $nextBoundary = self::transactionRecoveryBoundary($nextWalBytes, $currentDatabaseBytes, $databasePageSize);
        $nextWal = $nextBoundary['committed_wal'];
        $nextDatabaseBytes = $nextBoundary['checkpoint_database_bytes'] ?? $currentDatabaseBytes;

        $currentEndFrame = $currentBoundary['committed_frame_count'];
        $nextEndFrame = $nextBoundary['committed_frame_count'];
        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checksum salt recovery pages must be integers');
            }

            $current[] = $currentEndFrame === 0
                ? self::databasePageVisibilityOrError($currentDatabaseBytes, $currentWal->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($currentWal, $currentDatabaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextEndFrame === 0
                ? self::databasePageVisibilityOrError($nextDatabaseBytes, $nextWal->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $nextDatabaseBytes, $pageNumber, $nextEndFrame);
        }

        $currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
        $nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];
        $saltChanged = $currentSalt !== $nextSalt;
        $nextDiscardedCorruptTail = $nextBoundary['discarded_corrupt_tail_frame_count'];
        $reason = $saltChanged
            ? ($nextDiscardedCorruptTail > 0 ? 'next_wal_restarted_and_ignored_stale_salt_tail' : 'next_wal_restarted_with_new_salt')
            : 'wal_salt_unchanged';

        return [
            'status' => $saltChanged ? 'salt-recovered-current-next' : 'same-salt-current-next',
            'reason' => $reason,
            'salt_changed' => $saltChanged,
            'current_salt' => $currentSalt,
            'next_salt' => $nextSalt,
            'current' => $currentBoundary,
            'next' => $nextBoundary,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_valid_frame_count' => $currentBoundary['valid_frame_count'],
            'next_valid_frame_count' => $nextBoundary['valid_frame_count'],
            'current_committed_frame_count' => $currentBoundary['committed_frame_count'],
            'next_committed_frame_count' => $nextBoundary['committed_frame_count'],
            'next_discarded_corrupt_tail_frame_count' => $nextDiscardedCorruptTail,
            'next_uses_checkpoint_database' => $nextBoundary['checkpoint_database_bytes'] !== null,
            'images_changed' => self::visibilityImages($current) !== self::visibilityImages($next),
            'dependencies' => array_values(array_unique(array_merge(
                $currentBoundary['dependencies'],
                $nextBoundary['dependencies'],
                ['sqlite-wal-checksum-salt-recovery-current-next70']
            ))),
        ];
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

    public function toBytes(): string
    {
        $bytes = self::headerBytes($this->header);

        foreach ($this->frames as $frame) {
            $bytes .= pack(
                'N*',
                $frame->pageNumber,
                $frame->databasePageCountAfterCommit,
                $frame->salt1,
                $frame->salt2,
                $frame->checksum1,
                $frame->checksum2,
            ) . $frame->pageImage;
        }

        return $bytes;
    }

    private static function headerBytes(SQLiteWalHeader $header): string
    {
        return pack(
            'N*',
            $header->magic,
            $header->formatVersion,
            $header->pageSize,
            $header->checkpointSequence,
            $header->salt1,
            $header->salt2,
            $header->checksum1,
            $header->checksum2,
        );
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
     * @return array{can_reset:bool,action:string,reason:string,checkpointed_frame_count:int,uncommitted_frame_count:int,last_commit_frame:int|null,next_wal_header_salt:array{0:int,1:int}}
     */
    public function resetPlan(string $databaseBytes): array
    {
        $checkpointPlan = $this->checkpointPlan($databaseBytes);
        $lastCommitFrame = $this->lastCommitFrame();
        $uncommittedFrameCount = $this->uncommittedFrameCount();
        $checkpointedFrameCount = 0;
        foreach ($checkpointPlan['frames'] as $frame) {
            if ($frame['applied']) {
                $checkpointedFrameCount++;
            }
        }

        if ($lastCommitFrame === null) {
            $canReset = count($this->frames) === 0;
            $action = $canReset ? 'leave_empty_wal' : 'preserve_uncommitted_wal';
            $reason = $canReset ? 'wal_has_no_frames' : 'no_committed_transaction';
        } elseif ($uncommittedFrameCount > 0) {
            $canReset = false;
            $action = 'preserve_wal_tail';
            $reason = 'uncommitted_frames_after_last_commit';
        } else {
            $canReset = true;
            $action = 'truncate_or_restart_wal';
            $reason = 'all_committed_frames_checkpointed';
        }

        return [
            'can_reset' => $canReset,
            'action' => $action,
            'reason' => $reason,
            'checkpointed_frame_count' => $checkpointedFrameCount,
            'uncommitted_frame_count' => $uncommittedFrameCount,
            'last_commit_frame' => $lastCommitFrame?->index,
            'next_wal_header_salt' => [
                ($this->header->salt1 + 1) & 0xffffffff,
                $this->header->salt2,
            ],
        ];
    }

    /**
     * @return array{mode:string,busy:bool,reason:string,reader_end_frame:int|null,last_commit_frame:int|null,checkpointed_frame_count:int,total_committable_frame_count:int,remaining_committed_frame_count:int,uncommitted_frame_count:int,can_reset:bool,can_truncate:bool}
     */
    public function checkpointModePlan(string $databaseBytes, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint mode: {$mode}");
        }
        if ($readerEndFrame !== null && $readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL reader end frame must be non-negative');
        }

        $checkpointPlan = $this->checkpointPlan($databaseBytes);
        $lastCommitFrame = $this->lastCommitFrame();
        $lastCommitIndex = $lastCommitFrame?->index;
        $checkpointLimit = $lastCommitIndex;
        if ($readerEndFrame !== null && $checkpointLimit !== null) {
            $checkpointLimit = min($checkpointLimit, $readerEndFrame);
        }

        $checkpointedFrameCount = 0;
        $totalCommittableFrameCount = 0;
        foreach ($checkpointPlan['frames'] as $frame) {
            if ($frame['reason'] !== 'checkpointed_to_database') {
                continue;
            }
            $totalCommittableFrameCount++;
            if ($checkpointLimit !== null && $frame['frame_index'] <= $checkpointLimit) {
                $checkpointedFrameCount++;
            }
        }

        $remainingCommittedFrameCount = $totalCommittableFrameCount - $checkpointedFrameCount;
        $readerBlocksCompletion = $remainingCommittedFrameCount > 0 && $readerEndFrame !== null;
        $readerBlocksReset = $readerEndFrame !== null && ($mode === 'restart' || $mode === 'truncate');
        $uncommittedFrameCount = $this->uncommittedFrameCount();
        $allCommittedFramesCheckpointed = $lastCommitFrame !== null
            && $remainingCommittedFrameCount === 0
            && $uncommittedFrameCount === 0;
        $emptyWal = $lastCommitFrame === null && count($this->frames) === 0;
        $busy = ($readerBlocksCompletion && $mode !== 'passive') || ($readerBlocksReset && ($allCommittedFramesCheckpointed || $emptyWal));
        $canReset = ($mode === 'restart' || $mode === 'truncate') && !$busy && ($allCommittedFramesCheckpointed || $emptyWal);

        if ($lastCommitFrame === null) {
            $reason = count($this->frames) === 0 ? 'wal_has_no_frames' : 'no_committed_transaction';
        } elseif ($readerBlocksCompletion) {
            $reason = $mode === 'passive' ? 'reader_limited_passive_checkpoint' : 'reader_blocks_checkpoint_completion';
        } elseif ($readerBlocksReset && ($allCommittedFramesCheckpointed || $emptyWal)) {
            $reason = 'reader_blocks_wal_reset';
        } elseif ($uncommittedFrameCount > 0) {
            $reason = 'uncommitted_frames_after_last_commit';
        } else {
            $reason = match ($mode) {
                'passive' => 'passive_checkpoint_complete',
                'full' => 'full_checkpoint_complete',
                'restart' => 'restart_checkpoint_can_reset_wal',
                'truncate' => 'truncate_checkpoint_can_reset_and_truncate_wal',
            };
        }

        return [
            'mode' => $mode,
            'busy' => $busy,
            'reason' => $reason,
            'reader_end_frame' => $readerEndFrame,
            'last_commit_frame' => $lastCommitIndex,
            'checkpointed_frame_count' => $checkpointedFrameCount,
            'total_committable_frame_count' => $totalCommittableFrameCount,
            'remaining_committed_frame_count' => $remainingCommittedFrameCount,
            'uncommitted_frame_count' => $uncommittedFrameCount,
            'can_reset' => $canReset,
            'can_truncate' => $canReset && $mode === 'truncate',
        ];
    }

    /**
     * @return array{mode:string,busy:bool,reason:string,reader_end_frame:int|null,database_bytes:string,database_page_count:int,final_database_bytes:int,checkpointed_frame_count:int,total_committable_frame_count:int,remaining_committed_frame_count:int,uncommitted_frame_count:int,can_reset:bool,can_truncate:bool,wal_action:string,next_wal_header_salt:array{0:int,1:int}}
     */
    public function checkpointModeResult(string $databaseBytes, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        $plan = $this->checkpointModePlan($databaseBytes, $mode, $readerEndFrame);
        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }

        $databasePageCount = $plan['last_commit_frame'] === null
            ? intdiv(strlen($databaseBytes), $pageSize)
            : $this->frames[$plan['last_commit_frame'] - 1]->databasePageCountAfterCommit;
        $checkpointBytes = substr($databaseBytes . str_repeat("\0", max(0, ($databasePageCount * $pageSize) - strlen($databaseBytes))), 0, $databasePageCount * $pageSize);
        $checkpointLimit = $plan['last_commit_frame'];
        if ($readerEndFrame !== null && $checkpointLimit !== null) {
            $checkpointLimit = min($checkpointLimit, $readerEndFrame);
        }

        foreach ($this->checkpointPlan($databaseBytes)['frames'] as $frame) {
            if ($frame['reason'] !== 'checkpointed_to_database') {
                continue;
            }
            if ($checkpointLimit !== null && $frame['frame_index'] > $checkpointLimit) {
                continue;
            }

            $checkpointBytes = substr_replace(
                $checkpointBytes,
                $this->frames[$frame['frame_index'] - 1]->pageImage,
                $frame['database_offset'],
                $pageSize,
            );
        }

        $walAction = 'preserve_wal';
        if ($plan['can_truncate']) {
            $walAction = 'truncate_wal';
        } elseif ($plan['can_reset']) {
            $walAction = 'restart_wal';
        }

        return [
            'mode' => $plan['mode'],
            'busy' => $plan['busy'],
            'reason' => $plan['reason'],
            'reader_end_frame' => $plan['reader_end_frame'],
            'database_bytes' => $checkpointBytes,
            'database_page_count' => $databasePageCount,
            'final_database_bytes' => strlen($checkpointBytes),
            'checkpointed_frame_count' => $plan['checkpointed_frame_count'],
            'total_committable_frame_count' => $plan['total_committable_frame_count'],
            'remaining_committed_frame_count' => $plan['remaining_committed_frame_count'],
            'uncommitted_frame_count' => $plan['uncommitted_frame_count'],
            'can_reset' => $plan['can_reset'],
            'can_truncate' => $plan['can_truncate'],
            'wal_action' => $walAction,
            'next_wal_header_salt' => [
                ($this->header->salt1 + 1) & 0xffffffff,
                $this->header->salt2,
            ],
        ];
    }

    /**
     * @return array{mode:string,busy:bool,reason:string,reader_end_frame:int|null,database_bytes:string,database_page_count:int,final_database_bytes:int,checkpointed_frame_count:int,total_committable_frame_count:int,remaining_committed_frame_count:int,uncommitted_frame_count:int,can_reset:bool,can_truncate:bool,wal_action:string,wal_bytes:string,wal_bytes_length:int,wal_header:array<string, int|string>|null,next_wal_header_salt:array{0:int,1:int},dependencies:list<string>}
     */
    public function durableCheckpointResult(string $databaseBytes, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        $result = $this->checkpointModeResult($databaseBytes, $mode, $readerEndFrame);
        $walBytes = $this->toBytes();
        $walHeader = $this->header->toArray();

        if ($result['wal_action'] === 'truncate_wal') {
            $walBytes = '';
            $walHeader = null;
        } elseif ($result['wal_action'] === 'restart_wal') {
            $walHeaderBytes = pack(
                'N*',
                $this->header->magic,
                $this->header->formatVersion,
                $this->header->pageSize,
                ($this->header->checkpointSequence + 1) & 0xffffffff,
                $result['next_wal_header_salt'][0],
                $result['next_wal_header_salt'][1],
            );
            $checksum = self::checksumPair($walHeaderBytes, $this->header->usesLittleEndianChecksums());
            $walBytes = $walHeaderBytes . pack('N*', $checksum[0], $checksum[1]);
            $walHeader = SQLiteWalHeader::parse($walBytes)->toArray();
        }

        return $result + [
            'wal_bytes' => $walBytes,
            'wal_bytes_length' => strlen($walBytes),
            'wal_header' => $walHeader,
            'dependencies' => ['sqlite-wal-checkpoint', 'durable-sidecar-write'],
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{mode:string,reader_end_frame:int|null,wal_action:string,checkpoint_reason:string,checkpoint_busy:bool,before:list<array{page_number:int,source:string,frame_index:int|null,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}>,after:list<array{page_number:int,source:string,frame_index:int|null,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}>,stable:bool,dependencies:list<string>}
     */
    public function checkpointReaderVisibility(string $databaseBytes, array $pageNumbers, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader visibility requires at least one page number');
        }

        $before = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader visibility pages must be integers');
            }
            $before[] = $this->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame);
        }

        $checkpoint = $this->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $afterWal = $checkpoint['wal_bytes'] === ''
            ? null
            : self::parse($checkpoint['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        $afterSnapshotEndFrame = $afterWal === null
            ? 0
            : min($readerEndFrame ?? $afterWal->frameCount(), $afterWal->frameCount());

        $after = [];
        foreach ($pageNumbers as $pageNumber) {
            if ($afterWal === null) {
                $after[] = self::databasePageVisibility($checkpoint['database_bytes'], $this->header->pageSize, $pageNumber);
                continue;
            }
            $after[] = $afterWal->readerSnapshotPageImage($checkpoint['database_bytes'], $pageNumber, $afterSnapshotEndFrame);
        }

        return [
            'mode' => $checkpoint['mode'],
            'reader_end_frame' => $readerEndFrame,
            'wal_action' => $checkpoint['wal_action'],
            'checkpoint_reason' => $checkpoint['reason'],
            'checkpoint_busy' => $checkpoint['busy'],
            'before' => $before,
            'after' => $after,
            'stable' => self::visibilityImages($before) === self::visibilityImages($after),
            'dependencies' => ['sqlite-wal-checkpoint', 'wal-reader-current-visibility', 'durable-sidecar-write'],
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointTruncateCurrentNext(string $databaseBytes, array $pageNumbers, ?int $currentReaderEndFrame = null): array
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate current/next requires at least one page number');
        }

        $currentEndFrame = $currentReaderEndFrame ?? $this->frameCount();
        if ($currentEndFrame < 0 || $currentEndFrame > $this->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate current/next reader frame is outside the WAL frame range');
        }

        $current = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint truncate current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $currentEndFrame);
        }

        $checkpoint = $this->durableCheckpointResult($databaseBytes, 'truncate', null);
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            $next[] = self::databasePageVisibilityOrError($checkpoint['database_bytes'], $this->header->pageSize, $pageNumber);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);
        $checkpointPlan = $this->checkpointPlan($databaseBytes);

        return [
            'status' => $checkpoint['can_truncate'] ? 'truncate-checkpoint-drained-reader-next-database' : 'truncate-checkpoint-preserved-wal',
            'reason' => $checkpoint['reason'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => 0,
            'checkpoint' => $checkpoint,
            'checkpoint_plan' => $checkpointPlan,
            'wal_action' => $checkpoint['wal_action'],
            'wal_bytes_length' => $checkpoint['wal_bytes_length'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_errors' => self::visibilityErrors($current),
            'next_errors' => self::visibilityErrors($next),
            'images_match' => $currentImages === $nextImages,
            'next_uses_checkpoint_database' => $checkpoint['database_bytes'] !== $databaseBytes,
            'checkpointed_frame_count' => $checkpoint['checkpointed_frame_count'],
            'total_committable_frame_count' => $checkpoint['total_committable_frame_count'],
            'remaining_committed_frame_count' => $checkpoint['remaining_committed_frame_count'],
            'uncommitted_frame_count' => $checkpoint['uncommitted_frame_count'],
            'database_page_count' => $checkpoint['database_page_count'],
            'final_database_bytes' => $checkpoint['final_database_bytes'],
            'dependencies' => array_values(array_unique(array_merge($checkpoint['dependencies'], [
                'sqlite-wal-reader-checkpoint-truncate-current-next72',
                'sqlite-wal-drained-reader-truncate-boundary',
            ]))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,mode:string,source_status:string,reason:string,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,wal_bytes_length:int,checkpoint_sequence:int,restarted_checkpoint_sequence:int|null,current_salt:array{0:int,1:int},next_salt:array{0:int,1:int}|null,checkpoint:array<string,mixed>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_sources:list<string>,next_sources:list<string>,current_frame_indexes:list<int|null>,next_frame_indexes:list<int|null>,current_errors:list<string>,next_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,next_uses_restarted_header:bool,source_frame_count:int,parsed_frame_count:int,dependencies:list<string>}
     */
    public function restartTruncateReaderCurrentSourceNext(
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $currentReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source reader boundary requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source reader boundary requires restart or truncate mode');
        }

        $source = $this->assertCurrentWalBytes86($walBytes);
        $currentEndFrame = $currentReaderEndFrame ?? $this->frameCount();
        if ($currentEndFrame < 0 || $currentEndFrame > $this->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source reader frame is outside the WAL frame range');
        }

        $checkpoint = $source->durableCheckpointResult($databaseBytes, $mode, null);
        $nextWal = $checkpoint['wal_bytes'] === ''
            ? null
            : self::parse($checkpoint['wal_bytes'], $source->header->pageSize, $this->checksumsValidated);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source reader boundary pages must be integers');
            }

            $current[] = self::safeReaderVisibility($source, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextWal === null || $nextReaderEndFrame === 0
                ? self::databasePageVisibilityOrError($checkpoint['database_bytes'], $source->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);
        $restartedHeader = $checkpoint['wal_header'];

        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'ready',
            'mode' => $mode,
            'source_status' => 'current-source',
            'reason' => $checkpoint['reason'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $checkpoint['wal_action'],
            'wal_bytes_length' => $checkpoint['wal_bytes_length'],
            'checkpoint_sequence' => $source->header->checkpointSequence,
            'restarted_checkpoint_sequence' => is_array($restartedHeader) ? (int) $restartedHeader['checkpoint_sequence'] : null,
            'current_salt' => [$source->header->salt1, $source->header->salt2],
            'next_salt' => is_array($restartedHeader) ? [(int) $restartedHeader['salt1'], (int) $restartedHeader['salt2']] : null,
            'checkpoint' => $checkpoint,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_errors' => self::visibilityErrors($current),
            'next_errors' => self::visibilityErrors($next),
            'images_match' => $currentImages === $nextImages,
            'next_uses_checkpoint_database' => $checkpoint['database_bytes'] !== $databaseBytes,
            'next_uses_restarted_header' => $checkpoint['wal_action'] === 'restart_wal' && $nextWal !== null && $nextWal->frameCount() === 0,
            'source_frame_count' => $source->frameCount(),
            'parsed_frame_count' => $this->frameCount(),
            'dependencies' => array_values(array_unique(array_merge($checkpoint['dependencies'], [
                'sqlite-wal-restart-truncate-reader-current-source-next86',
                'sqlite-wal-current-source-admission',
            ]))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointTruncateReaderCurrentSourceNext(
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        ?int $currentReaderEndFrame
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL truncate reader current-source next88 requires at least one page number');
        }

        $source = $this->assertCurrentWalBytes86($walBytes);
        if ($currentReaderEndFrame !== null && ($currentReaderEndFrame < 0 || $currentReaderEndFrame > $source->frameCount())) {
            throw new \InvalidArgumentException('SQLite WAL truncate reader current-source next88 reader frame is outside the WAL frame range');
        }

        $pinnedCheckpoint = $source->durableCheckpointResult($databaseBytes, 'truncate', $currentReaderEndFrame);
        $drainedCheckpoint = $source->durableCheckpointResult($databaseBytes, 'truncate', null);
        $pinnedWal = $pinnedCheckpoint['wal_bytes'] === ''
            ? null
            : self::parse($pinnedCheckpoint['wal_bytes'], $source->header->pageSize, $this->checksumsValidated);

        $currentEndFrame = $currentReaderEndFrame ?? $source->frameCount();
        $nextEndFrame = $pinnedWal?->frameCount() ?? 0;
        $current = [];
        $next = [];
        $drained = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL truncate reader current-source next88 pages must be integers');
            }

            $current[] = self::safeReaderVisibility($source, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $pinnedWal === null || $nextEndFrame === 0
                ? self::databasePageVisibilityOrError($pinnedCheckpoint['database_bytes'], $source->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($pinnedWal, $pinnedCheckpoint['database_bytes'], $pageNumber, $nextEndFrame);
            $drained[] = self::databasePageVisibilityOrError($drainedCheckpoint['database_bytes'], $source->header->pageSize, $pageNumber);
        }

        $currentSources = self::checkpointSourceRows(
            $current,
            $pinnedCheckpoint['database_bytes'],
            $pinnedCheckpoint['checkpointed_frame_count'],
            'current'
        );
        $nextSources = self::checkpointSourceRows(
            $next,
            $pinnedCheckpoint['database_bytes'],
            $pinnedCheckpoint['checkpointed_frame_count'],
            'next'
        );
        $drainedSources = self::checkpointSourceRows(
            $drained,
            $drainedCheckpoint['database_bytes'],
            $drainedCheckpoint['checkpointed_frame_count'],
            'final'
        );

        return [
            'status' => $pinnedCheckpoint['busy'] ? 'reader-pinned-truncate-preserves-wal' : 'truncate-ready',
            'source_status' => 'current-source',
            'mode' => 'truncate',
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'pinned_checkpoint' => $pinnedCheckpoint,
            'drained_checkpoint' => $drainedCheckpoint,
            'wal_action' => $pinnedCheckpoint['wal_action'],
            'drained_wal_action' => $drainedCheckpoint['wal_action'],
            'wal_bytes_length' => $pinnedCheckpoint['wal_bytes_length'],
            'drained_wal_bytes_length' => $drainedCheckpoint['wal_bytes_length'],
            'current_reader' => $current,
            'next_reader' => $next,
            'drained_reader' => $drained,
            'current_source_rows' => $currentSources,
            'next_source_rows' => $nextSources,
            'drained_source_rows' => $drainedSources,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'drained_sources' => self::visibilityColumn($drained, 'source'),
            'current_source_names' => self::visibilityColumn($currentSources, 'current_source'),
            'next_source_names' => self::visibilityColumn($nextSources, 'current_source'),
            'drained_source_names' => self::visibilityColumn($drainedSources, 'current_source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'drained_frame_indexes' => self::visibilityColumn($drained, 'frame_index'),
            'current_errors' => self::visibilityErrors($current),
            'next_errors' => self::visibilityErrors($next),
            'drained_errors' => self::visibilityErrors($drained),
            'current_next_images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'next_drained_images_match' => self::visibilityImages($next) === self::visibilityImages($drained),
            'current_drained_images_match' => self::visibilityImages($current) === self::visibilityImages($drained),
            'next_uses_preserved_wal' => in_array('wal', self::visibilityColumn($next, 'source'), true),
            'next_uses_checkpoint_database' => in_array('checkpoint-database', self::visibilityColumn($nextSources, 'current_source'), true),
            'drained_uses_reset_database_only' => !in_array('preserved-wal', self::visibilityColumn($drainedSources, 'current_source'), true)
                && !in_array('missing', self::visibilityColumn($drainedSources, 'current_source'), true),
            'reader_pin_blocks_truncate' => $pinnedCheckpoint['busy'] && $pinnedCheckpoint['wal_action'] === 'preserve_wal',
            'drained_retry_truncates_wal' => !$drainedCheckpoint['busy'] && $drainedCheckpoint['wal_action'] === 'truncate_wal',
            'source_frame_count' => $source->frameCount(),
            'parsed_frame_count' => $this->frameCount(),
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $drainedCheckpoint['dependencies'],
                [
                    'sqlite-wal-current-source-admission',
                    'sqlite-wal-reader-checkpoint-truncate-current-source-next88',
                ]
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,mode:string,reason:string,busy:bool,reader_end_frame:int,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint:array<string,mixed>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,next_uses_checkpoint_database:bool,next_uses_preserved_wal:bool,dependencies:list<string>}
     */
    public function checkpointBusyReaderCurrentNext(string $databaseBytes, array $pageNumbers, string $mode = 'full', int $readerEndFrame = 0): array
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL busy-reader checkpoint visibility requires at least one page number');
        }
        if ($readerEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL busy-reader checkpoint requires a positive reader frame');
        }

        $checkpoint = $this->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $nextWal = $checkpoint['wal_bytes'] === ''
            ? null
            : self::parse($checkpoint['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL busy-reader checkpoint visibility pages must be integers');
            }

            $current[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $readerEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibilityOrError($checkpoint['database_bytes'], $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'ready',
            'mode' => $checkpoint['mode'],
            'reason' => $checkpoint['reason'],
            'busy' => $checkpoint['busy'],
            'reader_end_frame' => $readerEndFrame,
            'current_reader_end_frame' => $readerEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $checkpoint['wal_action'],
            'checkpoint' => $checkpoint,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => array_map(static fn (array $entry): string => (string) ($entry['source'] ?? 'error'), $current),
            'next_reader_sources' => array_map(static fn (array $entry): string => (string) ($entry['source'] ?? 'error'), $next),
            'current_reader_frame_indexes' => array_map(static fn (array $entry): ?int => $entry['frame_index'] ?? null, $current),
            'next_reader_frame_indexes' => array_map(static fn (array $entry): ?int => $entry['frame_index'] ?? null, $next),
            'current_reader_errors' => array_values(array_map(static fn (array $entry): string => (string) $entry['error'], array_filter($current, static fn (array $entry): bool => isset($entry['error'])))),
            'next_reader_errors' => array_values(array_map(static fn (array $entry): string => (string) $entry['error'], array_filter($next, static fn (array $entry): bool => isset($entry['error'])))),
            'next_uses_checkpoint_database' => $checkpoint['database_bytes'] !== $databaseBytes,
            'next_uses_preserved_wal' => $checkpoint['wal_action'] === 'preserve_wal',
            'dependencies' => array_values(array_unique(array_merge($checkpoint['dependencies'], [
                'sqlite-wal-checkpoint-busy-reader-current-next',
                'wal-reader-current-next-visibility',
            ]))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array{mode:string,checkpoint_reason:string,checkpoint_busy:bool,wal_action:string,read_mark_plan:array<string,mixed>,current_reader_end_frame:int|null,next_reader_end_frame:int,current_before:list<array<string,mixed>>,current_after:list<array<string,mixed>>,next_after:list<array<string,mixed>>,current_sources:list<string>,next_sources:list<string>,current_frame_indexes:list<int|null>,next_frame_indexes:list<int|null>,current_stable:bool,next_matches_latest_snapshot:bool,pin_blocks_reset:bool,dependencies:list<string>}
     */
    public function checkpointReaderPinCurrentNext(string $databaseBytes, array $pageNumbers, array $readMarks, string $mode = 'restart'): array
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader pin current/next requires at least one page number');
        }

        $readMarkPlan = $this->readMarkPlan($readMarks);
        $currentEndFrame = $readMarkPlan['checkpoint_pinned_frame'] ?? $readMarkPlan['recommended_reader_frame'];
        $checkpoint = $this->durableCheckpointResult($databaseBytes, $mode, $readMarkPlan['checkpoint_pinned_frame']);
        $afterWal = $checkpoint['wal_bytes'] === ''
            ? null
            : self::parse($checkpoint['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        $nextEndFrame = $afterWal?->frameCount() ?? 0;

        $currentBefore = [];
        $currentAfter = [];
        $nextAfter = [];
        $latestBefore = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader pin pages must be integers');
            }

            $currentBefore[] = $this->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentEndFrame);
            $latestBefore[] = $this->readerSnapshotPageImage($databaseBytes, $pageNumber);
            if ($afterWal === null) {
                $currentAfter[] = self::databasePageVisibility($checkpoint['database_bytes'], $this->header->pageSize, $pageNumber);
                $nextAfter[] = self::databasePageVisibility($checkpoint['database_bytes'], $this->header->pageSize, $pageNumber);
                continue;
            }

            $afterCurrentEndFrame = $currentEndFrame === null ? $afterWal->frameCount() : min($currentEndFrame, $afterWal->frameCount());
            $currentAfter[] = $afterWal->readerSnapshotPageImage($checkpoint['database_bytes'], $pageNumber, $afterCurrentEndFrame);
            $nextAfter[] = $afterWal->readerSnapshotPageImage($checkpoint['database_bytes'], $pageNumber);
        }

        return [
            'mode' => $checkpoint['mode'],
            'checkpoint_reason' => $checkpoint['reason'],
            'checkpoint_busy' => $checkpoint['busy'],
            'wal_action' => $checkpoint['wal_action'],
            'read_mark_plan' => $readMarkPlan,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_before' => $currentBefore,
            'current_after' => $currentAfter,
            'next_after' => $nextAfter,
            'current_sources' => self::visibilityColumn($currentAfter, 'source'),
            'next_sources' => self::visibilityColumn($nextAfter, 'source'),
            'current_frame_indexes' => self::visibilityColumn($currentAfter, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($nextAfter, 'frame_index'),
            'current_stable' => self::visibilityImages($currentBefore) === self::visibilityImages($currentAfter),
            'next_matches_latest_snapshot' => self::visibilityImages($latestBefore) === self::visibilityImages($nextAfter),
            'pin_blocks_reset' => $readMarkPlan['checkpoint_pinned_frame'] !== null && $checkpoint['wal_action'] === 'preserve_wal',
            'dependencies' => ['sqlite-wal-checkpoint', 'wal-reader-current-next-pin', 'wal-index-read-marks', 'durable-sidecar-write'],
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array<string,mixed>
     */
    public function checkpointReaderPinCurrentNextHandoff(string $databaseBytes, array $pageNumbers, array $readMarks, string $mode = 'restart'): array
    {
        $base = $this->checkpointReaderPinCurrentNext($databaseBytes, $pageNumbers, $readMarks, $mode);
        $readMarkPlan = $base['read_mark_plan'];
        $nextReadMarks = array_values($readMarks);
        $nextReaderSlot = self::nextReaderSlot($nextReadMarks, $readMarkPlan);
        if ($nextReaderSlot !== null) {
            $nextReadMarks[$nextReaderSlot] = $base['next_reader_end_frame'];
        }

        $releasedReadMarks = $nextReadMarks;
        foreach ($readMarkPlan['read_marks'] as $mark) {
            if ($mark['pins_checkpoint']) {
                $releasedReadMarks[$mark['slot']] = null;
            }
        }

        $releasedPlan = $this->readMarkPlan($releasedReadMarks);
        $retryCheckpoint = $this->durableCheckpointResult($databaseBytes, $mode, $releasedPlan['checkpoint_pinned_frame']);
        $nextReaderSurvivesRelease = $nextReaderSlot !== null
            && array_key_exists($nextReaderSlot, $releasedReadMarks)
            && $releasedReadMarks[$nextReaderSlot] === $base['next_reader_end_frame'];

        return [
            'mode' => $base['mode'],
            'status' => $base['pin_blocks_reset']
                ? ($releasedPlan['checkpoint_pinned_frame'] === null ? 'current-reader-released-next-reader-ready' : 'current-reader-still-pinned')
                : 'no-current-reader-pin',
            'checkpoint_reason' => $base['checkpoint_reason'],
            'checkpoint_busy' => $base['checkpoint_busy'],
            'wal_action' => $base['wal_action'],
            'current_reader_end_frame' => $base['current_reader_end_frame'],
            'next_reader_end_frame' => $base['next_reader_end_frame'],
            'next_reader_slot' => $nextReaderSlot,
            'current_read_marks' => array_values($readMarks),
            'next_read_marks' => $nextReadMarks,
            'released_read_marks' => $releasedReadMarks,
            'retry_checkpoint' => $retryCheckpoint,
            'current_sources' => $base['current_sources'],
            'next_sources' => $base['next_sources'],
            'current_frame_indexes' => $base['current_frame_indexes'],
            'next_frame_indexes' => $base['next_frame_indexes'],
            'current_stable' => $base['current_stable'],
            'next_matches_latest_snapshot' => $base['next_matches_latest_snapshot'],
            'current_pin_released' => $base['pin_blocks_reset'] && $releasedPlan['checkpoint_pinned_frame'] === null,
            'next_reader_survives_release' => $nextReaderSurvivesRelease,
            'retry_can_reset' => $retryCheckpoint['can_reset'],
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'wal-reader-pin-current-next64',
                'sqlite-wal-readmark-handoff',
            ]))),
            'base' => $base,
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array<string,mixed>
     */
    public function checkpointReaderPinSlotHandoffCurrentNext(
        string $databaseBytes,
        array $pageNumbers,
        array $readMarks,
        ?int $nextReaderSlot = null,
        string $mode = 'restart'
    ): array {
        $base = $this->checkpointReaderPinCurrentNext($databaseBytes, $pageNumbers, $readMarks, $mode);
        if (!$base['pin_blocks_reset']) {
            throw new \RuntimeException('SQLite WAL current/next reader-pin handoff requires a checkpoint pinned by an older current reader');
        }

        $readMarkPlan = $base['read_mark_plan'];
        $latestFrame = $readMarkPlan['last_commit_frame'];
        if ($latestFrame === null) {
            throw new \RuntimeException('SQLite WAL current/next reader-pin handoff requires a committed WAL frame');
        }

        $nextReadMarks = array_values($readMarks);
        $chosenSlot = $nextReaderSlot ?? self::nextReaderSlot($nextReadMarks, $readMarkPlan);
        if ($chosenSlot === null) {
            throw new \RuntimeException('SQLite WAL current/next reader-pin handoff requires a reusable read-mark slot');
        }
        if ($chosenSlot < 0 || $chosenSlot >= SQLiteShmIndex::READER_COUNT) {
            throw new \InvalidArgumentException('SQLite WAL current/next reader-pin handoff slot is outside the WAL-index reader range');
        }

        $slotReusable = false;
        foreach ($readMarkPlan['read_marks'] as $mark) {
            if ($mark['slot'] === $chosenSlot && !$mark['pins_checkpoint'] && in_array($chosenSlot, $readMarkPlan['reusable_slots'], true)) {
                $slotReusable = true;
                break;
            }
        }
        if (!$slotReusable && array_key_exists($chosenSlot, $nextReadMarks) && $nextReadMarks[$chosenSlot] !== null) {
            throw new \InvalidArgumentException('SQLite WAL current/next reader-pin handoff cannot overwrite an active non-reusable reader slot');
        }

        while (count($nextReadMarks) <= $chosenSlot) {
            $nextReadMarks[] = null;
        }
        $nextReadMarks[$chosenSlot] = $latestFrame;
        $nextPlan = $this->readMarkPlan($nextReadMarks);
        $checkpointWithNext = $this->durableCheckpointResult($databaseBytes, $mode, $nextPlan['checkpoint_pinned_frame']);

        $releasedReadMarks = $nextReadMarks;
        foreach ($readMarkPlan['read_marks'] as $mark) {
            if ($mark['pins_checkpoint']) {
                $releasedReadMarks[$mark['slot']] = null;
            }
        }
        $releasedPlan = $this->readMarkPlan($releasedReadMarks);
        $releasedCheckpoint = $this->durableCheckpointResult($databaseBytes, $mode, $releasedPlan['checkpoint_pinned_frame']);

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL current/next reader-pin handoff pages must be integers');
            }

            $current[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $base['current_reader_end_frame']);
            $next[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $latestFrame);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);

        return [
            'mode' => $base['mode'],
            'status' => $checkpointWithNext['busy'] ? 'current-reader-pinned-next-reader-active' : 'next-reader-active',
            'current_reader_end_frame' => $base['current_reader_end_frame'],
            'next_reader_end_frame' => $latestFrame,
            'next_reader_slot' => $chosenSlot,
            'current_read_marks' => array_values($readMarks),
            'next_read_marks' => $nextReadMarks,
            'released_read_marks' => $releasedReadMarks,
            'current_pin_frames' => array_values(array_map(
                static fn (array $mark): int => (int) $mark['frame'],
                array_filter($readMarkPlan['read_marks'], static fn (array $mark): bool => (bool) $mark['pins_checkpoint'])
            )),
            'next_reader_slot_reusable_before' => $slotReusable,
            'checkpoint_with_next' => $checkpointWithNext,
            'released_checkpoint' => $releasedCheckpoint,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_errors' => self::visibilityErrors($current),
            'next_errors' => self::visibilityErrors($next),
            'current_images' => $currentImages,
            'next_images' => $nextImages,
            'current_stable' => $currentImages === self::visibilityImages($base['current_after']),
            'next_matches_latest_snapshot' => $nextImages === self::visibilityImages($base['next_after']),
            'next_reader_does_not_pin_checkpoint' => $nextPlan['checkpoint_pinned_frame'] === $readMarkPlan['checkpoint_pinned_frame'],
            'release_unblocks_reset' => !$releasedCheckpoint['busy'] && $releasedCheckpoint['can_reset'],
            'base' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'wal-reader-pin-current-next68',
                'sqlite-wal-readmark-current-next-handoff',
            ]))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @param list<array{page_number:int,commit_page_count:int,page_image:string}> $appendFrames
     * @return array<string,mixed>
     */
    public function checkpointReaderPinAppendCurrentNext(
        string $databaseBytes,
        array $pageNumbers,
        array $readMarks,
        array $appendFrames,
        string $mode = 'restart'
    ): array {
        if ($appendFrames === []) {
            throw new \InvalidArgumentException('SQLite WAL reader-pin append requires at least one appended frame');
        }

        $base = $this->checkpointReaderPinCurrentNext($databaseBytes, $pageNumbers, $readMarks, $mode);
        if (!$base['pin_blocks_reset']) {
            throw new \RuntimeException('SQLite WAL reader-pin append requires a checkpoint pinned by a current reader');
        }

        $appendedWalBytes = self::appendFrameBytes($this->toBytes(), $this->header->pageSize, $appendFrames);
        $appendedWal = self::parse($appendedWalBytes, $this->header->pageSize, $this->checksumsValidated);
        $appendedReadMarks = array_values($readMarks);
        $readMarkPlan = $base['read_mark_plan'];
        $nextReaderSlot = self::nextReaderSlot($appendedReadMarks, $readMarkPlan);
        if ($nextReaderSlot !== null) {
            $appendedReadMarks[$nextReaderSlot] = $appendedWal->frameCount();
        }

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader-pin append pages must be integers');
            }

            $current[] = $appendedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $base['current_reader_end_frame']);
            $next[] = $appendedWal->readerSnapshotPageImage($databaseBytes, $pageNumber);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);

        return [
            'mode' => $base['mode'],
            'status' => 'current-reader-pinned-next-writer-appended',
            'checkpoint_reason' => $base['checkpoint_reason'],
            'checkpoint_busy' => $base['checkpoint_busy'],
            'wal_action' => $base['wal_action'],
            'current_reader_end_frame' => $base['current_reader_end_frame'],
            'next_reader_end_frame' => $appendedWal->frameCount(),
            'appended_frame_count' => count($appendFrames),
            'appended_wal_frame_count' => $appendedWal->frameCount(),
            'appended_wal_bytes_length' => strlen($appendedWalBytes),
            'next_reader_slot' => $nextReaderSlot,
            'current_read_marks' => array_values($readMarks),
            'next_read_marks' => $appendedReadMarks,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_images' => $currentImages,
            'next_images' => $nextImages,
            'current_stable' => $currentImages === self::visibilityImages($base['current_after']),
            'next_sees_appended_commit' => $nextImages !== self::visibilityImages($base['next_after']),
            'pin_blocks_reset' => $base['pin_blocks_reset'],
            'base' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'wal-reader-pin-current-next66',
                'sqlite-wal-append-after-pinned-checkpoint',
            ]))),
        ];
    }

    /**
     * @param list<array{page_number:int,commit_page_count:int,page_image:string}> $frames
     */
    private static function appendFrameBytes(string $walBytes, int $pageSize, array $frames): string
    {
        if (strlen($walBytes) < 32 || ((strlen($walBytes) - 32) % (24 + $pageSize)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL append requires complete WAL bytes');
        }

        $header = SQLiteWalHeader::parse($walBytes);
        $seed = strlen($walBytes) === 32
            ? self::checksumPair(substr($walBytes, 0, 24), $header->usesLittleEndianChecksums())
            : array_values(unpack('N2', substr($walBytes, -($pageSize + 8), 8)));

        /** @var array{0:int,1:int} $seed */
        foreach ($frames as $frame) {
            $pageNumber = $frame['page_number'];
            $commitPageCount = $frame['commit_page_count'];
            $pageImage = $frame['page_image'];
            if ($pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL append page numbers must be one-based');
            }
            if ($commitPageCount < 0) {
                throw new \InvalidArgumentException('SQLite WAL append commit page count must be non-negative');
            }
            if (strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite WAL append page image length must match the WAL page size');
            }

            $framePrefix = pack('N*', $pageNumber, $commitPageCount);
            $saltBytes = substr($walBytes, 16, 8);
            $seed = self::checksumPair($framePrefix . $pageImage, $header->usesLittleEndianChecksums(), $seed[0], $seed[1]);
            $walBytes .= $framePrefix . $saltBytes . pack('N*', $seed[0], $seed[1]) . $pageImage;
        }

        return $walBytes;
    }

    /**
     * @param list<int|null> $readMarks
     * @return array{mx_frame:int,last_commit_frame:int|null,checkpoint_pinned_frame:int|null,checkpoint_can_finish:bool,reset_blocked:bool,read_marks:list<array{slot:int,frame:int|null,active:bool,valid:bool,stale:bool,pins_checkpoint:bool,reason:string}>,reusable_slots:list<int>,recommended_reader_slot:int|null,recommended_reader_frame:int|null}
     */
    public function readMarkPlan(array $readMarks): array
    {
        $mxFrame = count($this->frames);
        $lastCommitFrame = $this->lastCommitFrame();
        $lastCommitIndex = $lastCommitFrame?->index;
        $rows = [];
        $pins = [];
        $reusableSlots = [];

        foreach (array_values($readMarks) as $slot => $frame) {
            if ($frame !== null && $frame < 0) {
                throw new \InvalidArgumentException('SQLite WAL read-mark frame must be non-negative');
            }

            $active = $frame !== null;
            $valid = $frame === null || $frame <= $mxFrame;
            $stale = $frame !== null && $lastCommitIndex !== null && $frame < $lastCommitIndex;
            $pinsCheckpoint = $valid && $frame !== null && $frame > 0 && $lastCommitIndex !== null && $frame < $lastCommitIndex;
            if ($pinsCheckpoint) {
                $pins[] = $frame;
            }
            if (!$active || !$valid || $stale) {
                $reusableSlots[] = $slot;
            }

            $reason = 'pins_latest_commit';
            if (!$active) {
                $reason = 'unused_slot';
            } elseif (!$valid) {
                $reason = 'beyond_wal_mx_frame';
            } elseif ($frame === 0) {
                $reason = $lastCommitIndex === null ? 'database_only_reader' : 'database_only_reader_before_wal_commit';
            } elseif ($stale) {
                $reason = 'reader_pins_older_snapshot';
            } elseif ($lastCommitIndex === null) {
                $reason = 'reader_on_uncommitted_wal';
            }

            $rows[] = [
                'slot' => $slot,
                'frame' => $frame,
                'active' => $active,
                'valid' => $valid,
                'stale' => $stale,
                'pins_checkpoint' => $pinsCheckpoint,
                'reason' => $reason,
            ];
        }

        $checkpointPinnedFrame = $pins === [] ? null : min($pins);
        $recommendedReaderSlot = $reusableSlots[0] ?? null;

        return [
            'mx_frame' => $mxFrame,
            'last_commit_frame' => $lastCommitIndex,
            'checkpoint_pinned_frame' => $checkpointPinnedFrame,
            'checkpoint_can_finish' => $checkpointPinnedFrame === null,
            'reset_blocked' => $lastCommitIndex !== null && $checkpointPinnedFrame !== null,
            'read_marks' => $rows,
            'reusable_slots' => $reusableSlots,
            'recommended_reader_slot' => $recommendedReaderSlot,
            'recommended_reader_frame' => $recommendedReaderSlot === null ? null : $lastCommitIndex,
        ];
    }

    /**
     * @return array{mode:string,status:string,current_reader_end_frame:int|null,current_shm:array<string,mixed>,checkpoint:array<string,mixed>,next_wal_frame_count:int,next_wal_header:array<string, int|string>|null,next_read_marks:list<int|null>,next_read_mark_plan:array<string,mixed>,next_reader_slot:int|null,next_reader_frame:int|null,current_reader_kept_snapshot:bool,next_reader_uses_database:bool,next_reader_uses_restarted_wal:bool,dependencies:list<string>}
     */
    public function restartReadMarkTransition(string $databaseBytes, SQLiteShmIndex $shm, string $mode = 'restart'): array
    {
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL read-mark restart mode: {$mode}");
        }

        $currentShm = $shm->checkpointPlan();
        $readerEndFrame = $currentShm['checkpoint_pinned_frame'];
        $checkpoint = $this->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);

        if ($checkpoint['wal_bytes'] === '') {
            $nextWal = null;
            $nextWalFrameCount = 0;
        } else {
            $nextWal = self::parse($checkpoint['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
            $nextWalFrameCount = $nextWal->frameCount();
        }

        $nextReadMarks = [];
        foreach ($currentShm['read_marks'] as $mark) {
            $keepCurrentReader = $checkpoint['busy']
                && $mark['read_lock_held']
                && $mark['valid']
                && $mark['frame'] !== null
                && $mark['frame'] > 0
                && $mark['frame'] <= $this->frameCount();
            $nextReadMarks[] = $keepCurrentReader ? $mark['frame'] : null;
        }

        if (!$checkpoint['busy']) {
            $nextReadMarks[0] = 0;
        }

        $nextReadMarkPlan = $nextWal === null
            ? [
                'mx_frame' => 0,
                'last_commit_frame' => null,
                'checkpoint_pinned_frame' => null,
                'checkpoint_can_finish' => true,
                'reset_blocked' => false,
                'read_marks' => [],
                'reusable_slots' => array_keys($nextReadMarks),
                'recommended_reader_slot' => 0,
                'recommended_reader_frame' => 0,
            ]
            : $nextWal->readMarkPlan($nextReadMarks);

        $nextReaderSlot = $nextReadMarkPlan['recommended_reader_slot'];
        $nextReaderFrame = $checkpoint['busy']
            ? ($nextReadMarkPlan['recommended_reader_frame'] ?? null)
            : 0;

        return [
            'mode' => $mode,
            'status' => $checkpoint['busy'] ? 'current-reader-pinned' : 'restart-ready',
            'current_reader_end_frame' => $readerEndFrame,
            'current_shm' => $currentShm,
            'checkpoint' => $checkpoint,
            'next_wal_frame_count' => $nextWalFrameCount,
            'next_wal_header' => $checkpoint['wal_header'],
            'next_read_marks' => $nextReadMarks,
            'next_read_mark_plan' => $nextReadMarkPlan,
            'next_reader_slot' => $nextReaderSlot,
            'next_reader_frame' => $nextReaderFrame,
            'current_reader_kept_snapshot' => $checkpoint['busy'] && $readerEndFrame !== null,
            'next_reader_uses_database' => $nextWalFrameCount === 0,
            'next_reader_uses_restarted_wal' => $nextWal !== null && $nextWalFrameCount === 0 && $checkpoint['wal_bytes_length'] === 32,
            'dependencies' => [
                'sqlite-shm-index',
                'wal-index-read-marks',
                'sqlite-wal-checkpoint-restart',
                'wal-current-next-reader-boundary',
            ],
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{mode:string,status:string,current_reader_end_frame:int|null,next_reader_end_frame:int|null,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string|null>,next_reader_images:list<string|null>,current_reader_kept_snapshot:bool,next_reader_uses_database:bool,next_reader_uses_restarted_wal:bool,images_match:bool,transition:array<string,mixed>,dependencies:list<string>}
     */
    public function restartCurrentNextReaderVisibility(
        string $databaseBytes,
        SQLiteShmIndex $shm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL restart current/next reader visibility requires at least one page number');
        }

        $transition = $this->restartReadMarkTransition($databaseBytes, $shm, $mode);
        $currentReaderEndFrame = $transition['current_reader_end_frame'];
        $checkpoint = $transition['checkpoint'];

        $current = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL restart current/next reader pages must be integers');
            }
            $current[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $currentReaderEndFrame);
        }

        $nextWal = $checkpoint['wal_bytes'] === ''
            ? null
            : self::parse($checkpoint['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        $nextReaderEndFrame = $transition['next_reader_frame'];
        if ($nextReaderEndFrame === null) {
            $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;
        }

        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if ($nextWal === null) {
                $next[] = self::databasePageVisibilityOrError($checkpoint['database_bytes'], $this->header->pageSize, $pageNumber);
                continue;
            }
            $next[] = self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, min($nextReaderEndFrame, $nextWal->frameCount()));
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);

        return [
            'mode' => $transition['mode'],
            'status' => $transition['status'],
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $checkpoint['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_reader_kept_snapshot' => $transition['current_reader_kept_snapshot'],
            'next_reader_uses_database' => $transition['next_reader_uses_database'],
            'next_reader_uses_restarted_wal' => $transition['next_reader_uses_restarted_wal'],
            'images_match' => $currentImages === $nextImages,
            'transition' => $transition,
            'dependencies' => array_values(array_unique(array_merge(
                $transition['dependencies'],
                ['wal-restart-current-next-reader-visibility']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int> $yieldedSlots
     * @return array{mode:string,status:string,yielded_slots:list<int>,current_reader_end_frame:int|null,next_reader_end_frame:int,first_checkpoint:array<string,mixed>,yielded_checkpoint:array<string,mixed>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string|null>,next_reader_images:list<string|null>,current_reader_kept_snapshot:bool,next_reader_uses_database:bool,next_reader_uses_restarted_wal:bool,reader_yield_unblocked_reset:bool,read_marks_before:list<array<string,mixed>>,read_marks_after:list<array<string,mixed>>,yield_count:int,dependencies:list<string>}
     */
    public function restartCheckpointReaderYieldCurrentNext(
        string $databaseBytes,
        SQLiteShmIndex $shm,
        array $pageNumbers,
        array $yieldedSlots,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL restart checkpoint reader yield requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL restart checkpoint reader yield mode: {$mode}");
        }

        $yieldedSlots = array_values(array_unique($yieldedSlots));
        sort($yieldedSlots, SORT_NUMERIC);
        foreach ($yieldedSlots as $slot) {
            if (!is_int($slot) || $slot < 0 || $slot >= SQLiteShmIndex::READER_COUNT) {
                throw new \InvalidArgumentException('SQLite WAL restart checkpoint reader yield slots must be valid reader slots');
            }
        }

        $firstTransition = $this->restartReadMarkTransition($databaseBytes, $shm, $mode);
        $currentReaderEndFrame = $firstTransition['current_reader_end_frame'];
        $yieldedMarks = [];
        foreach ($shm->readMarks as $mark) {
            $yieldedMarks[] = in_array($mark['slot'], $yieldedSlots, true) ? null : $mark['frame'];
        }

        $afterYieldPlan = $this->readMarkPlan($yieldedMarks);
        $yieldedReaderEndFrame = $afterYieldPlan['checkpoint_pinned_frame'];
        $yieldedCheckpoint = $this->durableCheckpointResult($databaseBytes, $mode, $yieldedReaderEndFrame);
        $nextWal = $yieldedCheckpoint['wal_bytes'] === ''
            ? null
            : self::parse($yieldedCheckpoint['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL restart checkpoint reader yield pages must be integers');
            }

            $current[] = $currentReaderEndFrame === null
                ? self::databasePageVisibilityOrError($databaseBytes, $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null || $nextWal->frameCount() === 0
                ? self::databasePageVisibilityOrError($yieldedCheckpoint['database_bytes'], $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $yieldedCheckpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);

        return [
            'mode' => $mode,
            'status' => $yieldedCheckpoint['busy'] ? 'still-pinned' : 'yielded-reset-ready',
            'yielded_slots' => $yieldedSlots,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'first_checkpoint' => $firstTransition['checkpoint'],
            'yielded_checkpoint' => $yieldedCheckpoint,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_reader_kept_snapshot' => $currentReaderEndFrame !== null && $firstTransition['checkpoint']['busy'],
            'next_reader_uses_database' => !in_array('wal', self::visibilityColumn($next, 'source'), true),
            'next_reader_uses_restarted_wal' => $nextWal !== null && $nextWal->frameCount() === 0 && $yieldedCheckpoint['wal_bytes_length'] === 32,
            'reader_yield_unblocked_reset' => $firstTransition['checkpoint']['busy'] && !$yieldedCheckpoint['busy'],
            'read_marks_before' => $firstTransition['current_shm']['read_marks'],
            'read_marks_after' => $afterYieldPlan['read_marks'],
            'yield_count' => 2 * count($pageNumbers),
            'dependencies' => array_values(array_unique(array_merge(
                $firstTransition['dependencies'],
                $yieldedCheckpoint['dependencies'],
                ['wal-restart-checkpoint-reader-yield-current-next52']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{mode:string,status:string,current_reader_end_frame:int|null,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_reader_kept_snapshot:bool,next_reader_uses_database:bool,next_reader_uses_restarted_wal:bool,transition:array<string,mixed>,dependencies:list<string>}
     */
    public function restartReadMarkReaderMapTransition(string $databaseBytes, SQLiteShmIndex $shm, array $pageNumbers, string $mode = 'restart'): array
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL restart reader map requires at least one page number');
        }

        $transition = $this->restartReadMarkTransition($databaseBytes, $shm, $mode);
        $nextWal = null;
        if ($transition['checkpoint']['wal_bytes'] !== '') {
            $nextWal = self::parse($transition['checkpoint']['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        }

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL restart reader map pages must be integers');
            }

            $current[] = $transition['current_reader_end_frame'] === null
                ? self::databasePageVisibilityOrError($databaseBytes, $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $transition['current_reader_end_frame']);
            $next[] = $nextWal === null || $transition['next_wal_frame_count'] === 0
                ? self::databasePageVisibilityOrError($transition['checkpoint']['database_bytes'], $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $transition['checkpoint']['database_bytes'], $pageNumber, $transition['next_wal_frame_count']);
        }

        return [
            'mode' => $transition['mode'],
            'status' => $transition['status'],
            'current_reader_end_frame' => $transition['current_reader_end_frame'],
            'next_reader_end_frame' => $transition['next_wal_frame_count'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => array_map(static fn (array $entry): string => (string) ($entry['source'] ?? 'error'), $current),
            'next_reader_sources' => array_map(static fn (array $entry): string => (string) ($entry['source'] ?? 'error'), $next),
            'current_reader_frame_indexes' => array_map(static fn (array $entry): ?int => $entry['frame_index'] ?? null, $current),
            'next_reader_frame_indexes' => array_map(static fn (array $entry): ?int => $entry['frame_index'] ?? null, $next),
            'current_reader_errors' => array_values(array_map(static fn (array $entry): string => (string) $entry['error'], array_filter($current, static fn (array $entry): bool => isset($entry['error'])))),
            'next_reader_errors' => array_values(array_map(static fn (array $entry): string => (string) $entry['error'], array_filter($next, static fn (array $entry): bool => isset($entry['error'])))),
            'current_reader_kept_snapshot' => $transition['current_reader_kept_snapshot'],
            'next_reader_uses_database' => $transition['next_reader_uses_database'],
            'next_reader_uses_restarted_wal' => $transition['next_reader_uses_restarted_wal'],
            'transition' => $transition,
            'dependencies' => array_values(array_unique(array_merge($transition['dependencies'], [
                'wal-checkpoint-restart-reader-map-current-next37',
            ]))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{mode:string,status:string,first:array<string,mixed>,retry:array<string,mixed>,current_reader_end_frame:int|null,current_reader:list<array<string,mixed>>,current_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,retry_reader_end_frame:int,next_reader:list<array<string,mixed>>,next_reader_sources:list<string>,next_reader_frame_indexes:list<int|null>,next_reader_errors:list<string>,current_reader_kept_snapshot:bool,retry_reset_ready:bool,next_reader_uses_database:bool,next_reader_uses_restarted_wal:bool,images_match:bool,dependencies:list<string>}
     */
    public function checkpointReaderPinRestartRetryCurrentNext(
        string $databaseBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $releasedShm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader-pin restart retry requires at least one page number');
        }
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint reader-pin restart retry mode: {$mode}");
        }

        $first = $this->restartReadMarkTransition($databaseBytes, $currentShm, $mode);
        $retry = $this->restartReadMarkTransition($databaseBytes, $releasedShm, $mode);

        $retryWal = null;
        if ($retry['checkpoint']['wal_bytes'] !== '') {
            $retryWal = self::parse($retry['checkpoint']['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        }

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader-pin restart retry pages must be integers');
            }

            $current[] = $first['current_reader_end_frame'] === null
                ? self::databasePageVisibilityOrError($databaseBytes, $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $first['current_reader_end_frame']);

            if ($retryWal === null || $retry['next_wal_frame_count'] === 0) {
                $next[] = self::databasePageVisibilityOrError($retry['checkpoint']['database_bytes'], $this->header->pageSize, $pageNumber);
                continue;
            }

            $next[] = self::safeReaderVisibility($retryWal, $retry['checkpoint']['database_bytes'], $pageNumber, $retry['next_wal_frame_count']);
        }

        return [
            'mode' => $mode,
            'status' => $first['status'] === 'current-reader-pinned' && $retry['status'] === 'restart-ready'
                ? 'reader-pin-restart-current-next'
                : 'restart-retry-' . $retry['status'],
            'first' => $first,
            'retry' => $retry,
            'current_reader_end_frame' => $first['current_reader_end_frame'],
            'current_reader' => $current,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'retry_reader_end_frame' => $retry['next_wal_frame_count'],
            'next_reader' => $next,
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_reader_kept_snapshot' => $first['current_reader_kept_snapshot'],
            'retry_reset_ready' => !$retry['checkpoint']['busy'] && in_array($retry['checkpoint']['wal_action'], ['restart_wal', 'truncate_wal'], true),
            'next_reader_uses_database' => $retry['next_reader_uses_database'],
            'next_reader_uses_restarted_wal' => $retry['next_reader_uses_restarted_wal'],
            'images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'dependencies' => array_values(array_unique(array_merge(
                $first['dependencies'],
                $retry['dependencies'],
                ['wal-checkpoint-reader-pin-restart-retry-current-next54']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointReaderPinRestartCurrentNext(
        string $databaseBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $currentReleasedShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader-pin current/next76 requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint reader-pin current/next76 mode: {$mode}");
        }

        $first = $this->restartReadMarkTransition($databaseBytes, $currentShm, $mode);
        $nextPin = $this->restartReadMarkTransition($databaseBytes, $nextReaderShm, $mode);
        $afterCurrentRelease = $this->restartReadMarkTransition($databaseBytes, $currentReleasedShm, $mode);
        $afterAllRelease = $this->restartReadMarkTransition($databaseBytes, $allReleasedShm, $mode);
        $nextReaderEndFrame = self::highestActiveReadMarkFrame($nextReaderShm);
        $currentReleasedReaderEndFrame = self::highestActiveReadMarkFrame($currentReleasedShm);
        if ($currentReleasedReaderEndFrame !== null) {
            $afterCurrentRelease['checkpoint'] = $this->durableCheckpointResult($databaseBytes, $mode, $currentReleasedReaderEndFrame);
            $afterCurrentRelease['status'] = $afterCurrentRelease['checkpoint']['busy'] ? 'current-reader-pinned' : 'restart-ready';
            $afterCurrentRelease['current_reader_end_frame'] = $currentReleasedReaderEndFrame;
            $afterCurrentRelease['current_reader_kept_snapshot'] = $afterCurrentRelease['checkpoint']['busy'];
            $afterCurrentRelease['next_read_marks'] = array_map(
                static fn (array $mark): ?int => $mark['frame'],
                $currentReleasedShm->readMarks
            );
        }

        $finalWal = null;
        if ($afterAllRelease['checkpoint']['wal_bytes'] !== '') {
            $finalWal = self::parse($afterAllRelease['checkpoint']['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);
        }

        $current = [];
        $next = [];
        $final = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader-pin current/next76 pages must be integers');
            }

            $current[] = $first['current_reader_end_frame'] === null
                ? self::databasePageVisibilityOrError($databaseBytes, $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $first['current_reader_end_frame']);
            $next[] = $nextReaderEndFrame === null
                ? self::databasePageVisibilityOrError($databaseBytes, $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $nextReaderEndFrame);
            $final[] = $finalWal === null || $afterAllRelease['next_wal_frame_count'] === 0
                ? self::databasePageVisibilityOrError($afterAllRelease['checkpoint']['database_bytes'], $this->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($finalWal, $afterAllRelease['checkpoint']['database_bytes'], $pageNumber, $afterAllRelease['next_wal_frame_count']);
        }

        return [
            'mode' => $mode,
            'status' => $first['status'] === 'current-reader-pinned' && $afterCurrentRelease['status'] === 'current-reader-pinned' && $afterAllRelease['status'] === 'restart-ready'
                ? 'reader-pin-next-reader-blocks-restart-current-next76'
                : 'reader-pin-next-reader-' . $afterCurrentRelease['status'],
            'first' => $first,
            'next_pin' => $nextPin,
            'after_current_release' => $afterCurrentRelease,
            'after_all_release' => $afterAllRelease,
            'current_reader_end_frame' => $first['current_reader_end_frame'],
            'next_reader_end_frame' => $nextReaderEndFrame,
            'final_reader_end_frame' => $afterAllRelease['next_wal_frame_count'],
            'current_reader' => $current,
            'next_reader' => $next,
            'final_reader' => $final,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'final_reader_sources' => self::visibilityColumn($final, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'final_reader_frame_indexes' => self::visibilityColumn($final, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'final_reader_errors' => self::visibilityErrors($final),
            'current_reader_kept_snapshot' => $first['current_reader_kept_snapshot'],
            'next_reader_kept_snapshot' => $afterCurrentRelease['current_reader_kept_snapshot'],
            'next_reader_blocks_reset' => $afterCurrentRelease['checkpoint']['busy'],
            'final_reset_ready' => !$afterAllRelease['checkpoint']['busy'] && in_array($afterAllRelease['checkpoint']['wal_action'], ['restart_wal', 'truncate_wal'], true),
            'current_next_images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'next_final_images_match' => self::visibilityImages($next) === self::visibilityImages($final),
            'dependencies' => array_values(array_unique(array_merge(
                $first['dependencies'],
                $nextPin['dependencies'],
                $afterCurrentRelease['dependencies'],
                $afterAllRelease['dependencies'],
                ['wal-reader-pin-checkpoint-restart-current-next76']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointReaderPinRestartCurrentSourceNext(
        string $databaseBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $currentReleasedShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        $plan = $this->checkpointReaderPinRestartCurrentNext(
            $databaseBytes,
            $currentShm,
            $nextReaderShm,
            $currentReleasedShm,
            $allReleasedShm,
            $pageNumbers,
            $mode
        );

        $afterCurrentCheckpoint = $plan['after_current_release']['checkpoint'];
        $afterAllCheckpoint = $plan['after_all_release']['checkpoint'];
        $currentSources = self::checkpointSourceRows(
            $plan['current_reader'],
            null,
            $plan['first']['checkpoint']['checkpointed_frame_count'],
            'current'
        );
        $nextSources = self::checkpointSourceRows(
            $plan['next_reader'],
            $afterCurrentCheckpoint['database_bytes'],
            $afterCurrentCheckpoint['checkpointed_frame_count'],
            'next'
        );
        $finalSources = self::checkpointSourceRows(
            $plan['final_reader'],
            $afterAllCheckpoint['database_bytes'],
            $afterAllCheckpoint['checkpointed_frame_count'],
            'final'
        );

        return array_merge($plan, [
            'status' => str_replace('current-next76', 'current-source-next83', (string) $plan['status']),
            'after_current_release_checkpointed_frame_count' => $afterCurrentCheckpoint['checkpointed_frame_count'],
            'after_all_release_checkpointed_frame_count' => $afterAllCheckpoint['checkpointed_frame_count'],
            'current_source_rows' => $currentSources,
            'next_source_rows_after_current_release' => $nextSources,
            'final_source_rows_after_all_release' => $finalSources,
            'current_source_names' => self::visibilityColumn($currentSources, 'current_source'),
            'next_source_names_after_current_release' => self::visibilityColumn($nextSources, 'current_source'),
            'final_source_names_after_all_release' => self::visibilityColumn($finalSources, 'current_source'),
            'next_mixed_checkpoint_database_and_wal' => in_array('checkpoint-database', self::visibilityColumn($nextSources, 'current_source'), true)
                && in_array('preserved-wal', self::visibilityColumn($nextSources, 'current_source'), true),
            'final_uses_reset_database_only' => !in_array('preserved-wal', self::visibilityColumn($finalSources, 'current_source'), true)
                && !in_array('missing', self::visibilityColumn($finalSources, 'current_source'), true),
            'dependencies' => array_values(array_unique(array_merge(
                $plan['dependencies'],
                ['wal-reader-pin-checkpoint-restart-current-source-next83']
            ))),
        ]);
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointReaderRestartCurrentSourceNext(
        string $databaseBytes,
        string $walBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $currentReleasedShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        $source = $this->assertCurrentWalBytes86($walBytes);
        if ($source->toBytes() !== $this->toBytes()) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart current-source bytes mismatch');
        }

        $plan = $this->checkpointReaderPinRestartCurrentSourceNext(
            $databaseBytes,
            $currentShm,
            $nextReaderShm,
            $currentReleasedShm,
            $allReleasedShm,
            $pageNumbers,
            $mode
        );

        $afterCurrentCheckpoint = $plan['after_current_release']['checkpoint'];
        $afterAllCheckpoint = $plan['after_all_release']['checkpoint'];
        $currentPlan = $currentShm->checkpointPlan();
        $nextPlan = $nextReaderShm->checkpointPlan();
        $currentReleasedPlan = $currentReleasedShm->checkpointPlan();
        $allReleasedPlan = $allReleasedShm->checkpointPlan();

        return array_merge($plan, [
            'status' => str_replace('current-source-next83', 'current-source-next89', (string) $plan['status']),
            'current_source_verified' => true,
            'current_source' => [
                'kind' => 'current_wal_sidecar',
                'wal_bytes_length' => strlen($walBytes),
                'frame_count' => $source->frameCount(),
                'committed_frame_count' => $source->lastCommitFrame()?->index ?? 0,
                'checkpoint_sequence' => $source->header->checkpointSequence,
                'page_size' => $source->header->pageSize,
                'salt1' => $source->header->salt1,
                'salt2' => $source->header->salt2,
                'checksums_validated' => $source->checksumsValidated,
            ],
            'restart_source' => [
                'kind' => $afterAllCheckpoint['wal_action'],
                'wal_bytes_length' => $afterAllCheckpoint['wal_bytes_length'],
                'checkpoint_sequence' => is_array($afterAllCheckpoint['wal_header']) ? $afterAllCheckpoint['wal_header']['checkpoint_sequence'] : null,
                'salt1' => is_array($afterAllCheckpoint['wal_header']) ? $afterAllCheckpoint['wal_header']['salt1'] : null,
                'salt2' => is_array($afterAllCheckpoint['wal_header']) ? $afterAllCheckpoint['wal_header']['salt2'] : null,
                'database_bytes_length' => strlen((string) $afterAllCheckpoint['database_bytes']),
            ],
            'read_mark_sources' => [
                'current' => $currentPlan,
                'next_reader' => $nextPlan,
                'current_released' => $currentReleasedPlan,
                'all_released' => $allReleasedPlan,
            ],
            'current_source_checkpointed_frame_count' => $plan['first']['checkpoint']['checkpointed_frame_count'],
            'next_source_checkpointed_frame_count' => $afterCurrentCheckpoint['checkpointed_frame_count'],
            'final_source_checkpointed_frame_count' => $afterAllCheckpoint['checkpointed_frame_count'],
            'current_checkpoint_pinned_frame' => $currentPlan['checkpoint_pinned_frame'],
            'next_checkpoint_pinned_frame' => $nextPlan['checkpoint_pinned_frame'],
            'current_released_checkpoint_pinned_frame' => $currentReleasedPlan['checkpoint_pinned_frame'],
            'all_released_checkpoint_pinned_frame' => $allReleasedPlan['checkpoint_pinned_frame'],
            'current_reader_preserves_sidecar_source' => in_array('preserved-wal', $plan['current_source_names'], true),
            'next_reader_uses_checkpoint_source' => in_array('checkpoint-database', $plan['next_source_names_after_current_release'], true),
            'final_reader_uses_restarted_source' => $plan['final_uses_reset_database_only'] && in_array($afterAllCheckpoint['wal_action'], ['restart_wal', 'truncate_wal'], true),
            'dependencies' => array_values(array_unique(array_merge(
                $plan['dependencies'],
                $currentPlan['dependencies'],
                $nextPlan['dependencies'],
                $currentReleasedPlan['dependencies'],
                $allReleasedPlan['dependencies'],
                ['wal-reader-checkpoint-restart-current-source-next89']
            ))),
        ]);
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointRestartTruncateReaderCurrentSourceNext(
        string $databaseBytes,
        string $walBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $currentReleasedShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        $source = $this->assertCurrentWalBytes86($walBytes);
        if ($source->toBytes() !== $this->toBytes()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint restart/truncate next93 current source bytes mismatch');
        }

        $plan = $this->checkpointReaderRestartCurrentSourceNext(
            $databaseBytes,
            $walBytes,
            $currentShm,
            $nextReaderShm,
            $currentReleasedShm,
            $allReleasedShm,
            $pageNumbers,
            $mode
        );

        $currentRows = $plan['current_source_rows'];
        $nextRows = $plan['next_source_rows_after_current_release'];
        $finalRows = $plan['final_source_rows_after_all_release'];
        $currentNames = self::visibilityColumn($currentRows, 'current_source');
        $nextNames = self::visibilityColumn($nextRows, 'current_source');
        $finalNames = self::visibilityColumn($finalRows, 'current_source');
        $afterCurrentCheckpoint = $plan['after_current_release']['checkpoint'];
        $afterAllCheckpoint = $plan['after_all_release']['checkpoint'];

        return array_merge($plan, [
            'status' => str_replace('current-source-next89', 'current-source-next93', (string) $plan['status']),
            'source_generation' => [
                'current_wal_bytes_sha1' => sha1($walBytes),
                'current_wal_bytes_length' => strlen($walBytes),
                'current_frame_count' => $source->frameCount(),
                'current_checkpoint_sequence' => $source->header->checkpointSequence,
                'current_salt' => [$source->header->salt1, $source->header->salt2],
                'after_current_release_wal_action' => $afterCurrentCheckpoint['wal_action'],
                'after_current_release_wal_bytes_length' => $afterCurrentCheckpoint['wal_bytes_length'],
                'after_all_release_wal_action' => $afterAllCheckpoint['wal_action'],
                'after_all_release_wal_bytes_length' => $afterAllCheckpoint['wal_bytes_length'],
                'after_all_release_checkpoint_sequence' => is_array($afterAllCheckpoint['wal_header']) ? $afterAllCheckpoint['wal_header']['checkpoint_sequence'] : null,
                'after_all_release_salt' => is_array($afterAllCheckpoint['wal_header'])
                    ? [$afterAllCheckpoint['wal_header']['salt1'], $afterAllCheckpoint['wal_header']['salt2']]
                    : null,
            ],
            'current_source_names_next93' => $currentNames,
            'next_source_names_next93' => $nextNames,
            'final_source_names_next93' => $finalNames,
            'current_uses_verified_sidecar' => in_array('preserved-wal', $currentNames, true),
            'next_uses_checkpoint_database' => in_array('checkpoint-database', $nextNames, true),
            'next_still_preserves_sidecar_for_reader_pin' => in_array('preserved-wal', $nextNames, true),
            'final_uses_reset_database_only_next93' => $finalNames !== [] && count(array_unique($finalNames)) === 1 && $finalNames[0] === 'reset-database',
            'restart_source_is_new_generation' => $afterAllCheckpoint['wal_action'] === 'restart_wal'
                && is_array($afterAllCheckpoint['wal_header'])
                && (int) $afterAllCheckpoint['wal_header']['checkpoint_sequence'] !== $source->header->checkpointSequence
                && (int) $afterAllCheckpoint['wal_header']['salt1'] !== $source->header->salt1,
            'truncate_source_is_empty_generation' => $afterAllCheckpoint['wal_action'] === 'truncate_wal'
                && $afterAllCheckpoint['wal_bytes_length'] === 0
                && $afterAllCheckpoint['wal_header'] === null,
            'current_to_next_source_transition' => self::sourceTransitionRows($currentRows, $nextRows, 'current_to_next'),
            'next_to_final_source_transition' => self::sourceTransitionRows($nextRows, $finalRows, 'next_to_final'),
            'current_to_final_source_transition' => self::sourceTransitionRows($currentRows, $finalRows, 'current_to_final'),
            'dependencies' => array_values(array_unique(array_merge(
                $plan['dependencies'],
                ['wal-checkpoint-restart-truncate-reader-current-source-next93']
            ))),
        ]);
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointRestartTruncateReaderPreserveCurrentSourceNext(
        string $databaseBytes,
        string $walBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader current-source next97 requires at least one page number');
        }

        $source = $this->assertCurrentWalBytes86($walBytes);
        if ($source->toBytes() !== $this->toBytes()) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader current-source next97 bytes mismatch');
        }

        $currentReleasedShm = $nextReaderShm;
        $restart = $this->checkpointReaderRestartCurrentSourceNext(
            $databaseBytes,
            $walBytes,
            $currentShm,
            $nextReaderShm,
            $currentReleasedShm,
            $allReleasedShm,
            $pageNumbers,
            'restart'
        );
        $truncate = $this->checkpointReaderRestartCurrentSourceNext(
            $databaseBytes,
            $walBytes,
            $currentShm,
            $nextReaderShm,
            $currentReleasedShm,
            $allReleasedShm,
            $pageNumbers,
            'truncate'
        );

        $restartAfterCurrent = $restart['after_current_release']['checkpoint'];
        $truncateAfterCurrent = $truncate['after_current_release']['checkpoint'];
        $restartAfterAll = $restart['after_all_release']['checkpoint'];
        $truncateAfterAll = $truncate['after_all_release']['checkpoint'];

        return [
            'status' => $restart['after_current_release']['status'] === 'current-reader-pinned'
                && $truncate['after_current_release']['status'] === 'current-reader-pinned'
                && $restart['after_all_release']['status'] === 'restart-ready'
                && $truncate['after_all_release']['status'] === 'restart-ready'
                    ? 'reader-current-source-next97'
                    : 'reader-current-source-next97-incomplete',
            'current_source_verified' => true,
            'current_source' => [
                'kind' => 'current_wal_sidecar',
                'wal_bytes_length' => strlen($walBytes),
                'frame_count' => $source->frameCount(),
                'committed_frame_count' => $source->lastCommitFrame()?->index ?? 0,
                'checkpoint_sequence' => $source->header->checkpointSequence,
                'page_size' => $source->header->pageSize,
                'salt1' => $source->header->salt1,
                'salt2' => $source->header->salt2,
                'checksums_validated' => $source->checksumsValidated,
            ],
            'current_reader_end_frame' => $restart['current_reader_end_frame'],
            'next_reader_end_frame' => $restart['next_reader_end_frame'],
            'final_reader_end_frame' => $restart['final_reader_end_frame'],
            'restart' => $restart,
            'truncate' => $truncate,
            'restart_after_current_wal_action' => $restartAfterCurrent['wal_action'],
            'truncate_after_current_wal_action' => $truncateAfterCurrent['wal_action'],
            'restart_after_all_wal_action' => $restartAfterAll['wal_action'],
            'truncate_after_all_wal_action' => $truncateAfterAll['wal_action'],
            'restart_after_all_wal_bytes_length' => $restartAfterAll['wal_bytes_length'],
            'truncate_after_all_wal_bytes_length' => $truncateAfterAll['wal_bytes_length'],
            'restart_after_all_checkpoint_sequence' => is_array($restartAfterAll['wal_header']) ? $restartAfterAll['wal_header']['checkpoint_sequence'] : null,
            'truncate_after_all_checkpoint_sequence' => is_array($truncateAfterAll['wal_header']) ? $truncateAfterAll['wal_header']['checkpoint_sequence'] : null,
            'current_sources' => $restart['current_source_names'],
            'next_sources_after_current_release' => $restart['next_source_names_after_current_release'],
            'restart_final_sources' => $restart['final_source_names_after_all_release'],
            'truncate_final_sources' => $truncate['final_source_names_after_all_release'],
            'current_reader_preserves_sidecar_source' => $restart['current_reader_preserves_sidecar_source'] && $truncate['current_reader_preserves_sidecar_source'],
            'next_reader_blocks_restart_reset' => $restartAfterCurrent['busy'] && $restartAfterCurrent['wal_action'] === 'preserve_wal',
            'next_reader_blocks_truncate_reset' => $truncateAfterCurrent['busy'] && $truncateAfterCurrent['wal_action'] === 'preserve_wal',
            'restart_final_uses_restarted_wal_header' => $restartAfterAll['wal_action'] === 'restart_wal' && $restartAfterAll['wal_bytes_length'] === 32,
            'truncate_final_removes_wal_sidecar' => $truncateAfterAll['wal_action'] === 'truncate_wal' && $truncateAfterAll['wal_bytes_length'] === 0,
            'restart_and_truncate_checkpoint_same_database' => $restartAfterAll['database_bytes'] === $truncateAfterAll['database_bytes'],
            'restart_next_final_images_match' => $restart['next_final_images_match'],
            'truncate_next_final_images_match' => $truncate['next_final_images_match'],
            'dependencies' => array_values(array_unique(array_merge(
                $restart['dependencies'],
                $truncate['dependencies'],
                ['wal-checkpoint-restart-truncate-reader-current-source-next97']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointRestartTruncateReaderRecoveryCurrentSourceNext(
        string $databaseBytes,
        string $walBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers
    ): array {
        $source = $this->assertCurrentWalBytes86($walBytes);
        if ($source->toBytes() !== $this->toBytes()) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader current-source next102 bytes mismatch');
        }

        $currentPlan = $currentShm->checkpointPlan();
        $nextPlan = $nextReaderShm->checkpointPlan();
        $allReleasedPlan = $allReleasedShm->checkpointPlan();
        $currentSalt = $currentShm->header['salt'] ?? [];
        if (($currentSalt[0] ?? null) !== $source->header->salt1 || ($currentSalt[1] ?? null) !== $source->header->salt2) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader current-source next102 SHM salt does not match current WAL');
        }
        if ((int) ($currentShm->header['mx_frame'] ?? -1) !== $source->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader current-source next102 SHM mxFrame does not match current WAL');
        }

        $plan = $this->checkpointRestartTruncateReaderPreserveCurrentSourceNext(
            $databaseBytes,
            $walBytes,
            $currentShm,
            $nextReaderShm,
            $allReleasedShm,
            $pageNumbers
        );
        $restart = $plan['restart'];
        $truncate = $plan['truncate'];

        $currentRows = $restart['current_source_rows'];
        $nextRows = $restart['next_source_rows_after_current_release'];
        $restartFinalRows = $restart['final_source_rows_after_all_release'];
        $truncateFinalRows = $truncate['final_source_rows_after_all_release'];
        $restartAfterAll = $restart['after_all_release']['checkpoint'];
        $truncateAfterAll = $truncate['after_all_release']['checkpoint'];

        return array_merge($plan, [
            'status' => $plan['status'] === 'reader-current-source-next97'
                && $currentPlan['reset_blocked']
                && $nextPlan['reset_blocked']
                && !$allReleasedPlan['reset_blocked']
                    ? 'reader-current-source-next102'
                    : 'reader-current-source-next102-incomplete',
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'current_shm_source' => [
                'mx_frame' => $currentShm->header['mx_frame'],
                'backfilled_frame_count' => $currentShm->backfilledFrameCount,
                'backfill_attempted_frame_count' => $currentShm->backfillAttemptedFrameCount,
                'salt1' => $currentSalt[0],
                'salt2' => $currentSalt[1],
                'headers_match' => $currentShm->headersMatch,
                'checkpoint_pinned_frame' => $currentPlan['checkpoint_pinned_frame'],
                'reset_blocked' => $currentPlan['reset_blocked'],
            ],
            'next_shm_source' => [
                'checkpoint_pinned_frame' => $nextPlan['checkpoint_pinned_frame'],
                'reset_blocked' => $nextPlan['reset_blocked'],
                'read_locks' => $nextPlan['read_locks'],
            ],
            'all_released_shm_source' => [
                'checkpoint_pinned_frame' => $allReleasedPlan['checkpoint_pinned_frame'],
                'reset_blocked' => $allReleasedPlan['reset_blocked'],
                'read_locks' => $allReleasedPlan['read_locks'],
            ],
            'current_to_next_source_transition_next102' => self::sourceTransitionRows($currentRows, $nextRows, 'current_to_next102'),
            'next_to_restart_final_source_transition_next102' => self::sourceTransitionRows($nextRows, $restartFinalRows, 'next_to_restart_final102'),
            'next_to_truncate_final_source_transition_next102' => self::sourceTransitionRows($nextRows, $truncateFinalRows, 'next_to_truncate_final102'),
            'current_source_names_next102' => self::visibilityColumn($currentRows, 'current_source'),
            'next_source_names_next102' => self::visibilityColumn($nextRows, 'current_source'),
            'restart_final_source_names_next102' => self::visibilityColumn($restartFinalRows, 'current_source'),
            'truncate_final_source_names_next102' => self::visibilityColumn($truncateFinalRows, 'current_source'),
            'restart_final_wal_generation' => [
                'action' => $restartAfterAll['wal_action'],
                'wal_bytes_length' => $restartAfterAll['wal_bytes_length'],
                'checkpoint_sequence' => is_array($restartAfterAll['wal_header']) ? $restartAfterAll['wal_header']['checkpoint_sequence'] : null,
            ],
            'truncate_final_wal_generation' => [
                'action' => $truncateAfterAll['wal_action'],
                'wal_bytes_length' => $truncateAfterAll['wal_bytes_length'],
                'checkpoint_sequence' => is_array($truncateAfterAll['wal_header']) ? $truncateAfterAll['wal_header']['checkpoint_sequence'] : null,
            ],
            'restart_truncate_final_database_match_next102' => $restartAfterAll['database_bytes'] === $truncateAfterAll['database_bytes'],
            'dependencies' => array_values(array_unique(array_merge(
                $plan['dependencies'],
                $currentPlan['dependencies'],
                $nextPlan['dependencies'],
                $allReleasedPlan['dependencies'],
                ['wal-restart-truncate-reader-current-source-next102']
            ))),
        ]);
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public function checkpointSnapshotCurrentSourceNext(
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        int $currentReaderEndFrame,
        ?int $nextReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot current-source next108 requires at least one page number');
        }
        if ($currentReaderEndFrame < 0 || $currentReaderEndFrame > $this->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot current-source next108 current reader frame is outside the WAL frame range');
        }

        $source = self::parse($walBytes, $this->header->pageSize, $this->checksumsValidated);
        if ($source->header != $this->header || $source->frameCount() !== $this->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot current-source next108 source header mismatch');
        }
        foreach ($source->frames as $index => $frame) {
            if (!isset($this->frames[$index]) || $this->frames[$index] != $frame) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot current-source next108 source frame ' . ($index + 1) . ' mismatch');
            }
        }

        $nextReaderEndFrame ??= $this->frameCount();
        if ($nextReaderEndFrame < 0 || $nextReaderEndFrame > $this->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot current-source next108 next reader frame is outside the WAL frame range');
        }

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot current-source next108 pages must be integers');
            }

            $current[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = self::safeReaderVisibility($this, $databaseBytes, $pageNumber, $nextReaderEndFrame);
        }

        $limitedPassive = $this->durableCheckpointResult($databaseBytes, 'passive', $currentReaderEndFrame);
        $limitedFull = $this->durableCheckpointResult($databaseBytes, 'full', $currentReaderEndFrame);
        $releasedFull = $this->durableCheckpointResult($databaseBytes, 'full', null);
        $limitedWal = self::parse((string) $limitedPassive['wal_bytes'], $this->header->pageSize, $this->checksumsValidated);

        $limitedCurrent = [];
        $limitedNext = [];
        $releasedDatabase = [];
        foreach ($pageNumbers as $pageNumber) {
            $limitedCurrent[] = self::safeReaderVisibility($limitedWal, (string) $limitedPassive['database_bytes'], $pageNumber, $currentReaderEndFrame);
            $limitedNext[] = self::safeReaderVisibility($limitedWal, (string) $limitedPassive['database_bytes'], $pageNumber, $nextReaderEndFrame);
            $releasedDatabase[] = self::databasePageVisibilityOrError((string) $releasedFull['database_bytes'], $this->header->pageSize, $pageNumber);
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);
        $limitedCurrentImages = self::visibilityImages($limitedCurrent);
        $limitedNextImages = self::visibilityImages($limitedNext);
        $releasedImages = self::visibilityImages($releasedDatabase);

        return [
            'status' => 'reader-checkpoint-snapshot-current-source-next108',
            'source_status' => 'current-source',
            'page_size' => $this->header->pageSize,
            'source_frame_count' => $this->frameCount(),
            'parsed_frame_count' => $source->frameCount(),
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'current_snapshot' => $this->readerSnapshot($databaseBytes, $currentReaderEndFrame),
            'next_snapshot' => $this->readerSnapshot($databaseBytes, $nextReaderEndFrame),
            'limited_passive_checkpoint' => $limitedPassive,
            'limited_full_checkpoint' => $limitedFull,
            'released_full_checkpoint' => $releasedFull,
            'current_reader' => $current,
            'next_reader' => $next,
            'limited_current_reader' => $limitedCurrent,
            'limited_next_reader' => $limitedNext,
            'released_database_reader' => $releasedDatabase,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'limited_current_sources' => self::visibilityColumn($limitedCurrent, 'source'),
            'limited_next_sources' => self::visibilityColumn($limitedNext, 'source'),
            'released_database_sources' => self::visibilityColumn($releasedDatabase, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'limited_current_frame_indexes' => self::visibilityColumn($limitedCurrent, 'frame_index'),
            'limited_next_frame_indexes' => self::visibilityColumn($limitedNext, 'frame_index'),
            'released_database_frame_indexes' => self::visibilityColumn($releasedDatabase, 'frame_index'),
            'current_errors' => self::visibilityErrors($current),
            'next_errors' => self::visibilityErrors($next),
            'limited_current_errors' => self::visibilityErrors($limitedCurrent),
            'limited_next_errors' => self::visibilityErrors($limitedNext),
            'released_database_errors' => self::visibilityErrors($releasedDatabase),
            'current_stable_after_limited_checkpoint' => $currentImages === $limitedCurrentImages,
            'next_stable_after_limited_checkpoint' => $nextImages === $limitedNextImages,
            'next_matches_released_checkpoint_database' => $nextImages === $releasedImages,
            'current_next_images_match' => $currentImages === $nextImages,
            'limited_checkpoint_preserves_wal' => $limitedPassive['wal_action'] === 'preserve_wal',
            'limited_full_reports_busy' => (bool) $limitedFull['busy'],
            'released_full_not_busy' => !(bool) $releasedFull['busy'],
            'released_database_has_all_committed_frames' => $releasedFull['checkpointed_frame_count'] === $releasedFull['total_committable_frame_count'],
            'reader_pin_limits_passive_checkpoint' => $limitedPassive['reason'] === 'reader_limited_passive_checkpoint',
            'reader_pin_blocks_full_checkpoint' => $limitedFull['reason'] === 'reader_blocks_checkpoint_completion',
            'source_digest' => hash('sha256', $walBytes),
            'dependencies' => array_values(array_unique(array_merge(
                $limitedPassive['dependencies'],
                $limitedFull['dependencies'],
                $releasedFull['dependencies'],
                ['sqlite-wal-reader-checkpoint-snapshot-current-source-next108']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array<string,mixed>
     */
    public function restartTruncateReaderPinCurrentSourceNext(
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        array $readMarks
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader-pin current-source next119 requires at least one page number');
        }

        $source = $this->assertCurrentWalBytes86($walBytes);
        if ($source->toBytes() !== $this->toBytes()) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader-pin current-source next119 bytes mismatch');
        }

        $currentReadPlan = $source->readMarkPlan($readMarks);
        $pinnedFrame = $currentReadPlan['checkpoint_pinned_frame'];
        $currentEndFrame = $pinnedFrame ?? $currentReadPlan['recommended_reader_frame'] ?? $source->frameCount();
        if (!is_int($currentEndFrame)) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate reader-pin current-source next119 could not choose a current reader frame');
        }

        $pinnedRestart = $source->durableCheckpointResult($databaseBytes, 'restart', $pinnedFrame);
        $pinnedTruncate = $source->durableCheckpointResult($databaseBytes, 'truncate', $pinnedFrame);
        $releasedRestart = $source->durableCheckpointResult($databaseBytes, 'restart', null);
        $releasedTruncate = $source->durableCheckpointResult($databaseBytes, 'truncate', null);

        $pinnedWal = self::parse((string) $pinnedRestart['wal_bytes'], $source->header->pageSize, $source->checksumsValidated);
        $restartWal = self::parse((string) $releasedRestart['wal_bytes'], $source->header->pageSize, $source->checksumsValidated);
        $truncateWal = $releasedTruncate['wal_bytes'] === ''
            ? null
            : self::parse((string) $releasedTruncate['wal_bytes'], $source->header->pageSize, $source->checksumsValidated);

        $current = [];
        $pinnedNext = [];
        $restartNext = [];
        $truncateNext = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL restart/truncate reader-pin current-source next119 pages must be integers');
            }

            $current[] = self::safeReaderVisibility($source, $databaseBytes, $pageNumber, $currentEndFrame);
            $pinnedNext[] = self::safeReaderVisibility($pinnedWal, (string) $pinnedRestart['database_bytes'], $pageNumber, $source->frameCount());
            $restartNext[] = $restartWal->frameCount() === 0
                ? self::databasePageVisibilityOrError((string) $releasedRestart['database_bytes'], $source->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($restartWal, (string) $releasedRestart['database_bytes'], $pageNumber, $restartWal->frameCount());
            $truncateNext[] = $truncateWal === null || $truncateWal->frameCount() === 0
                ? self::databasePageVisibilityOrError((string) $releasedTruncate['database_bytes'], $source->header->pageSize, $pageNumber)
                : self::safeReaderVisibility($truncateWal, (string) $releasedTruncate['database_bytes'], $pageNumber, $truncateWal->frameCount());
        }

        $releasedReadMarks = array_fill(0, max(1, count($readMarks)), null);
        $releasedReadPlan = $restartWal->readMarkPlan($releasedReadMarks);
        $currentImages = self::visibilityImages($current);
        $restartImages = self::visibilityImages($restartNext);
        $truncateImages = self::visibilityImages($truncateNext);

        return [
            'status' => $pinnedFrame !== null
                && $pinnedRestart['wal_action'] === 'preserve_wal'
                && $pinnedTruncate['wal_action'] === 'preserve_wal'
                && $releasedRestart['wal_action'] === 'restart_wal'
                && $releasedTruncate['wal_action'] === 'truncate_wal'
                    ? 'reader-pin-restart-truncate-current-source-next119'
                    : 'reader-pin-restart-truncate-current-source-next119-incomplete',
            'source_status' => 'current-source',
            'current_reader_end_frame' => $currentEndFrame,
            'checkpoint_pinned_frame' => $pinnedFrame,
            'current_read_marks' => $currentReadPlan,
            'released_read_marks' => $releasedReadPlan,
            'pinned_restart' => $pinnedRestart,
            'pinned_truncate' => $pinnedTruncate,
            'released_restart' => $releasedRestart,
            'released_truncate' => $releasedTruncate,
            'current_reader' => $current,
            'pinned_next_reader' => $pinnedNext,
            'restart_next_reader' => $restartNext,
            'truncate_next_reader' => $truncateNext,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'pinned_next_sources' => self::visibilityColumn($pinnedNext, 'source'),
            'restart_next_sources' => self::visibilityColumn($restartNext, 'source'),
            'truncate_next_sources' => self::visibilityColumn($truncateNext, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'pinned_next_frame_indexes' => self::visibilityColumn($pinnedNext, 'frame_index'),
            'restart_next_frame_indexes' => self::visibilityColumn($restartNext, 'frame_index'),
            'truncate_next_frame_indexes' => self::visibilityColumn($truncateNext, 'frame_index'),
            'pinned_restart_preserves_wal' => $pinnedRestart['wal_action'] === 'preserve_wal',
            'pinned_truncate_preserves_wal' => $pinnedTruncate['wal_action'] === 'preserve_wal',
            'released_restart_uses_new_header' => $releasedRestart['wal_action'] === 'restart_wal' && $restartWal->frameCount() === 0,
            'released_truncate_removes_wal' => $releasedTruncate['wal_action'] === 'truncate_wal' && $releasedTruncate['wal_bytes'] === '',
            'restart_truncate_database_match' => $releasedRestart['database_bytes'] === $releasedTruncate['database_bytes'],
            'current_restart_images_match' => $currentImages === $restartImages,
            'current_truncate_images_match' => $currentImages === $truncateImages,
            'restart_truncate_images_match' => $restartImages === $truncateImages,
            'source_generation' => [
                'wal_bytes_length' => strlen($walBytes),
                'frame_count' => $source->frameCount(),
                'checkpoint_sequence' => $source->header->checkpointSequence,
                'salt' => [$source->header->salt1, $source->header->salt2],
                'checksums_validated' => $source->checksumsValidated,
            ],
            'released_restart_generation' => [
                'wal_bytes_length' => $releasedRestart['wal_bytes_length'],
                'checkpoint_sequence' => is_array($releasedRestart['wal_header']) ? $releasedRestart['wal_header']['checkpoint_sequence'] : null,
                'salt' => is_array($releasedRestart['wal_header']) ? [$releasedRestart['wal_header']['salt1'], $releasedRestart['wal_header']['salt2']] : null,
            ],
            'released_truncate_generation' => [
                'wal_bytes_length' => $releasedTruncate['wal_bytes_length'],
                'checkpoint_sequence' => is_array($releasedTruncate['wal_header']) ? $releasedTruncate['wal_header']['checkpoint_sequence'] : null,
                'salt' => is_array($releasedTruncate['wal_header']) ? [$releasedTruncate['wal_header']['salt1'], $releasedTruncate['wal_header']['salt2']] : null,
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $currentReadPlan['dependencies'] ?? ['wal-index-read-marks'],
                $releasedReadPlan['dependencies'] ?? ['wal-index-read-marks'],
                $pinnedRestart['dependencies'],
                $pinnedTruncate['dependencies'],
                $releasedRestart['dependencies'],
                $releasedTruncate['dependencies'],
                ['sqlite-wal-reader-pin-restart-truncate-current-source-next119']
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string}
     */
    public function readerPageImage(string $databaseBytes, int $pageNumber): array
    {
        return $this->readerSnapshotPageImage($databaseBytes, $pageNumber);
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    public function readerSnapshotPageImage(string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame = null): array
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

        $snapshot = $this->readerSnapshot($databaseBytes, $snapshotEndFrame);
        $databasePageCount = $snapshot['database_page_count'];
        if ($pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader page {$pageNumber} is beyond the committed database size");
        }

        $frame = $this->lastCommittedFrameForPage($pageNumber, $snapshot['commit_frame']);
        if ($frame !== null) {
            return [
                'page_number' => $pageNumber,
                'source' => 'wal',
                'frame_index' => $frame->index,
                'database_offset' => ($pageNumber - 1) * $pageSize,
                'image' => $frame->pageImage,
                'snapshot_end_frame' => $snapshot['end_frame'],
                'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
                'database_page_count' => $databasePageCount,
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
            'snapshot_end_frame' => $snapshot['end_frame'],
            'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
            'database_page_count' => $databasePageCount,
        ];
    }

    /**
     * @return list<array{page_number:int,source:string,frame_index:int|null,database_offset:int}>
     */
    public function readerPageMap(string $databaseBytes): array
    {
        return $this->readerSnapshotPageMap($databaseBytes);
    }

    /**
     * @return list<array{page_number:int,source:string,frame_index:int|null,database_offset:int,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}>
     */
    public function readerSnapshotPageMap(string $databaseBytes, ?int $snapshotEndFrame = null): array
    {
        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader requires a database image aligned to the page size');
        }

        $snapshot = $this->readerSnapshot($databaseBytes, $snapshotEndFrame);
        $databasePageCount = $snapshot['database_page_count'];
        $map = [];
        for ($pageNumber = 1; $pageNumber <= $databasePageCount; $pageNumber++) {
            $entry = $this->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshot['end_frame']);
            unset($entry['image']);
            $map[] = $entry;
        }

        return $map;
    }

    /**
     * @return array{end_frame:int,commit_frame:SQLiteWalFrame|null,database_page_count:int}
     */
    public function readerSnapshot(string $databaseBytes, ?int $snapshotEndFrame = null): array
    {
        if ($snapshotEndFrame !== null && ($snapshotEndFrame < 0 || $snapshotEndFrame > count($this->frames))) {
            throw new \InvalidArgumentException('SQLite WAL snapshot end frame is outside the WAL frame range');
        }

        $endFrame = $snapshotEndFrame ?? count($this->frames);
        $commitFrame = $this->lastCommitFrameAtOrBefore($endFrame);
        $pageSize = $this->header->pageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader snapshot requires a database image aligned to the page size');
        }

        return [
            'end_frame' => $endFrame,
            'commit_frame' => $commitFrame,
            'database_page_count' => $commitFrame?->databasePageCountAfterCommit ?? intdiv(strlen($databaseBytes), $pageSize),
        ];
    }

    private function lastCommittedFrameForPage(int $pageNumber, ?SQLiteWalFrame $commitFrame): ?SQLiteWalFrame
    {
        if ($commitFrame === null) {
            return null;
        }

        for ($index = $commitFrame->index - 1; $index >= 0; $index--) {
            $frame = $this->frames[$index];
            if ($frame->pageNumber === $pageNumber) {
                return $frame;
            }
        }

        return null;
    }

    private function lastCommitFrameAtOrBefore(int $endFrame): ?SQLiteWalFrame
    {
        for ($index = min($endFrame, count($this->frames)) - 1; $index >= 0; $index--) {
            if ($this->frames[$index]->isCommitFrame()) {
                return $this->frames[$index];
            }
        }

        return null;
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function databasePageVisibility(string $databaseBytes, int $walPageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader page numbers are one-based');
        }

        $pageSize = $walPageSize;
        if ($pageSize === 0) {
            $pageSize = SQLiteHeader::parse($databaseBytes)->pageSize;
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader requires a database image aligned to the page size');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader base page {$pageNumber} is missing from the database image");
        }

        $offset = ($pageNumber - 1) * $pageSize;

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $databasePageCount,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeReaderVisibility(self $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\OutOfBoundsException $e) {
            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => $snapshotEndFrame ?? $wal->frameCount(),
                'snapshot_commit_frame' => $wal->lastCommitFrameAtOrBefore($snapshotEndFrame ?? $wal->frameCount())?->index,
                'database_page_count' => $wal->readerSnapshot($databaseBytes, $snapshotEndFrame)['database_page_count'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function databasePageVisibilityOrError(string $databaseBytes, int $walPageSize, int $pageNumber): array
    {
        try {
            return self::databasePageVisibility($databaseBytes, $walPageSize, $pageNumber);
        } catch (\OutOfBoundsException $e) {
            $pageSize = $walPageSize === 0 ? SQLiteHeader::parse($databaseBytes)->pageSize : $walPageSize;

            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function visibilityColumn(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function visibilityErrors(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if (isset($row['error']) && is_string($row['error'])) {
                $errors[] = $row['error'];
            }
        }

        return $errors;
    }

    private function assertCurrentWalBytes86(string $walBytes): self
    {
        $source = self::parse($walBytes, $this->header->pageSize, $this->checksumsValidated);
        if ($source->header->pageSize !== $this->header->pageSize) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source page size mismatch');
        }
        if ($source->header->checkpointSequence !== $this->header->checkpointSequence) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source checkpoint sequence mismatch');
        }
        if ($source->header->salt1 !== $this->header->salt1 || $source->header->salt2 !== $this->header->salt2) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source salt mismatch');
        }
        if ($source->frameCount() !== $this->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL restart/truncate current-source frame count mismatch');
        }

        return $source;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string|null>
     */
    private static function visibilityImages(array $rows): array
    {
        return array_map(static fn (array $row): ?string => is_string($row['image'] ?? null) ? $row['image'] : null, $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function checkpointSourceRows(array $rows, ?string $checkpointDatabaseBytes, int $checkpointedFrameCount, string $phase): array
    {
        $sourceRows = [];
        foreach ($rows as $row) {
            $frameIndex = $row['frame_index'] ?? null;
            $source = (string) ($row['source'] ?? 'missing');
            $checkpointHasPage = $checkpointDatabaseBytes !== null
                && is_int($row['database_offset'] ?? null)
                && is_string($row['image'] ?? null)
                && substr($checkpointDatabaseBytes, $row['database_offset'], strlen($row['image'])) === $row['image'];
            $currentSource = match (true) {
                $source === 'missing' => 'missing',
                $source === 'database' => $phase === 'final' ? 'reset-database' : 'database',
                is_int($frameIndex) && $checkpointHasPage => 'checkpoint-database',
                $source === 'wal' => 'preserved-wal',
                default => $source,
            };

            $sourceRows[] = $row + [
                'phase' => $phase,
                'current_source' => $currentSource,
                'checkpointed_frame_count' => $checkpointedFrameCount,
                'checkpoint_database_has_page' => $checkpointHasPage,
            ];
        }

        return $sourceRows;
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return list<array{phase:string,page_number:int|null,before_source:string|null,after_source:string|null,before_frame:int|null,after_frame:int|null,image_changed:bool,after_checkpoint_database_has_page:bool}>
     */
    private static function sourceTransitionRows(array $before, array $after, string $phase): array
    {
        $rows = [];
        $count = max(count($before), count($after));
        for ($index = 0; $index < $count; $index++) {
            $beforeRow = $before[$index] ?? [];
            $afterRow = $after[$index] ?? [];
            $rows[] = [
                'phase' => $phase,
                'page_number' => isset($beforeRow['page_number']) && is_int($beforeRow['page_number'])
                    ? $beforeRow['page_number']
                    : (isset($afterRow['page_number']) && is_int($afterRow['page_number']) ? $afterRow['page_number'] : null),
                'before_source' => isset($beforeRow['current_source']) && is_string($beforeRow['current_source']) ? $beforeRow['current_source'] : null,
                'after_source' => isset($afterRow['current_source']) && is_string($afterRow['current_source']) ? $afterRow['current_source'] : null,
                'before_frame' => isset($beforeRow['frame_index']) && is_int($beforeRow['frame_index']) ? $beforeRow['frame_index'] : null,
                'after_frame' => isset($afterRow['frame_index']) && is_int($afterRow['frame_index']) ? $afterRow['frame_index'] : null,
                'image_changed' => ($beforeRow['image'] ?? null) !== ($afterRow['image'] ?? null),
                'after_checkpoint_database_has_page' => (bool) ($afterRow['checkpoint_database_has_page'] ?? false),
            ];
        }

        return $rows;
    }

    private static function highestActiveReadMarkFrame(SQLiteShmIndex $shm): ?int
    {
        $frames = [];
        foreach ($shm->readMarks as $mark) {
            if (isset($mark['frame']) && is_int($mark['frame']) && $mark['frame'] > 0) {
                $frames[] = $mark['frame'];
            }
        }

        return $frames === [] ? null : max($frames);
    }

    /**
     * @param list<int|null> $readMarks
     * @param array<string,mixed> $readMarkPlan
     */
    private static function nextReaderSlot(array $readMarks, array $readMarkPlan): ?int
    {
        foreach ($readMarks as $slot => $frame) {
            if ($frame === null) {
                return $slot;
            }
        }

        foreach ($readMarkPlan['read_marks'] as $mark) {
            if (!$mark['pins_checkpoint'] && in_array($mark['slot'], $readMarkPlan['reusable_slots'], true)) {
                return $mark['slot'];
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
