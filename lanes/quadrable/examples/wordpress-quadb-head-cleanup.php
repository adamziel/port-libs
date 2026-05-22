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
    $repo->removeHead('preview-a');
    $headsAfterCleanup = $lines($repo->headText());

    echo json_encode([
        'scenario' => 'prune a discarded quadb WordPress preview head while preserving the approved preview root',
        'currentStatus' => rtrim($repo->statusText(), "\r\n"),
        'approvedPreviewRoot' => $approvedRoot,
        'headsBeforeCleanup' => $headsBeforeCleanup,
        'headsAfterCleanup' => $headsAfterCleanup,
        'discardedHeadRemoved' => !str_contains($repo->headText(), 'preview-a :'),
        'approvedPreviewStillCurrent' => $repo->currentHeadName() === 'preview-b',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
}
