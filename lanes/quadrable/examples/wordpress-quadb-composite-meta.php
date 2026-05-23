<?php

declare(strict_types=1);

use PortLibs\Quadrable\Blake2s;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-composite-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-composite-target-' . bin2hex(random_bytes(6));

$cleanup = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
};

$metaSuffix = static fn (string $metaKey): string => bin2hex(substr(Blake2s::hash($metaKey), -23));

try {
    $thumbnail = $metaSuffix('_thumbnail_id');
    $editLock = $metaSuffix('_edit_lock');
    $template = $metaSuffix('_wp_page_template');
    $missing = $metaSuffix('_missing_meta');

    $source = QuadbStore::init($sourceDir);
    $source->importCompositeLines(
        "42|{$thumbnail}|wp_postmeta:42:_thumbnail_id=7\n"
        . "42|{$editLock}|wp_postmeta:42:_edit_lock=1716400000\n"
        . "42|{$template}|wp_postmeta:42:_wp_page_template=templates/full-width.html\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $proofBytes = $source->exportCompositeProofBytesFromKeyLines("42|{$thumbnail}\n42|{$missing}\n", '|');

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-postmeta-proof');
    $target->importProofBytes($proofBytes, $trustedRoot);

    $missingMetaProvenAbsent = false;
    try {
        $target->getCompositeKey(42, $missing);
    } catch (RuntimeException) {
        $missingMetaProvenAbsent = true;
    }

    echo json_encode([
        'scenario' => 'delegated WordPress postmeta proof for upstream composite integer-hash keys',
        'trustedRoot' => $trustedRoot,
        'exportedCompositeLines' => explode("\n", trim($source->exportCompositeLines('|'))),
        'delegatedProofBytes' => strlen($proofBytes),
        'authenticatedThumbnail' => $target->getCompositeKey(42, $thumbnail),
        'missingMetaProvenAbsent' => $missingMetaProvenAbsent,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
