<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-partial-export-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-partial-export-target-' . bin2hex(random_bytes(6));

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

$lines = static fn (string $text): array => array_values(array_filter(explode("\n", trim($text)), 'strlen'));

try {
    $source = QuadbStore::init($sourceDir);
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $optionProof = $source->exportProofHex(
        ['wp_options:siteurl', 'wp_posts:404'],
        Proof::ENCODING_FULL_KEYS
    );
    $postProof = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-export');
    $target->importProofHex($optionProof, $trustedRoot);
    $partialExport = $lines($target->exportLines('|'));

    $target->mergeProofHex($postProof);
    $mergedExport = $lines($target->exportLines('|'));

    echo json_encode([
        'scenario' => 'export a delegated WordPress proof head with witness placeholders',
        'trustedRoot' => $trustedRoot,
        'currentHead' => $target->currentHeadName(),
        'partialExport' => $partialExport,
        'mergedExport' => $mergedExport,
        'siteUrl' => $target->get('wp_options:siteurl'),
        'post' => $target->get('wp_posts:1'),
        'containsWitnessPlaceholder' => count(array_filter(
            $mergedExport,
            static fn (string $line): bool => str_contains($line, 'H(?)=0x')
        )) > 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
