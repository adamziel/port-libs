<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-proof-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-proof-target-' . bin2hex(random_bytes(6));

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
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $optionProof = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
    $postProof = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-preview');
    $target->importProofHex($optionProof, $trustedRoot);

    $reopened = QuadbStore::open($targetDir);
    $reopened->mergeProofHex($postProof);

    echo json_encode([
        'scenario' => 'persist a delegated WordPress proof head, then merge another proof after reopen',
        'trustedRoot' => $trustedRoot,
        'partialRoot' => $reopened->status()['rootHash'],
        'currentHead' => $reopened->currentHeadName(),
        'siteUrl' => $reopened->get('wp_options:siteurl'),
        'post' => $reopened->get('wp_posts:1'),
        'proofHeadPersisted' => QuadbStore::open($targetDir)->get('wp_posts:1') === 'Published post',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
