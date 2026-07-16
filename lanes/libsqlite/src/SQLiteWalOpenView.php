<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalOpenView
{
    public function __construct(
        private readonly string $databaseBytes,
        public readonly SQLiteHeader $databaseHeader,
        public readonly ?SQLiteWal $wal = null,
    ) {
        if (strlen($databaseBytes) < $databaseHeader->pageSize || strlen($databaseBytes) % $databaseHeader->pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL open view requires a page-size-aligned database image');
        }
        if ($wal !== null && $wal->header->pageSize !== 0 && $wal->header->pageSize !== $databaseHeader->pageSize) {
            throw new \InvalidArgumentException('SQLite WAL page size does not match the database header page size');
        }
    }

    public static function fromBytes(string $databaseBytes, ?string $walBytes = null, bool $validateWalChecksums = false): self
    {
        $header = SQLiteHeader::parse($databaseBytes);
        $wal = $walBytes === null || $walBytes === ''
            ? null
            : SQLiteWal::parse($walBytes, $header->pageSize, $validateWalChecksums);

        return new self($databaseBytes, $header, $wal);
    }

    public static function fromFiles(string $databasePath, ?string $walPath = null, bool $validateWalChecksums = false): self
    {
        $databaseBytes = @file_get_contents($databasePath);
        if ($databaseBytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite database file: {$databasePath}");
        }

        $resolvedWalPath = $walPath ?? ($databasePath . '-wal');
        $walBytes = null;
        if (is_file($resolvedWalPath)) {
            $walBytes = @file_get_contents($resolvedWalPath);
            if ($walBytes === false) {
                throw new \InvalidArgumentException("Unable to read SQLite WAL file: {$resolvedWalPath}");
            }
        }

        return self::fromBytes($databaseBytes, $walBytes, $validateWalChecksums);
    }

    public function pageSize(): int
    {
        return $this->databaseHeader->pageSize;
    }

    public function hasWal(): bool
    {
        return $this->wal !== null;
    }

    public function baseDatabasePageCount(): int
    {
        return intdiv(strlen($this->databaseBytes), $this->databaseHeader->pageSize);
    }

    public function snapshot(?int $snapshotEndFrame = null): array
    {
        if ($this->wal === null) {
            if ($snapshotEndFrame !== null && $snapshotEndFrame !== 0) {
                throw new \InvalidArgumentException('SQLite database-only open view cannot read a WAL snapshot frame');
            }

            return [
                'end_frame' => 0,
                'commit_frame' => null,
                'database_page_count' => $this->baseDatabasePageCount(),
            ];
        }

        return $this->wal->readerSnapshot($this->databaseBytes, $snapshotEndFrame);
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    public function pageImage(int $pageNumber, ?int $snapshotEndFrame = null): array
    {
        if ($this->wal !== null) {
            return $this->wal->readerSnapshotPageImage($this->databaseBytes, $pageNumber, $snapshotEndFrame);
        }
        if ($snapshotEndFrame !== null && $snapshotEndFrame !== 0) {
            throw new \InvalidArgumentException('SQLite database-only open view cannot read a WAL snapshot frame');
        }
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($pageNumber > $this->baseDatabasePageCount()) {
            throw new \OutOfBoundsException("SQLite database page {$pageNumber} is beyond the database image");
        }

        $offset = ($pageNumber - 1) * $this->databaseHeader->pageSize;

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($this->databaseBytes, $offset, $this->databaseHeader->pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $this->baseDatabasePageCount(),
        ];
    }

    /**
     * @return list<array{page_number:int,source:string,frame_index:int|null,database_offset:int,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}>
     */
    public function pageMap(?int $snapshotEndFrame = null): array
    {
        $snapshot = $this->snapshot($snapshotEndFrame);
        $map = [];
        for ($pageNumber = 1; $pageNumber <= $snapshot['database_page_count']; $pageNumber++) {
            $entry = $this->pageImage($pageNumber, $snapshot['end_frame']);
            unset($entry['image']);
            $map[] = $entry;
        }

        return $map;
    }

    public function databaseImage(?int $snapshotEndFrame = null): string
    {
        $snapshot = $this->snapshot($snapshotEndFrame);
        $bytes = '';
        for ($pageNumber = 1; $pageNumber <= $snapshot['database_page_count']; $pageNumber++) {
            $bytes .= $this->pageImage($pageNumber, $snapshot['end_frame'])['image'];
        }

        return $bytes;
    }

    /**
     * @return array{mode:string,busy:bool,reason:string,reader_end_frame:int|null,database_bytes:string,database_page_count:int,final_database_bytes:int,checkpointed_frame_count:int,total_committable_frame_count:int,remaining_committed_frame_count:int,uncommitted_frame_count:int,can_reset:bool,can_truncate:bool,wal_action:string,next_wal_header_salt:array{0:int,1:int}}
     */
    public function checkpointResult(string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        if ($this->wal === null) {
            $mode = strtolower($mode);
            if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
                throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint mode: {$mode}");
            }
            if ($readerEndFrame !== null && $readerEndFrame < 0) {
                throw new \InvalidArgumentException('SQLite WAL reader end frame must be non-negative');
            }

            return [
                'mode' => $mode,
                'busy' => false,
                'reason' => 'wal_file_missing',
                'reader_end_frame' => $readerEndFrame,
                'database_bytes' => $this->databaseBytes,
                'database_page_count' => $this->baseDatabasePageCount(),
                'final_database_bytes' => strlen($this->databaseBytes),
                'checkpointed_frame_count' => 0,
                'total_committable_frame_count' => 0,
                'remaining_committed_frame_count' => 0,
                'uncommitted_frame_count' => 0,
                'can_reset' => false,
                'can_truncate' => false,
                'wal_action' => 'no_wal_file',
                'next_wal_header_salt' => [0, 0],
            ];
        }

        return $this->wal->checkpointModeResult($this->databaseBytes, $mode, $readerEndFrame);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?int $snapshotEndFrame = null): array
    {
        $snapshot = $this->snapshot($snapshotEndFrame);

        return [
            'page_size' => $this->databaseHeader->pageSize,
            'base_database_page_count' => $this->baseDatabasePageCount(),
            'has_wal' => $this->wal !== null,
            'snapshot_end_frame' => $snapshot['end_frame'],
            'snapshot_commit_frame' => $snapshot['commit_frame'] instanceof SQLiteWalFrame ? $snapshot['commit_frame']->index : null,
            'snapshot_database_page_count' => $snapshot['database_page_count'],
            'wal_frame_count' => $this->wal?->frameCount() ?? 0,
            'wal_uncommitted_frame_count' => $this->wal?->uncommittedFrameCount() ?? 0,
            'page_map' => $this->pageMap($snapshot['end_frame']),
        ];
    }
}
