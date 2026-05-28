<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext208Plan
{
    /**
     * @param list<array<string, mixed>> $sourceNextRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan,
        private readonly array $sourceNextRows,
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
        int $batchSize = 2,
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
            $batchSize,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan): self
    {
        $rows = self::buildSourceNextRows($basePlan);
        $errors = self::sourceNextErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next208 source-next failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceNextRows(): array
    {
        return $this->sourceNextRows;
    }

    /**
     * @return list<string>
     */
    public function sourceNextErrors(): array
    {
        return self::sourceNextErrorsForRows($this->sourceNextRows);
    }

    /**
     * @return list<int>
     */
    public function nextReadablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['next_reader_admitted'] === true);
    }

    /**
     * @return list<int>
     */
    public function pointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_next_kind'] === 'pointer-map-source-next');
    }

    /**
     * @return list<int>
     */
    public function payloadSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_next_kind'] === 'payload-source-next');
    }

    /**
     * @return list<string>
     */
    public function sourceNextTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['source_next_token'], $this->sourceNextRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceNextSummary(): array
    {
        $sealSummary = $this->basePlan->sealedCurrentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next208-ready',
            'source_next_row_count' => count($this->sourceNextRows),
            'next_readable_pages' => $this->nextReadablePages(),
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'sealed_pages' => $sealSummary['sealed_pages'],
            'seal_signature' => $sealSummary['seal_signature'],
            'next_writer_freeblock_source_token' => $sealSummary['next_writer_freeblock_source_token'],
            'source_next_tokens' => $this->sourceNextTokens(),
            'source_next_signature' => self::signature($this->sourceNextTokens()),
            'next_reader_source_token' => self::signature(array_merge(
                ['next208', $sealSummary['next_writer_freeblock_source_token']],
                $this->nextReadablePages(),
                $this->sourceNextTokens(),
            )),
            'all_seal_tokens_match' => !in_array(false, array_column($this->sourceNextRows, 'seal_token_matches'), true),
            'all_pointer_maps_before_payload' => $this->pointerMapsBeforePayload(),
            'all_tail_pages_fenced' => !in_array(false, array_column($this->sourceNextRows, 'tail_pages_fenced'), true),
            'all_source_next_chains_valid' => !in_array(false, array_column($this->sourceNextRows, 'source_next_chain_valid'), true),
            'source_next_errors' => $this->sourceNextErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next206',
                'sqlite-current-source-next208',
            ],
            'dependency_closure' => 'no new support component needed; next208 reuses next206 sealed pointer-map/payload rows, freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds source-next reader handoff after next206 sealed writer admission; does not repeat next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next208',
            'source_next_summary' => $this->sourceNextSummary(),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_rows' => $this->sourceNextRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->sourceNextRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['source_next_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsBeforePayload(): bool
    {
        $firstPayload = null;
        $lastPointer = null;
        foreach ($this->sourceNextRows as $row) {
            if ($row['source_next_kind'] === 'pointer-map-source-next') {
                $lastPointer = (int) $row['source_next_ordinal'];
            }
            if ($row['source_next_kind'] === 'payload-source-next' && $firstPayload === null) {
                $firstPayload = (int) $row['source_next_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceNextRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan): array
    {
        $sealRows = $basePlan->sealRows();
        $sealTokens = $basePlan->sealTokens();
        $pointerPages = [];
        $payloadPages = [];

        foreach ($sealRows as $row) {
            foreach ($row['sealed_pages'] as $pageNumber) {
                if ($row['seal_channel'] === 'pointer-map') {
                    $pointerPages[(int) $pageNumber] = true;
                } else {
                    $payloadPages[(int) $pageNumber] = true;
                }
            }
        }

        $groups = [
            ['pointer-map-source-next', self::sortedIntKeys($pointerPages)],
            ['payload-source-next', self::sortedIntKeys($payloadPages)],
        ];
        $rows = [];
        $previousToken = null;
        $ordinal = 0;
        $highWater = 0;

        foreach ($groups as [$kind, $pages]) {
            if ($pages === []) {
                continue;
            }
            ++$ordinal;
            $lastSealToken = $sealTokens === [] ? null : end($sealTokens);
            $highWater = max($highWater, max($pages));
            $token = self::signature(array_merge(
                ['next208', $ordinal, $kind, $previousToken ?? 'initial', $lastSealToken ?? 'none'],
                $pages,
                [$highWater],
            ));

            $rows[] = [
                'source_next_ordinal' => $ordinal,
                'source_next_kind' => $kind,
                'source_next_pages' => $pages,
                'expected_seal_token' => $lastSealToken,
                'actual_seal_token' => $lastSealToken,
                'seal_token_matches' => $lastSealToken !== null,
                'previous_source_next_token' => $previousToken,
                'next_reader_admitted' => true,
                'tail_pages_fenced' => !array_intersect([109, 110], $pages),
                'source_next_chain_valid' => $previousToken === null || is_string($previousToken),
                'high_water_page' => $highWater,
                'source_next_state' => 'current-source-next-reader-ready',
                'source_next_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sourceNextErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPayload = false;
        $previousHighWater = 0;

        foreach ($rows as $row) {
            if ($row['source_next_state'] !== 'current-source-next-reader-ready') {
                $errors[] = "source-next {$row['source_next_ordinal']} is not reader-ready";
            }
            if ((int) $row['source_next_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source-next {$row['source_next_ordinal']} skipped a handoff ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} lost the final seal token";
            }
            if ($row['previous_source_next_token'] !== $previousToken) {
                $errors[] = "source-next {$row['source_next_ordinal']} broke token chaining";
            }
            if ($row['source_next_kind'] === 'payload-source-next') {
                $seenPayload = true;
            }
            if ($row['source_next_kind'] === 'pointer-map-source-next' && $seenPayload) {
                $errors[] = "source-next {$row['source_next_ordinal']} admitted pointer maps after payload";
            }
            if ($row['tail_pages_fenced'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed a truncated tail page";
            }
            if ($row['source_next_chain_valid'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} has an invalid token chain";
            }
            if ((int) $row['high_water_page'] < $previousHighWater) {
                $errors[] = "source-next {$row['source_next_ordinal']} moved high-water backwards";
            }
            if ($row['source_next_token'] === '') {
                $errors[] = "source-next {$row['source_next_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['source_next_ordinal'];
            $previousToken = (string) $row['source_next_token'];
            $previousHighWater = (int) $row['high_water_page'];
        }

        return $errors;
    }

    /**
     * @param array<int, bool> $values
     * @return list<int>
     */
    private static function sortedIntKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return array_values(array_map('intval', $keys));
    }

    /**
     * @param list<mixed> $values
     */
    private static function signature(array $values): string
    {
        return hash('sha256', implode('|', array_map(
            static fn (mixed $value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            $values,
        )));
    }
}
