<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-proof-fork-sharing-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-proof-fork-sharing-target-' . bin2hex(random_bytes(6));

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

$uint64le = static function (string $bytes): int {
    $parts = unpack('Vlow/Vhigh', $bytes);
    if (!is_array($parts)) {
        throw new RuntimeException('unable to unpack uint64');
    }

    return $parts['low'] + ($parts['high'] * 4294967296);
};

$headTable = static function (array $bucket) use ($uint64le): array {
    $heads = [];
    foreach ($bucket as $head => $nodeIdBytes) {
        $heads[$head] = $uint64le($nodeIdBytes);
    }

    return $heads;
};

try {
    $source = QuadbStore::init($sourceDir);
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $proofHex = $source->exportProofHex([
        'wp_options:siteurl',
        'wp_options:home',
    ], Proof::ENCODING_FULL_KEYS);

    $source->put('wp_options:siteurl', 'https://preview.example.test');
    $updatedRoot = $source->tree()->rootHash();

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-base');
    $target->importProofHex($proofHex, $trustedRoot);
    $target->fork('delegated-preview');
    $target->put('wp_options:siteurl', 'https://preview.example.test');

    $target->checkout('delegated-base');
    $lmdb = $target->lmdbBucketSnapshot();
    $keyCounts = array_count_values(array_values($lmdb['quadrable_key']));

    echo json_encode([
        'scenario' => 'fork a delegated WordPress proof head and share unchanged imported storage',
        'trustedRoot' => $trustedRoot,
        'previewRoot' => $updatedRoot,
        'baseSiteUrl' => $target->get('wp_options:siteurl'),
        'headTableNodeIds' => $headTable($lmdb['quadrable_head']),
        'trackedKeyStorageCounts' => [
            'wp_options:home' => $keyCounts['wp_options:home'] ?? 0,
            'wp_options:siteurl' => $keyCounts['wp_options:siteurl'] ?? 0,
        ],
        'unchangedHomeStoredOnceAcrossForks' => ($keyCounts['wp_options:home'] ?? 0) === 1,
        'editedSiteUrlHasBaseAndPreviewLeaves' => ($keyCounts['wp_options:siteurl'] ?? 0) === 2,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
