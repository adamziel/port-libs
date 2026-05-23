<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-checkout-fork-' . bin2hex(random_bytes(6));
$missingDir = $dir . '-missing';

$cleanup = static function (string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
};

try {
    mkdir($dir, 0755, true);

    $missingCheckout = QuadbStore::checkoutCommandOutput($missingDir, 'preview');
    $checkoutPreview = QuadbStore::checkoutCommandOutput($dir, 'preview');
    QuadbStore::putCommandOutput($dir, 'wp_options:siteurl', 'https://preview.example.test');
    $preview = QuadbStore::open($dir);
    $previewRoot = $preview->tree()->rootHash();

    $forkApproved = QuadbStore::forkCommandOutput($dir, 'approved-preview');
    $checkoutDetached = QuadbStore::checkoutCommandOutput($dir);
    $forkEmpty = QuadbStore::forkCommandOutput($dir, 'empty-import-review', 'missing-head');
    $after = QuadbStore::open($dir);

    echo json_encode([
        'scenario' => 'guard WordPress preview checkout and fork commands with upstream-shaped quadb output',
        'missingCheckout' => $missingCheckout,
        'checkoutPreview' => $checkoutPreview,
        'forkApproved' => $forkApproved,
        'checkoutDetached' => $checkoutDetached,
        'forkFromMissingHead' => $forkEmpty,
        'previewRoot' => $previewRoot,
        'currentStatus' => rtrim($after->statusText(), "\r\n"),
        'headLines' => array_values(array_filter(explode("\n", rtrim($after->headText(), "\r\n")))),
        'missingStoreCreated' => is_dir($missingDir),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
    $cleanup($missingDir);
}
