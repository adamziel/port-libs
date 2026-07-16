<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$dir = sys_get_temp_dir() . '/quadrable-wp-quadb-proof-' . bin2hex(random_bytes(6));

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
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $repo->tree()->rootHash();
    $proofHex = $repo->exportProofHex([
        'wp_options:siteurl',
        'wp_posts:1',
        'wp_posts:404',
    ], Proof::ENCODING_FULL_KEYS);

    $proofBytes = (string) hex2bin(substr(trim($proofHex), 2));
    $partial = SparseTree::importProof(Proof::decode($proofBytes), $trustedRoot);

    $entries = [];
    foreach ($partial->orderedEntries() as $entry) {
        $entries[$entry->stringKey() ?? $entry->keyHex()] = $entry->value();
    }
    ksort($entries, SORT_STRING);

    echo json_encode([
        'scenario' => 'export a quadb-style FullKeys proof for a delegated WordPress preview read',
        'trustedRoot' => $trustedRoot,
        'proofHexBytes' => intdiv(strlen(trim($proofHex)) - 2, 2),
        'encodingType' => Proof::ENCODING_FULL_KEYS,
        'partialRootMatches' => $partial->rootHash() === $trustedRoot,
        'provedRecords' => $entries,
        'missingDraftProvedAbsent' => $partial->get('wp_posts:404') === null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($dir);
}
