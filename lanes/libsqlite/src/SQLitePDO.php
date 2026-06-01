<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePDO extends \PDO
{
    /** @var array<string,list<array<string,mixed>>> */
    private array $tables = [];

    /** @var array<string,list<string>> */
    private array $columns = [];

    private int $lastInsertId = 0;
    private int $lastChanges = 0;
    private ?array $transactionSnapshot = null;
    private int $errmode = \PDO::ERRMODE_EXCEPTION;
    private int $defaultFetchMode = \PDO::FETCH_BOTH;
    private string $errorCode = '00000';

    /** @var array{0:string,1:int|null,2:string|null} */
    private array $errorInfo = ['00000', null, null];

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        unset($username, $password);
        if ($dsn !== 'sqlite::memory:' && preg_match('/^sqlite:(.+)$/', $dsn, $match) !== 1) {
            throw new \PDOException("SQLitePDO invalid DSN '{$dsn}': expected sqlite::memory: or sqlite:/path");
        }
        if ($dsn !== 'sqlite::memory:') {
            $path = $match[1];
            if ($path === '') {
                throw new \PDOException("SQLitePDO invalid DSN '{$dsn}': file path cannot be empty");
            }
            if (is_file($path) && filesize($path) > 0) {
                throw new \PDOException('SQLitePDO cannot open existing SQLite file images in this first slice');
            }
        }
        foreach ($options ?? [] as $attribute => $value) {
            $this->setAttribute((int) $attribute, $value);
        }
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $statement = $this->prepare($query);
        $statement->execute();
        if ($fetchMode !== null) {
            $statement->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        return $statement;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        foreach ($options as $attribute => $value) {
            if ((int) $attribute !== \PDO::ATTR_CURSOR || $value !== \PDO::CURSOR_FWDONLY) {
                throw new \PDOException('SQLitePDO prepare options are not supported');
            }
        }
        if (count($options) > 1) {
            throw new \PDOException('SQLitePDO prepare options are not supported');
        }

        return new SQLitePDOStatement($this, $query);
    }

    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        if (!in_array($type, [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_BOOL, \PDO::PARAM_NULL], true)) {
            throw new \PDOException('SQLitePDO quote type is not supported');
        }
        if ($type === \PDO::PARAM_NULL) {
            return 'NULL';
        }
        if ($type === \PDO::PARAM_INT) {
            return (string) (int) $string;
        }
        if ($type === \PDO::PARAM_BOOL) {
            return ((bool) $string) ? '1' : '0';
        }

        return "'" . str_replace("'", "''", $string) . "'";
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        if ($attribute === \PDO::ATTR_ERRMODE) {
            if ($value !== \PDO::ERRMODE_EXCEPTION) {
                throw new \PDOException('SQLitePDO supports only PDO::ERRMODE_EXCEPTION');
            }
            $this->errmode = \PDO::ERRMODE_EXCEPTION;

            return true;
        }
        if ($attribute === \PDO::ATTR_DEFAULT_FETCH_MODE) {
            if (!in_array($value, SQLitePDOStatement::SUPPORTED_FETCH_MODES, true)) {
                throw new \PDOException('SQLitePDO default fetch mode is not supported');
            }
            $this->defaultFetchMode = $value;

            return true;
        }

        throw new \PDOException("SQLitePDO attribute {$attribute} is not supported");
    }

    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === \PDO::ATTR_ERRMODE) {
            return $this->errmode;
        }
        if ($attribute === \PDO::ATTR_DEFAULT_FETCH_MODE) {
            return $this->defaultFetchMode;
        }
        if ($attribute === \PDO::ATTR_DRIVER_NAME) {
            return 'sqlite';
        }

        throw new \PDOException("SQLitePDO attribute {$attribute} is not supported");
    }

    public function defaultFetchMode(): int
    {
        return $this->defaultFetchMode;
    }

    public function inTransaction(): bool
    {
        return $this->transactionSnapshot !== null;
    }

    public function exec(string $statement): int|false
    {
        $changes = 0;
        foreach ($this->splitStatements($statement) as $sql) {
            $result = $this->executeSql($sql, []);
            $changes += $result['changes'];
        }
        $this->lastChanges = $changes;

        return $changes;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        if ($name !== null) {
            throw new \PDOException('SQLitePDO sequence names are not supported');
        }

        return (string) $this->lastInsertId;
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionSnapshot !== null) {
            throw new \PDOException('SQLitePDO does not support nested transactions');
        }
        $this->transactionSnapshot = [$this->tables, $this->columns, $this->lastInsertId];

        return true;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /** @return array{0:string,1:int|null,2:string|null} */
    public function errorInfo(): array
    {
        return $this->errorInfo;
    }

    public function commit(): bool
    {
        if ($this->transactionSnapshot === null) {
            throw new \PDOException('SQLitePDO has no active transaction');
        }
        $this->transactionSnapshot = null;

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionSnapshot === null) {
            throw new \PDOException('SQLitePDO has no active transaction');
        }
        [$this->tables, $this->columns, $this->lastInsertId] = $this->transactionSnapshot;
        $this->transactionSnapshot = null;

        return true;
    }

    /**
     * @param array<int|string,mixed> $parameters
     * @return array{rows:list<array<string,mixed>>,changes:int}
     */
    public function executeSql(string $sql, array $parameters): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if ($sql === '') {
            $this->clearError();
            return ['rows' => [], 'changes' => 0];
        }
        try {
            if (preg_match('/^(?:select|values|with)\b/i', $sql) === 1) {
                $result = ['rows' => SQLiteSelectSql::execute($sql, $this->tables, $parameters), 'changes' => 0];
                $this->clearError();

                return $result;
            }
            if (preg_match('/^create\s+table\b/i', $sql) === 1) {
                $this->createTable($sql);
                $this->clearError();
                return ['rows' => [], 'changes' => 0];
            }
            if (SQLiteInsertValuesSql::startsWithInsertKeyword($sql)) {
                $result = $this->executeDataChangeAtomically(
                    fn (): array => ['rows' => [], 'changes' => $this->insertValues($sql, $parameters)],
                );
                $this->clearError();

                return $result;
            }
            if (preg_match('/^update\b/i', $sql) === 1) {
                $result = $this->executeDataChangeAtomically(
                    fn (): array => ['rows' => [], 'changes' => $this->updateRows($sql, $parameters)],
                );
                $this->clearError();

                return $result;
            }
            if (preg_match('/^delete\b/i', $sql) === 1) {
                $result = $this->executeDataChangeAtomically(
                    fn (): array => ['rows' => [], 'changes' => $this->deleteRows($sql, $parameters)],
                );
                $this->clearError();

                return $result;
            }
            if (preg_match('/^begin(?:\s+transaction)?$/i', $sql) === 1) {
                $this->beginTransaction();
                $this->clearError();
                return ['rows' => [], 'changes' => 0];
            }
            if (preg_match('/^commit$/i', $sql) === 1) {
                $this->commit();
                $this->clearError();
                return ['rows' => [], 'changes' => 0];
            }
            if (preg_match('/^rollback$/i', $sql) === 1) {
                $this->rollBack();
                $this->clearError();
                return ['rows' => [], 'changes' => 0];
            }
        } catch (\Throwable $exception) {
            throw $this->failure($exception->getMessage(), $exception);
        }

        throw $this->failure("SQLitePDO unsupported SQL statement: {$sql}");
    }

    private function clearError(): void
    {
        $this->errorCode = '00000';
        $this->errorInfo = ['00000', null, null];
    }

    private function failure(string $message, ?\Throwable $previous = null): \PDOException
    {
        $this->errorCode = 'HY000';
        $this->errorInfo = ['HY000', 1, $message];

        return new \PDOException($message, 0, $previous);
    }

    /**
     * @param callable(): array{rows:list<array<string,mixed>>,changes:int} $operation
     * @return array{rows:list<array<string,mixed>>,changes:int}
     */
    private function executeDataChangeAtomically(callable $operation): array
    {
        $tables = $this->tables;
        $columns = $this->columns;
        $lastInsertId = $this->lastInsertId;

        try {
            return $operation();
        } catch (\Throwable $exception) {
            $this->tables = $tables;
            $this->columns = $columns;
            $this->lastInsertId = $lastInsertId;

            throw $exception;
        }
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $parts = [];
        $start = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote) {
                if ($char === "'" && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                } elseif ($char === "'") {
                    $quote = false;
                }
                continue;
            }
            if ($char === "'") {
                $quote = true;
            } elseif ($char === ';') {
                $part = trim(substr($sql, $start, $i - $start));
                if ($part !== '') {
                    $parts[] = $part;
                }
                $start = $i + 1;
            }
        }
        $tail = trim(substr($sql, $start));
        if ($tail !== '') {
            $parts[] = $tail;
        }

        return $parts;
    }

    private function createTable(string $sql): void
    {
        if (preg_match('/^create\s+table\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)$/is', $sql, $match) !== 1) {
            throw new \PDOException('SQLitePDO CREATE TABLE support requires a simple column list');
        }
        $table = $match[1];
        $columns = [];
        foreach ($this->splitTopLevel($match[2], ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint\s+\S+\s+)?(?:primary|unique|check|foreign)\b/i', $definition) === 1) {
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\b/', $definition, $columnMatch) !== 1) {
                throw new \PDOException('SQLitePDO CREATE TABLE column is malformed');
            }
            $columns[] = $columnMatch[1];
        }
        if ($columns === []) {
            throw new \PDOException('SQLitePDO CREATE TABLE needs at least one column');
        }
        $this->tables[$table] = [];
        $this->columns[$table] = $columns;
    }

    /** @param array<int|string,mixed> $parameters */
    private function insertValues(string $sql, array $parameters): int
    {
        $statement = SQLiteInsertValuesSql::parse($sql);
        $table = $statement['target'];
        $this->assertTable($table);
        $columns = $statement['columns'] ?? $this->columns[$table];
        $this->assertColumns($table, $columns);
        $changes = 0;
        $parameterCursor = $parameters;
        foreach ($statement['tuples'] as $values) {
            if (count($values) !== count($columns)) {
                throw new \PDOException('SQLitePDO INSERT column count does not match value count');
            }
            $row = array_fill_keys($this->columns[$table], null);
            foreach ($columns as $index => $column) {
                $row[$column] = $this->value($values[$index], $parameterCursor);
            }
            $rowidColumn = $this->columns[$table][0] ?? null;
            if ($rowidColumn !== null && ($row[$rowidColumn] ?? null) === null && preg_match('/(?:^|_)id$/i', $rowidColumn) === 1) {
                $row[$rowidColumn] = $this->nextRowId($table, $rowidColumn);
            }
            $this->tables[$table][] = $row;
            $this->lastInsertId = is_int($row[$rowidColumn] ?? null) ? $row[$rowidColumn] : count($this->tables[$table]);
            $changes++;
        }

        return $changes;
    }

    /** @param array<int|string,mixed> $parameters */
    private function updateRows(string $sql, array $parameters): int
    {
        if (preg_match('/^update\s+([A-Za-z_][A-Za-z0-9_]*)\s+set\s+(.+?)(?:\s+where\s+(.+))?$/is', $sql, $match) !== 1) {
            throw new \PDOException('SQLitePDO UPDATE support requires UPDATE table SET ... [WHERE ...]');
        }
        $table = $match[1];
        $this->assertTable($table);
        $assignments = [];
        foreach ($this->splitTopLevel($match[2], ',') as $assignment) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)\s*$/s', $assignment, $assignmentMatch) !== 1) {
                throw new \PDOException('SQLitePDO UPDATE assignment is malformed');
            }
            $assignments[$assignmentMatch[1]] = $assignmentMatch[2];
        }
        $this->assertColumns($table, array_keys($assignments));
        $indexes = $this->matchingIndexes($table, $match[3] ?? null, $parameters);
        foreach ($indexes as $index) {
            $parameterCursor = $parameters;
            foreach ($assignments as $column => $expression) {
                $this->tables[$table][$index][$column] = $this->value($expression, $parameterCursor, $this->tables[$table][$index]);
            }
        }

        return count($indexes);
    }

    /** @param array<int|string,mixed> $parameters */
    private function deleteRows(string $sql, array $parameters): int
    {
        if (preg_match('/^delete\s+from\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+where\s+(.+))?$/is', $sql, $match) !== 1) {
            throw new \PDOException('SQLitePDO DELETE support requires DELETE FROM table [WHERE ...]');
        }
        $table = $match[1];
        $this->assertTable($table);
        $indexes = $this->matchingIndexes($table, $match[2] ?? null, $parameters);
        foreach (array_reverse($indexes) as $index) {
            array_splice($this->tables[$table], $index, 1);
        }

        return count($indexes);
    }

    /** @param array<int|string,mixed> $parameters @return list<int> */
    private function matchingIndexes(string $table, ?string $where, array $parameters): array
    {
        if ($where === null || trim($where) === '') {
            return array_keys($this->tables[$table]);
        }
        $rows = [];
        foreach ($this->tables[$table] as $index => $row) {
            $rows[] = ['__sqlitepdo_index' => $index] + $row;
        }
        $matches = SQLiteSelectSql::execute("SELECT __sqlitepdo_index FROM {$table} WHERE {$where}", [$table => $rows], $parameters);

        return array_map(static fn (array $row): int => (int) $row['__sqlitepdo_index'], $matches);
    }

    private function assertTable(string $table): void
    {
        if (!array_key_exists($table, $this->tables)) {
            throw new \PDOException("SQLitePDO table {$table} does not exist");
        }
    }

    /** @param list<string> $columns */
    private function assertColumns(string $table, array $columns): void
    {
        $known = array_flip($this->columns[$table]);
        foreach ($columns as $column) {
            if (!array_key_exists($column, $known)) {
                throw new \PDOException("SQLitePDO table {$table} has no column named {$column}");
            }
        }
    }

    private function nextRowId(string $table, string $column): int
    {
        $max = 0;
        foreach ($this->tables[$table] as $row) {
            if (is_int($row[$column] ?? null)) {
                $max = max($max, $row[$column]);
            }
        }

        return $max + 1;
    }

    /** @param array<int|string,mixed> $parameters @param array<string,mixed> $row */
    private function value(string $expression, array &$parameters, array $row = []): mixed
    {
        $expression = trim($expression);
        if ($expression === '?') {
            return array_shift($parameters);
        }
        if (preg_match('/^:([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $match) === 1) {
            return $parameters[':' . $match[1]] ?? $parameters[$match[1]] ?? null;
        }
        if (array_key_exists($expression, $row)) {
            return $row[$expression];
        }
        if (preg_match('/^null$/i', $expression) === 1) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $expression) === 1) {
            return (int) $expression;
        }
        if (preg_match('/^-?\d+\.\d+$/', $expression) === 1) {
            return (float) $expression;
        }
        if (preg_match("/^'(.*)'$/s", $expression, $match) === 1) {
            return str_replace("''", "'", $match[1]);
        }
        if (preg_match('/^(jsonb?)\s*\((.*)\)$/is', $expression, $match) === 1) {
            $arguments = [];
            foreach ($this->splitTopLevel($match[2], ',') as $argument) {
                $arguments[] = $this->value($argument, $parameters, $row);
            }

            return SQLiteJsonCanonical::jsonSqlFunctionArguments($match[1], $arguments);
        }

        throw new \PDOException("SQLitePDO unsupported scalar expression: {$expression}");
    }

    /** @return list<string> */
    private function splitTopLevel(string $sql, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote) {
                if ($char === "'" && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                } elseif ($char === "'") {
                    $quote = false;
                }
                continue;
            }
            if ($char === "'") {
                $quote = true;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === $delimiter && $depth === 0) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return $parts;
    }
}
