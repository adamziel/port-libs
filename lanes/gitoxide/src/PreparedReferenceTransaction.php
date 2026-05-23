<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PreparedReferenceTransaction
{
    private bool $open = true;

    /**
     * @param list<array{lockPath:string,edit:ReferenceTransactionEdit}> $locks
     */
    public function __construct(
        private readonly string $gitDirectory,
        private readonly array $locks,
    ) {
        foreach ($locks as $lock) {
            if (!is_string($lock['lockPath'] ?? null) || !$lock['edit'] instanceof ReferenceTransactionEdit) {
                throw new \InvalidArgumentException('Prepared reference locks must contain lock paths and transaction edits');
            }
        }
    }

    public function __destruct()
    {
        if (!$this->open) {
            return;
        }

        try {
            $this->rollback();
        } catch (\Throwable) {
            // Destructors must not turn rollback cleanup failures into shutdown-time fatals.
        }
    }

    /**
     * @return list<ReferenceTransactionEdit>
     */
    public function edits(): array
    {
        return array_map(static fn (array $lock): ReferenceTransactionEdit => $lock['edit'], $this->locks);
    }

    /**
     * @return list<ReferenceTransactionEdit>
     */
    public function rollback(): array
    {
        if (!$this->open) {
            return $this->edits();
        }

        for ($index = count($this->locks) - 1; $index >= 0; $index--) {
            $lockPath = $this->locks[$index]['lockPath'];
            if (is_file($lockPath) && !unlink($lockPath)) {
                throw new \RuntimeException("Unable to remove prepared reference lock: {$lockPath}");
            }

            $this->deleteEmptyParents(dirname($lockPath));
        }

        $this->open = false;

        return $this->edits();
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    private function deleteEmptyParents(string $directory): void
    {
        $boundary = str_replace('\\', '/', rtrim($this->gitDirectory, '/\\'));
        $current = str_replace('\\', '/', $directory);

        while ($current !== $boundary && str_starts_with($current, $boundary . '/')) {
            $entries = @scandir($current);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) !== 0) {
                break;
            }
            @rmdir($current);
            $current = str_replace('\\', '/', dirname($current));
        }
    }
}
