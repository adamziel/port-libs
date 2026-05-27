<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileControlState
{
    /**
     * @var array<string, mixed>
     */
    private array $controls;

    /**
     * @param array<string, mixed> $open
     * @param array<string, mixed> $fileControls
     */
    public function __construct(
        private readonly string $path,
        private readonly bool $readOnly = false,
        private readonly bool $immutable = false,
        private readonly bool $memory = false,
        private readonly bool $nolock = false,
        array $fileControls = [],
        private readonly array $open = [],
    ) {
        if (trim($path) === '') {
            throw new \InvalidArgumentException('SQLite VFS file-control state requires a path');
        }

        $this->controls = [
            'persist_wal' => (bool) ($fileControls['persist_wal'] ?? false),
            'chunk_size' => self::optionalNonNegativeInt($fileControls['chunk_size'] ?? null, 'SQLite VFS chunk size'),
            'mmap_size' => self::optionalNonNegativeInt($fileControls['mmap_size'] ?? null, 'SQLite VFS mmap size'),
            'powersafe_overwrite' => (bool) ($fileControls['powersafe_overwrite'] ?? false),
            'sector_size' => self::positiveInt($fileControls['sector_size'] ?? 512, 'SQLite VFS sector size'),
            'device_characteristics' => self::nonNegativeInt($fileControls['device_characteristics'] ?? 0, 'SQLite VFS device characteristics'),
            'name_hint' => null,
            'tempfile' => $this->memory,
        ];
    }

    /**
     * @param array{path:string,read_only:bool,immutable:bool,nolock:bool,file_controls:array<string, mixed>,open?:array<string, mixed>,status?:string} $capability
     */
    public static function fromCapabilityPlan(array $capability): self
    {
        return new self(
            (string) $capability['path'],
            (bool) $capability['read_only'],
            (bool) $capability['immutable'],
            (bool) (($capability['status'] ?? null) === 'memory'),
            (bool) $capability['nolock'],
            $capability['file_controls'],
            is_array($capability['open'] ?? null) ? $capability['open'] : [],
        );
    }

    /**
     * @return array{status:string,path:string,read_only:bool,immutable:bool,memory:bool,nolock:bool,controls:array<string, mixed>,dependencies:list<string>,open:array<string, mixed>}
     */
    public function snapshot(): array
    {
        return [
            'status' => 'ready',
            'path' => $this->path,
            'read_only' => $this->readOnly,
            'immutable' => $this->immutable,
            'memory' => $this->memory,
            'nolock' => $this->nolock,
            'controls' => $this->controls,
            'dependencies' => $this->dependencies(),
            'open' => $this->open,
        ];
    }

    /**
     * @param mixed $argument
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    public function apply(string $op, mixed $argument = null): array
    {
        $op = strtolower(str_replace('-', '_', trim($op)));
        if ($op === '') {
            throw new \InvalidArgumentException('SQLite VFS file-control op is required');
        }

        return match ($op) {
            'persist_wal' => $this->setBoolean('persist_wal', $op, $argument),
            'chunk_size' => $this->setChunkSize($argument),
            'mmap_size' => $this->setMmapSize($argument),
            'powersafe_overwrite' => $this->setBoolean('powersafe_overwrite', $op, $argument),
            'size_hint' => $this->sizeHint($argument),
            'tempfile' => $this->tempfile(),
            'name_hint' => $this->nameHint($argument),
            'sector_size' => $this->readOnlyValue('sector_size', $op),
            'device_characteristics' => $this->readOnlyValue('device_characteristics', $op),
            default => $this->result('notfound', $op, false, null, null, 'unsupported_file_control'),
        };
    }

    /**
     * @return array{status:string,applied:int,changed:int,results:list<array<string, mixed>>,controls:array<string, mixed>,dependencies:list<string>}
     */
    public function applyMany(array $controls): array
    {
        $results = [];
        $applied = 0;
        $changed = 0;
        foreach ($controls as $op => $argument) {
            if (is_int($op)) {
                if (!is_array($argument) || !array_key_exists('op', $argument)) {
                    throw new \InvalidArgumentException('SQLite VFS file-control batch item requires an op');
                }
                $result = $this->apply((string) $argument['op'], $argument['value'] ?? null);
            } else {
                $result = $this->apply((string) $op, $argument);
            }
            $results[] = $result;
            if ($result['status'] === 'ok') {
                $applied++;
            }
            if ($result['changed']) {
                $changed++;
            }
        }

        return [
            'status' => 'ok',
            'applied' => $applied,
            'changed' => $changed,
            'results' => $results,
            'controls' => $this->controls,
            'dependencies' => $this->dependencies(),
        ];
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function setBoolean(string $key, string $op, mixed $argument): array
    {
        if ($this->memory) {
            return $this->result('ignored', $op, false, $this->controls[$key], $this->controls[$key], 'memory_handle_has_no_file_control_side_effect');
        }

        $previous = (bool) $this->controls[$key];
        $value = self::boolean($argument);
        $this->controls[$key] = $value;

        return $this->result('ok', $op, $value !== $previous, $value, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function setChunkSize(mixed $argument): array
    {
        if ($this->readOnly || $this->immutable || $this->memory) {
            return $this->result('ignored', 'chunk_size', false, $this->controls['chunk_size'], $this->controls['chunk_size'], 'chunk_size_requires_writable_file_handle');
        }

        $previous = $this->controls['chunk_size'];
        $value = self::positiveInt($argument, 'SQLite VFS chunk size');
        $this->controls['chunk_size'] = $value;

        return $this->result('ok', 'chunk_size', $value !== $previous, $value, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function setMmapSize(mixed $argument): array
    {
        $previous = $this->controls['mmap_size'];
        $requested = self::nonNegativeInt($argument, 'SQLite VFS mmap size');
        $value = ($this->memory || $this->immutable || $this->nolock) ? 0 : $requested;
        $this->controls['mmap_size'] = $value;

        return $this->result($value === $requested ? 'ok' : 'ignored', 'mmap_size', $value !== $previous, $value, $previous, $value === $requested ? null : 'mmap_requires_lockable_mutable_file');
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function sizeHint(mixed $argument): array
    {
        if ($this->readOnly || $this->immutable || $this->memory) {
            return $this->result('ignored', 'size_hint', false, null, null, 'size_hint_requires_writable_file_handle');
        }

        $value = self::nonNegativeInt($argument, 'SQLite VFS size hint');

        return $this->result('ok', 'size_hint', false, $value, null, 'caller_may_preallocate_file');
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function tempfile(): array
    {
        return $this->result('ok', 'tempfile', false, $this->memory || $this->controls['tempfile'], $this->controls['tempfile'], null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function nameHint(mixed $argument): array
    {
        if (!is_string($argument) || trim($argument) === '') {
            throw new \InvalidArgumentException('SQLite VFS name hint requires a non-empty string');
        }
        if (str_contains($argument, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS name hint must not contain NUL bytes');
        }

        $previous = $this->controls['name_hint'];
        $this->controls['name_hint'] = $argument;

        return $this->result('ok', 'name_hint', $argument !== $previous, $argument, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function readOnlyValue(string $key, string $op): array
    {
        return $this->result('ok', $op, false, $this->controls[$key], $this->controls[$key], null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function result(string $status, string $op, bool $changed, mixed $value, mixed $previous, ?string $reason): array
    {
        return [
            'status' => $status,
            'op' => $op,
            'path' => $this->path,
            'changed' => $changed,
            'value' => $value,
            'previous' => $previous,
            'reason' => $reason,
            'controls' => $this->controls,
            'dependencies' => $this->dependencies(),
        ];
    }

    /**
     * @return list<string>
     */
    private function dependencies(): array
    {
        $dependencies = ['vfs-file-control-state', 'vfs-xfilecontrol'];
        if ($this->readOnly) {
            $dependencies[] = 'readonly-open';
        }
        if ($this->immutable) {
            $dependencies[] = 'immutable-readonly-open';
        }
        if ($this->memory) {
            $dependencies[] = 'memory-open';
        }
        if ($this->nolock) {
            $dependencies[] = 'nolock-open';
        }

        return $dependencies;
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }
        }

        throw new \InvalidArgumentException('SQLite VFS boolean file-control value is invalid');
    }

    private static function optionalNonNegativeInt(mixed $value, string $label): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::nonNegativeInt($value, $label);
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("{$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("{$label} must be a positive integer");
        }

        return $value;
    }
}
