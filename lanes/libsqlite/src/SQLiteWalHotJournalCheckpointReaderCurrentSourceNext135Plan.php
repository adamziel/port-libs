<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalCheckpointReaderCurrentSourceNext135Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $nextWalBytes,
        array $pageNumbers,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($nextWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next135 requires next WAL bytes');
        }

        $base = SQLiteWalCheckpointReaderHotJournalCurrentSourceNext132Plan::plan(
            $databasePath,
            $databaseBytes,
            $journalBytes,
            $currentWal,
            $currentWalBytes,
            $currentWalBytes,
            $pageNumbers,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $pageSize = (int) $base['page_size'];
        $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
        $hotDatabaseBytes = (string) ($base['restart_plan']['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '' && isset($base['base_plan']['hot_journal']['database_bytes'])) {
            $hotDatabaseBytes = (string) $base['base_plan']['hot_journal']['database_bytes'];
        }
        if ($hotDatabaseBytes === '' && (bool) $base['hot_recovered'] === false) {
            $hotDatabaseBytes = $databaseBytes;
        }
        if ($hotDatabaseBytes === '') {
            throw new \UnexpectedValueException('SQLite WAL hot-journal checkpoint reader current-source next135 requires recovered hot-journal database bytes');
        }

        $readerEndFrame = (int) $base['reader_end_frame'];
        $currentRows = [];
        $nextRows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next135 pages must be one-based integers');
            }

            $currentRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
            $nextRows[] = $nextWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $nextWal->frameCount());
        }

        $currentSource = self::walSource($currentWal, $currentWalBytes);
        $nextSource = self::walSource($nextWal, $nextWalBytes);
        $nextSourceSeparated = $currentSource['sha256'] !== $nextSource['sha256']
            && ($nextSource['checkpoint_sequence'] > $currentSource['checkpoint_sequence']
                || $nextSource['salt_1'] !== $currentSource['salt_1']
                || $nextSource['salt_2'] !== $currentSource['salt_2']);

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $currentImage = (string) $currentRows[$index]['image'];
            $nextImage = (string) $nextRows[$index]['image'];
            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $currentRows[$index]['source'],
                'next_source' => $nextRows[$index]['source'],
                'current_frame' => $currentRows[$index]['frame_index'],
                'next_frame' => $nextRows[$index]['frame_index'],
                'current_label' => self::label($currentImage),
                'next_label' => self::label($nextImage),
                'next_generation_changed_image' => $currentImage !== $nextImage,
                'source_transition' => $currentRows[$index]['source'] . '>checkpoint-reader>' . $nextRows[$index]['source'],
            ];
        }

        $changedPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => (bool) $row['next_generation_changed_image'])
        ));
        $status = (bool) $base['checkpoint_allowed'] && $nextSourceSeparated
            ? 'wal-hot-journal-checkpoint-reader-current-source-next135'
            : 'wal-hot-journal-checkpoint-reader-current-source-blocked-next135';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-checkpoint-reader-current-source-next135'
                ? 'current_reader_source_survives_hot_journal_checkpoint_before_next_wal_generation'
                : 'current_reader_source_not_ready_for_next_wal_generation',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $base['hot_recovered'],
            'checkpoint_allowed' => (bool) $base['checkpoint_allowed'],
            'reader_source_matches_current' => (bool) $base['reader_source_matches_current'],
            'next_source_separated' => $nextSourceSeparated,
            'current_wal_source' => $currentSource,
            'next_wal_source' => $nextSource,
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'next_wal_sha256' => hash('sha256', $nextWalBytes),
            'current_frame_count' => $currentWal->frameCount(),
            'next_frame_count' => $nextWal->frameCount(),
            'current_sources' => self::column($currentRows, 'source'),
            'next_sources' => self::column($nextRows, 'source'),
            'current_frame_indexes' => self::column($currentRows, 'frame_index'),
            'next_frame_indexes' => self::column($nextRows, 'frame_index'),
            'next_changed_page_numbers' => $changedPages,
            'next_changed_page_count' => count($changedPages),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'operation_reasons' => array_merge($base['operation_reasons'], [
                'pin_current_reader_source_through_hot_journal_checkpoint_next135',
                'open_next_writer_on_separate_wal_generation_next135',
            ]),
            'base_plan' => $base,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'],
                [
                    'sqlite-wal-hot-journal-checkpoint-reader-current-source-next135',
                    'sqlite-wal-checkpoint-reader-hot-journal-current-source-next132',
                    'sqlite-wal-next-generation-source-separation',
                ]
            ))),
        ];
    }

    /**
     * @return array{magic:int,version:int,page_size:int,checkpoint_sequence:int,salt_1:int,salt_2:int,frame_count:int,sha256:string}
     */
    private static function walSource(SQLiteWal $wal, string $walBytes): array
    {
        return [
            'magic' => $wal->header->magic,
            'version' => $wal->header->formatVersion,
            'page_size' => $wal->header->pageSize,
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt_1' => $wal->header->salt1,
            'salt_2' => $wal->header->salt2,
            'frame_count' => $wal->frameCount(),
            'sha256' => hash('sha256', $walBytes),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
