<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePDOStatement extends \PDOStatement
{
    public const SUPPORTED_FETCH_MODES = [
        \PDO::FETCH_ASSOC,
        \PDO::FETCH_NUM,
        \PDO::FETCH_BOTH,
        \PDO::FETCH_COLUMN,
        \PDO::FETCH_OBJ,
        \PDO::FETCH_BOUND,
        \PDO::FETCH_CLASS,
        \PDO::FETCH_INTO,
        \PDO::FETCH_KEY_PAIR,
    ];

    private const FETCH_FLAG_MASK = \PDO::FETCH_UNIQUE | \PDO::FETCH_GROUP;

    /** @var array<int|string,mixed> */
    private array $boundValues = [];

    /** @var array<int|string,mixed> */
    private array $boundReferences = [];

    /** @var array<int|string,int> */
    private array $boundReferenceTypes = [];

    /** @var array<int|string,mixed> */
    private array $boundColumns = [];

    /** @var array<int|string,int> */
    private array $boundColumnTypes = [];

    /** @var list<array<string,mixed>> */
    private array $rows = [];

    private int $cursor = 0;
    private int $rowCount = 0;
    private ?int $fetchMode = null;
    private int $fetchColumnIndex = 0;
    private string $fetchClass = 'stdClass';
    private ?object $fetchInto = null;
    private string $errorCode = '00000';

    /** @var array{0:string,1:int|null,2:string|null} */
    private array $errorInfo = ['00000', null, null];

    public function __construct(
        private readonly SQLitePDO $connection,
        private readonly string $sql,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $requireBoundParameters = $params !== null;
        $parameters = $this->boundValues;
        foreach ($this->boundReferences as $key => &$value) {
            $parameters[$key] = $this->coerce($value, $this->boundReferenceTypes[$key] ?? \PDO::PARAM_STR);
        }
        unset($value);
        if ($params !== null) {
            foreach ($params as $key => $value) {
                $parameters[is_int($key) ? $key + 1 : $key] = $value;
            }
        }
        try {
            $result = $this->connection->executeSql($this->sql, $parameters, $requireBoundParameters);
        } catch (\PDOException $exception) {
            $this->errorCode = $this->connection->errorCode() ?? 'HY000';
            $this->errorInfo = $this->connection->errorInfo();

            throw $exception;
        }
        $this->rows = $result['rows'];
        $this->rowCount = $result['changes'];
        $this->cursor = 0;
        $this->errorCode = '00000';
        $this->errorInfo = ['00000', null, null];

        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        unset($cursorOffset);
        if ($cursorOrientation !== \PDO::FETCH_ORI_NEXT) {
            throw new \PDOException('SQLitePDOStatement supports only forward fetches');
        }
        if (!array_key_exists($this->cursor, $this->rows)) {
            return false;
        }
        $row = $this->rows[$this->cursor++];

        return $this->formatRow($row, $this->effectiveFetchMode($mode));
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $mode = $this->effectiveFetchMode($mode);
        if ($mode === \PDO::FETCH_KEY_PAIR) {
            if ($this->columnCount() !== 2) {
                throw new \PDOException('SQLitePDOStatement FETCH_KEY_PAIR needs exactly two columns');
            }
            $pairs = [];
            while (array_key_exists($this->cursor, $this->rows)) {
                $values = array_values($this->rows[$this->cursor++]);
                $pairs[$values[0]] = $values[1];
            }

            return $pairs;
        }
        if (($mode & \PDO::FETCH_UNIQUE) === \PDO::FETCH_UNIQUE) {
            return $this->fetchAllUnique($mode, ...$args);
        }
        if ($mode === \PDO::FETCH_COLUMN) {
            $column = isset($args[0]) ? (int) $args[0] : $this->fetchColumnIndex;
            $values = [];
            while (($value = $this->fetchColumn($column)) !== false) {
                $values[] = $value;
            }

            return $values;
        }
        if ($mode === \PDO::FETCH_CLASS || $mode === \PDO::FETCH_INTO) {
            $this->setFetchMode($mode, ...$args);
        }
        $rows = [];
        while (($row = $this->fetch($mode)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (!array_key_exists($this->cursor, $this->rows)) {
            return false;
        }
        $row = $this->rows[$this->cursor++];
        if ($column < 0) {
            throw new \PDOException('SQLitePDOStatement column index cannot be negative');
        }

        return array_values($row)[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }

    public function columnCount(): int
    {
        return isset($this->rows[0]) ? count($this->rows[0]) : 0;
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

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $this->boundValues[$this->normalizeParam($param)] = $this->coerce($value, $type);

        return true;
    }

    public function bindParam(string|int $param, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        unset($maxLength, $driverOptions);
        $this->boundReferences[$this->normalizeParam($param)] = &$var;
        $this->boundReferenceTypes[$this->normalizeParam($param)] = $type;

        return true;
    }

    public function bindColumn(string|int $column, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        unset($maxLength, $driverOptions);
        $this->boundColumns[$column] = &$var;
        $this->boundColumnTypes[$column] = $type;

        return true;
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        if (!in_array($mode, self::SUPPORTED_FETCH_MODES, true)) {
            throw new \PDOException('SQLitePDOStatement fetch mode is not supported');
        }
        if ($mode === \PDO::FETCH_CLASS) {
            $class = $args[0] ?? 'stdClass';
            if (!is_string($class) || !class_exists($class)) {
                throw new \PDOException('SQLitePDOStatement FETCH_CLASS needs an existing class name');
            }
            $this->fetchClass = $class;
        } elseif ($mode === \PDO::FETCH_COLUMN) {
            if (isset($args[0])) {
                $column = (int) $args[0];
                if ($column < 0) {
                    throw new \PDOException('SQLitePDOStatement column index cannot be negative');
                }
                $this->fetchColumnIndex = $column;
            }
        } elseif ($mode === \PDO::FETCH_INTO) {
            if (!isset($args[0]) || !is_object($args[0])) {
                throw new \PDOException('SQLitePDOStatement FETCH_INTO needs an object');
            }
            $this->fetchInto = $args[0];
        } elseif ($args !== []) {
            throw new \PDOException('SQLitePDOStatement fetch mode arguments are not supported');
        }
        $this->fetchMode = $mode;

        return true;
    }

    /** @param array<string,mixed> $row */
    private function formatRow(array $row, int $mode): mixed
    {
        if ($mode === \PDO::FETCH_ASSOC) {
            return $row;
        }
        if ($mode === \PDO::FETCH_NUM) {
            return array_values($row);
        }
        if ($mode === \PDO::FETCH_COLUMN) {
            return array_values($row)[$this->fetchColumnIndex] ?? false;
        }
        if ($mode === \PDO::FETCH_OBJ) {
            return (object) $row;
        }
        if ($mode === \PDO::FETCH_CLASS) {
            $class = $this->fetchClass;
            $object = new $class();
            foreach ($row as $column => $value) {
                $object->{$column} = $value;
            }

            return $object;
        }
        if ($mode === \PDO::FETCH_INTO) {
            if ($this->fetchInto === null) {
                throw new \PDOException('SQLitePDOStatement FETCH_INTO needs an object');
            }
            foreach ($row as $column => $value) {
                $this->fetchInto->{$column} = $value;
            }

            return $this->fetchInto;
        }
        if ($mode === \PDO::FETCH_BOUND) {
            foreach ($this->boundColumns as $column => &$target) {
                $target = $this->coerce($this->columnValue($row, $column), $this->boundColumnTypes[$column] ?? \PDO::PARAM_STR);
            }
            unset($target);

            return true;
        }
        if ($mode === \PDO::FETCH_BOTH) {
            $both = $row;
            foreach (array_values($row) as $index => $value) {
                $both[$index] = $value;
            }

            return $both;
        }

        throw new \PDOException('SQLitePDOStatement fetch mode is not supported');
    }

    private function effectiveFetchMode(int $mode): int
    {
        return $mode === \PDO::FETCH_DEFAULT
            ? ($this->fetchMode ?? $this->connection->defaultFetchMode())
            : $mode;
    }

    private function baseFetchMode(int $mode): int
    {
        return $mode & ~self::FETCH_FLAG_MASK;
    }

    private function fetchAllUnique(int $mode, mixed ...$args): array
    {
        $baseMode = $this->baseFetchMode($mode);
        if ($baseMode === 0) {
            $baseMode = $this->connection->defaultFetchMode();
        }
        if (!in_array($baseMode, [\PDO::FETCH_ASSOC, \PDO::FETCH_NUM, \PDO::FETCH_BOTH, \PDO::FETCH_OBJ, \PDO::FETCH_CLASS, \PDO::FETCH_INTO], true)) {
            throw new \PDOException('SQLitePDOStatement FETCH_UNIQUE mode is not supported');
        }
        if ($baseMode === \PDO::FETCH_CLASS || $baseMode === \PDO::FETCH_INTO) {
            $this->setFetchMode($baseMode, ...$args);
        }

        $rows = [];
        while (array_key_exists($this->cursor, $this->rows)) {
            $row = $this->rows[$this->cursor++];
            $values = array_values($row);
            $key = array_shift($values);
            $tail = array_slice($row, 1, null, true);
            if ($baseMode === \PDO::FETCH_NUM) {
                $tail = $values;
            } elseif ($baseMode === \PDO::FETCH_BOTH) {
                foreach ($values as $index => $value) {
                    $tail[$index] = $value;
                }
            }
            $rows[$key] = $this->formatRow($tail, $baseMode);
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function columnValue(array $row, string|int $column): mixed
    {
        if (is_int($column)) {
            if ($column < 1) {
                throw new \PDOException('SQLitePDOStatement bound column positions are 1-based');
            }

            return array_values($row)[$column - 1] ?? null;
        }

        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        throw new \PDOException("SQLitePDOStatement bound column {$column} does not exist");
    }

    private function normalizeParam(string|int $param): string|int
    {
        return is_string($param) && $param !== '' && $param[0] !== ':' ? ':' . $param : $param;
    }

    private function coerce(mixed $value, int $type): mixed
    {
        return match ($type) {
            \PDO::PARAM_INT => (int) $value,
            \PDO::PARAM_BOOL => (bool) $value,
            \PDO::PARAM_NULL => null,
            default => $value,
        };
    }
}
