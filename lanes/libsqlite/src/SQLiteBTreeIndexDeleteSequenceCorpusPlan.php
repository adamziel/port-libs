<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeIndexDeleteSequenceCorpusPlan
{
    /**
     * @param list<list<mixed>> $records
     * @param list<list<mixed>> $deletions
     * @return array{
     *   page:string,
     *   initial_records:list<list<mixed>>,
     *   remaining_records:list<list<mixed>>,
     *   deleted_records:list<list<mixed>>,
     *   snapshots:list<array{label:string,cell_count:int,records:list<list<mixed>>,freeblock_count:int,free_space_bytes:int,integrity_status:string}>,
     *   non_overlap:string,
     *   upstream_source:string,
     *   dependency_closure:string
     * }
     */
    public static function applyIndexDeleteSequence(
        array $records,
        array $deletions,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
    ): array {
        if ($records === []) {
            throw new \InvalidArgumentException('SQLite b-tree index dynamic corpus requires at least one source record');
        }
        if ($deletions === []) {
            throw new \InvalidArgumentException('SQLite b-tree index dynamic corpus requires at least one deletion');
        }

        $page = SQLiteIndexLeafPage::assemble(
            array_map(
                static fn (array $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record)),
                $records,
            ),
            $pageSize,
            $headerOffset,
            usableSize: $usableSize,
        );

        $snapshots = [self::snapshot('initial', $page, $pageSize, $headerOffset, $usableSize, $textEncoding)];
        $deleted = [];
        foreach ($deletions as $index => $recordValues) {
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite b-tree index dynamic corpus deletions must be record value lists');
            }

            $page = SQLiteIndexLeafPage::deleteCellByRecordValues(
                $page,
                $recordValues,
                $pageSize,
                $headerOffset,
                $usableSize,
                $textEncoding,
                $secureDelete,
            );
            $deleted[] = array_values($recordValues);
            $snapshots[] = self::snapshot('delete-' . ($index + 1), $page, $pageSize, $headerOffset, $usableSize, $textEncoding);
        }

        return [
            'page' => $page,
            'initial_records' => array_values($records),
            'remaining_records' => $snapshots[array_key_last($snapshots)]['records'],
            'deleted_records' => $deleted,
            'snapshots' => $snapshots,
            'non_overlap' => 'ports real upstream index.test dynamic duplicate-key delete behavior; it does not repeat page relocation, root collapse, overflow freelist release, or index-interior merge clusters',
            'upstream_source' => 'SQLite upstream test/index.test scenarios index-10.4 through index-10.8',
            'dependency_closure' => 'no new support component needed; reuses SQLiteIndexLeafPage deletion, SQLiteIndexCell parsing, SQLiteRecord encoding, and B-tree page-header freeblock integrity',
        ];
    }

    /**
     * @return array{label:string,cell_count:int,records:list<list<mixed>>,freeblock_count:int,free_space_bytes:int,integrity_status:string}
     */
    private static function snapshot(
        string $label,
        string $page,
        int $pageSize,
        int $headerOffset,
        ?int $usableSize,
        int $textEncoding,
    ): array {
        $usableSize ??= $pageSize;
        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);

        return [
            'label' => $label,
            'cell_count' => $header->cellCount,
            'records' => array_map(
                static fn (SQLiteIndexCell $cell): array => $cell->record($textEncoding)->values,
                SQLiteIndexCell::parsePageCells($page, $header, $usableSize),
            ),
            'freeblock_count' => count($header->freeblocks($page, $usableSize)),
            'free_space_bytes' => $header->freeSpaceBytes($page),
            'integrity_status' => $header->freeblockIntegrityReport($page, $usableSize)['status'],
        ];
    }
}
