<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOpenPlan
{
    /**
     * @return array{status:string,can_open:bool,can_create:bool,read_only:bool,memory:bool,path:string,mode:string,cache:string|null,immutable:bool,nolock:bool,psow:bool|null,vfs:string|null,busy:array<string, mixed>|null,reason:string|null,dependencies:list<string>,uri:array<string, mixed>}
     */
    public static function forFilename(
        string $filename,
        bool $fileExists,
        bool $directoryWritable,
        bool $lockAvailable = true,
        ?SQLiteBusyHandler $busyHandler = null
    ): array {
        $uri = SQLiteFileUri::parse($filename);
        $mode = $uri['mode'] ?? 'rwc';
        $memory = $mode === 'memory' || $uri['path'] === ':memory:';
        $readOnly = $mode === 'ro' || $uri['immutable'] === true;
        $canCreate = !$memory && $mode === 'rwc';
        $busy = null;

        if ($memory) {
            return self::result('ready', true, false, false, true, $uri, null, null);
        }

        if (!$fileExists && !$canCreate) {
            return self::result('missing', false, false, $readOnly, false, $uri, null, 'database file does not exist');
        }

        if (!$fileExists && !$directoryWritable) {
            return self::result('cannot-create', false, true, false, false, $uri, null, 'database directory is not writable');
        }

        if (!$readOnly && !$lockAvailable && $uri['nolock'] !== true) {
            $busy = ($busyHandler ?? SQLiteBusyHandler::timeout(0))->lockedOperationPlan('open sqlite database', false);

            return self::result($busy['status'], false, $canCreate && !$fileExists, false, false, $uri, $busy, 'database lock is busy');
        }

        return self::result($fileExists ? 'ready' : 'create', true, $canCreate && !$fileExists, $readOnly, false, $uri, null, null);
    }

    /**
     * @param array<string, mixed> $uri
     * @param array<string, mixed>|null $busy
     * @return array{status:string,can_open:bool,can_create:bool,read_only:bool,memory:bool,path:string,mode:string,cache:string|null,immutable:bool,nolock:bool,psow:bool|null,vfs:string|null,busy:array<string, mixed>|null,reason:string|null,dependencies:list<string>,uri:array<string, mixed>}
     */
    private static function result(
        string $status,
        bool $canOpen,
        bool $canCreate,
        bool $readOnly,
        bool $memory,
        array $uri,
        ?array $busy,
        ?string $reason
    ): array {
        return [
            'status' => $status,
            'can_open' => $canOpen,
            'can_create' => $canCreate,
            'read_only' => $readOnly,
            'memory' => $memory,
            'path' => (string) $uri['path'],
            'mode' => (string) ($uri['mode'] ?? 'rwc'),
            'cache' => $uri['cache'],
            'immutable' => $uri['immutable'] === true,
            'nolock' => $uri['nolock'] === true,
            'psow' => $uri['psow'],
            'vfs' => $uri['vfs'],
            'busy' => $busy,
            'reason' => $reason,
            'dependencies' => self::dependencies($uri, $busy),
            'uri' => $uri,
        ];
    }

    /**
     * @param array<string, mixed> $uri
     * @param array<string, mixed>|null $busy
     * @return list<string>
     */
    private static function dependencies(array $uri, ?array $busy): array
    {
        $dependencies = ['file-uri-parser'];
        if (($uri['vfs'] ?? null) !== null) {
            $dependencies[] = 'vfs-admission';
        }
        if (($uri['cache'] ?? null) === 'shared') {
            $dependencies[] = 'shared-cache-coordination';
        }
        if ($uri['immutable'] === true) {
            $dependencies[] = 'immutable-readonly-open';
        }
        if ($uri['nolock'] === true) {
            $dependencies[] = 'nolock-open';
        } elseif ($busy !== null) {
            $dependencies[] = 'busy-handler';
        }

        return $dependencies;
    }
}
