<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempTransactionPlan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, temp?:bool, file?:string|null}> $schemas
     * @param list<array{op:string, schema:string, object?:string, savepoint?:string, outcome?:string}> $operations
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $operations, string $outcome = 'commit'): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp transaction plan requires operations');
        }
        if ($outcome !== 'commit' && $outcome !== 'rollback') {
            throw new \InvalidArgumentException('SQLite ATTACH WAL temp transaction outcome must be commit or rollback');
        }

        $state = self::initialState($schemas);
        $savepoints = [];
        $steps = [];
        $schemaWrites = [];
        $rolledBackWrites = [];

        foreach ($operations as $index => $operation) {
            $op = strtolower((string) ($operation['op'] ?? ''));
            if ($op === 'savepoint') {
                $name = self::savepointName($operation);
                $savepoints[$name] = self::copyNextCookies($state);
                $steps[] = self::step($index, $op, $name, [], $state, 'savepoint_open');
                continue;
            }

            if ($op === 'rollback_to') {
                $name = self::savepointName($operation);
                if (!isset($savepoints[$name])) {
                    throw new \InvalidArgumentException("SQLite savepoint {$name} is not open");
                }
                $before = self::copyNextCookies($state);
                self::restoreNextCookies($state, $savepoints[$name]);
                $rolledBack = self::changedSchemas($before, self::copyNextCookies($state));
                foreach ($rolledBack as $schema) {
                    $rolledBackWrites[] = $schema;
                }
                $steps[] = self::step($index, $op, $name, $rolledBack, $state, 'savepoint_rollback');
                continue;
            }

            if ($op === 'release') {
                $name = self::savepointName($operation);
                if (!isset($savepoints[$name])) {
                    throw new \InvalidArgumentException("SQLite savepoint {$name} is not open");
                }
                unset($savepoints[$name]);
                $steps[] = self::step($index, $op, $name, [], $state, 'savepoint_release');
                continue;
            }

            if ($op !== 'schema_write') {
                throw new \InvalidArgumentException("SQLite ATTACH WAL temp transaction operation {$op} is not supported");
            }

            $schema = self::schemaName((string) ($operation['schema'] ?? ''));
            if (!isset($state[$schema])) {
                throw new \InvalidArgumentException("SQLite schema {$schema} is not attached");
            }

            $state[$schema]['next_cookie']++;
            $state[$schema]['pending_writes']++;
            $state[$schema]['pending_objects'][] = (string) ($operation['object'] ?? 'sqlite_schema');
            $schemaWrites[] = $schema;
            $steps[] = self::step($index, $op, $schema, [$schema], $state, self::journal($state[$schema]));
        }

        $final = [];
        foreach ($state as $schema => $entry) {
            $nextCookie = $outcome === 'commit' ? $entry['next_cookie'] : $entry['current_cookie'];
            $final[$schema] = [
                'current_cookie' => $entry['current_cookie'],
                'transaction_next_cookie' => $entry['next_cookie'],
                'post_transaction_cookie' => $nextCookie,
                'journal' => self::journal($entry),
                'pending_writes' => $entry['pending_writes'],
                'pending_objects' => $entry['pending_objects'],
                'visible_to_current_reader' => false,
                'visible_after_' . $outcome => $nextCookie !== $entry['current_cookie'],
            ];
        }
        ksort($final);

        $changedSchemas = array_values(array_unique($schemaWrites));
        sort($changedSchemas);
        $rolledBackSchemas = array_values(array_unique($rolledBackWrites));
        sort($rolledBackSchemas);

        return [
            'status' => $outcome === 'commit' ? 'committed' : 'rolled_back',
            'operation' => 'attach-wal-temp-transaction',
            'outcome' => $outcome,
            'schema_count' => count($state),
            'operation_count' => count($operations),
            'schema_write_count' => count($schemaWrites),
            'changed_schemas' => $changedSchemas,
            'rolled_back_schemas' => $rolledBackSchemas,
            'open_savepoints' => array_keys($savepoints),
            'current_reader_policy' => 'continue_current_snapshot_until_statement_reset',
            'next_reader_policy' => $outcome === 'commit' ? 'read_committed_schema_cookies' : 'reuse_current_schema_cookies',
            'steps' => $steps,
            'schemas' => $final,
            'requires_reprepare' => $outcome === 'commit' && $changedSchemas !== [],
            'reprepare_schemas' => $outcome === 'commit' ? $changedSchemas : [],
            'dependencies' => [
                'sqlite-attach-wal-temp-transaction',
                'sqlite-attach-wal-temp-transaction-schema-cookie-visibility',
                'sqlite-savepoint-rollback-restores-uncommitted-schema-cookies',
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array{current_cookie:int,next_cookie:int,pending_writes:int,pending_objects:list<string>,temp:bool}>
     */
    private static function initialState(array $schemas): array
    {
        $state = [];
        foreach ($schemas as $schema => $entry) {
            $name = self::schemaName((string) $schema);
            if (!isset($entry['schema_cookie']) || !is_int($entry['schema_cookie'])) {
                throw new \InvalidArgumentException("SQLite schema {$name} requires an integer schema cookie");
            }
            $cookie = self::nextCommittedCookie($entry);
            $state[$name] = [
                'current_cookie' => $cookie,
                'next_cookie' => $cookie,
                'pending_writes' => 0,
                'pending_objects' => [],
                'temp' => (bool) ($entry['temp'] ?? $name === 'temp'),
            ];
        }
        ksort($state);

        return $state;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function nextCommittedCookie(array $entry): int
    {
        $cookie = (int) $entry['schema_cookie'];
        if (isset($entry['wal_schema_cookie']) && is_int($entry['wal_schema_cookie'])) {
            $cookie = $entry['wal_schema_cookie'];
        }
        foreach (($entry['wal_frames'] ?? []) as $frame) {
            if (($frame['page'] ?? null) === 1 && ($frame['commit'] ?? false) === true && isset($frame['schema_cookie']) && is_int($frame['schema_cookie'])) {
                $cookie = $frame['schema_cookie'];
            }
        }

        return $cookie;
    }

    /**
     * @param array<string,array{next_cookie:int}> $state
     * @return array<string,int>
     */
    private static function copyNextCookies(array $state): array
    {
        $cookies = [];
        foreach ($state as $schema => $entry) {
            $cookies[$schema] = $entry['next_cookie'];
        }

        return $cookies;
    }

    /**
     * @param array<string,array{next_cookie:int,pending_writes:int,pending_objects:list<string>}> $state
     * @param array<string,int> $cookies
     */
    private static function restoreNextCookies(array &$state, array $cookies): void
    {
        foreach ($cookies as $schema => $cookie) {
            if ($state[$schema]['next_cookie'] !== $cookie) {
                $state[$schema]['next_cookie'] = $cookie;
                $state[$schema]['pending_writes'] = 0;
                $state[$schema]['pending_objects'] = [];
            }
        }
    }

    /**
     * @param array<string,int> $before
     * @param array<string,int> $after
     * @return list<string>
     */
    private static function changedSchemas(array $before, array $after): array
    {
        $changed = [];
        foreach ($before as $schema => $cookie) {
            if (($after[$schema] ?? null) !== $cookie) {
                $changed[] = $schema;
            }
        }
        sort($changed);

        return $changed;
    }

    /**
     * @param array<string,mixed> $operation
     */
    private static function savepointName(array $operation): string
    {
        $name = trim((string) ($operation['savepoint'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite savepoint name cannot be empty');
        }

        return strtolower($name);
    }

    /**
     * @param array<string,array{current_cookie:int,next_cookie:int,pending_writes:int,pending_objects:list<string>,temp:bool}> $state
     * @param list<string> $schemas
     * @return array<string,mixed>
     */
    private static function step(int $index, string $op, string $target, array $schemas, array $state, string $action): array
    {
        return [
            'index' => $index,
            'op' => $op,
            'target' => $target,
            'schemas' => $schemas,
            'action' => $action,
            'current_cookies' => array_map(static fn (array $entry): int => $entry['current_cookie'], $state),
            'next_cookies' => array_map(static fn (array $entry): int => $entry['next_cookie'], $state),
        ];
    }

    /**
     * @param array{temp:bool} $entry
     */
    private static function journal(array $entry): string
    {
        return $entry['temp'] ? 'temp-rollback' : 'wal';
    }

    private static function schemaName(string $name): string
    {
        $normalized = strtolower(trim($name, " \t\r\n`\"[]"));
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite schema name cannot be empty');
        }

        return $normalized;
    }
}
