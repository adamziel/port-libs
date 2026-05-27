<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachUriSchemaCache
{
    /** @var array<string, array{records:list<SQLiteSchemaRecord>, schema_cookie:int, generation:int, file:string, cache:string|null, mode:string|null, vfs:string|null}> */
    private array $entries = [];

    private int $generation = 0;

    /**
     * Execute a bounded ATTACH through SQLiteAttachedSchemaCatalog while
     * modeling SQLite's shared-cache schema reuse rules for file URI opens.
     *
     * @param callable(string, string): list<SQLiteSchemaRecord> $recordLoader
     * @return array<string,mixed>
     */
    public function attach(
        SQLiteAttachedSchemaCatalog $catalog,
        string $sql,
        callable $recordLoader,
        int $currentSchemaCookie,
        ?int $nextSchemaCookie = null,
    ): array {
        $identity = self::identityFromAttachSql($sql, $currentSchemaCookie);
        $shared = $identity['uri']['cache'] === 'shared';
        $key = $identity['key'];
        $cacheEvent = 'uncacheable_private_or_plain';
        $loaded = false;
        $records = [];
        $fromCache = false;

        if ($shared && isset($this->entries[$key])) {
            $entry = $this->entries[$key];
            if ($entry['schema_cookie'] === $currentSchemaCookie) {
                $records = $entry['records'];
                $cacheEvent = 'shared_schema_cache_hit';
                $fromCache = true;
            } else {
                unset($this->entries[$key]);
                $cacheEvent = 'shared_schema_cookie_miss';
            }
        }

        $loader = function (string $file, string $schema) use ($recordLoader, $shared, $key, $currentSchemaCookie, &$records, &$cacheEvent, &$loaded, &$fromCache, $identity): array {
            if (!$fromCache) {
                $records = array_values($recordLoader($file, $schema));
                $loaded = true;
                if ($shared) {
                    $this->entries[$key] = [
                        'records' => $records,
                        'schema_cookie' => $currentSchemaCookie,
                        'generation' => ++$this->generation,
                        'file' => $file,
                        'cache' => $identity['uri']['cache'],
                        'mode' => $identity['uri']['mode'],
                        'vfs' => $identity['uri']['vfs'],
                    ];
                    $cacheEvent = 'shared_schema_cache_store';
                }
                $fromCache = true;
            }

            return $records;
        };

        $attach = $catalog->executeAttachDetachSql($sql, $loader);
        $entry = $shared && isset($this->entries[$key]) ? $this->entries[$key] : null;
        $nextCookie = $nextSchemaCookie ?? $currentSchemaCookie;
        $nextKey = self::cacheKey($identity['file'], $identity['uri'], $nextCookie);
        $current = [
            'status' => 'ok',
            'operation' => 'attach-uri-schema-cache',
            'schema' => $attach['schema'],
            'file' => $attach['file'],
            'cacheable' => $shared,
            'cache_event' => $cacheEvent,
            'loader_called' => $loaded,
            'record_count' => count($records),
            'cache_key' => $shared ? $key : null,
            'schema_cookie' => $currentSchemaCookie,
            'generation' => $entry['generation'] ?? null,
            'uri' => $identity['uri'],
            'open_plan' => $attach['open_plan'],
            'database_list' => $attach['database_list'],
            'dependencies' => $shared
                ? ['attach-uri-schema-cache', 'shared-cache-schema-cookie']
                : ['attach-uri-schema-cache'],
        ];

        $current['next'] = [
            'schema_cookie' => $nextCookie,
            'cache_key' => $shared ? $nextKey : null,
            'reuse_current' => $shared && $nextKey === $key,
            'requires_reload' => $shared && $nextKey !== $key,
        ];

        return $current;
    }

    /**
     * @return array<string, array{records:list<SQLiteSchemaRecord>, schema_cookie:int, generation:int, file:string, cache:string|null, mode:string|null, vfs:string|null}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return array{file:string, uri:array<string,mixed>, key:string}
     */
    private static function identityFromAttachSql(string $sql, int $schemaCookie): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (preg_match('/^attach(?:\s+database)?\s+(.+?)\s+as\s+(.+)$/is', $trimmed, $matches) !== 1) {
            throw new \InvalidArgumentException('SQLite URI schema cache only supports ATTACH statements');
        }

        $filename = self::parseFilenameExpression($matches[1]);
        $uri = SQLiteFileUri::parse($filename);
        $file = (string) $uri['path'];
        if ($file === '') {
            throw new \InvalidArgumentException('SQLite ATTACH file name cannot be empty');
        }

        return [
            'file' => $file,
            'uri' => $uri,
            'key' => self::cacheKey($file, $uri, $schemaCookie),
        ];
    }

    /**
     * @param array<string,mixed> $uri
     */
    private static function cacheKey(string $file, array $uri, int $schemaCookie): string
    {
        return implode('|', [
            $file,
            (string) ($uri['cache'] ?? ''),
            (string) ($uri['mode'] ?? ''),
            (string) ($uri['vfs'] ?? ''),
            (string) ($uri['immutable'] ?? ''),
            (string) $schemaCookie,
        ]);
    }

    private static function parseFilenameExpression(string $expression): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new \InvalidArgumentException('SQLite ATTACH file name cannot be empty');
        }

        $quote = $expression[0];
        if (($quote === "'" || $quote === '"') && substr($expression, -1) === $quote) {
            $body = substr($expression, 1, -1);
            if ($body === '') {
                throw new \InvalidArgumentException('SQLite ATTACH file name cannot be empty');
            }

            return str_replace($quote . $quote, $quote, $body);
        }

        if (preg_match('/^[A-Za-z0-9_\/.\-:?&=%]+$/', $expression) === 1) {
            return $expression;
        }

        throw new \InvalidArgumentException('SQLite ATTACH file name must be a bounded string literal or path token');
    }
}
