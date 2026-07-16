<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsLockedFileWriter
{
    public function __construct(
        private readonly SQLiteVfsFileWriter $writer,
        private readonly SQLiteVfsFileLock $locks,
    ) {
    }

    /**
     * @param array{level:string,can_lock:bool,nolock:bool,path:string,connection:string|null,ranges:list<array{name:string,offset:int,length:int,mode:string>>,dependencies:list<string>,reason:string|null} $lockPlan
     * @param list<array<string, mixed>> $operations
     * @param array<string, string> $payloads
     * @param list<string> $dependencies
     * @return array{status:string,locked:bool,applied:int,lock:array<string, mixed>,write:array<string, mixed>|null,release:array<string, mixed>|null,dependencies:list<string>,reason:string|null}
     */
    public function applyExclusive(array $lockPlan, array $operations, array $payloads = [], array $dependencies = []): array
    {
        $requested = strtolower((string) ($lockPlan['level'] ?? ''));
        if ($requested !== 'exclusive') {
            throw new \InvalidArgumentException('SQLite VFS locked write requires an exclusive lock plan');
        }

        $connection = $lockPlan['connection'] ?? null;
        if (!is_string($connection) || trim($connection) === '') {
            throw new \InvalidArgumentException('SQLite VFS locked write requires a connection id');
        }

        $lock = $this->locks->acquire($lockPlan);
        $allDependencies = self::mergeDependencies($dependencies, $lock['dependencies'], ['vfs-locked-file-write-application']);
        if ($lock['status'] !== 'acquired' || !$lock['applied']) {
            return [
                'status' => 'blocked',
                'locked' => false,
                'applied' => 0,
                'lock' => $lock,
                'write' => null,
                'release' => null,
                'dependencies' => $allDependencies,
                'reason' => $lock['reason'] ?? 'exclusive VFS lock could not be acquired',
            ];
        }

        $release = null;
        try {
            $write = $this->writer->applyOperations(
                $operations,
                $payloads,
                self::mergeDependencies($dependencies, ['vfs-exclusive-lock-held'])
            );
        } finally {
            $release = $this->locks->release($lock['path'], $connection);
        }

        return [
            'status' => 'applied',
            'locked' => true,
            'applied' => $write['applied'],
            'lock' => $lock,
            'write' => $write,
            'release' => $release,
            'dependencies' => self::mergeDependencies($allDependencies, $write['dependencies'], $release['dependencies']),
            'reason' => null,
        ];
    }

    /**
     * @param list<string> ...$sets
     * @return list<string>
     */
    private static function mergeDependencies(array ...$sets): array
    {
        $merged = [];
        foreach ($sets as $set) {
            foreach ($set as $dependency) {
                $dependency = (string) $dependency;
                if ($dependency !== '' && !in_array($dependency, $merged, true)) {
                    $merged[] = $dependency;
                }
            }
        }

        return $merged;
    }
}
