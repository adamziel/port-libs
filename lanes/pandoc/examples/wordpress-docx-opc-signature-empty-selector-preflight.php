<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-empty-selector.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

$signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="#local-empty-selector">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="https://example.test/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
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
    ['name' => 'word/media/hero.png', 'data' => 'PNG'],
    ['name' => '_xmlsignatures/sig-empty-selector.xml', 'data' => $signatureXml],
]));

$transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-empty-selector.xml');
$referenceIssues = array_map(
    static fn (array $transform): array => [
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'source' => $transform['source'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipCount' => $transform['relationshipCount'],
        'issues' => $transform['issues'],
    ],
    $transforms,
);

$summary = [
    'signaturePart' => '/_xmlsignatures/sig-empty-selector.xml',
    'referenceIssues' => $referenceIssues,
    'wordpressImport' => [
        'emptySelectorReferenceCount' => count(array_filter(
            $referenceIssues,
            static fn (array $reference): bool => in_array('empty-relationship-selector', $reference['issues'], true),
        )),
        'blockedSignatureTransformReferences' => count(array_filter(
            $referenceIssues,
            static fn (array $reference): bool => $reference['issues'] !== [],
        )),
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['wordpressImport']['emptySelectorReferenceCount'] ?? null) !== 3) {
        throw new RuntimeException('OPC signature empty-selector preflight did not flag every empty selector');
    }
    if (($referenceIssues[0]['issues'] ?? []) !== [
        'relationship-transform-reference-same-document',
        'relationship-transform-reference-has-fragment',
        'empty-relationship-selector',
    ]) {
        throw new RuntimeException('OPC signature same-document reference did not preserve empty-selector diagnostics');
    }
    if (($referenceIssues[1]['issues'] ?? []) !== [
        'relationship-transform-reference-external-uri',
        'empty-relationship-selector',
    ]) {
        throw new RuntimeException('OPC signature external reference did not preserve empty-selector diagnostics');
    }
    if (($referenceIssues[2]['source'] ?? null) !== '/word/document.xml' || ($referenceIssues[2]['selectorValid'] ?? null) !== false) {
        throw new RuntimeException('OPC signature resolvable reference did not report an invalid empty selector');
    }

    echo "wordpress-docx-opc-signature-empty-selector-preflight self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
