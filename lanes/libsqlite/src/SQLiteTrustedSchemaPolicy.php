<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTrustedSchemaPolicy
{
    /**
     * @param array<string, array{innocuous?:bool,direct_only?:bool,deterministic?:bool}> $functions
     * @return array{allowed:bool, schema:string, trusted_schema:bool, context:string, unsafe_function:string|null, reason:string|null, functions:array<int,string>}
     */
    public static function evaluate(string $sql, array $functions, bool $trustedSchema, string $schema = 'main'): array
    {
        $schema = self::schemaName($schema);
        $context = self::context($sql);
        $names = self::functionNames($sql);

        foreach ($names as $name) {
            $meta = $functions[strtolower($name)] ?? null;
            if ($meta === null) {
                continue;
            }

            if (($meta['direct_only'] ?? false) && $schema !== 'temp') {
                return self::result(false, $schema, $trustedSchema, $context, $name, 'direct-only function in schema object', $names);
            }

            if ($schema !== 'temp' && !$trustedSchema && !($meta['innocuous'] ?? false)) {
                return self::result(false, $schema, $trustedSchema, $context, $name, 'non-innocuous function while trusted_schema is off', $names);
            }
        }

        return self::result(true, $schema, $trustedSchema, $context, null, null, $names);
    }

    /**
     * @return array<string, array{innocuous?:bool,direct_only?:bool,deterministic?:bool}>
     */
    public static function upstreamTrustSchemaFunctions(): array
    {
        return [
            'f1' => ['innocuous' => true, 'deterministic' => true],
            'f2' => ['deterministic' => true],
            'f3' => ['direct_only' => true, 'deterministic' => true],
            'json_extract' => ['innocuous' => true, 'deterministic' => true],
        ];
    }

    /**
     * @return array{allowed:bool, schema:string, trusted_schema:bool, context:string, unsafe_function:string|null, reason:string|null, functions:array<int,string>}
     */
    private static function result(bool $allowed, string $schema, bool $trustedSchema, string $context, ?string $function, ?string $reason, array $functions): array
    {
        return [
            'allowed' => $allowed,
            'schema' => $schema,
            'trusted_schema' => $trustedSchema,
            'context' => $context,
            'unsafe_function' => $function,
            'reason' => $reason,
            'functions' => array_values($functions),
        ];
    }

    private static function schemaName(string $schema): string
    {
        $schema = strtolower(trim($schema));
        if ($schema === '') {
            throw new InvalidArgumentException('SQLite trusted schema policy requires a schema name');
        }

        return $schema;
    }

    private static function context(string $sql): string
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?? '');
        if (str_contains($normalized, ' create trigger ') || str_starts_with($normalized, 'create trigger')) {
            return 'trigger';
        }
        if (preg_match('/^create\s+(?:temp(?:orary)?\s+)?view\b/', $normalized) === 1) {
            return 'view';
        }
        if (preg_match('/\bas\s*\(/', $normalized) === 1) {
            return 'generated-column';
        }
        if (preg_match('/\bcheck\s*\(/', $normalized) === 1) {
            return 'check';
        }
        if (preg_match('/\bdefault\s*\(/', $normalized) === 1) {
            return 'default';
        }
        if (str_contains($normalized, ' where ')) {
            return 'partial-index';
        }
        if (str_starts_with($normalized, 'create index') || str_starts_with($normalized, 'create unique index')) {
            return 'expression-index';
        }

        return 'schema-sql';
    }

    /**
     * @return array<int,string>
     */
    private static function functionNames(string $sql): array
    {
        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $sql, $matches) !== false) {
            $keywords = [
                'as' => true,
                'check' => true,
                'default' => true,
                'select' => true,
                'values' => true,
            ];
            $names = [];
            foreach ($matches[1] as $name) {
                $lower = strtolower($name);
                if (!isset($keywords[$lower])) {
                    $names[$lower] = $lower;
                }
            }
            ksort($names);

            return array_values($names);
        }

        return [];
    }
}
