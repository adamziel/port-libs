<?php

declare(strict_types=1);

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$encodingSourceFiles = static function () use ($sourceRoot): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $name = $fileInfo->getBasename('.php');
        if (preg_match('/(?:Encoding|Utf16|Nocase|Glob|Like|Affinity|Rtrim)/', $name) === 1) {
            $files[] = $fileInfo->getPathname();
        }
    }

    sort($files);

    return $files;
};

$legacyEncodingDefaultSourceMatches = static function () use ($encodingSourceFiles, $libsqliteRoot): array {
    $matches = [];

    foreach ($encodingSourceFiles() as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match_all('/(?:main|temp)\.wp_options/', $contents, $fileMatches) > 0) {
            $relative = str_replace($libsqliteRoot . '/', '', $file);
            foreach ($fileMatches[0] as $match) {
                $matches[] = "{$relative}: {$match}";
            }
        }
    }

    return $matches;
};

return [
    'encoding source defaults use generic application setting sources' => static fn (TestRunner $t) => $t->same([], $legacyEncodingDefaultSourceMatches()),
];
