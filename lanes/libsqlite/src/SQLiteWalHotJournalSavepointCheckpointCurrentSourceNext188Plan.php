<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext188Plan
{
    /**
     * @param list<array{name:string,source_id?:string,epoch?:int,schema_cookie?:int,root_pages?:list<int>,dirty?:bool,closed?:bool,sql?:string,observed_checkpoint_sequence?:int,observed_salt?:list<int>,cursor_page?:int,observed_commit_hook?:int,observed_schema_cookie?:int}> $statements
     * @param list<array{name:string,source_id?:string,epoch?:int,observed_checkpoint_sequence?:int,observed_salt?:list<int>,pinned?:bool,dirty?:bool,closed?:bool,observed_commit_hook?:int,observed_schema_cookie?:int}> $readers
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
        int $currentCommitHook,
        int $nextCommitHook,
        ?array $expectedCurrentToken = null,
        ?array $expectedNextToken = null,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        if ($currentCommitHook < 0 || $nextCommitHook < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next188 commit hooks must be non-negative');
        }
        if ($nextCommitHook < $currentCommitHook) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next188 commit hook cannot move backwards');
        }

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext185Plan::plan(
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
            $statements,
            $readers,
            $currentSchemaCookie,
            $nextSchemaCookie,
            $expectedCurrentToken,
            $expectedNextToken,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch
        );

        $statementRows = [];
        $admittedStatements = [];
        $reprepareStatements = [];
        foreach ($base['statement_rows'] as $offset => $row) {
            $decision = self::hookDecision('statement', $statements[$offset], $row, $currentSchemaCookie, $nextSchemaCookie, $currentCommitHook, $nextCommitHook);
            $statementRows[] = $decision;
            if ($decision['admitted']) {
                $admittedStatements[] = $decision['name'];
            } else {
                $reprepareStatements[] = $decision['name'];
            }
        }

        $readerRows = [];
        $admittedReaders = [];
        $reopenReaders = [];
        foreach ($base['reader_rows'] as $offset => $row) {
            $decision = self::hookDecision('reader', $readers[$offset], $row, $currentSchemaCookie, $nextSchemaCookie, $currentCommitHook, $nextCommitHook);
            $readerRows[] = $decision;
            if ($decision['admitted']) {
                $admittedReaders[] = $decision['name'];
            } else {
                $reopenReaders[] = $decision['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'base_generation_current_source',
                'matched' => $base['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next185',
                'reason' => 'base next185 WAL generation admission must pass before commit-hook admission',
            ],
            [
                'name' => 'commit_hook_forward',
                'matched' => $nextCommitHook >= $currentCommitHook,
                'reason' => 'commit-hook counter cannot move backwards across checkpoint publication',
            ],
            [
                'name' => 'statement_commit_hook_mix',
                'matched' => $admittedStatements !== [] && $reprepareStatements !== [],
                'reason' => 'statement cache must retain current hook rows and reprepare stale hook rows',
            ],
            [
                'name' => 'reader_commit_hook_mix',
                'matched' => $admittedReaders !== [] && $reopenReaders !== [],
                'reason' => 'reader cache must retain current hook rows and reopen stale hook rows',
            ],
        ];
        $mismatches = array_values(array_filter($guardRows, static fn (array $row): bool => !(bool) $row['matched']));
        $status = $mismatches === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next188'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next188';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next188'
                ? 'prepared_statements_and_readers_admitted_by_commit_hook_after_wal_generation'
                : 'commit_hook_admission_blocked_after_wal_generation',
            'database_path' => $base['database_path'],
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $base['page_size'],
            'savepoint' => $base['savepoint'],
            'mode' => $base['mode'],
            'reader_end_frame' => $base['reader_end_frame'],
            'base_status' => $base['status'],
            'current_source_token' => $base['current_source_token'],
            'next_source_token' => $base['next_source_token'],
            'current_checkpoint_sequence' => $base['current_checkpoint_sequence'],
            'next_checkpoint_sequence' => $base['next_checkpoint_sequence'],
            'current_wal_salt' => $base['current_wal_salt'],
            'next_wal_salt' => $base['next_wal_salt'],
            'current_schema_cookie' => $currentSchemaCookie,
            'next_schema_cookie' => $nextSchemaCookie,
            'current_commit_hook' => $currentCommitHook,
            'next_commit_hook' => $nextCommitHook,
            'statement_rows' => $statementRows,
            'reader_rows' => $readerRows,
            'admitted_statement_names' => $admittedStatements,
            'reprepare_statement_names' => $reprepareStatements,
            'admitted_reader_names' => $admittedReaders,
            'reopen_reader_names' => $reopenReaders,
            'statement_hook_reasons' => array_column($statementRows, 'hook_reason'),
            'reader_hook_reasons' => array_column($readerRows, 'hook_reason'),
            'statement_hook_transitions' => array_column($statementRows, 'hook_transition'),
            'reader_hook_transitions' => array_column($readerRows, 'hook_transition'),
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => array_column($mismatches, 'name'),
            'operation_names' => array_values(array_merge(
                $base['operation_names'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'admit_commit_hook_current_source_next188'
                        : 'reprepare_commit_hook_current_source_next188',
                    $statementRows
                ),
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_reader_commit_hook_next188'
                        : 'reopen_reader_commit_hook_next188',
                    $readerRows
                ),
                ['publish_commit_hook_current_source_next188']
            )),
            'hook_digest' => hash('sha256', implode('|', array_merge(
                [$base['generation_digest'], (string) $currentSchemaCookie, (string) $nextSchemaCookie, (string) $currentCommitHook, (string) $nextCommitHook],
                array_column($statementRows, 'hook_transition'),
                array_column($readerRows, 'hook_transition')
            ))),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188',
                'sqlite-wal-commit-hook-prepared-statement-reader-admission',
            ]))),
            'dependency_closure' => 'no new support component needed; composes existing WAL generation admission with native schema-cookie and commit-hook counters',
            'non_overlap' => 'adds commit-hook and schema-cookie admission after next185 checkpoint generation checks; it does not repeat WAL byte truncation, VFS savepoint apply, rollback-journal apply, checkpoint transaction planning, next182 statement root admission, or next185 salt/sequence generation checks',
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $baseRow
     * @return array<string,mixed>
     */
    private static function hookDecision(
        string $kind,
        array $input,
        array $baseRow,
        int $currentSchemaCookie,
        int $nextSchemaCookie,
        int $currentCommitHook,
        int $nextCommitHook
    ): array {
        $observedHook = $input['observed_commit_hook'] ?? null;
        $observedSchema = $input['observed_schema_cookie'] ?? null;
        if (!is_int($observedHook) || $observedHook < 0 || !is_int($observedSchema) || $observedSchema < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next188 {$kind} commit hook and schema cookie are required");
        }

        $admitted = (bool) $baseRow['admitted'];
        $reason = $admitted ? "{$kind}_commit_hook_matches_current_source" : (string) ($baseRow['generation_reason'] ?? $baseRow['reason']);
        if ($admitted && $observedHook === $nextCommitHook && $observedSchema === $nextSchemaCookie) {
            $admitted = false;
            $reason = "{$kind}_observed_next_commit_hook_before_reprepare";
        } elseif ($admitted && $observedHook !== $currentCommitHook) {
            $admitted = false;
            $reason = "{$kind}_commit_hook_predates_current_source";
        } elseif ($admitted && $observedSchema !== $currentSchemaCookie) {
            $admitted = false;
            $reason = "{$kind}_schema_cookie_predates_current_source";
        }

        return array_merge($baseRow, [
            'admitted' => $admitted,
            'hook_reason' => $reason,
            'observed_commit_hook' => $observedHook,
            'observed_schema_cookie' => $observedSchema,
            'hook_transition' => $baseRow['name'] . '@schema' . $observedSchema . '#hook' . $observedHook . '>' . ($admitted ? 'retain-current-hook' : 'reprepare-current-hook'),
        ]);
    }
}
