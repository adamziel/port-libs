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

    private static function nonEmptyString(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite pager savepoint {$label} must be a non-empty string");
        }

        return $value;
    }
}
