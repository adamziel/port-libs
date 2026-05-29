<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Plan
{
    /**
     * @param list<array<string, mixed>> $checkpointRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $resumePlan,
        private readonly array $checkpointRows,
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
        return self::fromResumePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext229(
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

    public static function fromResumePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $resumePlan): self
    {
        $rows = self::buildCheckpointRows($resumePlan);
        $errors = self::checkpointErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next233 checkpoints failed: ' . implode('; ', $errors));
        }

        return new self($resumePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checkpointRows(): array
    {
        return $this->checkpointRows;
    }

    /**
     * @return list<string>
     */
    public function checkpointErrors(): array
    {
        return self::checkpointErrorsForRows($this->checkpointRows);
    }

    /**
     * @return list<int>
     */
    public function checkpointPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['checkpoint_page'], $this->checkpointRows));
    }

    /**
     * @return list<string>
     */
    public function checkpointTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['checkpoint_token'], $this->checkpointRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapCheckpointPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['checkpoint_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadCheckpointPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['checkpoint_channel'] === 'payload');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkpointSummary(): array
    {
        $resumeSummary = $this->resumePlan->resumeSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next233-ready',
            'checkpoint_row_count' => count($this->checkpointRows),
            'checkpoint_pages' => $this->checkpointPages(),
            'resume_pages' => $resumeSummary['resume_pages'],
            'checkpoint_pages_match_resume_pages' => $this->checkpointPages() === $resumeSummary['resume_pages'],
            'pointer_map_checkpoint_pages' => $this->pointerMapCheckpointPages(),
            'payload_checkpoint_pages' => $this->payloadCheckpointPages(),
            'all_resume_tokens_match' => !in_array(false, array_column($this->checkpointRows, 'resume_token_matches'), true),
            'all_checkpoint_links_valid' => !in_array(false, array_column($this->checkpointRows, 'checkpoint_link_valid'), true),
            'all_payload_checkpoints_have_pointer_map_visibility' => !in_array(false, array_column($this->checkpointRows, 'payload_checkpoint_has_pointer_map_visibility'), true),
            'all_duplicate_pointer_map_generations_tracked' => !in_array(false, array_column($this->checkpointRows, 'duplicate_pointer_map_generation_tracked'), true),
            'all_freeblock_receipts_checkpointed' => !in_array(false, array_column($this->checkpointRows, 'freeblock_checkpoint_receipt_carried'), true),
            'all_tail_pages_fenced_at_checkpoint' => !in_array(false, array_column($this->checkpointRows, 'tail_pages_fenced_at_checkpoint'), true),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_signature' => self::signature($this->checkpointTokens()),
            'current_source_next233_token' => self::signature(array_merge(
                ['next233', $resumeSummary['current_source_next229_token']],
                $this->checkpointPages(),
                $this->checkpointTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next224',
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next229',
                'sqlite-current-source-next233',
            ],
            'dependency_closure' => 'no new support component needed; next233 reuses next229 resume rows and records checkpoint-admission receipts only',
            'non_overlap' => 'adds checkpoint-admission receipts after next229 resume windows; does not repeat next229 resume construction, next224 cursor sequencing, next218 write receipts, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next233',
            'checkpoint_summary' => $this->checkpointSummary(),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_rows' => $this->checkpointRows,
            'resume_plan' => $this->resumePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->checkpointRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['checkpoint_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCheckpointRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $resumePlan): array
    {
        $resumeRows = $resumePlan->resumeRows();
        $resumeTokens = $resumePlan->resumeTokens();
        $rows = [];
        $previousToken = null;
        $visiblePointerMaps = [];
        $pointerMapGenerations = [];

        foreach ($resumeRows as $index => $resumeRow) {
            $pageNumber = (int) $resumeRow['resume_page'];
            $channel = (string) $resumeRow['resume_channel'];
            if ($channel === 'pointer-map') {
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
                $visiblePointerMaps[$pageNumber] = true;
            }

            $token = self::signature(array_merge(
                ['next233', $previousToken ?? 'initial', $resumeRow['resume_token']],
                [$pageNumber, $channel, (int) $resumeRow['resume_ordinal']],
                self::sortedIntKeys($visiblePointerMaps),
                self::generationParts($pointerMapGenerations),
            ));

            $rows[] = [
                'checkpoint_ordinal' => $index + 1,
                'resume_ordinal' => (int) $resumeRow['resume_ordinal'],
                'checkpoint_page' => $pageNumber,
                'checkpoint_channel' => $channel,
                'checkpoint_visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'pointer_map_generation' => $pointerMapGenerations[$pageNumber] ?? 0,
                'pointer_map_generation_state' => self::generationParts($pointerMapGenerations),
                'resume_token' => (string) $resumeRow['resume_token'],
                'expected_resume_token' => $resumeTokens[$index] ?? null,
                'resume_token_matches' => ($resumeTokens[$index] ?? null) === (string) $resumeRow['resume_token'],
                'previous_checkpoint_token' => $previousToken,
                'checkpoint_link_valid' => $resumeRow['next_resume_page'] === ($resumeRows[$index + 1]['resume_page'] ?? null),
                'payload_checkpoint_has_pointer_map_visibility' => $channel === 'pointer-map' || $visiblePointerMaps !== [],
                'duplicate_pointer_map_generation_tracked' => $channel !== 'pointer-map' || ($pointerMapGenerations[$pageNumber] ?? 0) >= 1,
                'freeblock_checkpoint_receipt_carried' => $resumeRow['freeblock_resume_receipt_carried'] === true,
                'tail_pages_fenced_at_checkpoint' => $resumeRow['tail_pages_fenced_at_resume'] === true && !in_array($pageNumber, [109, 110], true),
                'checkpoint_state' => 'current-source-checkpoint-admitted',
                'checkpoint_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function checkpointErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ($row['checkpoint_state'] !== 'current-source-checkpoint-admitted') {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} is not admitted";
            }
            if ((int) $row['checkpoint_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} skipped an ordinal";
            }
            if ((int) $row['resume_ordinal'] !== (int) $row['checkpoint_ordinal']) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} drifted from resume ordinal";
            }
            if ($row['resume_token_matches'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} resume token drifted";
            }
            if ($row['previous_checkpoint_token'] !== $previousToken) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} broke checkpoint token chaining";
            }
            if ($row['checkpoint_link_valid'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} has an invalid next-page link";
            }
            if ($row['checkpoint_channel'] === 'payload' && $row['payload_checkpoint_has_pointer_map_visibility'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} exposed payload before pointer-map checkpoint visibility";
            }
            if ($row['duplicate_pointer_map_generation_tracked'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} lost pointer-map generation state";
            }
            if ($row['freeblock_checkpoint_receipt_carried'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_at_checkpoint'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} exposed fenced tail pages";
            }
            if ($row['checkpoint_token'] === '') {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} has an empty checkpoint token";
            }

            $previousOrdinal = (int) $row['checkpoint_ordinal'];
            $previousToken = (string) $row['checkpoint_token'];
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
