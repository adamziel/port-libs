<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitLock;
use PortLibs\Gitoxide\GitLockAcquireException;
use PortLibs\Gitoxide\GitLockCommitException;

$tempDir = static function (string $name): string {
    $dir = sys_get_temp_dir() . '/port-libs-git-lock-' . $name . '-' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create test directory: {$dir}");
    }

    return $dir;
};
$removeTree = null;
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }

    $entries = scandir($path);
    if ($entries === false) {
        return;
    }
    foreach (array_diff($entries, ['.', '..']) as $entry) {
        $removeTree($path . '/' . $entry);
    }
    @rmdir($path);
};
$isFile = static function (string $path): bool {
    clearstatcache(true, $path);

    return is_file($path);
};
$isDir = static function (string $path): bool {
    clearstatcache(true, $path);

    return is_dir($path);
};
$catch = static function (string $class, callable $callback): Throwable {
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $class) {
            return $throwable;
        }

        $actualClass = $throwable::class;
        throw new RuntimeException("Expected {$class}, got {$actualClass}: {$throwable->getMessage()}");
    }

    throw new RuntimeException("Expected {$class} was not thrown");
};
$assertLockModeIsNotPrivate = static function (TestRunner $t, string $path): void {
    if (DIRECTORY_SEPARATOR !== '/') {
        return;
    }

    $mode = fileperms($path);
    if ($mode === false) {
        throw new RuntimeException("Unable to inspect lock mode: {$path}");
    }
    $t->true(($mode & 0777) !== 0600, 'lock mode is more permissive than 0600 after umask');
};

return [
    'upstream file.rs close acquire close commit to existing file' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile): void {
        $dir = $tempDir('close-existing');
        try {
            $resource = $dir . '/resource-existing.ext';
            $resourceLock = $resource . '.lock';
            file_put_contents($resource, 'old state');

            $file = GitLock::acquireToUpdateResource($resource);
            $t->true($isFile($resourceLock));
            $file->write('hello world');
            $marker = $file->close();

            $t->same($resourceLock, $marker->lockPath());
            $t->same($resource, $marker->resourcePath());
            $t->same($resource, $marker->commit(), 'returned and initial resource path match');
            $t->same('hello world', (string) file_get_contents($resource), 'it created the resource and wrote the data');
            $t->same(false, $isFile($resourceLock));
        } finally {
            $removeTree($dir);
        }
    },
    'upstream file.rs commit failure returns registered marker' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $catch): void {
        $dir = $tempDir('commit-marker-directory');
        try {
            $resource = $dir . '/resource-existing.ext';
            mkdir($resource);
            $marker = GitLock::acquireToHoldResource($resource);
            $lockPath = $marker->lockPath();
            $t->true($isFile($lockPath), 'the lock is placed');

            $err = $catch(GitLockCommitException::class, static fn () => $marker->commit());
            $t->true($isFile($err->instance()->lockPath()), 'the lock is still present');

            unset($err);
            gc_collect_cycles();
            $t->same(false, $isFile($lockPath), 'the lock file is still owned by the lock error instance');
        } finally {
            $removeTree($dir);
        }
    },
    'upstream file.rs commit failure returns registered file' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $catch): void {
        $dir = $tempDir('commit-file-directory');
        try {
            $resource = $dir . '/resource-existing.ext';
            mkdir($resource);
            $file = GitLock::acquireToUpdateResource($resource);
            $lockPath = $file->lockPath();
            $t->true($isFile($lockPath), 'the lock is placed');

            $err = $catch(GitLockCommitException::class, static fn () => $file->commit());
            $t->true($isFile($err->instance()->lockPath()), 'the lock is still present');
            rmdir($resource);
            $committed = $err->instance()->commit();
            $t->same($resource, $committed->resourcePath());
            $t->same(false, $isFile($lockPath), "the lock was moved into place, now it's the resource");

            $committed->write('hello');
            $committed->close();
            $t->same('hello', (string) file_get_contents($resource), 'committing returned a writable file handle');
        } finally {
            $removeTree($dir);
        }
    },
    'upstream file.rs acquire lock create dir write commit' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $assertLockModeIsNotPrivate): void {
        $dir = $tempDir('create-dir');
        try {
            $resource = $dir . '/a/resource-nonexisting';
            $resourceLock = $resource . '.lock';
            $file = GitLock::acquireToUpdateResource($resource, 0.0, $dir);

            $t->same($resourceLock, $file->lockPath());
            $t->same($resource, $file->resourcePath());
            $t->true($isFile($resourceLock));
            $assertLockModeIsNotPrivate($t, $resourceLock);
            $file->write('hello world');
            $committed = $file->commit();
            $t->same($resource, $committed->resourcePath(), 'returned and computed resource path match');
            $committed->close();
            $t->same('hello world', (string) file_get_contents($resource), 'it created the resource and wrote the data');
            $t->same(false, $isFile($resourceLock));
        } finally {
            $removeTree($dir);
        }
    },
    'upstream file.rs acquire lock write drop' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile): void {
        $dir = $tempDir('write-drop');
        try {
            $resource = $dir . '/resource-nonexisting.ext';
            $file = GitLock::acquireToUpdateResource($resource);
            $lockPath = $file->lockPath();
            $file->write('probably we will be interrupted');

            unset($file);
            gc_collect_cycles();
            $t->same(false, $isFile($resource), "the file wasn't created");
            $t->same(false, $isFile($lockPath), 'the lock file was cleaned up');
        } finally {
            $removeTree($dir);
        }
    },
    'upstream file.rs acquire lock non existing dir fails' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $isDir, $catch): void {
        $dir = $tempDir('missing-dir');
        try {
            $resource = $dir . '/a/resource.ext';
            $err = $catch(RuntimeException::class, static fn () => GitLock::acquireToUpdateResource($resource));

            $t->contains('containing directory does not exist', $err->getMessage());
            $t->true($isDir($dir), "it won't meddle with the containing directory");
            $t->same(false, $isFile($resource), 'the resource is not created');
            $t->same(false, $isDir(dirname($resource)), "parent dir wasn't created either");
        } finally {
            $removeTree($dir);
        }
    },
    'upstream marker.rs acquire immediate produces descriptive error' => static function (TestRunner $t) use ($tempDir, $removeTree, $catch): void {
        $dir = $tempDir('marker-immediate');
        try {
            $resource = $dir . '/the-resource';
            $guard = GitLock::acquireToHoldResource($resource);

            $t->true(str_ends_with($guard->lockPath(), 'the-resource.lock'));
            $t->true(str_ends_with($guard->resourcePath(), 'the-resource'));
            $err = $catch(GitLockAcquireException::class, static fn () => GitLock::acquireToHoldResource($resource));
            $t->contains("the-resource' could not be obtained immediately", $err->getMessage());
            $t->contains('the-resource.lock', $err->getMessage(), 'it mentions the lockfile itself');
        } finally {
            unset($guard, $err);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream marker.rs acquire after duration waits before failing' => static function (TestRunner $t) use ($tempDir, $removeTree, $catch): void {
        $dir = $tempDir('marker-after-duration');
        try {
            $resource = $dir . '/the-resource';
            $guard = GitLock::acquireToHoldResource($resource);
            $timeout = 0.02;
            $start = microtime(true);
            $err = $catch(GitLockAcquireException::class, static fn () => GitLock::acquireToHoldResource($resource, $timeout));
            $elapsed = microtime(true) - $start;

            $t->true($elapsed >= $timeout, 'it should never wait less than the given wait time');
            $t->contains('could not be obtained after 0.02s', $err->getMessage(), 'it lets us know that we were waiting for some time');
            $t->contains('the-resource.lock', $err->getMessage(), 'it mentions the lockfile itself');
        } finally {
            unset($guard, $err);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream marker.rs commit failure returns registered marker' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $catch): void {
        $dir = $tempDir('marker-from-file-directory');
        try {
            $resource = $dir . '/the-resource';
            $file = GitLock::acquireToUpdateResource($resource);
            $marker = $file->close();
            $resourceLockPath = $marker->lockPath();

            mkdir($resource);
            $err = $catch(GitLockCommitException::class, static fn () => $marker->commit());
            $t->true($isFile($resourceLockPath), "the underlying lock wasn't consumed after all");

            unset($err);
            gc_collect_cycles();
            $t->same(false, $isFile($resourceLockPath), 'and is linked to the err which makes the lock recoverable');
        } finally {
            $removeTree($dir);
        }
    },
    'upstream marker.rs commit ordinary marker that was never writable fails' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $catch, $assertLockModeIsNotPrivate): void {
        $dir = $tempDir('marker-never-writable');
        try {
            $resource = $dir . '/the-resource';
            $marker = GitLock::acquireToHoldResource($resource);
            $lockPath = $marker->lockPath();
            $assertLockModeIsNotPrivate($t, $lockPath);

            $err = $catch(GitLockCommitException::class, static fn () => $marker->commit());
            $t->same('refusing to commit marker that was never opened', $err->getMessage());
            $t->true($isFile($lockPath), 'the lock remains owned by the commit error instance');

            unset($err);
            gc_collect_cycles();
            $t->same(false, $isFile($lockPath), 'the lock is cleaned up when the error instance is dropped');
        } finally {
            $removeTree($dir);
        }
    },
];
