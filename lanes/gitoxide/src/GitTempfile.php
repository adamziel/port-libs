<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitTempfile
{
    public const CONTAINING_DIRECTORY_EXISTS = 'exists';
    public const CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF = 'create-all-race-proof';

    /** @var array<int, GitTempfileRegistryEntry|null> */
    private static array $registry = [];
    private static int $nextId = 0;

    public static function new(
        string $containingDirectory,
        string $directoryMode = self::CONTAINING_DIRECTORY_EXISTS,
        ?GitTempfileAutoRemove $cleanup = null,
    ): GitTempfileWritableHandle {
        $cleanup ??= GitTempfileAutoRemove::tempfile();
        self::resolveContainingDirectory($containingDirectory, $directoryMode);

        $path = tempnam($containingDirectory, 'tmp');
        if ($path === false) {
            throw new \RuntimeException("Unable to create tempfile in directory: {$containingDirectory}");
        }

        $handle = @fopen($path, 'r+b');
        if (!is_resource($handle)) {
            @unlink($path);
            throw new \RuntimeException("Unable to open tempfile: {$path}");
        }

        $id = self::register(new GitTempfileRegistryEntry($path, $handle, $cleanup, true));

        return new GitTempfileWritableHandle($id, $path);
    }

    public static function writableAt(
        string $path,
        string $directoryMode = self::CONTAINING_DIRECTORY_EXISTS,
        ?GitTempfileAutoRemove $cleanup = null,
    ): GitTempfileWritableHandle {
        $cleanup ??= GitTempfileAutoRemove::tempfile();
        $handle = self::createExactPath($path, $directoryMode);
        $id = self::register(new GitTempfileRegistryEntry($path, $handle, $cleanup, true));

        return new GitTempfileWritableHandle($id, $path);
    }

    public static function markAt(
        string $path,
        string $directoryMode = self::CONTAINING_DIRECTORY_EXISTS,
        ?GitTempfileAutoRemove $cleanup = null,
    ): GitTempfileClosedHandle {
        $cleanup ??= GitTempfileAutoRemove::tempfile();
        $handle = self::createExactPath($path, $directoryMode);
        if (!fclose($handle)) {
            @unlink($path);
            throw new \RuntimeException("Unable to close marker tempfile: {$path}");
        }

        $id = self::register(new GitTempfileRegistryEntry($path, null, $cleanup, false));

        return new GitTempfileClosedHandle($id, $path);
    }

    public static function autoRemoveTempfile(): GitTempfileAutoRemove
    {
        return GitTempfileAutoRemove::tempfile();
    }

    public static function autoRemoveTempfileAndEmptyParentDirectoriesUntil(string $boundaryDirectory): GitTempfileAutoRemove
    {
        return GitTempfileAutoRemove::tempfileAndEmptyParentDirectoriesUntil($boundaryDirectory);
    }

    public static function cleanupTempfiles(): void
    {
        foreach (self::$registry as $id => $entry) {
            if ($entry === null) {
                continue;
            }

            self::removeEntry($entry);
            self::$registry[$id] = null;
        }
    }

    public static function cleanupEmptyParents(string $directory, ?string $boundaryDirectory): void
    {
        if ($boundaryDirectory === null) {
            return;
        }

        $boundary = self::normalizePath(realpath($boundaryDirectory) ?: $boundaryDirectory);
        $current = self::normalizePath(realpath($directory) ?: $directory);
        while ($current !== $boundary && str_starts_with($current, $boundary . '/')) {
            $entries = @scandir($current);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) !== 0) {
                return;
            }

            @rmdir($current);
            $current = self::normalizePath(dirname($current));
        }
    }

    public static function entryFor(int $id): GitTempfileRegistryEntry
    {
        if (!array_key_exists($id, self::$registry) || self::$registry[$id] === null) {
            throw new \RuntimeException("The tempfile with id {$id} wasn't available anymore");
        }

        return self::$registry[$id];
    }

    public static function replaceEntry(int $id, GitTempfileRegistryEntry $entry): void
    {
        if (!array_key_exists($id, self::$registry) || self::$registry[$id] === null) {
            throw new \RuntimeException("The tempfile with id {$id} wasn't available anymore");
        }

        self::$registry[$id] = $entry;
    }

    public static function dropHandle(int $id): void
    {
        if (!array_key_exists($id, self::$registry)) {
            return;
        }

        $entry = self::$registry[$id];
        unset(self::$registry[$id]);

        if ($entry !== null) {
            self::removeEntry($entry);
        }
    }

    public static function takeEntry(int $id): ?GitTempfileRegistryEntry
    {
        if (!array_key_exists($id, self::$registry)) {
            return null;
        }

        $entry = self::$registry[$id];
        unset(self::$registry[$id]);

        return $entry;
    }

    public static function persistEntry(int $id, string $destination): ?GitTempfileRegistryEntry
    {
        if (!array_key_exists($id, self::$registry)) {
            return null;
        }

        $entry = self::$registry[$id];
        unset(self::$registry[$id]);

        if ($entry === null) {
            return null;
        }

        if (!@rename($entry->path, $destination)) {
            self::$registry[$id] = $entry;
            throw new GitTempfilePersistException(
                "Unable to persist tempfile {$entry->path} to {$destination}",
                $id,
                $entry->path,
                $destination,
            );
        }

        $entry->path = $destination;

        return $entry;
    }

    /**
     * @return resource
     */
    private static function createExactPath(string $path, string $directoryMode)
    {
        self::resolveContainingDirectory(dirname($path), $directoryMode);

        if (file_exists($path)) {
            throw new GitTempfileAlreadyExistsException("Tempfile already exists: {$path}");
        }

        $handle = @fopen($path, 'x+b');
        if (!is_resource($handle)) {
            if (file_exists($path)) {
                throw new GitTempfileAlreadyExistsException("Tempfile already exists: {$path}");
            }

            throw new \RuntimeException("Unable to create tempfile: {$path}");
        }

        return $handle;
    }

    private static function resolveContainingDirectory(string $directory, string $directoryMode): void
    {
        if (is_dir($directory)) {
            return;
        }

        if ($directoryMode !== self::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF) {
            throw new \RuntimeException("Tempfile containing directory does not exist: {$directory}");
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create tempfile containing directory: {$directory}");
        }
    }

    private static function register(GitTempfileRegistryEntry $entry): int
    {
        $id = self::$nextId++;
        self::$registry[$id] = $entry;

        return $id;
    }

    private static function removeEntry(GitTempfileRegistryEntry $entry): void
    {
        if (is_resource($entry->handle)) {
            @fflush($entry->handle);
            @fclose($entry->handle);
            $entry->handle = null;
        }

        if (is_file($entry->path) || is_link($entry->path)) {
            @unlink($entry->path);
        }

        $entry->cleanup->cleanupAfterRemoving(dirname($entry->path));
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

final class GitTempfileAutoRemove
{
    private const MODE_TEMPFILE = 'tempfile';
    private const MODE_TEMPFILE_AND_EMPTY_PARENTS = 'tempfile-and-empty-parents';

    private function __construct(
        private readonly string $mode,
        private readonly ?string $boundaryDirectory,
    ) {
    }

    public static function tempfile(): self
    {
        return new self(self::MODE_TEMPFILE, null);
    }

    public static function tempfileAndEmptyParentDirectoriesUntil(string $boundaryDirectory): self
    {
        return new self(self::MODE_TEMPFILE_AND_EMPTY_PARENTS, $boundaryDirectory);
    }

    public function cleanupAfterRemoving(string $parentDirectory): void
    {
        if ($this->mode !== self::MODE_TEMPFILE_AND_EMPTY_PARENTS) {
            return;
        }

        GitTempfile::cleanupEmptyParents($parentDirectory, $this->boundaryDirectory);
    }
}

final class GitTempfileRegistryEntry
{
    /**
     * @param resource|null $handle
     */
    public function __construct(
        public string $path,
        public $handle,
        public readonly GitTempfileAutoRemove $cleanup,
        public bool $writable,
    ) {
    }
}

abstract class GitTempfileHandle
{
    private bool $ownsRegistration = true;

    public function __construct(
        protected readonly int $id,
        protected readonly string $path,
    ) {
    }

    public function __destruct()
    {
        if ($this->ownsRegistration) {
            GitTempfile::dropHandle($this->id);
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    protected function id(): int
    {
        return $this->id;
    }

    protected function detach(): void
    {
        $this->ownsRegistration = false;
    }
}

final class GitTempfileWritableHandle extends GitTempfileHandle
{
    public function write(string $bytes): void
    {
        $entry = GitTempfile::entryFor($this->id());
        if (!$entry->writable || !is_resource($entry->handle)) {
            throw new \RuntimeException("Tempfile is not writable: {$this->path()}");
        }

        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($entry->handle, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException("Unable to write tempfile: {$this->path()}");
            }
            $offset += $written;
        }
    }

    public function withMut(callable $callback): mixed
    {
        $entry = GitTempfile::entryFor($this->id());
        if (!$entry->writable || !is_resource($entry->handle)) {
            throw new \RuntimeException("Tempfile is not writable: {$this->path()}");
        }

        return $callback($entry->handle, $entry->path);
    }

    public function close(): GitTempfileClosedHandle
    {
        $entry = GitTempfile::entryFor($this->id());
        if (!$entry->writable || !is_resource($entry->handle)) {
            throw new \RuntimeException("Tempfile is not writable: {$this->path()}");
        }

        if (!fflush($entry->handle)) {
            throw new \RuntimeException("Unable to flush tempfile: {$entry->path}");
        }
        if (!fclose($entry->handle)) {
            throw new \RuntimeException("Unable to close tempfile: {$entry->path}");
        }

        $entry->handle = null;
        $entry->writable = false;
        GitTempfile::replaceEntry($this->id(), $entry);
        $this->detach();

        return new GitTempfileClosedHandle($this->id(), $entry->path);
    }

    public function take(): ?GitTempfileTakenFile
    {
        $entry = GitTempfile::takeEntry($this->id());
        $this->detach();

        if ($entry === null) {
            return null;
        }

        if (!$entry->writable || !is_resource($entry->handle)) {
            throw new \RuntimeException("Tempfile is not writable: {$this->path()}");
        }

        return new GitTempfileTakenFile($entry->path, $entry->handle);
    }

    public function persist(string $destination): ?GitTempfilePersistedFile
    {
        try {
            $entry = GitTempfile::persistEntry($this->id(), $destination);
        } catch (GitTempfilePersistException $exception) {
            $exception->setHandle($this);
            throw $exception;
        }

        $this->detach();

        if ($entry === null) {
            return null;
        }
        if (!is_resource($entry->handle)) {
            throw new \RuntimeException("Persisted tempfile no longer has a writable handle: {$destination}");
        }

        return new GitTempfilePersistedFile($destination, $entry->handle);
    }
}

final class GitTempfileClosedHandle extends GitTempfileHandle
{
    public function take(): ?GitTempfileTakenPath
    {
        $entry = GitTempfile::takeEntry($this->id());
        $this->detach();

        if ($entry === null) {
            return null;
        }

        if (is_resource($entry->handle)) {
            @fclose($entry->handle);
        }

        return new GitTempfileTakenPath($entry->path);
    }

    public function persist(string $destination): void
    {
        try {
            GitTempfile::persistEntry($this->id(), $destination);
        } catch (GitTempfilePersistException $exception) {
            $exception->setHandle($this);
            throw $exception;
        }

        $this->detach();
    }
}

final class GitTempfileTakenFile
{
    /**
     * @param resource|null $handle
     */
    public function __construct(
        private string $path,
        private $handle,
        private bool $active = true,
    ) {
    }

    public function __destruct()
    {
        $this->removeIfActive();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function write(string $bytes): void
    {
        if (!$this->active || !is_resource($this->handle)) {
            throw new \RuntimeException("Taken tempfile is not writable: {$this->path}");
        }

        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($this->handle, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException("Unable to write taken tempfile: {$this->path}");
            }
            $offset += $written;
        }
    }

    public function keep(): string
    {
        if (is_resource($this->handle)) {
            @fflush($this->handle);
            @fclose($this->handle);
            $this->handle = null;
        }
        $this->active = false;

        return $this->path;
    }

    public function persist(string $destination): GitTempfilePersistedFile
    {
        if (!$this->active || !is_resource($this->handle)) {
            throw new \RuntimeException("Taken tempfile is not writable: {$this->path}");
        }

        if (!fflush($this->handle)) {
            throw new \RuntimeException("Unable to flush taken tempfile: {$this->path}");
        }
        if (!@rename($this->path, $destination)) {
            throw new \RuntimeException("Unable to persist taken tempfile {$this->path} to {$destination}");
        }

        $this->path = $destination;
        $this->active = false;

        return new GitTempfilePersistedFile($destination, $this->handle);
    }

    private function removeIfActive(): void
    {
        if (!$this->active) {
            return;
        }

        if (is_resource($this->handle)) {
            @fclose($this->handle);
            $this->handle = null;
        }
        if (is_file($this->path) || is_link($this->path)) {
            @unlink($this->path);
        }
        $this->active = false;
    }
}

final class GitTempfileTakenPath
{
    public function __construct(
        private string $path,
        private bool $active = true,
    ) {
    }

    public function __destruct()
    {
        if ($this->active && (is_file($this->path) || is_link($this->path))) {
            @unlink($this->path);
        }
        $this->active = false;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function keep(): string
    {
        $this->active = false;

        return $this->path;
    }

    public function persist(string $destination): void
    {
        if (!$this->active) {
            throw new \RuntimeException("Taken tempfile path is no longer active: {$this->path}");
        }

        if (!@rename($this->path, $destination)) {
            throw new \RuntimeException("Unable to persist taken tempfile {$this->path} to {$destination}");
        }

        $this->path = $destination;
        $this->active = false;
    }
}

final class GitTempfilePersistedFile
{
    /**
     * @param resource|null $handle
     */
    public function __construct(
        private readonly string $path,
        private $handle,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function write(string $bytes): void
    {
        if (!is_resource($this->handle)) {
            throw new \RuntimeException("Persisted tempfile is no longer writable: {$this->path}");
        }

        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($this->handle, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException("Unable to write persisted tempfile: {$this->path}");
            }
            $offset += $written;
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

final class GitTempfileAlreadyExistsException extends \RuntimeException
{
}

final class GitTempfilePersistException extends \RuntimeException
{
    private ?GitTempfileHandle $handle = null;

    public function __construct(
        string $message,
        public readonly int $id,
        public readonly string $source,
        public readonly string $destination,
    ) {
        parent::__construct($message);
    }

    public function handle(): GitTempfileHandle
    {
        if ($this->handle === null) {
            throw new \RuntimeException('Persist exception is not attached to a recoverable tempfile handle');
        }

        return $this->handle;
    }

    public function setHandle(GitTempfileHandle $handle): void
    {
        $this->handle = $handle;
    }
}
