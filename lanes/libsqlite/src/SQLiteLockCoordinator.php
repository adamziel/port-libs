<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteLockCoordinator
{
    private const LEVELS = ['none', 'shared', 'reserved', 'pending', 'exclusive'];

    /** @var array<string, string> */
    private array $locks = [];

    /**
     * @param array<string, string> $locks
     */
    public function __construct(array $locks = [])
    {
        foreach ($locks as $connection => $level) {
            $this->set($connection, $level);
        }
    }

    public function set(string $connection, string $level): void
    {
        $connection = self::connection($connection);
        $level = self::level($level);

        if ($level === 'none') {
            unset($this->locks[$connection]);

            return;
        }

        $this->locks[$connection] = $level;
    }

    public function release(string $connection): void
    {
        unset($this->locks[self::connection($connection)]);
    }

    /**
     * @return array{status:string,can_acquire:bool,connection:string,requested:string,current:string,holders:list<array{connection:string,level:string}>,blocking:list<array{connection:string,level:string,reason:string}>,dependencies:list<string>,reason:string|null,busy:array<string, mixed>|null}
     */
    public function plan(
        string $connection,
        string $requested,
        ?SQLiteBusyHandler $busyHandler = null
    ): array {
        $connection = self::connection($connection);
        $requested = self::level($requested);
        $current = $this->locks[$connection] ?? 'none';
        $blocking = $this->blockingLocks($connection, $requested);
        $busy = null;

        if ($blocking !== []) {
            $busy = ($busyHandler ?? SQLiteBusyHandler::timeout(0))->lockedOperationPlan(
                "acquire {$requested} sqlite lock",
                false
            );

            return $this->result($busy['status'], false, $connection, $requested, $current, $blocking, $busy, self::blockingReason($requested));
        }

        return $this->result('ready', true, $connection, $requested, $current, [], null, null);
    }

    /**
     * @return array{status:string,can_open:bool,read_only:bool,lock:array<string, mixed>,open:array<string, mixed>,dependencies:list<string>,reason:string|null}
     */
    public function openPlan(
        string $filename,
        string $connection,
        bool $fileExists,
        bool $directoryWritable,
        bool $writeIntent = false,
        ?SQLiteBusyHandler $busyHandler = null
    ): array {
        $open = SQLiteOpenPlan::forFilename($filename, $fileExists, $directoryWritable, true, $busyHandler);
        if (!$open['can_open']) {
            return [
                'status' => $open['status'],
                'can_open' => false,
                'read_only' => (bool) $open['read_only'],
                'lock' => $this->result('not-planned', false, self::connection($connection), 'none', $this->locks[self::connection($connection)] ?? 'none', [], null, 'open admission failed'),
                'open' => $open,
                'dependencies' => self::dependencies($open['dependencies'], []),
                'reason' => $open['reason'],
            ];
        }

        $requested = ($open['read_only'] || !$writeIntent) ? 'shared' : 'reserved';
        $lock = $this->plan($connection, $requested, $busyHandler);

        return [
            'status' => $lock['can_acquire'] ? $open['status'] : $lock['status'],
            'can_open' => $lock['can_acquire'],
            'read_only' => (bool) $open['read_only'],
            'lock' => $lock,
            'open' => $open,
            'dependencies' => self::dependencies($open['dependencies'], $lock['dependencies']),
            'reason' => $lock['reason'] ?? $open['reason'],
        ];
    }

    /**
     * @return list<array{connection:string,level:string}>
     */
    public function holders(): array
    {
        $holders = [];
        foreach ($this->locks as $connection => $level) {
            $holders[] = ['connection' => $connection, 'level' => $level];
        }

        usort($holders, static fn (array $a, array $b): int => $a['connection'] <=> $b['connection']);

        return $holders;
    }

    /**
     * @return list<array{connection:string,level:string,reason:string}>
     */
    private function blockingLocks(string $connection, string $requested): array
    {
        if ($requested === 'none') {
            return [];
        }

        $blocking = [];
        foreach ($this->holders() as $holder) {
            if ($holder['connection'] === $connection) {
                continue;
            }

            $level = $holder['level'];
            $reason = null;
            if ($requested === 'shared' && in_array($level, ['pending', 'exclusive'], true)) {
                $reason = $level === 'pending' ? 'pending lock blocks new shared readers' : 'exclusive lock blocks readers';
            } elseif ($requested === 'reserved' && in_array($level, ['reserved', 'pending', 'exclusive'], true)) {
                $reason = 'writer lock already held';
            } elseif ($requested === 'pending' && in_array($level, ['pending', 'exclusive'], true)) {
                $reason = 'pending or exclusive lock already held';
            } elseif ($requested === 'exclusive') {
                $reason = $level === 'shared' ? 'shared reader must drain before exclusive lock' : 'writer lock already held';
            }

            if ($reason !== null) {
                $blocking[] = [
                    'connection' => $holder['connection'],
                    'level' => $level,
                    'reason' => $reason,
                ];
            }
        }

        return $blocking;
    }

    /**
     * @param list<array{connection:string,level:string,reason:string}> $blocking
     * @param array<string, mixed>|null $busy
     * @return array{status:string,can_acquire:bool,connection:string,requested:string,current:string,holders:list<array{connection:string,level:string}>,blocking:list<array{connection:string,level:string,reason:string}>,dependencies:list<string>,reason:string|null,busy:array<string, mixed>|null}
     */
    private function result(
        string $status,
        bool $canAcquire,
        string $connection,
        string $requested,
        string $current,
        array $blocking,
        ?array $busy,
        ?string $reason
    ): array {
        return [
            'status' => $status,
            'can_acquire' => $canAcquire,
            'connection' => $connection,
            'requested' => $requested,
            'current' => $current,
            'holders' => $this->holders(),
            'blocking' => $blocking,
            'dependencies' => self::dependencies([], $busy === null ? ['sqlite-lock-coordinator'] : ['sqlite-lock-coordinator', 'busy-handler']),
            'reason' => $reason,
            'busy' => $busy,
        ];
    }

    private static function connection(string $connection): string
    {
        $connection = trim($connection);
        if ($connection === '') {
            throw new \InvalidArgumentException('SQLite lock connection id must not be empty');
        }

        return $connection;
    }

    private static function level(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, self::LEVELS, true)) {
            throw new \InvalidArgumentException("Unsupported SQLite lock level: {$level}");
        }

        return $level;
    }

    private static function blockingReason(string $requested): string
    {
        return match ($requested) {
            'shared' => 'new reader is blocked by pending or exclusive writer lock',
            'reserved' => 'writer is blocked by an existing writer lock',
            'pending' => 'pending writer is blocked by an existing pending or exclusive writer lock',
            'exclusive' => 'exclusive writer is blocked until readers and writers drain',
            default => 'requested lock is blocked',
        };
    }

    /**
     * @param list<string> $open
     * @param list<string> $lock
     * @return list<string>
     */
    private static function dependencies(array $open, array $lock): array
    {
        return array_values(array_unique(array_merge($open, $lock)));
    }
}
