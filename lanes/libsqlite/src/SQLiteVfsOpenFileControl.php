<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsOpenFileControl
{
    private readonly SQLiteVfsFileControlState $state;
    private readonly SQLiteVfsFileHandle $handle;
    private readonly array $capability;

    /**
     * @param array<string, mixed> $capability
     */
    public function __construct(
        private readonly string $rootDirectory,
        array $capability
    ) {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS open file-control requires a root directory');
        }
        if (!is_array($capability['open'] ?? null)) {
            throw new \InvalidArgumentException('SQLite VFS open file-control requires an open capability plan');
        }

        $open = $capability['open'];
        if (($open['can_open'] ?? false) !== true) {
            throw new \RuntimeException('SQLite VFS open file-control requires an openable file handle');
        }

        $path = (string) ($capability['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite VFS open file-control requires a path');
        }

        $this->capability = $capability;
        $this->state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
        $this->handle = new SQLiteVfsFileHandle(
            $rootDirectory,
            $path,
            (bool) ($capability['read_only'] ?? false),
            (bool) ($capability['immutable'] ?? false)
        );
    }

    public static function forFilename(
        string $rootDirectory,
        string $filename,
        bool $fileExists,
        bool $directoryWritable,
        int $sectorSize = 512,
        array $deviceFlags = ['powersafe_overwrite'],
        string $syncMode = 'normal',
        bool $persistWal = false,
        ?int $chunkSize = null,
        ?int $mmapSize = null
    ): self {
        return new self(
            $rootDirectory,
            SQLiteVfsCapabilityPlan::forFilename(
                $filename,
                $fileExists,
                $directoryWritable,
                $sectorSize,
                $deviceFlags,
                $syncMode,
                $persistWal,
                $chunkSize,
                $mmapSize
            )
        );
    }

    /**
     * @return array{status:string,root:string,capability:array<string, mixed>,state:array<string, mixed>,stat:array<string, mixed>,dependencies:list<string>}
     */
    public function snapshot(): array
    {
        $state = $this->state->snapshot();

        return [
            'status' => 'ready',
            'root' => $this->rootDirectory,
            'capability' => $this->capability,
            'state' => $state,
            'stat' => $this->handle->stat(),
            'dependencies' => $this->dependencies($state['dependencies'], ['vfs-open-file-control-application']),
        ];
    }

    /**
     * @param array<string|int, mixed> $controls
     * @return array{status:string,root:string,file_control:array<string, mixed>,preallocations:list<array<string, mixed>>,stat:array<string, mixed>,bytes_preallocated:int,dependencies:list<string>}
     */
    public function applyMany(array $controls): array
    {
        if ($controls === []) {
            throw new \InvalidArgumentException('SQLite VFS open file-control application requires at least one control');
        }

        $batch = $this->state->applyMany($controls);
        $preallocations = [];
        foreach ($batch['results'] as $result) {
            if (($result['op'] ?? null) !== 'size_hint' || ($result['status'] ?? null) !== 'ok') {
                continue;
            }
            $preallocations[] = $this->applySizeHint((int) $result['value']);
        }

        $bytesPreallocated = 0;
        foreach ($preallocations as $preallocation) {
            $bytesPreallocated += (int) $preallocation['bytes_added'];
        }

        return [
            'status' => 'applied',
            'root' => $this->rootDirectory,
            'file_control' => $batch,
            'preallocations' => $preallocations,
            'stat' => $this->handle->stat(),
            'bytes_preallocated' => $bytesPreallocated,
            'dependencies' => $this->dependencies($batch['dependencies'], ['vfs-open-file-control-application', 'vfs-size-hint-preallocation']),
        ];
    }

    /**
     * @param list<string|array<string, mixed>> $operations
     * @param array<string, mixed> $options
     * @return array{status:string,count:int,current:array<string, mixed>,next:array<string, mixed>,events:list<array<string, mixed>>,dependencies:list<string>}
     */
    public static function openFileControlSequence(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS open file-control open file-control sequence requires operations');
        }

        $root = self::stringOption($options, 'root', sys_get_temp_dir() . '/port-libsqlite-vfs-open-filecontrol-74-' . bin2hex(random_bytes(4)));
        $filename = self::stringOption($options, 'filename', '/srv/app/data/application.sqlite');
        $fileExists = (bool) ($options['file_exists'] ?? true);
        $directoryWritable = (bool) ($options['directory_writable'] ?? true);
        $sectorSize = (int) ($options['sector_size'] ?? 512);
        $deviceFlags = is_array($options['device_flags'] ?? null) ? array_values($options['device_flags']) : ['powersafe_overwrite'];
        $syncMode = self::stringOption($options, 'sync_mode', 'normal');
        $persistWal = (bool) ($options['persist_wal'] ?? false);
        $chunkSize = array_key_exists('chunk_size', $options) ? self::nullableInt($options['chunk_size'], 'SQLite VFS open file-control sequence chunk size') : null;
        $mmapSize = array_key_exists('mmap_size', $options) ? self::nullableInt($options['mmap_size'], 'SQLite VFS open file-control sequence mmap size') : null;

        $plan = self::forFilename($root, $filename, $fileExists, $directoryWritable, $sectorSize, $deviceFlags, $syncMode, $persistWal, $chunkSize, $mmapSize);
        $locks = new SQLiteVfsLockState();
        $events = [];

        foreach ($operations as $ordinal => $operation) {
            $current = self::currentNextSnapshot($plan, $locks);
            $normalized = self::normalizeOpenFileControlOperation($operation);
            if ($normalized['kind'] === 'file_control') {
                $applied = $plan->applyMany([$normalized['op'] => $normalized['value']]);
                $event = [
                    'ordinal' => $ordinal,
                    'kind' => 'file_control',
                    'op' => $normalized['op'],
                    'current' => $current,
                    'result' => $applied,
                    'next' => self::currentNextSnapshot($plan, $locks),
                ];
            } elseif ($normalized['kind'] === 'lock') {
                $lockPlan = SQLiteLockByteRangePlan::forOpenPlan(
                    $plan->capability['open'],
                    $normalized['level'],
                    $normalized['connection'],
                    $normalized['shared_index'] ?? 0,
                );
                $lock = $locks->acquire($lockPlan);
                $event = [
                    'ordinal' => $ordinal,
                    'kind' => 'lock',
                    'level' => $normalized['level'],
                    'connection' => $normalized['connection'],
                    'current' => $current,
                    'plan' => $lockPlan,
                    'result' => $lock,
                    'next' => self::currentNextSnapshot($plan, $locks),
                ];
            } else {
                $release = $locks->release((string) $plan->capability['open']['path'], $normalized['connection']);
                $event = [
                    'ordinal' => $ordinal,
                    'kind' => 'unlock',
                    'connection' => $normalized['connection'],
                    'current' => $current,
                    'result' => $release,
                    'next' => self::currentNextSnapshot($plan, $locks),
                ];
            }

            $events[] = $event;
        }

        $next = self::currentNextSnapshot($plan, $locks);

        return [
            'status' => $events === [] ? 'empty' : (string) ($events[array_key_last($events)]['result']['status'] ?? 'ok'),
            'count' => count($events),
            'current' => $events === [] ? $next : $events[0]['current'],
            'next' => $next,
            'events' => $events,
            'dependencies' => $plan->dependencies($next['dependencies'], ['vfs-open-file-control-locking-sequence']),
        ];
    }

    /**
     * @return array{status:string,path:string,requested_size:int,target_size:int,previous_size:int,bytes_added:int,chunk_size:int|null,operation:array<string, mixed>|null,reason:string|null,dependencies:list<string>}
     */
    private function applySizeHint(int $requestedSize): array
    {
        $snapshot = $this->state->snapshot();
        $controls = $snapshot['controls'];
        $stat = $this->handle->stat();
        $previousSize = (int) $stat['size'];
        $chunkSize = isset($controls['chunk_size']) && is_int($controls['chunk_size']) && $controls['chunk_size'] > 0
            ? $controls['chunk_size']
            : null;
        $targetSize = $this->roundedSize($requestedSize, $chunkSize);

        if ($targetSize <= $previousSize) {
            return [
                'status' => 'skipped',
                'path' => $snapshot['path'],
                'requested_size' => $requestedSize,
                'target_size' => $targetSize,
                'previous_size' => $previousSize,
                'bytes_added' => 0,
                'chunk_size' => $chunkSize,
                'operation' => null,
                'reason' => 'size_hint_does_not_extend_file',
                'dependencies' => $this->dependencies($snapshot['dependencies'], ['vfs-size-hint-preallocation']),
            ];
        }

        $operation = $this->handle->truncateTo($targetSize);

        return [
            'status' => 'preallocated',
            'path' => $snapshot['path'],
            'requested_size' => $requestedSize,
            'target_size' => $targetSize,
            'previous_size' => $previousSize,
            'bytes_added' => $targetSize - $previousSize,
            'chunk_size' => $chunkSize,
            'operation' => $operation,
            'reason' => $chunkSize === null ? 'apply_size_hint_to_open_file' : 'apply_chunked_size_hint_to_open_file',
            'dependencies' => $this->dependencies($snapshot['dependencies'], ['vfs-size-hint-preallocation', 'vfs-xtruncate']),
        ];
    }

    private function roundedSize(int $requestedSize, ?int $chunkSize): int
    {
        if ($requestedSize < 0) {
            throw new \InvalidArgumentException('SQLite VFS size hint must be a non-negative integer');
        }
        if ($chunkSize === null || $requestedSize === 0) {
            return $requestedSize;
        }

        return (int) (ceil($requestedSize / $chunkSize) * $chunkSize);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private function dependencies(array $left, array $right): array
    {
        return array_values(array_unique(array_merge($left, $right, ['vfs-file-handle-primitive'])));
    }

    /**
     * @return array{stat:array<string, mixed>,controls:array<string, mixed>,holders:array<string, string>,dependencies:list<string>}
     */
    private static function currentNextSnapshot(self $plan, SQLiteVfsLockState $locks): array
    {
        $snapshot = $plan->state->snapshot();
        $stat = $plan->handle->stat();
        $path = (string) $plan->capability['open']['path'];

        return [
            'stat' => $stat,
            'controls' => $snapshot['controls'],
            'holders' => $locks->holders($path),
            'dependencies' => $plan->dependencies($snapshot['dependencies'], $stat['dependencies']),
        ];
    }

    /**
     * @param string|array<string, mixed> $operation
     * @return array{kind:string,op?:string,value?:mixed,level?:string,connection?:string|null,shared_index?:int|null}
     */
    private static function normalizeOpenFileControlOperation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower((string) ($operation['kind'] ?? $operation['op'] ?? 'file_control'));
            if ($kind === 'lock') {
                return [
                    'kind' => 'lock',
                    'level' => strtolower((string) ($operation['level'] ?? 'shared')),
                    'connection' => self::connection($operation['connection'] ?? null, true),
                    'shared_index' => array_key_exists('shared_index', $operation) ? self::nullableInt($operation['shared_index'], 'SQLite VFS shared lock index') : null,
                ];
            }
            if ($kind === 'unlock' || $kind === 'release') {
                return [
                    'kind' => 'unlock',
                    'connection' => self::connection($operation['connection'] ?? null, false),
                ];
            }

            return [
                'kind' => 'file_control',
                'op' => (string) ($operation['op'] ?? $operation['control'] ?? $kind),
                'value' => $operation['value'] ?? null,
            ];
        }

        $trimmed = trim(rtrim($operation, ';'));
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite VFS open file-control sequence operation is empty');
        }

        if (preg_match('/^lock\s+(?<level>shared|reserved|pending|exclusive|none)(?:\s+by\s+|\s+)(?<connection>[A-Za-z0-9_.:-]+)(?:\s+(?<shared>\d+))?$/i', $trimmed, $matches)) {
            return [
                'kind' => 'lock',
                'level' => strtolower($matches['level']),
                'connection' => $matches['connection'],
                'shared_index' => isset($matches['shared']) && $matches['shared'] !== '' ? (int) $matches['shared'] : null,
            ];
        }

        if (preg_match('/^(?:unlock|release)(?:\s+(?<connection>[A-Za-z0-9_.:-]+))?$/i', $trimmed, $matches)) {
            return [
                'kind' => 'unlock',
                'connection' => ($matches['connection'] ?? '') !== '' ? $matches['connection'] : null,
            ];
        }

        if (preg_match('/^file_control\s*\(\s*(?<op>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches)) {
            return [
                'kind' => 'file_control',
                'op' => strtolower(str_replace('-', '_', $matches['op'])),
                'value' => self::parseOpenFileControlValue($matches['value'] ?? null),
            ];
        }

        if (preg_match('/^pragma\s+(?:(?:main|temp)\s*\.\s*)?(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*(?:=\s*(?<value>.+)|\(\s*(?<paren>[^)]*)\s*\))?$/i', $trimmed, $matches)) {
            $name = strtolower($matches['name']);
            $raw = ($matches['value'] ?? '') !== '' ? $matches['value'] : (($matches['paren'] ?? '') !== '' ? $matches['paren'] : null);

            return [
                'kind' => 'file_control',
                'op' => match ($name) {
                    'journal_size_limit', 'max_page_count' => 'size_limit',
                    'busy_timeout' => 'lock_timeout',
                    default => $name,
                },
                'value' => self::parseOpenFileControlValue($raw),
            ];
        }

        throw new \InvalidArgumentException('SQLite VFS open file-control sequence operation is unsupported');
    }

    private static function parseOpenFileControlValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/^[-+]?\d+$/', $trimmed)) {
            return (int) $trimmed;
        }
        if (strcasecmp($trimmed, 'on') === 0 || strcasecmp($trimmed, 'true') === 0) {
            return true;
        }
        if (strcasecmp($trimmed, 'off') === 0 || strcasecmp($trimmed, 'false') === 0) {
            return false;
        }
        if (
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
            || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
        ) {
            $quote = $trimmed[0];

            return str_replace($quote . $quote, $quote, substr($trimmed, 1, -1));
        }

        return $trimmed;
    }

    private static function stringOption(array $options, string $key, string $default): string
    {
        $value = (string) ($options[$key] ?? $default);
        if ($value === '') {
            throw new \InvalidArgumentException("SQLite VFS open file-control sequence {$key} must not be empty");
        }

        return $value;
    }

    private static function nullableInt(mixed $value, string $label): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value)) {
            throw new \InvalidArgumentException("{$label} must be an integer");
        }

        return $value;
    }

    private static function connection(mixed $value, bool $required): ?string
    {
        if ($value === null && !$required) {
            return null;
        }
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite VFS open file-control sequence lock connection is required');
        }

        return $value;
    }
}
