<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext178Plan
{
    /**
     * @param list<array<string, mixed>> $publicationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $publicationRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext175(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $rows = self::buildPublicationRows($basePlan);
        $errors = self::publicationErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next178 publication failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicationRows(): array
    {
        return $this->publicationRows;
    }

    /**
     * @return list<string>
     */
    public function publicationErrors(): array
    {
        return self::publicationErrorsForRows($this->publicationRows);
    }

    /**
     * @return list<int>
     */
    public function publishedCurrentSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_to_next_current_source'] === true);
    }

    /**
     * @return list<int>
     */
    public function blockedCurrentSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_to_next_current_source'] === false);
    }

    /**
     * @return list<int>
     */
    public function freeblockReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freeblock_receipt_required'] === true);
    }

    /**
     * @return list<int>
     */
    public function pointerMapReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['pointer_map_receipt_required'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicationSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next178-ready',
            'published_current_source_pages' => $this->publishedCurrentSourcePages(),
            'blocked_current_source_pages' => $this->blockedCurrentSourcePages(),
            'freeblock_receipt_pages' => $this->freeblockReceiptPages(),
            'pointer_map_receipt_pages' => $this->pointerMapReceiptPages(),
            'publication_signature' => self::signature(array_map(
                static fn (array $row): string => $row['page_number'] . ':' . $row['publication_state'] . ':' . $row['receipt_kind'],
                $this->publicationRows,
            )),
            'current_source_token' => self::signature(array_map(
                static fn (array $row): string => $row['publish_to_next_current_source'] === true
                    ? $row['page_number'] . ':' . $row['final_pointer_map_type'] . ':' . ($row['final_next_page'] ?? 'null')
                    : $row['page_number'] . ':blocked',
                $this->publicationRows,
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next175',
                'sqlite-current-source-next178',
            ],
            'dependency_closure' => 'no new support component needed; next178 reuses native next175 current-source admission rows, secure-delete leaf freeblock receipts, overflow next-pointer fencing, and auto-vacuum pointer-map metadata',
            'non_overlap' => 'adds final publication receipts for the next current source after next175 admission; does not repeat next175 admission fencing, next173 transition rows, next166 write admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next178',
            'publication_summary' => $this->publicationSummary(),
            'publication_errors' => $this->publicationErrors(),
            'publication_rows' => $this->publicationRows,
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
            array_filter($this->publicationRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPublicationRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        foreach ($basePlan->admissionRows() as $index => $row) {
            $published = $row['admission'] === 'admit-final-page';
            $receiptKind = self::receiptKind($row);
            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'sequence' => $index + 1,
                'status' => $row['status'],
                'publication_state' => $published ? 'published-current-source' : 'blocked-truncated-tail',
                'publish_to_next_current_source' => $published,
                'block_reason' => $published ? null : 'truncated-tail-page-not-materialized',
                'receipt_kind' => $receiptKind,
                'freeblock_receipt_required' => $receiptKind === 'leaf-freeblock-receipt',
                'pointer_map_receipt_required' => $row['pointer_map_rewrite_required'] === true,
                'next_pointer_receipt_required' => $row['next_pointer_rewritten'] === true,
                'source_materialized' => $row['source_materialized'],
                'final_materialized' => $row['final_materialized'],
                'source_next_page' => $row['source_next_page'],
                'final_next_page' => $row['final_next_page'],
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'final_pointer_map_type' => $row['final_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'final_pointer_map_parent' => $row['final_pointer_map_parent'],
                'stable_leaf_hash_preserved' => $row['stable_leaf_hash_preserved'],
                'stable_leaf_freeblocks_preserved' => $row['stable_leaf_freeblocks_preserved'],
                'receipt_signature' => self::signature([
                    (int) $row['page_number'],
                    $published ? '1' : '0',
                    $receiptKind,
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
    private static function receiptKind(array $row): string
    {
        if ($row['status'] === 'stable-leaf-freeblock') {
            return 'leaf-freeblock-receipt';
        }
        if ($row['status'] === 'replacement-overflow') {
            return $row['next_pointer_rewritten'] === true ? 'overflow-tail-rewrite-receipt' : 'overflow-chain-receipt';
        }

        return 'truncated-tail-fence-receipt';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function publicationErrorsForRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if ($row['publish_to_next_current_source'] === true && $row['final_materialized'] !== true) {
                $errors[] = "published page {$row['page_number']} is not materialized";
            }
            if ($row['publish_to_next_current_source'] === false && $row['block_reason'] !== 'truncated-tail-page-not-materialized') {
                $errors[] = "blocked page {$row['page_number']} has no truncated-tail block reason";
            }
            if ($row['status'] === 'stable-leaf-freeblock') {
                if ($row['freeblock_receipt_required'] !== true) {
                    $errors[] = "stable leaf page {$row['page_number']} has no freeblock receipt";
                }
                if ($row['stable_leaf_hash_preserved'] !== true || $row['stable_leaf_freeblocks_preserved'] !== true) {
                    $errors[] = "stable leaf page {$row['page_number']} changed before publication";
                }
            }
            if ($row['status'] === 'replacement-overflow' && !in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)) {
                $errors[] = "replacement overflow page {$row['page_number']} has invalid published pointer-map type";
            }
            if ($row['status'] === 'truncated-tail-page' && $row['publish_to_next_current_source'] === true) {
                $errors[] = "truncated tail page {$row['page_number']} was published";
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
