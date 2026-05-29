<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteShmIndex
{
    public const HEADER_BYTES = 48;
    public const HEADER_COPY_BYTES = 96;
    public const CHECKPOINT_INFO_OFFSET = 96;
    public const CHECKPOINT_INFO_BYTES = 40;
    public const READER_COUNT = 5;

    /**
     * @param array<string, int|bool|string|array<int, int>> $header
     * @param list<array{slot:int,frame:int|null,active:bool,valid:bool,stale:bool,read_lock_held:bool,pins_checkpoint:bool,reason:string}> $readMarks
     * @param list<bool> $readLocks
     */
    public function __construct(
        public readonly array $header,
        public readonly array $readMarks,
        public readonly int $backfilledFrameCount,
        public readonly int $backfillAttemptedFrameCount,
        public readonly bool $headersMatch,
        public readonly string $byteOrder,
        public readonly array $readLocks = [],
    ) {
    }

    public static function parse(string $bytes, string $byteOrder = 'little-endian'): self
    {
        if (strlen($bytes) < self::CHECKPOINT_INFO_OFFSET + self::CHECKPOINT_INFO_BYTES) {
            throw new \InvalidArgumentException('SQLite SHM wal-index requires at least 136 bytes');
        }

        $byteOrder = self::byteOrder($byteOrder);
        $first = self::parseHeader(substr($bytes, 0, self::HEADER_BYTES), $byteOrder);
        $second = self::parseHeader(substr($bytes, self::HEADER_BYTES, self::HEADER_BYTES), $byteOrder);
        if ($first['version'] <= 0 || $first['page_size'] < 512 || $first['page_size'] > 65536 || ($first['page_size'] & ($first['page_size'] - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite SHM wal-index header has an invalid version or page size');
        }
        if ($first['mx_frame'] < $first['backfill_hint']) {
            throw new \InvalidArgumentException('SQLite SHM wal-index backfill hint cannot exceed mxFrame');
        }

        $format = $byteOrder === 'little-endian' ? 'V' : 'N';
        /** @var array{backfill:int,read0:int,read1:int,read2:int,read3:int,read4:int,lock0:int,lock1:int,lock2:int,lock3:int,lock4:int,lock5:int,lock6:int,lock7:int,attempted:int,unused:int} $fields */
        $fields = unpack(
            $format . 'backfill/'
            . $format . 'read0/' . $format . 'read1/' . $format . 'read2/' . $format . 'read3/' . $format . 'read4/'
            . 'Clock0/Clock1/Clock2/Clock3/Clock4/Clock5/Clock6/Clock7/'
            . $format . 'attempted/' . $format . 'unused',
            substr($bytes, self::CHECKPOINT_INFO_OFFSET, self::CHECKPOINT_INFO_BYTES)
        );

        if ($fields['backfill'] > $first['mx_frame'] || $fields['attempted'] > $first['mx_frame']) {
            throw new \InvalidArgumentException('SQLite SHM wal-index backfill counters cannot exceed mxFrame');
        }

        $readMarks = [];
        $readLocks = [];
        for ($slot = 0; $slot < self::READER_COUNT; $slot++) {
            $frame = $fields['read' . $slot];
            $readLockHeld = ($fields['lock' . $slot] ?? 0) !== 0;
            $readLocks[] = $readLockHeld;
            $readMarks[] = self::readMark($slot, $frame, $first['mx_frame'], $fields['backfill'], $readLockHeld);
        }

        return new self(
            $first,
            $readMarks,
            $fields['backfill'],
            $fields['attempted'],
            $first === $second,
            $byteOrder,
            $readLocks,
        );
    }

    public static function fromFile(string $path, string $byteOrder = 'little-endian'): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite SHM file: {$path}");
        }

        return self::parse($bytes, $byteOrder);
    }

    /**
     * @return array{status:string,checkpoint_can_finish:bool,reset_blocked:bool,headers_match:bool,mx_frame:int,backfilled_frame_count:int,backfill_attempted_frame_count:int,checkpoint_pinned_frame:int|null,reusable_slots:list<int>,read_locks:list<bool>,dependencies:list<string>,read_marks:list<array{slot:int,frame:int|null,active:bool,valid:bool,stale:bool,read_lock_held:bool,pins_checkpoint:bool,reason:string}>}
     */
    public function checkpointPlan(): array
    {
        return $this->checkpointPlanForReadLocks($this->readLocks, []);
    }

    /**
     * Re-evaluate WAL checkpoint readiness from live VFS SHM lock holders.
     *
     * SQLite's aReadMark[] values live in the -shm file, but the matching
     * read locks are OS/VFS byte locks. This lets callers combine a parsed
     * wal-index image with the current VFS lock table instead of trusting stale
     * lock bytes copied from a fixture.
     *
     * @param array<string,mixed> $shmLocks
     * @return array{status:string,checkpoint_can_finish:bool,reset_blocked:bool,headers_match:bool,mx_frame:int,backfilled_frame_count:int,backfill_attempted_frame_count:int,checkpoint_pinned_frame:int|null,reusable_slots:list<int>,read_locks:list<bool>,dependencies:list<string>,read_marks:list<array{slot:int,frame:int|null,active:bool,valid:bool,stale:bool,read_lock_held:bool,pins_checkpoint:bool,reason:string}>,lock_holders:array<string,list<string>>,lock_source:string}
     */
    public function checkpointPlanWithVfsLocks(array $shmLocks): array
    {
        $readLocks = [];
        $holdersByLock = [];
        for ($slot = 0; $slot < self::READER_COUNT; $slot++) {
            $lock = 'read' . $slot;
            $holders = self::lockHolders($shmLocks[$lock] ?? []);
            $holdersByLock[$lock] = $holders;
            $readLocks[] = $holders !== [];
        }

        return $this->checkpointPlanForReadLocks($readLocks, [
            'lock_holders' => $holdersByLock,
            'lock_source' => 'vfs-shm-lock-table',
        ]);
    }

    /**
     * @param list<bool> $readLocks
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function checkpointPlanForReadLocks(array $readLocks, array $extra): array
    {
        $marks = [];
        for ($slot = 0; $slot < self::READER_COUNT; $slot++) {
            $frame = $this->readMarks[$slot]['frame'] ?? null;
            $marks[] = self::readMarkFromFrame($slot, $frame, $this->header['mx_frame'], $this->backfilledFrameCount, $readLocks[$slot] ?? false);
        }

        $pinned = null;
        $reusable = [];
        foreach ($marks as $mark) {
            if ($mark['pins_checkpoint']) {
                $pinned = $pinned === null ? $mark['frame'] : min($pinned, $mark['frame']);
            }
            if (!$mark['active'] || !$mark['valid'] || !$mark['read_lock_held']) {
                $reusable[] = $mark['slot'];
            }
        }

        return $extra + [
            'status' => $this->headersMatch ? 'ready' : 'stale-header-copy',
            'checkpoint_can_finish' => $pinned === null,
            'reset_blocked' => $pinned !== null,
            'headers_match' => $this->headersMatch,
            'mx_frame' => $this->header['mx_frame'],
            'backfilled_frame_count' => $this->backfilledFrameCount,
            'backfill_attempted_frame_count' => $this->backfillAttemptedFrameCount,
            'checkpoint_pinned_frame' => $pinned,
            'reusable_slots' => $reusable,
            'read_locks' => $readLocks,
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-shm-index', 'wal-index-read-marks', 'wal-index-read-locks', 'checkpoint-backfill-state'],
                $extra === [] ? [] : ['vfs-wal-shm-lock-byte-current-source']
            ))),
            'read_marks' => $marks,
        ];
    }

    /**
     * @return array{status:string,reason:string,wal_mx_frame:int,last_commit_frame:int|null,headers_match:bool,salt_matches_wal:bool,backfilled_frame_count:int,next_read_marks:list<int|null>,next_checkpoint_plan:array<string,mixed>,preserved_slots:list<int>,discarded_slots:list<int>,next_reader_slot:int|null,next_reader_frame:int|null,current_reader_frames:list<int>,dependencies:list<string>}
     */
    public function recoverReadMarksFromWal(SQLiteWal $wal): array
    {
        $walMxFrame = $wal->frameCount();
        $lastCommitFrame = $wal->lastCommitFrame()?->index;
        $saltMatchesWal = ($this->header['salt'][0] ?? null) === $wal->header->salt1
            && ($this->header['salt'][1] ?? null) === $wal->header->salt2;
        $canPreserveReaders = $this->headersMatch && $saltMatchesWal && $lastCommitFrame !== null;
        $preservedSlots = [];
        $discardedSlots = [];
        $nextReadMarks = [];
        $currentReaderFrames = [];

        foreach ($this->readMarks as $mark) {
            $frame = $mark['frame'];
            $preserve = $canPreserveReaders
                && $mark['read_lock_held']
                && $frame !== null
                && $frame > 0
                && $frame <= $lastCommitFrame;

            if ($preserve) {
                $nextReadMarks[] = $frame;
                $preservedSlots[] = $mark['slot'];
                $currentReaderFrames[] = $frame;
            } else {
                $nextReadMarks[] = null;
                if ($frame !== null || $mark['read_lock_held']) {
                    $discardedSlots[] = $mark['slot'];
                }
            }
        }

        if ($nextReadMarks === []) {
            $nextReadMarks = array_fill(0, self::READER_COUNT, null);
        }

        if ($preservedSlots === []) {
            $nextReadMarks[0] = 0;
        }

        $nextCheckpointPlan = $wal->readMarkPlan($nextReadMarks);
        $nextReaderSlot = $nextCheckpointPlan['recommended_reader_slot'];
        $nextReaderFrame = $nextReaderSlot === null ? null : ($lastCommitFrame ?? 0);
        $reason = 'read_marks_recovered_from_matching_wal';
        if (!$this->headersMatch) {
            $reason = 'stale_shm_header_copy_rebuilt_from_wal';
        } elseif (!$saltMatchesWal) {
            $reason = 'shm_salt_mismatch_rebuilt_from_wal';
        } elseif ($lastCommitFrame === null) {
            $reason = 'wal_has_no_committed_frames';
        } elseif ($preservedSlots === []) {
            $reason = 'no_locked_read_marks_to_preserve';
        }

        return [
            'status' => $preservedSlots === [] ? 'rebuilt' : 'recovered-with-readers',
            'reason' => $reason,
            'wal_mx_frame' => $walMxFrame,
            'last_commit_frame' => $lastCommitFrame,
            'headers_match' => $this->headersMatch,
            'salt_matches_wal' => $saltMatchesWal,
            'backfilled_frame_count' => min($this->backfilledFrameCount, $lastCommitFrame ?? 0),
            'next_read_marks' => $nextReadMarks,
            'next_checkpoint_plan' => $nextCheckpointPlan,
            'preserved_slots' => $preservedSlots,
            'discarded_slots' => $discardedSlots,
            'next_reader_slot' => $nextReaderSlot,
            'next_reader_frame' => $nextReaderFrame,
            'current_reader_frames' => $currentReaderFrames,
            'dependencies' => [
                'sqlite-shm-index',
                'wal-index-read-marks',
                'sqlite-wal-frame-salt',
                'wal-shm-readmark-recovery',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'byte_order' => $this->byteOrder,
            'headers_match' => $this->headersMatch,
            'header' => $this->header,
            'backfilled_frame_count' => $this->backfilledFrameCount,
            'backfill_attempted_frame_count' => $this->backfillAttemptedFrameCount,
            'read_locks' => $this->readLocks,
            'read_marks' => $this->readMarks,
            'checkpoint_plan' => $this->checkpointPlan(),
        ];
    }

    /**
     * @return array<string, int|bool|string|array<int, int>>
     */
    private static function parseHeader(string $bytes, string $byteOrder): array
    {
        $format = $byteOrder === 'little-endian' ? 'V' : 'N';
        /** @var array{version:int,unused:int,change:int,pageSize:int,mxFrame:int,nPage:int,frameCksum0:int,frameCksum1:int,salt0:int,salt1:int,cksum0:int,cksum1:int} $fields */
        $fields = unpack(
            $format . 'version/' . $format . 'unused/' . $format . 'change/'
            . $format . 'pageSize/' . $format . 'mxFrame/' . $format . 'nPage/'
            . $format . 'frameCksum0/' . $format . 'frameCksum1/'
            . $format . 'salt0/' . $format . 'salt1/'
            . $format . 'cksum0/' . $format . 'cksum1',
            $bytes
        );

        return [
            'version' => $fields['version'],
            'change_counter' => $fields['change'],
            'page_size' => $fields['pageSize'] & 0xffff,
            'big_endian_checksums' => (($fields['pageSize'] >> 16) & 0xff) === 1,
            'initialized' => (($fields['pageSize'] >> 24) & 0xff) === 1,
            'mx_frame' => $fields['mxFrame'],
            'database_page_count' => $fields['nPage'],
            'backfill_hint' => $fields['unused'],
            'frame_checksum' => [$fields['frameCksum0'], $fields['frameCksum1']],
            'salt' => [$fields['salt0'], $fields['salt1']],
            'checksum' => [$fields['cksum0'], $fields['cksum1']],
        ];
    }

    /**
     * @return array{slot:int,frame:int|null,active:bool,valid:bool,stale:bool,read_lock_held:bool,pins_checkpoint:bool,reason:string}
     */
    private static function readMark(int $slot, int $rawFrame, int $mxFrame, int $backfilled, bool $readLockHeld): array
    {
        $frame = $rawFrame === 0xffffffff ? null : $rawFrame;
        return self::readMarkFromFrame($slot, $frame, $mxFrame, $backfilled, $readLockHeld);
    }

    /**
     * @return array{slot:int,frame:int|null,active:bool,valid:bool,stale:bool,read_lock_held:bool,pins_checkpoint:bool,reason:string}
     */
    private static function readMarkFromFrame(int $slot, ?int $frame, int $mxFrame, int $backfilled, bool $readLockHeld): array
    {
        $active = $frame !== null;
        $valid = $frame === null || $frame <= $mxFrame;
        $stale = $valid && $frame !== null && $frame < $mxFrame;
        $pins = $readLockHeld && $valid && $frame !== null && $frame > $backfilled && $frame < $mxFrame;
        $reason = match (true) {
            $frame === null => 'unused_slot',
            !$valid => 'beyond_wal_mx_frame',
            $frame === 0 => 'database_only_reader',
            !$readLockHeld => 'read_mark_without_read_lock',
            $pins => 'reader_pins_checkpoint_backfill',
            $frame === $mxFrame => 'pins_latest_commit',
            $stale => 'stale_reader_snapshot',
            default => 'reader_mark_active',
        };

        return [
            'slot' => $slot,
            'frame' => $frame,
            'active' => $active,
            'valid' => $valid,
            'stale' => $stale,
            'read_lock_held' => $readLockHeld,
            'pins_checkpoint' => $pins,
            'reason' => $reason,
        ];
    }

    /**
     * @param mixed $holders
     * @return list<string>
     */
    private static function lockHolders(mixed $holders): array
    {
        if (is_string($holders) && trim($holders) !== '') {
            return [trim($holders)];
        }
        if (!is_array($holders)) {
            return [];
        }

        $out = [];
        foreach ($holders as $key => $value) {
            if (is_string($key) && $key !== '' && $value !== false && $value !== null) {
                $out[] = $key;
                continue;
            }
            if (is_string($value) && $value !== '') {
                $out[] = $value;
            }
        }

        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private static function byteOrder(string $byteOrder): string
    {
        $byteOrder = strtolower(trim($byteOrder));
        if (!in_array($byteOrder, ['little-endian', 'big-endian'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite SHM byte order: {$byteOrder}");
        }

        return $byteOrder;
    }
}
