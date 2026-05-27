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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string|null>
     */
    private static function visibilityImages(array $rows): array
    {
        return array_map(static fn (array $row): ?string => is_string($row['image'] ?? null) ? $row['image'] : null, $rows);
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
