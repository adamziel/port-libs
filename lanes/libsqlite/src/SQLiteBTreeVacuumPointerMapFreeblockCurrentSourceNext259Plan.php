<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext259Plan
{
    /**
     * @param list<array<string, mixed>> $sourceNextRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan $publicationPlan,
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
        return self::fromPublicationPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan::tableLeafFromDeleteResult(
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

    public static function fromPublicationPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan $publicationPlan): self
    {
        $rows = self::buildSourceNextRows($publicationPlan);
        $errors = self::sourceNextErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next259 source-next cursor failed: ' . implode('; ', $errors));
        }

        return new self($publicationPlan, $rows);
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
    public function sourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['source_page'], $this->sourceNextRows));
    }

    /**
     * @return list<int|null>
     */
    public function sourceNextPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['source_next_page'], $this->sourceNextRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'pointer-map-source');
    }

    /**
     * @return list<int>
     */
    public function freeblockSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'freeblock-source');
    }

    /**
     * @return list<int>
     */
    public function payloadSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'payload-source');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_source_generation'] === true);
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
        $publicationSummary = $this->publicationPlan->publicationSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next259-ready',
            'source_next_row_count' => count($this->sourceNextRows),
            'source_pages' => $this->sourcePages(),
            'source_next_pages' => $this->sourceNextPages(),
            'published_pages' => $publicationSummary['published_pages'],
            'source_pages_match_publication' => $this->sourcePages() === $publicationSummary['published_pages'],
            'source_next_pages_match_publication' => $this->sourceNextPages() === $publicationSummary['next_published_pages'],
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'freeblock_source_pages' => $this->freeblockSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'duplicate_pointer_map_source_pages' => $this->duplicatePointerMapSourcePages(),
            'all_publication_tokens_match' => !in_array(false, array_column($this->sourceNextRows, 'publication_token_matches'), true),
            'all_source_next_links_valid' => !in_array(false, array_column($this->sourceNextRows, 'source_next_link_valid'), true),
            'all_freeblocks_open_after_pointer_map' => !in_array(false, array_column($this->sourceNextRows, 'freeblock_opens_after_pointer_map_source'), true),
            'all_payloads_wait_for_freeblock_source' => !in_array(false, array_column($this->sourceNextRows, 'payload_waits_for_freeblock_source'), true),
            'all_tail_pages_fenced_for_source_next' => !in_array(false, array_column($this->sourceNextRows, 'tail_pages_fenced_for_source_next'), true),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_signature' => self::signature($this->sourceNextTokens()),
            'current_source_next259_token' => self::signature(array_merge(
                ['next259', $publicationSummary['current_source_next255_token']],
                $this->sourcePages(),
                $this->sourceNextPages(),
                $this->sourceNextTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next255',
                'sqlite-current-source-next259',
            ],
            'dependency_closure' => 'no new support component needed; next259 reuses next255 publication rows and validates current-source next links before freeblock/payload reuse',
            'non_overlap' => 'adds current-source next-link validation after next255 publication; does not repeat next255 publication, next251 admission, next254 write slots, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next259',
            'source_next_summary' => $this->sourceNextSummary(),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_rows' => $this->sourceNextRows,
            'publication_plan' => $this->publicationPlan->toArray(),
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
            if ($predicate($row)) {
                $pages[(int) $row['source_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceNextRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan $publicationPlan): array
    {
        $publicationRows = $publicationPlan->publicationRows();
        $publicationTokens = $publicationPlan->publicationTokens();
        $rows = [];
        $previousToken = null;
        $seenPointerMapSource = false;
        $seenFreeblockSource = false;
        $pointerMapGenerations = [];

        foreach ($publicationRows as $index => $publicationRow) {
            $pageNumber = (int) $publicationRow['published_page'];
            $publicationChannel = (string) $publicationRow['publication_channel'];
            $ordinal = $index + 1;
            $sourceChannel = match ($publicationChannel) {
                'pointer-map' => 'pointer-map-source',
                'payload' => $pageNumber === 3 ? 'freeblock-source' : 'payload-source',
                default => 'unknown-source',
            };

            if ($sourceChannel === 'pointer-map-source') {
                $seenPointerMapSource = true;
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
            }
            if ($sourceChannel === 'freeblock-source') {
                $seenFreeblockSource = true;
            }

            $nextPage = $publicationRow['next_published_page'];
            $publicationToken = (string) $publicationRow['publication_token'];
            $token = self::signature(array_merge(
                ['next259', $ordinal, $previousToken ?? 'initial', $publicationToken],
                [$pageNumber, $nextPage ?? 'eof', $sourceChannel],
                self::generationParts($pointerMapGenerations),
                [$seenPointerMapSource ? 'pointer-map-seen' : 'pointer-map-pending'],
                [$seenFreeblockSource ? 'freeblock-seen' : 'freeblock-pending'],
            ));

            $rows[] = [
                'source_next_ordinal' => $ordinal,
                'publication_ordinal' => (int) $publicationRow['publication_ordinal'],
                'source_page' => $pageNumber,
                'source_next_page' => is_int($nextPage) ? $nextPage : null,
                'source_channel' => $sourceChannel,
                'source_publication_token' => $publicationToken,
                'expected_publication_token' => $publicationTokens[$index] ?? null,
                'publication_token_matches' => ($publicationTokens[$index] ?? null) === $publicationToken,
                'previous_source_next_token' => $previousToken,
                'source_next_link_valid' => $nextPage === ($publicationRows[$index + 1]['published_page'] ?? null),
                'pointer_map_source_generations' => self::generationParts($pointerMapGenerations),
                'duplicate_pointer_map_source_generation' => $sourceChannel === 'pointer-map-source' && ($pointerMapGenerations[$pageNumber] ?? 0) > 1,
                'freeblock_opens_after_pointer_map_source' => $sourceChannel !== 'freeblock-source' || $seenPointerMapSource,
                'payload_waits_for_freeblock_source' => $sourceChannel !== 'payload-source' || ($seenPointerMapSource && $seenFreeblockSource),
                'tail_pages_fenced_for_source_next' => $publicationRow['tail_pages_remain_fenced_for_publication'] === true && !in_array($pageNumber, [109, 110], true),
                'source_next_state' => $sourceChannel === 'pointer-map-source' ? 'current-source-next259-pointer-map-linked' : 'current-source-next259-freeblock-linked',
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
        $seenPointerMap = false;
        $seenFreeblock = false;

        foreach ($rows as $row) {
            if ((int) $row['source_next_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source-next {$row['source_next_ordinal']} skipped an ordinal";
            }
            if ((int) $row['publication_ordinal'] !== (int) $row['source_next_ordinal']) {
                $errors[] = "source-next {$row['source_next_ordinal']} drifted from publication ordinal";
            }
            if ($row['publication_token_matches'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} publication token drifted";
            }
            if ($row['previous_source_next_token'] !== $previousToken) {
                $errors[] = "source-next {$row['source_next_ordinal']} broke source-next token chaining";
            }
            if ($row['source_next_link_valid'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} does not point at the next published page";
            }
            if ($row['source_channel'] === 'pointer-map-source') {
                $seenPointerMap = true;
            }
            if ($row['source_channel'] === 'freeblock-source') {
                if (!$seenPointerMap || $row['freeblock_opens_after_pointer_map_source'] !== true) {
                    $errors[] = "source-next {$row['source_next_ordinal']} opened freeblock source before pointer-map source";
                }
                $seenFreeblock = true;
            }
            if ($row['source_channel'] === 'payload-source' && $row['payload_waits_for_freeblock_source'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed payload before freeblock source";
            }
            if ($row['tail_pages_fenced_for_source_next'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed a fenced tail page";
            }
            if ($row['source_next_token'] === '') {
                $errors[] = "source-next {$row['source_next_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['source_next_ordinal'];
            $previousToken = (string) $row['source_next_token'];
        }

        if ($rows === []) {
            $errors[] = 'source-next cursor plan is empty';
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
            $parts[] = ((int) $pageNumber) . ':' . ((int) $generation);
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
