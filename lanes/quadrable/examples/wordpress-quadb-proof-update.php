<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-proof-update-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-proof-update-target-' . bin2hex(random_bytes(6));

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
    $proofHex = $source->exportProofHex([
        'wp_options:siteurl',
        'wp_posts:404',
    ], Proof::ENCODING_FULL_KEYS);

    $source->put('wp_options:siteurl', 'https://preview.example.test');
    $updatedRoot = $source->tree()->rootHash();

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-preview');
    $target->importProofHex($proofHex, $trustedRoot);
    $target->fork('delegated-preview-edit');
    $target->put('wp_options:siteurl', 'https://preview.example.test');

    $reopened = QuadbStore::open($targetDir);
    $reopened->checkout('delegated-preview-edit');
    $updatedProofHex = $reopened->exportProofHex([
        'wp_options:siteurl',
        'wp_posts:404',
    ], Proof::ENCODING_FULL_KEYS);
    $updatedProofBytes = (string) hex2bin(substr(trim($updatedProofHex), 2));
    $partial = SparseTree::importProof(Proof::decode($updatedProofBytes), $updatedRoot);

    echo json_encode([
        'scenario' => 'fork and update a persisted delegated WordPress proof head',
        'trustedRoot' => $trustedRoot,
        'updatedRoot' => $updatedRoot,
        'reopenedRoot' => $reopened->status()['rootHash'],
        'currentHead' => $reopened->currentHeadName(),
        'siteUrl' => $reopened->get('wp_options:siteurl'),
        'updatedProofRootMatches' => $partial->rootHash() === $updatedRoot,
        'missingDraftStillProvedAbsent' => $partial->get('wp_posts:404') === null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
