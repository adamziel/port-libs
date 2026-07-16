<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-numeric-head-source-' . bin2hex(random_bytes(6));
$proofDir = sys_get_temp_dir() . '/quadrable-wp-numeric-head-proof-' . bin2hex(random_bytes(6));

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

$uint64le = static function (string $bytes): int {
    $parts = unpack('Vlow/Vhigh', $bytes);
    if (!is_array($parts)) {
        throw new RuntimeException('unable to unpack uint64');
    }

    return $parts['low'] + ($parts['high'] * 4294967296);
};

try {
    $repo = QuadbStore::init($sourceDir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );

    $masterRoot = $repo->tree()->rootHash();
    $repo->fork('20260523');
    $repo->put('wp_posts:1', 'Editorial preview for 2026-05-23');
    $previewRaw = $repo->lmdbRawEntrySnapshot();

    $repo->checkout('master');
    $proofHex = $repo->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);

    $proofRepo = QuadbStore::init($proofDir);
    $proofRepo->checkout('404');
    $proofRepo->importProofHex($proofHex, $masterRoot);
    $delegatedRaw = $proofRepo->lmdbRawEntrySnapshot();
    $repo->checkout('20260523');

    echo json_encode([
        'scenario' => 'persist WordPress preview heads whose names are numeric ids or dates',
        'previewHead' => [
            'currentHead' => $repo->currentHeadName(),
            'rawState' => array_map(
                static fn (array $entry): array => [
                    'key' => $entry['key'],
                    'valueText' => $entry['value'],
                ],
                $previewRaw['quadrable_quadb_state']
            ),
            'rawHeadKeys' => array_map(
                static fn (array $entry): array => [
                    'headKey' => $entry['key'],
                    'nodeId' => $uint64le($entry['value']),
                ],
                $previewRaw['quadrable_head']
            ),
        ],
        'delegatedProofHead' => [
            'currentHead' => $proofRepo->currentHeadName(),
            'trustedRoot' => $masterRoot,
            'siteurl' => $proofRepo->get('wp_options:siteurl'),
            'rawState' => array_map(
                static fn (array $entry): array => [
                    'key' => $entry['key'],
                    'valueText' => $entry['value'],
                ],
                $delegatedRaw['quadrable_quadb_state']
            ),
            'rawHeadKeys' => array_map(
                static fn (array $entry): array => [
                    'headKey' => $entry['key'],
                    'nodeId' => $uint64le($entry['value']),
                ],
                $delegatedRaw['quadrable_head']
            ),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($proofDir);
}
