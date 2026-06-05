<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml" review:origin="fixture">
    <review:Note value="ignored"/>
  </Default>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/draft.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/media/source%20diagram.svg" ContentType="image/svg+xml; charset=UTF-8"/>
  <Override PartName="/word/embeddings/source%20workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/oleObject1.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml" review:label="main">
    <review:Trace value="ignored"/>
  </Relationship>
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
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source%20workbook.xlsx"/>
  <Relationship Id="rIdEmbeddedOle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/oleObject1.bin"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdRelativeReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="review/source.html#packet" TargetMode="External"/>
  <Relationship Id="rIdUnsafeReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdMalformedType" Type="officeDocument/relationships/hyperlink" Target="https://example.test/source-with-bad-type" TargetMode="External"/>
  <Relationship Id="rIdDraftReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="draft.xml"/>
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

$draftRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDraftImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/draft-hidden.png"/>
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
    ['name' => 'word/draft.xml', 'data' => '<draft/>'],
    ['name' => 'word/_rels/draft.xml.rels', 'data' => $draftRelationshipsXml],
    ['name' => 'word/media/draft-hidden.png', 'data' => 'PNG'],
    ['name' => 'word/media/hero image.PNG', 'data' => 'PNG'],
    ['name' => 'word/media/source diagram.svg', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
    ['name' => 'word/embeddings/source workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
    ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
    ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
    ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
]);

$graph = OpcRelationshipGraph::fromPackage($package);
$types = $graph->contentTypes();
$officeDocumentRoot = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
$documentPart = $officeDocumentRoot['relationships'][0]['targetPart'] ?? null;
if ($documentPart === null || $officeDocumentRoot['valid'] !== true) {
    throw new RuntimeException('DOCX package does not contain one valid WordprocessingML officeDocument relationship');
}

$documentRelationships = $graph->requireRelationshipsForSource($documentPart);

$packagePartPreflight = [];
foreach ($graph->preflightPackageParts() as $part) {
    $packagePartPreflight[$part['partName']] = [
        'partName' => $part['partName'],
        'contentType' => $part['contentType'],
        'relationshipPart' => $part['relationshipPart'],
        'relationshipSource' => $part['relationshipSource'],
        'relationshipSourceIsRelationshipPart' => $part['relationshipSourceIsRelationshipPart'],
        'relationshipSourceLoaded' => $part['relationshipSourceLoaded'],
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
        'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
        'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
        'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
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
        'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
        'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
        'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
        'depth' => $target['depth'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

$digitalSignatures = $graph->preflightDigitalSignatures();
$embeddedPackages = $graph->preflightEmbeddedPackages($documentPart);
$embeddedPackageParts = [];
$embeddedObjectParts = [];
foreach ($embeddedPackages as $embeddedPackage) {
    if ($embeddedPackage['targetPart'] === null) {
        continue;
    }

    if ($embeddedPackage['kind'] === 'embedded-package') {
        $embeddedPackageParts[] = $embeddedPackage['targetPart'];
    } elseif ($embeddedPackage['kind'] === 'embedded-object') {
        $embeddedObjectParts[] = $embeddedPackage['targetPart'];
    }
}
$embeddedPackageParts = array_values(array_unique($embeddedPackageParts));
$embeddedObjectParts = array_values(array_unique($embeddedObjectParts));
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
$strictXmlShapeGuards = [
    'contentTypeUnexpectedAttributeRejected' => false,
    'contentTypeRootAttributeRejected' => false,
    'relationshipChildContentRejected' => false,
    'relationshipRootTextRejected' => false,
];
$markupCompatibilityGuards = [
    'ignorableContentTypeExtensionAccepted' => $types->contentTypeForPart('/word/document.xml') === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
    'ignorableRelationshipExtensionAccepted' => $graph->firstTargetOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument') === '/word/document.xml',
    'undeclaredContentTypeExtensionRejected' => false,
    'undeclaredRelationshipExtensionRejected' => false,
    'unsupportedMarkupCompatibilityAttributeRejected' => false,
];
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml" Extra="1"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeUnexpectedAttributeRejected'] = true;
}
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" Extra="1"><Default Extension="xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeRootAttributeRejected'] = true;
}
try {
    OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdBad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"><Child/></Relationship></Relationships>', '/word/document.xml');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['relationshipChildContentRejected'] = true;
}
try {
    OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '">text<Relationship Id="rIdBad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>', '/word/document.xml');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['relationshipRootTextRejected'] = true;
}
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" review:source="import-preflight"><Default Extension="xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $markupCompatibilityGuards['undeclaredContentTypeExtensionRejected'] = true;
}
try {
    OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><review:Audit/><Relationship Id="rIdBad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>', '/word/document.xml');
} catch (InvalidArgumentException) {
    $markupCompatibilityGuards['undeclaredRelationshipExtensionRejected'] = true;
}
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:ProcessContent="review"><Default Extension="xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $markupCompatibilityGuards['unsupportedMarkupCompatibilityAttributeRejected'] = true;
}

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
    'officeDocumentRoot' => $officeDocumentRoot,
    'digitalSignatures' => $digitalSignatures,
    'embeddedPackages' => $embeddedPackages,
    'packageParts' => $packagePartPreflight,
    'relationshipSources' => $graph->sourcePartNames(),
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
        'invalidRelationshipParts' => array_values(array_filter(
            $packagePartPreflight,
            static fn (array $part): bool => $part['relationshipPart'] === true && $part['issues'] !== []
        )),
        'issues' => array_values(array_filter(
            $reachableTargets,
            static fn (array $target): bool => $target['issues'] !== []
        )),
        'strictXmlShapeGuards' => $strictXmlShapeGuards,
        'markupCompatibilityGuards' => $markupCompatibilityGuards,
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
                'requiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'rewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'rewriteReason' => $target['externalTargetRewriteReason'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'issues' => $target['issues'],
            ],
            array_filter($relationshipPreflight, static fn (array $target): bool => $target['external'] === true)
        )),
        'digitalSignatureParts' => $digitalSignatureParts,
        'embeddedPackageParts' => $embeddedPackageParts,
        'embeddedObjectParts' => $embeddedObjectParts,
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
        '/word/embeddings/source workbook.xlsx',
        'application/vnd.openxmlformats-officedocument.package',
        '/word/embeddings/oleObject1.bin',
        'application/vnd.openxmlformats-officedocument.oleObject',
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
        $summary['wordpressImport']['embeddedPackageParts'][0] ?? null,
        $summary['embeddedPackages'][0]['contentType'] ?? null,
        $summary['wordpressImport']['embeddedObjectParts'][0] ?? null,
        $summary['embeddedPackages'][1]['contentType'] ?? null,
    ];
    if (
        $actual !== $expected
        || $summary['wordpressImport']['hasReviewerEditLink'] !== true
        || ($summary['embeddedPackages'][0]['kind'] ?? null) !== 'embedded-package'
        || ($summary['embeddedPackages'][0]['valid'] ?? null) !== true
        || ($summary['embeddedPackages'][0]['issues'] ?? null) !== []
        || ($summary['embeddedPackages'][1]['kind'] ?? null) !== 'embedded-object'
        || ($summary['embeddedPackages'][1]['valid'] ?? null) !== true
        || ($summary['embeddedPackages'][1]['issues'] ?? null) !== []
        || ($summary['officeDocumentRoot']['relationshipCount'] ?? null) !== 1
        || ($summary['officeDocumentRoot']['valid'] ?? null) !== true
        || ($summary['officeDocumentRoot']['issues'] ?? null) !== []
        || ($summary['officeDocumentRoot']['relationships'][0]['id'] ?? null) !== 'rIdDocument'
        || ($summary['officeDocumentRoot']['relationships'][0]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['officeDocumentRoot']['relationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['digitalSignatures'][0]['relationshipPartName'] ?? null) !== '/_xmlsignatures/_rels/origin.sigs.rels'
        || ($summary['digitalSignatures'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.digital-signature-origin'
        || ($summary['digitalSignatures'][0]['signatures'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml'
        || ($summary['digitalSignatures'][0]['valid'] ?? null) !== true
        || $summary['integrity']['packagePartsValid'] !== false
        || $summary['relationshipSources'] !== ['/', '/_xmlsignatures/origin.sigs', '/word/document.xml', '/word/footnotes.xml']
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipSource'] ?? null) !== '/word/draft.xml'
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['integrity']['invalidRelationshipParts'][0]['partName'] ?? null) !== '/word/_rels/draft.xml.rels'
        || ($summary['integrity']['invalidRelationshipParts'][0]['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeUnexpectedAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeRootAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['relationshipChildContentRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['relationshipRootTextRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['ignorableContentTypeExtensionAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['ignorableRelationshipExtensionAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['undeclaredContentTypeExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['undeclaredRelationshipExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['unsupportedMarkupCompatibilityAttributeRejected'] ?? null) !== true
        || $summary['packageParts']['/_rels/.rels']['relationshipSource'] !== '/'
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceLoaded'] !== true
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSource'] !== '/word/document.xml'
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceLoaded'] !== true
        || $summary['packageParts']['/word/media/hero image.PNG']['contentType'] !== 'image/png'
        || isset($summary['relationships']['rIdDraftImage'])
        || $summary['integrity']['documentRelationshipsValid'] !== false
        || $summary['integrity']['reachableRelationshipsValid'] !== false
        || ($summary['wordpressImport']['externalTargets'][0]['scheme'] ?? null) !== 'https'
        || ($summary['wordpressImport']['externalTargets'][0]['allowed'] ?? null) !== true
        || ($summary['wordpressImport']['externalTargets'][0]['requiresBaseUri'] ?? null) !== false
        || ($summary['wordpressImport']['externalTargets'][1]['id'] ?? null) !== 'rIdRelativeReviewer'
        || ($summary['wordpressImport']['externalTargets'][1]['kind'] ?? null) !== 'relative-reference'
        || ($summary['wordpressImport']['externalTargets'][1]['allowed'] ?? null) !== true
        || ($summary['wordpressImport']['externalTargets'][1]['requiresBaseUri'] ?? null) !== true
        || ($summary['wordpressImport']['externalTargets'][1]['rewriteBasePart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['externalTargets'][1]['rewriteReason'] ?? null) !== 'external-target-relative-reference'
        || ($summary['wordpressImport']['externalTargets'][1]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['externalTargets'][2]['id'] ?? null) !== 'rIdUnsafeReviewer'
        || ($summary['wordpressImport']['externalTargets'][2]['scheme'] ?? null) !== 'javascript'
        || ($summary['wordpressImport']['externalTargets'][2]['allowed'] ?? null) !== false
        || ($summary['wordpressImport']['externalTargets'][2]['issues'] ?? null) !== ['external-target-unsafe-scheme']
        || ($summary['integrity']['issues'][0]['id'] ?? null) !== 'rIdUnsafeReviewer'
        || ($summary['integrity']['issues'][0]['issues'] ?? null) !== ['external-target-unsafe-scheme']
        || ($summary['wordpressImport']['externalTargets'][3]['id'] ?? null) !== 'rIdMalformedType'
        || ($summary['wordpressImport']['externalTargets'][3]['relationshipTypeKind'] ?? null) !== 'relative-reference'
        || ($summary['wordpressImport']['externalTargets'][3]['relationshipTypeValid'] ?? null) !== false
        || ($summary['wordpressImport']['externalTargets'][3]['relationshipTypeIssues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['wordpressImport']['externalTargets'][3]['issues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['integrity']['issues'][1]['id'] ?? null) !== 'rIdMalformedType'
        || ($summary['integrity']['issues'][1]['issues'] ?? null) !== ['relationship-type-not-absolute-uri']
    ) {
        throw new RuntimeException('OPC DOCX preflight self-test failed');
    }

    echo "opc docx preflight self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
