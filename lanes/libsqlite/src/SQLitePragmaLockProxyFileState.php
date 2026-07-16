<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaLockProxyFileState
{
    /** @var array<int,array{database:string,host_id:int,force_proxy_locking:bool,proxy_file:string|null,auto_proxy:bool,closed:bool}> */
    private array $connections = [];

    /** @var array<string,array{proxy_file:string,host_id:int,connections:list<int>}> */
    private array $activeLocks = [];

    /** @var array<string,string> */
    private array $rememberedProxyFiles = [];

    private int $nextConnectionId = 1;

    /**
     * @return array{connection:int,database:string,host_id:int,force_proxy_locking:bool,status:string}
     */
    public function open(string $databasePath, int $hostId = 1, bool $forceProxyLocking = false): array
    {
        $database = self::databasePath($databasePath);
        if ($hostId < 0) {
            throw new InvalidArgumentException('SQLite lock proxy host id must be non-negative');
        }

        $connectionId = $this->nextConnectionId++;
        $this->connections[$connectionId] = [
            'database' => $database,
            'host_id' => $hostId,
            'force_proxy_locking' => $forceProxyLocking,
            'proxy_file' => null,
            'auto_proxy' => false,
            'closed' => false,
        ];

        return [
            'connection' => $connectionId,
            'database' => $database,
            'host_id' => $hostId,
            'force_proxy_locking' => $forceProxyLocking,
            'status' => 'ok',
        ];
    }

    /**
     * @return array{status:string,connection:int,database:string}
     */
    public function close(int $connectionId): array
    {
        $connection = $this->connection($connectionId);
        $this->connections[$connectionId]['closed'] = true;
        $database = $connection['database'];

        if (isset($this->activeLocks[$database])) {
            $this->activeLocks[$database]['connections'] = array_values(array_filter(
                $this->activeLocks[$database]['connections'],
                static fn (int $candidate): bool => $candidate !== $connectionId,
            ));
            if ($this->activeLocks[$database]['connections'] === []) {
                unset($this->activeLocks[$database]);
            }
        }

        return [
            'status' => 'closed',
            'connection' => $connectionId,
            'database' => $database,
        ];
    }

    /**
     * @return array{
     *     status:string,
     *     operation:string,
     *     connection:int,
     *     database:string,
     *     requested:string|null,
     *     proxy_file:string|null,
     *     auto_proxy:bool,
     *     rows:list<array{lock_proxy_file:string|null}>,
     *     assignment_returns_rows:false,
     *     dependencies:list<string>
     * }
     */
    public function pragma(int $connectionId, string $sql): array
    {
        $connection = $this->connection($connectionId);
        $parsed = self::parse($sql);
        $requested = $parsed['value'];
        $proxyFile = $connection['proxy_file'];
        $autoProxy = $connection['auto_proxy'];

        if ($parsed['has_rhs']) {
            $autoProxy = strcasecmp((string) $requested, ':auto:') === 0;
            $proxyFile = $autoProxy
                ? $this->autoProxyFile($connection['database'])
                : self::proxyPath((string) $requested);

            $this->connections[$connectionId]['proxy_file'] = $proxyFile;
            $this->connections[$connectionId]['auto_proxy'] = $autoProxy;
            $this->rememberedProxyFiles[$connection['database']] = $proxyFile;
        }

        return [
            'status' => 'ok',
            'operation' => $parsed['has_rhs'] ? 'lock-proxy-file-assignment' : 'lock-proxy-file-query',
            'connection' => $connectionId,
            'database' => $connection['database'],
            'requested' => $requested,
            'proxy_file' => $proxyFile,
            'auto_proxy' => $autoProxy,
            'rows' => $parsed['has_rhs'] ? [] : [['lock_proxy_file' => $proxyFile]],
            'assignment_returns_rows' => false,
            'dependencies' => ['sqlite-pragma-lock-proxy-file-state'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $schemaRows
     * @return array{
     *     status:string,
     *     operation:string,
     *     connection:int,
     *     database:string,
     *     proxy_file:string|null,
     *     auto_proxy:bool,
     *     locked:bool,
     *     reason:string|null,
     *     error:string|null,
     *     rows:list<array<string,mixed>>,
     *     active_lock:array{proxy_file:string,host_id:int,connections:list<int>}|null,
     *     dependencies:list<string>
     * }
     */
    public function selectSchema(int $connectionId, array $schemaRows = []): array
    {
        $connection = $this->connection($connectionId);
        $database = $connection['database'];
        $proxyFile = $connection['proxy_file'];
        $autoProxy = $connection['auto_proxy'];

        if ($proxyFile === null && $connection['force_proxy_locking']) {
            $proxyFile = $this->autoProxyFile($database);
            $autoProxy = true;
            $this->connections[$connectionId]['proxy_file'] = $proxyFile;
            $this->connections[$connectionId]['auto_proxy'] = true;
            $this->rememberedProxyFiles[$database] = $proxyFile;
        }

        $activeLock = $this->activeLocks[$database] ?? null;
        if ($activeLock !== null && $activeLock['host_id'] !== $connection['host_id']) {
            return $this->lockedSelect($connectionId, $database, $proxyFile, $autoProxy, 'host_id_mismatch', $activeLock);
        }

        if ($activeLock !== null && $proxyFile !== null && $activeLock['proxy_file'] !== $proxyFile) {
            return $this->lockedSelect($connectionId, $database, $proxyFile, $autoProxy, 'proxy_file_conflict', $activeLock);
        }

        if ($proxyFile !== null) {
            $this->activeLocks[$database] = [
                'proxy_file' => $proxyFile,
                'host_id' => $connection['host_id'],
                'connections' => array_values(array_unique(array_merge(
                    $activeLock['connections'] ?? [],
                    [$connectionId],
                ))),
            ];
            $this->rememberedProxyFiles[$database] = $proxyFile;
        }

        return [
            'status' => 'ok',
            'operation' => 'sqlite-master-select',
            'connection' => $connectionId,
            'database' => $database,
            'proxy_file' => $proxyFile,
            'auto_proxy' => $autoProxy,
            'locked' => false,
            'reason' => null,
            'error' => null,
            'rows' => $schemaRows,
            'active_lock' => $this->activeLocks[$database] ?? null,
            'dependencies' => ['sqlite-pragma-lock-proxy-file-state'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function rememberedProxyFiles(): array
    {
        return $this->rememberedProxyFiles;
    }

    /**
     * @return array<string,array{proxy_file:string,host_id:int,connections:list<int>}>
     */
    public function activeLocks(): array
    {
        return $this->activeLocks;
    }

    /**
     * @return array{has_rhs:bool,value:string|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (preg_match('/^pragma\s+lock_proxy_file(?<tail>.*)$/is', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('SQLite lock_proxy_file state needs a PRAGMA lock_proxy_file statement');
        }

        $tail = trim($matches['tail']);
        if ($tail === '') {
            return ['has_rhs' => false, 'value' => null];
        }

        if (str_starts_with($tail, '=')) {
            $raw = trim(substr($tail, 1));
        } elseif (str_starts_with($tail, '(') && str_ends_with($tail, ')')) {
            $raw = trim(substr($tail, 1, -1));
        } else {
            throw new InvalidArgumentException('SQLite lock_proxy_file PRAGMA only supports query, equals, or parenthesized assignment');
        }

        return [
            'has_rhs' => true,
            'value' => self::unquote($raw),
        ];
    }

    /**
     * @return array{database:string,host_id:int,force_proxy_locking:bool,proxy_file:string|null,auto_proxy:bool,closed:bool}
     */
    private function connection(int $connectionId): array
    {
        if (!isset($this->connections[$connectionId])) {
            throw new InvalidArgumentException('Unknown SQLite lock proxy connection');
        }

        if ($this->connections[$connectionId]['closed']) {
            throw new InvalidArgumentException('SQLite lock proxy connection is closed');
        }

        return $this->connections[$connectionId];
    }

    /**
     * @param array{proxy_file:string,host_id:int,connections:list<int>} $activeLock
     * @return array{
     *     status:string,
     *     operation:string,
     *     connection:int,
     *     database:string,
     *     proxy_file:string|null,
     *     auto_proxy:bool,
     *     locked:bool,
     *     reason:string,
     *     error:string,
     *     rows:list<array<string,mixed>>,
     *     active_lock:array{proxy_file:string,host_id:int,connections:list<int>},
     *     dependencies:list<string>
     * }
     */
    private function lockedSelect(int $connectionId, string $database, ?string $proxyFile, bool $autoProxy, string $reason, array $activeLock): array
    {
        return [
            'status' => 'error',
            'operation' => 'sqlite-master-select',
            'connection' => $connectionId,
            'database' => $database,
            'proxy_file' => $proxyFile,
            'auto_proxy' => $autoProxy,
            'locked' => true,
            'reason' => $reason,
            'error' => 'database is locked',
            'rows' => [],
            'active_lock' => $activeLock,
            'dependencies' => ['sqlite-pragma-lock-proxy-file-state'],
        ];
    }

    private function autoProxyFile(string $database): string
    {
        return $this->rememberedProxyFiles[$database] ?? $database . ':auto:';
    }

    private static function databasePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQLite lock proxy database path cannot be empty');
        }

        return $trimmed;
    }

    private static function proxyPath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQLite lock proxy file path cannot be empty');
        }

        return $trimmed;
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $quote = $value[0];
            if (($quote === "'" || $quote === '"') && $value[strlen($value) - 1] === $quote) {
                $inner = substr($value, 1, -1);

                return $quote === "'"
                    ? str_replace("''", "'", $inner)
                    : str_replace('""', '"', $inner);
            }
        }

        return $value;
    }
}
