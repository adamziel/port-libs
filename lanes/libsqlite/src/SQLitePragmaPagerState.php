<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaPagerState
{
    /** @var array<string, array{cache_size:int, default_cache_size:int, synchronous:int, dirty_default:bool}> */
    private array $schemas = [];

    private int $builtInDefaultCacheSize;

    /**
     * @param array<string, array<string, int|string|bool>> $schemas
     */
    public function __construct(array $schemas = [], int $builtInDefaultCacheSize = 2000)
    {
        if ($builtInDefaultCacheSize < 1) {
            throw new InvalidArgumentException('built-in default cache size must be positive');
        }

        $this->builtInDefaultCacheSize = $builtInDefaultCacheSize;
        if ($schemas === []) {
            $schemas = [
                'main' => [],
                'temp' => [],
            ];
        }

        foreach ($schemas as $schema => $state) {
            $name = self::normalizeSchemaName((string) $schema);
            $default = self::normalizeDefaultCacheSize((int) ($state['default_cache_size'] ?? $builtInDefaultCacheSize), $builtInDefaultCacheSize);
            $this->schemas[$name] = [
                'cache_size' => (int) ($state['cache_size'] ?? $default),
                'default_cache_size' => $default,
                'synchronous' => self::normalizeSynchronous($state['synchronous'] ?? 2),
                'dirty_default' => (bool) ($state['dirty_default'] ?? false),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $schema = $parsed['schema'];
        $pragma = $parsed['pragma'];
        $value = $parsed['value'];
        $this->ensureSchema($schema);

        if ($value === null) {
            return $this->row($schema, $pragma, false, 'current');
        }

        if ($pragma === 'cache_size') {
            $old = $this->schemas[$schema]['cache_size'];
            $this->schemas[$schema]['cache_size'] = self::normalizeCacheSize($value);

            return $this->row($schema, $pragma, $old !== $this->schemas[$schema]['cache_size'], 'assigned_connection_local');
        }

        if ($pragma === 'default_cache_size') {
            $old = $this->schemas[$schema]['default_cache_size'];
            $default = self::normalizeDefaultCacheSize((int) $value, $this->builtInDefaultCacheSize);
            $this->schemas[$schema]['default_cache_size'] = $default;
            $this->schemas[$schema]['cache_size'] = $default;
            $this->schemas[$schema]['dirty_default'] = $old !== $default;

            return $this->row($schema, $pragma, $old !== $default, $value === '0' || $value === 0 ? 'reset_to_builtin_default' : 'assigned_persistent_default');
        }

        $old = $this->schemas[$schema]['synchronous'];
        $this->schemas[$schema]['synchronous'] = self::normalizeSynchronous($value);

        return $this->row($schema, $pragma, $old !== $this->schemas[$schema]['synchronous'], 'assigned_connection_local');
    }

    /**
     * @return array{status:string, schema:string, inherited_cache_spill:bool, cache_size:int, default_cache_size:int, synchronous:int}
     */
    public function attach(string $schema, bool $inheritCacheSpill = true): array
    {
        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);

        return [
            'status' => 'ok',
            'schema' => $schema,
            'inherited_cache_spill' => $inheritCacheSpill,
            'cache_size' => $this->schemas[$schema]['cache_size'],
            'default_cache_size' => $this->schemas[$schema]['default_cache_size'],
            'synchronous' => $this->schemas[$schema]['synchronous'],
        ];
    }

    /**
     * @return array{status:string, reopened:bool, schema_count:int}
     */
    public function reopen(): array
    {
        foreach ($this->schemas as &$state) {
            $state['cache_size'] = $state['default_cache_size'];
            $state['synchronous'] = 2;
        }
        unset($state);

        return [
            'status' => 'ok',
            'reopened' => true,
            'schema_count' => count($this->schemas),
        ];
    }

    /**
     * @return array<string, array<string, int|bool>>
     */
    public function state(): array
    {
        return $this->schemas;
    }

    /**
     * @return array{pragma:string, schema:string, value:int|string|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        $identifier = '[A-Za-z_][A-Za-z0-9_]*';
        $value = '(?:[-+]?\d+|OFF|ON|NORMAL|FULL|EXTRA)';
        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\.)?(?<pragma>cache_size|default_cache_size|synchronous)\s*(?:(?:=|\()\s*(?<value>' . $value . ')\s*\)?)?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException("Unsupported pager PRAGMA SQL: {$sql}");
        }

        return [
            'pragma' => strtolower($matches['pragma']),
            'schema' => self::normalizeSchemaName($matches['schema'] !== '' ? $matches['schema'] : 'main'),
            'value' => array_key_exists('value', $matches) && $matches['value'] !== '' ? $matches['value'] : null,
        ];
    }

    private function ensureSchema(string $schema): void
    {
        if (isset($this->schemas[$schema])) {
            return;
        }

        $this->schemas[$schema] = [
            'cache_size' => $this->builtInDefaultCacheSize,
            'default_cache_size' => $this->builtInDefaultCacheSize,
            'synchronous' => 2,
            'dirty_default' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $schema, string $pragma, bool $changed, string $reason): array
    {
        $value = $this->schemas[$schema][$pragma];

        return [
            'status' => 'ok',
            'pragma' => $pragma,
            'schema' => $schema,
            'value' => $value,
            'changed' => $changed,
            'reason' => $reason,
            'rows' => [[$pragma => $value]],
            'pager' => [
                'cache_size' => $this->schemas[$schema]['cache_size'],
                'default_cache_size' => $this->schemas[$schema]['default_cache_size'],
                'synchronous' => $this->schemas[$schema]['synchronous'],
                'dirty_default' => $this->schemas[$schema]['dirty_default'],
            ],
        ];
    }

    private static function normalizeCacheSize(int|string $value): int
    {
        $cacheSize = (int) $value;
        if ($cacheSize < -0x7fffffff || $cacheSize > 0x7fffffff) {
            throw new InvalidArgumentException('cache_size must fit in a signed 32-bit integer');
        }

        return $cacheSize;
    }

    private static function normalizeDefaultCacheSize(int $value, int $builtInDefaultCacheSize): int
    {
        if ($value === 0) {
            return $builtInDefaultCacheSize;
        }

        $default = abs($value);
        if ($default > 0x7fffffff) {
            throw new InvalidArgumentException('default_cache_size must fit in a signed 32-bit integer');
        }

        return $default;
    }

    private static function normalizeSynchronous(int|string|bool $value): int
    {
        if (is_string($value) && !is_numeric($value)) {
            return match (strtolower($value)) {
                'off' => 0,
                'normal', 'on' => 1,
                'full' => 2,
                'extra' => 3,
                default => throw new InvalidArgumentException("Unsupported synchronous value: {$value}"),
            };
        }

        $numeric = (int) $value;
        if ($numeric < 0) {
            throw new InvalidArgumentException('synchronous must be non-negative');
        }

        return $numeric <= 4 ? $numeric : $numeric % 4;
    }

    private static function normalizeSchemaName(string $schema): string
    {
        $schema = strtolower(trim($schema));
        if ($schema === '' || !preg_match('/^[a-z_][a-z0-9_]*$/', $schema)) {
            throw new InvalidArgumentException("Invalid schema name: {$schema}");
        }

        return $schema;
    }
}
