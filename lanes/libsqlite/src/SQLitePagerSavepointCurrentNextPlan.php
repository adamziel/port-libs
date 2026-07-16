<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $events
     * @param array<string,mixed> $action
     * @return array<string,mixed>
     */
    public static function currentNext(array $events, array $action): array
    {
        $current = self::stackFromEvents($events);
        $next = clone $current;
        $op = self::operation($action);
        $name = isset($action['name']) ? self::name($action['name']) : null;
        $transition = [];
        $journalAction = 'none';
        $dirtyCacheAction = 'preserve';
        $lockAfter = $current->transactionActive() ? 'reserved' : 'none';

        switch ($op) {
            case 'savepoint':
                if ($name === null) {
                    throw new \InvalidArgumentException('SQLite pager savepoint action requires a name');
                }
                $next->savepoint($name);
                $transition = [
                    'status' => 'savepoint_opened',
                    'savepoint' => $name,
                    'created_transaction' => !$current->transactionActive(),
                ];
                $journalAction = 'open_or_reuse_statement_journal';
                $dirtyCacheAction = 'preserve';
                $lockAfter = 'reserved';
                break;

            case 'rollback_to':
                if ($name === null) {
                    throw new \InvalidArgumentException('SQLite pager rollback-to action requires a name');
                }
                $transition = $next->rollbackToWithPlan($name);
                $transition['status'] = 'rolled_back_to_savepoint';
                $journalAction = 'restore_savepoint_pages';
                $dirtyCacheAction = 'clear_rolled_back_pages';
                $lockAfter = 'reserved';
                break;

            case 'release':
                if ($name === null) {
                    throw new \InvalidArgumentException('SQLite pager release action requires a name');
                }
                $transition = $next->releaseWithPlan($name);
                $transition['status'] = $transition['transaction_active_after'] ? 'savepoint_released' : 'transaction_committed_by_release';
                $journalAction = $transition['transaction_active_after'] ? 'merge_savepoint_journal' : 'finalize_transaction_journal';
                $dirtyCacheAction = $transition['transaction_active_after'] ? 'merge_dirty_pages_to_parent' : 'clear_committed_pages';
                $lockAfter = $transition['transaction_active_after'] ? 'reserved' : 'none';
                break;

            case 'commit':
                $transition = $next->commitWithPlan();
                $transition['status'] = 'transaction_committed';
                $journalAction = 'finalize_transaction_journal';
                $dirtyCacheAction = 'clear_committed_pages';
                $lockAfter = 'none';
                break;

            case 'rollback':
                $transition = $next->rollbackWithPlan();
                $transition['status'] = 'transaction_rolled_back';
                $journalAction = 'restore_transaction_pages';
                $dirtyCacheAction = 'clear_rolled_back_pages';
                $lockAfter = 'none';
                break;

            default:
                throw new \InvalidArgumentException("Unsupported SQLite pager savepoint action: {$op}");
        }

        return [
            'status' => $transition['status'],
            'action' => $op,
            'savepoint' => $name,
            'current' => self::snapshot($current),
            'next' => self::snapshot($next),
            'transition' => $transition,
            'pager' => [
                'journal_action' => $journalAction,
                'dirty_cache_action' => $dirtyCacheAction,
                'lock_after' => $lockAfter,
            ],
            'operations' => self::operations($op, $transition),
            'dependencies' => [
                'sqlite-pager-savepoint-current-next',
                'sqlite-savepoint-stack-state',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $events
     * @param array<string,mixed> $action
     * @return array<string,mixed>
     */
    public static function rollbackJournalLifecycle(array $events, array $action): array
    {
        $plan = self::currentNext($events, $action);
        $mode = self::journalMode($action['journal_mode'] ?? 'delete');
        $pageSize = self::positiveInt($action['page_size'] ?? 4096, 'page_size');
        $databasePageCount = self::positiveInt($action['database_page_count'] ?? 1, 'database_page_count');
        $truncateOnCommit = (bool) ($action['truncate_on_commit'] ?? ($mode === 'truncate'));
        $syncDirectory = (bool) ($action['sync_directory'] ?? in_array($mode, ['delete', 'truncate', 'persist'], true));
        $hotJournal = (bool) ($action['hot_journal'] ?? false);
        $currentPages = $plan['current']['pending_pages'];
        $nextPages = $plan['next']['pending_pages'];
        $rollbackPages = self::operationPageNumbers($plan, ['restore_savepoint_pages', 'rollback_transaction']);
        $mergePages = self::operationPageNumbers($plan, ['merge_savepoint_pages', 'commit_transaction']);
        $journalBytesBefore = self::journalBytes($currentPages, $pageSize);
        $journalBytesAfter = self::journalBytes($nextPages, $pageSize);
        $statementJournalPages = self::statementJournalPagesForAction($plan);
        $needsHotJournal = $hotJournal || ($journalBytesBefore > 0 && $plan['current']['active']);
        $finalDisposition = self::journalDisposition(
            $plan['action'],
            $plan['next']['active'],
            $mode,
            $truncateOnCommit
        );

        return $plan + [
            'journal_lifecycle' => [
                'mode' => $mode,
                'page_size' => $pageSize,
                'database_page_count' => $databasePageCount,
                'journal_bytes_before' => $journalBytesBefore,
                'journal_bytes_after' => $journalBytesAfter,
                'statement_journal_pages' => $statementJournalPages,
                'restore_page_numbers' => $rollbackPages,
                'merge_page_numbers' => $mergePages,
                'hot_journal_required_before' => $needsHotJournal,
                'final_disposition' => $finalDisposition,
                'sync_sequence' => self::syncSequence(
                    $plan['action'],
                    $mode,
                    $plan['next']['active'],
                    $syncDirectory,
                    $finalDisposition
                ),
                'super_journal_participant' => (bool) ($action['super_journal_participant'] ?? false),
                'requires_reserved_lock' => $plan['pager']['lock_after'] === 'reserved',
                'dependencies' => [
                    'sqlite-pager-savepoint-rollback-journal-lifecycle',
                    'sqlite-rollback-journal-lifecycle',
                ],
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $events
     */
    private static function stackFromEvents(array $events): SQLiteSavepointStack
    {
        $stack = new SQLiteSavepointStack();
        foreach ($events as $event) {
            $op = self::operation($event);
            switch ($op) {
                case 'begin':
                    $stack->beginTransaction(isset($event['name']) ? self::name($event['name']) : 'transaction');
                    break;
                case 'savepoint':
                    $stack->savepoint(self::name($event['name'] ?? null));
                    break;
                case 'page_write':
                    $stack->recordPageWrite(self::positiveInt($event['page'] ?? null, 'page'));
                    break;
                case 'page_image_write':
                    $stack->recordPageImageWrite(
                        self::positiveInt($event['page'] ?? null, 'page'),
                        self::nonEmptyString($event['image'] ?? null, 'image')
                    );
                    break;
                case 'wal_frame':
                    $stack->recordWalFrameWrite(
                        self::positiveInt($event['frame'] ?? null, 'frame'),
                        self::positiveInt($event['page'] ?? null, 'page'),
                        (bool) ($event['commit'] ?? false)
                    );
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported SQLite pager savepoint event: {$op}");
            }
        }

        return $stack;
    }

    /**
     * @return array{active:bool,depth:int,names:list<string>,pending_pages:list<int>,pending_wal_frames:list<int>}
     */
    private static function snapshot(SQLiteSavepointStack $stack): array
    {
        return [
            'active' => $stack->transactionActive(),
            'depth' => $stack->depth(),
            'names' => $stack->names(),
            'pending_pages' => $stack->pendingPageNumbers(),
            'pending_wal_frames' => $stack->pendingWalFrameIndexes(),
        ];
    }

    /**
     * @param array<string,mixed> $transition
     * @return list<array<string,mixed>>
     */
    private static function operations(string $op, array $transition): array
    {
        return match ($op) {
            'savepoint' => [[
                'op' => 'open_savepoint',
                'savepoint' => $transition['savepoint'],
                'reason' => ($transition['created_transaction'] ?? false) ? 'implicit_transaction' : 'nested_savepoint',
            ]],
            'rollback_to' => [
                [
                    'op' => 'restore_savepoint_pages',
                    'pages' => $transition['rollback_page_numbers'],
                    'reason' => 'rollback_to_savepoint',
                ],
                [
                    'op' => 'discard_nested_savepoints',
                    'savepoints' => $transition['discarded_frame_names'],
                    'reason' => 'rollback_to_keeps_target_open',
                ],
            ],
            'release' => [
                [
                    'op' => 'merge_savepoint_pages',
                    'pages' => $transition['merged_page_numbers'],
                    'reason' => 'release_savepoint',
                ],
                [
                    'op' => ($transition['transaction_active_after'] ?? false) ? 'keep_transaction_open' : 'commit_transaction',
                    'savepoints' => $transition['released_frame_names'],
                    'reason' => 'release_target',
                ],
            ],
            'commit' => [
                [
                    'op' => 'commit_transaction',
                    'pages' => $transition['committed_page_numbers'],
                    'reason' => 'outer_commit',
                ],
            ],
            'rollback' => [
                [
                    'op' => 'rollback_transaction',
                    'pages' => $transition['rollback_page_numbers'],
                    'reason' => 'outer_rollback',
                ],
            ],
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function operation(array $payload): string
    {
        $op = $payload['op'] ?? null;
        if (!is_string($op) || trim($op) === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint operation must be a non-empty string');
        }

        return strtolower(trim($op));
    }

    private static function name(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint name must be a non-empty string');
        }

        return trim($value);
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("SQLite pager savepoint {$label} must be a positive integer");
        }

        return $value;
    }

    private static function journalMode(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint journal_mode must be a non-empty string');
        }

        $mode = strtolower(trim($value));
        if (!in_array($mode, ['delete', 'truncate', 'persist', 'memory', 'off'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite pager savepoint journal_mode: {$mode}");
        }

        return $mode;
    }

    /**
     * @param list<int> $pages
     */
    private static function journalBytes(array $pages, int $pageSize): int
    {
        if ($pages === []) {
            return 0;
        }

        return 28 + (count($pages) * ($pageSize + 8));
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<int>
     */
    private static function statementJournalPagesForAction(array $plan): array
    {
        if ($plan['action'] === 'savepoint') {
            return [];
        }

        if ($plan['action'] === 'rollback_to') {
            return $plan['transition']['rollback_page_numbers'];
        }

        if ($plan['action'] === 'release') {
            return $plan['transition']['merged_page_numbers'];
        }

        return $plan['current']['pending_pages'];
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $ops
     * @return list<int>
     */
    private static function operationPageNumbers(array $plan, array $ops): array
    {
        $pages = [];
        foreach ($plan['operations'] as $operation) {
            if (!in_array($operation['op'], $ops, true) || !isset($operation['pages'])) {
                continue;
            }
            foreach ($operation['pages'] as $pageNumber) {
                $pages[$pageNumber] = true;
            }
        }

        $numbers = array_keys($pages);
        sort($numbers, SORT_NUMERIC);

        return $numbers;
    }

    private static function journalDisposition(string $action, bool $transactionActiveAfter, string $mode, bool $truncateOnCommit): string
    {
        if ($transactionActiveAfter) {
            return 'keep_open';
        }

        if ($mode === 'memory' || $mode === 'off') {
            return 'discard_memory_journal';
        }

        if ($action === 'rollback') {
            return $mode === 'persist' ? 'zero_header' : 'delete';
        }

        if ($truncateOnCommit || $mode === 'truncate') {
            return 'truncate';
        }

        if ($mode === 'persist') {
            return 'zero_header';
        }

        return 'delete';
    }

    /**
     * @return list<array{op:string,target:string,reason:string}>
     */
    private static function syncSequence(
        string $action,
        string $mode,
        bool $transactionActiveAfter,
        bool $syncDirectory,
        string $finalDisposition
    ): array {
        if ($transactionActiveAfter) {
            return [[
                'op' => 'sync',
                'target' => 'statement-journal',
                'reason' => $action === 'rollback_to' ? 'savepoint_rollback_keeps_outer_transaction' : 'savepoint_release_keeps_outer_transaction',
            ]];
        }

        if ($mode === 'memory' || $mode === 'off') {
            return [];
        }

        $sequence = [[
            'op' => 'sync',
            'target' => 'rollback-journal',
            'reason' => $action === 'rollback' ? 'rollback_before_database_restore' : 'commit_before_database_write',
        ]];
        if ($action !== 'rollback') {
            $sequence[] = [
                'op' => 'sync',
                'target' => 'database',
                'reason' => 'commit_database_pages',
            ];
        }
        if ($syncDirectory && in_array($finalDisposition, ['delete', 'truncate', 'zero_header'], true)) {
            $sequence[] = [
                'op' => 'sync',
                'target' => 'directory',
                'reason' => "journal_{$finalDisposition}_durable",
            ];
        }

        return $sequence;
    }

    private static function nonEmptyString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite pager savepoint {$label} must be a non-empty string");
        }

        return $value;
    }
}
