<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-dump-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-dump-target-' . bin2hex(random_bytes(6));

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
    $proofHex = $source->exportProofHex([
        'wp_options:siteurl',
        'wp_posts:404',
    ], Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-preview-tree');
    $target->importProofHex($proofHex, $trustedRoot);

    $fullDumpLines = $lines($source->dumpTreeText());
    $partialDumpLines = $lines($target->dumpTreeText());

    echo json_encode([
        'scenario' => 'inspect a quadb-style WordPress snapshot tree and delegated proof tree',
        'trustedRoot' => $trustedRoot,
        'fullTreeFirstLines' => array_slice($fullDumpLines, 0, 5),
        'partialTreeFirstLines' => array_slice($partialDumpLines, 0, 6),
        'partialTreeShowsWitnesses' => str_contains($target->dumpTreeText(), 'witness'),
        'delegatedSiteUrl' => $target->get('wp_options:siteurl'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
