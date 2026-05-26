<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSavepointStack
{
    /**
     * @var list<array{name:string,transaction:bool,pages:array<int,true>}>
     */
    private array $frames = [];

    public function beginTransaction(string $name = 'transaction'): void
    {
        if ($this->frames !== []) {
            throw new \LogicException('SQLite transaction is already active');
        }

        $this->frames[] = [
            'name' => $name,
            'transaction' => true,
            'pages' => [],
        ];
    }

    public function savepoint(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite savepoint name must not be empty');
        }

        if ($this->frames === []) {
            $this->beginTransaction($name);
            return;
        }

        $this->frames[] = [
            'name' => $name,
            'transaction' => false,
            'pages' => [],
        ];
    }

    public function recordPageWrite(int $pageNumber): void
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }

        if ($this->frames === []) {
            $this->beginTransaction();
        }

        $this->frames[array_key_last($this->frames)]['pages'][$pageNumber] = true;
    }

    public function rollbackTo(string $name): void
    {
        $index = $this->findFrame($name);
        $this->frames = array_slice($this->frames, 0, $index + 1);
        $this->frames[$index]['pages'] = [];
    }

    public function release(string $name): void
    {
        $index = $this->findFrame($name);
        $releasedFrames = array_splice($this->frames, $index);
        $releasedPages = [];
        foreach ($releasedFrames as $frame) {
            foreach ($frame['pages'] as $pageNumber => $_) {
                $releasedPages[$pageNumber] = true;
            }
        }

        if ($this->frames === []) {
            return;
        }

        foreach ($releasedPages as $pageNumber => $_) {
            $this->frames[array_key_last($this->frames)]['pages'][$pageNumber] = true;
        }
    }

    /**
     * @return list<int>
     */
    public function rollbackToPageNumbers(string $name): array
    {
        $index = $this->findFrame($name);
        $pages = [];
        for ($frameIndex = $index; $frameIndex < count($this->frames); $frameIndex++) {
            foreach ($this->frames[$frameIndex]['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    public function commit(): void
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $this->frames = [];
    }

    public function transactionActive(): bool
    {
        return $this->frames !== [];
    }

    public function depth(): int
    {
        return count($this->frames);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (array $frame): string => $frame['name'], $this->frames);
    }

    /**
     * @return list<int>
     */
    public function pendingPageNumbers(): array
    {
        $pages = [];
        foreach ($this->frames as $frame) {
            foreach ($frame['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    /**
     * @return list<array{name:string,transaction:bool,page_numbers:list<int>}>
     */
    public function toArray(): array
    {
        return array_map(static function (array $frame): array {
            $pageNumbers = array_keys($frame['pages']);
            sort($pageNumbers, SORT_NUMERIC);

            return [
                'name' => $frame['name'],
                'transaction' => $frame['transaction'],
                'page_numbers' => $pageNumbers,
            ];
        }, $this->frames);
    }

    private function findFrame(string $name): int
    {
        for ($index = count($this->frames) - 1; $index >= 0; $index--) {
            if ($this->frames[$index]['name'] === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException("SQLite savepoint does not exist: {$name}");
    }
}
