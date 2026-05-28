<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext182Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param list<array{name:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,closed?:bool}> $readers
     * @param list<array{name:string,source_id?:string,epoch?:int,schema_cookie?:int,root_pages?:list<int>,dirty?:bool,closed?:bool,sql?:string}> $statements
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
        array $readers,
        array $statements,
        int $currentSchemaCookie,
        int $nextSchemaCookie,
        ?array $expectedCurrentToken = null,
        ?array $expectedNextToken = null,
        ?string $expectedPublicationFingerprint = null,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next182 requires prepared statements');
        }
        if ($currentSchemaCookie < 0 || $nextSchemaCookie < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next182 schema cookies must be non-negative');
        }

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext167Plan::plan(
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
            $readers,
            $expectedCurrentToken,
            $expectedNextToken,
            $expectedPublicationFingerprint,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch
        );

        $currentToken = $base['current_source_token'];
        $nextToken = $base['next_source_token'];
        self::assertToken($currentToken, 'current');
        self::assertToken($nextToken, 'next');

        $changedPages = array_values(array_unique(array_merge(
            array_map('intval', $base['base_plan']['hot_journal_page_numbers'] ?? []),
            array_map('intval', $base['base_plan']['savepoint_rollback_page_numbers'] ?? [])
        )));
        sort($changedPages, SORT_NUMERIC);

        $statementRows = [];
        $admitted = [];
        $reprepare = [];
        foreach ($statements as $statement) {
            $row = self::statementAdmission(
                $statement,
                $currentToken,
                $nextToken,
                $currentSchemaCookie,
                $nextSchemaCookie,
                $changedPages
            );
            $statementRows[] = $row;
            if ($row['admitted']) {
                $admitted[] = $row['name'];
            } else {
                $reprepare[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'publication_guard',
                'matched' => $base['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next167',
                'reason' => 'base_current_source_publication_guard_admitted_checkpoint',
            ],
            [
                'name' => 'statement_mix',
                'matched' => $admitted !== [] && $reprepare !== [],
                'reason' => 'statement_cache_has_current_admissions_and_reprepare_closures',
            ],
            [
                'name' => 'schema_cookie_boundary',
                'matched' => $nextSchemaCookie >= $currentSchemaCookie,
                'reason' => 'schema_cookie_not_moved_backwards_across_checkpoint_publication',
            ],
        ];
        $mismatches = array_values(array_filter($guardRows, static fn (array $row): bool => !$row['matched']));
        $status = $mismatches === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next182'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next182';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next182'
                ? 'prepared_statement_cache_rebased_after_hot_journal_savepoint_checkpoint_current_source'
                : 'prepared_statement_cache_rebase_blocked_after_hot_journal_savepoint_checkpoint',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'reader_end_frame' => $base['reader_end_frame'],
            'base_status' => $base['status'],
            'base_reason' => $base['reason'],
            'current_source_token' => $currentToken,
            'next_source_token' => $nextToken,
            'publication_fingerprint' => $base['publication_fingerprint'],
            'current_schema_cookie' => $currentSchemaCookie,
            'next_schema_cookie' => $nextSchemaCookie,
            'schema_cookie_changed' => $nextSchemaCookie !== $currentSchemaCookie,
            'changed_page_numbers' => $changedPages,
            'statement_rows' => $statementRows,
            'admitted_statement_names' => $admitted,
            'reprepare_statement_names' => $reprepare,
            'statement_reprepare_count' => count($reprepare),
            'statement_reprepare_reasons' => array_column($statementRows, 'reason'),
            'statement_transitions' => array_column($statementRows, 'transition'),
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => array_column($mismatches, 'name'),
            'operation_names' => array_values(array_merge(
                $base['operation_names'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'admit_statement_on_checkpoint_current_source_next182'
                        : 'reprepare_statement_for_checkpoint_current_source_next182',
                    $statementRows
                ),
                ['publish_statement_cache_current_source_next182']
            )),
            'source_digest' => hash('sha256', $base['publication_fingerprint'] . '|' . implode('|', array_column($statementRows, 'transition'))),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next182',
                'sqlite-wal-current-source-statement-cache-rebase',
            ]))),
            'dependency_closure' => 'no new support component needed; composes accepted WAL/hot-journal/savepoint checkpoint publication guards with prepared statement cache admission metadata',
            'non_overlap' => 'extends accepted next167 current-source publication by deciding prepared statement cache admission after schema-cookie/root-page checks; it does not repeat VFS byte application, pinned-reader preservation, or next167 publication fingerprints',
        ];
    }

    /**
     * @param array{name:string,source_id?:string,epoch?:int,schema_cookie?:int,root_pages?:list<int>,dirty?:bool,closed?:bool,sql?:string} $statement
     * @param array{id:string,epoch:int} $currentToken
     * @param array{id:string,epoch:int} $nextToken
     * @param list<int> $changedPages
     * @return array{name:string,admitted:bool,reason:string,source_id:string,epoch:int,schema_cookie:int,root_pages:list<int>,dirty:bool,closed:bool,sql:string,transition:string}
     */
    private static function statementAdmission(
        array $statement,
        array $currentToken,
        array $nextToken,
        int $currentSchemaCookie,
        int $nextSchemaCookie,
        array $changedPages
    ): array {
        $name = trim((string) ($statement['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next182 statement name is required');
        }

        $sourceId = (string) ($statement['source_id'] ?? '');
        $epoch = $statement['epoch'] ?? 0;
        $schemaCookie = $statement['schema_cookie'] ?? -1;
        if ($sourceId === '' || !is_int($epoch) || $epoch < 1 || !is_int($schemaCookie) || $schemaCookie < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next182 statement source, epoch, and schema cookie are required');
        }

        $rootPages = [];
        foreach (($statement['root_pages'] ?? []) as $rootPage) {
            if (!is_int($rootPage) || $rootPage < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next182 statement root pages must be one-based integers');
            }
            $rootPages[] = $rootPage;
        }
        $rootPages = array_values(array_unique($rootPages));
        sort($rootPages, SORT_NUMERIC);

        $dirty = (bool) ($statement['dirty'] ?? false);
        $closed = (bool) ($statement['closed'] ?? false);
        $sql = trim((string) ($statement['sql'] ?? ''));
        $admitted = true;
        $reason = 'statement_matches_checkpoint_current_source';

        if ($closed) {
            $admitted = false;
            $reason = 'statement_closed_before_checkpoint_publish';
        } elseif ($dirty) {
            $admitted = false;
            $reason = 'statement_dirty_after_failed_savepoint';
        } elseif ($sourceId === (string) $nextToken['id'] && $epoch === (int) $nextToken['epoch']) {
            $admitted = false;
            $reason = 'statement_already_reprepared_on_next_wal_source';
        } elseif ($sourceId !== (string) $currentToken['id']) {
            $admitted = false;
            $reason = 'statement_source_token_predates_checkpoint_current_source';
        } elseif ($epoch !== (int) $currentToken['epoch']) {
            $admitted = false;
            $reason = 'statement_epoch_predates_checkpoint_current_source';
        } elseif ($schemaCookie !== $currentSchemaCookie) {
            $admitted = false;
            $reason = 'statement_schema_cookie_predates_checkpoint_current_source';
        } elseif ($nextSchemaCookie !== $currentSchemaCookie && self::intersects($rootPages, $changedPages)) {
            $admitted = false;
            $reason = 'statement_root_page_touched_by_hot_journal_or_savepoint_checkpoint';
        }

        return [
            'name' => $name,
            'admitted' => $admitted,
            'reason' => $reason,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'schema_cookie' => $schemaCookie,
            'root_pages' => $rootPages,
            'dirty' => $dirty,
            'closed' => $closed,
            'sql' => $sql,
            'transition' => $sourceId . '@' . $epoch . '#' . $schemaCookie . '>' . ($admitted ? 'checkpoint-current-statement' : 'reprepare-next-statement'),
        ];
    }

    /**
     * @param array<string,mixed> $token
     */
    private static function assertToken(array $token, string $label): void
    {
        if (($token['id'] ?? '') === '' || !isset($token['epoch']) || !is_int($token['epoch']) || $token['epoch'] < 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next182 {$label} token is invalid");
        }
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     */
    private static function intersects(array $left, array $right): bool
    {
        return array_intersect($left, $right) !== [];
    }
}
