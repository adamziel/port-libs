<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-stats-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-stats-target-' . bin2hex(random_bytes(6));

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
    $source = QuadbStore::init($sourceDir);
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $proofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('wp-delegated-stats');
    $target->importProofHex($proofHex, $trustedRoot);

    $fullStats = $source->stats();
    $partialStats = $target->stats();

    echo json_encode([
        'scenario' => 'report quadb-style stats for a full WordPress snapshot and a delegated proof head',
        'trustedRoot' => $trustedRoot,
        'fullHeadStatsText' => $lines($source->statsText()),
        'fullHeadStats' => $fullStats,
        'delegatedProofStatsText' => $lines($target->statsText()),
        'delegatedProofStats' => $partialStats,
        'delegatedProofHasWitnesses' => $partialStats['numWitnessNodes'] > 0,
        'reopenedStatsStable' => QuadbStore::open($targetDir)->statsText() === $target->statsText(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
