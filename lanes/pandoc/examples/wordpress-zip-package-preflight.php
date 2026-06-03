<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    [
        'name' => '[Content_Types].xml',
        'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
        'compressionMethod' => 0,
    ],
    [
        'name' => '_rels/.rels',
        'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
    ],
    [
        'name' => 'word/document.xml',
        'data' => '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>',
        'comment' => 'generated document part',
    ],
], 'wordpress import package');

if (in_array('--self-test', $argv, true)) {
    if (!$package->has('/word/document.xml')) {
        throw new RuntimeException('Expected word/document.xml to be discoverable as an OPC part');
    }

    if ($package->read('/word/document.xml') !== '<w:document><w:body><w:p>WordPress import source</w:p></w:body></w:document>') {
        throw new RuntimeException('Expected document part bytes to round-trip from the ZIP package');
    }

    if ($package->packageComment() !== 'wordpress import package') {
        throw new RuntimeException('Expected package comment metadata to round-trip from the generated ZIP package');
    }

    echo "zip package writer preflight self-test passed\n";
    exit(0);
}

echo "ZIP package parts for WordPress import preflight:\n";
echo 'packageComment=' . $package->packageComment() . "\n";
foreach ($package->entries() as $entry) {
    echo '- ' . $entry->name . ' method=' . $entry->compressionMethod . ' crc32=' . $entry->crc32Hex() . "\n";
}
echo 'document.xml=' . $package->read('/word/document.xml') . "\n";
