<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourcePlugin = $root . '/tools/playground-converter-plugin/port-libs-playground-converter.php';
$targetDir = $root . '/pandoc-showcase/playground';
$targetZip = $targetDir . '/port-libs-playground-converter.zip';
$staging = $root . '/.port-libs/playground-converter-plugin';

if (!is_file($sourcePlugin)) {
    fwrite(STDERR, "Missing plugin source: {$sourcePlugin}\n");
    exit(1);
}

remove_tree($staging);
mkdir($staging . '/port-libs-playground-converter/lanes/pandoc/src', 0777, true);
mkdir($staging . '/port-libs-playground-converter/lanes/markerpdf/src', 0777, true);
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

copy($sourcePlugin, $staging . '/port-libs-playground-converter/port-libs-playground-converter.php');
copy_tree($root . '/lanes/pandoc/src', $staging . '/port-libs-playground-converter/lanes/pandoc/src');
copy_tree($root . '/lanes/markerpdf/src', $staging . '/port-libs-playground-converter/lanes/markerpdf/src');

if (is_file($targetZip)) {
    unlink($targetZip);
}

$zip = new ZipArchive();
if ($zip->open($targetZip, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Unable to create {$targetZip}\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($staging . '/port-libs-playground-converter', FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $local = substr($path, strlen($staging) + 1);
    $zip->addFile($path, str_replace(DIRECTORY_SEPARATOR, '/', $local));
}
$zip->close();

remove_tree($staging);
echo $targetZip . "\n";

function copy_tree(string $source, string $destination): void
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        $target = $destination . substr($file->getPathname(), strlen($source));
        if ($file->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0777, true);
            }
            continue;
        }
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        copy($file->getPathname(), $target);
    }
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($path);
}
