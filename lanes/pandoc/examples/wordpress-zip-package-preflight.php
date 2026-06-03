<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ZipPackage;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

/**
 * @param list<array{name:string, data:string, method:int}> $entries
 */
$buildZipPackage = static function (array $entries) use ($crc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'];
        $flags = 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $crc = $crc32($data);
        $offset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        );
        $central .= $name;
    }

    $centralOffset = strlen($body);
    $centralSize = strlen($central);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralSize, $centralOffset, 0);
};

$package = ZipPackage::fromString($buildZipPackage([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'method' => 0,
    ],
    [
        'name' => '_rels/.rels',
        'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
        'method' => 8,
    ],
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>',
        'method' => 8,
    ],
]));

if (in_array('--self-test', $argv, true)) {
    if (!$package->has('/word/document.xml')) {
        throw new RuntimeException('Expected word/document.xml to be discoverable as an OPC part');
    }

    if ($package->read('/word/document.xml') !== '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected document part bytes to round-trip from the ZIP package');
    }

    echo "zip package preflight self-test passed\n";
    exit(0);
}

echo "ZIP package parts for WordPress import preflight:\n";
foreach ($package->entries() as $entry) {
    echo '- ' . $entry->name . ' method=' . $entry->compressionMethod . ' crc32=' . $entry->crc32Hex() . "\n";
}
echo 'document.xml=' . $package->read('/word/document.xml') . "\n";
