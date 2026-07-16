<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePDO extends \PDO
{
    private const MAX_VARIABLE_NUMBER = 32766;

    /** @var array<string,list<array<string,mixed>>> */
    private array $tables = [];

    /** @var array<string,list<string>> */
    private array $columns = [];

    /** @var array<string,string> */
    private array $tableSql = [];

    /** @var array<string,string|null> */
    private array $rowidAliases = [];

    private ?string $filePath = null;
    private int $schemaCookie = 0;
    private int $fileChangeCounter = 0;
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
            $this->openFileBackedDatabase($path);
        }
        foreach ($options ?? [] as $attribute => $value) {
            $this->setAttribute((int) $attribute, $value);
        }
    }

    private function openFileBackedDatabase(string $path): void
    {
        if (is_dir($path)) {
            throw new \PDOException("SQLitePDO invalid DSN sqlite:{$path}: path is a directory");
        }

        $parent = dirname($path);
        if ($parent !== '' && !is_dir($parent)) {
            throw new \PDOException("SQLitePDO cannot open sqlite:{$path}: parent directory does not exist");
        }

        if (!is_file($path) && @file_put_contents($path, '') === false) {
            throw new \PDOException("SQLitePDO cannot create SQLite file: {$path}");
        }

        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            throw new \PDOException("SQLitePDO cannot resolve SQLite file: {$path}");
        }

        $this->filePath = $resolvedPath;
        clearstatcache(true, $resolvedPath);
        $size = filesize($resolvedPath);
        if ($size === false) {
            throw new \PDOException("SQLitePDO cannot inspect SQLite file: {$path}");
        }
        if ($size === 0) {
            return;
        }

        try {
            $state = SQLitePdoFileImage::decode($resolvedPath);
        } catch (\Throwable $exception) {
            throw new \PDOException("SQLitePDO cannot open SQLite file image {$path}: {$exception->getMessage()}", 0, $exception);
        }

        $this->columns = $state['columns'];
        $this->tables = $state['tables'];
        $this->tableSql = $state['tableSql'];
        $this->rowidAliases = $state['rowidAliases'];
        $this->schemaCookie = $state['schemaCookie'];
        $this->fileChangeCounter = $state['fileChangeCounter'];
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $statement = $this->prepare($query);
        if ($statement === false) {
            return false;
        }
        try {
            if ($statement->execute() === false) {
                $this->recordErrorInfo($statement->errorInfo());

                return false;
            }
        } catch (\PDOException $exception) {
            $this->recordErrorInfo($statement->errorInfo());
            throw $exception;
        }
        if ($fetchMode !== null) {
            $statement->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        return $statement;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $initialStatementErrorInfo = ['', $this->errorInfo[1], $this->errorInfo[2]];
        foreach ($options as $attribute => $value) {
            if ((int) $attribute !== \PDO::ATTR_CURSOR || $value !== \PDO::CURSOR_FWDONLY) {
                throw new \PDOException('SQLitePDO prepare options are not supported');
            }
        }
        if (count($options) > 1) {
            throw new \PDOException('SQLitePDO prepare options are not supported');
        }

        try {
            $this->validatePrepareSql($query);
        } catch (\PDOException $exception) {
            return $this->handleConnectionFailure($exception, __METHOD__);
        }

        return new SQLitePDOStatement($this, $query, $initialStatementErrorInfo);
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
            if (!in_array($value, [\PDO::ERRMODE_SILENT, \PDO::ERRMODE_WARNING, \PDO::ERRMODE_EXCEPTION], true)) {
                throw new \PDOException('SQLitePDO error mode is not supported');
            }
            $this->errmode = (int) $value;

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

    public function exec(string $statement, ?array $params = null): int|false
    {
        if ($params !== null) {
            $statements = $this->splitStatements($statement);
            if (count($statements) > 1) {
                return $this->handleConnectionFailure(
                    $this->failure('SQLitePDO exec with parameters does not support multi-statement SQL batches'),
                    __METHOD__,
                );
            }

            $prepared = $this->prepare($statements[0] ?? '');
            if ($prepared === false) {
                return false;
            }
            try {
                if ($prepared->execute($params) === false) {
                    $this->recordErrorInfo($prepared->errorInfo());

                    return false;
                }
            } catch (\PDOException $exception) {
                $this->recordErrorInfo($prepared->errorInfo());
                throw $exception;
            }
            $this->lastChanges = $prepared->rowCount();

            return $this->lastChanges;
        }

        $changes = 0;
        try {
            foreach ($this->splitStatements($statement) as $sql) {
                $result = $this->executeSql($sql, []);
                $changes += $result['changes'];
            }
        } catch (\PDOException $exception) {
            return $this->handleConnectionFailure($exception, __METHOD__);
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
        $this->transactionSnapshot = [
            $this->tables,
            $this->columns,
            $this->tableSql,
            $this->rowidAliases,
            $this->lastInsertId,
            $this->schemaCookie,
            $this->fileChangeCounter,
        ];

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

    /**
     * @internal
     */
    public function handleStatementFailure(\PDOException $exception, string $method): false
    {
        if ($this->errmode === \PDO::ERRMODE_EXCEPTION) {
            throw $exception;
        }
        if ($this->errmode === \PDO::ERRMODE_WARNING) {
            $this->emitPdoWarning($method, $exception);
        }

        return false;
    }

    /**
     * @internal
     * @return array{code:string,info:array{0:string,1:int|null,2:string|null}}
     */
    public function pdoErrorState(): array
    {
        return ['code' => $this->errorCode, 'info' => $this->errorInfo];
    }

    /**
     * @internal
     * @param array{code:string,info:array{0:string,1:int|null,2:string|null}} $state
     */
    public function restorePdoErrorState(array $state): void
    {
        $this->errorCode = $state['code'];
        $this->errorInfo = $state['info'];
    }

    public function commit(): bool
    {
        if ($this->transactionSnapshot === null) {
            throw new \PDOException('SQLitePDO has no active transaction');
        }
        $this->persistIfNeeded(true);
        $this->transactionSnapshot = null;

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionSnapshot === null) {
            throw new \PDOException('SQLitePDO has no active transaction');
        }
        [
            $this->tables,
            $this->columns,
            $this->tableSql,
            $this->rowidAliases,
            $this->lastInsertId,
            $this->schemaCookie,
            $this->fileChangeCounter,
        ] = $this->transactionSnapshot;
        $this->transactionSnapshot = null;

        return true;
    }

    /**
     * @param array<int|string,mixed> $parameters
     * @return array{rows:list<array<string,mixed>>,changes:int}
     */
    public function executeSql(string $sql, array $parameters, bool $validateParameterTokens = false): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if ($sql === '') {
            $this->clearError();
            return ['rows' => [], 'changes' => 0];
        }
        try {
            if ($validateParameterTokens) {
                $this->assertParameterTokensAreValid($sql);
            }
            if (preg_match('/^(?:select|values|with)\b/i', $sql) === 1) {
                $result = ['rows' => SQLiteSelectSql::execute($sql, $this->tables, $parameters), 'changes' => 0];
                $this->clearError();

                return $result;
            }
            if (preg_match('/^create\s+table\b/i', $sql) === 1) {
                $this->executeDataChangeAtomically(
                    function () use ($sql): array {
                        $this->createTable($sql);

                        return ['rows' => [], 'changes' => 0];
                    },
                );
                $this->clearError();
                return ['rows' => [], 'changes' => 0];
            }
            if (SQLiteInsertValuesSql::startsWithInsertKeyword($sql)) {
                $this->validateInsertPrepareSql($sql);
                $result = $this->executeDataChangeAtomically(
                    fn (): array => ['rows' => [], 'changes' => $this->insertValues($sql, $parameters)],
                );
                $this->clearError();

                return $result;
            }
            if (preg_match('/^update\b/i', $sql) === 1) {
                $this->validateUpdatePrepareSql($sql);
                $result = $this->executeDataChangeAtomically(
                    fn (): array => ['rows' => [], 'changes' => $this->updateRows($sql, $parameters)],
                );
                $this->clearError();

                return $result;
            }
            if (preg_match('/^delete\b/i', $sql) === 1) {
                $this->validateDeletePrepareSql($sql);
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
            throw $this->failure($this->pdoSqliteMessage($exception), $exception);
        }

        throw $this->failure("SQLitePDO unsupported SQL statement: {$sql}");
    }

    private function clearError(): void
    {
        $this->errorCode = '00000';
        $this->errorInfo = ['00000', null, null];
    }

    private function failure(string $message, ?\Throwable $previous = null, int $driverCode = 1): \PDOException
    {
        $this->errorCode = 'HY000';
        $this->errorInfo = ['HY000', $driverCode, $message];

        return $this->pdoException($message, $driverCode, $previous);
    }

    private function handleConnectionFailure(\PDOException $exception, string $method): false
    {
        if ($this->errmode === \PDO::ERRMODE_EXCEPTION) {
            throw $exception;
        }
        if ($this->errmode === \PDO::ERRMODE_WARNING) {
            $this->emitPdoWarning($method, $exception);
        }

        return false;
    }

    /** @param array{0:string,1:int|null,2:string|null} $errorInfo */
    private function recordErrorInfo(array $errorInfo): void
    {
        $this->errorCode = $errorInfo[0];
        $this->errorInfo = $errorInfo;
    }

    private function pdoException(string $message, int $driverCode = 1, ?\Throwable $previous = null): \PDOException
    {
        $exception = new \PDOException($message, 0, $previous);
        $exception->errorInfo = ['HY000', $driverCode, $message];
        $this->setExceptionCode($exception, 'HY000');

        return $exception;
    }

    private function emitPdoWarning(string $method, \PDOException $exception): void
    {
        $errorInfo = $exception->errorInfo ?? $this->errorInfo;
        $sqlState = is_string($errorInfo[0] ?? null) ? $errorInfo[0] : 'HY000';
        $driverCode = is_int($errorInfo[1] ?? null) ? $errorInfo[1] : 1;
        $message = is_string($errorInfo[2] ?? null) ? $errorInfo[2] : $exception->getMessage();

        trigger_error(
            "{$this->nativePdoWarningMethod($method)}(): SQLSTATE[{$sqlState}]: General error: {$driverCode} {$message}",
            E_USER_WARNING,
        );
    }

    private function nativePdoWarningMethod(string $method): string
    {
        [$class, $name] = array_pad(explode('::', $method, 2), 2, '');

        return match ($class) {
            self::class => 'PDO::' . $name,
            SQLitePDOStatement::class => 'PDOStatement::' . $name,
            default => $method,
        };
    }

    private function setExceptionCode(\PDOException $exception, string $code): void
    {
        try {
            static $property = null;
            $property ??= new \ReflectionProperty(\Exception::class, 'code');
            $property->setValue($exception, $code);
        } catch (\Throwable) {
        }
    }

    private function pdoSqliteMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (preg_match('/^SQLite SELECT (?:expression|predicate) row is missing column (.+)$/', $message, $match) === 1) {
            return 'no such column: ' . $match[1];
        }
        if (preg_match('/^SQLite GROUP BY row is missing column (.+)$/', $message, $match) === 1) {
            return 'no such column: ' . $match[1];
        }
        if (preg_match('/^SQLitePDO table ([A-Za-z_][A-Za-z0-9_]*) does not exist$/', $message, $match) === 1) {
            return 'no such table: ' . $match[1];
        }
        if (preg_match('/^SQLite SELECT SQL (?:source )?table ([A-Za-z_][A-Za-z0-9_]*) is not available$/', $message, $match) === 1) {
            return 'no such table: ' . $match[1];
        }
        if (preg_match('/^SQLite SELECT SQL (variable number must be between \?1 and \?' . self::MAX_VARIABLE_NUMBER . ')$/', $message, $match) === 1) {
            return $match[1];
        }

        return $message;
    }

    private function validatePrepareSql(string $sql): void
    {
        $statements = $this->splitStatements($sql);
        if ($statements === []) {
            $this->clearError();
            return;
        }

        foreach ($statements as $statement) {
            $statement = trim(rtrim(trim($statement), ';'));
            if ($statement === '') {
                continue;
            }

            try {
                if (preg_match('/^(?:select|values|with)\b/i', $statement) === 1) {
                    $this->validateSelectPrepareSql($statement);
                    continue;
                }
                if (preg_match('/^create\s+table\b/i', $statement) === 1) {
                    $this->validateCreateTablePrepareSql($statement);
                    continue;
                }
                if (SQLiteInsertValuesSql::startsWithInsertKeyword($statement)) {
                    $this->validateInsertPrepareSql($statement);
                    continue;
                }
                if (preg_match('/^update\b/i', $statement) === 1) {
                    $this->validateUpdatePrepareSql($statement);
                    continue;
                }
                if (preg_match('/^delete\b/i', $statement) === 1) {
                    $this->validateDeletePrepareSql($statement);
                }
            } catch (\Throwable $exception) {
                $message = $this->pdoSqliteMessage($exception);
                if ($this->isColumnResolutionError($message)
                    || $this->isTableResolutionError($message)
                    || $this->isParameterResolutionError($message)
                    || $this->isCreateTableResolutionError($message)
                ) {
                    throw $this->failure($message, $exception);
                }
            }
        }

        $this->clearError();
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function prepareValidationTables(): array
    {
        $tables = $this->tables;
        foreach ($this->columns as $table => $columns) {
            if (($tables[$table] ?? []) === []) {
                $tables[$table] = [array_fill_keys($columns, null)];
            }
        }

        return $tables;
    }

    private function validateInsertPrepareSql(string $sql): void
    {
        $statement = SQLiteInsertValuesSql::parse($sql);
        $table = $statement['target'];
        $this->assertWritableInsertTarget($table);
        $this->assertTable($table);
        $columns = $statement['columns'] ?? $this->columns[$table];
        $this->assertColumns($table, $columns, 'insert');
        foreach ($statement['tuples'] as $values) {
            if (count($values) !== count($columns)) {
                continue;
            }
            foreach ($values as $expression) {
                $this->assertPrepareScalarReferences($expression, []);
            }
        }
    }

    private function validateCreateTablePrepareSql(string $sql): void
    {
        $table = $this->createTableName($sql);
        if ($this->hasTable($table)) {
            throw new \PDOException("table {$table} already exists");
        }

        try {
            SQLitePdoFileImage::parseCreateTableDefinition($sql);
        } catch (\InvalidArgumentException $exception) {
            throw new \PDOException($exception->getMessage(), 0, $exception);
        }
    }

    private function validateSelectPrepareSql(string $sql): void
    {
        $tables = $this->prepareValidationTables();
        $plan = SQLiteSelectSql::plan($sql, $tables, []);
        $this->assertSelectPlanColumnReferences($plan['select'] ?? [], $this->selectPlanColumns($plan));
        if (isset($plan['where'])) {
            $this->assertSelectPlanColumnReferences($plan['where'], $this->selectPlanColumns($plan));
        }

        SQLiteSelectSql::execute($sql, $tables, []);
    }

    /** @param array<string,mixed> $plan @return array<string,bool> */
    private function selectPlanColumns(array $plan): array
    {
        $columns = ['rowid' => true, '_rowid_' => true, 'oid' => true];
        foreach (($plan['from'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $column) {
                if (!is_string($column)) {
                    continue;
                }
                $columns[$column] = true;
                if (str_contains($column, '.')) {
                    $columns[substr($column, strrpos($column, '.') + 1)] = true;
                }
            }
        }

        return $columns;
    }

    /** @param array<string,bool> $knownColumns */
    private function assertSelectPlanColumnReferences(mixed $node, array $knownColumns): void
    {
        if (!is_array($node)) {
            return;
        }

        if (($node['type'] ?? null) === 'column' && isset($node['name']) && is_string($node['name'])) {
            if (!isset($node['sourceExpression']) && !$this->selectPlanHasColumn($node['name'], $knownColumns)) {
                throw new \PDOException("no such column: {$node['name']}");
            }
            if (isset($node['sourceExpression'])) {
                $this->assertSelectPlanColumnReferences($node['sourceExpression'], $knownColumns);
            }

            return;
        }

        foreach ($node as $value) {
            $this->assertSelectPlanColumnReferences($value, $knownColumns);
        }
    }

    /** @param array<string,bool> $knownColumns */
    private function selectPlanHasColumn(string $column, array $knownColumns): bool
    {
        if (array_key_exists($column, $knownColumns)) {
            return true;
        }
        if (str_contains($column, '.')) {
            return array_key_exists(substr($column, strrpos($column, '.') + 1), $knownColumns);
        }

        return false;
    }

    private function validateUpdatePrepareSql(string $sql): void
    {
        if (preg_match('/^update(?:\s+or\s+(?:rollback|abort|replace|fail|ignore))?\s+([A-Za-z_][A-Za-z0-9_]*)\s+set\s+(.+?)(?:\s+where\s+(.+))?$/is', $sql, $match) !== 1) {
            return;
        }

        $table = $match[1];
        $this->assertTable($table);
        $assignments = [];
        foreach ($this->splitTopLevel($match[2], ',') as $assignment) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)\s*$/s', $assignment, $assignmentMatch) !== 1) {
                continue;
            }
            $assignments[$assignmentMatch[1]] = $assignmentMatch[2];
        }
        $this->assertColumns($table, array_keys($assignments), 'update');
        $knownColumns = array_fill_keys($this->columns[$table], true);
        foreach ($assignments as $expression) {
            $this->assertPrepareScalarReferences($expression, $knownColumns);
        }
        if (isset($match[3]) && trim($match[3]) !== '') {
            $this->validateWherePrepareSql($table, $match[3]);
        }
    }

    private function validateDeletePrepareSql(string $sql): void
    {
        if (preg_match('/^delete\s+from\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+where\s+(.+))?$/is', $sql, $match) !== 1) {
            return;
        }

        $table = $match[1];
        $this->assertTable($table);
        if (isset($match[2]) && trim($match[2]) !== '') {
            $this->validateWherePrepareSql($table, $match[2]);
        }
    }

    private function validateWherePrepareSql(string $table, string $where): void
    {
        $row = ['__sqlitepdo_index' => 0] + array_fill_keys($this->columns[$table], null);
        SQLiteSelectSql::execute(
            "SELECT __sqlitepdo_index FROM {$table} WHERE {$where}",
            [$table => [$row]],
            [],
        );
    }

    /** @param array<string,bool> $knownColumns */
    private function assertPrepareScalarReferences(string $expression, array $knownColumns): void
    {
        $expression = trim($expression);
        if (preg_match('/^\?[0-9]+$/', $expression) === 1) {
            $this->explicitParameterIndex($expression);

            return;
        }
        if ($expression === ''
            || preg_match('/^\?(?:\d+)?$/', $expression) === 1
            || preg_match('/^[:@$][A-Za-z_][A-Za-z0-9_]*(?:::[A-Za-z_][A-Za-z0-9_]*)?(?:\([^)]*\))?$/', $expression) === 1
            || preg_match('/^null$/i', $expression) === 1
            || preg_match('/^-?\d+(?:\.\d+)?$/', $expression) === 1
            || preg_match("/^(?:X)?'(?:''|[^'])*'$/i", $expression) === 1
        ) {
            return;
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $match) === 1) {
            if (!array_key_exists($match[1], $knownColumns)) {
                throw new \PDOException("no such column: {$match[1]}");
            }

            return;
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s*\((.*)\)$/s', $expression, $match) === 1) {
            foreach ($this->splitTopLevel($match[1], ',') as $argument) {
                $this->assertPrepareScalarReferences($argument, $knownColumns);
            }

            return;
        }

        $this->assertPrepareExpressionReferences($expression, $knownColumns);
    }

    /** @param array<string,bool> $knownColumns */
    private function assertPrepareExpressionReferences(string $expression, array $knownColumns): void
    {
        $length = strlen($expression);
        $expectingTypeName = false;
        $expectingCollationName = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if (($char === 'X' || $char === 'x') && ($expression[$i + 1] ?? null) === "'") {
                $i = $this->skipSingleQuotedSql($expression, $i + 1);
                continue;
            }
            if ($char === "'") {
                $i = $this->skipSingleQuotedSql($expression, $i);
                continue;
            }
            if ($char === '?') {
                $end = $i + 1;
                while ($end < $length && ctype_digit($expression[$end])) {
                    $end++;
                }
                $token = substr($expression, $i, $end - $i);
                if ($token !== '?') {
                    $this->explicitParameterIndex($token);
                }
                $i = $end - 1;
                continue;
            }
            if ($char === ':' || $char === '@' || $char === '$') {
                $token = $this->namedParameterToken($expression, $i);
                if ($token !== null) {
                    $i += strlen($token) - 1;
                }
                continue;
            }
            if (!ctype_alpha($char) && $char !== '_') {
                continue;
            }

            $start = $i;
            $i++;
            while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                $i++;
            }
            $identifier = substr($expression, $start, $i - $start);
            $i--;
            $lowerIdentifier = strtolower($identifier);
            $next = $this->nextNonWhitespaceChar($expression, $i + 1);
            $previous = $this->previousNonWhitespaceChar($expression, $start - 1);
            if ($lowerIdentifier === 'as') {
                $expectingTypeName = true;
                continue;
            }
            if ($lowerIdentifier === 'collate') {
                $expectingCollationName = true;
                continue;
            }
            if ($expectingTypeName && $this->isPrepareExpressionTypeName($identifier)) {
                $expectingTypeName = false;
                continue;
            }
            if ($expectingCollationName) {
                $expectingCollationName = false;
                continue;
            }
            $expectingTypeName = false;
            if ($next === '(' || $next === '.' || $this->isPrepareExpressionKeyword($identifier)) {
                continue;
            }
            if (array_key_exists($identifier, $knownColumns)) {
                continue;
            }
            if ($previous === '.' && array_key_exists($identifier, $knownColumns)) {
                continue;
            }

            throw new \PDOException("no such column: {$identifier}");
        }
    }

    private function skipSingleQuotedSql(string $sql, int $quoteOffset): int
    {
        $length = strlen($sql);
        for ($i = $quoteOffset + 1; $i < $length; $i++) {
            if ($sql[$i] === "'" && ($sql[$i + 1] ?? null) === "'") {
                $i++;
                continue;
            }
            if ($sql[$i] === "'") {
                return $i;
            }
        }

        return $length - 1;
    }

    private function nextNonWhitespaceChar(string $sql, int $offset): ?string
    {
        $length = strlen($sql);
        while ($offset < $length) {
            if (!ctype_space($sql[$offset])) {
                return $sql[$offset];
            }
            $offset++;
        }

        return null;
    }

    private function previousNonWhitespaceChar(string $sql, int $offset): ?string
    {
        while ($offset >= 0) {
            if (!ctype_space($sql[$offset])) {
                return $sql[$offset];
            }
            $offset--;
        }

        return null;
    }

    private function isPrepareExpressionKeyword(string $identifier): bool
    {
        static $keywords = [
            'and' => true,
            'between' => true,
            'case' => true,
            'current_date' => true,
            'current_time' => true,
            'current_timestamp' => true,
            'else' => true,
            'end' => true,
            'escape' => true,
            'false' => true,
            'glob' => true,
            'in' => true,
            'is' => true,
            'like' => true,
            'match' => true,
            'not' => true,
            'null' => true,
            'or' => true,
            'regexp' => true,
            'then' => true,
            'true' => true,
            'when' => true,
        ];

        return isset($keywords[strtolower($identifier)]);
    }

    private function isPrepareExpressionTypeName(string $identifier): bool
    {
        static $types = [
            'bigint' => true,
            'blob' => true,
            'boolean' => true,
            'char' => true,
            'clob' => true,
            'date' => true,
            'datetime' => true,
            'double' => true,
            'int' => true,
            'integer' => true,
            'numeric' => true,
            'real' => true,
            'text' => true,
            'varchar' => true,
        ];

        return isset($types[strtolower($identifier)]);
    }

    private function isColumnResolutionError(string $message): bool
    {
        return str_starts_with($message, 'no such column: ')
            || preg_match('/^table [A-Za-z_][A-Za-z0-9_]* has no column named [A-Za-z_][A-Za-z0-9_]*$/', $message) === 1;
    }

    private function isTableResolutionError(string $message): bool
    {
        return preg_match('/^no such table: [A-Za-z_][A-Za-z0-9_]*$/', $message) === 1;
    }

    private function isParameterResolutionError(string $message): bool
    {
        return $message === 'column index out of range'
            || $message === 'variable number must be between ?1 and ?' . self::MAX_VARIABLE_NUMBER;
    }

    private function isCreateTableResolutionError(string $message): bool
    {
        return preg_match('/^table [A-Za-z_][A-Za-z0-9_]* already exists$/', $message) === 1
            || str_starts_with($message, 'unrecognized token: ')
            || preg_match('/^near ".+": syntax error$/', $message) === 1
            || $message === 'incomplete input';
    }

    /**
     * @param array<int|string,mixed> $parameters
     */
    public function assertExecuteParameterArrayMatches(string $sql, array $parameters): void
    {
        $usedKeys = [];
        $positionalIndex = 1;
        $parameterCount = 0;
        $namedParameterIndexes = [];
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
                continue;
            }
            if ($char === '?') {
                $end = $i + 1;
                while ($end < $length && ctype_digit($sql[$end])) {
                    $end++;
                }
                $token = substr($sql, $i, $end - $i);
                $index = $token === '?' ? $positionalIndex++ : $this->explicitParameterIndex($token);
                if ($token !== '?') {
                    $positionalIndex = max($positionalIndex, $index + 1);
                }
                $parameterCount = max($parameterCount, $index);
                $zeroBased = $index - 1;
                $i = $end - 1;
                continue;
            }
            if ($char === ':') {
                $token = $this->namedParameterToken($sql, $i);
                if ($token === null) {
                    continue;
                }
                if (!array_key_exists($token, $namedParameterIndexes)) {
                    $namedParameterIndexes[$token] = $positionalIndex++;
                }
                $parameterCount = max($parameterCount, $namedParameterIndexes[$token]);
                $bare = substr($token, 1);
                if (array_key_exists($token, $parameters)) {
                    $usedKeys[$token] = true;
                }
                if (array_key_exists($bare, $parameters)) {
                    $usedKeys[$bare] = true;
                }
                $i += strlen($token) - 1;
            }
        }

        foreach (array_keys($parameters) as $key) {
            if (is_int($key) && $key >= 0 && $key < $parameterCount) {
                continue;
            }
            if (!array_key_exists((string) $key, $usedKeys)) {
                throw $this->failure('column index out of range', null, 25);
            }
        }
    }

    private function assertParameterTokensAreValid(string $sql): void
    {
        $quote = false;
        $positionalIndex = 1;
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
                continue;
            }
            if ($char === '?') {
                $end = $i + 1;
                while ($end < $length && ctype_digit($sql[$end])) {
                    $end++;
                }
                $token = substr($sql, $i, $end - $i);
                if ($token === '?') {
                    $positionalIndex++;
                } else {
                    $index = $this->explicitParameterIndex($token);
                    $positionalIndex = max($positionalIndex, $index + 1);
                }
                $i = $end - 1;
                continue;
            }
            if ($char === ':') {
                $token = $this->namedParameterToken($sql, $i);
                if ($token === null) {
                    continue;
                }
                $i += strlen($token) - 1;
            }
        }
    }

    private function namedParameterToken(string $sql, int $offset): ?string
    {
        $first = $sql[$offset + 1] ?? '';
        if ($first === '' || (!ctype_alpha($first) && $first !== '_')) {
            return null;
        }

        $end = $offset + 2;
        $length = strlen($sql);
        while ($end < $length) {
            $char = $sql[$end];
            if (!ctype_alnum($char) && $char !== '_') {
                break;
            }
            $end++;
        }

        return substr($sql, $offset, $end - $offset);
    }

    private function explicitParameterIndex(string $token): int
    {
        $digits = substr($token, 1);
        $normalized = ltrim($digits, '0');
        if ($normalized === '') {
            throw $this->failure('variable number must be between ?1 and ?' . self::MAX_VARIABLE_NUMBER);
        }

        $max = (string) self::MAX_VARIABLE_NUMBER;
        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            throw $this->failure('variable number must be between ?1 and ?' . self::MAX_VARIABLE_NUMBER);
        }

        return (int) $normalized;
    }

    private function persistIfNeeded(bool $force = false): void
    {
        if ($this->filePath === null || (!$force && $this->transactionSnapshot !== null)) {
            return;
        }

        $nextFileChangeCounter = $this->fileChangeCounter + 1;
        $bytes = SQLitePdoFileImage::encode(
            $this->columns,
            $this->tables,
            $this->tableSql,
            $this->rowidAliases,
            $this->schemaCookie,
            $nextFileChangeCounter,
        );

        $written = @file_put_contents($this->filePath, $bytes);
        if ($written !== strlen($bytes)) {
            throw new \PDOException("SQLitePDO cannot write SQLite file: {$this->filePath}");
        }
        $this->fileChangeCounter = $nextFileChangeCounter;
    }

    /**
     * @param callable(): array{rows:list<array<string,mixed>>,changes:int} $operation
     * @return array{rows:list<array<string,mixed>>,changes:int}
     */
    private function executeDataChangeAtomically(callable $operation): array
    {
        $tables = $this->tables;
        $columns = $this->columns;
        $tableSql = $this->tableSql;
        $rowidAliases = $this->rowidAliases;
        $lastInsertId = $this->lastInsertId;
        $schemaCookie = $this->schemaCookie;
        $fileChangeCounter = $this->fileChangeCounter;

        try {
            $result = $operation();
            $this->persistIfNeeded();

            return $result;
        } catch (\Throwable $exception) {
            $this->tables = $tables;
            $this->columns = $columns;
            $this->tableSql = $tableSql;
            $this->rowidAliases = $rowidAliases;
            $this->lastInsertId = $lastInsertId;
            $this->schemaCookie = $schemaCookie;
            $this->fileChangeCounter = $fileChangeCounter;

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
        $table = $this->createTableName($sql);
        if ($this->hasTable($table)) {
            throw new \PDOException("table {$table} already exists");
        }

        try {
            $definition = SQLitePdoFileImage::parseCreateTableDefinition($sql);
        } catch (\InvalidArgumentException $exception) {
            throw new \PDOException($exception->getMessage(), 0, $exception);
        }

        $this->tables[$table] = [];
        $this->columns[$table] = $definition['columns'];
        $this->tableSql[$table] = $sql;
        $this->rowidAliases[$table] = $definition['rowidAlias'];
        $this->schemaCookie++;
    }

    private function createTableName(string $sql): string
    {
        if (preg_match('/^create\s+table\s+([A-Za-z_][A-Za-z0-9_]*)\b/i', trim($sql), $match) !== 1) {
            throw new \PDOException('SQLitePDO CREATE TABLE support requires a simple column list');
        }

        return $match[1];
    }

    private function hasTable(string $table): bool
    {
        foreach (array_keys($this->tables) as $knownTable) {
            if (strcasecmp($knownTable, $table) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int|string,mixed> $parameters */
    private function insertValues(string $sql, array $parameters): int
    {
        $statement = SQLiteInsertValuesSql::parse($sql);
        $table = $statement['target'];
        $this->assertWritableInsertTarget($table);
        $this->assertTable($table);
        $columns = $statement['columns'] ?? $this->columns[$table];
        $this->assertColumns($table, $columns, 'insert');
        $metadata = $this->tableColumnMetadata($table);
        $changes = 0;
        $parameterCursor = $this->parameterCursor($parameters);
        foreach ($statement['tuples'] as $values) {
            if (count($values) !== count($columns)) {
                throw new \PDOException($this->insertColumnCountMessage($table, $statement['columns'], count($columns), count($values)));
            }
            $row = [];
            foreach ($this->columns[$table] as $column) {
                $default = $metadata[$column]['default'] ?? null;
                $value = $default === null ? null : $this->value($default, $parameterCursor, [], true);
                $row[$column] = $this->applyColumnAffinity($value, $metadata[$column]['type'] ?? '');
            }
            foreach ($columns as $index => $column) {
                $row[$column] = $this->applyColumnAffinity(
                    $this->value($values[$index], $parameterCursor),
                    $metadata[$column]['type'] ?? '',
                );
            }
            $rowidAlias = $this->rowidAliases[$table] ?? null;
            $rowidColumn = $rowidAlias ?? ($this->columns[$table][0] ?? null);
            if ($rowidAlias !== null && ($row[$rowidAlias] ?? null) === null) {
                $row[$rowidAlias] = $this->nextRowId($table, $rowidAlias);
            } elseif ($rowidColumn !== null && ($row[$rowidColumn] ?? null) === null && preg_match('/(?:^|_)id$/i', $rowidColumn) === 1) {
                $row[$rowidColumn] = $this->nextRowId($table, $rowidColumn);
            }
            $this->tables[$table][] = $row;
            $this->lastInsertId = is_int($row[$rowidColumn] ?? null) ? $row[$rowidColumn] : count($this->tables[$table]);
            $changes++;
        }

        return $changes;
    }

    private function assertWritableInsertTarget(string $table): void
    {
        if (str_starts_with(strtolower($table), 'sqlite_')) {
            throw new \PDOException("table {$table} may not be modified");
        }
    }

    /**
     * @param list<string>|null $explicitColumns
     */
    private function insertColumnCountMessage(string $table, ?array $explicitColumns, int $expected, int $actual): string
    {
        if ($explicitColumns !== null) {
            return "{$actual} values for {$expected} columns";
        }

        return "table {$table} has {$expected} columns but {$actual} values were supplied";
    }

    /**
     * @return array<string,array{type:string,default:?string}>
     */
    private function tableColumnMetadata(string $table): array
    {
        $schema = $this->tableSql[$table] ?? '';
        if ($schema === '') {
            $metadata = [];
            foreach ($this->columns[$table] ?? [] as $column) {
                $metadata[$column] = ['type' => '', 'default' => null];
            }

            return $metadata;
        }

        $open = strpos($schema, '(');
        if ($open === false) {
            throw new \PDOException('SQLitePDO CREATE TABLE support requires a simple column list');
        }
        $close = self::matchingParen($schema, $open);
        if ($close === null) {
            throw new \PDOException('SQLitePDO CREATE TABLE support requires a simple column list');
        }

        $metadata = [];
        foreach ($this->splitTopLevel(substr($schema, $open + 1, $close - $open - 1), ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint\s+\S+\s+)?(?:primary|unique|check|foreign)\b/i', $definition) === 1) {
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(.*)$/s', $definition, $match) !== 1) {
                throw new \PDOException('SQLitePDO CREATE TABLE column is malformed');
            }

            $tail = $match[2];
            $metadata[$match[1]] = [
                'type' => self::declaredTypeFromColumnTail($tail),
                'default' => self::defaultExpressionFromColumnTail($tail),
            ];
        }

        foreach ($this->columns[$table] ?? [] as $column) {
            $metadata[$column] ??= ['type' => '', 'default' => null];
        }

        return $metadata;
    }

    private static function declaredTypeFromColumnTail(string $tail): string
    {
        $end = self::firstTopLevelKeywordOffset($tail, ['CONSTRAINT', 'PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'DEFAULT', 'COLLATE', 'REFERENCES', 'GENERATED', 'AS']);
        $type = $end === null ? $tail : substr($tail, 0, $end);

        return trim(preg_replace('/\s+/', ' ', $type) ?? $type);
    }

    private static function defaultExpressionFromColumnTail(string $tail): ?string
    {
        $offset = self::firstTopLevelKeywordOffset($tail, ['DEFAULT']);
        if ($offset === null) {
            return null;
        }

        $start = self::skipWhitespaceStatic($tail, $offset + strlen('DEFAULT'));
        $end = self::firstTopLevelKeywordOffset(substr($tail, $start), ['CONSTRAINT', 'PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'COLLATE', 'REFERENCES', 'GENERATED']);
        $expression = $end === null ? substr($tail, $start) : substr($tail, $start, $end);
        $expression = trim($expression);

        return $expression === '' ? null : $expression;
    }

    /** @param array<int|string,mixed> $parameters */
    private function updateRows(string $sql, array $parameters): int
    {
        if (preg_match('/^update(?:\s+or\s+(?:rollback|abort|replace|fail|ignore))?\s+([A-Za-z_][A-Za-z0-9_]*)\s+set\s+(.+?)(?:\s+where\s+(.+))?$/is', $sql, $match) !== 1) {
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
        $this->assertColumns($table, array_keys($assignments), 'update');
        $where = isset($match[3]) && trim($match[3]) !== ''
            ? $this->rewriteUpdateWhereAnonymousParameters($match[3], array_values($assignments))
            : null;
        $indexes = $this->matchingIndexes($table, $where, $parameters);
        foreach ($indexes as $index) {
            $parameterCursor = $this->parameterCursor($parameters);
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

    /**
     * @param list<string> $assignmentExpressions
     */
    private function rewriteUpdateWhereAnonymousParameters(string $where, array $assignmentExpressions): string
    {
        $state = ['next' => 1, 'named' => []];
        foreach ($assignmentExpressions as $expression) {
            $this->scanUpdateParameterSql($expression, $state, false);
        }

        return $this->scanUpdateParameterSql($where, $state, true);
    }

    /**
     * @param array{next:int,named:array<string,int>} $state
     */
    private function scanUpdateParameterSql(string $sql, array &$state, bool $rewriteAnonymous): string
    {
        $result = '';
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                $result .= $char;
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $result .= "'";
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                $result .= $char;
                continue;
            }
            if ($char === '?') {
                $end = $i + 1;
                while ($end < $length && ctype_digit($sql[$end])) {
                    $end++;
                }
                $token = substr($sql, $i, $end - $i);
                if ($token === '?') {
                    $index = $state['next']++;
                    $result .= $rewriteAnonymous ? '?' . $index : $token;
                } else {
                    $index = $this->explicitParameterIndex($token);
                    $state['next'] = max($state['next'], $index + 1);
                    $result .= $token;
                }
                $i = $end - 1;
                continue;
            }
            if ($char === ':') {
                $token = $this->namedParameterToken($sql, $i);
                if ($token !== null) {
                    if (!array_key_exists($token, $state['named'])) {
                        $state['named'][$token] = $state['next']++;
                    }
                    $result .= $token;
                    $i += strlen($token) - 1;
                    continue;
                }
            }

            $result .= $char;
        }

        return $result;
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
    private function assertColumns(string $table, array $columns, string $operation): void
    {
        $known = array_flip($this->columns[$table]);
        foreach ($columns as $column) {
            if (!array_key_exists($column, $known)) {
                $message = $operation === 'update'
                    ? "no such column: {$column}"
                    : "table {$table} has no column named {$column}";
                throw new \PDOException($message);
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

    /**
     * @param array<int|string,mixed> $parameters
     * @return array{parameters:array<int|string,mixed>,anonymous:int}
     */
    private function parameterCursor(array $parameters): array
    {
        return ['parameters' => $parameters, 'anonymous' => 1];
    }

    /** @param array{parameters:array<int|string,mixed>,anonymous:int} $parameters @param array<string,mixed> $row */
    private function value(string $expression, array &$parameters, array $row = [], bool $bareIdentifierAsText = false): mixed
    {
        $expression = trim($expression);
        if (str_starts_with($expression, '(') && self::matchingParen($expression, 0) === strlen($expression) - 1) {
            return $this->value(substr($expression, 1, -1), $parameters, $row, $bareIdentifierAsText);
        }
        if ($expression === '?') {
            return $this->parameterValue($parameters, $parameters['anonymous']++);
        }
        if (preg_match('/^\?[0-9]+$/', $expression) === 1) {
            $index = $this->explicitParameterIndex($expression);
            $parameters['anonymous'] = max($parameters['anonymous'], $index + 1);

            return $this->parameterValue($parameters, $index);
        }
        if (preg_match('/^:([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $match) === 1) {
            return $this->parameterValue($parameters, ':' . $match[1]);
        }
        if (array_key_exists($expression, $row)) {
            return $row[$expression];
        }
        if (preg_match('/^null$/i', $expression) === 1) {
            return null;
        }
        if (preg_match('/^[+-]?\d+$/', $expression) === 1) {
            return (int) $expression;
        }
        if (preg_match('/^[+-]?(?:(?:\d+\.\d*)|(?:\d*\.\d+))(?:[eE][+-]?\d+)?$/', $expression) === 1
            || preg_match('/^[+-]?\d+[eE][+-]?\d+$/', $expression) === 1) {
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
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            if ($bareIdentifierAsText) {
                return $expression;
            }
            throw new \PDOException("no such column: {$expression}");
        }

        throw new \PDOException("SQLitePDO unsupported scalar expression: {$expression}");
    }

    private function applyColumnAffinity(mixed $value, string $declaredType): mixed
    {
        if ($value === null) {
            return null;
        }

        $coerced = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
            [['value' => $value]],
            ['value' => self::declaredAffinity($declaredType)],
        );

        return $coerced[0]['value'];
    }

    private static function declaredAffinity(string $declaredType): string
    {
        $type = strtoupper($declaredType);
        if ($type === '') {
            return 'BLOB';
        }
        if (str_contains($type, 'INT')) {
            return 'INTEGER';
        }
        if (str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'TEXT')) {
            return 'TEXT';
        }
        if (str_contains($type, 'BLOB')) {
            return 'BLOB';
        }
        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }

    /** @param array{parameters:array<int|string,mixed>,anonymous:int} $cursor */
    private function parameterValue(array $cursor, int|string $key): mixed
    {
        $parameters = $cursor['parameters'];
        if (is_int($key)) {
            return $parameters[$key] ?? null;
        }

        $bare = substr($key, 1);

        return $parameters[$key] ?? $parameters[$bare] ?? null;
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

    private static function matchingParen(string $sql, int $offset): ?int
    {
        if (($sql[$offset] ?? null) !== '(') {
            return null;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $offset; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote && ($sql[$i + 1] ?? null) === $quote) {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $keywords
     */
    private static function firstTopLevelKeywordOffset(string $text, array $keywords): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($quote !== null) {
                if ($char === $quote && ($text[$i + 1] ?? null) === $quote) {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($depth !== 0) {
                continue;
            }
            foreach ($keywords as $keyword) {
                $keywordLength = strlen($keyword);
                if (
                    strncasecmp(substr($text, $i, $keywordLength), $keyword, $keywordLength) === 0
                    && ($i === 0 || !self::isIdentifierChar($text[$i - 1]))
                    && (!isset($text[$i + $keywordLength]) || !self::isIdentifierChar($text[$i + $keywordLength]))
                ) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function skipWhitespaceStatic(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }
}
