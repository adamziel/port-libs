<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaJournalState
{
    /** @var array<string, array{synchronous:int,journal_mode:string,temporary:bool,memory:bool,wal_capable:bool}> */
    private array $schemas = [];

    private bool $transactionActive = false;

    /**
     * @param array<string, array{synchronous?:int|string,journal_mode?:string,temporary?:bool,memory?:bool,wal_capable?:bool}> $schemas
     */
    public function __construct(array $schemas = [])
    {
        $this->schemas['main'] = $this->normalizeSchemaState($schemas['main'] ?? []);
        $this->schemas['temp'] = $this->normalizeSchemaState(($schemas['temp'] ?? []) + ['temporary' => true, 'journal_mode' => 'delete']);

        foreach ($schemas as $schema => $state) {
            $name = strtolower(trim((string) $schema));
            if ($name === '' || isset($this->schemas[$name])) {
                continue;
            }
            $this->schemas[$name] = $this->normalizeSchemaState($state);
        }
    }

    /**
     * @return array{status:string,pragma:'synchronous'|'journal_mode',schema:string,requested:mixed,effective:int|string,changed:bool,rows:list<array<string,int|string>>,reason:string|null,dependencies:list<string>}
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $schema = $parsed['schema'] ?? 'main';
        $this->ensureSchema($schema);

        if ($parsed['pragma'] === 'synchronous') {
            return $this->executeSynchronous($schema, $parsed['value']);
        }

        if ($parsed['value'] !== null && $schema === 'main' && !self::hasExplicitSchema($sql)) {
            $result = $this->executeJournalMode($schema, $parsed['value']);
            foreach (array_keys($this->schemas) as $attachedSchema) {
                if ($attachedSchema === 'main' || $attachedSchema === 'temp') {
                    continue;
                }
                $this->executeJournalMode($attachedSchema, $parsed['value']);
            }

            return $result;
        }

        return $this->executeJournalMode($schema, $parsed['value']);
    }

    /**
     * @return array{schema:string,pragma:'synchronous'|'journal_mode',value:string|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = trim($sql);
        $trimmed = rtrim($trimmed, " \t\r\n;");

        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>synchronous|journal_mode)(?:\s*(?:=\s*(?<equals>[A-Za-z_][A-Za-z0-9_]*|\d+)|\(\s*(?<paren>[A-Za-z_][A-Za-z0-9_]*|\d+)\s*\)))?$/i', $trimmed, $matches)) {
            throw new \InvalidArgumentException('Unsupported SQLite PRAGMA journal/synchronous SQL');
        }

        $value = null;
        if (($matches['equals'] ?? '') !== '') {
            $value = $matches['equals'];
        } elseif (($matches['paren'] ?? '') !== '') {
            $value = $matches['paren'];
        }

        return [
            'schema' => strtolower($matches['schema'] !== '' ? $matches['schema'] : 'main'),
            'pragma' => strtolower($matches['pragma']) === 'synchronous' ? 'synchronous' : 'journal_mode',
            'value' => $value,
        ];
    }

    private static function hasExplicitSchema(string $sql): bool
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");

        return preg_match('/^pragma\s+[A-Za-z_][A-Za-z0-9_]*\s*\.\s*(?:synchronous|journal_mode)\b/i', $trimmed) === 1;
    }

    /**
     * @return array<string, array{synchronous:int,journal_mode:string,temporary:bool,memory:bool,wal_capable:bool}>
     */
    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * @return array{status:string,transaction_active:true}
     */
    public function begin(): array
    {
        if ($this->transactionActive) {
            throw new \InvalidArgumentException('SQLite PRAGMA journal transaction is already active');
        }

        $this->transactionActive = true;

        return ['status' => 'ok', 'transaction_active' => true];
    }

    /**
     * @return array{status:string,transaction_active:false}
     */
    public function commit(): array
    {
        if (!$this->transactionActive) {
            throw new \InvalidArgumentException('SQLite PRAGMA journal transaction is not active');
        }

        $this->transactionActive = false;

        return ['status' => 'ok', 'transaction_active' => false];
    }

    /**
     * @return array{status:string,transaction_active:false}
     */
    public function rollback(): array
    {
        if (!$this->transactionActive) {
            throw new \InvalidArgumentException('SQLite PRAGMA journal transaction is not active');
        }

        $this->transactionActive = false;

        return ['status' => 'ok', 'transaction_active' => false];
    }

    /**
     * @param array{synchronous?:int|string,journal_mode?:string,temporary?:bool,memory?:bool,wal_capable?:bool} $state
     * @return array{synchronous:int,journal_mode:string,temporary:bool,memory:bool,wal_capable:bool}
     */
    private function normalizeSchemaState(array $state): array
    {
        $temporary = (bool) ($state['temporary'] ?? false);
        $memory = (bool) ($state['memory'] ?? false);
        $journalMode = self::normalizeJournalMode((string) ($state['journal_mode'] ?? ($memory ? 'memory' : 'delete')));
        if ($temporary && in_array($journalMode, ['wal', 'off'], true)) {
            $journalMode = 'delete';
        }
        if ($memory && $journalMode === 'wal') {
            $journalMode = 'memory';
        }

        return [
            'synchronous' => self::normalizeSynchronous($state['synchronous'] ?? 'full'),
            'journal_mode' => $journalMode,
            'temporary' => $temporary,
            'memory' => $memory,
            'wal_capable' => (bool) ($state['wal_capable'] ?? !$memory),
        ];
    }

    private function ensureSchema(string $schema): void
    {
        if (!isset($this->schemas[$schema])) {
            $this->schemas[$schema] = $this->normalizeSchemaState([]);
        }
    }

    /**
     * @return array{status:string,pragma:'synchronous',schema:string,requested:int|string|null,effective:int,changed:bool,rows:list<array{synchronous:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executeSynchronous(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['synchronous'];
        $effective = $before;
        $reason = null;

        if ($requested !== null) {
            if ($this->transactionActive) {
                throw new \RuntimeException('Safety level may not be changed inside a transaction');
            }
            $effective = self::normalizeSynchronous($requested);
            $this->schemas[$schema]['synchronous'] = $effective;
        }

        return [
            'status' => 'ok',
            'pragma' => 'synchronous',
            'schema' => $schema,
            'requested' => $requested,
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['synchronous' => $effective]],
            'reason' => $reason,
            'dependencies' => ['sqlite-pragma-synchronous-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'journal_mode',schema:string,requested:string|null,effective:string,changed:bool,rows:list<array{journal_mode:string}>,reason:string|null,dependencies:list<string>}
     */
    private function executeJournalMode(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['journal_mode'];
        $effective = $before;
        $reason = null;

        if ($requested !== null) {
            $mode = self::normalizeJournalMode($requested);
            if ($this->schemas[$schema]['temporary'] && in_array($mode, ['wal', 'off'], true)) {
                $reason = 'temporary_schema_keeps_delete_journal';
                $effective = 'delete';
            } elseif ($this->schemas[$schema]['memory'] && $mode === 'wal') {
                $reason = 'memory_database_cannot_enter_wal';
                $effective = 'memory';
            } elseif ($mode === 'wal' && !$this->schemas[$schema]['wal_capable']) {
                $reason = 'vfs_not_wal_capable';
                $effective = $before;
            } else {
                $effective = $mode;
            }
            $this->schemas[$schema]['journal_mode'] = $effective;

            if ($effective === 'wal' && $this->schemas[$schema]['synchronous'] === 2) {
                $this->schemas[$schema]['synchronous'] = 1;
            }
        }

        return [
            'status' => 'ok',
            'pragma' => 'journal_mode',
            'schema' => $schema,
            'requested' => $requested === null ? null : strtolower($requested),
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['journal_mode' => $effective]],
            'reason' => $reason,
            'dependencies' => ['sqlite-pragma-journal-mode-state'],
        ];
    }

    private static function normalizeSynchronous(int|string $value): int
    {
        $normalized = is_int($value) || ctype_digit((string) $value)
            ? (int) $value
            : match (strtolower(trim((string) $value))) {
                'off' => 0,
                'normal' => 1,
                'full' => 2,
                'extra' => 3,
                default => -1,
            };

        if (!in_array($normalized, [0, 1, 2, 3], true)) {
            throw new \InvalidArgumentException('Unsupported SQLite synchronous mode');
        }

        return $normalized;
    }

    private static function normalizeJournalMode(string $value): string
    {
        $mode = strtolower(trim($value));
        if (!in_array($mode, ['delete', 'truncate', 'persist', 'memory', 'wal', 'off'], true)) {
            throw new \InvalidArgumentException('Unsupported SQLite journal mode');
        }

        return $mode;
    }
}
