<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext185Plan
{
    /**
     * @param list<array{name:string,source_id?:string,epoch?:int,schema_cookie?:int,root_pages?:list<int>,dirty?:bool,closed?:bool,sql?:string,observed_checkpoint_sequence?:int,observed_salt?:list<int>,cursor_page?:int}> $statements
     * @param list<array{name:string,source_id?:string,epoch?:int,observed_checkpoint_sequence?:int,observed_salt?:list<int>,pinned?:bool,dirty?:bool,closed?:bool}> $readers
     * @param array{id:string,epoch:int}|null $expectedCurrentToken
     * @param array{id:string,epoch:int}|null $expectedNextToken
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $savepointBeforePages,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerCachePages,
        array $checkpointPages,
        array $baseReaders,
        array $statements,
        array $readers,
        int $currentSchemaCookie,
        int $nextSchemaCookie,
        ?array $expectedCurrentToken = null,
        ?array $expectedNextToken = null,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next185 requires prepared statements');
        }
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next185 requires reader rows');
        }

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext182Plan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $savepoint,
            $hotJournalPages,
            $savepointBeforePages,
            $currentWal,
            $currentWalBytes,
            $nextWal,
            $nextWalBytes,
            $readerCachePages,
            $checkpointPages,
            $baseReaders,
            self::stripGenerationFields($statements),
            $currentSchemaCookie,
            $nextSchemaCookie,
            $expectedCurrentToken,
            $expectedNextToken,
            null,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch
        );

        $currentToken = $base['current_source_token'];
        $nextToken = $base['next_source_token'];
        $currentSequence = $currentWal->header->checkpointSequence;
        $nextSequence = $nextWal->header->checkpointSequence;
        $currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
        $nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];

        $statementRows = [];
        $admittedStatements = [];
        $reprepareStatements = [];
        foreach ($base['statement_rows'] as $offset => $row) {
            $generation = self::generationDecision(
                $statements[$offset],
                $row,
                $currentSequence,
                $nextSequence,
                $currentSalt,
                $nextSalt
            );
            $statementRows[] = $generation;
            if ($generation['admitted']) {
                $admittedStatements[] = $generation['name'];
            } else {
                $reprepareStatements[] = $generation['name'];
            }
        }

        $readerRows = [];
        $admittedReaders = [];
        $reopenReaders = [];
        foreach ($readers as $reader) {
            $row = self::readerDecision($reader, $currentToken, $nextToken, $currentSequence, $nextSequence, $currentSalt, $nextSalt);
            $readerRows[] = $row;
            if ($row['admitted']) {
                $admittedReaders[] = $row['name'];
            } else {
                $reopenReaders[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'base_statement_current_source',
                'matched' => $base['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next182',
                'reason' => 'base next182 statement token/schema/root admission must pass before generation admission',
            ],
            [
                'name' => 'statement_generation_mix',
                'matched' => $admittedStatements !== [] && $reprepareStatements !== [],
                'reason' => 'checkpoint generation guard must admit current statements and reject stale generation statements',
            ],
            [
                'name' => 'reader_generation_mix',
                'matched' => $admittedReaders !== [] && $reopenReaders !== [],
                'reason' => 'checkpoint generation guard must keep current readers and force stale readers to reopen',
            ],
        ];
        $mismatches = array_values(array_filter($guardRows, static fn (array $row): bool => !(bool) $row['matched']));
        $status = $mismatches === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next185'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next185';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next185'
                ? 'prepared_statements_and_readers_admitted_by_checkpoint_generation_after_hot_journal_savepoint'
                : 'checkpoint_generation_admission_blocked_after_hot_journal_savepoint',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'reader_end_frame' => $base['reader_end_frame'],
            'base_status' => $base['status'],
            'current_source_token' => $currentToken,
            'next_source_token' => $nextToken,
            'current_checkpoint_sequence' => $currentSequence,
            'next_checkpoint_sequence' => $nextSequence,
            'current_wal_salt' => $currentSalt,
            'next_wal_salt' => $nextSalt,
            'statement_rows' => $statementRows,
            'reader_rows' => $readerRows,
            'admitted_statement_names' => $admittedStatements,
            'reprepare_statement_names' => $reprepareStatements,
            'admitted_reader_names' => $admittedReaders,
            'reopen_reader_names' => $reopenReaders,
            'statement_generation_reasons' => array_column($statementRows, 'generation_reason'),
            'reader_generation_reasons' => array_column($readerRows, 'generation_reason'),
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => array_column($mismatches, 'name'),
            'operation_names' => array_values(array_merge(
                $base['operation_names'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'admit_checkpoint_generation_current_source_next185'
                        : 'reprepare_checkpoint_generation_current_source_next185',
                    $statementRows
                ),
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_reader_checkpoint_generation_next185'
                        : 'reopen_reader_checkpoint_generation_next185',
                    $readerRows
                ),
                ['publish_checkpoint_generation_current_source_next185']
            )),
            'generation_digest' => hash('sha256', implode('|', array_merge(
                [$base['source_digest'], (string) $currentSequence, implode(',', $currentSalt), (string) $nextSequence, implode(',', $nextSalt)],
                array_column($statementRows, 'generation_transition'),
                array_column($readerRows, 'generation_transition')
            ))),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next185',
                'sqlite-wal-checkpoint-generation-prepared-statement-admission',
            ]))),
            'dependency_closure' => 'no new support component needed; composes native WAL header salt/checkpoint parsing with existing hot-journal savepoint current-source statement admission',
            'non_overlap' => 'adds checkpoint-sequence and salt generation admission for prepared statements/readers after next182 token/schema/root checks; it does not repeat WAL byte truncation, VFS apply, hot-journal rollback application, checkpoint transaction planning, or next182 statement root-page admission',
        ];
    }

    /**
     * @param list<array<string,mixed>> $statements
     * @return list<array<string,mixed>>
     */
    private static function stripGenerationFields(array $statements): array
    {
        return array_map(static function (array $statement): array {
            unset($statement['observed_checkpoint_sequence'], $statement['observed_salt'], $statement['cursor_page']);

            return $statement;
        }, $statements);
    }

    /**
     * @param array<string,mixed> $statement
     * @param array<string,mixed> $baseRow
     * @param list<int> $currentSalt
     * @param list<int> $nextSalt
     * @return array<string,mixed>
     */
    private static function generationDecision(array $statement, array $baseRow, int $currentSequence, int $nextSequence, array $currentSalt, array $nextSalt): array
    {
        $observedSequence = $statement['observed_checkpoint_sequence'] ?? null;
        if (!is_int($observedSequence) || $observedSequence < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next185 statement checkpoint sequence must be non-negative');
        }
        $observedSalt = self::observedSalt($statement, 'statement');
        $cursorPage = $statement['cursor_page'] ?? null;
        if ($cursorPage !== null && (!is_int($cursorPage) || $cursorPage < 1)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next185 cursor page must be a one-based integer');
        }

        $admitted = (bool) $baseRow['admitted'];
        $reason = $admitted ? 'statement_checkpoint_generation_matches_current_wal' : (string) $baseRow['reason'];
        if ($admitted && $observedSequence === $nextSequence && $observedSalt === $nextSalt) {
            $admitted = false;
            $reason = 'statement_observed_next_wal_generation_before_reprepare';
        } elseif ($admitted && $observedSequence !== $currentSequence) {
            $admitted = false;
            $reason = 'statement_checkpoint_sequence_predates_current_wal_generation';
        } elseif ($admitted && $observedSalt !== $currentSalt) {
            $admitted = false;
            $reason = 'statement_wal_salt_predates_current_checkpoint_generation';
        }

        return array_merge($baseRow, [
            'admitted' => $admitted,
            'generation_reason' => $reason,
            'observed_checkpoint_sequence' => $observedSequence,
            'observed_salt' => $observedSalt,
            'cursor_page' => $cursorPage,
            'generation_transition' => $baseRow['name'] . '@' . $observedSequence . '#' . implode(',', $observedSalt) . '>' . ($admitted ? 'retain-current-generation' : 'reprepare-generation'),
        ]);
    }

    /**
     * @param array<string,mixed> $reader
     * @param array{id:string,epoch:int} $currentToken
     * @param array{id:string,epoch:int} $nextToken
     * @param list<int> $currentSalt
     * @param list<int> $nextSalt
     * @return array<string,mixed>
     */
    private static function readerDecision(array $reader, array $currentToken, array $nextToken, int $currentSequence, int $nextSequence, array $currentSalt, array $nextSalt): array
    {
        $name = trim((string) ($reader['name'] ?? ''));
        $sourceId = (string) ($reader['source_id'] ?? '');
        $epoch = $reader['epoch'] ?? 0;
        $observedSequence = $reader['observed_checkpoint_sequence'] ?? null;
        if ($name === '' || $sourceId === '' || !is_int($epoch) || $epoch < 1 || !is_int($observedSequence) || $observedSequence < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next185 reader name, source, epoch, and checkpoint sequence are required');
        }
        $observedSalt = self::observedSalt($reader, 'reader');
        $dirty = (bool) ($reader['dirty'] ?? false);
        $closed = (bool) ($reader['closed'] ?? false);
        $pinned = (bool) ($reader['pinned'] ?? false);
        $admitted = true;
        $reason = 'reader_checkpoint_generation_matches_current_wal';

        if ($closed) {
            $admitted = false;
            $reason = 'reader_closed_before_checkpoint_generation_publish';
        } elseif ($dirty) {
            $admitted = false;
            $reason = 'reader_dirty_after_failed_savepoint';
        } elseif ($sourceId === $nextToken['id'] && $epoch === $nextToken['epoch']) {
            $admitted = false;
            $reason = 'reader_already_reopened_on_next_wal_generation';
        } elseif ($sourceId !== $currentToken['id']) {
            $admitted = false;
            $reason = 'reader_source_token_predates_checkpoint_current_source';
        } elseif ($epoch !== $currentToken['epoch']) {
            $admitted = false;
            $reason = 'reader_epoch_predates_checkpoint_current_source';
        } elseif ($observedSequence === $nextSequence && $observedSalt === $nextSalt) {
            $admitted = false;
            $reason = 'reader_observed_next_wal_generation_before_reopen';
        } elseif ($observedSequence !== $currentSequence) {
            $admitted = false;
            $reason = 'reader_checkpoint_sequence_predates_current_wal_generation';
        } elseif ($observedSalt !== $currentSalt) {
            $admitted = false;
            $reason = 'reader_wal_salt_predates_current_checkpoint_generation';
        }

        return [
            'name' => $name,
            'admitted' => $admitted,
            'generation_reason' => $reason,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'observed_checkpoint_sequence' => $observedSequence,
            'observed_salt' => $observedSalt,
            'pinned' => $pinned,
            'dirty' => $dirty,
            'closed' => $closed,
            'generation_transition' => $name . '@' . $observedSequence . '#' . implode(',', $observedSalt) . '>' . ($admitted ? 'retain-reader-generation' : 'reopen-reader-generation'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return list<int>
     */
    private static function observedSalt(array $row, string $label): array
    {
        $salt = $row['observed_salt'] ?? null;
        if (!is_array($salt) || count($salt) !== 2) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next185 {$label} salt must contain two integers");
        }
        $salt = array_values($salt);
        foreach ($salt as $value) {
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next185 {$label} salt must contain two non-negative integers");
            }
        }

        return $salt;
    }
}
