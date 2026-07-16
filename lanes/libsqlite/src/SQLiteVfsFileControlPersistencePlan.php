<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileControlPersistencePlan
{
    private SQLiteVfsFileControlState $state;

    /**
     * @var array<string, mixed>
     */
    private array $persistent;

    private bool $handleOpen = true;
    private int $openGeneration = 1;

    /**
     * @param array<string, mixed> $fileControls
     */
    public function __construct(
        private readonly string $filename,
        private readonly bool $fileExists = true,
        private readonly bool $directoryWritable = true,
        private readonly int $sectorSize = 4096,
        private readonly array $deviceFlags = ['safe_append', 'powersafe_overwrite'],
        private readonly string $syncMode = 'full',
        array $fileControls = [],
    ) {
        if (trim($filename) === '') {
            throw new \InvalidArgumentException('SQLite VFS file-control persistence requires a filename');
        }

        $this->persistent = [
            'persist_wal' => (bool) ($fileControls['persist_wal'] ?? false),
            'reserve_bytes' => self::reserveBytes($fileControls['reserve_bytes'] ?? 0),
            'powersafe_overwrite' => (bool) ($fileControls['powersafe_overwrite'] ?? in_array('powersafe_overwrite', $deviceFlags, true)),
        ];
        $this->state = $this->newState($fileControls);
    }

    /**
     * @param list<string|array<string, mixed>> $operations
     * @param array<string, mixed> $options
     * @return array{status:string,count:int,current:array<string, mixed>,next:array<string, mixed>,events:list<array<string, mixed>>,persistent:array<string, mixed>,dependencies:list<string>}
     */
    public static function persistentFileControlSequence(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS file-control persistence persistent file-control sequence requires operations');
        }

        $plan = new self(
            self::stringOption($options, 'filename', 'file:/srv/app/data/application.sqlite?mode=rw&cache=shared&vfs=unix'),
            (bool) ($options['file_exists'] ?? true),
            (bool) ($options['directory_writable'] ?? true),
            self::intOption($options, 'sector_size', 4096),
            is_array($options['device_flags'] ?? null) ? array_values($options['device_flags']) : ['safe_append', 'powersafe_overwrite'],
            self::stringOption($options, 'sync_mode', 'full'),
            is_array($options['file_controls'] ?? null) ? $options['file_controls'] : [],
        );

        return $plan->run($operations);
    }

    /**
     * @param list<string|array<string, mixed>> $operations
     * @return array{status:string,count:int,current:array<string, mixed>,next:array<string, mixed>,events:list<array<string, mixed>>,persistent:array<string, mixed>,dependencies:list<string>}
     */
    public function run(array $operations): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS file-control persistence persistent file-control sequence requires operations');
        }

        $events = [];
        foreach ($operations as $ordinal => $operation) {
            $current = $this->snapshot();
            $normalized = $this->normalizeOperation($operation);
            if ($normalized['kind'] === 'close') {
                $this->handleOpen = false;
                $result = [
                    'status' => 'closed',
                    'op' => 'close',
                    'changed' => $current['handle_open'],
                    'reason' => 'file_handle_closed',
                ];
            } elseif ($normalized['kind'] === 'reopen') {
                $this->reopen();
                $result = [
                    'status' => 'reopened',
                    'op' => 'reopen',
                    'changed' => true,
                    'reason' => 'persistent_file_controls_reloaded',
                ];
            } elseif (!$this->handleOpen) {
                $result = [
                    'status' => 'ignored',
                    'op' => $normalized['op'],
                    'changed' => false,
                    'reason' => 'file_control_requires_open_handle',
                ];
            } else {
                $result = $this->applyFileControl($normalized);
            }

            $events[] = [
                'ordinal' => $ordinal,
                'kind' => $normalized['kind'],
                'source' => $operation,
                'current' => $current,
                'result' => $result,
                'next' => $this->snapshot(),
            ];
        }

        $next = $this->snapshot();

        return [
            'status' => (string) ($events[array_key_last($events)]['result']['status'] ?? 'ok'),
            'count' => count($events),
            'current' => $events[0]['current'],
            'next' => $next,
            'events' => $events,
            'persistent' => $this->persistent,
            'dependencies' => $this->dependencies($next['dependencies']),
        ];
    }

    /**
     * @return array{handle_open:bool,open_generation:int,handle:array<string, mixed>,persistent:array<string, mixed>,dependencies:list<string>}
     */
    public function snapshot(): array
    {
        $snapshot = $this->state->snapshot();

        return [
            'handle_open' => $this->handleOpen,
            'open_generation' => $this->openGeneration,
            'handle' => $snapshot['controls'],
            'persistent' => $this->persistent,
            'dependencies' => $this->dependencies($snapshot['dependencies']),
        ];
    }

    /**
     * @param array{kind:string,op:string,value:mixed,source:mixed} $normalized
     * @return array<string, mixed>
     */
    private function applyFileControl(array $normalized): array
    {
        $sequence = is_string($normalized['source'])
            ? $this->state->sqlFileControlSequence([$normalized['source']])
            : $this->state->sqlFileControlSequence([['op' => $normalized['op'], 'value' => $normalized['value']]]);
        $result = $sequence['pairs'][0]['result'];
        $persistentBefore = $this->persistent;
        $persistentChanged = false;

        if (($result['status'] ?? null) === 'ok') {
            $persistentChanged = $this->persistResult($result);
        }

        $result['persistent_changed'] = $persistentChanged;
        $result['persistent_previous'] = $persistentBefore;
        $result['persistent_next'] = $this->persistent;

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function persistResult(array $result): bool
    {
        $op = (string) ($result['op'] ?? '');
        $before = $this->persistent;

        if ($op === 'persist_wal') {
            $this->persistent['persist_wal'] = (bool) ($result['value'] ?? false);
        } elseif ($op === 'reserve_bytes') {
            $this->persistent['reserve_bytes'] = self::reserveBytes($result['value'] ?? $this->persistent['reserve_bytes']);
        } elseif ($op === 'powersafe_overwrite') {
            $this->persistent['powersafe_overwrite'] = (bool) ($result['value'] ?? false);
        }

        return $before !== $this->persistent;
    }

    private function reopen(): void
    {
        $this->handleOpen = true;
        $this->openGeneration++;
        $this->state = $this->newState($this->persistent);
    }

    /**
     * @param array<string, mixed> $fileControls
     */
    private function newState(array $fileControls): SQLiteVfsFileControlState
    {
        $capability = SQLiteVfsCapabilityPlan::forFilename(
            $this->filename,
            $this->fileExists,
            $this->directoryWritable,
            $this->sectorSize,
            $this->deviceFlags,
            $this->syncMode,
            (bool) ($fileControls['persist_wal'] ?? $this->persistent['persist_wal'] ?? false),
            null,
            null,
        );
        $capability['file_controls'] = array_merge($capability['file_controls'], $this->persistent ?? [], $fileControls);

        return SQLiteVfsFileControlState::fromCapabilityPlan($capability);
    }

    /**
     * @param string|array<string, mixed> $operation
     * @return array{kind:string,op:string,value:mixed,source:mixed}
     */
    private function normalizeOperation(string|array $operation): array
    {
        if (is_string($operation)) {
            $trimmed = trim(rtrim($operation, ';'));
            if ($trimmed === '') {
                throw new \InvalidArgumentException('SQLite VFS file-control persistence operation is empty');
            }
            $lower = strtolower($trimmed);
            if ($lower === 'close') {
                return ['kind' => 'close', 'op' => 'close', 'value' => null, 'source' => $operation];
            }
            if ($lower === 'reopen' || $lower === 'open') {
                return ['kind' => 'reopen', 'op' => 'reopen', 'value' => null, 'source' => $operation];
            }

            return ['kind' => 'file_control', 'op' => 'sql', 'value' => null, 'source' => $operation];
        }

        $kind = strtolower((string) ($operation['kind'] ?? 'file_control'));
        if ($kind === 'close' || $kind === 'reopen' || $kind === 'open') {
            return [
                'kind' => $kind === 'close' ? 'close' : 'reopen',
                'op' => $kind,
                'value' => null,
                'source' => $operation,
            ];
        }
        $op = (string) ($operation['op'] ?? $operation['control'] ?? '');
        if (trim($op) === '') {
            throw new \InvalidArgumentException('SQLite VFS file-control persistence array operation requires an op');
        }

        return [
            'kind' => 'file_control',
            'op' => $op,
            'value' => $operation['value'] ?? null,
            'source' => $operation,
        ];
    }

    /**
     * @param list<string> $dependencies
     * @return list<string>
     */
    private function dependencies(array $dependencies): array
    {
        return array_values(array_unique(array_merge($dependencies, ['vfs-filecontrol-persistence-sequence'])));
    }

    private static function stringOption(array $options, string $key, string $default): string
    {
        $value = (string) ($options[$key] ?? $default);
        if (trim($value) === '') {
            throw new \InvalidArgumentException("SQLite VFS file-control persistence {$key} must not be empty");
        }

        return $value;
    }

    private static function intOption(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? $default;
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite VFS file-control persistence {$key} must be an integer");
        }

        return $value;
    }

    private static function reserveBytes(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > 255) {
            throw new \InvalidArgumentException('SQLite VFS persistent reserve bytes must be an integer between 0 and 255');
        }

        return $value;
    }
}
