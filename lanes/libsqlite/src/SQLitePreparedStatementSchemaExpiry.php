<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePreparedStatementSchemaExpiry
{
    /** @var array<string,array{id:string, sql:string, kind:string, target:string|null, generation:int, expired:bool, expiry_reason:string|null, steps:int}> */
    private array $statements = [];

    private int $generation = 0;

    /** @var array<string,bool> */
    private array $tables = [];

    /** @var array<string,bool> */
    private array $views = [];

    /** @var array<string,bool> */
    private array $indexes = [];

    /** @var array<string,bool> */
    private array $triggers = [];

    /** @var array<string,bool> */
    private array $attachedSchemas = [];

    /** @param list<string> $tables */
    public function __construct(array $tables = [])
    {
        foreach ($tables as $table) {
            $this->tables[self::key($table)] = true;
        }
    }

    /**
     * @return array{id:string, sql:string, kind:string, target:string|null, generation:int, expired:bool, expiry_reason:null, steps:int}
     */
    public function prepare(string $id, string $sql): array
    {
        $statementId = trim($id);
        if ($statementId === '') {
            throw new InvalidArgumentException('SQLite prepared statement id cannot be empty');
        }
        if (isset($this->statements[$statementId])) {
            throw new InvalidArgumentException("SQLite prepared statement {$statementId} already exists");
        }

        $statement = [
            'id' => $statementId,
            'sql' => trim($sql),
            'kind' => $this->statementKind($sql),
            'target' => $this->statementTarget($sql),
            'generation' => $this->generation,
            'expired' => false,
            'expiry_reason' => null,
            'steps' => 0,
        ];
        $this->statements[$statementId] = $statement;

        return $statement;
    }

    /**
     * @return array{status:string, id:string, code:string, auto_reprepared:bool, generation_before:int, generation_after:int, expiry_reason:string|null, result_rows:int, target_exists:bool|null}
     */
    public function step(string $id): array
    {
        $statement = $this->statement($id);
        $generationBefore = $statement['generation'];
        $autoReprepared = $statement['expired'] || $statement['generation'] !== $this->generation;
        $reason = $statement['expiry_reason'];
        if ($autoReprepared) {
            $statement['generation'] = $this->generation;
            $statement['expired'] = false;
            $statement['expiry_reason'] = null;
        }
        $statement['steps']++;
        $this->statements[$statement['id']] = $statement;

        $targetExists = $this->targetExists($statement);
        $rows = $this->rowCountFor($statement, $targetExists);

        return [
            'status' => 'ok',
            'id' => $statement['id'],
            'code' => $rows > 0 ? 'SQLITE_ROW' : 'SQLITE_DONE',
            'auto_reprepared' => $autoReprepared,
            'generation_before' => $generationBefore,
            'generation_after' => $this->generation,
            'expiry_reason' => $reason,
            'result_rows' => $rows,
            'target_exists' => $targetExists,
        ];
    }

    /**
     * @return array{status:string, id:string, code:string, generation:int}
     */
    public function reset(string $id): array
    {
        $statement = $this->statement($id);

        return [
            'status' => 'ok',
            'id' => $statement['id'],
            'code' => 'SQLITE_OK',
            'generation' => $this->generation,
        ];
    }

    /**
     * @return array{status:string, id:string, code:string, generation:int, existed:bool}
     */
    public function finalize(string $id): array
    {
        $statement = $this->statement($id);
        unset($this->statements[$statement['id']]);

        return [
            'status' => 'ok',
            'id' => $statement['id'],
            'code' => 'SQLITE_OK',
            'generation' => $this->generation,
            'existed' => true,
        ];
    }

    /**
     * Apply the bounded schema/runtime operations covered by upstream
     * schema2.test. sqlite3_prepare_v2 statements are expired, but their next
     * step can auto-reprepare instead of returning SQLITE_SCHEMA.
     *
     * @return array{status:string, operation:string, name:string|null, generation:int, invalidated:bool, invalidated_statements:list<string>, reason:string|null}
     */
    public function apply(string $operation, ?string $name = null): array
    {
        $op = strtolower(trim($operation));
        $object = $name === null ? null : self::key($name);
        $invalidates = true;
        $reason = null;

        switch ($op) {
            case 'create_table':
                $this->requireName($object, $op);
                $this->tables[$object] = true;
                $reason = 'schema_table_created';
                break;
            case 'drop_table':
                $this->requireName($object, $op);
                unset($this->tables[$object]);
                $reason = 'schema_table_dropped';
                break;
            case 'create_view':
                $this->requireName($object, $op);
                $this->views[$object] = true;
                $reason = 'schema_view_created';
                break;
            case 'drop_view':
                $this->requireName($object, $op);
                unset($this->views[$object]);
                $reason = 'schema_view_dropped';
                break;
            case 'create_trigger':
                $this->requireName($object, $op);
                $this->triggers[$object] = true;
                $reason = 'schema_trigger_created';
                break;
            case 'drop_trigger':
                $this->requireName($object, $op);
                unset($this->triggers[$object]);
                $reason = 'schema_trigger_dropped';
                break;
            case 'create_index':
                $this->requireName($object, $op);
                $this->indexes[$object] = true;
                $reason = 'schema_index_created';
                break;
            case 'drop_index':
                $this->requireName($object, $op);
                unset($this->indexes[$object]);
                $reason = 'schema_index_dropped';
                break;
            case 'attach':
                $this->requireName($object, $op);
                $this->attachedSchemas[$object] = true;
                $invalidates = false;
                break;
            case 'detach':
                $this->requireName($object, $op);
                unset($this->attachedSchemas[$object]);
                $reason = 'schema_detached';
                break;
            case 'add_function':
                $this->requireName($object, $op);
                $invalidates = false;
                break;
            case 'delete_function':
                $this->requireName($object, $op);
                $reason = 'runtime_function_deleted';
                break;
            case 'add_collation':
                $this->requireName($object, $op);
                $invalidates = false;
                break;
            case 'delete_collation':
                $this->requireName($object, $op);
                $reason = 'runtime_collation_deleted';
                break;
            case 'set_authorizer':
                $reason = 'authorizer_changed';
                break;
            default:
                throw new InvalidArgumentException("Unsupported SQLite schema expiry operation {$operation}");
        }

        $invalidated = [];
        if ($invalidates) {
            $this->generation++;
            foreach ($this->statements as $id => $statement) {
                $this->statements[$id]['expired'] = true;
                $this->statements[$id]['expiry_reason'] = $reason;
                $invalidated[] = $id;
            }
        }

        return [
            'status' => 'ok',
            'operation' => $op,
            'name' => $object,
            'generation' => $this->generation,
            'invalidated' => $invalidates,
            'invalidated_statements' => $invalidated,
            'reason' => $reason,
        ];
    }

    /** @return array{generation:int, prepared:int, tables:list<string>, views:list<string>, indexes:list<string>, triggers:list<string>, attached_schemas:list<string>} */
    public function snapshot(): array
    {
        return [
            'generation' => $this->generation,
            'prepared' => count($this->statements),
            'tables' => array_keys($this->tables),
            'views' => array_keys($this->views),
            'indexes' => array_keys($this->indexes),
            'triggers' => array_keys($this->triggers),
            'attached_schemas' => array_keys($this->attachedSchemas),
        ];
    }

    /** @return array{id:string, sql:string, kind:string, target:string|null, generation:int, expired:bool, expiry_reason:string|null, steps:int} */
    private function statement(string $id): array
    {
        $statementId = trim($id);
        if (!isset($this->statements[$statementId])) {
            throw new InvalidArgumentException("Unknown SQLite prepared statement {$statementId}");
        }

        return $this->statements[$statementId];
    }

    private function statementKind(string $sql): string
    {
        $trimmed = trim($sql);
        if (preg_match('/^select\s+\*\s+from\s+(?:sqlite_schema|sqlite_master)\b/i', $trimmed) === 1) {
            return 'schema_scan';
        }
        if (preg_match('/^select\s+\*\s+from\s+([A-Za-z_][A-Za-z0-9_]*)\b/i', $trimmed) === 1) {
            return 'table_scan';
        }

        return 'other';
    }

    private function statementTarget(string $sql): ?string
    {
        if (preg_match('/^select\s+\*\s+from\s+(?!sqlite_schema\b|sqlite_master\b)([A-Za-z_][A-Za-z0-9_]*)\b/i', trim($sql), $matches) !== 1) {
            return null;
        }

        return self::key($matches[1]);
    }

    /** @param array{id:string, sql:string, kind:string, target:string|null, generation:int, expired:bool, expiry_reason:string|null, steps:int} $statement */
    private function targetExists(array $statement): ?bool
    {
        if ($statement['target'] === null) {
            return null;
        }

        $target = $statement['target'];

        return isset($this->tables[$target]) || isset($this->views[$target]);
    }

    /** @param array{id:string, sql:string, kind:string, target:string|null, generation:int, expired:bool, expiry_reason:string|null, steps:int} $statement */
    private function rowCountFor(array $statement, ?bool $targetExists): int
    {
        if ($statement['kind'] === 'schema_scan') {
            return count($this->tables) + count($this->views) + count($this->indexes) + count($this->triggers);
        }
        if ($statement['kind'] === 'table_scan') {
            return $targetExists === true ? 1 : 0;
        }

        return 0;
    }

    private function requireName(?string $name, string $operation): void
    {
        if ($name === null || $name === '') {
            throw new InvalidArgumentException("SQLite schema expiry operation {$operation} requires a name");
        }
    }

    private static function key(string $name): string
    {
        return strtolower(trim($name));
    }
}
