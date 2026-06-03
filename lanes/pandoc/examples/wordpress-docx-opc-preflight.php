<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcRelationship;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.PNG"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
</Relationships>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
    ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/media/hero.PNG', 'data' => 'PNG'],
    ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
]);

$types = OpcContentTypes::fromXml($package->read('[Content_Types].xml'));
$packageRelationships = OpcRelationships::fromPackage($package);
$officeDocument = $packageRelationships->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument');
if (!$officeDocument instanceof OpcRelationship) {
    throw new RuntimeException('DOCX package does not contain an officeDocument relationship');
}

$documentPart = $packageRelationships->resolveTarget($officeDocument);
$documentRelationships = OpcRelationships::fromPackage($package, $documentPart);

$relationshipSummaries = [];
foreach ($documentRelationships->all() as $relationship) {
    $target = $documentRelationships->resolveTarget($relationship);
    $relationshipSummaries[$relationship->id] = [
        'target' => $target,
        'contentType' => $relationship->isExternal() ? null : $types->contentTypeForPart($target),
        'external' => $relationship->isExternal(),
    ];
}

$summary = [
    'document' => [
        'part' => $documentPart,
        'contentType' => $types->contentTypeForPart($documentPart),
        'relationshipsPart' => $documentRelationships->relationshipPartName(),
    ],
    'coreProperties' => [
        'part' => $packageRelationships->resolveTarget('rIdCore'),
        'contentType' => $types->contentTypeForPart($packageRelationships->resolveTarget('rIdCore')),
    ],
    'relationships' => $relationshipSummaries,
    'wordpressImport' => [
        'mediaParts' => array_values(array_filter(
            array_column($relationshipSummaries, 'target'),
            static fn (string $target): bool => str_starts_with($target, '/word/media/')
        )),
        'hasReviewerEditLink' => ($relationshipSummaries['rIdReviewer']['external'] ?? false) === true,
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        '/word/document.xml',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        '/word/_rels/document.xml.rels',
        '/word/media/hero.PNG',
        'image/png',
        'https://example.test/wp-admin/post.php?post=42&action=edit',
    ];
    $actual = [
        $summary['document']['part'],
        $summary['document']['contentType'],
        $summary['document']['relationshipsPart'],
        $summary['relationships']['rIdHero']['target'],
        $summary['relationships']['rIdHero']['contentType'],
        $summary['relationships']['rIdReviewer']['target'],
    ];
    if ($actual !== $expected || $summary['wordpressImport']['hasReviewerEditLink'] !== true) {
        throw new RuntimeException('OPC DOCX preflight self-test failed');
    }

    echo "opc docx preflight self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
