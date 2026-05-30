<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaDynamicSchemaState
{
    /** @var array<string, array{cache_size:int,default_cache_size:int,freelist_count:int,schema_version:int,user_version:int,database_empty:bool}> */
    private array $schemas = [];

    /**
     * @param array<string, array{cache_size?:int|string,default_cache_size?:int|string,freelist_count?:int|string,schema_version?:int|string,user_version?:int|string,database_empty?:bool}> $schemas
     */
    public function __construct(array $schemas = [])
    {
        $this->schemas['main'] = $this->normalizeSchema($schemas['main'] ?? []);
        $this->schemas['temp'] = $this->normalizeSchema(($schemas['temp'] ?? []) + [
            'database_empty' => false,
        ]);

        foreach ($schemas as $schema => $state) {
            $name = strtolower(trim((string) $schema));
            if ($name === '' || isset($this->schemas[$name])) {
                continue;
            }

            $this->schemas[$name] = $this->normalizeSchema($state);
        }
    }

    /**
     * @return array{
     *     status:string,
     *     pragma:'cache_size'|'default_cache_size'|'freelist_count'|'schema_version'|'user_version',
     *     schema:string,
     *     requested:int|null,
     *     value:int,
     *     changed:bool,
     *     rows:list<array<string,int>>,
     *     reason:string|null,
     *     dependencies:list<string>
     * }
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $schema = $parsed['schema'];
        $this->ensureSchema($schema);

        return match ($parsed['pragma']) {
            'cache_size' => $this->executeCacheSize($schema, $parsed['value']),
            'default_cache_size' => $this->executeDefaultCacheSize($schema, $parsed['value']),
            'freelist_count' => $this->executeFreelistCount($schema, $parsed['value']),
            'schema_version' => $this->executeVersion($schema, 'schema_version', $parsed['value']),
            'user_version' => $this->executeVersion($schema, 'user_version', $parsed['value']),
        };
    }

    /**
     * @return array{schema:string,pragma:'cache_size'|'default_cache_size'|'freelist_count'|'schema_version'|'user_version',value:int|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>cache_size|default_cache_size|freelist_count|schema_version|user_version)(?:\s*(?:=\s*(?<equals>[+-]?\d+)|\(\s*(?<paren>[+-]?\d+)\s*\)))?$/i', $trimmed, $matches)) {
            throw new \InvalidArgumentException('Unsupported SQLite dynamic schema PRAGMA SQL');
        }

        $value = null;
        if (($matches['equals'] ?? '') !== '') {
            $value = self::signedInt($matches['equals'], 'SQLite PRAGMA value');
        } elseif (($matches['paren'] ?? '') !== '') {
            $value = self::signedInt($matches['paren'], 'SQLite PRAGMA value');
        }

        return [
            'schema' => strtolower(($matches['schema'] ?? '') !== '' ? $matches['schema'] : 'main'),
            'pragma' => strtolower($matches['pragma']),
            'value' => $value,
        ];
    }

    /**
     * @return array<string, array{cache_size:int,default_cache_size:int,freelist_count:int,schema_version:int,user_version:int,database_empty:bool}>
     */
    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * @param array{cache_size?:int|string,default_cache_size?:int|string,freelist_count?:int|string,schema_version?:int|string,user_version?:int|string,database_empty?:bool} $state
     * @return array{cache_size:int,default_cache_size:int,freelist_count:int,schema_version:int,user_version:int,database_empty:bool}
     */
    private function normalizeSchema(array $state): array
    {
        $default = self::signedInt($state['default_cache_size'] ?? 2000, 'SQLite default_cache_size');
        return [
            'cache_size' => self::signedInt($state['cache_size'] ?? $default, 'SQLite cache_size'),
            'default_cache_size' => $default,
            'freelist_count' => self::nonNegativeInt($state['freelist_count'] ?? 0, 'SQLite freelist_count'),
            'schema_version' => self::nonNegativeInt($state['schema_version'] ?? 0, 'SQLite schema_version'),
            'user_version' => self::signedInt($state['user_version'] ?? 0, 'SQLite user_version'),
            'database_empty' => (bool) ($state['database_empty'] ?? true),
        ];
    }

    private function ensureSchema(string $schema): void
    {
        if (!isset($this->schemas[$schema])) {
            $this->schemas[$schema] = $this->normalizeSchema([]);
        }
    }

    /**
     * @return array{status:string,pragma:'cache_size',schema:string,requested:int|null,value:int,changed:bool,rows:list<array{cache_size:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executeCacheSize(string $schema, ?int $requested): array
    {
        $before = $this->schemas[$schema]['cache_size'];
        $value = $before;
        if ($requested !== null) {
            $value = $requested;
            $this->schemas[$schema]['cache_size'] = $value;
        }

        return $this->result('cache_size', $schema, $requested, $value, $before !== $value, null, 'sqlite-pragma-cache-size-state');
    }

    /**
     * @return array{status:string,pragma:'default_cache_size',schema:string,requested:int|null,value:int,changed:bool,rows:list<array{default_cache_size:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executeDefaultCacheSize(string $schema, ?int $requested): array
    {
        $before = $this->schemas[$schema]['default_cache_size'];
        $value = $before;
        if ($requested !== null) {
            $value = $requested;
            $this->schemas[$schema]['default_cache_size'] = $value;
            $this->schemas[$schema]['cache_size'] = $value;
        }

        return $this->result('default_cache_size', $schema, $requested, $value, $before !== $value, null, 'sqlite-pragma-default-cache-size-state');
    }

    /**
     * @return array{status:string,pragma:'freelist_count',schema:string,requested:int|null,value:int,changed:false,rows:list<array{freelist_count:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executeFreelistCount(string $schema, ?int $requested): array
    {
        $value = $this->schemas[$schema]['freelist_count'];

        return $this->result('freelist_count', $schema, $requested, $value, false, $requested === null ? null : 'read_only_pragma_ignored', 'sqlite-pragma-freelist-count-state');
    }

    /**
     * @param 'schema_version'|'user_version' $pragma
     * @return array{status:string,pragma:'schema_version'|'user_version',schema:string,requested:int|null,value:int,changed:bool,rows:list<array<string,int>>,reason:string|null,dependencies:list<string>}
     */
    private function executeVersion(string $schema, string $pragma, ?int $requested): array
    {
        $before = $this->schemas[$schema][$pragma];
        $value = $before;
        if ($requested !== null) {
            if ($pragma === 'schema_version') {
                $requested = self::nonNegativeInt($requested, 'SQLite schema_version');
            }
            $value = $requested;
            $this->schemas[$schema][$pragma] = $value;
        }

        return $this->result($pragma, $schema, $requested, $value, $before !== $value, null, 'sqlite-pragma-version-state');
    }

    /**
     * @template T of 'cache_size'|'default_cache_size'|'freelist_count'|'schema_version'|'user_version'
     * @param T $pragma
     * @return array{status:string,pragma:T,schema:string,requested:int|null,value:int,changed:bool,rows:list<array<string,int>>,reason:string|null,dependencies:list<string>}
     */
    private function result(string $pragma, string $schema, ?int $requested, int $value, bool $changed, ?string $reason, string $dependency): array
    {
        return [
            'status' => 'ok',
            'pragma' => $pragma,
            'schema' => $schema,
            'requested' => $requested,
            'value' => $value,
            'changed' => $changed,
            'rows' => [[$pragma => $value]],
            'reason' => $reason,
            'dependencies' => [$dependency],
        ];
    }

    private static function signedInt(int|string $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if (!preg_match('/^[+-]?\d+$/', $trimmed)) {
            throw new \InvalidArgumentException($label . ' must be an integer');
        }

        return (int) $trimmed;
    }

    private static function nonNegativeInt(int|string $value, string $label): int
    {
        $int = self::signedInt($value, $label);
        if ($int < 0) {
            throw new \InvalidArgumentException($label . ' must be non-negative');
        }

        return $int;
    }
}
