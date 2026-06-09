<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdGoodEncodedType" Type="http://example.test/relationships/source%20image" Target="media/review.png"/>
  <Relationship Id="rIdBadPercentType" Type="http://example.test/relationships/%ZZ" Target="media/review.png"/>
  <Relationship Id="rIdEncodedNulType" Type="http://example.test/relationships/%00image" Target="media/review.png"/>
</Relationships>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
    ['name' => 'word/media/review.png', 'data' => 'PNG'],
]);

$graph = OpcRelationshipGraph::fromPackage($package);
$relationshipTypePercentGuards = [];
foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
    $relationshipTypePercentGuards[$target['id']] = [
        'id' => $target['id'],
        'type' => $target['type'],
        'relationshipTypeKind' => $target['relationshipTypeKind'],
        'relationshipTypeScheme' => $target['relationshipTypeScheme'],
        'relationshipTypeValid' => $target['relationshipTypeValid'],
        'relationshipTypeIssues' => $target['relationshipTypeIssues'],
        'targetPartExists' => $target['exists'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

$invalid = array_values(array_filter(
    $relationshipTypePercentGuards,
    static fn (array $target): bool => !$target['relationshipTypeValid']
));

$summary = [
    'relationshipTypePercentGuards' => $relationshipTypePercentGuards,
    'wordpressImport' => [
        'reviewStatus' => $invalid === [] ? 'relationship-types-ready' : 'relationship-types-review',
        'invalidRelationshipTypeCount' => count($invalid),
        'invalidRelationshipTypes' => $invalid,
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['wordpressImport']['reviewStatus'] ?? null) !== 'relationship-types-review') {
        throw new RuntimeException('OPC relationship type percent preflight did not request review');
    }

    if (($summary['wordpressImport']['invalidRelationshipTypeCount'] ?? null) !== 2) {
        throw new RuntimeException('OPC relationship type percent preflight did not count invalid relationship types');
    }

    if (($summary['relationshipTypePercentGuards']['rIdGoodEncodedType']['valid'] ?? null) !== true) {
        throw new RuntimeException('OPC relationship type percent preflight rejected an encoded space relationship type');
    }

    if (($summary['relationshipTypePercentGuards']['rIdBadPercentType']['issues'] ?? null) !== ['relationship-type-malformed-percent-escape']) {
        throw new RuntimeException('OPC relationship type percent preflight missed malformed percent escape');
    }

    if (($summary['relationshipTypePercentGuards']['rIdEncodedNulType']['issues'] ?? null) !== ['relationship-type-unsafe-percent-encoded-byte']) {
        throw new RuntimeException('OPC relationship type percent preflight missed unsafe encoded control byte');
    }

    echo "wordpress-docx-opc-relationship-type-percent-preflight self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
