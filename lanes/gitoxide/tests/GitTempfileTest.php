<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitTempfile;
use PortLibs\Gitoxide\GitTempfileAlreadyExistsException;
use PortLibs\Gitoxide\GitTempfilePersistException;

$tempDir = static function (string $name): string {
    $dir = sys_get_temp_dir() . '/port-libs-git-tempfile-' . $name . '-' . bin2hex(random_bytes(4));
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
$fileCountIn = static function (string $path): int {
    $entries = scandir($path);
    if ($entries === false) {
        throw new RuntimeException("Unable to read directory: {$path}");
    }

    return count(array_diff($entries, ['.', '..']));
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
$cleanupParents = static fn (string $boundary): PortLibs\Gitoxide\GitTempfileAutoRemove => GitTempfile::autoRemoveTempfileAndEmptyParentDirectoriesUntil($boundary);

return [
    'upstream registry.rs cleanup_tempfiles' => static function (TestRunner $t) use ($tempDir, $removeTree, $fileCountIn, $catch): void {
        $dir = $tempDir('registry-cleanup');
        try {
            $tempfile = GitTempfile::new($dir, GitTempfile::CONTAINING_DIRECTORY_EXISTS, GitTempfile::autoRemoveTempfile());
            $t->same(1, $fileCountIn($dir), 'only one tempfile exists no matter the iteration');

            GitTempfile::cleanupTempfiles();
            $t->same(0, $fileCountIn($dir), "registry cleanup removes the tempfile without terminating the process");

            $err = $catch(RuntimeException::class, static fn () => $tempfile->write('bogus'));
            $t->contains("wasn't available anymore", $err->getMessage(), "cannot write into a tempfile that doesn't exist in registry");
        } finally {
            unset($tempfile, $err);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle mark_path it_persists_markers_along_with_newly_created_directories' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $cleanupParents, $catch): void {
        $dir = $tempDir('mark-persist-created-dirs');
        try {
            $target = $dir . '/a/b/file.tmp';
            $newFilename = dirname($target) . '/file.ext';
            $handle = GitTempfile::markAt(
                $target,
                GitTempfile::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF,
                $cleanupParents($dir),
            );

            mkdir($newFilename);
            $err = $catch(GitTempfilePersistException::class, static fn () => $handle->persist($newFilename));
            $recovered = $err->handle();
            rmdir($newFilename);

            $taken = $recovered->take();
            if ($taken === null) {
                throw new RuntimeException('expected recovered marker to still be registered');
            }
            $taken->persist($newFilename);

            $t->same(false, $isFile($target), 'tempfile was renamed');
            $t->true($isFile($newFilename), 'new file was placed and parent directories still exist');
        } finally {
            unset($handle, $err, $recovered, $taken);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle mark_path it_can_create_the_containing_directory_and_remove_it_on_drop' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $isDir, $cleanupParents): void {
        $dir = $tempDir('mark-create-remove-dir');
        try {
            $firstDir = 'dir';
            $filename = $dir . '/' . $firstDir . '/subdir/file.tmp';
            $tempfile = GitTempfile::markAt(
                $filename,
                GitTempfile::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF,
                $cleanupParents($dir),
            );
            $t->true($isFile($filename), 'specified file should exist precisely');

            unset($tempfile);
            gc_collect_cycles();
            $t->same(false, $isFile($filename), 'after drop named files are deleted');
            $t->same(false, $isDir($dir . '/' . $firstDir), 'previously created and now empty directories are deleted too');
        } finally {
            unset($tempfile);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle at_path reduce_resource_usage_by_converting_files_to_markers_and_persist_them' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $cleanupParents): void {
        $dir = $tempDir('writable-close-persist');
        try {
            $target = $dir . '/a/file.tmp';
            $newFilename = dirname($target) . '/file.ext';
            $file = GitTempfile::writableAt(
                $target,
                GitTempfile::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF,
                $cleanupParents($dir),
            );
            $file->write('hello world');

            $mark = $file->close();
            $taken = $mark->take();
            if ($taken === null) {
                throw new RuntimeException('expected closed marker to still be registered');
            }
            $taken->persist($newFilename);

            $t->same(false, $isFile($target), 'tempfile was renamed');
            $t->true($isFile($newFilename), 'new file was placed and parent directories still exist');
            $t->same('hello world', (string) file_get_contents($newFilename), 'written content is persisted too');
        } finally {
            unset($file, $mark, $taken);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle at_path it_persists_tempfiles_along_with_newly_created_directories' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $cleanupParents, $catch): void {
        $dir = $tempDir('writable-persist-created-dirs');
        try {
            $target = $dir . '/a/b/file.tmp';
            $newFilename = dirname($target) . '/file.ext';
            $t->same(false, $isFile($newFilename), "the filename for persistence doesn't exist yet");

            $handle = GitTempfile::writableAt(
                $target,
                GitTempfile::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF,
                $cleanupParents($dir),
            );
            mkdir($newFilename);
            $err = $catch(GitTempfilePersistException::class, static fn () => $handle->persist($newFilename));
            $recovered = $err->handle();
            rmdir($newFilename);

            $taken = $recovered->take();
            if ($taken === null) {
                throw new RuntimeException('expected recovered writable tempfile to still be registered');
            }
            $taken->write('hello world');
            $persisted = $taken->persist($newFilename);
            $persisted->close();

            $t->same(false, $isFile($target), 'tempfile was renamed');
            $t->true($isFile($newFilename), 'new file was placed and parent directories still exist');
            $t->same('hello world', (string) file_get_contents($newFilename), 'written content is persisted too');
        } finally {
            unset($handle, $err, $recovered, $taken, $persisted);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle at_path it_can_create_the_containing_directory_and_remove_it_on_drop' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $isDir, $cleanupParents): void {
        $dir = $tempDir('writable-create-remove-dir');
        try {
            $firstDir = 'dir';
            $filename = $dir . '/' . $firstDir . '/subdir/file.tmp';
            $tempfile = GitTempfile::writableAt(
                $filename,
                GitTempfile::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF,
                $cleanupParents($dir),
            );
            $t->true($isFile($filename), 'specified file should exist precisely');

            unset($tempfile);
            gc_collect_cycles();
            $t->same(false, $isFile($filename), 'after drop named files are deleted');
            $t->same(false, $isDir($dir . '/' . $firstDir), 'previously created and now empty directories are deleted too');
            $t->true($isDir($dir), "it won't touch the containing directory");
        } finally {
            unset($tempfile);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle at_path it_names_files_correctly_and_similarly_named_tempfiles_cannot_be_created' => static function (TestRunner $t) use ($tempDir, $removeTree, $isFile, $isDir, $catch): void {
        $dir = $tempDir('exact-path-exclusive');
        try {
            $filename = $dir . '/something-specific.ext';
            $tempfile = GitTempfile::writableAt($filename, GitTempfile::CONTAINING_DIRECTORY_EXISTS, GitTempfile::autoRemoveTempfile());

            $catch(GitTempfileAlreadyExistsException::class, static fn () => GitTempfile::writableAt(
                $filename,
                GitTempfile::CONTAINING_DIRECTORY_EXISTS,
                GitTempfile::autoRemoveTempfile(),
            ));
            $t->true($isFile($filename), 'specified file should exist precisely');

            unset($tempfile);
            gc_collect_cycles();
            $t->same(false, $isFile($filename), 'after drop named files are deleted as well');
            $t->true($isDir($dir), "it won't touch the containing directory");
        } finally {
            unset($tempfile);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle new it_can_be_kept' => static function (TestRunner $t) use ($tempDir, $removeTree, $fileCountIn): void {
        $dir = $tempDir('new-keep');
        try {
            $taken = GitTempfile::new($dir, GitTempfile::CONTAINING_DIRECTORY_EXISTS, GitTempfile::autoRemoveTempfile())->take();
            if ($taken === null) {
                throw new RuntimeException('expected new tempfile to still be registered');
            }
            $taken->keep();
            unset($taken);
            gc_collect_cycles();

            $t->same(1, $fileCountIn($dir), 'a temp file was persisted');
        } finally {
            unset($taken);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle new it_is_removed_if_it_goes_out_of_scope' => static function (TestRunner $t) use ($tempDir, $removeTree, $fileCountIn): void {
        $dir = $tempDir('new-drop');
        try {
            $keep = GitTempfile::new($dir, GitTempfile::CONTAINING_DIRECTORY_EXISTS, GitTempfile::autoRemoveTempfile());
            $t->same(1, $fileCountIn($dir), 'a temp file was created');

            unset($keep);
            gc_collect_cycles();
            $t->same(0, $fileCountIn($dir), 'lock was automatically removed');
        } finally {
            unset($keep);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
    'upstream tempfile handle new it_can_create_the_containing_directory_and_remove_it_when_dropped' => static function (TestRunner $t) use ($tempDir, $removeTree, $fileCountIn, $isFile, $isDir, $cleanupParents, $catch): void {
        $dir = $tempDir('new-create-remove-dir');
        try {
            $containingDir = $dir . '/dir';
            $t->same(false, file_exists($containingDir));
            $writable = GitTempfile::new(
                $containingDir,
                GitTempfile::CONTAINING_DIRECTORY_CREATE_ALL_RACE_PROOF,
                $cleanupParents($dir),
            );
            $t->same(1, $fileCountIn($dir), 'a temp file was created, as well as the directory');
            $writable->write('hello world');

            $err = $catch(RuntimeException::class, static fn () => $writable->withMut(
                static fn () => throw new RuntimeException('propagated tempfile callback error'),
            ));
            $t->same('propagated tempfile callback error', $err->getMessage(), 'errors are propagated');
            unset($err);
            gc_collect_cycles();
            $writable->withMut(static function ($handle, string $path) use ($t, $isFile): void {
                $t->true(is_resource($handle));
                $t->true($isFile($path), 'after seeing an error before the file still exists');
            });

            unset($writable);
            gc_collect_cycles();
            $t->same(false, $isDir($containingDir), 'the now empty directory was deleted as well');
            $t->true($isDir($dir), "it won't touch the containing directory");
        } finally {
            unset($writable, $err);
            gc_collect_cycles();
            $removeTree($dir);
        }
    },
];
