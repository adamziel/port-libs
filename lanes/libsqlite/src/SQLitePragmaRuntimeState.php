<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaRuntimeState
{
    /** @var array<string,array{schema_version:int,user_version:int,cache_size:int,cache_spill:int|null,dirty_pages:int,lock:string,file:string|null}> */
    private array $schemas = [];

    /** @var array<string,array{schema_version:int,user_version:int,cache_size:int,cache_spill:int|null,dirty_pages:int,lock:string,file:string|null}>|null */
    private ?array $transactionSnapshot = null;

    public function __construct(
        int $schemaVersion = 0,
        int $userVersion = 0,
        int $cacheSize = 2000,
        ?int $cacheSpill = null,
    ) {
        $this->schemas['main'] = $this->schemaState($schemaVersion, $userVersion, $cacheSize, $cacheSpill, null);
        $this->schemas['temp'] = $this->schemaState(0, 0, $cacheSize, $cacheSpill, '');
    }

    /**
     * @return array{schema:string,schema_version:int,user_version:int,cache_size:int,cache_spill:int,lock:string}
     */
    public function pragma(string $sql, bool $defensive = false): array
    {
        $parsed = $this->parsePragma($sql);
        $schema = $parsed['schema'];
        $name = $parsed['name'];
        $value = $parsed['value'];
        $this->assertSchema($schema);

        if ($name === 'schema_version') {
            if ($value !== null && !$defensive) {
                $this->schemas[$schema]['schema_version'] = $this->integerValue($value, 'schema_version');
            }

            return $this->stateRow($schema);
        }

        if ($name === 'user_version') {
            if ($value !== null) {
                $this->schemas[$schema]['user_version'] = $this->integerValue($value, 'user_version');
            }

            return $this->stateRow($schema);
        }

        if ($name === 'cache_size') {
            if ($value !== null) {
                $this->schemas[$schema]['cache_size'] = max(0, $this->integerValue($value, 'cache_size'));
            }

            return $this->stateRow($schema);
        }

        if ($name === 'cache_spill') {
            if ($value !== null) {
                if ($schema === 'main' && !$parsed['qualified']) {
                    foreach (array_keys($this->schemas) as $schemaName) {
                        $this->schemas[$schemaName]['cache_spill'] = $this->cacheSpillValue($value, $this->schemas[$schemaName]['cache_size']);
                    }
                } else {
                    $spill = $this->cacheSpillValue($value, $this->schemas[$schema]['cache_size']);
                    $this->schemas[$schema]['cache_spill'] = $spill;
                }
            }

            return $this->stateRow($schema);
        }

        throw new InvalidArgumentException("SQLite runtime pragma {$name} is not supported");
    }

    /**
     * @return array{operation:string,schema:string,file:string|null,schema_version:int,user_version:int,cache_size:int,cache_spill:int,lock:string}
     */
    public function attach(string $schemaName, string $fileName): array
    {
        $schema = $this->normalizeSchema($schemaName);
        if ($schema === 'main' || $schema === 'temp') {
            throw new InvalidArgumentException('SQLite ATTACH runtime schema cannot be main or temp');
        }
        if (isset($this->schemas[$schema])) {
            throw new InvalidArgumentException("SQLite ATTACH runtime schema {$schema} is already attached");
        }

        $main = $this->schemas['main'];
        $this->schemas[$schema] = $this->schemaState(0, 0, $main['cache_size'], $main['cache_spill'], $fileName);

        return ['operation' => 'attach', 'schema' => $schema] + $this->stateRow($schema);
    }

    /**
     * @return array{operation:string,schema:string}
     */
    public function detach(string $schemaName): array
    {
        $schema = $this->normalizeSchema($schemaName);
        if ($schema === 'main' || $schema === 'temp') {
            throw new InvalidArgumentException('SQLite DETACH runtime schema cannot detach main or temp');
        }
        $this->assertSchema($schema);
        unset($this->schemas[$schema]);

        return ['operation' => 'detach', 'schema' => $schema];
    }

    public function begin(): void
    {
        if ($this->transactionSnapshot !== null) {
            throw new InvalidArgumentException('SQLite runtime pragma transaction is already active');
        }

        $this->transactionSnapshot = $this->schemas;
    }

    public function commit(): void
    {
        if ($this->transactionSnapshot === null) {
            throw new InvalidArgumentException('SQLite runtime pragma transaction is not active');
        }

        $this->transactionSnapshot = null;
        $this->clearDirtyPages();
    }

    public function rollback(): void
    {
        if ($this->transactionSnapshot === null) {
            throw new InvalidArgumentException('SQLite runtime pragma transaction is not active');
        }

        $this->schemas = $this->transactionSnapshot;
        $this->transactionSnapshot = null;
        $this->clearDirtyPages();
    }

    /**
     * @return array{schema:string,dirty_pages:int,cache_spill:int,lock:string}
     */
    public function dirtyPages(string $schemaName, int $pageCount): array
    {
        $schema = $this->normalizeSchema($schemaName);
        $this->assertSchema($schema);
        if ($pageCount < 0) {
            throw new InvalidArgumentException('SQLite runtime dirty page count must be non-negative');
        }

        $this->schemas[$schema]['dirty_pages'] = $pageCount;
        $threshold = $this->cacheSpillEffectiveValue($schema);
        $this->schemas[$schema]['lock'] = $threshold > 0 && $pageCount >= $threshold ? 'exclusive' : 'reserved';

        return [
            'schema' => $schema,
            'dirty_pages' => $pageCount,
            'cache_spill' => $threshold,
            'lock' => $this->schemas[$schema]['lock'],
        ];
    }

    /**
     * @return list<array{schema:string,lock:string}>
     */
    public function lockStatus(): array
    {
        $rows = [];
        foreach ($this->schemas as $schema => $state) {
            $rows[] = ['schema' => $schema, 'lock' => $state['lock']];
        }

        return $rows;
    }

    /**
     * @return array{schema:string,schema_version:int,user_version:int,cache_size:int,cache_spill:int,lock:string,file:string|null}
     */
    public function state(string $schemaName = 'main'): array
    {
        $schema = $this->normalizeSchema($schemaName);
        $this->assertSchema($schema);

        return $this->stateRow($schema);
    }

    /**
     * @return array<string,array{schema_version:int,user_version:int,cache_size:int,cache_spill:int|null,dirty_pages:int,lock:string,file:string|null}>
     */
    public function snapshot(): array
    {
        return $this->schemas;
    }

    /**
     * @return array{schema:string,name:string,value:string|null,qualified:bool}
     */
    private function parsePragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\.)?(?<name>schema_version|user_version|cache_size|cache_spill)\s*(?:=\s*(?<eq>.+)|\(\s*(?<paren>.*?)\s*\))?$/i', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('SQLite runtime pragma SQL is not supported');
        }

        $schema = isset($matches['schema']) && $matches['schema'] !== '' ? $this->normalizeSchema($matches['schema']) : 'main';
        $value = null;
        if (isset($matches['eq']) && $matches['eq'] !== '') {
            $value = trim($matches['eq']);
        } elseif (isset($matches['paren']) && $matches['paren'] !== '') {
            $value = trim($matches['paren']);
        }

        return [
            'schema' => $schema,
            'name' => strtolower($matches['name']),
            'value' => $value,
            'qualified' => isset($matches['schema']) && $matches['schema'] !== '',
        ];
    }

    /**
     * @return array{schema_version:int,user_version:int,cache_size:int,cache_spill:int|null,dirty_pages:int,lock:string,file:string|null}
     */
    private function schemaState(int $schemaVersion, int $userVersion, int $cacheSize, ?int $cacheSpill, ?string $fileName): array
    {
        return [
            'schema_version' => $schemaVersion,
            'user_version' => $userVersion,
            'cache_size' => max(0, $cacheSize),
            'cache_spill' => $cacheSpill,
            'dirty_pages' => 0,
            'lock' => $fileName === '' ? 'closed' : 'unlocked',
            'file' => $fileName,
        ];
    }

    /**
     * @return array{schema:string,schema_version:int,user_version:int,cache_size:int,cache_spill:int,lock:string,file:string|null}
     */
    private function stateRow(string $schema): array
    {
        $state = $this->schemas[$schema];

        return [
            'schema' => $schema,
            'schema_version' => $state['schema_version'],
            'user_version' => $state['user_version'],
            'cache_size' => $state['cache_size'],
            'cache_spill' => $this->cacheSpillEffectiveValue($schema),
            'lock' => $state['lock'],
            'file' => $state['file'],
        ];
    }

    private function cacheSpillEffectiveValue(string $schema): int
    {
        $state = $this->schemas[$schema];
        if ($state['cache_spill'] === null) {
            return $state['cache_size'];
        }

        if ($state['cache_spill'] <= 0) {
            return 0;
        }

        return max($state['cache_size'], $state['cache_spill']);
    }

    private function cacheSpillValue(string $value, int $cacheSize): int
    {
        $normalized = strtolower(trim($value, " \t\r\n'\""));
        if (in_array($normalized, ['on', 'yes', 'true'], true)) {
            return $cacheSize;
        }
        if (in_array($normalized, ['off', 'no', 'false'], true)) {
            return 0;
        }

        $integer = $this->integerValue($value, 'cache_spill');
        if ($integer < 0) {
            return $cacheSize;
        }

        return $integer;
    }

    private function integerValue(string $value, string $label): int
    {
        $trimmed = trim($value, " \t\r\n'\"");
        if (preg_match('/^[+-]?\d+$/', $trimmed) !== 1) {
            throw new InvalidArgumentException("SQLite runtime {$label} value must be an integer");
        }

        return (int) $trimmed;
    }

    private function normalizeSchema(string $schema): string
    {
        $normalized = strtolower(trim($schema, " \t\r\n`\"[]"));
        if ($normalized === '' || preg_match('/^[a-z_][a-z0-9_]*$/', $normalized) !== 1) {
            throw new InvalidArgumentException('SQLite runtime schema name must be a bounded identifier');
        }

        return $normalized;
    }

    private function assertSchema(string $schema): void
    {
        if (!isset($this->schemas[$schema])) {
            throw new InvalidArgumentException("SQLite runtime schema {$schema} is not attached");
        }
    }

    private function clearDirtyPages(): void
    {
        foreach (array_keys($this->schemas) as $schema) {
            $this->schemas[$schema]['dirty_pages'] = 0;
            $this->schemas[$schema]['lock'] = $schema === 'temp' ? 'closed' : 'unlocked';
        }
    }
}
