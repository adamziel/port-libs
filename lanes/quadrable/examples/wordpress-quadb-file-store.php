<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
$dir = sys_get_temp_dir() . '/quadrable-wp-quadb-' . bin2hex(random_bytes(6));

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

try {
    $repo = QuadbStore::init($dir);
    $import = '';
    foreach ($records as $record) {
        $import .= $record['key'] . ',' . $record['value'] . "\n";
    }

    $repo->importIntegerLines($import);
    $published = $repo->tree();
    $publishedRoot = $published->rootHash();
    $publishedHeadNodeId = $published->headNodeId();

    $repo->fork('preview-snapshot');
    $repo->importIntegerLines("3,wp_posts:1=File-backed preview edit\n6,wp_posts:3=Unpublished preview\n");

    $reopened = QuadbStore::open($dir);
    $currentHeadAfterReopen = $reopened->currentHeadName();
    $preview = $reopened->tree();
    $publishedAgain = $reopened->checkout('master');

    echo json_encode([
        'scenario' => 'persist quadb-style current heads for WordPress snapshots in native PHP',
        'currentHeadAfterReopen' => $currentHeadAfterReopen,
        'publishedHeadNodeId' => $publishedHeadNodeId,
        'publishedRootRestored' => $publishedAgain->rootHash() === $publishedRoot,
        'previewPost' => $preview->getKey(Key::fromInteger(3)),
        'publishedPost' => $publishedAgain->getKey(Key::fromInteger(3)),
        'publishedHidesPreviewOnlyPost' => $publishedAgain->getKey(Key::fromInteger(6)) === null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
}
