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
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/media/source%20diagram.svg" ContentType="image/svg+xml; charset=UTF-8"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero%20image.PNG"/>
  <Relationship Id="rIdDiagram" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/source%20diagram.svg"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdUnsafeReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdMalformedType" Type="officeDocument/relationships/hyperlink" Target="https://example.test/source-with-bad-type" TargetMode="External"/>
</Relationships>
XML;

$footnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFootnoteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/footnote-source.png"/>
</Relationships>
XML;

$signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
    ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
    ['name' => 'word/media/hero image.PNG', 'data' => 'PNG'],
    ['name' => 'word/media/source diagram.svg', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
    ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
    ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
]);

$graph = OpcRelationshipGraph::fromPackage($package);
$types = $graph->contentTypes();
$documentPart = $graph->firstTargetOfType(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);
if ($documentPart === null) {
    throw new RuntimeException('DOCX package does not contain an officeDocument relationship');
}

$documentRelationships = $graph->requireRelationshipsForSource($documentPart);

$packagePartPreflight = [];
foreach ($graph->preflightPackageParts() as $part) {
    $packagePartPreflight[$part['partName']] = [
        'contentType' => $part['contentType'],
        'relationshipPart' => $part['relationshipPart'],
        'relationshipSource' => $part['relationshipSource'],
        'relationshipSourceIsRelationshipPart' => $part['relationshipSourceIsRelationshipPart'],
        'sourceExists' => $part['sourceExists'],
        'valid' => $part['valid'],
        'issues' => $part['issues'],
    ];
}

$relationshipSummaries = [];
foreach ($graph->summarizeTargetsForSource($documentPart) as $relationship) {
    $relationshipSummaries[$relationship['id']] = [
        'target' => $relationship['target'],
        'contentType' => $relationship['contentType'],
        'external' => $relationship['external'],
    ];
}

$relationshipPreflight = [];
foreach ($graph->preflightTargetsForSource($documentPart) as $target) {
    $relationshipPreflight[$target['id']] = [
        'id' => $target['id'],
        'target' => $target['target'],
        'contentType' => $target['contentType'],
        'relationshipTypeKind' => $target['relationshipTypeKind'],
        'relationshipTypeScheme' => $target['relationshipTypeScheme'],
        'relationshipTypeValid' => $target['relationshipTypeValid'],
        'relationshipTypeIssues' => $target['relationshipTypeIssues'],
        'external' => $target['external'],
        'exists' => $target['exists'],
        'externalTargetKind' => $target['externalTargetKind'],
        'externalTargetScheme' => $target['externalTargetScheme'],
        'externalTargetAllowed' => $target['externalTargetAllowed'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

$reachableTargets = [];
foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
    $reachableTargets[] = [
        'source' => $target['source'],
        'id' => $target['id'],
        'target' => $target['target'],
        'targetPart' => $target['targetPart'],
        'contentType' => $target['contentType'],
        'relationshipTypeKind' => $target['relationshipTypeKind'],
        'relationshipTypeScheme' => $target['relationshipTypeScheme'],
        'relationshipTypeValid' => $target['relationshipTypeValid'],
        'relationshipTypeIssues' => $target['relationshipTypeIssues'],
        'external' => $target['external'],
        'externalTargetKind' => $target['externalTargetKind'],
        'externalTargetScheme' => $target['externalTargetScheme'],
        'externalTargetAllowed' => $target['externalTargetAllowed'],
        'depth' => $target['depth'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

$digitalSignatures = $graph->preflightDigitalSignatures();
$digitalSignatureParts = [];
foreach ($digitalSignatures as $origin) {
    if ($origin['targetPart'] !== null) {
        $digitalSignatureParts[] = $origin['targetPart'];
    }

    foreach ($origin['signatures'] as $signature) {
        if ($signature['targetPart'] !== null) {
            $digitalSignatureParts[] = $signature['targetPart'];
        }
    }
}
$digitalSignatureParts = array_values(array_unique($digitalSignatureParts));

$corePropertiesPart = $graph->firstTargetOfType('http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties');

$summary = [
    'document' => [
        'part' => $documentPart,
        'contentType' => $types->contentTypeForPart($documentPart),
        'relationshipsPart' => $documentRelationships->relationshipPartName(),
    ],
    'coreProperties' => [
        'part' => $corePropertiesPart,
        'contentType' => $corePropertiesPart === null ? null : $types->contentTypeForPart($corePropertiesPart),
    ],
    'digitalSignatures' => $digitalSignatures,
    'packageParts' => $packagePartPreflight,
    'relationships' => $relationshipSummaries,
    'reachableRelationships' => $reachableTargets,
    'integrity' => [
        'packagePartsValid' => array_reduce(
            $packagePartPreflight,
            static fn (bool $valid, array $part): bool => $valid && $part['valid'],
            true
        ),
        'documentRelationshipsValid' => array_reduce(
            $relationshipPreflight,
            static fn (bool $valid, array $target): bool => $valid && $target['valid'],
            true
        ),
        'reachableRelationshipsValid' => array_reduce(
            $reachableTargets,
            static fn (bool $valid, array $target): bool => $valid && $target['valid'],
            true
        ),
        'issues' => array_values(array_filter(
            $reachableTargets,
            static fn (array $target): bool => $target['issues'] !== []
        )),
    ],
    'wordpressImport' => [
        'mediaParts' => array_values(array_unique(array_filter(
            array_map(static fn (array $target): ?string => $target['targetPart'], $reachableTargets),
            static fn (?string $target): bool => $target !== null && str_starts_with($target, '/word/media/')
        ))),
        'externalTargets' => array_values(array_map(
            static fn (array $target): array => [
                'id' => $target['id'],
                'target' => $target['target'],
                'kind' => $target['externalTargetKind'],
                'scheme' => $target['externalTargetScheme'],
                'allowed' => $target['externalTargetAllowed'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'issues' => $target['issues'],
            ],
            array_filter($relationshipPreflight, static fn (array $target): bool => $target['external'] === true)
        )),
        'digitalSignatureParts' => $digitalSignatureParts,
        'hasReviewerEditLink' => ($relationshipSummaries['rIdReviewer']['external'] ?? false) === true,
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        '/word/document.xml',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        '/word/_rels/document.xml.rels',
        '/word/media/hero image.PNG',
        'image/png',
        '/word/media/source diagram.svg',
        'image/svg+xml; charset=UTF-8',
        '/word/media/footnote-source.png',
        'https://example.test/wp-admin/post.php?post=42&action=edit',
        '/_xmlsignatures/origin.sigs',
        '/_xmlsignatures/sig1.xml',
    ];
    $actual = [
        $summary['document']['part'],
        $summary['document']['contentType'],
        $summary['document']['relationshipsPart'],
        $summary['relationships']['rIdHero']['target'],
        $summary['relationships']['rIdHero']['contentType'],
        $summary['wordpressImport']['mediaParts'][1] ?? null,
        $summary['relationships']['rIdDiagram']['contentType'],
        $summary['wordpressImport']['mediaParts'][2] ?? null,
        $summary['relationships']['rIdReviewer']['target'],
        $summary['wordpressImport']['digitalSignatureParts'][0] ?? null,
        $summary['wordpressImport']['digitalSignatureParts'][1] ?? null,
    ];
    if (
        $actual !== $expected
        || $summary['wordpressImport']['hasReviewerEditLink'] !== true
        || ($summary['digitalSignatures'][0]['relationshipPartName'] ?? null) !== '/_xmlsignatures/_rels/origin.sigs.rels'
        || ($summary['digitalSignatures'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.digital-signature-origin'
        || ($summary['digitalSignatures'][0]['signatures'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml'
        || ($summary['digitalSignatures'][0]['valid'] ?? null) !== true
        || $summary['integrity']['packagePartsValid'] !== true
        || $summary['packageParts']['/_rels/.rels']['relationshipSource'] !== '/'
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSource'] !== '/word/document.xml'
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/word/media/hero image.PNG']['contentType'] !== 'image/png'
        || $summary['integrity']['documentRelationshipsValid'] !== false
        || $summary['integrity']['reachableRelationshipsValid'] !== false
        || ($summary['wordpressImport']['externalTargets'][0]['scheme'] ?? null) !== 'https'
        || ($summary['wordpressImport']['externalTargets'][0]['allowed'] ?? null) !== true
        || ($summary['wordpressImport']['externalTargets'][1]['id'] ?? null) !== 'rIdUnsafeReviewer'
        || ($summary['wordpressImport']['externalTargets'][1]['scheme'] ?? null) !== 'javascript'
        || ($summary['wordpressImport']['externalTargets'][1]['allowed'] ?? null) !== false
        || ($summary['wordpressImport']['externalTargets'][1]['issues'] ?? null) !== ['external-target-unsafe-scheme']
        || ($summary['integrity']['issues'][0]['id'] ?? null) !== 'rIdUnsafeReviewer'
        || ($summary['integrity']['issues'][0]['issues'] ?? null) !== ['external-target-unsafe-scheme']
        || ($summary['wordpressImport']['externalTargets'][2]['id'] ?? null) !== 'rIdMalformedType'
        || ($summary['wordpressImport']['externalTargets'][2]['relationshipTypeKind'] ?? null) !== 'relative-reference'
        || ($summary['wordpressImport']['externalTargets'][2]['relationshipTypeValid'] ?? null) !== false
        || ($summary['wordpressImport']['externalTargets'][2]['relationshipTypeIssues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['wordpressImport']['externalTargets'][2]['issues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['integrity']['issues'][1]['id'] ?? null) !== 'rIdMalformedType'
        || ($summary['integrity']['issues'][1]['issues'] ?? null) !== ['relationship-type-not-absolute-uri']
    ) {
        throw new RuntimeException('OPC DOCX preflight self-test failed');
    }

    echo "opc docx preflight self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
