<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-head-cleanup-' . bin2hex(random_bytes(6));

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

$lines = static function (string $output): array {
    $trimmed = rtrim($output, "\r\n");
    if ($trimmed === '') {
        return [];
    }

    return explode("\n", $trimmed);
};

$storedNodeCount = static function (QuadbStore $repo): int {
    $snapshot = $repo->nodeStore()->exportSnapshot();

    return count($snapshot['leaves']) + count($snapshot['branches']);
};

try {
    $repo = QuadbStore::init($dir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $repo->fork('preview-a');
    $repo->put('wp_posts:1', 'Discarded preview edit');

    $repo->fork('preview-b', 'master');
    $repo->put('wp_posts:2', 'Approved draft');
    $approvedRoot = $repo->tree()->rootHash();

    $headsBeforeCleanup = $lines($repo->headText());
    $headRemoveCommand = QuadbStore::headRemoveCommandOutput($dir, 'preview-a');
    if ($headRemoveCommand['exitCode'] !== 0) {
        throw new RuntimeException($headRemoveCommand['stderr']);
    }

    $afterRemove = QuadbStore::open($dir);
    $storedNodesBeforeGc = $storedNodeCount($afterRemove);
    $gcCommand = QuadbStore::garbageCollectCommandOutput($dir);
    if ($gcCommand['exitCode'] !== 0) {
        throw new RuntimeException($gcCommand['stderr']);
    }

    $afterGc = QuadbStore::open($dir);
    $gcOutput = rtrim($gcCommand['stdout'], "\r\n");
    $storedNodesAfterGc = $storedNodeCount($afterGc);
    $headsAfterCleanup = $lines($afterGc->headText());

    echo json_encode([
        'scenario' => 'remove and garbage collect a discarded quadb WordPress preview head with upstream-shaped command output',
        'currentStatus' => rtrim($afterGc->statusText(), "\r\n"),
        'approvedPreviewRoot' => $approvedRoot,
        'approvedPreviewRootAfterGc' => $afterGc->tree()->rootHash(),
        'headsBeforeCleanup' => $headsBeforeCleanup,
        'headsAfterCleanup' => $headsAfterCleanup,
        'headRemoveCommand' => $headRemoveCommand,
        'gcOutput' => $gcOutput,
        'storedNodesBeforeGc' => $storedNodesBeforeGc,
        'storedNodesAfterGc' => $storedNodesAfterGc,
        'discardedNodesCollected' => $storedNodesAfterGc < $storedNodesBeforeGc,
        'discardedHeadRemoved' => !str_contains($afterGc->headText(), 'preview-a :'),
        'approvedPreviewStillCurrent' => $afterGc->currentHeadName() === 'preview-b',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
}
