<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$missingDir = sys_get_temp_dir() . '/quadrable-wp-inspect-missing-' . bin2hex(random_bytes(6));
$dir = sys_get_temp_dir() . '/quadrable-wp-inspect-' . bin2hex(random_bytes(6));

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

    return $trimmed === '' ? [] : explode("\n", $trimmed);
};

try {
    $missingStatus = QuadbStore::statusCommandOutput($missingDir);

    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('unable to create WordPress inspection store directory');
    }

    $emptyStatus = QuadbStore::statusCommandOutput($dir);
    QuadbStore::putCommandOutput($dir, 'wp_options:siteurl', 'https://example.test');
    QuadbStore::putCommandOutput($dir, 'wp_posts:1', 'Published post');
    QuadbStore::forkCommandOutput($dir, 'preview-discard');
    QuadbStore::putCommandOutput($dir, 'wp_posts:1', 'Discarded preview edit');
    QuadbStore::forkCommandOutput($dir, 'preview-approved', 'master');
    QuadbStore::putCommandOutput($dir, 'wp_posts:2', 'Approved page');

    $statusBeforeCleanup = QuadbStore::statusCommandOutput($dir);
    $headsBeforeCleanup = QuadbStore::headCommandOutput($dir);
    $statsBeforeCleanup = QuadbStore::statsCommandOutput($dir);
    $dumpBeforeCleanup = QuadbStore::dumpTreeCommandOutput($dir);

    $removeDiscarded = QuadbStore::headRemoveCommandOutput($dir, 'preview-discard');
    $gc = QuadbStore::garbageCollectCommandOutput($dir);
    $headsAfterCleanup = QuadbStore::headCommandOutput($dir);

    echo json_encode([
        'scenario' => 'inspect and clean WordPress snapshot preview heads with upstream-shaped quadb command output',
        'missingStatusFailsClosed' => $missingStatus['exitCode'] === 1 && !is_dir($missingDir),
        'emptyStatus' => rtrim($emptyStatus['stdout'], "\r\n"),
        'statusBeforeCleanup' => rtrim($statusBeforeCleanup['stdout'], "\r\n"),
        'headsBeforeCleanup' => $lines($headsBeforeCleanup['stdout']),
        'statsBeforeCleanup' => $lines($statsBeforeCleanup['stdout']),
        'dumpTreePreviewLines' => array_slice($lines($dumpBeforeCleanup['stdout']), 0, 5),
        'removeDiscardedHead' => $removeDiscarded,
        'gcOutput' => rtrim($gc['stdout'], "\r\n"),
        'headsAfterCleanup' => $lines($headsAfterCleanup['stdout']),
        'discardedHeadRemoved' => !str_contains($headsAfterCleanup['stdout'], 'preview-discard :'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($missingDir);
    $cleanup($dir);
}
