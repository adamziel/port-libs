<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-quadb-notrackkeys-' . bin2hex(random_bytes(6));
$proofDir = sys_get_temp_dir() . '/quadrable-wp-quadb-notrackkeys-proof-' . bin2hex(random_bytes(6));

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
    $repo = QuadbStore::init($dir, false);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $repo->tree()->rootHash();
    $hashedProofBytes = $repo->exportProofBytes(['wp_options:siteurl']);
    $partial = SparseTree::importProof(Proof::decode($hashedProofBytes), $trustedRoot);
    $raw = $repo->lmdbRawEntrySnapshot();

    $proofRepo = QuadbStore::init($proofDir, false);
    $proofRepo->checkout('private-proof');
    $proofRepo->importProofBytes($hashedProofBytes, $trustedRoot);
    $proofRepo->put('wp_options:siteurl', 'https://private.example.test');
    $proofRaw = $proofRepo->lmdbRawEntrySnapshot();

    $fullKeysAvailable = true;
    try {
        $repo->exportProofBytes(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
    } catch (RuntimeException) {
        $fullKeysAvailable = false;
    }

    echo json_encode([
        'scenario' => 'quadb --noTrackKeys for privacy-preserving WordPress snapshot exports',
        'trustedRoot' => $trustedRoot,
        'exportLines' => array_values(array_filter(explode("\n", trim($repo->exportLines('|'))))),
        'dumpMentionsOriginalKeys' => str_contains($repo->dumpTreeText(), 'wp_options:siteurl'),
        'hashedProofBytes' => strlen($hashedProofBytes),
        'hashedProofRead' => $partial->get('wp_options:siteurl'),
        'rawKeyBucketEntries' => count($raw['quadrable_key']),
        'proofRawKeyBucketEntries' => count($proofRaw['quadrable_key']),
        'proofHead' => $proofRepo->currentHeadName(),
        'proofHeadRead' => $proofRepo->get('wp_options:siteurl'),
        'fullKeysAvailable' => $fullKeysAvailable,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
    $cleanup($proofDir);
}
