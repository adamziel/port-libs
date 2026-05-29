<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan
{
    /**
     * @param array<string,array<string,mixed>> $schemas
     * @param list<array{name?:string,sql:string,active?:bool,read_only?:bool}> $statements
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $statements, string $sourceSchema = 'main'): array
    {
        $base = SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas, $statements, $sourceSchema);
        $normalized = self::normalizeSchemas($schemas);
        $rootSignatures = [];
        $changedRootSchemas = [];
        $sourceOnlySchemas = [];

        foreach ($base['search_order'] as $schema) {
            $currentRoots = $normalized[$schema]['schema_roots'] ?? [];
            $nextRoots = $normalized[$schema]['next_schema_roots'] ?? $currentRoots;
            $currentSignature = self::rootSignature($currentRoots);
            $nextSignature = self::rootSignature($nextRoots);
            $sameCookie = ($base['schema_cookies_current'][$schema] ?? null) === ($base['schema_cookies_next'][$schema] ?? null);
            $sourceChanged = ($base['schema_cookie_sources'][$schema]['current_source'] ?? null) !== ($base['schema_cookie_sources'][$schema]['next_source'] ?? null);
            $rootsChanged = $currentSignature !== $nextSignature;

            $rootSignatures[$schema] = [
                'current_root_signature' => $currentSignature,
                'next_root_signature' => $nextSignature,
                'root_signature_changed' => $rootsChanged,
                'same_cookie' => $sameCookie,
                'source_changed' => $sourceChanged,
                'source_only_cookie_move' => $sameCookie && $sourceChanged && !$rootsChanged,
                'schema_changed' => !$sameCookie || $rootsChanged,
            ];

            if ($rootsChanged) {
                $changedRootSchemas[] = $schema;
            } elseif ($sameCookie && $sourceChanged) {
                $sourceOnlySchemas[] = $schema;
            }
        }

        $expired = [];
        $stable = [];
        $retryableReads = [];
        $writeBlocked = [];
        $activeCurrent = [];
        $statementsOut = [];

        foreach ($base['statements'] as $statement) {
            $requires = false;
            $transitions = [];
            foreach ($statement['schema_transitions'] as $transition) {
                $prepareSchema = $transition['prepare_schema'];
                $nextSchema = $transition['next_schema'];
                $prepareRoot = self::tableRoot($normalized[$prepareSchema]['schema_roots'] ?? [], $transition['table']);
                $nextRoot = self::tableRoot($normalized[$nextSchema]['next_schema_roots'] ?? ($normalized[$nextSchema]['schema_roots'] ?? []), $transition['table']);
                $rootChanged = $prepareRoot !== $nextRoot || ($rootSignatures[$prepareSchema]['root_signature_changed'] ?? false) || ($rootSignatures[$nextSchema]['root_signature_changed'] ?? false);
                $changed = (bool) $transition['resolution_changed']
                    || $transition['prepare_schema_cookie'] !== $transition['next_schema_cookie']
                    || $rootChanged;
                $requires = $requires || $changed;
                $transition['prepare_root_page'] = $prepareRoot;
                $transition['next_root_page'] = $nextRoot;
                $transition['root_signature_changed'] = $rootChanged;
                $transition['source_only_cookie_move'] = ($rootSignatures[$prepareSchema]['source_only_cookie_move'] ?? false)
                    || ($rootSignatures[$nextSchema]['source_only_cookie_move'] ?? false);
                $transition['requires_reprepare'] = $changed;
                $transitions[] = $transition;
            }

            $name = $statement['name'];
            if ($requires) {
                $expired[] = $name;
                if ($statement['active']) {
                    $activeCurrent[] = $name;
                }
                if ($statement['read_only']) {
                    $retryableReads[] = $name;
                } else {
                    $writeBlocked[] = $name;
                }
            } else {
                $stable[] = $name;
            }

            $statement['schema_transitions'] = $transitions;
            $statement['requires_reprepare'] = $requires;
            $statement['sqlite_result_on_current_step'] = $statement['active'] ? 'SQLITE_OK' : ($requires ? 'SQLITE_SCHEMA' : 'SQLITE_OK');
            $statement['next_step_action'] = self::nextAction($requires, (bool) $statement['active'], (bool) $statement['read_only']);
            $statementsOut[] = $statement;
        }

        $base['operation'] = 'attach-temp-wal-schema-cookie-current-source-next98';
        $base['status'] = $expired === [] ? 'schema_cache_stable' : 'schema_cache_expired';
        $base['statements'] = $statementsOut;
        $base['expired_statements'] = $expired;
        $base['stable_statements'] = $stable;
        $base['active_current_snapshot_statements'] = $activeCurrent;
        $base['retryable_read_statements'] = $retryableReads;
        $base['write_statements_blocked_before_retry'] = $writeBlocked;
        $base['requires_reprepare'] = $expired !== [];
        $base['schema_root_signatures'] = $rootSignatures;
        $base['changed_root_schemas'] = $changedRootSchemas;
        $base['source_only_cookie_move_schemas'] = $sourceOnlySchemas;
        $base['dependencies'] = [
            'sqlite-attach-temp-wal-schema-cookie-current-source-next98',
            'sqlite-attach-wal-temp-schema-cookie-current-source-next87',
            'sqlite-schema-root-signature-current-source',
        ];

        return $base;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array{schema_roots:array<string,int>,next_schema_roots:array<string,int>|null}>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [];
        foreach ($schemas as $schema => $entry) {
            $name = self::plainName((string) $schema);
            $normalized[$name] = [
                'schema_roots' => self::normalizeRoots($entry['schema_roots'] ?? []),
                'next_schema_roots' => array_key_exists('next_schema_roots', $entry) && $entry['next_schema_roots'] !== null
                    ? self::normalizeRoots($entry['next_schema_roots'])
                    : null,
            ];
        }

        foreach (['main', 'temp'] as $schema) {
            $normalized[$schema] ??= ['schema_roots' => [], 'next_schema_roots' => null];
        }

        return $normalized;
    }

    /**
     * @param mixed $roots
     * @return array<string,int>
     */
    private static function normalizeRoots(mixed $roots): array
    {
        if (!is_array($roots)) {
            throw new \InvalidArgumentException('SQLite schema root map must be an array');
        }

        $normalized = [];
        foreach ($roots as $table => $rootPage) {
            if (!is_int($rootPage) || $rootPage < 0) {
                throw new \InvalidArgumentException('SQLite schema root map requires non-negative integer root pages');
            }
            $normalized[self::plainName((string) $table)] = $rootPage;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string,int> $roots
     */
    private static function rootSignature(array $roots): string
    {
        if ($roots === []) {
            return 'empty';
        }

        $parts = [];
        foreach ($roots as $table => $rootPage) {
            $parts[] = $table . ':' . $rootPage;
        }

        return implode('|', $parts);
    }

    /**
     * @param array<string,int> $roots
     */
    private static function tableRoot(array $roots, string $table): ?int
    {
        $parts = explode('.', $table, 2);
        $name = self::plainName((string) end($parts));

        return $roots[$name] ?? null;
    }

    private static function nextAction(bool $requiresReprepare, bool $active, bool $readOnly): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement';
        }
        if ($active) {
            return 'finish_current_snapshot_then_sqlite_schema_on_reset';
        }
        if ($readOnly) {
            return 'sqlite_schema_then_reprepare';
        }

        return 'sqlite_schema_before_write_retry';
    }

    private static function plainName(string $name): string
    {
        $trimmed = trim($name, " \t\r\n`'\"");
        if ($trimmed === '') {
            throw new \InvalidArgumentException('SQLite schema or table name cannot be empty');
        }

        return strtolower($trimmed);
    }
}
