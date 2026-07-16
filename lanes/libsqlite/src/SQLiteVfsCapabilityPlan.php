<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCapabilityPlan
{
    private const DEVICE_FLAGS = [
        'atomic' => 0x00000001,
        'atomic512' => 0x00000002,
        'atomic1k' => 0x00000004,
        'atomic2k' => 0x00000008,
        'atomic4k' => 0x00000010,
        'atomic8k' => 0x00000020,
        'atomic16k' => 0x00000040,
        'atomic32k' => 0x00000080,
        'atomic64k' => 0x00000100,
        'safe_append' => 0x00000200,
        'sequential' => 0x00000400,
        'undeletable_when_open' => 0x00000800,
        'powersafe_overwrite' => 0x00001000,
        'immutable' => 0x00002000,
        'batch_atomic' => 0x00004000,
    ];

    /**
     * @param list<string> $deviceFlags
     * @return array{status:string,path:string,vfs:string|null,read_only:bool,immutable:bool,nolock:bool,sector_size:int,device_characteristics:int,device_flags:list<string>,sync_mode:string,requires_full_sync:bool,requires_directory_sync:bool,uses_powersafe_overwrite:bool,journal_header_padding:int,persist_wal:bool,chunk_size:int|null,mmap_size:int|null,mmap_allowed:bool,file_controls:array<string, mixed>,dependencies:list<string>,open:array<string, mixed>}
     */
    public static function forFilename(
        string $filename,
        bool $fileExists,
        bool $directoryWritable,
        int $sectorSize = 512,
        array $deviceFlags = ['powersafe_overwrite'],
        string $syncMode = 'normal',
        bool $persistWal = false,
        ?int $chunkSize = null,
        ?int $mmapSize = null
    ): array {
        $open = SQLiteOpenPlan::forFilename($filename, $fileExists, $directoryWritable);
        $sectorSize = self::sectorSize($sectorSize);
        $flags = self::deviceFlags($deviceFlags);
        $syncMode = self::syncMode($syncMode);
        $chunkSize = self::optionalNonNegative($chunkSize, 'SQLite chunk size');
        $mmapSize = self::optionalNonNegative($mmapSize, 'SQLite mmap size');
        $readOnly = (bool) $open['read_only'];
        $immutable = (bool) $open['immutable'];
        $memory = (bool) $open['memory'];
        $nolock = (bool) $open['nolock'];
        $characteristics = self::characteristics($flags, $open['psow']);
        $usesPowersafeOverwrite = ($characteristics & self::DEVICE_FLAGS['powersafe_overwrite']) !== 0;
        $mmapAllowed = !$memory && !$nolock && !$immutable && $mmapSize !== null && $mmapSize > 0;

        return [
            'status' => $memory ? 'memory' : (string) $open['status'],
            'path' => (string) $open['path'],
            'vfs' => $open['vfs'],
            'read_only' => $readOnly,
            'immutable' => $immutable,
            'nolock' => $nolock,
            'sector_size' => $sectorSize,
            'device_characteristics' => $characteristics,
            'device_flags' => self::flagNames($characteristics),
            'sync_mode' => $syncMode,
            'requires_full_sync' => !$memory && !$readOnly && $syncMode === 'full',
            'requires_directory_sync' => !$memory && !$readOnly && !$usesPowersafeOverwrite,
            'uses_powersafe_overwrite' => $usesPowersafeOverwrite,
            'journal_header_padding' => $memory ? 0 : $sectorSize,
            'persist_wal' => !$memory && $persistWal,
            'chunk_size' => $chunkSize,
            'mmap_size' => $mmapSize,
            'mmap_allowed' => $mmapAllowed,
            'file_controls' => [
                'sector_size' => $sectorSize,
                'device_characteristics' => $characteristics,
                'persist_wal' => !$memory && $persistWal,
                'chunk_size' => $chunkSize,
                'mmap_size' => $mmapAllowed ? $mmapSize : 0,
                'powersafe_overwrite' => $usesPowersafeOverwrite,
            ],
            'dependencies' => self::dependencies($open, $chunkSize, $mmapSize),
            'open' => $open,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function deviceFlagMap(): array
    {
        return self::DEVICE_FLAGS;
    }

    private static function sectorSize(int $sectorSize): int
    {
        if ($sectorSize < 512 || ($sectorSize & ($sectorSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS sector size must be a power of two at least 512');
        }

        return $sectorSize;
    }

    /**
     * @param list<string> $flags
     * @return list<string>
     */
    private static function deviceFlags(array $flags): array
    {
        $normalized = [];
        foreach ($flags as $flag) {
            $name = strtolower(str_replace('-', '_', trim($flag)));
            if (!isset(self::DEVICE_FLAGS[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS device characteristic: {$flag}");
            }
            $normalized[$name] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param list<string> $flags
     */
    private static function characteristics(array $flags, ?bool $psow): int
    {
        $characteristics = 0;
        foreach ($flags as $flag) {
            $characteristics |= self::DEVICE_FLAGS[$flag];
        }
        if ($psow === true) {
            $characteristics |= self::DEVICE_FLAGS['powersafe_overwrite'];
        } elseif ($psow === false) {
            $characteristics &= ~self::DEVICE_FLAGS['powersafe_overwrite'];
        }

        return $characteristics;
    }

    /**
     * @return list<string>
     */
    private static function flagNames(int $characteristics): array
    {
        $names = [];
        foreach (self::DEVICE_FLAGS as $name => $bit) {
            if (($characteristics & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private static function syncMode(string $syncMode): string
    {
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite sync mode: {$syncMode}");
        }

        return $syncMode;
    }

    private static function optionalNonNegative(?int $value, string $label): ?int
    {
        if ($value !== null && $value < 0) {
            throw new \InvalidArgumentException("{$label} must be non-negative");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $open
     * @return list<string>
     */
    private static function dependencies(array $open, ?int $chunkSize, ?int $mmapSize): array
    {
        $dependencies = $open['dependencies'];
        $dependencies[] = 'vfs-file-control';
        $dependencies[] = 'vfs-device-characteristics';
        $dependencies[] = 'vfs-sector-size';
        if ($chunkSize !== null) {
            $dependencies[] = 'vfs-chunk-size-control';
        }
        if ($mmapSize !== null) {
            $dependencies[] = 'vfs-mmap-size-control';
        }

        return array_values(array_unique($dependencies));
    }
}
