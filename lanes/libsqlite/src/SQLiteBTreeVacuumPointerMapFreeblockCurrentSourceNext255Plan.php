<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan
{
    /**
     * @param list<array<string, mixed>> $publicationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan $admissionPlan,
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
        int $batchSize = 2,
    ): self {
        return self::fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan::tableLeafFromDeleteResult(
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

    public static function fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan $admissionPlan): self
    {
        $rows = self::buildPublicationRows($admissionPlan);
        $errors = self::publicationErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next255 publication failed: ' . implode('; ', $errors));
        }

        return new self($admissionPlan, $rows);
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
    public function publishedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['published_page'], $this->publicationRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextPublishedPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_published_page'], $this->publicationRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publication_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function freeblockPayloadPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publication_channel'] === 'payload' && $row['freeblock_payload_published'] === true);
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_generation_carried'] === true);
    }

    /**
     * @return list<string>
     */
    public function publicationTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['publication_token'], $this->publicationRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function publicationSummary(): array
    {
        $admissionSummary = $this->admissionPlan->admissionSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next255-ready',
            'publication_row_count' => count($this->publicationRows),
            'published_pages' => $this->publishedPages(),
            'next_published_pages' => $this->nextPublishedPages(),
            'admitted_pages' => $admissionSummary['admitted_pages'],
            'published_pages_match_admitted_pages' => $this->publishedPages() === $admissionSummary['admitted_pages'],
            'pointer_map_publication_pages' => $this->pointerMapPublicationPages(),
            'freeblock_payload_publication_pages' => $this->freeblockPayloadPublicationPages(),
            'duplicate_pointer_map_publication_pages' => $this->duplicatePointerMapPublicationPages(),
            'all_admission_tokens_match' => !in_array(false, array_column($this->publicationRows, 'admission_token_matches'), true),
            'all_publication_links_valid' => !in_array(false, array_column($this->publicationRows, 'publication_link_valid'), true),
            'all_payload_publications_wait_for_pointer_maps' => !in_array(false, array_column($this->publicationRows, 'payload_publication_waits_for_pointer_map'), true),
            'all_freeblock_payload_publications_visible' => !in_array(false, array_column($this->publicationRows, 'freeblock_payload_published'), true),
            'all_tail_pages_remain_fenced_for_publication' => !in_array(false, array_column($this->publicationRows, 'tail_pages_remain_fenced_for_publication'), true),
            'publication_errors' => $this->publicationErrors(),
            'publication_signature' => self::signature($this->publicationTokens()),
            'current_source_next255_token' => self::signature(array_merge(
                ['next255', $admissionSummary['current_source_next251_token']],
                $this->publishedPages(),
                $this->publicationTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next251',
                'sqlite-current-source-next255',
            ],
            'dependency_closure' => 'no new support component needed; next255 reuses next251 admission rows and publishes current-source next-page order for pointer-map/freeblock visibility',
            'non_overlap' => 'adds current-source next-page publication after next251 cursor admission; does not repeat next251 admission, next248 sealing, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next255',
            'publication_summary' => $this->publicationSummary(),
            'publication_errors' => $this->publicationErrors(),
            'publication_rows' => $this->publicationRows,
            'admission_plan' => $this->admissionPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->publicationRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['published_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPublicationRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan $admissionPlan): array
    {
        $admissionRows = $admissionPlan->admissionRows();
        $admissionTokens = $admissionPlan->admissionTokens();
        $rows = [];
        $previousPublicationToken = null;
        $publishedPointerMaps = [];
        $publishedPayloads = [];

        foreach ($admissionRows as $index => $admissionRow) {
            $pageNumber = (int) $admissionRow['admitted_page'];
            $channel = (string) $admissionRow['admission_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $publishedPointerMaps[$pageNumber] = ($publishedPointerMaps[$pageNumber] ?? 0) + 1;
            }

            $payloadPublished = $channel === 'payload'
                && $admissionRow['source_cursor_can_advance'] === true
                && $admissionRow['payload_cursor_advance_safe'] === true
                && $admissionRow['published_freeblock_pages'] !== []
                && $publishedPointerMaps !== [];
            if ($payloadPublished) {
                $publishedPayloads[$pageNumber] = true;
            }

            $nextPage = $admissionRows[$index + 1]['admitted_page'] ?? null;
            $token = self::signature(array_merge(
                ['next255', $previousPublicationToken ?? 'initial', $admissionRow['admission_token']],
                [$ordinal, $pageNumber, $nextPage ?? 'eof', $channel, $payloadPublished],
                self::generationParts($publishedPointerMaps),
                self::sortedIntKeys($publishedPayloads),
            ));

            $rows[] = [
                'publication_ordinal' => $ordinal,
                'admission_ordinal' => (int) $admissionRow['admission_ordinal'],
                'published_page' => $pageNumber,
                'next_published_page' => $nextPage,
                'publication_channel' => $channel,
                'source_admission_token' => (string) $admissionRow['admission_token'],
                'expected_admission_token' => $admissionTokens[$index] ?? null,
                'admission_token_matches' => ($admissionTokens[$index] ?? null) === (string) $admissionRow['admission_token'],
                'previous_publication_token' => $previousPublicationToken,
                'publication_link_valid' => $nextPage === ($admissionRows[$index + 1]['admitted_page'] ?? null),
                'published_pointer_map_generations' => self::generationParts($publishedPointerMaps),
                'published_payload_pages' => self::sortedIntKeys($publishedPayloads),
                'payload_publication_waits_for_pointer_map' => $channel !== 'payload' || ($payloadPublished && $publishedPointerMaps !== []),
                'freeblock_payload_published' => $channel !== 'payload' || $payloadPublished,
                'duplicate_pointer_map_generation_carried' => $channel === 'pointer-map' && ($publishedPointerMaps[$pageNumber] ?? 0) > 1,
                'tail_pages_remain_fenced_for_publication' => $admissionRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'publication_state' => $payloadPublished ? 'current-source-next255-payload-published' : 'current-source-next255-pointer-map-published',
                'publication_token' => $token,
            ];

            $previousPublicationToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function publicationErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ((int) $row['publication_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "publication {$row['publication_ordinal']} skipped an ordinal";
            }
            if ((int) $row['admission_ordinal'] !== (int) $row['publication_ordinal']) {
                $errors[] = "publication {$row['publication_ordinal']} drifted from admission ordinal";
            }
            if ($row['admission_token_matches'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} admission token drifted";
            }
            if ($row['previous_publication_token'] !== $previousToken) {
                $errors[] = "publication {$row['publication_ordinal']} broke token chaining";
            }
            if (($rows[$index + 1]['published_page'] ?? null) !== $row['next_published_page']) {
                $errors[] = "publication {$row['publication_ordinal']} has an invalid next-page link";
            }
            if ($row['payload_publication_waits_for_pointer_map'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} exposed payload before pointer-map publication";
            }
            if ($row['freeblock_payload_published'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} lost freeblock payload publication";
            }
            if ($row['tail_pages_remain_fenced_for_publication'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} exposed a fenced tail page";
            }
            if ($row['publication_token'] === '') {
                $errors[] = "publication {$row['publication_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['publication_ordinal'];
            $previousToken = (string) $row['publication_token'];
        }

        return $errors;
    }

    /**
     * @param array<int, int> $generations
     * @return list<string>
     */
    private static function generationParts(array $generations): array
    {
        ksort($generations);
        $parts = [];
        foreach ($generations as $pageNumber => $generation) {
            $parts[] = (int) $pageNumber . ':' . (int) $generation;
        }

        return $parts;
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
