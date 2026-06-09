<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="XML" ContentType="text/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/WORD/document.xml" ContentType="application/xml"/>
  <Override PartName="/word/media/hero.png" ContentType="image/png"/>
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>'],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/media/hero.png', 'data' => 'PNG'],
]);

$preflight = OpcRelationshipGraph::preflightContentTypesInPackage($package);
$strictParserRejected = false;
try {
    OpcContentTypes::fromXml($contentTypesXml);
} catch (InvalidArgumentException) {
    $strictParserRejected = true;
}

$summary = [
    'partName' => $preflight['partName'],
    'present' => $preflight['present'],
    'valid' => $preflight['valid'],
    'recordCount' => $preflight['recordCount'],
    'invalidCount' => $preflight['invalidCount'],
    'duplicateDefaultExtensions' => $preflight['duplicateDefaultExtensions'],
    'duplicateOverridePartNames' => $preflight['duplicateOverridePartNames'],
    'issueCounts' => $preflight['issueCounts'],
    'strictParserRejected' => $strictParserRejected,
    'wordpressImport' => [
        'reviewStatus' => $preflight['valid'] ? 'content-types-ready' : 'content-types-review',
        'blockedReason' => $preflight['issues'],
        'duplicateDefaultExtensionGroups' => $preflight['duplicateDefaultExtensionGroups'],
        'duplicateOverridePartNameGroups' => $preflight['duplicateOverridePartNameGroups'],
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['wordpressImport']['reviewStatus'] ?? null) !== 'content-types-review') {
        throw new RuntimeException('OPC content-type collision preflight did not request review');
    }

    if (($summary['invalidCount'] ?? null) !== 4) {
        throw new RuntimeException('OPC content-type collision preflight did not count invalid records');
    }

    if (($summary['duplicateDefaultExtensions'] ?? null) !== ['xml']) {
        throw new RuntimeException('OPC content-type collision preflight did not preserve duplicate default extension key');
    }

    if (($summary['duplicateOverridePartNames'] ?? null) !== ['/word/document.xml']) {
        throw new RuntimeException('OPC content-type collision preflight did not preserve duplicate override part key');
    }

    if (($summary['strictParserRejected'] ?? null) !== true) {
        throw new RuntimeException('OPC strict content-type parser should still reject duplicate declarations');
    }

    echo "wordpress-docx-opc-content-type-collision-preflight self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
