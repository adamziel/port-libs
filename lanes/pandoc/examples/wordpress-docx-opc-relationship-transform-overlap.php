<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-a.docx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/source-b.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/_xmlsignatures/sig-selector-overlap.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPackageA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-a.docx"/>
  <Relationship Id="rIdPackageB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-b.xlsx"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
</Relationships>
XML;

$signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdPackageA"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
    ['name' => 'word/embeddings/source-a.docx', 'data' => 'PK' . "\x03\x04"],
    ['name' => 'word/embeddings/source-b.xlsx', 'data' => 'PK' . "\x03\x04"],
    ['name' => '_xmlsignatures/sig-selector-overlap.xml', 'data' => $signatureXml],
]));

$transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-selector-overlap.xml');
$transform = $transforms[0] ?? null;
if (!is_array($transform)) {
    throw new RuntimeException('Expected one OPC relationship transform preflight row');
}

$summary = [
    'signaturePart' => '/_xmlsignatures/sig-selector-overlap.xml',
    'transform' => [
        'source' => $transform['source'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'sourceIds' => $transform['sourceIds'],
        'sourceTypes' => $transform['sourceTypes'],
        'selectorOverlappingRelationshipIds' => $transform['selectorOverlappingRelationshipIds'],
        'selectorOverlapCount' => $transform['selectorOverlapCount'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
    ],
    'wordpressImport' => [
        'reviewStatus' => $transform['selectorOverlapCount'] > 0 ? 'selector-overlap-review' : 'ready',
        'overlappingRelationshipIds' => $transform['selectorOverlappingRelationshipIds'],
        'uniqueSelectedRelationshipCount' => $transform['relationshipCount'],
        'externalRelationshipsSkipped' => array_values(array_filter(
            ['rIdReviewer'],
            static fn (string $id): bool => !in_array($id, $transform['relationshipIds'], true),
        )),
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['wordpressImport']['reviewStatus'] ?? null) !== 'selector-overlap-review') {
        throw new RuntimeException('OPC relationship transform overlap did not request review');
    }

    if (($summary['wordpressImport']['overlappingRelationshipIds'] ?? null) !== ['rIdPackageA']) {
        throw new RuntimeException('OPC relationship transform overlap did not preserve the overlapping relationship id');
    }

    if (($summary['wordpressImport']['uniqueSelectedRelationshipCount'] ?? null) !== 2) {
        throw new RuntimeException('OPC relationship transform overlap did not preserve unique selected relationship count');
    }

    if (($summary['transform']['relationshipIds'] ?? null) !== ['rIdPackageA', 'rIdPackageB']) {
        throw new RuntimeException('OPC relationship transform overlap selected the wrong relationships');
    }

    if (($summary['transform']['valid'] ?? null) !== true || ($summary['transform']['issues'] ?? null) !== []) {
        throw new RuntimeException('OPC relationship transform overlap should remain a valid union selector');
    }

    echo "wordpress-docx-opc-relationship-transform-overlap self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
