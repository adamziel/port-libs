<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaPagerState
{
    /** @var array<string, array{cache_size:int, default_cache_size:int, synchronous:int, page_size:int, cache_spill:int, dirty_default:bool}> */
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
            $cacheSize = (int) ($state['cache_size'] ?? $default);
            $pageSize = (int) ($state['page_size'] ?? 4096);
            $this->schemas[$name] = [
                'cache_size' => $cacheSize,
                'default_cache_size' => $default,
                'synchronous' => self::normalizeSynchronous($state['synchronous'] ?? 2),
                'page_size' => self::normalizePageSize($pageSize),
                'cache_spill' => self::normalizeCacheSpill($state['cache_spill'] ?? $cacheSize, $cacheSize, $pageSize),
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
            if ($this->schemas[$schema]['cache_spill'] > 0) {
                $this->schemas[$schema]['cache_spill'] = $this->schemas[$schema]['cache_size'];
            }

            return $this->row($schema, $pragma, $old !== $this->schemas[$schema]['cache_size'], 'assigned_connection_local');
        }

        if ($pragma === 'page_size') {
            $old = $this->schemas[$schema]['page_size'];
            $this->schemas[$schema]['page_size'] = self::normalizePageSize((int) $value);

            return $this->row($schema, $pragma, $old !== $this->schemas[$schema]['page_size'], 'assigned_connection_local');
        }

        if ($pragma === 'cache_spill') {
            $cacheSize = $this->schemas[$schema]['cache_size'];
            $pageSize = $this->schemas[$schema]['page_size'];
            $old = $this->schemas[$schema]['cache_spill'];
            $next = self::normalizeCacheSpill($value, $cacheSize, $pageSize);
            if ($schema === 'main' && !str_contains(rtrim(trim($sql), ';'), '.')) {
                foreach (array_keys($this->schemas) as $schemaName) {
                    $this->schemas[$schemaName]['cache_spill'] = self::normalizeCacheSpill(
                        $value,
                        $this->schemas[$schemaName]['cache_size'],
                        $this->schemas[$schemaName]['page_size'],
                    );
                }
            } else {
                $this->schemas[$schema]['cache_spill'] = $next;
            }

            return $this->row($schema, $pragma, $old !== $next, 'assigned_connection_local');
        }

        if ($pragma === 'default_cache_size') {
            $old = $this->schemas[$schema]['default_cache_size'];
            $default = self::normalizeDefaultCacheSize((int) $value, $this->builtInDefaultCacheSize);
            $this->schemas[$schema]['default_cache_size'] = $default;
            $this->schemas[$schema]['cache_size'] = $default;
            if ($this->schemas[$schema]['cache_spill'] > 0) {
                $this->schemas[$schema]['cache_spill'] = $default;
            }
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
        if (!$inheritCacheSpill) {
            $this->schemas[$schema]['cache_spill'] = $this->schemas[$schema]['cache_size'];
        } elseif (isset($this->schemas['main']) && $this->schemas['main']['cache_spill'] === 0) {
            $this->schemas[$schema]['cache_spill'] = 0;
        }

        return [
            'status' => 'ok',
            'schema' => $schema,
            'inherited_cache_spill' => $inheritCacheSpill,
            'cache_size' => $this->schemas[$schema]['cache_size'],
            'default_cache_size' => $this->schemas[$schema]['default_cache_size'],
            'synchronous' => $this->schemas[$schema]['synchronous'],
            'cache_spill' => $this->schemas[$schema]['cache_spill'],
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
            if ($state['cache_spill'] > 0) {
                $state['cache_spill'] = $state['cache_size'];
            }
        }
        unset($state);

        return [
            'status' => 'ok',
            'reopened' => true,
            'schema_count' => count($this->schemas),
        ];
    }

    /**
     * @return array{
     *     status:string,
     *     operation:string,
     *     schema:string,
     *     before_generation:int,
     *     after_generation:int,
     *     generation_changed:bool,
     *     cache_size:int,
     *     default_cache_size:int,
     *     synchronous:int,
     *     page_size:int,
     *     cache_spill:int,
     *     dirty_default:bool,
     *     rows:list<array{cache_size:int}>,
     *     reason:string,
     *     dependencies:list<string>
     * }
     */
    public function schemaReload(string $schema = 'main', int $beforeGeneration = 0, ?int $afterGeneration = null): array
    {
        if ($beforeGeneration < 0 || ($afterGeneration !== null && $afterGeneration < 0)) {
            throw new InvalidArgumentException('schema generations must be non-negative');
        }

        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);
        $afterGeneration ??= $beforeGeneration + 1;
        $generationChanged = $afterGeneration !== $beforeGeneration;
        $state = $this->schemas[$schema];

        return [
            'status' => 'ok',
            'operation' => 'pragma-schema-reload-pager-state',
            'schema' => $schema,
            'before_generation' => $beforeGeneration,
            'after_generation' => $afterGeneration,
            'generation_changed' => $generationChanged,
            'cache_size' => $state['cache_size'],
            'default_cache_size' => $state['default_cache_size'],
            'synchronous' => $state['synchronous'],
            'page_size' => $state['page_size'],
            'cache_spill' => $state['cache_spill'],
            'dirty_default' => $state['dirty_default'],
            'rows' => [['cache_size' => $state['cache_size']]],
            'reason' => $generationChanged
                ? 'schema_reload_preserves_connection_local_pager_pragmas'
                : 'schema_generation_unchanged',
            'dependencies' => ['sqlite-pragma-cache-size-state', 'sqlite-schema-cookie-live-reload'],
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
        $value = '(?:[-+]?\d+|OFF|ON|NO|YES|FALSE|TRUE|NORMAL|FULL|EXTRA)';
        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\.)?(?<pragma>cache_size|default_cache_size|synchronous|page_size|cache_spill)\s*(?:(?:=|\()\s*(?<value>' . $value . ')\s*\)?)?$/i', $trimmed, $matches)) {
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
            'page_size' => 4096,
            'cache_spill' => $this->builtInDefaultCacheSize,
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
                'page_size' => $this->schemas[$schema]['page_size'],
                'cache_spill' => $this->schemas[$schema]['cache_spill'],
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

    private static function normalizePageSize(int $value): int
    {
        if ($value < 512 || $value > 65536 || ($value & ($value - 1)) !== 0) {
            throw new InvalidArgumentException('page_size must be a power of two between 512 and 65536');
        }

        return $value;
    }

    private static function normalizeCacheSpill(int|string|bool $value, int $cacheSize, int $pageSize): int
    {
        if (is_string($value) && !is_numeric($value)) {
            return match (strtolower($value)) {
                'off', 'no', 'false' => 0,
                'on', 'yes', 'true' => $cacheSize,
                default => throw new InvalidArgumentException("Unsupported cache_spill value: {$value}"),
            };
        }

        $numeric = (int) $value;
        if ($numeric === 0) {
            return 0;
        }

        if ($numeric < 0) {
            return max(1, intdiv(abs($numeric), max(1, intdiv($pageSize, 1024))));
        }

        return max($cacheSize, $numeric);
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
