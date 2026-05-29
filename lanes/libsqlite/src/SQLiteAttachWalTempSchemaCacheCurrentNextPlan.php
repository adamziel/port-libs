<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempSchemaCacheCurrentNextPlan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, next_tables?:list<string>|null, indexes?:list<string>, next_indexes?:list<string>|null, temp?:bool, file?:string|null, cache?:string|null}> $schemas
     * @param list<array{op:string, schema:string, object?:string, savepoint?:string}> $operations
     * @param list<array{sql:string, active?:bool, name?:string}> $statements
     * @return array<string,mixed>
     */
    public static function plan(array $schemas, array $operations, array $statements, string $outcome = 'commit', string $sourceSchema = 'main'): array
    {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite attach WAL temp schema-cache current source requires statements');
        }

        $transaction = SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, $operations, $outcome);
        $cacheSchemas = self::schemasAfterTransaction($schemas, $transaction, $outcome);
        $lifecycle = SQLiteAttachWalTempStatementLifecyclePlan::plan($cacheSchemas, $statements, $sourceSchema);

        $active = [];
        $retryable = [];
        $writeBlocked = [];
        foreach ($lifecycle['statements'] as $statement) {
            if ($statement['active']) {
                $active[] = $statement['name'];
            }
            if ($statement['retryable_after_reprepare']) {
                $retryable[] = $statement['name'];
            }
            if ($statement['requires_reprepare'] && !$statement['read_only']) {
                $writeBlocked[] = $statement['name'];
            }
        }

        return [
            'status' => $lifecycle['requires_reprepare'] ? 'schema_cache_expired' : 'schema_cache_stable',
            'operation' => 'attach-wal-temp-schema-cache-current',
            'outcome' => $outcome,
            'source' => $lifecycle['source'],
            'transaction_status' => $transaction['status'],
            'transaction_reprepare_schemas' => $transaction['reprepare_schemas'],
            'schema_cookies_current' => $lifecycle['schema_cookies_current'],
            'schema_cookies_next' => $lifecycle['schema_cookies_next'],
            'changed_schemas' => $lifecycle['changed_schemas'],
            'object_changed_schemas' => $lifecycle['object_changed_schemas'],
            'active_current_snapshot_statements' => $active,
            'retryable_read_statements' => $retryable,
            'write_statements_blocked_before_retry' => $writeBlocked,
            'expired_statements' => $lifecycle['expired_statements'],
            'stable_statements' => $lifecycle['stable_statements'],
            'statement_count' => $lifecycle['statement_count'],
            'statements' => $lifecycle['statements'],
            'requires_reprepare' => $lifecycle['requires_reprepare'],
            'dependencies' => [
                'sqlite-attach-wal-temp-schema-cache-current',
                'sqlite-attach-wal-temp-transaction-current',
                'sqlite-schema-cookie-expire-prepared-statements',
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @param array<string,mixed> $transaction
     * @return array<string,array<string,mixed>>
     */
    private static function schemasAfterTransaction(array $schemas, array $transaction, string $outcome): array
    {
        $next = [];
        foreach ($schemas as $schema => $entry) {
            $name = self::schemaName((string) $schema);
            $schemaState = $transaction['schemas'][$name] ?? null;
            if (!is_array($schemaState)) {
                throw new \InvalidArgumentException("SQLite attach WAL temp schema-cache current source missing transaction schema {$name}");
            }

            $copy = $entry;
            $copy['schema_cookie'] = $schemaState['current_cookie'];
            $postCookie = $schemaState['post_transaction_cookie'];
            if ($outcome === 'commit' && $postCookie !== $schemaState['current_cookie']) {
                $copy['wal_schema_cookie'] = $postCookie;
            } else {
                unset($copy['wal_schema_cookie']);
                $copy['wal_frames'] = [];
                $copy['next_tables'] = $copy['tables'] ?? [];
                $copy['next_indexes'] = $copy['indexes'] ?? [];
            }
            $next[$name] = $copy;
        }

        return $next;
    }

    private static function schemaName(string $name): string
    {
        $normalized = strtolower(trim($name, " \t\r\n`\"[]'"));
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite schema name cannot be empty');
        }

        return $normalized;
    }
}
