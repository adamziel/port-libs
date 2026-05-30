<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePDOStatement extends \PDOStatement
{
    /** @var array<int|string,mixed> */
    private array $boundValues = [];

    /** @var array<int|string,mixed> */
    private array $boundReferences = [];

    /** @var list<array<string,mixed>> */
    private array $rows = [];

    private int $cursor = 0;
    private int $rowCount = 0;
    private int $fetchMode = \PDO::FETCH_BOTH;

    public function __construct(
        private readonly SQLitePDO $connection,
        private readonly string $sql,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $parameters = $this->boundValues;
        foreach ($this->boundReferences as $key => &$value) {
            $parameters[$key] = $value;
        }
        unset($value);
        if ($params !== null) {
            foreach ($params as $key => $value) {
                $parameters[is_int($key) ? $key + 1 : $key] = $value;
            }
        }
        $result = $this->connection->executeSql($this->sql, $parameters);
        $this->rows = $result['rows'];
        $this->rowCount = $result['changes'];
        $this->cursor = 0;

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

        return $this->formatRow($row, $mode === \PDO::FETCH_DEFAULT ? $this->fetchMode : $mode);
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $mode = $mode === \PDO::FETCH_DEFAULT ? $this->fetchMode : $mode;
        if ($mode === \PDO::FETCH_COLUMN) {
            $column = isset($args[0]) ? (int) $args[0] : 0;
            $values = [];
            while (($value = $this->fetchColumn($column)) !== false) {
                $values[] = $value;
            }

            return $values;
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
        $row = array_values($this->rows[$this->cursor++]);

        return $row[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $this->boundValues[$this->normalizeParam($param)] = $this->coerce($value, $type);

        return true;
    }

    public function bindParam(string|int $param, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        unset($type, $maxLength, $driverOptions);
        $this->boundReferences[$this->normalizeParam($param)] = &$var;

        return true;
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        if ($args !== []) {
            throw new \PDOException('SQLitePDOStatement fetch mode arguments are not supported');
        }
        if (!in_array($mode, [\PDO::FETCH_ASSOC, \PDO::FETCH_NUM, \PDO::FETCH_BOTH, \PDO::FETCH_COLUMN], true)) {
            throw new \PDOException('SQLitePDOStatement fetch mode is not supported');
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
            return array_values($row)[0] ?? false;
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
