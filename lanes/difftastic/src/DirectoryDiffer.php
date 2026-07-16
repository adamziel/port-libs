<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

use InvalidArgumentException;

final class DirectoryDiffer
{
    public function __construct(
        private readonly JsonDiffRenderer $jsonRenderer = new JsonDiffRenderer(),
        private readonly LanguageCatalog $languageCatalog = new LanguageCatalog(),
    ) {
    }

    /**
     * @param array{printUnchanged?: bool, sortPaths?: bool, languageOverrides?: list<string>, binaryOverrides?: list<string>, fileOptions?: array<string, mixed>} $options
     * @return list<array<string, mixed>>
     */
    public function diffDirectories(string $lhsDirectory, string $rhsDirectory, array $options = []): array
    {
        $this->assertDirectory($lhsDirectory);
        $this->assertDirectory($rhsDirectory);

        $paths = $this->relativePathsInEither($lhsDirectory, $rhsDirectory);
        if (($options['sortPaths'] ?? false) === true) {
            sort($paths, SORT_STRING);
        }

        $files = [];
        foreach ($paths as $relativePath) {
            $lhsPath = $this->joinPath($lhsDirectory, $relativePath);
            $rhsPath = $this->joinPath($rhsDirectory, $relativePath);
            $lhsBytes = $this->readFileOrEmpty($lhsPath);
            $rhsBytes = $this->readFileOrEmpty($rhsPath);
            $language = $this->languageCatalog->languageForPath(
                $relativePath,
                is_file($rhsPath) ? $rhsBytes : '',
                $options['languageOverrides'] ?? [],
            );
            $fileOptions = $options['fileOptions'] ?? [];
            if (!isset($fileOptions['language'])) {
                $fileOptions['language'] = $language['option'];
            }
            if (isset($options['binaryOverrides']) && !isset($fileOptions['binaryOverrides'])) {
                $fileOptions['binaryOverrides'] = $options['binaryOverrides'];
            }

            $file = $this->jsonRenderer->fileBytesDiff(
                $lhsBytes,
                $rhsBytes,
                $relativePath,
                $language['display'],
                $fileOptions,
            );

            if (($options['printUnchanged'] ?? false) === true || $file['status'] !== 'unchanged') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param array{printUnchanged?: bool, sortPaths?: bool, languageOverrides?: list<string>, binaryOverrides?: list<string>, fileOptions?: array<string, mixed>} $options
     */
    public function renderJsonDirectoryDiff(string $lhsDirectory, string $rhsDirectory, array $options = []): string
    {
        return json_encode(
            $this->diffDirectories($lhsDirectory, $rhsDirectory, $options),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Walk `lhsDirectory` and `rhsDirectory`, returning relative file paths
     * that occur in at least one side. This mirrors upstream's two-list merge
     * shape while the PHP walker keeps dotfiles and skips `.git` directories.
     *
     * @return list<string>
     */
    public function relativePathsInEither(string $lhsDirectory, string $rhsDirectory): array
    {
        $lhsPaths = $this->relativeFilePathsInDirectory($lhsDirectory);
        $rhsPaths = $this->relativeFilePathsInDirectory($rhsDirectory);
        $seen = [];
        $paths = [];
        $left = 0;
        $right = 0;

        while (isset($lhsPaths[$left]) && isset($rhsPaths[$right])) {
            $lhsPath = $lhsPaths[$left];
            $rhsPath = $rhsPaths[$right];

            if ($lhsPath === $rhsPath) {
                if (!isset($seen[$lhsPath])) {
                    $paths[] = $lhsPath;
                    $seen[$lhsPath] = true;
                }
                $left++;
                $right++;
                continue;
            }

            if (isset($seen[$lhsPath])) {
                $left++;
                continue;
            }
            if (isset($seen[$rhsPath])) {
                $right++;
                continue;
            }

            $paths[] = $lhsPath;
            $paths[] = $rhsPath;
            $seen[$lhsPath] = true;
            $seen[$rhsPath] = true;
            $left++;
            $right++;
        }

        for (; isset($lhsPaths[$left]); $left++) {
            if (!isset($seen[$lhsPaths[$left]])) {
                $paths[] = $lhsPaths[$left];
                $seen[$lhsPaths[$left]] = true;
            }
        }
        for (; isset($rhsPaths[$right]); $right++) {
            if (!isset($seen[$rhsPaths[$right]])) {
                $paths[] = $rhsPaths[$right];
                $seen[$rhsPaths[$right]] = true;
            }
        }

        return $paths;
    }

    private function assertDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Expected directory: ' . $directory);
        }
    }

    /**
     * @return list<string>
     */
    private function relativeFilePathsInDirectory(string $directory): array
    {
        $paths = [];
        $this->appendRelativeFilePaths(rtrim($directory, DIRECTORY_SEPARATOR), '', $paths);
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * @param list<string> $paths
     */
    private function appendRelativeFilePaths(string $root, string $relativeDirectory, array &$paths): void
    {
        $directory = $relativeDirectory === '' ? $root : $this->joinPath($root, $relativeDirectory);
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $relativePath = $relativeDirectory === '' ? $entry : $relativeDirectory . '/' . $entry;
            $absolutePath = $this->joinPath($root, $relativePath);
            if (is_dir($absolutePath)) {
                if ($entry === '.git') {
                    continue;
                }
                $this->appendRelativeFilePaths($root, $relativePath, $paths);
                continue;
            }

            if (is_file($absolutePath)) {
                $paths[] = $relativePath;
            }
        }
    }

    private function joinPath(string $base, string $relativePath): string
    {
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function readFileOrEmpty(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $bytes = file_get_contents($path);

        return $bytes === false ? '' : $bytes;
    }

}
