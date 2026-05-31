<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PreparedReferenceTransaction
{
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_NOOP = 'noop';

    private bool $open = true;

    /**
     * @param list<array{action?:string,lockPath?:string,edit:ReferenceTransactionEdit,reflog?:array{physicalName:string,previousTarget:?ReferenceTarget,newTarget:ReferenceTarget,committer:?CommitSignature,message:string,forceCreate:bool,algorithm:string}|null,delete?:array{physicalName:string,deleteReference:bool,deleteReflog:bool}}> $locks
     */
    public function __construct(
        private readonly string $gitDirectory,
        private readonly array $locks,
    ) {
        foreach ($locks as $lock) {
            if (!$lock['edit'] instanceof ReferenceTransactionEdit) {
                throw new \InvalidArgumentException('Prepared reference operations must contain transaction edits');
            }
            $action = $lock['action'] ?? self::ACTION_UPDATE;
            if (!in_array($action, [self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_NOOP], true)) {
                throw new \InvalidArgumentException("Unknown prepared reference action: {$action}");
            }
            if ($action === self::ACTION_NOOP) {
                continue;
            }
            if (!is_string($lock['lockPath'] ?? null)) {
                throw new \InvalidArgumentException('Prepared reference locks must contain lock paths and transaction edits');
            }
            $reflog = $lock['reflog'] ?? null;
            if ($reflog === null) {
                if ($action === self::ACTION_UPDATE) {
                    continue;
                }
            } elseif (
                    !is_string($reflog['physicalName'] ?? null)
                    || !(($reflog['previousTarget'] ?? null) === null || $reflog['previousTarget'] instanceof ReferenceTarget)
                    || !$reflog['newTarget'] instanceof ReferenceTarget
                    || !(($reflog['committer'] ?? null) === null || $reflog['committer'] instanceof CommitSignature)
                    || !is_string($reflog['message'] ?? null)
                    || !is_bool($reflog['forceCreate'] ?? null)
                    || !is_string($reflog['algorithm'] ?? null)
                ) {
                    throw new \InvalidArgumentException('Prepared reference reflogs must contain validated reference targets and metadata');
            }

            if ($action === self::ACTION_DELETE) {
                $delete = $lock['delete'] ?? null;
                if (
                    !is_array($delete)
                    || !is_string($delete['physicalName'] ?? null)
                    || !is_bool($delete['deleteReference'] ?? null)
                    || !is_bool($delete['deleteReflog'] ?? null)
                ) {
                    throw new \InvalidArgumentException('Prepared reference deletes must contain validated reference names and deletion modes');
                }
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
            if (($this->locks[$index]['action'] ?? self::ACTION_UPDATE) === self::ACTION_NOOP) {
                continue;
            }

            $lockPath = $this->locks[$index]['lockPath'];
            if (is_file($lockPath) && !unlink($lockPath)) {
                throw new \RuntimeException("Unable to remove prepared reference lock: {$lockPath}");
            }

            $this->deleteEmptyParents(dirname($lockPath));
        }

        $this->open = false;

        return $this->edits();
    }

    /**
     * Publish prepared loose-reference lock files in order.
     *
     * Like upstream gix-ref file transactions, commit is intentionally
     * non-atomic across multiple loose reference files: if a later lock cannot
     * be committed, earlier references stay published and no rollback is
     * attempted.
     *
     * @return list<ReferenceTransactionEdit>
     */
    public function commit(): array
    {
        if (!$this->open) {
            return $this->edits();
        }

        $this->open = false;

        foreach ($this->locks as $lock) {
            $action = $lock['action'] ?? self::ACTION_UPDATE;
            if ($action === self::ACTION_NOOP) {
                continue;
            }
            if ($action === self::ACTION_DELETE) {
                $this->commitDelete($lock);
            } else {
                $this->commitUpdate($lock);
            }
        }

        return $this->edits();
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    private function targetPathForLock(string $lockPath): string
    {
        if (!str_ends_with($lockPath, '.lock')) {
            throw new \RuntimeException("Prepared reference lock path has no .lock suffix: {$lockPath}");
        }

        return substr($lockPath, 0, -5);
    }

    /**
     * @param array{lockPath:string,edit:ReferenceTransactionEdit,reflog?:array{physicalName:string,previousTarget:?ReferenceTarget,newTarget:ReferenceTarget,committer:?CommitSignature,message:string,forceCreate:bool,algorithm:string}|null} $lock
     */
    private function commitUpdate(array $lock): void
    {
        $lockPath = $lock['lockPath'];
        $targetPath = $this->targetPathForLock($lockPath);

        if (!is_file($lockPath)) {
            throw new \RuntimeException("Prepared reference lock is missing: {$lockPath}");
        }

        $this->appendPreparedReflog($lock['reflog'] ?? null);

        if (is_dir($targetPath) && !$this->removeEmptyDirectoryTree($targetPath)) {
            throw new \RuntimeException("Unable to replace directory blocker with prepared reference: {$lock['edit']->name}");
        }

        $directory = dirname($targetPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create reference directory: {$directory}");
        }

        if (!rename($lockPath, $targetPath)) {
            throw new \RuntimeException("Unable to commit prepared reference lock: {$lock['edit']->name}");
        }
    }

    /**
     * @param array{lockPath:string,edit:ReferenceTransactionEdit,delete:array{physicalName:string,deleteReference:bool,deleteReflog:bool}} $lock
     */
    private function commitDelete(array $lock): void
    {
        $lockPath = $lock['lockPath'];
        if (!is_file($lockPath)) {
            throw new \RuntimeException("Prepared reference lock is missing: {$lockPath}");
        }

        $delete = $lock['delete'];
        if ($delete['deleteReflog']) {
            $this->deletePreparedReflog($delete['physicalName']);
        }

        if ($delete['deleteReference']) {
            $targetPath = $this->targetPathForLock($lockPath);
            if (is_file($targetPath) && !unlink($targetPath)) {
                throw new \RuntimeException("Unable to delete prepared reference: {$lock['edit']->name}");
            }
            $this->deleteEmptyParents(dirname($targetPath));
        }

        if (is_file($lockPath) && !unlink($lockPath)) {
            throw new \RuntimeException("Unable to remove prepared reference delete lock: {$lockPath}");
        }
        $this->deleteEmptyParents(dirname($lockPath));
    }

    /**
     * @param array{physicalName:string,previousTarget:?ReferenceTarget,newTarget:ReferenceTarget,committer:?CommitSignature,message:string,forceCreate:bool,algorithm:string}|null $reflog
     */
    private function appendPreparedReflog(?array $reflog): void
    {
        if ($reflog === null || !$reflog['newTarget']->isObject()) {
            return;
        }

        $previous = $reflog['previousTarget'];
        if ($previous !== null && !$previous->isObject()) {
            $previous = null;
        }

        $new = $reflog['newTarget'];
        if ($previous !== null && $previous->value === $new->value) {
            return;
        }

        $physicalName = $reflog['physicalName'];
        $path = $this->reflogPath($physicalName);
        $shouldCreate = $reflog['forceCreate'] || $this->shouldAutoCreateReflog($physicalName);
        if (!is_file($path) && !$shouldCreate && !is_dir($path)) {
            return;
        }

        if ($reflog['committer'] === null) {
            throw new \InvalidArgumentException('Reflog updates need a committer signature');
        }
        if (str_contains($reflog['message'], "\n")) {
            throw new \InvalidArgumentException('Reflog message must not contain newline bytes');
        }

        ReferenceTarget::assertValidObjectId($new->value, $reflog['algorithm']);
        if ($previous !== null) {
            ReferenceTarget::assertValidObjectId($previous->value, $reflog['algorithm']);
        }

        if (is_dir($path)) {
            if (!$shouldCreate || !$this->removeEmptyDirectoryTree($path)) {
                throw new \RuntimeException("Unable to replace directory blocker with prepared reflog: {$physicalName}");
            }
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create prepared reflog directory: {$directory}");
        }

        $old = $previous?->value ?? str_repeat('0', ReferenceTarget::hashHexLength($reflog['algorithm']));
        $line = $old . ' ' . $new->value . ' ' . $reflog['committer']->trimmed()->storageBytes();
        $line .= $reflog['message'] === '' ? "\n" : "\t{$reflog['message']}\n";

        if (file_put_contents($path, $line, FILE_APPEND) === false) {
            throw new \RuntimeException("Unable to append prepared reflog: {$physicalName}");
        }
    }

    private function deletePreparedReflog(string $physicalName): void
    {
        $path = $this->reflogPath($physicalName);
        if (is_dir($path)) {
            throw new \RuntimeException("Unable to delete prepared reflog: {$physicalName}");
        }
        if (!is_file($path)) {
            return;
        }

        if (!unlink($path)) {
            throw new \RuntimeException("Unable to delete prepared reflog: {$physicalName}");
        }

        $this->deleteEmptyParents(dirname($path), rtrim($this->gitDirectory, '/\\') . '/logs');
    }

    private function reflogPath(string $physicalName): string
    {
        ReferenceName::assertValid($physicalName);

        return rtrim($this->gitDirectory, '/\\') . '/logs/' . $physicalName;
    }

    private function shouldAutoCreateReflog(string $physicalName): bool
    {
        $physicalName = $this->reflogAutoCreateName($physicalName);

        return $physicalName === 'HEAD'
            || str_starts_with($physicalName, 'refs/heads/')
            || str_starts_with($physicalName, 'refs/remotes/')
            || str_starts_with($physicalName, 'refs/notes/')
            || str_starts_with($physicalName, 'refs/worktree/');
    }

    private function reflogAutoCreateName(string $physicalName): string
    {
        $name = $physicalName;
        while (str_starts_with($name, 'refs/namespaces/')) {
            $rest = substr($name, strlen('refs/namespaces/'));
            $slash = strpos($rest, '/');
            if ($slash === false) {
                return $physicalName;
            }
            $name = substr($rest, $slash + 1);
        }

        return $name;
    }

    private function deleteEmptyParents(string $directory, ?string $boundary = null): void
    {
        $boundary = str_replace('\\', '/', rtrim($boundary ?? $this->gitDirectory, '/\\'));
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

    private function removeEmptyDirectoryTree(string $directory): bool
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException("Unable to inspect prepared reference directory blocker: {$directory}");
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                if (!$this->removeEmptyDirectoryTree($path)) {
                    return false;
                }
                continue;
            }

            return false;
        }

        return @rmdir($directory) || !is_dir($directory);
    }
}
