<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitLock
{
    private const DOT_LOCK_SUFFIX = '.lock';

    public static function acquireToUpdateResource(
        string $resourcePath,
        float $timeoutSeconds = 0.0,
        ?string $boundaryDirectory = null,
    ): GitLockFile {
        [$lockPath, $handle] = self::acquireHandle($resourcePath, $timeoutSeconds, $boundaryDirectory);

        return new GitLockFile($resourcePath, $lockPath, $handle, $boundaryDirectory, true);
    }

    public static function acquireToHoldResource(
        string $resourcePath,
        float $timeoutSeconds = 0.0,
        ?string $boundaryDirectory = null,
    ): GitLockMarker {
        [$lockPath, $handle] = self::acquireHandle($resourcePath, $timeoutSeconds, $boundaryDirectory);
        if (!fclose($handle)) {
            @unlink($lockPath);
            throw new \RuntimeException("Unable to close marker lock handle: {$lockPath}");
        }

        return new GitLockMarker($resourcePath, $lockPath, $boundaryDirectory, false, true);
    }

    public static function lockPathFor(string $resourcePath): string
    {
        return $resourcePath . self::DOT_LOCK_SUFFIX;
    }

    public static function cleanupEmptyParents(string $directory, ?string $boundaryDirectory): void
    {
        if ($boundaryDirectory === null) {
            return;
        }

        $boundary = self::normalizePath($boundaryDirectory);
        $current = self::normalizePath($directory);
        while ($current !== $boundary && str_starts_with($current, $boundary . '/')) {
            $entries = @scandir($current);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) !== 0) {
                return;
            }
            @rmdir($current);
            $current = self::normalizePath(dirname($current));
        }
    }

    /**
     * @return array{0: string, 1: resource}
     */
    private static function acquireHandle(
        string $resourcePath,
        float $timeoutSeconds,
        ?string $boundaryDirectory,
    ): array {
        $lockPath = self::lockPathFor($resourcePath);
        self::prepareContainingDirectory(dirname($lockPath), $boundaryDirectory);

        $attempts = 0;
        $startedAt = microtime(true);
        $timeoutSeconds = max(0.0, $timeoutSeconds);

        while (true) {
            $attempts++;
            $handle = @fopen($lockPath, 'x+b');
            if (is_resource($handle)) {
                return [$lockPath, $handle];
            }

            if (!file_exists($lockPath)) {
                throw new \RuntimeException("Unable to create lock file: {$lockPath}");
            }

            if ($timeoutSeconds === 0.0 || microtime(true) - $startedAt >= $timeoutSeconds) {
                throw new GitLockAcquireException(
                    $resourcePath,
                    $lockPath,
                    $timeoutSeconds,
                    $attempts,
                );
            }

            $remaining = $timeoutSeconds - (microtime(true) - $startedAt);
            usleep((int) max(1000, min(5000, $remaining * 1_000_000)));
        }
    }

    private static function prepareContainingDirectory(string $directory, ?string $boundaryDirectory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if ($boundaryDirectory === null) {
            throw new \RuntimeException("Lock containing directory does not exist: {$directory}");
        }

        $normalizedDirectory = self::normalizePath($directory);
        $normalizedBoundary = self::normalizePath($boundaryDirectory);
        if ($normalizedDirectory !== $normalizedBoundary && !str_starts_with($normalizedDirectory, $normalizedBoundary . '/')) {
            throw new \RuntimeException("Lock containing directory is outside boundary: {$directory}");
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create lock containing directory: {$directory}");
        }
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '') {
            return getcwd() ?: '.';
        }

        $absolute = str_starts_with($path, '/') ? $path : (getcwd() ?: '.') . '/' . $path;
        $segments = [];
        foreach (explode('/', $absolute) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}

final class GitLockAcquireException extends \RuntimeException
{
    public function __construct(
        public readonly string $resourcePath,
        public readonly string $lockPath,
        public readonly float $timeoutSeconds,
        public readonly int $attempts,
    ) {
        $mode = $timeoutSeconds === 0.0 ? 'immediately' : sprintf('after %.02fs', $timeoutSeconds);
        parent::__construct(
            "The lock for resource '{$resourcePath}' could not be obtained {$mode} after {$attempts} attempt(s). "
            . "The lockfile at '{$lockPath}' might need manual deletion.",
        );
    }
}

final class GitLockCommitException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly GitLockFile|GitLockMarker $instance,
    ) {
        parent::__construct($message);
    }

    public function instance(): GitLockFile|GitLockMarker
    {
        return $this->instance;
    }
}

final class GitLockFile
{
    /**
     * @param resource|null $handle
     */
    public function __construct(
        private readonly string $resourcePath,
        private readonly string $lockPath,
        private $handle,
        private readonly ?string $boundaryDirectory,
        private bool $active,
    ) {
    }

    public function __destruct()
    {
        $this->rollback();
    }

    public function lockPath(): string
    {
        return $this->lockPath;
    }

    public function resourcePath(): string
    {
        return $this->resourcePath;
    }

    public function write(string $bytes): void
    {
        $this->ensureActive();
        if (fwrite($this->handle, $bytes) !== strlen($bytes)) {
            throw new \RuntimeException("Unable to write lock file: {$this->lockPath}");
        }
    }

    public function close(): GitLockMarker
    {
        $this->ensureActive();
        if (!fflush($this->handle)) {
            throw new \RuntimeException("Unable to flush lock file: {$this->lockPath}");
        }
        if (!fclose($this->handle)) {
            throw new \RuntimeException("Unable to close lock file: {$this->lockPath}");
        }

        $this->handle = null;
        $this->active = false;

        return new GitLockMarker($this->resourcePath, $this->lockPath, $this->boundaryDirectory, true, true);
    }

    public function commit(): GitLockCommitResult
    {
        $this->ensureActive();
        if (!fflush($this->handle)) {
            throw new \RuntimeException("Unable to flush lock file: {$this->lockPath}");
        }

        if (!@rename($this->lockPath, $this->resourcePath)) {
            throw new GitLockCommitException($this->commitFailureMessage(), $this->transferToErrorInstance());
        }

        $handle = $this->handle;
        $this->handle = null;
        $this->active = false;

        return new GitLockCommitResult($this->resourcePath, $handle);
    }

    private function ensureActive(): void
    {
        if (!$this->active || !is_resource($this->handle)) {
            throw new \RuntimeException("Lock file is no longer active: {$this->lockPath}");
        }
    }

    private function rollback(): void
    {
        if (!$this->active) {
            return;
        }

        if (is_resource($this->handle)) {
            @fclose($this->handle);
        }
        $this->handle = null;
        $this->active = false;
        if (is_file($this->lockPath)) {
            @unlink($this->lockPath);
            GitLock::cleanupEmptyParents(dirname($this->lockPath), $this->boundaryDirectory);
        }
    }

    private function transferToErrorInstance(): self
    {
        $instance = new self($this->resourcePath, $this->lockPath, $this->handle, $this->boundaryDirectory, true);
        $this->handle = null;
        $this->active = false;

        return $instance;
    }

    private function commitFailureMessage(): string
    {
        $reason = is_dir($this->resourcePath) ? 'resource path is a directory' : 'rename failed';

        return "Unable to commit lock {$this->lockPath} to {$this->resourcePath}: {$reason}";
    }
}

final class GitLockMarker
{
    public function __construct(
        private readonly string $resourcePath,
        private readonly string $lockPath,
        private readonly ?string $boundaryDirectory,
        private readonly bool $createdFromFile,
        private bool $active,
    ) {
    }

    public function __destruct()
    {
        $this->rollback();
    }

    public function lockPath(): string
    {
        return $this->lockPath;
    }

    public function resourcePath(): string
    {
        return $this->resourcePath;
    }

    public function commit(): string
    {
        $this->ensureActive();
        if (!$this->createdFromFile) {
            throw new GitLockCommitException(
                'refusing to commit marker that was never opened',
                $this->transferToErrorInstance(),
            );
        }

        if (!@rename($this->lockPath, $this->resourcePath)) {
            throw new GitLockCommitException($this->commitFailureMessage(), $this->transferToErrorInstance());
        }

        $this->active = false;

        return $this->resourcePath;
    }

    private function ensureActive(): void
    {
        if (!$this->active) {
            throw new \RuntimeException("Lock marker is no longer active: {$this->lockPath}");
        }
    }

    private function rollback(): void
    {
        if (!$this->active) {
            return;
        }

        $this->active = false;
        if (is_file($this->lockPath)) {
            @unlink($this->lockPath);
            GitLock::cleanupEmptyParents(dirname($this->lockPath), $this->boundaryDirectory);
        }
    }

    private function transferToErrorInstance(): self
    {
        $instance = new self(
            $this->resourcePath,
            $this->lockPath,
            $this->boundaryDirectory,
            $this->createdFromFile,
            true,
        );
        $this->active = false;

        return $instance;
    }

    private function commitFailureMessage(): string
    {
        $reason = is_dir($this->resourcePath) ? 'resource path is a directory' : 'rename failed';

        return "Unable to commit lock {$this->lockPath} to {$this->resourcePath}: {$reason}";
    }
}

final class GitLockCommitResult
{
    /**
     * @param resource|null $handle
     */
    public function __construct(
        private readonly string $resourcePath,
        private $handle,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function resourcePath(): string
    {
        return $this->resourcePath;
    }

    public function write(string $bytes): void
    {
        if (!is_resource($this->handle)) {
            throw new \RuntimeException("Committed lock file is no longer writable: {$this->resourcePath}");
        }
        if (fwrite($this->handle, $bytes) !== strlen($bytes)) {
            throw new \RuntimeException("Unable to write committed lock file: {$this->resourcePath}");
        }
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            @fflush($this->handle);
            @fclose($this->handle);
        }
        $this->handle = null;
    }
}
