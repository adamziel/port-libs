<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-integer-proof-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-integer-proof-target-' . bin2hex(random_bytes(6));

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
    $source = QuadbStore::init($sourceDir);
    $source->importIntegerLines(
        "1,wp_options:siteurl=https://example.test\n"
        . "2,wp_options:home=https://example.test\n"
        . "3,wp_posts:1=Published post\n"
    );

    $trustedRoot = $source->tree()->rootHash();
    $homeAndMetaProof = $source->exportIntegerProofHex([2, 4]);

    $source->importIntegerLines(
        "2,wp_options:home=https://preview.example.test\n"
        . "4,wp_postmeta:1:_thumbnail_id=42\n"
    );
    $updatedRoot = $source->tree()->rootHash();

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-integer-preview');
    $target->importProofHex($homeAndMetaProof, $trustedRoot);
    $target->importIntegerLines(
        "2,wp_options:home=https://preview.example.test\n"
        . "4,wp_postmeta:1:_thumbnail_id=42\n"
    );

    $delegatedProof = QuadbStore::open($targetDir)->exportIntegerProofHex([2, 4]);
    $partial = SparseTree::importProof(
        Proof::decode(hex2bin(substr(trim($delegatedProof), 2))),
        $updatedRoot
    );

    echo json_encode([
        'scenario' => 'persist a raw integer proof-head WordPress preview update',
        'trustedRoot' => $trustedRoot,
        'updatedRoot' => $updatedRoot,
        'partialRoot' => $target->status()['rootHash'],
        'currentHead' => $target->currentHeadName(),
        'home' => $target->getInteger(2),
        'thumbnailMeta' => $target->getKey(Key::fromInteger(4)),
        'delegatedHome' => $partial->getKey(Key::fromInteger(2)),
        'delegatedThumbnailMeta' => $partial->getKey(Key::fromInteger(4)),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
