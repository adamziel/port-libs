<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-lmdb-raw-source-' . bin2hex(random_bytes(6));
$proofDir = sys_get_temp_dir() . '/quadrable-wp-lmdb-raw-proof-' . bin2hex(random_bytes(6));

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

$entrySummary = static function (array $entries, int $limit = 3) use ($uint64le): array {
    $summary = [];
    foreach (array_slice($entries, 0, $limit) as $entry) {
        $nodeType = null;
        if (strlen($entry['value']) >= 8) {
            $nodeType = $uint64le(substr($entry['value'], 0, 8)) % 16;
        }

        $summary[] = [
            'keyHex' => bin2hex($entry['key']),
            'keyNodeId' => strlen($entry['key']) === 8 ? $uint64le($entry['key']) : null,
            'valueLength' => strlen($entry['value']),
            'valueNodeType' => $nodeType,
            'valueHexPrefix' => substr(bin2hex($entry['value']), 0, 96),
        ];
    }

    return $summary;
};

$stringSummary = static function (string $value): array {
    return [
        'hex' => bin2hex($value),
        'text' => ctype_print($value) ? $value : null,
    ];
};

try {
    $binaryKey = "wp_options:serialized-\xff";
    $binaryValue = "autoload\0\xffserialized:site-option\x80";
    $previewBinaryValue = "preview\0\xffpost-bytes\x81";

    $repo = QuadbStore::init($sourceDir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n"
        . "wp_postmeta:1:_thumbnail_id|42\n",
        '|'
    );
    $repo->put($binaryKey, $binaryValue);
    $repo->fork('2');
    $repo->put('wp_posts:2', $previewBinaryValue);
    $repo->fork('10', 'master');
    $repo->fork('a-preview', '2');

    $fullRaw = $repo->lmdbRawEntrySnapshot();
    $trustedRoot = $repo->tree()->rootHash();
    $proofHex = $repo->exportProofHex([
        $binaryKey,
        'wp_posts:404',
    ], Proof::ENCODING_FULL_KEYS);

    $proofRepo = QuadbStore::init($proofDir);
    $proofRepo->checkout('delegated-preview');
    $proofRepo->importProofHex($proofHex, $trustedRoot);
    $proofRepo->put($binaryKey, "delegated\0\xffpreview-update\x82");
    $proofRaw = $proofRepo->lmdbRawEntrySnapshot();

    echo json_encode([
        'scenario' => 'export raw Quadrable LMDB entry bytes for a WordPress snapshot backup manifest',
        'binaryFixture' => [
            'keyHex' => bin2hex($binaryKey),
            'valueHex' => bin2hex($binaryValue),
            'previewValueHex' => bin2hex($previewBinaryValue),
        ],
        'fullHead' => [
            'headCursorOrder' => array_column($fullRaw['quadrable_head'], 'key'),
            'headNodeId' => isset($fullRaw['quadrable_head'][0])
                ? $uint64le($fullRaw['quadrable_head'][0]['value'])
                : null,
            'stateEntries' => array_map(
                static fn (array $entry): array => [
                    'key' => $entry['key'],
                    'valueHex' => bin2hex($entry['value']),
                    'valueText' => ctype_print($entry['value']) ? $entry['value'] : null,
                ],
                $fullRaw['quadrable_quadb_state']
            ),
            'bucketCounts' => [
                'quadrable_nodesLeaf' => count($fullRaw['quadrable_nodesLeaf']),
                'quadrable_nodesInterior' => count($fullRaw['quadrable_nodesInterior']),
                'quadrable_key' => count($fullRaw['quadrable_key']),
            ],
            'firstLeafEntries' => $entrySummary($fullRaw['quadrable_nodesLeaf']),
            'trackedKeyEntryKeys' => array_map(
                static fn (array $entry): array => [
                    'nodeId' => $uint64le($entry['key']),
                    'key' => $stringSummary($entry['value']),
                ],
                $fullRaw['quadrable_key']
            ),
        ],
        'delegatedProofHead' => [
            'headKey' => $proofRaw['quadrable_head'][0]['key'] ?? null,
            'headNodeId' => isset($proofRaw['quadrable_head'][0])
                ? $uint64le($proofRaw['quadrable_head'][0]['value'])
                : null,
            'stateEntries' => array_map(
                static fn (array $entry): array => [
                    'key' => $entry['key'],
                    'valueHex' => bin2hex($entry['value']),
                    'valueText' => ctype_print($entry['value']) ? $entry['value'] : null,
                ],
                $proofRaw['quadrable_quadb_state']
            ),
            'firstLeafEntries' => $entrySummary($proofRaw['quadrable_nodesLeaf']),
            'firstInteriorEntries' => $entrySummary($proofRaw['quadrable_nodesInterior']),
            'trackedKeyEntryKeys' => array_map(
                static fn (array $entry): array => [
                    'nodeId' => $uint64le($entry['key']),
                    'key' => $stringSummary($entry['value']),
                ],
                $proofRaw['quadrable_key']
            ),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($proofDir);
}
