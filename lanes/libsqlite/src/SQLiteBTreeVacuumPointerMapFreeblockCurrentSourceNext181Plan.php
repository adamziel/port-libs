<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Plan
{
    /**
     * @param list<array<string, mixed>> $snapshotRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Plan $basePlan,
        private readonly array $snapshotRows,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Plan $basePlan): self
    {
        $rows = self::buildSnapshotRows($basePlan);
        $errors = self::snapshotErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next181 snapshot failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function snapshotRows(): array
    {
        return $this->snapshotRows;
    }

    /**
     * @return list<string>
     */
    public function snapshotErrors(): array
    {
        return self::snapshotErrorsForRows($this->snapshotRows);
    }

    /**
     * @return list<int>
     */
    public function replayablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['next_reader_admitted'] === true);
    }

    /**
     * @return list<int>
     */
    public function quarantinedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['next_reader_admitted'] === false);
    }

    /**
     * @return list<int>
     */
    public function leafFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['snapshot_kind'] === 'leaf-freeblock-current-source');
    }

    /**
     * @return list<int>
     */
    public function overflowPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => str_starts_with((string) $row['snapshot_kind'], 'overflow-'));
    }

    /**
     * @return list<int>
     */
    public function pointerMapReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['pointer_map_receipt_carried'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next181-ready',
            'replayable_pages' => $this->replayablePages(),
            'quarantined_pages' => $this->quarantinedPages(),
            'leaf_freeblock_pages' => $this->leafFreeblockPages(),
            'overflow_pages' => $this->overflowPages(),
            'pointer_map_receipt_pages' => $this->pointerMapReceiptPages(),
            'next_reader_token' => self::signature(array_map(
                static fn (array $row): string => $row['next_reader_admitted'] === true
                    ? $row['slot'] . ':' . $row['page_number'] . ':' . $row['snapshot_kind'] . ':' . ($row['final_next_page'] ?? 'null')
                    : 'blocked:' . $row['page_number'] . ':' . $row['quarantine_reason'],
                $this->snapshotRows,
            )),
            'receipt_chain_token' => self::signature(array_column($this->snapshotRows, 'receipt_chain_key')),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next178',
                'sqlite-current-source-next181',
            ],
            'dependency_closure' => 'no new support component needed; next181 reuses next178 publication receipts, leaf freeblock receipts, overflow next-pointer receipts, and auto-vacuum pointer-map receipt metadata',
            'non_overlap' => 'adds next-reader snapshot admission after next178 publication receipts; does not repeat next178 publication, next175 admission fencing, next173 transition rows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next181',
            'snapshot_summary' => $this->snapshotSummary(),
            'snapshot_errors' => $this->snapshotErrors(),
            'snapshot_rows' => $this->snapshotRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->snapshotRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSnapshotRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Plan $basePlan): array
    {
        $rows = [];
        $slot = 0;
        foreach ($basePlan->publicationRows() as $row) {
            $published = $row['publish_to_next_current_source'] === true;
            if ($published) {
                $slot++;
            }

            $snapshotKind = self::snapshotKind($row);
            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'slot' => $published ? $slot : null,
                'status' => $row['status'],
                'snapshot_kind' => $snapshotKind,
                'next_reader_admitted' => $published,
                'quarantine_reason' => $published ? null : 'truncated-tail-fenced-from-next-reader',
                'publication_state' => $row['publication_state'],
                'receipt_kind' => $row['receipt_kind'],
                'freeblock_receipt_carried' => $row['freeblock_receipt_required'] === true,
                'pointer_map_receipt_carried' => $row['pointer_map_receipt_required'] === true,
                'next_pointer_receipt_carried' => $row['next_pointer_receipt_required'] === true,
                'source_materialized' => $row['source_materialized'],
                'final_materialized' => $row['final_materialized'],
                'final_next_page' => $row['final_next_page'],
                'final_pointer_map_type' => $row['final_pointer_map_type'],
                'final_pointer_map_parent' => $row['final_pointer_map_parent'],
                'stable_leaf_hash_preserved' => $row['stable_leaf_hash_preserved'],
                'stable_leaf_freeblocks_preserved' => $row['stable_leaf_freeblocks_preserved'],
                'receipt_chain_key' => self::signature([
                    (int) $row['page_number'],
                    $snapshotKind,
                    $published ? '1' : '0',
                    $row['receipt_signature'],
                    $row['final_next_page'] ?? 'null',
                    $row['final_pointer_map_type'] ?? 'null',
                    $row['final_pointer_map_parent'] ?? 'null',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function snapshotKind(array $row): string
    {
        if ($row['publish_to_next_current_source'] !== true) {
            return 'quarantined-truncated-tail';
        }
        if ($row['status'] === 'stable-leaf-freeblock') {
            return 'leaf-freeblock-current-source';
        }
        if ($row['receipt_kind'] === 'overflow-tail-rewrite-receipt') {
            return 'overflow-tail-current-source';
        }

        return 'overflow-current-source';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function snapshotErrorsForRows(array $rows): array
    {
        $errors = [];
        $expectedSlot = 0;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['next_reader_admitted'] === true) {
                $expectedSlot++;
                if ($row['slot'] !== $expectedSlot) {
                    $errors[] = "published page {$pageNumber} has a non-contiguous next-reader slot";
                }
                if ($row['final_materialized'] !== true) {
                    $errors[] = "published page {$pageNumber} is not materialized for the next reader";
                }
            } else {
                if ($row['slot'] !== null || $row['quarantine_reason'] !== 'truncated-tail-fenced-from-next-reader') {
                    $errors[] = "quarantined page {$pageNumber} has an invalid next-reader fence";
                }
                if ($row['final_materialized'] !== false || $row['snapshot_kind'] !== 'quarantined-truncated-tail') {
                    $errors[] = "quarantined page {$pageNumber} survived into the next-reader snapshot";
                }
            }

            if ($row['snapshot_kind'] === 'leaf-freeblock-current-source') {
                if ($row['freeblock_receipt_carried'] !== true) {
                    $errors[] = "leaf page {$pageNumber} did not carry its freeblock receipt";
                }
                if ($row['stable_leaf_hash_preserved'] !== true || $row['stable_leaf_freeblocks_preserved'] !== true) {
                    $errors[] = "leaf page {$pageNumber} lost its post-delete freeblock image";
                }
            }

            if (str_starts_with((string) $row['snapshot_kind'], 'overflow-')) {
                if (!in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)) {
                    $errors[] = "overflow page {$pageNumber} has no overflow pointer-map receipt";
                }
                if ($row['snapshot_kind'] === 'overflow-tail-current-source' && $row['next_pointer_receipt_carried'] !== true) {
                    $errors[] = "overflow tail page {$pageNumber} did not carry the rewritten next-pointer receipt";
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $items
     */
    private static function signature(array $items): string
    {
        return hash('sha256', implode('|', array_map(
            static fn (mixed $item): string => is_bool($item) ? ($item ? '1' : '0') : (string) $item,
            $items,
        )));
    }
}
