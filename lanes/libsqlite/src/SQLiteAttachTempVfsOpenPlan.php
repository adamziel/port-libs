<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachTempVfsOpenPlan
{
    /**
     * @return array{status:string,operation:'attach',schema:string,file:string,temp_attachment:bool,open:array<string,mixed>,sidecar:array<string,mixed>,database_list:list<array{seq:int,name:string,file:string|null}>,dependencies:list<string>}
     */
    public static function forAttachSql(
        string $sql,
        bool $fileExists,
        bool $directoryWritable,
        bool $lockAvailable = true,
        ?SQLiteBusyHandler $busyHandler = null
    ): array {
        $parsed = self::parseAttach($sql);
        $file = $parsed['file'];
        $schema = $parsed['schema'];
        $tempAttachment = $file === '';

        if ($schema === 'main' || $schema === 'temp') {
            throw new \InvalidArgumentException('SQLite ATTACH schema name cannot be main or temp');
        }

        if ($tempAttachment) {
            $open = self::tempOpen($schema, $directoryWritable);
            $sidecar = self::tempSidecar($schema, $directoryWritable, $open);
        } else {
            $open = SQLiteOpenPlan::forFilename($file, $fileExists, $directoryWritable, $lockAvailable, $busyHandler);
            $sidecar = SQLiteVfsSidecarPlan::forFilename($file, $fileExists, $directoryWritable);
        }

        $dependencies = array_values(array_unique(array_merge(
            ['attach-schema-open'],
            $open['dependencies'],
            $sidecar['dependencies'],
            $tempAttachment ? ['temp-attached-database'] : [],
        )));

        return [
            'status' => $open['can_open'] ? ($tempAttachment ? 'temp-ready' : (string) $open['status']) : (string) $open['status'],
            'operation' => 'attach',
            'schema' => $schema,
            'file' => $file,
            'temp_attachment' => $tempAttachment,
            'open' => $open,
            'sidecar' => $sidecar,
            'database_list' => [
                ['seq' => 0, 'name' => 'main', 'file' => null],
                ['seq' => 1, 'name' => 'temp', 'file' => ''],
                ['seq' => 2, 'name' => $schema, 'file' => $file],
            ],
            'dependencies' => $dependencies,
        ];
    }

    /**
     * @return array{file:string,schema:string}
     */
    private static function parseAttach(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (preg_match('/^attach(?:\s+database)?\s+(.+?)\s+as\s+(.+)$/is', $trimmed, $matches) !== 1) {
            throw new \InvalidArgumentException('SQLite ATTACH VFS open planning requires an ATTACH statement');
        }

        return [
            'file' => self::parseBoundedSqlToken($matches[1], true),
            'schema' => self::normalizeSchemaName(self::parseBoundedSqlToken($matches[2], false)),
        ];
    }

    private static function parseBoundedSqlToken(string $token, bool $allowEmpty): string
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('SQLite ATTACH token cannot be empty');
        }

        $first = $token[0];
        $last = substr($token, -1);
        if (($first === "'" || $first === '"' || $first === '`') && $last === $first) {
            $inner = substr($token, 1, -1);
            $value = str_replace($first . $first, $first, $inner);
        } elseif ($first === '[' && $last === ']') {
            $value = str_replace(']]', ']', substr($token, 1, -1));
        } elseif (preg_match('/^[A-Za-z0-9_:.\/?&=%+\-]+$/', $token) === 1) {
            $value = $token;
        } else {
            throw new \InvalidArgumentException('SQLite ATTACH VFS open planning only accepts bounded literal tokens');
        }

        if (!$allowEmpty && $value === '') {
            throw new \InvalidArgumentException('SQLite ATTACH schema name cannot be empty');
        }

        return $value;
    }

    private static function normalizeSchemaName(string $schemaName): string
    {
        $name = strtolower($schemaName);
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite ATTACH schema name cannot be empty');
        }

        return $name;
    }

    /**
     * @return array<string,mixed>
     */
    private static function tempOpen(string $schema, bool $directoryWritable): array
    {
        return [
            'status' => $directoryWritable ? 'create-temp' : 'temp-directory-fallback',
            'can_open' => true,
            'can_create' => true,
            'read_only' => false,
            'memory' => false,
            'path' => '',
            'mode' => 'rwc',
            'cache' => null,
            'immutable' => false,
            'nolock' => false,
            'psow' => null,
            'vfs' => null,
            'busy' => null,
            'reason' => null,
            'dependencies' => ['temp-vfs-open', 'temp-journal-delete-on-commit'],
            'uri' => [
                'path' => '',
                'mode' => 'rwc',
                'cache' => null,
                'immutable' => false,
                'nolock' => false,
                'psow' => null,
                'vfs' => null,
            ],
            'schema' => $schema,
        ];
    }

    /**
     * @param array<string,mixed> $open
     * @return array<string,mixed>
     */
    private static function tempSidecar(string $schema, bool $directoryWritable, array $open): array
    {
        $directory = $directoryWritable ? sys_get_temp_dir() : sys_get_temp_dir();
        $basename = 'sqlite-temp-' . $schema;

        return [
            'status' => (string) $open['status'],
            'path' => '',
            'directory' => $directory,
            'basename' => $basename,
            'wal_path' => '',
            'shm_path' => '',
            'journal_path' => $directory . DIRECTORY_SEPARATOR . $basename . '-journal',
            'super_journal_glob' => '',
            'temp_directory' => $directory,
            'read_only' => false,
            'immutable' => false,
            'nolock' => false,
            'wal_readable' => false,
            'wal_writable' => false,
            'shm_readable' => false,
            'shm_writable' => false,
            'journal_readable' => false,
            'journal_writable' => true,
            'uses_shared_memory' => false,
            'requires_directory_write' => true,
            'dependencies' => ['temp-vfs-open', 'temp-rollback-journal-sidecar', 'temp-journal-delete-on-commit'],
            'open' => $open,
        ];
    }
}
