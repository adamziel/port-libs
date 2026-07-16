<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaDataVersionTracker
{
    /** @var array<string, array{generation:int, last_seen:int, in_transaction:bool}> */
    private array $connections = [];

    public function __construct(private int $databaseGeneration = 1)
    {
        if ($databaseGeneration < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA data_version generation must be positive');
        }
    }

    public function open(string $connectionId): int
    {
        $connectionId = self::connectionId($connectionId);
        $this->connections[$connectionId] = [
            'generation' => 1,
            'last_seen' => $this->databaseGeneration,
            'in_transaction' => false,
        ];

        return 1;
    }

    /**
     * @return array{status:string, pragma:'data_version', schema:string, value:int, database_generation:int, changed_by_other_connection:bool, write_ignored:bool}
     */
    public function executePragma(string $connectionId, string $sql): array
    {
        $parsed = self::parseDataVersionPragma($sql);
        $connectionId = self::connectionId($connectionId);
        $state = $this->state($connectionId);
        $changedByOther = $state['last_seen'] !== $this->databaseGeneration;
        $value = $state['generation'] + ($changedByOther ? 1 : 0);
        $state['generation'] = $value;
        $state['last_seen'] = $this->databaseGeneration;
        $this->connections[$connectionId] = $state;

        return [
            'status' => 'ok',
            'pragma' => 'data_version',
            'schema' => $parsed['schema'],
            'value' => $value,
            'database_generation' => $this->databaseGeneration,
            'changed_by_other_connection' => $changedByOther,
            'write_ignored' => $parsed['write'],
        ];
    }

    public function begin(string $connectionId): void
    {
        $connectionId = self::connectionId($connectionId);
        $state = $this->state($connectionId);
        $state['in_transaction'] = true;
        $this->connections[$connectionId] = $state;
    }

    public function commit(string $connectionId, bool $changedDatabase): void
    {
        $connectionId = self::connectionId($connectionId);
        $state = $this->state($connectionId);
        if (!$state['in_transaction']) {
            throw new InvalidArgumentException("SQLite PRAGMA data_version commit requires an active transaction for {$connectionId}");
        }
        $state['in_transaction'] = false;
        if ($changedDatabase) {
            $this->databaseGeneration++;
            $state['last_seen'] = $this->databaseGeneration;
        }
        $this->connections[$connectionId] = $state;
    }

    public function autocommitChange(string $connectionId): void
    {
        $connectionId = self::connectionId($connectionId);
        $state = $this->state($connectionId);
        if ($state['in_transaction']) {
            throw new InvalidArgumentException("SQLite PRAGMA data_version autocommit change cannot run inside a transaction for {$connectionId}");
        }
        $this->databaseGeneration++;
        $state['last_seen'] = $this->databaseGeneration;
        $this->connections[$connectionId] = $state;
    }

    /**
     * @return array{database_generation:int, connections:array<string,array{generation:int,last_seen:int,in_transaction:bool}>}
     */
    public function snapshot(): array
    {
        ksort($this->connections);

        return [
            'database_generation' => $this->databaseGeneration,
            'connections' => $this->connections,
        ];
    }

    /**
     * @return array{schema:string, write:bool}
     */
    public static function parseDataVersionPragma(string $sql): array
    {
        $trimmed = trim(rtrim($sql, " \t\r\n;"));
        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?data_version\s*(?:(?<op>=|\()\s*(?<value>[^)]*)\)?)?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA data_version is supported by SQLitePragmaDataVersionTracker');
        }

        return [
            'schema' => strtolower($matches['schema'] ?? 'main'),
            'write' => isset($matches['op']) && $matches['op'] !== '',
        ];
    }

    /**
     * @return array{generation:int, last_seen:int, in_transaction:bool}
     */
    private function state(string $connectionId): array
    {
        if (!isset($this->connections[$connectionId])) {
            $this->open($connectionId);
        }

        return $this->connections[$connectionId];
    }

    private static function connectionId(string $connectionId): string
    {
        $connectionId = trim($connectionId);
        if ($connectionId === '') {
            throw new InvalidArgumentException('SQLite PRAGMA data_version connection id cannot be empty');
        }

        return $connectionId;
    }
}
