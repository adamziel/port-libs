<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-unauth-proof-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-unauth-proof-target-' . bin2hex(random_bytes(6));

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

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-preview-unauthenticated');

    $dumpLines = explode("\n", rtrim($target->importProofHexDumpText($proofHex), "\n"));
    $importOutput = $target->importProofHexOutputText($proofHex);
    try {
        $target->get('wp_posts:404');
        $missingDraftUnavailable = false;
    } catch (RuntimeException) {
        $missingDraftUnavailable = true;
    }

    echo json_encode([
        'scenario' => 'debug and import a delegated WordPress proof without supplying a trusted root',
        'caution' => 'matches quadb importProof without --root; callers should compare the reported root with a trusted channel before serving data',
        'proofDumpFirstLine' => $dumpLines[0] ?? '',
        'unauthenticatedImportOutput' => trim($importOutput),
        'reportedRoot' => $target->status()['rootHash'],
        'trustedRootFromSource' => $trustedRoot,
        'rootMatchesTrustedSource' => $target->status()['rootHash'] === $trustedRoot,
        'siteUrl' => $target->get('wp_options:siteurl'),
        'missingDraftUnavailable' => $missingDraftUnavailable,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
