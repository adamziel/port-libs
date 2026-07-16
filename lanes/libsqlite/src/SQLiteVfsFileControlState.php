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
            'size_limit' => self::optionalNonNegativeInt($fileControls['size_limit'] ?? null, 'SQLite VFS size limit'),
            'reserve_bytes' => self::reserveBytes($fileControls['reserve_bytes'] ?? 0),
            'lock_timeout' => self::nonNegativeInt($fileControls['lock_timeout'] ?? 0, 'SQLite VFS lock timeout'),
            'data_version' => self::positiveInt($fileControls['data_version'] ?? 1, 'SQLite VFS data version'),
            'has_moved' => false,
            'atomic_write_active' => false,
            'atomic_write_generation' => self::nonNegativeInt($fileControls['atomic_write_generation'] ?? 0, 'SQLite VFS atomic-write generation'),
            'last_sync_flags' => null,
            'sync_count' => self::nonNegativeInt($fileControls['sync_count'] ?? 0, 'SQLite VFS sync count'),
            'commit_phase_two_count' => self::nonNegativeInt($fileControls['commit_phase_two_count'] ?? 0, 'SQLite VFS commit phase-two count'),
            'write_hint_bytes' => self::optionalNonNegativeInt($fileControls['write_hint_bytes'] ?? null, 'SQLite VFS write hint bytes'),
            'overwrite_pages' => [],
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
            'size_limit' => $this->sizeLimit($argument),
            'reserve_bytes' => $this->reserveBytesControl($argument),
            'lock_timeout' => $this->lockTimeout($argument),
            'data_version' => $this->readOnlyValue('data_version', $op),
            'has_moved' => $this->hasMoved($argument),
            'tempfilename', 'temp_filename' => $this->tempFilename($argument),
            'begin_atomic_write' => $this->beginAtomicWrite(),
            'commit_atomic_write' => $this->commitAtomicWrite(),
            'rollback_atomic_write' => $this->rollbackAtomicWrite(),
            'sync' => $this->syncControl($argument),
            'commit_phasetwo', 'commit_phase_two' => $this->commitPhaseTwo(),
            'write_hint' => $this->writeHint($argument),
            'overwrite' => $this->overwrite($argument),
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
     * @param array<string|int, mixed> $controls
     * @return array{status:string,count:int,pairs:list<array{ordinal:int,op:string,current:array<string, mixed>,next:array<string, mixed>,result:array<string, mixed>}>,controls:array<string, mixed>,dependencies:list<string>}
     */
    public function fileControlSnapshotSequence(array $controls): array
    {
        $pairs = [];
        $ordinal = 0;
        foreach ($controls as $op => $argument) {
            if (is_int($op)) {
                if (!is_array($argument) || !array_key_exists('op', $argument)) {
                    throw new \InvalidArgumentException('SQLite VFS file-control snapshot sequence item requires an op');
                }
                $op = (string) $argument['op'];
                $argument = $argument['value'] ?? null;
            } else {
                $op = (string) $op;
            }

            $current = $this->fileControlSnapshot();
            $result = $this->apply($op, $argument);
            $pairs[] = [
                'ordinal' => $ordinal++,
                'op' => (string) $result['op'],
                'current' => $current,
                'next' => $this->fileControlSnapshot(),
                'result' => $result,
            ];
        }

        return [
            'status' => 'ok',
            'count' => count($pairs),
            'pairs' => $pairs,
            'controls' => $this->controls,
            'dependencies' => array_values(array_unique(array_merge($this->dependencies(), ['vfs-file-control-snapshot-sequence']))),
        ];
    }

    /**
     * @param array<string|int, mixed> $controls
     * @return array{status:string,count:int,pairs:list<array{ordinal:int,op:string,current:array<string, mixed>,next:array<string, mixed>,result:array<string, mixed>}>,controls:array<string, mixed>,dependencies:list<string>}
     */
    public function transactionFileControlSequence(array $controls): array
    {
        $pairs = [];
        $ordinal = 0;
        foreach ($controls as $op => $argument) {
            if (is_int($op)) {
                if (!is_array($argument) || !array_key_exists('op', $argument)) {
                    throw new \InvalidArgumentException('SQLite VFS transaction file-control sequence item requires an op');
                }
                $op = (string) $argument['op'];
                $argument = $argument['value'] ?? null;
            } else {
                $op = (string) $op;
            }

            $current = $this->transactionFileControlSnapshot();
            $result = $this->apply($op, $argument);
            $pairs[] = [
                'ordinal' => $ordinal++,
                'op' => (string) $result['op'],
                'current' => $current,
                'next' => $this->transactionFileControlSnapshot(),
                'result' => $result,
            ];
        }

        return [
            'status' => 'ok',
            'count' => count($pairs),
            'pairs' => $pairs,
            'controls' => $this->controls,
            'dependencies' => array_values(array_unique(array_merge($this->dependencies(), ['vfs-transaction-file-control-sequence']))),
        ];
    }

    /**
     * @param array<string|int, mixed> $controls
     * @return array{status:string,count:int,applied:int,ignored:int,notfound:int,changed:int,pairs:list<array{ordinal:int,source:mixed,op:string,current:array<string, mixed>,next:array<string, mixed>,result:array<string, mixed>}>,controls:array<string, mixed>,dependencies:list<string>}
     */
    public function sqlFileControlSequence(array $controls): array
    {
        $pairs = [];
        $applied = 0;
        $ignored = 0;
        $notfound = 0;
        $changed = 0;
        $ordinal = 0;

        foreach ($controls as $op => $argument) {
            $source = is_int($op) ? $argument : [$op => $argument];
            [$normalizedOp, $value] = $this->normalizeSqlFileControl($op, $argument);
            $current = $this->sqlFileControlSnapshot();
            $result = $this->apply($normalizedOp, $value);
            $status = (string) $result['status'];

            if ($status === 'ok') {
                $applied++;
            } elseif ($status === 'ignored') {
                $ignored++;
            } elseif ($status === 'notfound') {
                $notfound++;
            }
            if ($result['changed']) {
                $changed++;
            }

            $pairs[] = [
                'ordinal' => $ordinal++,
                'source' => $source,
                'op' => (string) $result['op'],
                'current' => $current,
                'next' => $this->sqlFileControlSnapshot(),
                'result' => $result,
            ];
        }

        return [
            'status' => 'ok',
            'count' => count($pairs),
            'applied' => $applied,
            'ignored' => $ignored,
            'notfound' => $notfound,
            'changed' => $changed,
            'pairs' => $pairs,
            'controls' => $this->controls,
            'dependencies' => array_values(array_unique(array_merge($this->dependencies(), ['vfs-sql-file-control-sequence']))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileControlSnapshot(): array
    {
        return [
            'size_limit' => $this->controls['size_limit'],
            'mmap_size' => $this->controls['mmap_size'],
            'chunk_size' => $this->controls['chunk_size'],
            'lock_timeout' => $this->controls['lock_timeout'],
            'data_version' => $this->controls['data_version'],
            'reserve_bytes' => $this->controls['reserve_bytes'],
            'persist_wal' => $this->controls['persist_wal'],
            'powersafe_overwrite' => $this->controls['powersafe_overwrite'],
            'has_moved' => $this->controls['has_moved'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionFileControlSnapshot(): array
    {
        return array_merge($this->fileControlSnapshot(), [
            'atomic_write_active' => $this->controls['atomic_write_active'],
            'atomic_write_generation' => $this->controls['atomic_write_generation'],
            'last_sync_flags' => $this->controls['last_sync_flags'],
            'sync_count' => $this->controls['sync_count'],
            'commit_phase_two_count' => $this->controls['commit_phase_two_count'],
            'write_hint_bytes' => $this->controls['write_hint_bytes'],
            'overwrite_pages' => $this->controls['overwrite_pages'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sqlFileControlSnapshot(): array
    {
        return $this->transactionFileControlSnapshot() + [
            'name_hint' => $this->controls['name_hint'],
            'tempfile' => $this->controls['tempfile'],
            'sector_size' => $this->controls['sector_size'],
            'device_characteristics' => $this->controls['device_characteristics'],
        ];
    }

    /**
     * @return array{0:string,1:mixed}
     */
    private function normalizeSqlFileControl(string|int $op, mixed $argument): array
    {
        if (is_int($op)) {
            if (is_string($argument)) {
                return self::parseSqlFileControl($argument);
            }
            if (!is_array($argument) || !array_key_exists('op', $argument)) {
                throw new \InvalidArgumentException('SQLite VFS SQL file-control sequence item requires an op or SQL text');
            }

            return [(string) $argument['op'], $argument['value'] ?? null];
        }

        return [(string) $op, $argument];
    }

    /**
     * @return array{0:string,1:mixed}
     */
    private static function parseSqlFileControl(string $sql): array
    {
        $trimmed = trim(rtrim($sql, ';'));
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite VFS SQL file-control sequence SQL control is empty');
        }

        if (preg_match('/^pragma\s+(?:(?:main|temp)\s*\.\s*)?(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*(?:=\s*(?<equals>.+)|\(\s*(?<paren>[^)]*)\s*\))?$/i', $trimmed, $matches)) {
            $name = strtolower($matches['name']);
            $raw = ($matches['equals'] ?? '') !== '' ? $matches['equals'] : (($matches['paren'] ?? '') !== '' ? $matches['paren'] : null);
            $value = self::parseSqlFileControlValue($raw);

            return match ($name) {
                'mmap_size' => ['mmap_size', $value],
                'chunk_size' => ['chunk_size', $value],
                'size_limit', 'max_page_count' => ['size_limit', $value],
                'reserve_bytes' => ['reserve_bytes', $value],
                'lock_timeout', 'busy_timeout' => ['lock_timeout', $value],
                'data_version' => ['data_version', null],
                'journal_size_limit' => ['size_limit', $value],
                default => [$name, $value],
            };
        }

        if (preg_match('/^file_control\s*\(\s*(?<op>[A-Za-z_][A-Za-z0-9_-]*)\s*(?:,\s*(?<value>.*))?\)$/i', $trimmed, $matches)) {
            return [(string) $matches['op'], self::parseSqlFileControlValue($matches['value'] ?? null)];
        }

        throw new \InvalidArgumentException('SQLite VFS SQL file-control sequence SQL control is unsupported');
    }

    private static function parseSqlFileControlValue(mixed $value): mixed
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
        if (preg_match('/^0x[0-9a-f]+$/i', $trimmed)) {
            return intval($trimmed, 0);
        }
        if (
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
            || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
        ) {
            $quote = $trimmed[0];
            $inner = substr($trimmed, 1, -1);

            return str_replace($quote . $quote, $quote, $inner);
        }

        return $trimmed;
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
        if (is_int($this->controls['size_limit']) && $value > $this->controls['size_limit']) {
            return $this->result('ignored', 'size_hint', false, $value, null, 'size_hint_exceeds_size_limit');
        }

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
    private function sizeLimit(mixed $argument): array
    {
        $previous = $this->controls['size_limit'];
        if ($argument === null || $argument === -1) {
            return $this->result('ok', 'size_limit', false, $previous, $previous, 'current_size_limit_returned');
        }

        $value = self::nonNegativeInt($argument, 'SQLite VFS size limit');
        $this->controls['size_limit'] = $value;

        return $this->result('ok', 'size_limit', $value !== $previous, $value, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function reserveBytesControl(mixed $argument): array
    {
        if ($this->readOnly || $this->immutable || $this->memory) {
            return $this->result('ignored', 'reserve_bytes', false, $this->controls['reserve_bytes'], $this->controls['reserve_bytes'], 'reserve_bytes_requires_writable_file_handle');
        }

        $previous = $this->controls['reserve_bytes'];
        if ($argument === null || $argument === -1) {
            return $this->result('ok', 'reserve_bytes', false, $previous, $previous, 'current_reserve_bytes_returned');
        }

        $value = self::reserveBytes($argument);
        $this->controls['reserve_bytes'] = $value;

        return $this->result('ok', 'reserve_bytes', $value !== $previous, $value, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function lockTimeout(mixed $argument): array
    {
        if ($this->memory || $this->nolock) {
            return $this->result('ignored', 'lock_timeout', false, $this->controls['lock_timeout'], $this->controls['lock_timeout'], 'lock_timeout_requires_lockable_file');
        }

        $previous = $this->controls['lock_timeout'];
        $value = self::nonNegativeInt($argument, 'SQLite VFS lock timeout');
        $this->controls['lock_timeout'] = $value;

        return $this->result('ok', 'lock_timeout', $value !== $previous, $value, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function hasMoved(mixed $argument): array
    {
        if ($argument === null) {
            return $this->readOnlyValue('has_moved', 'has_moved');
        }
        if (!is_string($argument) || trim($argument) === '') {
            throw new \InvalidArgumentException('SQLite VFS has_moved comparison requires a non-empty path');
        }

        $previous = (bool) $this->controls['has_moved'];
        $moved = $argument !== $this->path;
        $this->controls['has_moved'] = $moved;

        return $this->result('ok', 'has_moved', $moved !== $previous, $moved, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function tempFilename(mixed $argument): array
    {
        $suffix = is_string($argument) && trim($argument) !== '' ? trim($argument) : 'sqlite';
        if (str_contains($suffix, "\0") || str_contains($suffix, '/') || str_contains($suffix, '\\')) {
            throw new \InvalidArgumentException('SQLite VFS temp filename suffix must be a plain path segment');
        }

        $hint = is_string($this->controls['name_hint']) ? preg_replace('/[^A-Za-z0-9_.-]+/', '-', $this->controls['name_hint']) : 'sqlite';
        $value = rtrim(dirname($this->path), '/') . '/etilqs_' . substr(hash('sha256', $this->path . '|' . $hint . '|' . $suffix), 0, 16) . '.' . $suffix;

        return $this->result('ok', 'tempfilename', false, $value, null, 'generated_temp_filename');
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function beginAtomicWrite(): array
    {
        if ($this->readOnly || $this->immutable || $this->memory) {
            return $this->result('ignored', 'begin_atomic_write', false, $this->controls['atomic_write_active'], $this->controls['atomic_write_active'], 'atomic_write_requires_writable_file_handle');
        }

        $previous = (bool) $this->controls['atomic_write_active'];
        $this->controls['atomic_write_active'] = true;

        return $this->result('ok', 'begin_atomic_write', !$previous, true, $previous, $previous ? 'atomic_write_already_active' : null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function commitAtomicWrite(): array
    {
        if (!$this->controls['atomic_write_active']) {
            return $this->result('ignored', 'commit_atomic_write', false, false, false, 'atomic_write_not_active');
        }

        $previous = true;
        $this->controls['atomic_write_active'] = false;
        $this->controls['atomic_write_generation']++;

        return $this->result('ok', 'commit_atomic_write', true, false, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function rollbackAtomicWrite(): array
    {
        if (!$this->controls['atomic_write_active']) {
            return $this->result('ignored', 'rollback_atomic_write', false, false, false, 'atomic_write_not_active');
        }

        $previous = true;
        $this->controls['atomic_write_active'] = false;

        return $this->result('ok', 'rollback_atomic_write', true, false, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function syncControl(mixed $argument): array
    {
        if ($this->readOnly || $this->memory) {
            return $this->result('ignored', 'sync', false, $this->controls['last_sync_flags'], $this->controls['last_sync_flags'], 'sync_requires_writable_file_handle');
        }

        $previous = $this->controls['last_sync_flags'];
        $flags = self::syncFlags($argument);
        $this->controls['last_sync_flags'] = $flags;
        $this->controls['sync_count']++;

        return $this->result('ok', 'sync', $flags !== $previous, $flags, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function commitPhaseTwo(): array
    {
        if ($this->readOnly || $this->memory) {
            return $this->result('ignored', 'commit_phasetwo', false, $this->controls['commit_phase_two_count'], $this->controls['commit_phase_two_count'], 'commit_phase_two_requires_writable_file_handle');
        }

        $previous = $this->controls['commit_phase_two_count'];
        $this->controls['commit_phase_two_count']++;

        return $this->result('ok', 'commit_phasetwo', true, $this->controls['commit_phase_two_count'], $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function writeHint(mixed $argument): array
    {
        if ($this->readOnly || $this->immutable || $this->memory) {
            return $this->result('ignored', 'write_hint', false, $this->controls['write_hint_bytes'], $this->controls['write_hint_bytes'], 'write_hint_requires_writable_file_handle');
        }

        $previous = $this->controls['write_hint_bytes'];
        $value = $argument === null ? null : self::nonNegativeInt($argument, 'SQLite VFS write hint bytes');
        $this->controls['write_hint_bytes'] = $value;

        return $this->result('ok', 'write_hint', $value !== $previous, $value, $previous, null);
    }

    /**
     * @return array{status:string,op:string,path:string,changed:bool,value:mixed,previous:mixed,reason:string|null,controls:array<string, mixed>,dependencies:list<string>}
     */
    private function overwrite(mixed $argument): array
    {
        if ($this->readOnly || $this->immutable || $this->memory) {
            return $this->result('ignored', 'overwrite', false, $this->controls['overwrite_pages'], $this->controls['overwrite_pages'], 'overwrite_requires_writable_file_handle');
        }

        $page = self::positiveInt($argument, 'SQLite VFS overwrite page');
        $previous = $this->controls['overwrite_pages'];
        if (!in_array($page, $this->controls['overwrite_pages'], true)) {
            $this->controls['overwrite_pages'][] = $page;
            sort($this->controls['overwrite_pages']);
        }

        return $this->result('ok', 'overwrite', $previous !== $this->controls['overwrite_pages'], $this->controls['overwrite_pages'], $previous, null);
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

    private static function reserveBytes(mixed $value): int
    {
        if (!is_int($value) || $value < 0 || $value > 255) {
            throw new \InvalidArgumentException('SQLite VFS reserve bytes must be an integer between 0 and 255');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function syncFlags(mixed $value): array
    {
        if ($value === null) {
            return ['normal'];
        }
        if (is_string($value)) {
            $parts = preg_split('/[|,\s]+/', strtolower(trim($value))) ?: [];
        } elseif (is_array($value)) {
            $parts = array_map(static fn (mixed $part): string => strtolower(trim((string) $part)), $value);
        } else {
            throw new \InvalidArgumentException('SQLite VFS sync flags must be a string or list');
        }

        $flags = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (!in_array($part, ['normal', 'full', 'dataonly'], true)) {
                throw new \InvalidArgumentException('SQLite VFS sync flag is invalid');
            }
            $flags[] = $part;
        }

        return $flags === [] ? ['normal'] : array_values(array_unique($flags));
    }
}
