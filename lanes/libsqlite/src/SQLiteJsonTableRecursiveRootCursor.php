<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonTableRecursiveRootCursor
{
    /** @var list<string> */
    private array $queue;
    /** @var array<string,true> */
    private array $seen = [];
    /** @var list<array<string,mixed>> */
    private array $currentRows = [];
    private ?string $currentRoot = null;
    private int $position = -1;

    /**
     * @param list<string> $roots
     */
    public function __construct(
        private readonly string $function,
        private readonly string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $json,
        array $roots = ['$'],
    ) {
        if (strcasecmp($function, 'json_tree') !== 0 && strcasecmp($function, 'json_each') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON recursive root cursor requires json_tree or json_each');
        }

        $this->queue = [];
        foreach ($roots as $root) {
            $this->enqueue($root);
        }
    }

    public static function tree(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $json, string $root = '$'): self
    {
        return new self('json_tree', $json, [$root]);
    }

    public static function each(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $json, string $root = '$'): self
    {
        return new self('json_each', $json, [$root]);
    }

    public function next(): bool
    {
        while ($this->queue !== []) {
            $root = array_shift($this->queue);
            if (!is_string($root)) {
                throw new \InvalidArgumentException('SQLite JSON recursive root queue root must be text');
            }

            $this->currentRoot = $root;
            $this->currentRows = $this->rowsForRoot($root);
            $this->position++;

            return true;
        }

        $this->currentRoot = null;
        $this->currentRows = [];

        return false;
    }

    public function currentRoot(): ?string
    {
        return $this->currentRoot;
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentRows(): array
    {
        return $this->currentRows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentAtomRows(): array
    {
        return array_values(array_filter(
            $this->currentRows,
            static fn (array $row): bool => ($row['atom'] ?? null) !== null,
        ));
    }

    /**
     * @return list<string>
     */
    public function queuedRoots(): array
    {
        return $this->queue;
    }

    /**
     * @return list<string>
     */
    public function enqueueChildRootsByKey(string|int $key): array
    {
        $roots = [];
        foreach ($this->currentRows as $row) {
            if (($row['key'] ?? null) !== $key) {
                continue;
            }
            if (($row['type'] ?? null) !== 'array' && ($row['type'] ?? null) !== 'object') {
                continue;
            }

            $fullkey = $row['fullkey'] ?? null;
            if (!is_string($fullkey)) {
                continue;
            }
            if ($this->enqueue($fullkey)) {
                $roots[] = $fullkey;
            }
        }

        return $roots;
    }

    /**
     * @return list<string>
     */
    public function enqueueRootsWhere(callable $predicate): array
    {
        $roots = [];
        foreach ($this->currentRows as $row) {
            if (!$predicate($row)) {
                continue;
            }

            $fullkey = $row['fullkey'] ?? null;
            if (!is_string($fullkey)) {
                continue;
            }
            if ($this->enqueue($fullkey)) {
                $roots[] = $fullkey;
            }
        }

        return $roots;
    }

    /**
     * @return list<array{root:string,position:int,rows:int,atoms:list<mixed>,queued:list<string>}>
     */
    public function drainByChildKey(string|int $key, int $limit = 100): array
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite JSON recursive root cursor limit must be non-negative');
        }

        $frames = [];
        while (count($frames) < $limit && $this->next()) {
            $queued = $this->enqueueChildRootsByKey($key);
            $frames[] = [
                'root' => (string) $this->currentRoot,
                'position' => $this->position,
                'rows' => count($this->currentRows),
                'atoms' => array_map(static fn (array $row): mixed => $row['atom'], $this->currentAtomRows()),
                'queued' => $queued,
            ];
        }

        return $frames;
    }

    private function enqueue(string $root): bool
    {
        if (!SQLiteJsonPath::isWellFormed($root)) {
            throw new \InvalidArgumentException('SQLite JSON recursive root cursor root must be a well-formed path');
        }
        if (isset($this->seen[$root])) {
            return false;
        }

        $this->seen[$root] = true;
        $this->queue[] = $root;

        return true;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rowsForRoot(string $root): array
    {
        try {
            if ($this->function === 'json_each') {
                return SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$this->json, $root]);
            }

            return SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$this->json, $root]);
        } catch (\InvalidArgumentException $exception) {
            if (SQLiteJsonTablePlan::invalidInputCanBeSkipped($this->json)) {
                return [];
            }

            throw $exception;
        }
    }
}
