<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcPackagePath;
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
  <Override PartName="/word/review%20source.xml" ContentType="application/xml"/>
  <Override PartName="/word/media/source%20diagram.svg" ContentType="image/svg+xml; charset=UTF-8"/>
  <Override PartName="/word/embeddings/source%20workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/oleObject1.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
  <Override PartName="/word/media/stale%20source.png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-selector-shape.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
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
  <Relationship Id="rIdReviewSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="review%20source.xml"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source%20workbook.xlsx"/>
  <Relationship Id="rIdEmbeddedOle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/oleObject1.bin"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdInternalBookmark" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#review-bookmark"/>
  <Relationship Id="rIdInternalReviewState" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="?review=ready#packet"/>
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

$reviewSourceRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review%20source.png"/>
</Relationships>
XML;

$signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML;

$signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipReference SourceId="rIdReviewer"/>
          <mdssi:RelationshipGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$selectorShapeSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero" mdssi:review="bad"><mdssi:Trace/></mdssi:RelationshipReference>
          <mdssi:RelationshipGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Extra="bad">text</mdssi:RelationshipGroupReference>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
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
    ['name' => 'word/review source.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/review%20source.xml.rels', 'data' => $reviewSourceRelationshipsXml],
    ['name' => 'word/draft.xml', 'data' => '<draft/>'],
    ['name' => 'word/_rels/draft.xml.rels', 'data' => $draftRelationshipsXml],
    ['name' => 'word/media/draft-hidden.png', 'data' => 'PNG'],
    ['name' => 'word/media/hero image.PNG', 'data' => 'PNG'],
    ['name' => 'word/media/source diagram.svg', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
    ['name' => 'word/media/review source.png', 'data' => 'PNG'],
    ['name' => 'word/embeddings/source workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
    ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
    ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
    ['name' => '_xmlsignatures/sig1.xml', 'data' => $signatureXml],
    ['name' => '_xmlsignatures/sig-selector-shape.xml', 'data' => $selectorShapeSignatureXml],
]);

$aliasCollisionContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/review%20source.xml" ContentType="application/xml"/>
</Types>
XML;

$aliasCollisionRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/review%20source.xml"/>
</Relationships>
XML;

$aliasCollisionEncodedRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEncodedReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/encoded.png"/>
</Relationships>
XML;

$aliasCollisionRawRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdRawReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/raw.png"/>
</Relationships>
XML;

$relationshipSourceAliasPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $aliasCollisionContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $aliasCollisionRootRelationshipsXml],
    ['name' => 'word/review source.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/review%20source.xml.rels', 'data' => $aliasCollisionEncodedRelationshipsXml],
    ['name' => 'word/_rels/review source.xml.rels', 'data' => $aliasCollisionRawRelationshipsXml],
    ['name' => 'word/media/encoded.png', 'data' => 'PNG'],
    ['name' => 'word/media/raw.png', 'data' => 'PNG'],
]);

$caseCollisionContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/Word/Document.XML" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$caseCollisionRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="Word/Document.XML"/>
</Relationships>
XML;

$caseCollisionPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $caseCollisionContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $caseCollisionRootRelationshipsXml],
    ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/media/Hero.PNG', 'data' => 'PNG'],
    ['name' => 'word/media/hero.png', 'data' => 'PNG'],
]);

$processContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc:Records">
  <pc:Records>
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/process-document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  </pc:Records>
  <pc:Ignored>
    <Override PartName="/word/hidden.xml" ContentType="application/xml"/>
  </pc:Ignored>
</Types>
XML;

$processContentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc:Records">
  <pc:Records>
    <Relationship Id="rIdProcessDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/process-document.xml"/>
    <Relationship Id="rIdProcessAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/process-audit.xml"/>
  </pc:Records>
  <pc:Ignored>
    <Relationship Id="rIdHidden" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/hidden.xml"/>
  </pc:Ignored>
</Relationships>
XML;

$processContentGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $processContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $processContentRelationshipsXml],
    ['name' => 'word/process-document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/process-audit.xml', 'data' => '<review/>'],
    ['name' => 'word/hidden.xml', 'data' => '<hidden/>'],
]));
$processContentRoot = $processContentGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
$processContentRelationships = $processContentGraph->requireRelationshipsForSource('/');
$markupCompatibilityProcessContent = [
    'sourceParts' => $processContentGraph->sourcePartNames(),
    'relationshipIds' => array_map(
        static fn ($relationship): string => $relationship->id,
        $processContentRelationships->all()
    ),
    'officeDocumentTargetPart' => $processContentRoot['relationships'][0]['targetPart'] ?? null,
    'officeDocumentValid' => $processContentRoot['valid'],
    'auditContentType' => $processContentGraph->contentTypes()->contentTypeForPart('/word/process-audit.xml'),
    'hiddenRelationshipLoaded' => $processContentRelationships->byId('rIdHidden') !== null,
];

$caseEquivalentTypes = new OpcContentTypes();
$caseEquivalentTypes->addDefault('xml', 'application/xml');
$caseEquivalentTypes->addOverride('/Word/Document.XML', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');
$caseEquivalentOverrideDuplicateRejected = false;
try {
    $caseEquivalentTypes->addOverride('/word/document.xml', 'application/xml');
} catch (InvalidArgumentException) {
    $caseEquivalentOverrideDuplicateRejected = true;
}

$caseEquivalentTargetContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/Word/Document.XML" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/Word/Styles.XML" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;

$caseEquivalentTargetRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$caseEquivalentTargetDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

$caseEquivalentTargetGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $caseEquivalentTargetContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $caseEquivalentTargetRootRelationshipsXml],
    ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'Word/_rels/Document.XML.rels', 'data' => $caseEquivalentTargetDocumentRelationshipsXml],
    ['name' => 'Word/Styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
]));
$caseEquivalentTargetRoot = $caseEquivalentTargetGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
$caseEquivalentTargetClosure = [];
foreach ($caseEquivalentTargetGraph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
    $caseEquivalentTargetClosure[$target['id']] = [
        'source' => $target['source'],
        'targetPart' => $target['targetPart'],
        'contentType' => $target['contentType'],
        'exists' => $target['exists'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}
$caseEquivalentTargets = [
    'officeDocumentPart' => $caseEquivalentTargetGraph->firstTargetOfType(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE),
    'lowercaseSourceRelationshipsLoaded' => $caseEquivalentTargetGraph->hasRelationshipsForSource('/word/document.xml'),
    'relationshipPartName' => $caseEquivalentTargetGraph->requireRelationshipsForSource('/word/document.xml')->relationshipPartName(),
    'officeDocumentRootTargetPart' => $caseEquivalentTargetRoot['relationships'][0]['targetPart'] ?? null,
    'officeDocumentRootExists' => $caseEquivalentTargetRoot['relationships'][0]['exists'] ?? null,
    'officeDocumentRootValid' => $caseEquivalentTargetRoot['valid'],
    'closure' => $caseEquivalentTargetClosure,
];

$relationshipSourceAliasGraphRejected = false;
try {
    OpcRelationshipGraph::fromPackage($relationshipSourceAliasPackage);
} catch (RuntimeException) {
    $relationshipSourceAliasGraphRejected = true;
}

$partNameCaseCollisionGraphRejected = false;
try {
    OpcRelationshipGraph::fromPackage($caseCollisionPackage);
} catch (RuntimeException) {
    $partNameCaseCollisionGraphRejected = true;
}

$partNameCaseCollisionGuards = [];
foreach (OpcRelationshipGraph::preflightPackagePartNameEquivalence($caseCollisionPackage) as $part) {
    if ($part['valid']) {
        continue;
    }

    $partNameCaseCollisionGuards[$part['partName']] = [
        'partName' => $part['partName'],
        'equivalenceKey' => $part['equivalenceKey'],
        'equivalentPartNames' => $part['equivalentPartNames'],
        'valid' => $part['valid'],
        'issues' => $part['issues'],
    ];
}

$relationshipSourceAliasGuards = [];
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($relationshipSourceAliasPackage) as $part) {
    if ($part['relationshipSource'] !== '/word/review source.xml') {
        continue;
    }

    $relationshipSourceAliasGuards[$part['partName']] = [
        'partName' => $part['partName'],
        'relationshipSource' => $part['relationshipSource'],
        'sourceExists' => $part['sourceExists'],
        'duplicateRelationshipPartNames' => $part['duplicateRelationshipPartNames'],
        'loaded' => $part['loaded'],
        'relationshipCount' => $part['relationshipCount'],
        'valid' => $part['valid'],
        'issues' => $part['issues'],
    ];
}

$relationshipPartLoads = [];
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
    $relationshipPartLoads[$part['partName']] = [
        'partName' => $part['partName'],
        'contentType' => $part['contentType'],
        'relationshipSource' => $part['relationshipSource'],
        'relationshipSourceIsRelationshipPart' => $part['relationshipSourceIsRelationshipPart'],
        'sourceExists' => $part['sourceExists'],
        'loaded' => $part['loaded'],
        'relationshipCount' => $part['relationshipCount'],
        'valid' => $part['valid'],
        'issues' => $part['issues'],
        'parseError' => $part['parseError'],
    ];
}

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

$packageConsistency = $graph->preflightPackageConsistency();
$packageConsistencyOverrides = [];
foreach ($packageConsistency['contentTypeOverrides'] as $override) {
    $packageConsistencyOverrides[$override['partName']] = $override;
}
$packageConsistencyTargets = [];
foreach ($packageConsistency['relationshipTargets'] as $target) {
    $packageConsistencyTargets[$target['source'] . ':' . $target['id']] = $target;
}

$relationshipSummaries = [];
foreach ($graph->summarizeTargetsForSource($documentPart) as $relationship) {
    $relationshipSummaries[$relationship['id']] = [
        'target' => $relationship['target'],
        'contentType' => $relationship['contentType'],
        'external' => $relationship['external'],
    ];
}

$relationshipTypeInventory = [];
foreach ($graph->relationshipTypeInventory() as $type) {
    $relationshipTypeInventory[$type['type']] = $type;
}

$contentTypeInventory = [];
foreach ($graph->contentTypeInventory() as $contentType) {
    $contentTypeInventory[$contentType['contentType']] = $contentType;
}

$relationshipPreflight = [];
foreach ($graph->preflightTargetsForSource($documentPart) as $target) {
    $relationshipPreflight[$target['id']] = [
        'id' => $target['id'],
        'target' => $target['target'],
        'targetPart' => $target['external'] || in_array('invalid-target', $target['issues'], true)
            ? null
            : OpcPackagePath::stripQueryAndFragment($target['target']),
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

$relationshipSelector = $graph->preflightRelationshipSelector(
    $documentPart,
    ['rIdHero', 'rIdReviewer', 'rIdMissingSelector'],
    [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE],
);
$relationshipSelectorRelationships = [];
foreach ($relationshipSelector['relationships'] as $relationship) {
    $relationshipSelectorRelationships[$relationship['id']] = [
        'id' => $relationship['id'],
        'type' => $relationship['type'],
        'target' => $relationship['target'],
        'targetPart' => $relationship['targetPart'],
        'contentType' => $relationship['contentType'],
        'external' => $relationship['external'],
        'selectedBySourceId' => $relationship['selectedBySourceId'],
        'selectedBySourceType' => $relationship['selectedBySourceType'],
        'valid' => $relationship['valid'],
        'issues' => $relationship['issues'],
    ];
}
$relationshipSelectorSummary = [
    'source' => $relationshipSelector['source'],
    'sourceIds' => $relationshipSelector['sourceIds'],
    'sourceTypes' => $relationshipSelector['sourceTypes'],
    'unmatchedSourceIds' => $relationshipSelector['unmatchedSourceIds'],
    'unmatchedSourceTypes' => $relationshipSelector['unmatchedSourceTypes'],
    'valid' => $relationshipSelector['valid'],
    'issues' => $relationshipSelector['issues'],
    'relationships' => $relationshipSelectorRelationships,
];
$relationshipTransform = $graph->materializeRelationshipTransform(
    $documentPart,
    ['rIdHero', 'rIdReviewer'],
    [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE],
);
$signatureRelationshipTransforms = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig1.xml') as $transform) {
    $signatureRelationshipTransforms[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceIndex' => $transform['referenceIndex'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceTargetContentType' => $transform['referenceTargetContentType'],
        'referenceContentType' => $transform['referenceContentType'],
        'referenceContentTypeMatches' => $transform['referenceContentTypeMatches'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'sourceTypes' => $transform['sourceTypes'],
        'followingCanonicalizationAlgorithm' => $transform['followingCanonicalizationAlgorithm'],
        'followedByCanonicalization' => $transform['followedByCanonicalization'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXml' => $transform['relationshipXml'],
    ];
}
$signatureRelationshipTransformGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-selector-shape.xml') as $transform) {
    $signatureRelationshipTransformGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceTargetContentType' => $transform['referenceTargetContentType'],
        'referenceContentType' => $transform['referenceContentType'],
        'referenceContentTypeMatches' => $transform['referenceContentTypeMatches'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'sourceTypes' => $transform['sourceTypes'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
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
    'contentTypeDefaultDotExtensionRejected' => false,
    'contentTypeOverrideRelativePartNameRejected' => false,
    'contentTypeOverrideDotSegmentRejected' => false,
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
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension=".xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeDefaultDotExtensionRejected'] = true;
}
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="word/document.xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeOverrideRelativePartNameRejected'] = true;
}
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/./document.xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeOverrideDotSegmentRejected'] = true;
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
    'packageConsistency' => [
        'valid' => $packageConsistency['valid'],
        'packagePartsValid' => $packageConsistency['packagePartsValid'],
        'contentTypeOverridesValid' => $packageConsistency['contentTypeOverridesValid'],
        'relationshipTargetsValid' => $packageConsistency['relationshipTargetsValid'],
        'contentTypeOverrides' => $packageConsistencyOverrides,
        'relationshipTargets' => $packageConsistencyTargets,
    ],
    'relationshipPartLoads' => $relationshipPartLoads,
    'packageParts' => $packagePartPreflight,
    'relationshipSources' => $graph->sourcePartNames(),
    'relationshipTypeInventory' => $relationshipTypeInventory,
    'relationships' => $relationshipSummaries,
    'relationshipSelector' => $relationshipSelectorSummary,
    'relationshipTransform' => [
        'source' => $relationshipTransform['source'],
        'relationshipPartName' => $relationshipTransform['relationshipPartName'],
        'transformAlgorithm' => $relationshipTransform['transformAlgorithm'],
        'sourceIds' => $relationshipTransform['sourceIds'],
        'sourceTypes' => $relationshipTransform['sourceTypes'],
        'relationshipIds' => $relationshipTransform['relationshipIds'],
        'relationshipCount' => $relationshipTransform['relationshipCount'],
        'selectorValid' => $relationshipTransform['selectorValid'],
        'relationshipTargetsValid' => $relationshipTransform['relationshipTargetsValid'],
        'valid' => $relationshipTransform['valid'],
        'issues' => $relationshipTransform['issues'],
        'relationshipXml' => $relationshipTransform['relationshipXml'],
    ],
    'signatureRelationshipTransforms' => $signatureRelationshipTransforms,
    'signatureRelationshipTransformGuards' => $signatureRelationshipTransformGuards,
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
        'markupCompatibilityProcessContent' => $markupCompatibilityProcessContent,
        'relationshipSourceAliasGraphRejected' => $relationshipSourceAliasGraphRejected,
        'partNameCaseCollisionGraphRejected' => $partNameCaseCollisionGraphRejected,
        'contentTypeOverrideCaseLookup' => $caseEquivalentTypes->contentTypeForPart('/word/document.xml'),
        'contentTypeOverrideDuplicateRejected' => $caseEquivalentOverrideDuplicateRejected,
        'caseEquivalentTargets' => $caseEquivalentTargets,
    ],
    'relationshipSourceAliasGuards' => $relationshipSourceAliasGuards,
    'partNameCaseCollisionGuards' => $partNameCaseCollisionGuards,
    'contentTypeInventory' => $contentTypeInventory,
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
        'internalSourceReferences' => array_values(array_map(
            static fn (array $target): array => [
                'id' => $target['id'],
                'target' => $target['target'],
                'targetPart' => $target['targetPart'],
                'contentType' => $target['contentType'],
                'issues' => $target['issues'],
            ],
            array_filter($relationshipPreflight, static fn (array $target): bool => $target['external'] === false
                && $target['targetPart'] === $documentPart
                && $target['target'] !== $documentPart)
        )),
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
        '/word/document.xml#review-bookmark',
        '/word/document.xml?review=ready#packet',
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
        $summary['relationships']['rIdInternalBookmark']['target'] ?? null,
        $summary['relationships']['rIdInternalReviewState']['target'] ?? null,
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
        || ($summary['relationships']['rIdInternalBookmark']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['relationships']['rIdInternalReviewState']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['id'] ?? null) !== 'rIdInternalBookmark'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['internalSourceReferences'][1]['id'] ?? null) !== 'rIdInternalReviewState'
        || ($summary['wordpressImport']['internalSourceReferences'][1]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['internalSourceReferences'][1]['issues'] ?? null) !== []
        || ($summary['packageConsistency']['valid'] ?? null) !== false
        || ($summary['packageConsistency']['packagePartsValid'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverridesValid'] ?? null) !== false
        || ($summary['packageConsistency']['relationshipTargetsValid'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/media/stale source.png']['exists'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/media/stale source.png']['issues'] ?? null) !== ['override-target-missing-part']
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/_rels/draft.xml.rels']['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdCore']['targetPart'] ?? null) !== '/docProps/core.xml'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdSignatureOrigin']['targetPart'] ?? null) !== '/_xmlsignatures/origin.sigs'
        || ($summary['packageConsistency']['relationshipTargets']['/word/review source.xml:rIdReviewSourceImage']['targetPart'] ?? null) !== '/word/media/review source.png'
        || isset($summary['packageConsistency']['relationshipTargets']['/word/draft.xml:rIdDraftImage'])
        || $summary['integrity']['packagePartsValid'] !== false
        || $summary['relationshipSources'] !== ['/', '/_xmlsignatures/origin.sigs', '/word/document.xml', '/word/footnotes.xml', '/word/review source.xml']
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['relationshipSource'] ?? null) !== '/word/review source.xml'
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['relationshipSourceLoaded'] ?? null) !== true
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['issues'] ?? null) !== []
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipSource'] ?? null) !== '/word/draft.xml'
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['relationshipPartLoads']['/_rels/.rels']['relationshipSource'] ?? null) !== '/'
        || ($summary['relationshipPartLoads']['/_rels/.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/_rels/.rels']['relationshipCount'] ?? null) !== 3
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['relationshipSource'] ?? null) !== '/word/document.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['relationshipCount'] ?? null) !== 14
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['relationshipSource'] ?? null) !== '/word/review source.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['sourceExists'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['relationshipSource'] ?? null) !== '/word/draft.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['loaded'] ?? null) !== false
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['relationshipCount'] ?? null) !== null
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['relationshipPartLoads']['/_xmlsignatures/_rels/origin.sigs.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/_xmlsignatures/_rels/origin.sigs.rels']['relationshipCount'] ?? null) !== 1
        || ($summary['integrity']['invalidRelationshipParts'][0]['partName'] ?? null) !== '/word/_rels/draft.xml.rels'
        || ($summary['integrity']['invalidRelationshipParts'][0]['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['integrity']['relationshipSourceAliasGraphRejected'] ?? null) !== true
        || ($summary['integrity']['partNameCaseCollisionGraphRejected'] ?? null) !== true
        || ($summary['integrity']['contentTypeOverrideCaseLookup'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['integrity']['contentTypeOverrideDuplicateRejected'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['officeDocumentPart'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentTargets']['lowercaseSourceRelationshipsLoaded'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['relationshipPartName'] ?? null) !== '/Word/_rels/Document.XML.rels'
        || ($summary['integrity']['caseEquivalentTargets']['officeDocumentRootTargetPart'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentTargets']['officeDocumentRootExists'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['officeDocumentRootValid'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['closure']['rIdDocument']['targetPart'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentTargets']['closure']['rIdStyles']['source'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentTargets']['closure']['rIdStyles']['targetPart'] ?? null) !== '/Word/Styles.XML'
        || ($summary['integrity']['caseEquivalentTargets']['closure']['rIdStyles']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml'
        || ($summary['integrity']['caseEquivalentTargets']['closure']['rIdStyles']['exists'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['closure']['rIdStyles']['valid'] ?? null) !== true
        || array_keys($summary['relationshipSourceAliasGuards'] ?? []) !== [
            '/word/_rels/review%20source.xml.rels',
            '/word/_rels/review source.xml.rels',
        ]
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review%20source.xml.rels']['relationshipSource'] ?? null) !== '/word/review source.xml'
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review%20source.xml.rels']['duplicateRelationshipPartNames'] ?? null) !== [
            '/word/_rels/review source.xml.rels',
            '/word/_rels/review%20source.xml.rels',
        ]
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review%20source.xml.rels']['loaded'] ?? null) !== false
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review%20source.xml.rels']['relationshipCount'] ?? null) !== null
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review%20source.xml.rels']['issues'] ?? null) !== ['duplicate-relationship-source']
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review source.xml.rels']['relationshipSource'] ?? null) !== '/word/review source.xml'
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review source.xml.rels']['loaded'] ?? null) !== false
        || ($summary['relationshipSourceAliasGuards']['/word/_rels/review source.xml.rels']['issues'] ?? null) !== ['duplicate-relationship-source']
        || array_keys($summary['partNameCaseCollisionGuards'] ?? []) !== [
            '/Word/Document.XML',
            '/word/document.xml',
            '/word/media/Hero.PNG',
            '/word/media/hero.png',
        ]
        || ($summary['partNameCaseCollisionGuards']['/Word/Document.XML']['equivalenceKey'] ?? null) !== '/word/document.xml'
        || ($summary['partNameCaseCollisionGuards']['/Word/Document.XML']['equivalentPartNames'] ?? null) !== [
            '/Word/Document.XML',
            '/word/document.xml',
        ]
        || ($summary['partNameCaseCollisionGuards']['/Word/Document.XML']['issues'] ?? null) !== ['equivalent-part-name-case-collision']
        || ($summary['partNameCaseCollisionGuards']['/word/document.xml']['valid'] ?? null) !== false
        || ($summary['partNameCaseCollisionGuards']['/word/media/Hero.PNG']['equivalenceKey'] ?? null) !== '/word/media/hero.png'
        || ($summary['partNameCaseCollisionGuards']['/word/media/Hero.PNG']['equivalentPartNames'] ?? null) !== [
            '/word/media/Hero.PNG',
            '/word/media/hero.png',
        ]
        || ($summary['partNameCaseCollisionGuards']['/word/media/hero.png']['issues'] ?? null) !== ['equivalent-part-name-case-collision']
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeUnexpectedAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeDefaultDotExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeOverrideRelativePartNameRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeOverrideDotSegmentRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeRootAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['relationshipChildContentRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['relationshipRootTextRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['ignorableContentTypeExtensionAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['ignorableRelationshipExtensionAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['undeclaredContentTypeExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['undeclaredRelationshipExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['unsupportedMarkupCompatibilityAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityProcessContent']['sourceParts'] ?? null) !== ['/']
        || ($summary['integrity']['markupCompatibilityProcessContent']['relationshipIds'] ?? null) !== ['rIdProcessDocument', 'rIdProcessAudit']
        || ($summary['integrity']['markupCompatibilityProcessContent']['officeDocumentTargetPart'] ?? null) !== '/word/process-document.xml'
        || ($summary['integrity']['markupCompatibilityProcessContent']['officeDocumentValid'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityProcessContent']['auditContentType'] ?? null) !== 'application/xml'
        || ($summary['integrity']['markupCompatibilityProcessContent']['hiddenRelationshipLoaded'] ?? null) !== false
        || $summary['packageParts']['/_rels/.rels']['relationshipSource'] !== '/'
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceLoaded'] !== true
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSource'] !== '/word/document.xml'
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceLoaded'] !== true
        || $summary['packageParts']['/word/media/hero image.PNG']['contentType'] !== 'image/png'
        || ($summary['wordpressImport']['mediaParts'][3] ?? null) !== '/word/media/review source.png'
        || ($summary['relationships']['rIdReviewSource']['target'] ?? null) !== '/word/review source.xml'
        || isset($summary['relationships']['rIdDraftImage'])
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image']['relationshipCount'] ?? null) !== 4
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image']['sourceCount'] ?? null) !== 3
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image']['targetParts'] ?? null) !== [
            '/word/media/footnote-source.png',
            '/word/media/hero image.PNG',
            '/word/media/review source.png',
            '/word/media/source diagram.svg',
        ]
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image']['contentTypes'] ?? null) !== ['image/png', 'image/svg+xml; charset=UTF-8']
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['relationshipCount'] ?? null) !== 4
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['externalCount'] ?? null) !== 3
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['internalCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['issues'] ?? null) !== ['external-target-unsafe-scheme']
        || ($summary['relationshipTypeInventory']['officeDocument/relationships/hyperlink']['relationshipTypeValid'] ?? null) !== false
        || ($summary['relationshipTypeInventory']['officeDocument/relationships/hyperlink']['relationshipTypeIssues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]['targetParts'] ?? null) !== ['/word/embeddings/source workbook.xlsx']
        || ($summary['contentTypeInventory']['application/vnd.openxmlformats-package.relationships+xml']['parts'] ?? null) !== [
            '/_rels/.rels',
            '/_xmlsignatures/_rels/origin.sigs.rels',
            '/word/_rels/document.xml.rels',
            '/word/_rels/footnotes.xml.rels',
            '/word/_rels/review%20source.xml.rels',
        ]
        || ($summary['contentTypeInventory']['application/vnd.openxmlformats-package.relationships+xml']['relationshipSources'] ?? null) !== [
            '/',
            '/_xmlsignatures/origin.sigs',
            '/word/document.xml',
            '/word/footnotes.xml',
            '/word/review source.xml',
        ]
        || ($summary['contentTypeInventory']['image/png']['parts'] ?? null) !== [
            '/word/media/draft-hidden.png',
            '/word/media/footnote-source.png',
            '/word/media/hero image.PNG',
            '/word/media/review source.png',
        ]
        || ($summary['contentTypeInventory']['image/png']['missingOverrideParts'] ?? null) !== ['/word/media/stale source.png']
        || ($summary['contentTypeInventory']['image/png']['issues'] ?? null) !== ['override-target-missing-part']
        || ($summary['contentTypeInventory']['application/xml']['relationshipParts'] ?? null) !== ['/word/_rels/draft.xml.rels']
        || ($summary['contentTypeInventory']['application/xml']['relationshipSources'] ?? null) !== ['/word/draft.xml']
        || ($summary['contentTypeInventory']['application/xml']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['relationshipSelector']['source'] ?? null) !== '/word/document.xml'
        || ($summary['relationshipSelector']['sourceIds'] ?? null) !== ['rIdHero', 'rIdReviewer', 'rIdMissingSelector']
        || ($summary['relationshipSelector']['sourceTypes'] ?? null) !== [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]
        || ($summary['relationshipSelector']['unmatchedSourceIds'] ?? null) !== ['rIdMissingSelector']
        || ($summary['relationshipSelector']['unmatchedSourceTypes'] ?? null) !== []
        || ($summary['relationshipSelector']['valid'] ?? null) !== false
        || ($summary['relationshipSelector']['issues'] ?? null) !== ['unmatched-source-id']
        || array_keys($summary['relationshipSelector']['relationships'] ?? []) !== ['rIdHero', 'rIdEmbeddedWorkbook', 'rIdReviewer']
        || ($summary['relationshipSelector']['relationships']['rIdHero']['selectedBySourceId'] ?? null) !== true
        || ($summary['relationshipSelector']['relationships']['rIdHero']['selectedBySourceType'] ?? null) !== false
        || ($summary['relationshipSelector']['relationships']['rIdHero']['targetPart'] ?? null) !== '/word/media/hero image.PNG'
        || ($summary['relationshipSelector']['relationships']['rIdEmbeddedWorkbook']['selectedBySourceType'] ?? null) !== true
        || ($summary['relationshipSelector']['relationships']['rIdEmbeddedWorkbook']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.package'
        || ($summary['relationshipSelector']['relationships']['rIdReviewer']['external'] ?? null) !== true
        || ($summary['relationshipSelector']['relationships']['rIdReviewer']['selectedBySourceId'] ?? null) !== true
        || ($summary['relationshipTransform']['source'] ?? null) !== '/word/document.xml'
        || ($summary['relationshipTransform']['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['relationshipTransform']['transformAlgorithm'] ?? null) !== OpcRelationshipGraph::RELATIONSHIP_TRANSFORM_ALGORITHM
        || ($summary['relationshipTransform']['sourceIds'] ?? null) !== ['rIdHero', 'rIdReviewer']
        || ($summary['relationshipTransform']['sourceTypes'] ?? null) !== [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]
        || ($summary['relationshipTransform']['relationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer']
        || ($summary['relationshipTransform']['relationshipCount'] ?? null) !== 3
        || ($summary['relationshipTransform']['selectorValid'] ?? null) !== true
        || ($summary['relationshipTransform']['relationshipTargetsValid'] ?? null) !== true
        || ($summary['relationshipTransform']['valid'] ?? null) !== true
        || ($summary['relationshipTransform']['issues'] ?? null) !== []
        || !str_contains((string) ($summary['relationshipTransform']['relationshipXml'] ?? ''), 'TargetMode="Internal"')
        || !str_contains((string) ($summary['relationshipTransform']['relationshipXml'] ?? ''), 'TargetMode="External"')
        || str_contains((string) ($summary['relationshipTransform']['relationshipXml'] ?? ''), 'rIdDraftReview')
        || count($summary['signatureRelationshipTransforms'] ?? []) !== 1
        || ($summary['signatureRelationshipTransforms'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig1.xml'
        || ($summary['signatureRelationshipTransforms'][0]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipTransforms'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureRelationshipTransforms'][0]['referenceTargetContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipTransforms'][0]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipTransforms'][0]['referenceContentTypeMatches'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['signatureRelationshipTransforms'][0]['sourceIds'] ?? null) !== ['rIdHero', 'rIdReviewer']
        || ($summary['signatureRelationshipTransforms'][0]['sourceTypes'] ?? null) !== [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]
        || ($summary['signatureRelationshipTransforms'][0]['followingCanonicalizationAlgorithm'] ?? null) !== 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'
        || ($summary['signatureRelationshipTransforms'][0]['followedByCanonicalization'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['relationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer']
        || ($summary['signatureRelationshipTransforms'][0]['relationshipCount'] ?? null) !== 3
        || ($summary['signatureRelationshipTransforms'][0]['selectorValid'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['valid'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['issues'] ?? null) !== []
        || !str_contains((string) ($summary['signatureRelationshipTransforms'][0]['relationshipXml'] ?? ''), 'Id="rIdEmbeddedWorkbook"')
        || str_contains((string) ($summary['signatureRelationshipTransforms'][0]['relationshipXml'] ?? ''), 'rIdDraftReview')
        || count($summary['signatureRelationshipTransformGuards'] ?? []) !== 1
        || ($summary['signatureRelationshipTransformGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-selector-shape.xml'
        || ($summary['signatureRelationshipTransformGuards'][0]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureRelationshipTransformGuards'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureRelationshipTransformGuards'][0]['referenceTargetContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipTransformGuards'][0]['referenceContentType'] ?? null) !== null
        || ($summary['signatureRelationshipTransformGuards'][0]['referenceContentTypeMatches'] ?? null) !== null
        || ($summary['signatureRelationshipTransformGuards'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['signatureRelationshipTransformGuards'][0]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureRelationshipTransformGuards'][0]['sourceTypes'] ?? null) !== [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]
        || ($summary['signatureRelationshipTransformGuards'][0]['relationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero']
        || ($summary['signatureRelationshipTransformGuards'][0]['relationshipCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransformGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureRelationshipTransformGuards'][0]['issues'] ?? null) !== [
            'unsupported-relationship-transform-selector-attribute',
            'unsupported-relationship-transform-selector-child',
            'unsupported-relationship-transform-selector-content',
        ]
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
