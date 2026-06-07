<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcMarkupCompatibility;
use PortLibs\Pandoc\OpcPackagePath;
use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review:Audit review:Note" mc:PreserveAttributes="review:source review:origin" review:source="import-preflight">
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
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>
  <Override PartName="/docProps/thumbnail.png" ContentType="image/png"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-selector-shape.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-missing-rels.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-fragment.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-dot-segments.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-reference-uri-kinds.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-unsafe-reference.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review:Audit review:Trace" mc:PreserveAttributes="review:source review:label" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml" review:label="main">
    <review:Trace value="ignored"/>
  </Relationship>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdExtended" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  <Relationship Id="rIdCustomProperties" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>
  <Relationship Id="rIdThumbnail" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumbnail.png"/>
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
  <ds:KeyInfo>
    <ds:X509Data>
      <ds:X509Certificate>SGVsbG8gc2lnbmVyIGNlcnQ=</ds:X509Certificate>
    </ds:X509Data>
  </ds:KeyInfo>
  <ds:Object Id="idPackageSignatureObject" MimeType="text/xml">
    <ds:SignatureProperties>
      <ds:SignatureProperty Target="#idPackageSignature">
        <mdssi:SignatureTime>
          <mdssi:Format>YYYY-MM-DDThh:mm:ssTZD</mdssi:Format>
          <mdssi:Value>2026-06-06T22:33:48Z</mdssi:Value>
        </mdssi:SignatureTime>
      </ds:SignatureProperty>
    </ds:SignatureProperties>
  </ds:Object>
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

$missingRelationshipPartSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/missing-comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdMissingCommentImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$fragmentReferenceSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml#fragment">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$dotSegmentReferenceSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/./_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$referenceUriKindSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="#local-relationship-transform">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="https://example.test/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="//example.test/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$unsafeReferenceSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document%ZZ.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document%2Fhidden.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document%5Chidden.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document%00hidden.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/%2E%2E/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/raw space.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="../word/_rels/trailing./document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="../../evil/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
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

$embeddedWorkbookBytes = ZipPackage::build([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdWorkbook" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="xl/workbook.xml"/></Relationships>'],
    ['name' => 'xl/workbook.xml', 'data' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
    ['name' => 'xl/_rels/workbook.xml.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>'],
    ['name' => 'xl/worksheets/sheet1.xml', 'data' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
]);

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
    ['name' => 'word/embeddings/source workbook.xlsx', 'data' => $embeddedWorkbookBytes],
    ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
    ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
    ['name' => 'docProps/app.xml', 'data' => '<Properties/>'],
    ['name' => 'docProps/custom.xml', 'data' => '<Properties/>'],
    ['name' => 'docProps/thumbnail.png', 'data' => 'PNG'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
    ['name' => '_xmlsignatures/sig1.xml', 'data' => $signatureXml],
    ['name' => '_xmlsignatures/sig-selector-shape.xml', 'data' => $selectorShapeSignatureXml],
    ['name' => '_xmlsignatures/sig-missing-rels.xml', 'data' => $missingRelationshipPartSignatureXml],
    ['name' => '_xmlsignatures/sig-fragment.xml', 'data' => $fragmentReferenceSignatureXml],
    ['name' => '_xmlsignatures/sig-dot-segments.xml', 'data' => $dotSegmentReferenceSignatureXml],
    ['name' => '_xmlsignatures/sig-reference-uri-kinds.xml', 'data' => $referenceUriKindSignatureXml],
    ['name' => '_xmlsignatures/sig-unsafe-reference.xml', 'data' => $unsafeReferenceSignatureXml],
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

$targetModeDiagnosticContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;

$targetModeDiagnosticRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdTargetModeAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/targetmode.xml"/>
</Relationships>
XML;

$targetModeDiagnosticRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLowerExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="external"/>
</Relationships>
XML;

$relationshipTargetModePackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $targetModeDiagnosticContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $targetModeDiagnosticRootRelationshipsXml],
    ['name' => 'word/targetmode.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/targetmode.xml.rels', 'data' => $targetModeDiagnosticRelationshipsXml],
]);

$relationshipRecordShapeContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;

$relationshipRecordShapeRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMissingIdAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/missing-id.xml"/>
  <Relationship Id="rIdMissingTypeAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/missing-type.xml"/>
  <Relationship Id="rIdMissingTargetAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/missing-target.xml"/>
  <Relationship Id="rIdInvalidIdAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/invalid-id.xml"/>
  <Relationship Id="rIdDuplicateIdAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/duplicate-id.xml"/>
</Relationships>
XML;

$relationshipRecordMissingIdXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML;

$relationshipRecordMissingTypeXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Target="media/review.png"/>
</Relationships>
XML;

$relationshipRecordMissingTargetXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"/>
</Relationships>
XML;

$relationshipRecordInvalidIdXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="1bad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML;

$relationshipRecordDuplicateIdXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-a.png"/>
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-b.png"/>
</Relationships>
XML;

$relationshipRecordShapePackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $relationshipRecordShapeContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $relationshipRecordShapeRootRelationshipsXml],
    ['name' => 'word/missing-id.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/missing-id.xml.rels', 'data' => $relationshipRecordMissingIdXml],
    ['name' => 'word/missing-type.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/missing-type.xml.rels', 'data' => $relationshipRecordMissingTypeXml],
    ['name' => 'word/missing-target.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/missing-target.xml.rels', 'data' => $relationshipRecordMissingTargetXml],
    ['name' => 'word/invalid-id.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/invalid-id.xml.rels', 'data' => $relationshipRecordInvalidIdXml],
    ['name' => 'word/duplicate-id.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/duplicate-id.xml.rels', 'data' => $relationshipRecordDuplicateIdXml],
]);

$fixedContentTypesItemContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/[Content_Types].xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$fixedContentTypesItemRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdContentTypes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="[Content_Types].xml"/>
</Relationships>
XML;

$fixedContentTypesItemRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdContentTypeAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/document.xml"/>
</Relationships>
XML;

$fixedContentTypesItemPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $fixedContentTypesItemContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $fixedContentTypesItemRootRelationshipsXml],
    ['name' => '_rels/[Content_Types].xml.rels', 'data' => $fixedContentTypesItemRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
]);

$reservedRelationshipContentTypeContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/reserved.bin" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
</Types>
XML;

$reservedRelationshipContentTypeRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$reservedRelationshipContentTypeRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDefaultRels" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/default.rels"/>
  <Relationship Id="rIdReservedOverride" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/reserved.bin"/>
</Relationships>
XML;

$reservedRelationshipContentTypePackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $reservedRelationshipContentTypeContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $reservedRelationshipContentTypeRootRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $reservedRelationshipContentTypeRelationshipsXml],
    ['name' => 'word/media/default.rels', 'data' => 'not relationship xml'],
    ['name' => 'word/media/reserved.bin', 'data' => 'not relationship xml'],
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
  <Override PartName="/_xmlsignatures/sig-case-equivalent.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
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

$caseEquivalentSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdStyles"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/Word/_rels/Document.XML.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdStyles"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$caseEquivalentTargetPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $caseEquivalentTargetContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $caseEquivalentTargetRootRelationshipsXml],
    ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'Word/_rels/Document.XML.rels', 'data' => $caseEquivalentTargetDocumentRelationshipsXml],
    ['name' => 'Word/Styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => '_xmlsignatures/sig-case-equivalent.xml', 'data' => $caseEquivalentSignatureXml],
]);
$caseEquivalentTargetRelationships = OpcRelationships::fromPackage($caseEquivalentTargetPackage, '/word/document.xml');
$caseEquivalentTargetGraph = OpcRelationshipGraph::fromPackage($caseEquivalentTargetPackage);
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
    'directLoaderHasLowercaseSource' => OpcRelationships::packageHasRelationshipsForSource($caseEquivalentTargetPackage, '/word/document.xml'),
    'directLoaderRelationshipPartName' => $caseEquivalentTargetRelationships->relationshipPartName(),
    'directLoaderStylesTarget' => $caseEquivalentTargetRelationships->resolveTarget('rIdStyles'),
    'relationshipPartName' => $caseEquivalentTargetGraph->requireRelationshipsForSource('/word/document.xml')->relationshipPartName(),
    'officeDocumentRootTargetPart' => $caseEquivalentTargetRoot['relationships'][0]['targetPart'] ?? null,
    'officeDocumentRootExists' => $caseEquivalentTargetRoot['relationships'][0]['exists'] ?? null,
    'officeDocumentRootValid' => $caseEquivalentTargetRoot['valid'],
    'closure' => $caseEquivalentTargetClosure,
];
$caseEquivalentSignatureTransforms = [];
foreach ($caseEquivalentTargetGraph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-case-equivalent.xml') as $transform) {
    $caseEquivalentSignatureTransforms[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
        'referenceContentType' => $transform['referenceContentType'],
        'referenceContentTypeMatches' => $transform['referenceContentTypeMatches'],
        'source' => $transform['source'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXml' => $transform['relationshipXml'],
    ];
}

$roleCaseContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="Application/Vnd.Openxmlformats-Package.Relationships+Xml"/>
  <Default Extension="xml" ContentType="Application/Xml"/>
  <Default Extension="png" ContentType="Image/Png"/>
  <Override PartName="/word/document.xml" ContentType="Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml"/>
  <Override PartName="/docProps/core.xml" ContentType="Application/Vnd.Openxmlformats-Package.Core-Properties+Xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="Application/Vnd.Openxmlformats-Package.Digital-Signature-Origin"/>
  <Override PartName="/_xmlsignatures/sig-case.xml" ContentType="Application/Vnd.Openxmlformats-Package.Digital-Signature-XmlSignature+Xml"/>
</Types>
XML;

$roleCasePackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

$roleCaseDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Hero.PNG"/>
</Relationships>
XML;

$roleCaseOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureCase" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-case.xml"/>
</Relationships>
XML;

$roleCaseSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=Application/Vnd.OpenXMLFormats-Package.Relationships+XML">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$roleCasePackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $roleCaseContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $roleCasePackageRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $roleCaseDocumentRelationshipsXml],
    ['name' => 'word/media/Hero.PNG', 'data' => 'PNG'],
    ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $roleCaseOriginRelationshipsXml],
    ['name' => '_xmlsignatures/sig-case.xml', 'data' => $roleCaseSignatureXml],
]);
$roleCaseLoads = [];
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($roleCasePackage) as $part) {
    $roleCaseLoads[$part['partName']] = $part;
}
$roleCaseGraph = OpcRelationshipGraph::fromPackage($roleCasePackage);
$roleCaseOfficeDocumentRoot = $roleCaseGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
$roleCaseCoreProperties = $roleCaseGraph->preflightCoreProperties();
$roleCaseDigitalSignatures = $roleCaseGraph->preflightDigitalSignatures();
$roleCaseSignatureTransform = $roleCaseGraph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-case.xml')[0] ?? null;
$roleCaseContentTypeMatch = [
    'sourceParts' => $roleCaseGraph->sourcePartNames(),
    'rootRelationshipPartLoaded' => $roleCaseLoads['/_rels/.rels']['loaded'] ?? null,
    'documentRelationshipPartLoaded' => $roleCaseLoads['/word/_rels/document.xml.rels']['loaded'] ?? null,
    'signatureOriginRelationshipPartLoaded' => $roleCaseLoads['/_xmlsignatures/_rels/origin.sigs.rels']['loaded'] ?? null,
    'officeDocumentValid' => $roleCaseOfficeDocumentRoot['valid'],
    'officeDocumentContentType' => $roleCaseOfficeDocumentRoot['relationships'][0]['contentType'] ?? null,
    'corePropertiesValid' => $roleCaseCoreProperties['valid'],
    'corePropertiesContentType' => $roleCaseCoreProperties['relationships'][0]['contentType'] ?? null,
    'digitalSignatureValid' => $roleCaseDigitalSignatures[0]['valid'] ?? null,
    'digitalSignatureOriginContentType' => $roleCaseDigitalSignatures[0]['contentType'] ?? null,
    'digitalSignatureContentType' => $roleCaseDigitalSignatures[0]['signatures'][0]['contentType'] ?? null,
    'signatureReferenceTargetContentType' => $roleCaseSignatureTransform['referenceTargetContentType'] ?? null,
    'signatureReferenceContentType' => $roleCaseSignatureTransform['referenceContentType'] ?? null,
    'signatureReferenceContentTypeMatches' => $roleCaseSignatureTransform['referenceContentTypeMatches'] ?? null,
    'signatureTransformValid' => $roleCaseSignatureTransform['valid'] ?? null,
    'signatureTransformRelationshipIds' => $roleCaseSignatureTransform['relationshipIds'] ?? null,
];

$internalTargetDiagnosticsContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$internalTargetDiagnosticsRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$internalTargetDiagnosticsDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdAbsoluteUri" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://example.test/review.png"/>
  <Relationship Id="rIdRawSpace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/raw space.png"/>
  <Relationship Id="rIdEncodedSlash" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media%2Fhidden.png"/>
  <Relationship Id="rIdEncodedDotSegment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="media/%2E%2E/styles.xml"/>
</Relationships>
XML;

$internalTargetDiagnosticsGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $internalTargetDiagnosticsContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $internalTargetDiagnosticsRootRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $internalTargetDiagnosticsDocumentRelationshipsXml],
    ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
]));
$internalTargetDiagnostics = [];
foreach ($internalTargetDiagnosticsGraph->preflightTargetsForSource('/word/document.xml') as $target) {
    $internalTargetDiagnostics[$target['id']] = [
        'id' => $target['id'],
        'target' => $target['target'],
        'targetPart' => $target['external'] || in_array('invalid-target', $target['issues'], true)
            ? null
            : OpcPackagePath::stripQueryAndFragment($target['target']),
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

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

$relationshipTargetModeGuards = [];
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($relationshipTargetModePackage) as $part) {
    if ($part['partName'] !== '/word/_rels/targetmode.xml.rels') {
        continue;
    }

    $relationshipTargetModeGuards[$part['partName']] = [
        'partName' => $part['partName'],
        'relationshipSource' => $part['relationshipSource'],
        'sourceExists' => $part['sourceExists'],
        'loaded' => $part['loaded'],
        'loadAction' => $part['loadAction'],
        'loadReason' => $part['loadReason'],
        'relationshipCount' => $part['relationshipCount'],
        'valid' => $part['valid'],
        'issues' => $part['issues'],
        'parseError' => $part['parseError'],
    ];
}

$relationshipRecordShapeGuards = [];
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($relationshipRecordShapePackage) as $part) {
    if (!str_starts_with($part['partName'], '/word/_rels/')) {
        continue;
    }

    $relationshipRecordShapeGuards[$part['partName']] = [
        'partName' => $part['partName'],
        'relationshipSource' => $part['relationshipSource'],
        'sourceExists' => $part['sourceExists'],
        'loaded' => $part['loaded'],
        'loadAction' => $part['loadAction'],
        'loadReason' => $part['loadReason'],
        'relationshipCount' => $part['relationshipCount'],
        'valid' => $part['valid'],
        'issues' => $part['issues'],
        'parseError' => $part['parseError'],
    ];
}

$fixedContentTypesItemGraph = OpcRelationshipGraph::fromPackage($fixedContentTypesItemPackage);
$fixedContentTypesItemConsistency = $fixedContentTypesItemGraph->preflightPackageConsistency();
$fixedContentTypesItemSourceLoad = null;
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($fixedContentTypesItemPackage) as $part) {
    if ($part['partName'] === '/_rels/[Content_Types].xml.rels') {
        $fixedContentTypesItemSourceLoad = $part;
        break;
    }
}
$fixedContentTypesItemOverride = null;
foreach ($fixedContentTypesItemConsistency['contentTypeOverrides'] as $override) {
    if ($override['partName'] === '/[Content_Types].xml') {
        $fixedContentTypesItemOverride = $override;
        break;
    }
}
$fixedContentTypesItemTarget = null;
foreach ($fixedContentTypesItemConsistency['relationshipTargets'] as $target) {
    if ($target['id'] === 'rIdContentTypes') {
        $fixedContentTypesItemTarget = $target;
        break;
    }
}
$fixedContentTypesItemSourcePart = null;
foreach ($fixedContentTypesItemConsistency['packageParts'] as $part) {
    if ($part['partName'] === '/_rels/[Content_Types].xml.rels') {
        $fixedContentTypesItemSourcePart = $part;
        break;
    }
}
$fixedContentTypesItemGuard = [
    'overridePart' => $fixedContentTypesItemOverride['partName'] ?? null,
    'overrideExists' => $fixedContentTypesItemOverride['exists'] ?? null,
    'overrideValid' => $fixedContentTypesItemOverride['valid'] ?? null,
    'overrideIssues' => $fixedContentTypesItemOverride['issues'] ?? null,
    'targetId' => $fixedContentTypesItemTarget['id'] ?? null,
    'targetPart' => $fixedContentTypesItemTarget['targetPart'] ?? null,
    'targetExists' => $fixedContentTypesItemTarget['exists'] ?? null,
    'targetContentType' => $fixedContentTypesItemTarget['contentType'] ?? null,
    'targetValid' => $fixedContentTypesItemTarget['valid'] ?? null,
    'targetIssues' => $fixedContentTypesItemTarget['issues'] ?? null,
    'sourceRelationshipPart' => $fixedContentTypesItemSourceLoad['partName'] ?? null,
    'sourceRelationshipSource' => $fixedContentTypesItemSourceLoad['relationshipSource'] ?? null,
    'sourceExists' => $fixedContentTypesItemSourceLoad['sourceExists'] ?? null,
    'sourceLoaded' => $fixedContentTypesItemSourceLoad['loaded'] ?? null,
    'sourceLoadReason' => $fixedContentTypesItemSourceLoad['loadReason'] ?? null,
    'sourceRelationshipCount' => $fixedContentTypesItemSourceLoad['relationshipCount'] ?? null,
    'sourceIssues' => $fixedContentTypesItemSourceLoad['issues'] ?? null,
    'packagePartSourceLoaded' => $fixedContentTypesItemSourcePart['relationshipSourceLoaded'] ?? null,
    'packagePartLoadAction' => $fixedContentTypesItemSourcePart['relationshipPartLoadAction'] ?? null,
    'packagePartLoadReason' => $fixedContentTypesItemSourcePart['relationshipPartLoadReason'] ?? null,
    'packagePartIssues' => $fixedContentTypesItemSourcePart['issues'] ?? null,
    'packageConsistencyValid' => $fixedContentTypesItemConsistency['valid'],
    'packagePartsValid' => $fixedContentTypesItemConsistency['packagePartsValid'],
    'contentTypeOverridesValid' => $fixedContentTypesItemConsistency['contentTypeOverridesValid'],
    'relationshipTargetsValid' => $fixedContentTypesItemConsistency['relationshipTargetsValid'],
];

$reservedRelationshipContentTypeGraph = OpcRelationshipGraph::fromPackage($reservedRelationshipContentTypePackage);
$reservedRelationshipContentTypeConsistency = $reservedRelationshipContentTypeGraph->preflightPackageConsistency();
$reservedRelationshipContentTypeParts = [];
foreach ($reservedRelationshipContentTypeConsistency['packageParts'] as $part) {
    $reservedRelationshipContentTypeParts[$part['partName']] = $part;
}
$reservedRelationshipContentTypeOverrides = [];
foreach ($reservedRelationshipContentTypeConsistency['contentTypeOverrides'] as $override) {
    $reservedRelationshipContentTypeOverrides[$override['partName']] = $override;
}
$reservedRelationshipContentTypeTargets = [];
foreach ($reservedRelationshipContentTypeConsistency['relationshipTargets'] as $target) {
    $reservedRelationshipContentTypeTargets[$target['source'] . ':' . $target['id']] = $target;
}
$reservedRelationshipContentTypeGuard = [
    'defaultPart' => $reservedRelationshipContentTypeParts['/word/media/default.rels']['partName'] ?? null,
    'defaultPartValid' => $reservedRelationshipContentTypeParts['/word/media/default.rels']['valid'] ?? null,
    'defaultPartIssues' => $reservedRelationshipContentTypeParts['/word/media/default.rels']['issues'] ?? null,
    'overridePart' => $reservedRelationshipContentTypeOverrides['/word/media/reserved.bin']['partName'] ?? null,
    'overrideExists' => $reservedRelationshipContentTypeOverrides['/word/media/reserved.bin']['exists'] ?? null,
    'overrideValid' => $reservedRelationshipContentTypeOverrides['/word/media/reserved.bin']['valid'] ?? null,
    'overrideIssues' => $reservedRelationshipContentTypeOverrides['/word/media/reserved.bin']['issues'] ?? null,
    'overridePackagePartIssues' => $reservedRelationshipContentTypeParts['/word/media/reserved.bin']['issues'] ?? null,
    'defaultTargetPart' => $reservedRelationshipContentTypeTargets['/word/document.xml:rIdDefaultRels']['targetPart'] ?? null,
    'defaultTargetValid' => $reservedRelationshipContentTypeTargets['/word/document.xml:rIdDefaultRels']['valid'] ?? null,
    'defaultTargetIssues' => $reservedRelationshipContentTypeTargets['/word/document.xml:rIdDefaultRels']['issues'] ?? null,
    'overrideTargetPart' => $reservedRelationshipContentTypeTargets['/word/document.xml:rIdReservedOverride']['targetPart'] ?? null,
    'overrideTargetValid' => $reservedRelationshipContentTypeTargets['/word/document.xml:rIdReservedOverride']['valid'] ?? null,
    'overrideTargetIssues' => $reservedRelationshipContentTypeTargets['/word/document.xml:rIdReservedOverride']['issues'] ?? null,
    'packageConsistencyValid' => $reservedRelationshipContentTypeConsistency['valid'],
    'packagePartsValid' => $reservedRelationshipContentTypeConsistency['packagePartsValid'],
    'contentTypeOverridesValid' => $reservedRelationshipContentTypeConsistency['contentTypeOverridesValid'],
    'relationshipTargetsValid' => $reservedRelationshipContentTypeConsistency['relationshipTargetsValid'],
];

$relationshipPartLoads = [];
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
    $relationshipPartLoads[$part['partName']] = [
        'partName' => $part['partName'],
        'contentType' => $part['contentType'],
        'relationshipSource' => $part['relationshipSource'],
        'relationshipSourceIsRelationshipPart' => $part['relationshipSourceIsRelationshipPart'],
        'sourceExists' => $part['sourceExists'],
        'loaded' => $part['loaded'],
        'loadAction' => $part['loadAction'],
        'loadReason' => $part['loadReason'],
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
        'relationshipPartLoadAction' => $part['relationshipPartLoadAction'],
        'relationshipPartLoadReason' => $part['relationshipPartLoadReason'],
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

$relationshipSourceInventory = [];
foreach ($graph->relationshipSourceInventory() as $source) {
    $relationshipSourceInventory[$source['source']] = $source;
}

$contentTypeInventory = [];
foreach ($graph->contentTypeInventory() as $contentType) {
    $contentTypeInventory[$contentType['contentType']] = $contentType;
}
$packagePartReferences = [];
foreach ($graph->packagePartReferenceInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $part) {
    $packagePartReferences[$part['partName']] = $part;
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
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
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
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
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
$signatureMissingRelationshipPartGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-missing-rels.xml') as $transform) {
    $signatureMissingRelationshipPartGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
        'referenceTargetContentType' => $transform['referenceTargetContentType'],
        'referenceContentType' => $transform['referenceContentType'],
        'referenceContentTypeMatches' => $transform['referenceContentTypeMatches'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'sourceTypes' => $transform['sourceTypes'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXml' => $transform['relationshipXml'],
    ];
}
$signatureFragmentReferenceGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-fragment.xml') as $transform) {
    $signatureFragmentReferenceGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
        'referenceTargetContentType' => $transform['referenceTargetContentType'],
        'referenceContentType' => $transform['referenceContentType'],
        'referenceContentTypeMatches' => $transform['referenceContentTypeMatches'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXml' => $transform['relationshipXml'],
    ];
}
$signatureDotSegmentReferenceGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-dot-segments.xml') as $transform) {
    $signatureDotSegmentReferenceGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'parseError' => $transform['parseError'],
        'relationshipXml' => $transform['relationshipXml'],
    ];
}
$signatureReferenceUriKindGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-reference-uri-kinds.xml') as $transform) {
    $signatureReferenceUriKindGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
        'referenceTargetContentType' => $transform['referenceTargetContentType'],
        'referenceContentType' => $transform['referenceContentType'],
        'referenceContentTypeMatches' => $transform['referenceContentTypeMatches'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXml' => $transform['relationshipXml'],
    ];
}
$signatureUnsafeReferenceGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-unsafe-reference.xml') as $transform) {
    $signatureUnsafeReferenceGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'referenceRelationshipPartExists' => $transform['referenceRelationshipPartExists'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'parseError' => $transform['parseError'],
        'relationshipXml' => $transform['relationshipXml'],
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
$digitalSignatureMetadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig1.xml');
$embeddedPackages = $graph->preflightEmbeddedPackages($documentPart);
$embeddedPackageGraphs = $graph->preflightEmbeddedPackageGraphs($documentPart);
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
$nestedEmbeddedOfficeDocuments = [];
foreach ($embeddedPackageGraphs as $embeddedPackageGraph) {
    $officeRelationship = $embeddedPackageGraph['nestedOfficeDocument']['relationships'][0] ?? null;
    if (!is_array($officeRelationship)) {
        continue;
    }

    $nestedEmbeddedOfficeDocuments[] = [
        'id' => $embeddedPackageGraph['id'],
        'packagePart' => $embeddedPackageGraph['targetPart'],
        'officeDocumentPart' => $officeRelationship['targetPart'],
        'contentType' => $officeRelationship['contentType'],
        'expanded' => $embeddedPackageGraph['expanded'],
        'valid' => $embeddedPackageGraph['valid'],
        'issues' => $embeddedPackageGraph['issues'],
    ];
}
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

$emptySignatureOriginGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/></Types>'],
    ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/><Relationship Id="rIdSignatureOrigin" Type="' . OpcRelationshipGraph::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE . '" Target="_xmlsignatures/origin.sigs"/></Relationships>'],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>'],
]));
$emptySignatureOrigin = $emptySignatureOriginGraph->preflightDigitalSignatures()[0] ?? null;
$emptySignatureOriginGuard = [
    'id' => $emptySignatureOrigin['id'] ?? null,
    'targetPart' => $emptySignatureOrigin['targetPart'] ?? null,
    'relationshipPartName' => $emptySignatureOrigin['relationshipPartName'] ?? null,
    'signatureCount' => isset($emptySignatureOrigin['signatures']) ? count($emptySignatureOrigin['signatures']) : null,
    'valid' => $emptySignatureOrigin['valid'] ?? null,
    'issues' => $emptySignatureOrigin['issues'] ?? null,
];

$corePropertiesPreflight = $graph->preflightCoreProperties();
$corePropertiesPart = $corePropertiesPreflight['relationships'][0]['targetPart'] ?? null;
$documentPropertiesPreflight = $graph->preflightDocumentProperties();
$documentPropertyParts = [];
foreach ($documentPropertiesPreflight['roles'] as $role) {
    foreach ($role['relationships'] as $relationship) {
        if ($relationship['targetPart'] !== null) {
            $documentPropertyParts[] = $relationship['targetPart'];
        }
    }
}
$documentPropertyParts = array_values(array_unique($documentPropertyParts));
$thumbnailPreflight = [];
foreach ($graph->preflightThumbnails() as $thumbnail) {
    $thumbnailPreflight[$thumbnail['source'] . ':' . $thumbnail['id']] = [
        'source' => $thumbnail['source'],
        'id' => $thumbnail['id'],
        'target' => $thumbnail['target'],
        'targetPart' => $thumbnail['targetPart'],
        'contentType' => $thumbnail['contentType'],
        'external' => $thumbnail['external'],
        'exists' => $thumbnail['exists'],
        'relationshipTypeKind' => $thumbnail['relationshipTypeKind'],
        'relationshipTypeValid' => $thumbnail['relationshipTypeValid'],
        'externalTargetKind' => $thumbnail['externalTargetKind'],
        'externalTargetScheme' => $thumbnail['externalTargetScheme'],
        'valid' => $thumbnail['valid'],
        'issues' => $thumbnail['issues'],
    ];
}
$strictXmlShapeGuards = [
    'contentTypeUnexpectedAttributeRejected' => false,
    'contentTypeDefaultDotExtensionRejected' => false,
    'contentTypeDefaultWhitespaceExtensionRejected' => false,
    'contentTypeOverrideRelativePartNameRejected' => false,
    'contentTypeOverrideDotSegmentRejected' => false,
    'contentTypeOverrideRawSpaceRejected' => false,
    'contentTypeOverrideTrailingDotSegmentRejected' => false,
    'contentTypeRootAttributeRejected' => false,
    'relationshipChildContentRejected' => false,
    'relationshipRootTextRejected' => false,
];
$markupCompatibilityGuards = [
    'ignorableContentTypeExtensionAccepted' => $types->contentTypeForPart('/word/document.xml') === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
    'ignorableRelationshipExtensionAccepted' => $graph->firstTargetOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument') === '/word/document.xml',
    'preserveContentTypeDeclarationsAccepted' => $types->contentTypeForPart('/word/media/source diagram.svg') === 'image/svg+xml; charset=UTF-8',
    'preserveRelationshipDeclarationsAccepted' => $graph->requireRelationshipsForSource('/')->byId('rIdDocument') !== null,
    'undeclaredContentTypeExtensionRejected' => false,
    'undeclaredRelationshipExtensionRejected' => false,
    'unsupportedMarkupCompatibilityAttributeRejected' => false,
    'malformedPreserveDeclarationRejected' => false,
    'coreNamespacePreserveDeclarationRejected' => false,
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
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="x y" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeDefaultWhitespaceExtensionRejected'] = true;
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
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/media/raw source.png" ContentType="image/png"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeOverrideRawSpaceRejected'] = true;
}
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/media/trailing./source.png" ContentType="image/png"/></Types>');
} catch (InvalidArgumentException) {
    $strictXmlShapeGuards['contentTypeOverrideTrailingDotSegmentRejected'] = true;
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
try {
    OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review"><Default Extension="xml" ContentType="application/xml"/></Types>');
} catch (InvalidArgumentException) {
    $markupCompatibilityGuards['malformedPreserveDeclarationRejected'] = true;
}
try {
    OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" xmlns:r="' . OpcRelationships::NAMESPACE_URI . '" mc:Ignorable="review" mc:PreserveAttributes="r:Id"><Relationship Id="rIdBad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>', '/word/document.xml');
} catch (InvalidArgumentException) {
    $markupCompatibilityGuards['coreNamespacePreserveDeclarationRejected'] = true;
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
        'preflight' => $corePropertiesPreflight,
    ],
    'documentPropertiesPreflight' => $documentPropertiesPreflight,
    'thumbnailPreflight' => $thumbnailPreflight,
    'officeDocumentRoot' => $officeDocumentRoot,
    'digitalSignatures' => $digitalSignatures,
    'digitalSignatureMetadata' => $digitalSignatureMetadata,
    'embeddedPackages' => $embeddedPackages,
    'embeddedPackageGraphs' => $embeddedPackageGraphs,
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
    'relationshipSourceInventory' => $relationshipSourceInventory,
    'relationshipTypeInventory' => $relationshipTypeInventory,
    'packagePartReferences' => $packagePartReferences,
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
    'signatureMissingRelationshipPartGuards' => $signatureMissingRelationshipPartGuards,
    'signatureFragmentReferenceGuards' => $signatureFragmentReferenceGuards,
    'signatureDotSegmentReferenceGuards' => $signatureDotSegmentReferenceGuards,
    'signatureReferenceUriKindGuards' => $signatureReferenceUriKindGuards,
    'signatureUnsafeReferenceGuards' => $signatureUnsafeReferenceGuards,
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
        'caseEquivalentSignatureTransforms' => $caseEquivalentSignatureTransforms,
        'caseInsensitiveRoleContentTypes' => $roleCaseContentTypeMatch,
        'internalTargetDiagnostics' => $internalTargetDiagnostics,
        'emptySignatureOriginGuard' => $emptySignatureOriginGuard,
    ],
    'relationshipSourceAliasGuards' => $relationshipSourceAliasGuards,
    'relationshipTargetModeGuards' => $relationshipTargetModeGuards,
    'relationshipRecordShapeGuards' => $relationshipRecordShapeGuards,
    'fixedContentTypesItemGuard' => $fixedContentTypesItemGuard,
    'reservedRelationshipContentTypeGuard' => $reservedRelationshipContentTypeGuard,
    'partNameCaseCollisionGuards' => $partNameCaseCollisionGuards,
    'contentTypeInventory' => $contentTypeInventory,
    'wordpressImport' => [
        'documentPropertyParts' => $documentPropertyParts,
        'thumbnailParts' => array_values(array_unique(array_filter(
            array_map(static fn (array $thumbnail): ?string => $thumbnail['targetPart'], $thumbnailPreflight),
            static fn (?string $target): bool => $target !== null
        ))),
        'mediaParts' => array_values(array_unique(array_filter(
            array_map(static fn (array $target): ?string => $target['targetPart'], $reachableTargets),
            static fn (?string $target): bool => $target !== null && str_starts_with($target, '/word/media/')
        ))),
        'relationshipSourceReview' => array_values(array_map(
            static fn (array $source): array => [
                'source' => $source['source'],
                'relationshipPartName' => $source['relationshipPartName'],
                'relationshipCount' => $source['relationshipCount'],
                'invalidTargetCount' => $source['invalidTargetCount'],
                'externalTargets' => $source['externalTargets'],
                'missingTargetParts' => $source['missingTargetParts'],
                'valid' => $source['valid'],
                'issues' => $source['issues'],
            ],
            $relationshipSourceInventory
        )),
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
        'digitalSignatureCertificateCount' => $digitalSignatureMetadata['certificateCount'],
        'digitalSignatureTime' => $digitalSignatureMetadata['objects'][0]['signatureTimeValue'] ?? null,
        'embeddedPackageParts' => $embeddedPackageParts,
        'embeddedObjectParts' => $embeddedObjectParts,
        'nestedEmbeddedOfficeDocuments' => $nestedEmbeddedOfficeDocuments,
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
        'mediaReferenceProvenance' => array_values(array_map(
            static fn (array $part): array => [
                'partName' => $part['partName'],
                'contentType' => $part['contentType'],
                'directReferenceCount' => $part['directReferenceCount'],
                'reachableReferenceCount' => $part['reachableReferenceCount'],
                'directReferences' => array_values(array_map(
                    static fn (array $reference): array => [
                        'source' => $reference['source'],
                        'id' => $reference['id'],
                        'type' => $reference['type'],
                        'valid' => $reference['valid'],
                        'issues' => $reference['issues'],
                    ],
                    $part['directReferences']
                )),
            ],
            array_filter($packagePartReferences, static fn (array $part): bool => str_starts_with($part['partName'], '/word/media/')
                && $part['directReferenceCount'] > 0)
        )),
        'unreferencedMediaParts' => array_values(array_map(
            static fn (array $part): string => $part['partName'],
            array_filter($packagePartReferences, static fn (array $part): bool => str_starts_with($part['partName'], '/word/media/')
                && $part['exists'] === true
                && $part['directReferenceCount'] === 0)
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
        '/word/_rels/document.xml.rels',
        14,
        2,
        'external-target-unsafe-scheme',
        'relationship-type-not-absolute-uri',
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
        $summary['relationshipSourceInventory']['/word/document.xml']['relationshipPartName'] ?? null,
        $summary['relationshipSourceInventory']['/word/document.xml']['relationshipCount'] ?? null,
        $summary['relationshipSourceInventory']['/word/document.xml']['invalidTargetCount'] ?? null,
        $summary['relationshipSourceInventory']['/word/document.xml']['issues'][0] ?? null,
        $summary['relationshipSourceInventory']['/word/document.xml']['issues'][1] ?? null,
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
        || ($summary['embeddedPackageGraphs'][0]['id'] ?? null) !== 'rIdEmbeddedWorkbook'
        || ($summary['embeddedPackageGraphs'][0]['targetPart'] ?? null) !== '/word/embeddings/source workbook.xlsx'
        || ($summary['embeddedPackageGraphs'][0]['expanded'] ?? null) !== true
        || ($summary['embeddedPackageGraphs'][0]['nestedPackagePartCount'] ?? null) !== 5
        || ($summary['embeddedPackageGraphs'][0]['nestedSourcePartNames'] ?? null) !== ['/', '/xl/workbook.xml']
        || ($summary['embeddedPackageGraphs'][0]['nestedOfficeDocument']['relationships'][0]['targetPart'] ?? null) !== '/xl/workbook.xml'
        || ($summary['embeddedPackageGraphs'][0]['nestedOfficeDocument']['relationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'
        || ($summary['embeddedPackageGraphs'][0]['valid'] ?? null) !== true
        || ($summary['embeddedPackageGraphs'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['id'] ?? null) !== 'rIdEmbeddedWorkbook'
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['packagePart'] ?? null) !== '/word/embeddings/source workbook.xlsx'
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['officeDocumentPart'] ?? null) !== '/xl/workbook.xml'
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'
        || ($summary['officeDocumentRoot']['relationshipCount'] ?? null) !== 1
        || ($summary['officeDocumentRoot']['valid'] ?? null) !== true
        || ($summary['officeDocumentRoot']['issues'] ?? null) !== []
        || ($summary['officeDocumentRoot']['relationships'][0]['id'] ?? null) !== 'rIdDocument'
        || ($summary['officeDocumentRoot']['relationships'][0]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['officeDocumentRoot']['relationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['coreProperties']['preflight']['relationshipCount'] ?? null) !== 1
        || ($summary['coreProperties']['preflight']['valid'] ?? null) !== true
        || ($summary['coreProperties']['preflight']['issues'] ?? null) !== []
        || ($summary['coreProperties']['preflight']['relationships'][0]['id'] ?? null) !== 'rIdCore'
        || ($summary['coreProperties']['preflight']['relationships'][0]['targetPart'] ?? null) !== '/docProps/core.xml'
        || ($summary['coreProperties']['preflight']['relationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.core-properties+xml'
        || ($summary['coreProperties']['preflight']['relationships'][0]['valid'] ?? null) !== true
        || ($summary['coreProperties']['preflight']['relationships'][0]['issues'] ?? null) !== []
        || ($summary['documentPropertiesPreflight']['valid'] ?? null) !== true
        || array_keys($summary['documentPropertiesPreflight']['roles'] ?? []) !== ['core', 'extended', 'custom']
        || ($summary['documentPropertiesPreflight']['roles']['core']['relationshipCount'] ?? null) !== 1
        || ($summary['documentPropertiesPreflight']['roles']['core']['relationships'][0]['targetPart'] ?? null) !== '/docProps/core.xml'
        || ($summary['documentPropertiesPreflight']['roles']['extended']['relationshipCount'] ?? null) !== 1
        || ($summary['documentPropertiesPreflight']['roles']['extended']['relationships'][0]['id'] ?? null) !== 'rIdExtended'
        || ($summary['documentPropertiesPreflight']['roles']['extended']['relationships'][0]['targetPart'] ?? null) !== '/docProps/app.xml'
        || ($summary['documentPropertiesPreflight']['roles']['extended']['relationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.extended-properties+xml'
        || ($summary['documentPropertiesPreflight']['roles']['extended']['relationships'][0]['valid'] ?? null) !== true
        || ($summary['documentPropertiesPreflight']['roles']['extended']['relationships'][0]['issues'] ?? null) !== []
        || ($summary['documentPropertiesPreflight']['roles']['custom']['relationshipCount'] ?? null) !== 1
        || ($summary['documentPropertiesPreflight']['roles']['custom']['relationships'][0]['id'] ?? null) !== 'rIdCustomProperties'
        || ($summary['documentPropertiesPreflight']['roles']['custom']['relationships'][0]['targetPart'] ?? null) !== '/docProps/custom.xml'
        || ($summary['documentPropertiesPreflight']['roles']['custom']['relationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.custom-properties+xml'
        || ($summary['documentPropertiesPreflight']['roles']['custom']['relationships'][0]['valid'] ?? null) !== true
        || ($summary['documentPropertiesPreflight']['roles']['custom']['relationships'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['documentPropertyParts'] ?? null) !== ['/docProps/core.xml', '/docProps/app.xml', '/docProps/custom.xml']
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['source'] ?? null) !== '/'
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['targetPart'] ?? null) !== '/docProps/thumbnail.png'
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['contentType'] ?? null) !== 'image/png'
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['relationshipTypeKind'] ?? null) !== 'absolute-uri'
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['external'] ?? null) !== false
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['exists'] ?? null) !== true
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['valid'] ?? null) !== true
        || ($summary['thumbnailPreflight']['/:rIdThumbnail']['issues'] ?? null) !== []
        || ($summary['wordpressImport']['thumbnailParts'] ?? null) !== ['/docProps/thumbnail.png']
        || ($summary['digitalSignatures'][0]['relationshipPartName'] ?? null) !== '/_xmlsignatures/_rels/origin.sigs.rels'
        || ($summary['digitalSignatures'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.digital-signature-origin'
        || ($summary['digitalSignatures'][0]['signatures'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml'
        || ($summary['digitalSignatures'][0]['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['signaturePart'] ?? null) !== '/_xmlsignatures/sig1.xml'
        || ($summary['digitalSignatureMetadata']['objectCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['certificateCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['issues'] ?? null) !== []
        || ($summary['digitalSignatureMetadata']['objects'][0]['id'] ?? null) !== 'idPackageSignatureObject'
        || ($summary['digitalSignatureMetadata']['objects'][0]['signatureTimeValue'] ?? null) !== '2026-06-06T22:33:48Z'
        || ($summary['digitalSignatureMetadata']['objects'][0]['signatureTimeValid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['packageSignatureElements'] ?? null) !== ['SignatureTime', 'Format', 'Value']
        || ($summary['digitalSignatureMetadata']['certificates'][0]['decodedBytes'] ?? null) !== 17
        || ($summary['digitalSignatureMetadata']['certificates'][0]['sha256'] ?? null) !== '339af39211d5f1a9de3c16e229830accd22d7063980248a5ea57edf61cac6c6d'
        || ($summary['digitalSignatureMetadata']['certificates'][0]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureCertificateCount'] ?? null) !== 1
        || ($summary['wordpressImport']['digitalSignatureTime'] ?? null) !== '2026-06-06T22:33:48Z'
        || ($summary['integrity']['emptySignatureOriginGuard']['id'] ?? null) !== 'rIdSignatureOrigin'
        || ($summary['integrity']['emptySignatureOriginGuard']['targetPart'] ?? null) !== '/_xmlsignatures/origin.sigs'
        || ($summary['integrity']['emptySignatureOriginGuard']['relationshipPartName'] ?? null) !== '/_xmlsignatures/_rels/origin.sigs.rels'
        || ($summary['integrity']['emptySignatureOriginGuard']['signatureCount'] ?? null) !== 0
        || ($summary['integrity']['emptySignatureOriginGuard']['valid'] ?? null) !== false
        || ($summary['integrity']['emptySignatureOriginGuard']['issues'] ?? null) !== ['missing-digital-signature-signature-relationships']
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
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['targetPart'] ?? null) !== '/docProps/thumbnail.png'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['contentType'] ?? null) !== 'image/png'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['issues'] ?? null) !== []
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdSignatureOrigin']['targetPart'] ?? null) !== '/_xmlsignatures/origin.sigs'
        || ($summary['packageConsistency']['relationshipTargets']['/word/review source.xml:rIdReviewSourceImage']['targetPart'] ?? null) !== '/word/media/review source.png'
        || isset($summary['packageConsistency']['relationshipTargets']['/word/draft.xml:rIdDraftImage'])
        || $summary['integrity']['packagePartsValid'] !== false
        || $summary['relationshipSources'] !== ['/', '/_xmlsignatures/origin.sigs', '/word/document.xml', '/word/footnotes.xml', '/word/review source.xml']
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['relationshipSource'] ?? null) !== '/word/review source.xml'
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['relationshipSourceLoaded'] ?? null) !== true
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['relationshipPartLoadAction'] ?? null) !== 'loaded'
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['relationshipPartLoadReason'] ?? null) !== 'loaded'
        || ($summary['packageParts']['/word/_rels/review%20source.xml.rels']['issues'] ?? null) !== []
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipSource'] ?? null) !== '/word/draft.xml'
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipPartLoadAction'] ?? null) !== 'skipped'
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['relationshipPartLoadReason'] ?? null) !== 'invalid-relationship-content-type'
        || ($summary['packageParts']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['relationshipPartLoads']['/_rels/.rels']['relationshipSource'] ?? null) !== '/'
        || ($summary['relationshipPartLoads']['/_rels/.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/_rels/.rels']['loadAction'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/_rels/.rels']['loadReason'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/_rels/.rels']['relationshipCount'] ?? null) !== 6
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['relationshipSource'] ?? null) !== '/word/document.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loadAction'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loadReason'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['relationshipCount'] ?? null) !== 14
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['relationshipSource'] ?? null) !== '/word/review source.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['sourceExists'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['loadAction'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/word/_rels/review%20source.xml.rels']['loadReason'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['relationshipSource'] ?? null) !== '/word/draft.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['loaded'] ?? null) !== false
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['loadAction'] ?? null) !== 'skipped'
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['loadReason'] ?? null) !== 'invalid-relationship-content-type'
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['relationshipCount'] ?? null) !== null
        || ($summary['relationshipPartLoads']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['relationshipPartLoads']['/_xmlsignatures/_rels/origin.sigs.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/_xmlsignatures/_rels/origin.sigs.rels']['loadAction'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/_xmlsignatures/_rels/origin.sigs.rels']['loadReason'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/_xmlsignatures/_rels/origin.sigs.rels']['relationshipCount'] ?? null) !== 1
        || ($summary['integrity']['invalidRelationshipParts'][0]['partName'] ?? null) !== '/word/_rels/draft.xml.rels'
        || ($summary['integrity']['invalidRelationshipParts'][0]['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['integrity']['invalidRelationshipParts'][0]['relationshipPartLoadAction'] ?? null) !== 'skipped'
        || ($summary['integrity']['invalidRelationshipParts'][0]['relationshipPartLoadReason'] ?? null) !== 'invalid-relationship-content-type'
        || ($summary['integrity']['relationshipSourceAliasGraphRejected'] ?? null) !== true
        || ($summary['integrity']['partNameCaseCollisionGraphRejected'] ?? null) !== true
        || ($summary['integrity']['contentTypeOverrideCaseLookup'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['integrity']['contentTypeOverrideDuplicateRejected'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['officeDocumentPart'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentTargets']['lowercaseSourceRelationshipsLoaded'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['directLoaderHasLowercaseSource'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentTargets']['directLoaderRelationshipPartName'] ?? null) !== '/Word/_rels/Document.XML.rels'
        || ($summary['integrity']['caseEquivalentTargets']['directLoaderStylesTarget'] ?? null) !== '/Word/styles.xml'
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
        || count($summary['integrity']['caseEquivalentSignatureTransforms'] ?? []) !== 2
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['relationshipPartName'] ?? null) !== '/Word/_rels/Document.XML.rels'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['referenceRelationshipPartExists'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['source'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['relationshipIds'] ?? null) !== ['rIdStyles']
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['valid'] ?? null) !== false
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['issues'] ?? null) !== ['multiple-relationship-transforms-for-part']
        || !str_contains((string) ($summary['integrity']['caseEquivalentSignatureTransforms'][0]['relationshipXml'] ?? ''), 'Target="styles.xml"')
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['referenceUri'] ?? null) !== '/Word/_rels/Document.XML.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['relationshipPartName'] ?? null) !== '/Word/_rels/Document.XML.rels'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['referenceContentTypeMatches'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['source'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['relationshipIds'] ?? null) !== ['rIdStyles']
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['valid'] ?? null) !== false
        || ($summary['integrity']['caseEquivalentSignatureTransforms'][1]['issues'] ?? null) !== ['multiple-relationship-transforms-for-part']
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['sourceParts'] ?? null) !== ['/', '/_xmlsignatures/origin.sigs', '/word/document.xml']
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['rootRelationshipPartLoaded'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['documentRelationshipPartLoaded'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureOriginRelationshipPartLoaded'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['officeDocumentValid'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['officeDocumentContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['corePropertiesValid'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['corePropertiesContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Core-Properties+Xml'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['digitalSignatureValid'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['digitalSignatureOriginContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Digital-Signature-Origin'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['digitalSignatureContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Digital-Signature-XmlSignature+Xml'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureReferenceTargetContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Relationships+Xml'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureReferenceContentType'] ?? null) !== 'Application/Vnd.OpenXMLFormats-Package.Relationships+XML'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureReferenceContentTypeMatches'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureTransformValid'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureTransformRelationshipIds'] ?? null) !== ['rIdHero']
        || ($summary['integrity']['internalTargetDiagnostics']['rIdAbsoluteUri']['targetPart'] ?? null) !== null
        || ($summary['integrity']['internalTargetDiagnostics']['rIdAbsoluteUri']['valid'] ?? null) !== false
        || ($summary['integrity']['internalTargetDiagnostics']['rIdAbsoluteUri']['issues'] ?? null) !== ['invalid-target', 'internal-target-absolute-uri']
        || ($summary['integrity']['internalTargetDiagnostics']['rIdRawSpace']['targetPart'] ?? null) !== null
        || ($summary['integrity']['internalTargetDiagnostics']['rIdRawSpace']['valid'] ?? null) !== false
        || ($summary['integrity']['internalTargetDiagnostics']['rIdRawSpace']['issues'] ?? null) !== ['invalid-target', 'internal-target-invalid-uri-byte']
        || ($summary['integrity']['internalTargetDiagnostics']['rIdEncodedSlash']['targetPart'] ?? null) !== null
        || ($summary['integrity']['internalTargetDiagnostics']['rIdEncodedSlash']['valid'] ?? null) !== false
        || ($summary['integrity']['internalTargetDiagnostics']['rIdEncodedSlash']['issues'] ?? null) !== ['invalid-target', 'internal-target-unsafe-percent-encoded-path-byte']
        || ($summary['integrity']['internalTargetDiagnostics']['rIdEncodedDotSegment']['targetPart'] ?? null) !== null
        || ($summary['integrity']['internalTargetDiagnostics']['rIdEncodedDotSegment']['valid'] ?? null) !== false
        || ($summary['integrity']['internalTargetDiagnostics']['rIdEncodedDotSegment']['issues'] ?? null) !== ['invalid-target', 'internal-target-unsafe-percent-encoded-dot-segment']
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
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['relationshipSource'] ?? null) !== '/word/targetmode.xml'
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['sourceExists'] ?? null) !== true
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['loaded'] ?? null) !== false
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['loadAction'] ?? null) !== 'skipped'
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['loadReason'] ?? null) !== 'malformed-relationship-xml'
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['relationshipCount'] ?? null) !== null
        || ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['issues'] ?? null) !== ['malformed-relationship-xml', 'invalid-relationship-target-mode']
        || !str_contains((string) ($summary['relationshipTargetModeGuards']['/word/_rels/targetmode.xml.rels']['parseError'] ?? ''), 'Unsupported OPC relationship TargetMode: external')
        || array_keys($summary['relationshipRecordShapeGuards'] ?? []) !== [
            '/word/_rels/missing-id.xml.rels',
            '/word/_rels/missing-type.xml.rels',
            '/word/_rels/missing-target.xml.rels',
            '/word/_rels/invalid-id.xml.rels',
            '/word/_rels/duplicate-id.xml.rels',
        ]
        || ($summary['relationshipRecordShapeGuards']['/word/_rels/missing-id.xml.rels']['issues'] ?? null) !== ['malformed-relationship-xml', 'missing-relationship-id']
        || !str_contains((string) ($summary['relationshipRecordShapeGuards']['/word/_rels/missing-id.xml.rels']['parseError'] ?? ''), 'missing required Id attribute')
        || ($summary['relationshipRecordShapeGuards']['/word/_rels/missing-type.xml.rels']['issues'] ?? null) !== ['malformed-relationship-xml', 'missing-relationship-type']
        || !str_contains((string) ($summary['relationshipRecordShapeGuards']['/word/_rels/missing-type.xml.rels']['parseError'] ?? ''), 'missing required Type attribute')
        || ($summary['relationshipRecordShapeGuards']['/word/_rels/missing-target.xml.rels']['issues'] ?? null) !== ['malformed-relationship-xml', 'missing-relationship-target']
        || !str_contains((string) ($summary['relationshipRecordShapeGuards']['/word/_rels/missing-target.xml.rels']['parseError'] ?? ''), 'missing required Target attribute')
        || ($summary['relationshipRecordShapeGuards']['/word/_rels/invalid-id.xml.rels']['issues'] ?? null) !== ['malformed-relationship-xml', 'invalid-relationship-id']
        || !str_contains((string) ($summary['relationshipRecordShapeGuards']['/word/_rels/invalid-id.xml.rels']['parseError'] ?? ''), 'XML NCName-style identifier')
        || ($summary['relationshipRecordShapeGuards']['/word/_rels/duplicate-id.xml.rels']['issues'] ?? null) !== ['malformed-relationship-xml', 'duplicate-relationship-id']
        || !str_contains((string) ($summary['relationshipRecordShapeGuards']['/word/_rels/duplicate-id.xml.rels']['parseError'] ?? ''), 'Duplicate OPC relationship Id: rIdReviewImage')
        || ($summary['fixedContentTypesItemGuard']['overridePart'] ?? null) !== '/[Content_Types].xml'
        || ($summary['fixedContentTypesItemGuard']['overrideExists'] ?? null) !== true
        || ($summary['fixedContentTypesItemGuard']['overrideValid'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['overrideIssues'] ?? null) !== ['content-types-override-target']
        || ($summary['fixedContentTypesItemGuard']['targetId'] ?? null) !== 'rIdContentTypes'
        || ($summary['fixedContentTypesItemGuard']['targetPart'] ?? null) !== '/[Content_Types].xml'
        || ($summary['fixedContentTypesItemGuard']['targetExists'] ?? null) !== true
        || ($summary['fixedContentTypesItemGuard']['targetContentType'] ?? null) !== 'application/xml'
        || ($summary['fixedContentTypesItemGuard']['targetValid'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['targetIssues'] ?? null) !== ['targets-content-types-item']
        || ($summary['fixedContentTypesItemGuard']['sourceRelationshipPart'] ?? null) !== '/_rels/[Content_Types].xml.rels'
        || ($summary['fixedContentTypesItemGuard']['sourceRelationshipSource'] ?? null) !== '/[Content_Types].xml'
        || ($summary['fixedContentTypesItemGuard']['sourceExists'] ?? null) !== true
        || ($summary['fixedContentTypesItemGuard']['sourceLoaded'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['sourceLoadReason'] ?? null) !== 'content-types-item-source'
        || ($summary['fixedContentTypesItemGuard']['sourceRelationshipCount'] ?? null) !== null
        || ($summary['fixedContentTypesItemGuard']['sourceIssues'] ?? null) !== ['content-types-item-source']
        || ($summary['fixedContentTypesItemGuard']['packagePartSourceLoaded'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['packagePartLoadAction'] ?? null) !== 'skipped'
        || ($summary['fixedContentTypesItemGuard']['packagePartLoadReason'] ?? null) !== 'content-types-item-source'
        || ($summary['fixedContentTypesItemGuard']['packagePartIssues'] ?? null) !== ['content-types-item-source']
        || ($summary['fixedContentTypesItemGuard']['packageConsistencyValid'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['packagePartsValid'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['contentTypeOverridesValid'] ?? null) !== false
        || ($summary['fixedContentTypesItemGuard']['relationshipTargetsValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['defaultPart'] ?? null) !== '/word/media/default.rels'
        || ($summary['reservedRelationshipContentTypeGuard']['defaultPartValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['defaultPartIssues'] ?? null) !== ['relationship-content-type-on-non-relationship-part']
        || ($summary['reservedRelationshipContentTypeGuard']['overridePart'] ?? null) !== '/word/media/reserved.bin'
        || ($summary['reservedRelationshipContentTypeGuard']['overrideExists'] ?? null) !== true
        || ($summary['reservedRelationshipContentTypeGuard']['overrideValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['overrideIssues'] ?? null) !== ['relationship-content-type-on-non-relationship-part']
        || ($summary['reservedRelationshipContentTypeGuard']['overridePackagePartIssues'] ?? null) !== ['relationship-content-type-on-non-relationship-part']
        || ($summary['reservedRelationshipContentTypeGuard']['defaultTargetPart'] ?? null) !== '/word/media/default.rels'
        || ($summary['reservedRelationshipContentTypeGuard']['defaultTargetValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['defaultTargetIssues'] ?? null) !== ['relationship-content-type-on-non-relationship-part']
        || ($summary['reservedRelationshipContentTypeGuard']['overrideTargetPart'] ?? null) !== '/word/media/reserved.bin'
        || ($summary['reservedRelationshipContentTypeGuard']['overrideTargetValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['overrideTargetIssues'] ?? null) !== ['relationship-content-type-on-non-relationship-part']
        || ($summary['reservedRelationshipContentTypeGuard']['packageConsistencyValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['packagePartsValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['contentTypeOverridesValid'] ?? null) !== false
        || ($summary['reservedRelationshipContentTypeGuard']['relationshipTargetsValid'] ?? null) !== false
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
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeDefaultWhitespaceExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeOverrideRelativePartNameRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeOverrideDotSegmentRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeOverrideRawSpaceRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeOverrideTrailingDotSegmentRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['contentTypeRootAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['relationshipChildContentRejected'] ?? null) !== true
        || ($summary['integrity']['strictXmlShapeGuards']['relationshipRootTextRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['ignorableContentTypeExtensionAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['ignorableRelationshipExtensionAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['preserveContentTypeDeclarationsAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['preserveRelationshipDeclarationsAccepted'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['undeclaredContentTypeExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['undeclaredRelationshipExtensionRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['unsupportedMarkupCompatibilityAttributeRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['malformedPreserveDeclarationRejected'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityGuards']['coreNamespacePreserveDeclarationRejected'] ?? null) !== true
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
        || ($summary['wordpressImport']['relationshipSourceReview'][2]['source'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['relationshipSourceReview'][2]['valid'] ?? null) !== false
        || ($summary['wordpressImport']['relationshipSourceReview'][2]['invalidTargetCount'] ?? null) !== 2
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
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['relationshipCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['sourceCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['targetParts'] ?? null) !== ['/docProps/thumbnail.png']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['contentTypes'] ?? null) !== ['image/png']
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
            '/docProps/thumbnail.png',
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
        || ($summary['packagePartReferences']['/_rels/.rels']['relationshipPart'] ?? null) !== true
        || ($summary['packagePartReferences']['/_rels/.rels']['relationshipSource'] ?? null) !== '/'
        || ($summary['packagePartReferences']['/_rels/.rels']['directReferenceCount'] ?? null) !== 0
        || ($summary['packagePartReferences']['/docProps/core.xml']['directReferences'][0]['source'] ?? null) !== '/'
        || ($summary['packagePartReferences']['/docProps/core.xml']['directReferences'][0]['id'] ?? null) !== 'rIdCore'
        || ($summary['packagePartReferences']['/docProps/core.xml']['reachableReferenceCount'] ?? null) !== 0
        || ($summary['packagePartReferences']['/docProps/thumbnail.png']['directReferences'][0]['source'] ?? null) !== '/'
        || ($summary['packagePartReferences']['/docProps/thumbnail.png']['directReferences'][0]['id'] ?? null) !== 'rIdThumbnail'
        || ($summary['packagePartReferences']['/docProps/thumbnail.png']['reachableReferenceCount'] ?? null) !== 0
        || ($summary['packagePartReferences']['/word/document.xml']['directReferences'][0]['id'] ?? null) !== 'rIdDocument'
        || ($summary['packagePartReferences']['/word/document.xml']['reachableReferences'][0]['depth'] ?? null) !== 0
        || ($summary['packagePartReferences']['/word/media/hero image.PNG']['directReferences'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['packagePartReferences']['/word/media/hero image.PNG']['directReferences'][0]['id'] ?? null) !== 'rIdHero'
        || ($summary['packagePartReferences']['/word/media/hero image.PNG']['reachableReferences'][0]['depth'] ?? null) !== 1
        || ($summary['packagePartReferences']['/word/media/review source.png']['directReferences'][0]['source'] ?? null) !== '/word/review source.xml'
        || ($summary['packagePartReferences']['/word/media/review source.png']['reachableReferences'][0]['depth'] ?? null) !== 2
        || ($summary['packagePartReferences']['/word/media/draft-hidden.png']['directReferenceCount'] ?? null) !== 0
        || ($summary['packagePartReferences']['/word/media/draft-hidden.png']['reachableReferenceCount'] ?? null) !== 0
        || ($summary['wordpressImport']['unreferencedMediaParts'] ?? null) !== ['/word/media/draft-hidden.png']
        || count($summary['wordpressImport']['mediaReferenceProvenance'] ?? []) !== 4
        || ($summary['wordpressImport']['mediaReferenceProvenance'][1]['partName'] ?? null) !== '/word/media/hero image.PNG'
        || ($summary['wordpressImport']['mediaReferenceProvenance'][1]['directReferences'][0]['id'] ?? null) !== 'rIdHero'
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
        || str_contains((string) ($summary['relationshipTransform']['relationshipXml'] ?? ''), 'TargetMode="Internal"')
        || !str_contains((string) ($summary['relationshipTransform']['relationshipXml'] ?? ''), 'TargetMode="External"')
        || str_contains((string) ($summary['relationshipTransform']['relationshipXml'] ?? ''), 'rIdDraftReview')
        || count($summary['signatureRelationshipTransforms'] ?? []) !== 1
        || ($summary['signatureRelationshipTransforms'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig1.xml'
        || ($summary['signatureRelationshipTransforms'][0]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipTransforms'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureRelationshipTransforms'][0]['referenceRelationshipPartExists'] ?? null) !== true
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
        || ($summary['signatureRelationshipTransformGuards'][0]['referenceRelationshipPartExists'] ?? null) !== true
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
        || count($summary['signatureMissingRelationshipPartGuards'] ?? []) !== 1
        || ($summary['signatureMissingRelationshipPartGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-missing-rels.xml'
        || ($summary['signatureMissingRelationshipPartGuards'][0]['referenceUri'] ?? null) !== '/word/_rels/missing-comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureMissingRelationshipPartGuards'][0]['relationshipPartName'] ?? null) !== '/word/_rels/missing-comments.xml.rels'
        || ($summary['signatureMissingRelationshipPartGuards'][0]['referenceRelationshipPartExists'] ?? null) !== false
        || ($summary['signatureMissingRelationshipPartGuards'][0]['referenceTargetContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureMissingRelationshipPartGuards'][0]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureMissingRelationshipPartGuards'][0]['referenceContentTypeMatches'] ?? null) !== true
        || ($summary['signatureMissingRelationshipPartGuards'][0]['source'] ?? null) !== '/word/missing-comments.xml'
        || ($summary['signatureMissingRelationshipPartGuards'][0]['sourceIds'] ?? null) !== ['rIdMissingCommentImage']
        || ($summary['signatureMissingRelationshipPartGuards'][0]['relationshipIds'] ?? null) !== []
        || ($summary['signatureMissingRelationshipPartGuards'][0]['relationshipCount'] ?? null) !== 0
        || ($summary['signatureMissingRelationshipPartGuards'][0]['selectorValid'] ?? null) !== false
        || ($summary['signatureMissingRelationshipPartGuards'][0]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureMissingRelationshipPartGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureMissingRelationshipPartGuards'][0]['issues'] ?? null) !== [
            'reference-relationship-part-missing-in-package',
            'relationship-source-not-loaded',
            'unmatched-source-id',
        ]
        || !array_key_exists('relationshipXml', $summary['signatureMissingRelationshipPartGuards'][0] ?? [])
        || $summary['signatureMissingRelationshipPartGuards'][0]['relationshipXml'] !== null
        || count($summary['signatureFragmentReferenceGuards'] ?? []) !== 1
        || ($summary['signatureFragmentReferenceGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-fragment.xml'
        || ($summary['signatureFragmentReferenceGuards'][0]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml#fragment'
        || ($summary['signatureFragmentReferenceGuards'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureFragmentReferenceGuards'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['signatureFragmentReferenceGuards'][0]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureFragmentReferenceGuards'][0]['relationshipIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureFragmentReferenceGuards'][0]['relationshipCount'] ?? null) !== 1
        || ($summary['signatureFragmentReferenceGuards'][0]['selectorValid'] ?? null) !== true
        || ($summary['signatureFragmentReferenceGuards'][0]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureFragmentReferenceGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureFragmentReferenceGuards'][0]['issues'] ?? null) !== ['relationship-transform-reference-has-fragment']
        || !str_contains((string) ($summary['signatureFragmentReferenceGuards'][0]['relationshipXml'] ?? ''), 'Id="rIdHero"')
        || count($summary['signatureDotSegmentReferenceGuards'] ?? []) !== 1
        || ($summary['signatureDotSegmentReferenceGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-dot-segments.xml'
        || ($summary['signatureDotSegmentReferenceGuards'][0]['referenceUri'] ?? null) !== '/word/./_rels/document.xml.rels'
        || !array_key_exists('relationshipPartName', $summary['signatureDotSegmentReferenceGuards'][0] ?? [])
        || $summary['signatureDotSegmentReferenceGuards'][0]['relationshipPartName'] !== null
        || !array_key_exists('referenceRelationshipPartExists', $summary['signatureDotSegmentReferenceGuards'][0] ?? [])
        || $summary['signatureDotSegmentReferenceGuards'][0]['referenceRelationshipPartExists'] !== null
        || !array_key_exists('source', $summary['signatureDotSegmentReferenceGuards'][0] ?? [])
        || $summary['signatureDotSegmentReferenceGuards'][0]['source'] !== null
        || ($summary['signatureDotSegmentReferenceGuards'][0]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureDotSegmentReferenceGuards'][0]['relationshipIds'] ?? null) !== []
        || ($summary['signatureDotSegmentReferenceGuards'][0]['relationshipCount'] ?? null) !== 0
        || !array_key_exists('selectorValid', $summary['signatureDotSegmentReferenceGuards'][0] ?? [])
        || $summary['signatureDotSegmentReferenceGuards'][0]['selectorValid'] !== null
        || !array_key_exists('relationshipTargetsValid', $summary['signatureDotSegmentReferenceGuards'][0] ?? [])
        || $summary['signatureDotSegmentReferenceGuards'][0]['relationshipTargetsValid'] !== null
        || ($summary['signatureDotSegmentReferenceGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureDotSegmentReferenceGuards'][0]['issues'] ?? null) !== ['relationship-transform-reference-invalid-part-name']
        || !str_contains((string) ($summary['signatureDotSegmentReferenceGuards'][0]['parseError'] ?? ''), 'must not contain empty or dot path segments')
        || !array_key_exists('relationshipXml', $summary['signatureDotSegmentReferenceGuards'][0] ?? [])
        || $summary['signatureDotSegmentReferenceGuards'][0]['relationshipXml'] !== null
        || count($summary['signatureReferenceUriKindGuards'] ?? []) !== 3
        || ($summary['signatureReferenceUriKindGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-reference-uri-kinds.xml'
        || ($summary['signatureReferenceUriKindGuards'][0]['referenceUri'] ?? null) !== '#local-relationship-transform'
        || !array_key_exists('relationshipPartName', $summary['signatureReferenceUriKindGuards'][0] ?? [])
        || $summary['signatureReferenceUriKindGuards'][0]['relationshipPartName'] !== null
        || !array_key_exists('source', $summary['signatureReferenceUriKindGuards'][0] ?? [])
        || $summary['signatureReferenceUriKindGuards'][0]['source'] !== null
        || ($summary['signatureReferenceUriKindGuards'][0]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureReferenceUriKindGuards'][0]['relationshipIds'] ?? null) !== []
        || ($summary['signatureReferenceUriKindGuards'][0]['relationshipCount'] ?? null) !== 0
        || !array_key_exists('selectorValid', $summary['signatureReferenceUriKindGuards'][0] ?? [])
        || $summary['signatureReferenceUriKindGuards'][0]['selectorValid'] !== null
        || ($summary['signatureReferenceUriKindGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureReferenceUriKindGuards'][0]['issues'] ?? null) !== [
            'relationship-transform-reference-same-document',
            'relationship-transform-reference-has-fragment',
        ]
        || !array_key_exists('relationshipXml', $summary['signatureReferenceUriKindGuards'][0] ?? [])
        || $summary['signatureReferenceUriKindGuards'][0]['relationshipXml'] !== null
        || ($summary['signatureReferenceUriKindGuards'][1]['referenceUri'] ?? null) !== 'https://example.test/word/_rels/document.xml.rels'
        || !array_key_exists('relationshipPartName', $summary['signatureReferenceUriKindGuards'][1] ?? [])
        || $summary['signatureReferenceUriKindGuards'][1]['relationshipPartName'] !== null
        || ($summary['signatureReferenceUriKindGuards'][1]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureReferenceUriKindGuards'][1]['relationshipIds'] ?? null) !== []
        || ($summary['signatureReferenceUriKindGuards'][1]['valid'] ?? null) !== false
        || ($summary['signatureReferenceUriKindGuards'][1]['issues'] ?? null) !== ['relationship-transform-reference-external-uri']
        || !array_key_exists('relationshipXml', $summary['signatureReferenceUriKindGuards'][1] ?? [])
        || $summary['signatureReferenceUriKindGuards'][1]['relationshipXml'] !== null
        || ($summary['signatureReferenceUriKindGuards'][2]['referenceUri'] ?? null) !== '//example.test/word/_rels/document.xml.rels'
        || !array_key_exists('relationshipPartName', $summary['signatureReferenceUriKindGuards'][2] ?? [])
        || $summary['signatureReferenceUriKindGuards'][2]['relationshipPartName'] !== null
        || ($summary['signatureReferenceUriKindGuards'][2]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureReferenceUriKindGuards'][2]['relationshipIds'] ?? null) !== []
        || ($summary['signatureReferenceUriKindGuards'][2]['valid'] ?? null) !== false
        || ($summary['signatureReferenceUriKindGuards'][2]['issues'] ?? null) !== ['relationship-transform-reference-external-uri']
        || !array_key_exists('relationshipXml', $summary['signatureReferenceUriKindGuards'][2] ?? [])
        || $summary['signatureReferenceUriKindGuards'][2]['relationshipXml'] !== null
        || count($summary['signatureUnsafeReferenceGuards'] ?? []) !== 8
        || array_column($summary['signatureUnsafeReferenceGuards'] ?? [], 'issues', 'referenceUri') !== [
            '/word/_rels/document%ZZ.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-malformed-percent-escape'],
            '/word/_rels/document%2Fhidden.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-unsafe-percent-encoded-path-byte'],
            '/word/_rels/document%5Chidden.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-unsafe-percent-encoded-path-byte'],
            '/word/_rels/document%00hidden.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-unsafe-percent-encoded-path-byte'],
            '/word/_rels/%2E%2E/document.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-unsafe-percent-encoded-dot-segment'],
            '/word/_rels/raw space.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-invalid-uri-byte'],
            '../word/_rels/trailing./document.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-trailing-dot-segment'],
            '../../evil/_rels/document.xml.rels' => ['invalid-reference-uri', 'relationship-transform-reference-package-root-traversal'],
        ]
        || ($summary['signatureUnsafeReferenceGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-unsafe-reference.xml'
        || !array_key_exists('relationshipPartName', $summary['signatureUnsafeReferenceGuards'][0] ?? [])
        || $summary['signatureUnsafeReferenceGuards'][0]['relationshipPartName'] !== null
        || ($summary['signatureUnsafeReferenceGuards'][0]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureUnsafeReferenceGuards'][0]['relationshipIds'] ?? null) !== []
        || ($summary['signatureUnsafeReferenceGuards'][0]['relationshipCount'] ?? null) !== 0
        || !array_key_exists('selectorValid', $summary['signatureUnsafeReferenceGuards'][0] ?? [])
        || $summary['signatureUnsafeReferenceGuards'][0]['selectorValid'] !== null
        || ($summary['signatureUnsafeReferenceGuards'][0]['valid'] ?? null) !== false
        || !array_key_exists('relationshipXml', $summary['signatureUnsafeReferenceGuards'][0] ?? [])
        || $summary['signatureUnsafeReferenceGuards'][0]['relationshipXml'] !== null
        || !str_contains((string) ($summary['signatureUnsafeReferenceGuards'][6]['parseError'] ?? ''), 'segments must not end with a dot')
        || !str_contains((string) ($summary['signatureUnsafeReferenceGuards'][7]['parseError'] ?? ''), 'traverse above the package root')
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
