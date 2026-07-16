<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcMarkupCompatibility;
use PortLibs\Pandoc\OpcPackagePath;
use PortLibs\Pandoc\OpcRelationship;
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
  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/word/media/source%20diagram.svg" ContentType="image/svg+xml; charset=UTF-8"/>
  <Override PartName="/word/embeddings/source%20workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/oleObject1.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
  <Override PartName="/word/media/stale%20source.png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>
  <Override PartName="/docProps/thumbnail.png" ContentType="image/png"/>
  <Override PartName="/EncryptedPackage" ContentType="application/vnd.openxmlformats-package.encrypted-package"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-selector-shape.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-duplicate-selector.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-missing-rels.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-fragment.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-dot-segments.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-reference-uri-kinds.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-enveloped-transform.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
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
  <Relationship Id="rIdEncryptedPackage" Type="http://schemas.openxmlformats.org/package/2006/relationships/encrypted-package" Target="EncryptedPackage"/>
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
  <Relationship Id="rIdSchemeRelativeReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="//cdn.example.test/review/source.html" TargetMode="External"/>
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
  <Relationship Id="rIdReviewSourceProperties" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="../customXml/itemProps1.xml"/>
  <Relationship Id="rIdReviewSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review%20source.png"/>
</Relationships>
XML;

$signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML;

$signatureXml = <<<'XML'
<ds:Signature Id="idPackageSignature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipReference SourceId="rIdReviewer"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>SGVsbG8=</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#manifestPackageParts">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>SGVsbG8=</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:KeyInfo>
    <ds:X509Data>
      <ds:X509Certificate>SGVsbG8gc2lnbmVyIGNlcnQ=</ds:X509Certificate>
    </ds:X509Data>
  </ds:KeyInfo>
  <ds:Object Id="idPackageSignatureObject" MimeType="text/xml">
    <ds:Manifest Id="manifestPackageParts">
      <ds:Reference URI="/word/document.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>SGVsbG8=</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/docProps/core.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
        <ds:DigestValue>U291cmNl</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/word/media/hero%20image.PNG">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>UE5H</ds:DigestValue>
      </ds:Reference>
    </ds:Manifest>
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
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Extra="bad">text</mdssi:RelationshipsGroupReference>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

$duplicateSelectorSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
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

$relationshipPartContentTypeSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdCommentImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/footnotes.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdFootnoteImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/settings.xml.rels?ContentType=application/xml%20bad">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdSettingsImage"/>
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

$envelopedTransformSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
        <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>SGVsbG8=</ds:DigestValue>
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
    ['name' => 'customXml/itemProps1.xml', 'data' => '<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="{11111111-2222-3333-4444-555555555555}"><ds:schemaRefs><ds:schemaRef ds:uri="urn:wordpress:review-packet"/><ds:schemaRef ds:uri="https://example.test/schema/review.xsd"/></ds:schemaRefs></ds:datastoreItem>'],
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
    ['name' => 'EncryptedPackage', 'data' => 'encrypted package bytes'],
    ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
    ['name' => '_xmlsignatures/sig1.xml', 'data' => $signatureXml],
    ['name' => '_xmlsignatures/sig-selector-shape.xml', 'data' => $selectorShapeSignatureXml],
    ['name' => '_xmlsignatures/sig-duplicate-selector.xml', 'data' => $duplicateSelectorSignatureXml],
    ['name' => '_xmlsignatures/sig-missing-rels.xml', 'data' => $missingRelationshipPartSignatureXml],
    ['name' => '_xmlsignatures/sig-fragment.xml', 'data' => $fragmentReferenceSignatureXml],
    ['name' => '_xmlsignatures/sig-dot-segments.xml', 'data' => $dotSegmentReferenceSignatureXml],
    ['name' => '_xmlsignatures/sig-reference-uri-kinds.xml', 'data' => $referenceUriKindSignatureXml],
    ['name' => '_xmlsignatures/sig-enveloped-transform.xml', 'data' => $envelopedTransformSignatureXml],
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

$nestedRelationshipPayloadSegmentRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPayloadSegmentAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/media/document.xml"/>
</Relationships>
XML;

$nestedRelationshipPayloadSegmentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHiddenPayload" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../hidden.png"/>
</Relationships>
XML;

$nestedRelationshipPayloadSegmentPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $targetModeDiagnosticContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $nestedRelationshipPayloadSegmentRootRelationshipsXml],
    ['name' => 'word/media/document.xml', 'data' => '<review/>'],
    ['name' => 'word/_rels/media/document.xml.rels', 'data' => $nestedRelationshipPayloadSegmentRelationshipsXml],
]);

$packageRootExternalTargetContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$packageRootExternalTargetRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdPackageRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="review/source.html#packet" TargetMode="External"/>
  <Relationship Id="rIdPackageFragment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#package-review" TargetMode="External"/>
</Relationships>
XML;

$packageRootExternalTargetPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $packageRootExternalTargetContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $packageRootExternalTargetRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
]);

$externalTargetPercentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$externalTargetPercentDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdGoodEncodedSpace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%20packet.html" TargetMode="External"/>
  <Relationship Id="rIdBadPercentEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%ZZpacket.html" TargetMode="External"/>
  <Relationship Id="rIdEncodedNul" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%00packet.html" TargetMode="External"/>
</Relationships>
XML;

$externalTargetPercentPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $packageRootExternalTargetContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $externalTargetPercentRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $externalTargetPercentDocumentRelationshipsXml],
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

$alternativeFormatImportPolicyRelationshipType = OpcRelationshipGraph::WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE;
$alternativeFormatImportPolicyContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/chunks/review.html" ContentType="text/html"/>
  <Override PartName="/word/chunks/plain-review.txt" ContentType="text/plain; charset=utf-8"/>
</Types>
XML;
$alternativeFormatImportPolicyRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
$alternativeFormatImportPolicyDocumentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHtmlChunk" Type="{$alternativeFormatImportPolicyRelationshipType}" Target="chunks/review.html"/>
  <Relationship Id="rIdPlainTextChunk" Type="{$alternativeFormatImportPolicyRelationshipType}" Target="chunks/plain-review.txt"/>
</Relationships>
XML;
$alternativeFormatImportPolicyPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $alternativeFormatImportPolicyContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $alternativeFormatImportPolicyRootRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $alternativeFormatImportPolicyDocumentRelationshipsXml],
    ['name' => 'word/chunks/review.html', 'data' => '<p>Imported review</p>'],
    ['name' => 'word/chunks/plain-review.txt', 'data' => 'Imported review'],
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

$reservedRelationshipDirectoryContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/review-metadata.xml" ContentType="application/xml"/>
</Types>
XML;

$reservedRelationshipDirectoryRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$reservedRelationshipDirectoryRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReservedDirectory" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/review-metadata.xml"/>
</Relationships>
XML;

$reservedRelationshipDirectoryPackage = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $reservedRelationshipDirectoryContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $reservedRelationshipDirectoryRootRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $reservedRelationshipDirectoryRelationshipsXml],
    ['name' => 'word/_rels/review-metadata.xml', 'data' => '<review/>'],
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

$alternateContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:p="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:future="urn:wordpress-opc-alternate-future">
  <mc:AlternateContent>
    <mc:Choice Requires="future">
      <Default Extension="rels" ContentType="application/x-future-relationships"/>
    </mc:Choice>
    <mc:Fallback>
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
  <mc:AlternateContent>
    <mc:Choice Requires="p">
      <p:Override PartName="/word/alternate-document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
      <p:Override PartName="/word/alternate-review.xml" ContentType="application/xml"/>
    </mc:Choice>
    <mc:Fallback>
      <Override PartName="/word/alternate-fallback.xml" ContentType="application/xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
</Types>
XML;

$alternateContentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:r="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:future="urn:wordpress-opc-alternate-future">
  <mc:AlternateContent>
    <mc:Choice Requires="future">
      <Relationship Id="rIdAlternateHidden" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/hidden.xml"/>
    </mc:Choice>
    <mc:Fallback>
      <Relationship Id="rIdAlternateDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/alternate-document.xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
  <mc:AlternateContent>
    <mc:Choice Requires="r">
      <r:Relationship Id="rIdAlternateAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/alternate-review.xml"/>
    </mc:Choice>
    <mc:Fallback>
      <Relationship Id="rIdAlternateAuditFallback" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/alternate-fallback.xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
</Relationships>
XML;

$alternateContentGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $alternateContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $alternateContentRelationshipsXml],
    ['name' => 'word/alternate-document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/alternate-review.xml', 'data' => '<review/>'],
    ['name' => 'word/alternate-fallback.xml', 'data' => '<fallback/>'],
    ['name' => 'word/hidden.xml', 'data' => '<hidden/>'],
]));
$alternateContentRoot = $alternateContentGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
$alternateContentRelationships = $alternateContentGraph->requireRelationshipsForSource('/');
$markupCompatibilityAlternateContent = [
    'sourceParts' => $alternateContentGraph->sourcePartNames(),
    'relationshipIds' => array_map(
        static fn ($relationship): string => $relationship->id,
        $alternateContentRelationships->all()
    ),
    'officeDocumentTargetPart' => $alternateContentRoot['relationships'][0]['targetPart'] ?? null,
    'officeDocumentValid' => $alternateContentRoot['valid'],
    'auditContentType' => $alternateContentGraph->contentTypes()->contentTypeForPart('/word/alternate-review.xml'),
    'fallbackOverrideSelected' => array_key_exists('/word/alternate-fallback.xml', $alternateContentGraph->contentTypes()->overrides()),
    'hiddenRelationshipLoaded' => $alternateContentRelationships->byId('rIdAlternateHidden') !== null,
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
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
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
$caseEquivalentContentTypeOverrides = [];
foreach ($caseEquivalentTargetGraph->preflightContentTypeOverrides() as $override) {
    $caseEquivalentContentTypeOverrides[$override['partName']] = [
        'packagePartName' => $override['packagePartName'],
        'partNameExactMatch' => $override['partNameExactMatch'],
        'partNameEquivalentMatch' => $override['partNameEquivalentMatch'],
        'valid' => $override['valid'],
        'issues' => $override['issues'],
    ];
}
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
    ];
}

$roleCaseContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="Application/Vnd.Openxmlformats-Package.Relationships+Xml; Charset=UTF-8"/>
  <Default Extension="xml" ContentType="Application/Xml; Charset=UTF-8"/>
  <Default Extension="png" ContentType="Image/Png; review=thumbnail"/>
  <Override PartName="/word/document.xml" ContentType="Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml; Profile=Docx"/>
  <Override PartName="/docProps/core.xml" ContentType="Application/Vnd.Openxmlformats-Package.Core-Properties+Xml; Audit=Core"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="Application/Vnd.Openxmlformats-Package.Digital-Signature-Origin; Profile=OPC"/>
  <Override PartName="/_xmlsignatures/sig-case.xml" ContentType="Application/Vnd.Openxmlformats-Package.Digital-Signature-XmlSignature+Xml; Profile=OPC"/>
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

$relationshipPartContentTypeGuardContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/document.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/_rels/settings.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/_xmlsignatures/sig-relationship-part-content-type.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

$relationshipPartContentTypeGuardDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
</Relationships>
XML;

$relationshipPartContentTypeGuardCommentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

$relationshipPartContentTypeGuardFootnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFootnoteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/footnote.png"/>
</Relationships>
XML;

$relationshipPartContentTypeGuardSettingsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSettingsImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/settings.png"/>
</Relationships>
XML;

$relationshipPartContentTypeGuardGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $relationshipPartContentTypeGuardContentTypesXml],
    ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $relationshipPartContentTypeGuardDocumentRelationshipsXml],
    ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/comments.xml.rels', 'data' => $relationshipPartContentTypeGuardCommentsRelationshipsXml],
    ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $relationshipPartContentTypeGuardFootnotesRelationshipsXml],
    ['name' => 'word/settings.xml', 'data' => '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/settings.xml.rels', 'data' => $relationshipPartContentTypeGuardSettingsRelationshipsXml],
    ['name' => 'word/media/settings.png', 'data' => 'PNG'],
    ['name' => '_xmlsignatures/sig-relationship-part-content-type.xml', 'data' => $relationshipPartContentTypeSignatureXml],
]));
$signatureRelationshipPartContentTypeGuards = [];
foreach ($relationshipPartContentTypeGuardGraph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-relationship-part-content-type.xml') as $transform) {
    $signatureRelationshipPartContentTypeGuards[] = [
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
    ];
}

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

$nestedRelationshipPayloadSegmentGuard = null;
foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($nestedRelationshipPayloadSegmentPackage) as $part) {
    if ($part['partName'] !== '/word/_rels/media/document.xml.rels') {
        continue;
    }

    $nestedRelationshipPayloadSegmentGuard = [
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

$packageRootExternalTargetGraph = OpcRelationshipGraph::fromPackage($packageRootExternalTargetPackage);
$packageRootExternalTargetGuards = [];
foreach ($packageRootExternalTargetGraph->preflightTargetsForSource('/') as $target) {
    if (!$target['external']) {
        continue;
    }

    $packageRootExternalTargetGuards[$target['id']] = [
        'id' => $target['id'],
        'target' => $target['target'],
        'kind' => $target['externalTargetKind'],
        'allowed' => $target['externalTargetAllowed'],
        'requiresBaseUri' => $target['externalTargetRequiresBaseUri'],
        'rewriteBasePart' => $target['externalTargetRewriteBasePart'],
        'rewriteReason' => $target['externalTargetRewriteReason'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

$externalTargetPercentGraph = OpcRelationshipGraph::fromPackage($externalTargetPercentPackage);
$externalTargetPercentGuards = [];
foreach ($externalTargetPercentGraph->preflightTargetsForSource('/word/document.xml') as $target) {
    if (!$target['external']) {
        continue;
    }

    $externalTargetPercentGuards[$target['id']] = [
        'id' => $target['id'],
        'target' => $target['target'],
        'kind' => $target['externalTargetKind'],
        'scheme' => $target['externalTargetScheme'],
        'allowed' => $target['externalTargetAllowed'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
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

$alternativeFormatImportPolicyGraph = OpcRelationshipGraph::fromPackage($alternativeFormatImportPolicyPackage);
$alternativeFormatImportPolicyGuard = null;
foreach ($alternativeFormatImportPolicyGraph->preflightPackageConsistency()['relationshipTypePolicies'] as $policy) {
    if ($policy['type'] !== $alternativeFormatImportPolicyRelationshipType) {
        continue;
    }

    $alternativeFormatImportPolicyGuard = [
        'type' => $policy['type'],
        'knownRole' => $policy['knownRole'],
        'sourceScope' => $policy['sourceScope'],
        'singletonScope' => $policy['singletonScope'],
        'policyValid' => $policy['policyValid'],
        'policyIssues' => $policy['policyIssues'],
        'relationshipCount' => $policy['relationshipCount'],
        'sourceCount' => $policy['sourceCount'],
        'targetParts' => $policy['targetParts'],
        'contentTypes' => $policy['contentTypes'],
    ];
    break;
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

$reservedRelationshipDirectoryGraph = OpcRelationshipGraph::fromPackage($reservedRelationshipDirectoryPackage);
$reservedRelationshipDirectoryConsistency = $reservedRelationshipDirectoryGraph->preflightPackageConsistency();
$reservedRelationshipDirectoryParts = [];
foreach ($reservedRelationshipDirectoryConsistency['packageParts'] as $part) {
    $reservedRelationshipDirectoryParts[$part['partName']] = $part;
}
$reservedRelationshipDirectoryOverrides = [];
foreach ($reservedRelationshipDirectoryConsistency['contentTypeOverrides'] as $override) {
    $reservedRelationshipDirectoryOverrides[$override['partName']] = $override;
}
$reservedRelationshipDirectoryTargets = [];
foreach ($reservedRelationshipDirectoryConsistency['relationshipTargets'] as $target) {
    $reservedRelationshipDirectoryTargets[$target['source'] . ':' . $target['id']] = $target;
}
$reservedRelationshipDirectoryReferences = [];
foreach ($reservedRelationshipDirectoryGraph->packagePartReferenceInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $reference) {
    $reservedRelationshipDirectoryReferences[$reference['partName']] = $reference;
}
$reservedRelationshipDirectoryGuard = [
    'packagePart' => $reservedRelationshipDirectoryParts['/word/_rels/review-metadata.xml']['partName'] ?? null,
    'packagePartContentType' => $reservedRelationshipDirectoryParts['/word/_rels/review-metadata.xml']['contentType'] ?? null,
    'packagePartRelationshipPart' => $reservedRelationshipDirectoryParts['/word/_rels/review-metadata.xml']['relationshipPart'] ?? null,
    'packagePartValid' => $reservedRelationshipDirectoryParts['/word/_rels/review-metadata.xml']['valid'] ?? null,
    'packagePartIssues' => $reservedRelationshipDirectoryParts['/word/_rels/review-metadata.xml']['issues'] ?? null,
    'overridePart' => $reservedRelationshipDirectoryOverrides['/word/_rels/review-metadata.xml']['partName'] ?? null,
    'overrideExists' => $reservedRelationshipDirectoryOverrides['/word/_rels/review-metadata.xml']['exists'] ?? null,
    'overrideValid' => $reservedRelationshipDirectoryOverrides['/word/_rels/review-metadata.xml']['valid'] ?? null,
    'overrideIssues' => $reservedRelationshipDirectoryOverrides['/word/_rels/review-metadata.xml']['issues'] ?? null,
    'targetPart' => $reservedRelationshipDirectoryTargets['/word/document.xml:rIdReservedDirectory']['targetPart'] ?? null,
    'targetExists' => $reservedRelationshipDirectoryTargets['/word/document.xml:rIdReservedDirectory']['exists'] ?? null,
    'targetValid' => $reservedRelationshipDirectoryTargets['/word/document.xml:rIdReservedDirectory']['valid'] ?? null,
    'targetIssues' => $reservedRelationshipDirectoryTargets['/word/document.xml:rIdReservedDirectory']['issues'] ?? null,
    'referenceValid' => $reservedRelationshipDirectoryReferences['/word/_rels/review-metadata.xml']['valid'] ?? null,
    'referenceIssues' => $reservedRelationshipDirectoryReferences['/word/_rels/review-metadata.xml']['issues'] ?? null,
    'directReferenceIssues' => $reservedRelationshipDirectoryReferences['/word/_rels/review-metadata.xml']['directReferences'][0]['issues'] ?? null,
    'packageConsistencyValid' => $reservedRelationshipDirectoryConsistency['valid'],
    'packagePartsValid' => $reservedRelationshipDirectoryConsistency['packagePartsValid'],
    'contentTypeOverridesValid' => $reservedRelationshipDirectoryConsistency['contentTypeOverridesValid'],
    'relationshipTargetsValid' => $reservedRelationshipDirectoryConsistency['relationshipTargetsValid'],
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
$relationshipPartLoadSummary = OpcRelationshipGraph::relationshipPartLoadSummary($package);

$directRelationshipContentTypeGuard = [
    'source' => '/word/draft.xml',
    'relationshipPartName' => '/word/_rels/draft.xml.rels',
    'hasRelationshipsForSource' => OpcRelationships::packageHasRelationshipsForSource($package, '/word/draft.xml'),
    'fromPackageRejected' => false,
    'fromPackageError' => null,
    'preflightLoadReason' => $relationshipPartLoads['/word/_rels/draft.xml.rels']['loadReason'] ?? null,
    'preflightIssues' => $relationshipPartLoads['/word/_rels/draft.xml.rels']['issues'] ?? null,
];
try {
    OpcRelationships::fromPackage($package, '/word/draft.xml');
} catch (RuntimeException $exception) {
    $directRelationshipContentTypeGuard['fromPackageRejected'] = true;
    $directRelationshipContentTypeGuard['fromPackageError'] = $exception->getMessage();
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
        'contentTypeSource' => $part['contentTypeSource'],
        'contentTypeDefaultExtension' => $part['contentTypeDefaultExtension'],
        'contentTypeOverridePartName' => $part['contentTypeOverridePartName'],
        'contentTypeOverridePartNameExactMatch' => $part['contentTypeOverridePartNameExactMatch'],
        'contentTypeOverridePartNameEquivalentMatch' => $part['contentTypeOverridePartNameEquivalentMatch'],
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
$packageConsistencySummary = $graph->packageConsistencySummary();
$packageConsistencyOverrides = [];
foreach ($packageConsistency['contentTypeOverrides'] as $override) {
    $packageConsistencyOverrides[$override['partName']] = $override;
}
$packageConsistencyTargets = [];
foreach ($packageConsistency['relationshipTargets'] as $target) {
    $packageConsistencyTargets[$target['source'] . ':' . $target['id']] = $target;
}
$packageConsistencyRelationshipTypePolicies = [];
foreach ($packageConsistency['relationshipTypePolicies'] as $policy) {
    $packageConsistencyRelationshipTypePolicies[$policy['type']] = [
        'type' => $policy['type'],
        'knownRole' => $policy['knownRole'],
        'sourceScope' => $policy['sourceScope'],
        'singletonScope' => $policy['singletonScope'],
        'policyValid' => $policy['policyValid'],
        'policyIssues' => $policy['policyIssues'],
        'relationshipCount' => $policy['relationshipCount'],
        'sourceCount' => $policy['sourceCount'],
        'sources' => $policy['sources'],
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
$packagePartRelationshipCoverage = $graph->packagePartRelationshipCoverageSummary(
    '/',
    OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
);
$relationshipSourceClosure = $graph->relationshipSourceClosureInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);
$relationshipSourceClosureCoverage = $graph->relationshipSourceClosureCoverageSummary(
    '/',
    OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
);
$relationshipSourceClosureSources = [];
foreach ($relationshipSourceClosure['sources'] as $source) {
    $relationshipSourceClosureSources[$source['source']] = [
        'source' => $source['source'],
        'reachable' => $source['reachable'],
        'depth' => $source['depth'],
        'closureAction' => $source['closureAction'],
        'relationshipPartName' => $source['relationshipPartName'],
        'relationshipCount' => $source['relationshipCount'],
        'invalidTargetCount' => $source['invalidTargetCount'],
        'externalTargets' => $source['externalTargets'],
        'missingTargetParts' => $source['missingTargetParts'],
        'valid' => $source['valid'],
        'issues' => $source['issues'],
    ];
}
$relationshipSourceClosureStops = [];
foreach ($relationshipSourceClosure['stops'] as $stop) {
    $relationshipSourceClosureStops[$stop['id']] = [
        'source' => $stop['source'],
        'depth' => $stop['depth'],
        'id' => $stop['id'],
        'type' => $stop['type'],
        'target' => $stop['target'],
        'targetPart' => $stop['targetPart'],
        'targetSource' => $stop['targetSource'],
        'contentType' => $stop['contentType'],
        'external' => $stop['external'],
        'exists' => $stop['exists'],
        'relationshipPartTarget' => $stop['relationshipPartTarget'],
        'stopReason' => $stop['stopReason'],
        'valid' => $stop['valid'],
        'issues' => $stop['issues'],
    ];
}
$relationshipSourceClosureSummary = [
    'source' => $relationshipSourceClosure['source'],
    'relationshipType' => $relationshipSourceClosure['relationshipType'],
    'valid' => $relationshipSourceClosure['valid'],
    'issues' => $relationshipSourceClosure['issues'],
    'expandedSourceCount' => $relationshipSourceClosure['expandedSourceCount'],
    'outsideSourceCount' => $relationshipSourceClosure['outsideSourceCount'],
    'stopCount' => $relationshipSourceClosure['stopCount'],
    'externalStopCount' => $relationshipSourceClosure['externalStopCount'],
    'invalidStopCount' => $relationshipSourceClosure['invalidStopCount'],
    'missingStopCount' => $relationshipSourceClosure['missingStopCount'],
    'relationshipPartStopCount' => $relationshipSourceClosure['relationshipPartStopCount'],
    'cycleStopCount' => $relationshipSourceClosure['cycleStopCount'],
    'unloadedStopCount' => $relationshipSourceClosure['unloadedStopCount'],
    'sources' => $relationshipSourceClosureSources,
    'stops' => $relationshipSourceClosureStops,
];
$officeDocumentRelationshipReadiness = $graph->preflightOfficeDocumentRelationshipReadiness();
$relationshipRoleTargetPolicy = $graph->preflightRelationshipRoleTargets();
$relationshipRoleTargetPolicyInvalidRelationships = [];
foreach ($relationshipRoleTargetPolicy['relationships'] as $relationship) {
    if ($relationship['valid']) {
        continue;
    }

    $relationshipRoleTargetPolicyInvalidRelationships[] = [
        'source' => $relationship['source'],
        'id' => $relationship['id'],
        'role' => $relationship['role'],
        'target' => $relationship['target'],
        'targetPart' => $relationship['targetPart'],
        'contentType' => $relationship['contentType'],
        'expectedContentType' => $relationship['expectedContentType'],
        'expectedContentTypes' => $relationship['expectedContentTypes'],
        'expectedContentTypePrefix' => $relationship['expectedContentTypePrefix'],
        'expectedSource' => $relationship['expectedSource'],
        'expectedSourceContentTypes' => $relationship['expectedSourceContentTypes'],
        'expectedExternal' => $relationship['expectedExternal'],
        'external' => $relationship['external'],
        'valid' => $relationship['valid'],
        'issues' => $relationship['issues'],
    ];
}
$relationshipRolePolicySummary = $graph->relationshipRolePolicySummary();
$relationshipRolePolicyInvalidRoles = [];
foreach ($relationshipRolePolicySummary['roles'] as $role) {
    if ($role['policyValid']) {
        continue;
    }

    $relationshipRolePolicyInvalidRoles[] = $role;
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
        'contentTypeSource' => $target['contentTypeSource'],
        'contentTypeDefaultExtension' => $target['contentTypeDefaultExtension'],
        'contentTypeOverridePartName' => $target['contentTypeOverridePartName'],
        'contentTypeOverridePartNameExactMatch' => $target['contentTypeOverridePartNameExactMatch'],
        'contentTypeOverridePartNameEquivalentMatch' => $target['contentTypeOverridePartNameEquivalentMatch'],
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

$internalSourceReferences = [];
foreach ($graph->preflightInternalTargetReferences($documentPart) as $reference) {
    if (!$reference['sameSourceReference'] || $reference['target'] === $reference['targetPart']) {
        continue;
    }

    $internalSourceReferences[] = [
        'id' => $reference['id'],
        'target' => $reference['target'],
        'targetPart' => $reference['targetPart'],
        'targetQuery' => $reference['targetQuery'],
        'targetFragment' => $reference['targetFragment'],
        'sameSourceReference' => $reference['sameSourceReference'],
        'contentType' => $reference['contentType'],
        'issues' => $reference['issues'],
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
        'duplicateSourceIds' => $transform['duplicateSourceIds'],
        'duplicateSourceTypes' => $transform['duplicateSourceTypes'],
        'selectorDuplicateSourceIdCount' => $transform['selectorDuplicateSourceIdCount'],
        'selectorDuplicateSourceTypeCount' => $transform['selectorDuplicateSourceTypeCount'],
        'selectorChildCount' => $transform['selectorChildCount'],
        'selectorRelationshipReferenceCount' => $transform['selectorRelationshipReferenceCount'],
        'selectorRelationshipGroupReferenceCount' => $transform['selectorRelationshipGroupReferenceCount'],
        'selectorUnsupportedChildCount' => $transform['selectorUnsupportedChildCount'],
        'selectorUnsupportedContentCount' => $transform['selectorUnsupportedContentCount'],
        'followingCanonicalizationAlgorithm' => $transform['followingCanonicalizationAlgorithm'],
        'followingCanonicalization' => $transform['followingCanonicalization'],
        'followedByCanonicalization' => $transform['followedByCanonicalization'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXml' => $transform['relationshipXml'],
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
    ];
}
$signatureRelationshipTransformSummary = $graph->signatureRelationshipTransformSummary('/_xmlsignatures/sig1.xml');
$signedRelationshipPolicySummary = $graph->signedRelationshipPolicySummary('/_xmlsignatures/sig1.xml', [
    OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
    OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
]);
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
        'duplicateSourceIds' => $transform['duplicateSourceIds'],
        'duplicateSourceTypes' => $transform['duplicateSourceTypes'],
        'selectorDuplicateSourceIdCount' => $transform['selectorDuplicateSourceIdCount'],
        'selectorDuplicateSourceTypeCount' => $transform['selectorDuplicateSourceTypeCount'],
        'selectorChildCount' => $transform['selectorChildCount'],
        'selectorRelationshipReferenceCount' => $transform['selectorRelationshipReferenceCount'],
        'selectorRelationshipGroupReferenceCount' => $transform['selectorRelationshipGroupReferenceCount'],
        'selectorUnsupportedChildCount' => $transform['selectorUnsupportedChildCount'],
        'selectorUnsupportedContentCount' => $transform['selectorUnsupportedContentCount'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
    ];
}
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-duplicate-selector.xml') as $transform) {
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
        'duplicateSourceIds' => $transform['duplicateSourceIds'],
        'duplicateSourceTypes' => $transform['duplicateSourceTypes'],
        'selectorDuplicateSourceIdCount' => $transform['selectorDuplicateSourceIdCount'],
        'selectorDuplicateSourceTypeCount' => $transform['selectorDuplicateSourceTypeCount'],
        'selectorChildCount' => $transform['selectorChildCount'],
        'selectorRelationshipReferenceCount' => $transform['selectorRelationshipReferenceCount'],
        'selectorRelationshipGroupReferenceCount' => $transform['selectorRelationshipGroupReferenceCount'],
        'selectorUnsupportedChildCount' => $transform['selectorUnsupportedChildCount'],
        'selectorUnsupportedContentCount' => $transform['selectorUnsupportedContentCount'],
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
    ];
}
$signatureEnvelopedTransformGuards = [];
foreach ($graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-enveloped-transform.xml') as $transform) {
    $signatureEnvelopedTransformGuards[] = [
        'signaturePart' => $transform['signaturePart'],
        'referenceUri' => $transform['referenceUri'],
        'relationshipPartName' => $transform['relationshipPartName'],
        'source' => $transform['source'],
        'sourceIds' => $transform['sourceIds'],
        'followingCanonicalizationAlgorithm' => $transform['followingCanonicalizationAlgorithm'],
        'followedByCanonicalization' => $transform['followedByCanonicalization'],
        'relationshipIds' => $transform['relationshipIds'],
        'relationshipCount' => $transform['relationshipCount'],
        'selectorValid' => $transform['selectorValid'],
        'relationshipTargetsValid' => $transform['relationshipTargetsValid'],
        'valid' => $transform['valid'],
        'issues' => $transform['issues'],
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
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
        'relationshipXmlBytes' => $transform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $transform['relationshipXmlSha256'],
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
$digitalSignatureRelationshipRoles = $graph->preflightDigitalSignatureRelationshipRoles();
$digitalSignatureMetadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig1.xml');
$digitalSignatureDigestPolicySummary = $graph->digitalSignatureDigestPolicySummary('/_xmlsignatures/sig1.xml');
$digitalSignatureSignedInfoReferences = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig1.xml');
$signatureEnvelopedSignedInfoReferences = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-enveloped-transform.xml');
$encryptedPackages = $graph->preflightEncryptedPackages();
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
$nestedEmbeddedRelationshipClosures = [];
foreach ($embeddedPackageGraphs as $embeddedPackageGraph) {
    $nestedClosure = $embeddedPackageGraph['nestedRelationshipClosure'] ?? null;
    if (is_array($nestedClosure)) {
        $nestedEmbeddedRelationshipClosures[] = [
            'id' => $embeddedPackageGraph['id'],
            'packagePart' => $embeddedPackageGraph['targetPart'],
            'valid' => $nestedClosure['valid'],
            'issues' => $nestedClosure['issues'],
            'expandedSourceCount' => $nestedClosure['expandedSourceCount'],
            'stopCount' => $nestedClosure['stopCount'],
            'externalStopCount' => $nestedClosure['externalStopCount'],
            'missingStopCount' => $nestedClosure['missingStopCount'],
            'unloadedStopCount' => $nestedClosure['unloadedStopCount'],
            'sources' => array_values(array_map(
                static fn (array $source): array => [
                    'source' => $source['source'],
                    'reachable' => $source['reachable'],
                    'depth' => $source['depth'],
                    'relationshipCount' => $source['relationshipCount'],
                    'invalidTargetCount' => $source['invalidTargetCount'],
                    'targetParts' => $source['targetParts'],
                    'missingTargetParts' => $source['missingTargetParts'],
                    'externalTargets' => $source['externalTargets'],
                    'valid' => $source['valid'],
                    'issues' => $source['issues'],
                ],
                $nestedClosure['sources']
            )),
            'stops' => array_values(array_map(
                static fn (array $stop): array => [
                    'source' => $stop['source'],
                    'depth' => $stop['depth'],
                    'id' => $stop['id'],
                    'targetPart' => $stop['targetPart'],
                    'contentType' => $stop['contentType'],
                    'external' => $stop['external'],
                    'exists' => $stop['exists'],
                    'stopReason' => $stop['stopReason'],
                    'valid' => $stop['valid'],
                    'issues' => $stop['issues'],
                ],
                $nestedClosure['stops']
            )),
        ];
    }

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
$invalidSignatureOriginGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/_xmlsignatures/bad-origin.sigs" ContentType="application/xml"/><Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/></Types>'],
    ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdBadSignatureOrigin" Type="' . OpcRelationshipGraph::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE . '" Target="_xmlsignatures/bad-origin.sigs"/></Relationships>'],
    ['name' => '_xmlsignatures/bad-origin.sigs', 'data' => ''],
    ['name' => '_xmlsignatures/_rels/bad-origin.sigs.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdSignature1" Type="' . OpcRelationshipGraph::DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE . '" Target="sig1.xml"/></Relationships>'],
    ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
]));
$invalidSignatureOriginRoles = $invalidSignatureOriginGraph->preflightDigitalSignatureRelationshipRoles();
$invalidSignatureOriginRolesById = [];
foreach ($invalidSignatureOriginRoles['roles'] as $role) {
    $invalidSignatureOriginRolesById[$role['id']] = $role;
}
$invalidSignatureOriginGuard = [
    'allowedSignatureSources' => $invalidSignatureOriginRoles['allowedSignatureSources'],
    'originValid' => $invalidSignatureOriginRolesById['rIdBadSignatureOrigin']['valid'] ?? null,
    'originIssues' => $invalidSignatureOriginRolesById['rIdBadSignatureOrigin']['issues'] ?? null,
    'signatureSourceAllowed' => $invalidSignatureOriginRolesById['rIdSignature1']['sourceAllowed'] ?? null,
    'signatureValid' => $invalidSignatureOriginRolesById['rIdSignature1']['valid'] ?? null,
    'signatureIssues' => $invalidSignatureOriginRolesById['rIdSignature1']['issues'] ?? null,
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
$customXmlPropertyRelationships = [];
foreach ($graph->preflightWordprocessingDocumentRelationships('/word/review source.xml') as $role) {
    if ($role['role'] !== 'custom-xml-properties') {
        continue;
    }

    $customXmlPropertyRelationships[$role['id']] = [
        'id' => $role['id'],
        'source' => $role['source'],
        'sourceContentType' => $role['sourceContentType'],
        'targetPart' => $role['targetPart'],
        'contentType' => $role['contentType'],
        'expectedContentType' => $role['expectedContentType'],
        'expectedSourceContentTypes' => $role['expectedSourceContentTypes'],
        'external' => $role['external'],
        'valid' => $role['valid'],
        'issues' => $role['issues'],
    ];
}
$customXmlPropertyParts = array_values(array_unique(array_filter(
    array_map(static fn (array $role): ?string => $role['valid'] ? $role['targetPart'] : null, $customXmlPropertyRelationships),
    static fn (?string $part): bool => $part !== null
)));
$customXmlPropertyPayloads = [];
foreach ($graph->preflightCustomXmlProperties('/word/review source.xml') as $payload) {
    $customXmlPropertyPayloads[$payload['id']] = [
        'id' => $payload['id'],
        'source' => $payload['source'],
        'targetPart' => $payload['targetPart'],
        'contentType' => $payload['contentType'],
        'rootName' => $payload['rootName'],
        'rootNamespace' => $payload['rootNamespace'],
        'itemId' => $payload['itemId'],
        'itemIdValid' => $payload['itemIdValid'],
        'schemaRefCount' => $payload['schemaRefCount'],
        'schemaRefUris' => $payload['schemaRefUris'],
        'valid' => $payload['valid'],
        'issues' => $payload['issues'],
    ];
}
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

$serializedReviewTargetName = "\u{00E9}" . 'preuve.png';
$serializedRelationships = new OpcRelationships('/word/document.xml');
$serializedRelationships->add(new OpcRelationship(
    'rIdSerializedReview',
    'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
    'media/review source ' . $serializedReviewTargetName . '#crop'
));
$serializedRelationships->add(new OpcRelationship(
    'rIdSerializedExternal',
    'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
    'https://example.test/source.html?post=42&action=edit',
    OpcRelationship::TARGET_MODE_EXTERNAL
));
$serializedRelationshipXml = $serializedRelationships->toXml();
$serializedRelationshipRoundTrip = OpcRelationships::fromXml($serializedRelationshipXml, '/word/document.xml');
$serializedInternalTargetRejections = [];
foreach ([
    'encodedSlash' => 'media%2Fhidden.png',
    'encodedDotSegment' => 'media/%2E%2E/styles.xml',
    'encodedBackslash' => 'media%5Chidden.png',
    'rootFragment' => '#package-fragment',
] as $label => $target) {
    try {
        $guardRelationships = new OpcRelationships($label === 'rootFragment' ? '/' : '/word/document.xml');
        $guardRelationships->add(new OpcRelationship(
            'rId' . ucfirst($label),
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
            $target
        ));
        $guardRelationships->toXml();
        $serializedInternalTargetRejections[$label] = false;
    } catch (InvalidArgumentException) {
        $serializedInternalTargetRejections[$label] = true;
    }
}
$serializedExternalTargetRejections = [];
foreach ([
    'rawSpace' => 'https://example.test/source packet.html',
    'badPercentEscape' => 'https://example.test/source%ZZpacket.html',
    'encodedControlByte' => 'https://example.test/source%00packet.html',
    'unsafeScheme' => 'javascript:alert(1)',
] as $label => $target) {
    try {
        $guardRelationships = new OpcRelationships('/word/document.xml');
        $guardRelationships->add(new OpcRelationship(
            'rId' . ucfirst($label),
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            $target,
            OpcRelationship::TARGET_MODE_EXTERNAL
        ));
        $guardRelationships->toXml();
        $serializedExternalTargetRejections[$label] = false;
    } catch (InvalidArgumentException) {
        $serializedExternalTargetRejections[$label] = true;
    }
}
$relationshipSerializationGuard = [
    'xmlContainsEscapedInternalTarget' => str_contains($serializedRelationshipXml, 'Target="media/review%20source%20%C3%A9preuve.png#crop"'),
    'xmlOmitsRawInternalSpace' => !str_contains($serializedRelationshipXml, 'Target="media/review source '),
    'xmlEscapesExternalAmpersand' => str_contains($serializedRelationshipXml, 'Target="https://example.test/source.html?post=42&amp;action=edit"'),
    'xmlOmitsInternalTargetMode' => !str_contains($serializedRelationshipXml, 'TargetMode="Internal"'),
    'xmlKeepsExternalTargetMode' => str_contains($serializedRelationshipXml, 'TargetMode="External"'),
    'roundTripInternalTarget' => $serializedRelationshipRoundTrip->resolveTarget('rIdSerializedReview'),
    'roundTripExternalTarget' => $serializedRelationshipRoundTrip->resolveTarget('rIdSerializedExternal'),
    'internalTargetRejections' => $serializedInternalTargetRejections,
    'externalTargetRejections' => $serializedExternalTargetRejections,
];

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
    'digitalSignatureRelationshipRoles' => $digitalSignatureRelationshipRoles,
    'digitalSignatureMetadata' => $digitalSignatureMetadata,
    'digitalSignatureSignedInfoReferences' => $digitalSignatureSignedInfoReferences,
    'encryptedPackages' => $encryptedPackages,
    'embeddedPackages' => $embeddedPackages,
    'embeddedPackageGraphs' => $embeddedPackageGraphs,
    'packageConsistency' => [
        'valid' => $packageConsistency['valid'],
        'packagePartsValid' => $packageConsistency['packagePartsValid'],
        'contentTypeOverridesValid' => $packageConsistency['contentTypeOverridesValid'],
        'relationshipTargetsValid' => $packageConsistency['relationshipTargetsValid'],
        'relationshipTypePoliciesValid' => $packageConsistency['relationshipTypePoliciesValid'],
        'summary' => $packageConsistencySummary,
        'contentTypeOverrides' => $packageConsistencyOverrides,
        'relationshipTargets' => $packageConsistencyTargets,
        'relationshipTypePolicies' => $packageConsistencyRelationshipTypePolicies,
    ],
    'relationshipPartLoads' => $relationshipPartLoads,
    'relationshipPartLoadSummary' => $relationshipPartLoadSummary,
    'directRelationshipContentTypeGuard' => $directRelationshipContentTypeGuard,
    'packageParts' => $packagePartPreflight,
    'relationshipSources' => $graph->sourcePartNames(),
    'relationshipSourceInventory' => $relationshipSourceInventory,
    'relationshipSourceClosure' => $relationshipSourceClosureSummary,
    'relationshipSourceClosureCoverage' => $relationshipSourceClosureCoverage,
    'officeDocumentRelationshipReadiness' => $officeDocumentRelationshipReadiness,
    'relationshipTypeInventory' => $relationshipTypeInventory,
    'relationshipRolePolicySummary' => $relationshipRolePolicySummary,
    'packagePartReferences' => $packagePartReferences,
    'packagePartRelationshipCoverage' => $packagePartRelationshipCoverage,
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
        'relationshipXmlBytes' => $relationshipTransform['relationshipXmlBytes'],
        'relationshipXmlSha256' => $relationshipTransform['relationshipXmlSha256'],
    ],
    'signatureRelationshipTransforms' => $signatureRelationshipTransforms,
    'signatureRelationshipTransformSummary' => $signatureRelationshipTransformSummary,
    'signedRelationshipPolicySummary' => $signedRelationshipPolicySummary,
    'digitalSignatureDigestPolicySummary' => $digitalSignatureDigestPolicySummary,
    'signatureRelationshipTransformGuards' => $signatureRelationshipTransformGuards,
    'signatureMissingRelationshipPartGuards' => $signatureMissingRelationshipPartGuards,
    'signatureRelationshipPartContentTypeGuards' => $signatureRelationshipPartContentTypeGuards,
    'signatureFragmentReferenceGuards' => $signatureFragmentReferenceGuards,
    'signatureDotSegmentReferenceGuards' => $signatureDotSegmentReferenceGuards,
    'signatureReferenceUriKindGuards' => $signatureReferenceUriKindGuards,
    'signatureEnvelopedTransformGuards' => $signatureEnvelopedTransformGuards,
    'signatureEnvelopedSignedInfoReferences' => $signatureEnvelopedSignedInfoReferences,
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
        'markupCompatibilityAlternateContent' => $markupCompatibilityAlternateContent,
        'relationshipSourceAliasGraphRejected' => $relationshipSourceAliasGraphRejected,
        'partNameCaseCollisionGraphRejected' => $partNameCaseCollisionGraphRejected,
        'contentTypeOverrideCaseLookup' => $caseEquivalentTypes->contentTypeForPart('/word/document.xml'),
        'contentTypeOverrideDuplicateRejected' => $caseEquivalentOverrideDuplicateRejected,
        'caseEquivalentContentTypeOverrides' => $caseEquivalentContentTypeOverrides,
        'caseEquivalentTargets' => $caseEquivalentTargets,
        'caseEquivalentSignatureTransforms' => $caseEquivalentSignatureTransforms,
        'caseInsensitiveRoleContentTypes' => $roleCaseContentTypeMatch,
        'internalTargetDiagnostics' => $internalTargetDiagnostics,
        'relationshipSerializationGuard' => $relationshipSerializationGuard,
        'emptySignatureOriginGuard' => $emptySignatureOriginGuard,
        'invalidSignatureOriginGuard' => $invalidSignatureOriginGuard,
    ],
    'relationshipSourceAliasGuards' => $relationshipSourceAliasGuards,
    'relationshipTargetModeGuards' => $relationshipTargetModeGuards,
    'nestedRelationshipPayloadSegmentGuard' => $nestedRelationshipPayloadSegmentGuard,
    'packageRootExternalTargetGuards' => $packageRootExternalTargetGuards,
    'externalTargetPercentGuards' => $externalTargetPercentGuards,
    'relationshipRecordShapeGuards' => $relationshipRecordShapeGuards,
    'alternativeFormatImportPolicyGuard' => $alternativeFormatImportPolicyGuard,
    'fixedContentTypesItemGuard' => $fixedContentTypesItemGuard,
    'reservedRelationshipContentTypeGuard' => $reservedRelationshipContentTypeGuard,
    'reservedRelationshipDirectoryGuard' => $reservedRelationshipDirectoryGuard,
    'partNameCaseCollisionGuards' => $partNameCaseCollisionGuards,
    'contentTypeInventory' => $contentTypeInventory,
    'wordpressImport' => [
        'documentPropertyParts' => $documentPropertyParts,
        'customXmlPropertyParts' => $customXmlPropertyParts,
        'customXmlPropertyRelationships' => array_values($customXmlPropertyRelationships),
        'customXmlPropertyPayloads' => array_values($customXmlPropertyPayloads),
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
        'relationshipPartLoadSummary' => [
            'relationshipPartCount' => $relationshipPartLoadSummary['relationshipPartCount'],
            'loadedCount' => $relationshipPartLoadSummary['loadedCount'],
            'skippedCount' => $relationshipPartLoadSummary['skippedCount'],
            'invalidCount' => $relationshipPartLoadSummary['invalidCount'],
            'relationshipCount' => $relationshipPartLoadSummary['relationshipCount'],
            'loadReasonCounts' => $relationshipPartLoadSummary['loadReasonCounts'],
            'issueCounts' => $relationshipPartLoadSummary['issueCounts'],
            'issues' => $relationshipPartLoadSummary['issues'],
        ],
        'packageConsistencySummary' => $packageConsistencySummary,
        'signatureRelationshipTransformSummary' => [
            'valid' => $signatureRelationshipTransformSummary['valid'],
            'transformCount' => $signatureRelationshipTransformSummary['transformCount'],
            'validTransformCount' => $signatureRelationshipTransformSummary['validTransformCount'],
            'invalidTransformCount' => $signatureRelationshipTransformSummary['invalidTransformCount'],
            'relationshipPartNames' => $signatureRelationshipTransformSummary['relationshipPartNames'],
            'sources' => $signatureRelationshipTransformSummary['sources'],
            'selectedRelationshipIds' => $signatureRelationshipTransformSummary['selectedRelationshipIds'],
            'selectedInternalTargetParts' => $signatureRelationshipTransformSummary['selectedInternalTargetParts'],
            'selectedExternalTargets' => $signatureRelationshipTransformSummary['selectedExternalTargets'],
            'relationshipXmlSha256s' => $signatureRelationshipTransformSummary['relationshipXmlSha256s'],
            'issues' => $signatureRelationshipTransformSummary['issues'],
        ],
        'signedRelationshipPolicy' => [
            'valid' => $signedRelationshipPolicySummary['valid'],
            'allowedRelationshipTypes' => $signedRelationshipPolicySummary['allowedRelationshipTypes'],
            'selectedRelationshipCount' => $signedRelationshipPolicySummary['selectedRelationshipCount'],
            'allowedRelationshipCount' => $signedRelationshipPolicySummary['allowedRelationshipCount'],
            'disallowedRelationshipCount' => $signedRelationshipPolicySummary['disallowedRelationshipCount'],
            'externalRelationshipCount' => $signedRelationshipPolicySummary['externalRelationshipCount'],
            'internalRelationshipCount' => $signedRelationshipPolicySummary['internalRelationshipCount'],
            'invalidRelationshipCount' => $signedRelationshipPolicySummary['invalidRelationshipCount'],
            'selectedRelationshipIds' => $signedRelationshipPolicySummary['selectedRelationshipIds'],
            'selectedRelationshipTypes' => $signedRelationshipPolicySummary['selectedRelationshipTypes'],
            'disallowedRelationshipTypes' => $signedRelationshipPolicySummary['disallowedRelationshipTypes'],
            'selectedInternalTargetParts' => $signedRelationshipPolicySummary['selectedInternalTargetParts'],
            'selectedExternalTargets' => $signedRelationshipPolicySummary['selectedExternalTargets'],
            'issueCounts' => $signedRelationshipPolicySummary['issueCounts'],
            'issues' => $signedRelationshipPolicySummary['issues'],
            'disallowedRelationships' => $signedRelationshipPolicySummary['disallowedRelationships'],
        ],
        'digitalSignatureDigestPolicy' => [
            'valid' => $digitalSignatureDigestPolicySummary['valid'],
            'referenceCount' => $digitalSignatureDigestPolicySummary['referenceCount'],
            'signedInfoReferenceCount' => $digitalSignatureDigestPolicySummary['signedInfoReferenceCount'],
            'manifestReferenceCount' => $digitalSignatureDigestPolicySummary['manifestReferenceCount'],
            'invalidDigestPolicyCount' => $digitalSignatureDigestPolicySummary['invalidDigestPolicyCount'],
            'unknownDigestAlgorithmCount' => $digitalSignatureDigestPolicySummary['unknownDigestAlgorithmCount'],
            'digestValueLengthMismatchCount' => $digitalSignatureDigestPolicySummary['digestValueLengthMismatchCount'],
            'issueCounts' => $digitalSignatureDigestPolicySummary['issueCounts'],
            'issues' => $digitalSignatureDigestPolicySummary['issues'],
            'invalidReferences' => $digitalSignatureDigestPolicySummary['invalidReferences'],
        ],
        'relationshipClosureReview' => [
            'source' => $relationshipSourceClosureSummary['source'],
            'expandedSourceCount' => $relationshipSourceClosureSummary['expandedSourceCount'],
            'outsideSourceCount' => $relationshipSourceClosureSummary['outsideSourceCount'],
            'stopCount' => $relationshipSourceClosureSummary['stopCount'],
            'issues' => $relationshipSourceClosureSummary['issues'],
            'sources' => array_values($relationshipSourceClosureSummary['sources']),
            'stops' => array_values($relationshipSourceClosureSummary['stops']),
        ],
        'relationshipClosureCoverage' => [
            'valid' => $relationshipSourceClosureCoverage['valid'],
            'sourceCount' => $relationshipSourceClosureCoverage['sourceCount'],
            'expandedSourceCount' => $relationshipSourceClosureCoverage['expandedSourceCount'],
            'outsideSourceCount' => $relationshipSourceClosureCoverage['outsideSourceCount'],
            'stopCount' => $relationshipSourceClosureCoverage['stopCount'],
            'expandedSourceNames' => $relationshipSourceClosureCoverage['expandedSourceNames'],
            'outsideSourceNames' => $relationshipSourceClosureCoverage['outsideSourceNames'],
            'sourceDepths' => $relationshipSourceClosureCoverage['sourceDepths'],
            'stopReasonCounts' => $relationshipSourceClosureCoverage['stopReasonCounts'],
            'stopIdsByReason' => $relationshipSourceClosureCoverage['stopIdsByReason'],
            'stopTargetsByReason' => $relationshipSourceClosureCoverage['stopTargetsByReason'],
            'invalidStopCount' => $relationshipSourceClosureCoverage['invalidStopCount'],
            'invalidStopIds' => $relationshipSourceClosureCoverage['invalidStopIds'],
            'missingTargetParts' => $relationshipSourceClosureCoverage['missingTargetParts'],
            'relationshipPartTargetParts' => $relationshipSourceClosureCoverage['relationshipPartTargetParts'],
            'unloadedTargetSources' => $relationshipSourceClosureCoverage['unloadedTargetSources'],
            'externalTargets' => $relationshipSourceClosureCoverage['externalTargets'],
            'issues' => $relationshipSourceClosureCoverage['issues'],
        ],
        'packagePartRelationshipCoverage' => [
            'valid' => $packagePartRelationshipCoverage['valid'],
            'inventoryPartCount' => $packagePartRelationshipCoverage['inventoryPartCount'],
            'packagePartCount' => $packagePartRelationshipCoverage['packagePartCount'],
            'relationshipPartCount' => $packagePartRelationshipCoverage['relationshipPartCount'],
            'relationshipSourcePartCount' => $packagePartRelationshipCoverage['relationshipSourcePartCount'],
            'directReferencePartCount' => $packagePartRelationshipCoverage['directReferencePartCount'],
            'reachableReferencePartCount' => $packagePartRelationshipCoverage['reachableReferencePartCount'],
            'directOnlyPartCount' => $packagePartRelationshipCoverage['directOnlyPartCount'],
            'missingReferencedPartCount' => $packagePartRelationshipCoverage['missingReferencedPartCount'],
            'unreferencedPackagePartCount' => $packagePartRelationshipCoverage['unreferencedPackagePartCount'],
            'unreferencedRelationshipPartCount' => $packagePartRelationshipCoverage['unreferencedRelationshipPartCount'],
            'invalidPartCount' => $packagePartRelationshipCoverage['invalidPartCount'],
            'externalDirectReferenceCount' => $packagePartRelationshipCoverage['externalDirectReferenceCount'],
            'externalReachableReferenceCount' => $packagePartRelationshipCoverage['externalReachableReferenceCount'],
            'invalidExternalReferenceCount' => $packagePartRelationshipCoverage['invalidExternalReferenceCount'],
            'referencedPartNames' => $packagePartRelationshipCoverage['referencedPartNames'],
            'reachablePartNames' => $packagePartRelationshipCoverage['reachablePartNames'],
            'directOnlyPartNames' => $packagePartRelationshipCoverage['directOnlyPartNames'],
            'missingReferencedPartNames' => $packagePartRelationshipCoverage['missingReferencedPartNames'],
            'unreferencedPackagePartNames' => $packagePartRelationshipCoverage['unreferencedPackagePartNames'],
            'unreferencedRelationshipPartNames' => $packagePartRelationshipCoverage['unreferencedRelationshipPartNames'],
            'invalidPartNames' => $packagePartRelationshipCoverage['invalidPartNames'],
            'externalTargets' => $packagePartRelationshipCoverage['externalTargets'],
            'reachableExternalTargets' => $packagePartRelationshipCoverage['reachableExternalTargets'],
            'issueCounts' => $packagePartRelationshipCoverage['issueCounts'],
            'issues' => $packagePartRelationshipCoverage['issues'],
        ],
        'officeDocumentRelationshipReadiness' => [
            'valid' => $officeDocumentRelationshipReadiness['valid'],
            'issues' => $officeDocumentRelationshipReadiness['issues'],
            'documentPart' => $officeDocumentRelationshipReadiness['documentPart'],
            'documentRelationshipPartName' => $officeDocumentRelationshipReadiness['documentRelationshipPartName'],
            'documentRelationshipPartLoaded' => $officeDocumentRelationshipReadiness['documentRelationshipPartLoaded'],
            'relationshipRoleCount' => $officeDocumentRelationshipReadiness['relationshipRoleCount'],
            'relationshipRoleCounts' => $officeDocumentRelationshipReadiness['relationshipRoleCounts'],
            'invalidRelationshipRoleCount' => $officeDocumentRelationshipReadiness['invalidRelationshipRoleCount'],
            'invalidRelationshipRoleIssues' => $officeDocumentRelationshipReadiness['invalidRelationshipRoleIssues'],
            'relationshipClosure' => $officeDocumentRelationshipReadiness['relationshipClosure'],
        ],
        'relationshipRoleTargetPolicy' => [
            'valid' => $relationshipRoleTargetPolicy['valid'],
            'roleTargetCount' => $relationshipRoleTargetPolicy['roleTargetCount'],
            'validRoleTargetCount' => $relationshipRoleTargetPolicy['validRoleTargetCount'],
            'invalidRoleTargetCount' => $relationshipRoleTargetPolicy['invalidRoleTargetCount'],
            'roleCounts' => $relationshipRoleTargetPolicy['roleCounts'],
            'issueCounts' => $relationshipRoleTargetPolicy['issueCounts'],
            'issues' => $relationshipRoleTargetPolicy['issues'],
            'invalidRelationships' => $relationshipRoleTargetPolicyInvalidRelationships,
        ],
        'relationshipRolePolicySummary' => [
            'valid' => $relationshipRolePolicySummary['valid'],
            'knownRoleCount' => $relationshipRolePolicySummary['knownRoleCount'],
            'relationshipCount' => $relationshipRolePolicySummary['relationshipCount'],
            'validPolicyCount' => $relationshipRolePolicySummary['validPolicyCount'],
            'invalidPolicyCount' => $relationshipRolePolicySummary['invalidPolicyCount'],
            'packageScopedCount' => $relationshipRolePolicySummary['packageScopedCount'],
            'sourceScopedCount' => $relationshipRolePolicySummary['sourceScopedCount'],
            'unscopedCount' => $relationshipRolePolicySummary['unscopedCount'],
            'packageSingletonCount' => $relationshipRolePolicySummary['packageSingletonCount'],
            'sourceSingletonCount' => $relationshipRolePolicySummary['sourceSingletonCount'],
            'issueCounts' => $relationshipRolePolicySummary['issueCounts'],
            'issues' => $relationshipRolePolicySummary['issues'],
            'invalidRoles' => $relationshipRolePolicyInvalidRoles,
        ],
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
        'externalTargetPercentGuards' => array_values($externalTargetPercentGuards),
        'alternativeFormatImportPolicy' => $alternativeFormatImportPolicyGuard,
        'digitalSignatureParts' => $digitalSignatureParts,
        'digitalSignatureRoleIssues' => array_values(array_filter(
            $digitalSignatureRelationshipRoles['roles'],
            static fn (array $role): bool => $role['issues'] !== []
        )),
        'digitalSignatureCertificateCount' => $digitalSignatureMetadata['certificateCount'],
        'digitalSignatureTime' => $digitalSignatureMetadata['objects'][0]['signatureTimeValue'] ?? null,
        'digitalSignatureObjectPolicy' => [
            'objectIds' => $digitalSignatureMetadata['objectIds'],
            'duplicateObjectIds' => $digitalSignatureMetadata['duplicateObjectIds'],
            'signaturePropertyTargets' => array_values(array_map(
                static fn (array $target): array => [
                    'target' => $target['target'],
                    'targetKind' => $target['targetKind'],
                    'targetFragment' => $target['targetFragment'],
                    'targetMatched' => $target['targetMatched'],
                    'targetMatchedElementNames' => $target['targetMatchedElementNames'],
                    'valid' => $target['valid'],
                    'issues' => $target['issues'],
                ],
                $digitalSignatureMetadata['objects'][0]['signaturePropertyTargets'] ?? []
            )),
            'manifestIds' => $digitalSignatureMetadata['objects'][0]['manifestIds'] ?? [],
            'duplicateManifestIds' => $digitalSignatureMetadata['objects'][0]['duplicateManifestIds'] ?? [],
            'missingManifestIdCount' => $digitalSignatureMetadata['objects'][0]['missingManifestIdCount'] ?? 0,
        ],
        'digitalSignatureManifestReferences' => array_values(array_map(
            static fn (array $reference): array => [
                'manifestId' => $reference['manifestId'],
                'uri' => $reference['uri'],
                'targetPart' => $reference['targetPart'],
                'contentType' => $reference['contentType'],
                'relationshipTransformTargetMatched' => $reference['relationshipTransformTargetMatched'],
                'relationshipTransformTargetMatchCount' => $reference['relationshipTransformTargetMatchCount'],
                'relationshipTransformPayloadByteCounts' => $reference['relationshipTransformPayloadByteCounts'],
                'relationshipTransformPayloadSha256s' => $reference['relationshipTransformPayloadSha256s'],
                'relationshipTransformTargetMatches' => array_values(array_map(
                    static fn (array $match): array => [
                        'relationshipPartName' => $match['relationshipPartName'],
                        'source' => $match['source'],
                        'id' => $match['id'],
                        'targetPart' => $match['targetPart'],
                        'selectedBySourceId' => $match['selectedBySourceId'],
                        'selectedBySourceType' => $match['selectedBySourceType'],
                        'relationshipValid' => $match['relationshipValid'],
                        'transformValid' => $match['transformValid'],
                        'relationshipXmlBytes' => $match['relationshipXmlBytes'],
                        'relationshipXmlSha256' => $match['relationshipXmlSha256'],
                    ],
                    $reference['relationshipTransformTargetMatches']
                )),
                'digestAlgorithm' => $reference['digestAlgorithm'],
                'digestValueDecodedBytes' => $reference['digestValueDecodedBytes'],
                'valid' => $reference['valid'],
                'issues' => $reference['issues'],
            ],
            $digitalSignatureMetadata['objects'][0]['manifestReferences'] ?? []
        )),
        'digitalSignatureSignedInfoReferences' => array_values(array_map(
            static fn (array $reference): array => [
                'uri' => $reference['uri'],
                'targetPart' => $reference['targetPart'],
                'contentType' => $reference['contentType'],
                'sameDocumentReference' => $reference['sameDocumentReference'],
                'sameDocumentFragment' => $reference['sameDocumentFragment'],
                'sameDocumentTargetMatched' => $reference['sameDocumentTargetMatched'],
                'sameDocumentTargetMatchCount' => $reference['sameDocumentTargetMatchCount'],
                'sameDocumentTargetMatchedElementNames' => $reference['sameDocumentTargetMatchedElementNames'],
                'relationshipPart' => $reference['relationshipPart'],
                'referenceContentType' => $reference['referenceContentType'],
                'referenceContentTypeMatches' => $reference['referenceContentTypeMatches'],
                'relationshipTransformIndexes' => $reference['relationshipTransformIndexes'],
                'canonicalizationTransformIndexes' => $reference['canonicalizationTransformIndexes'],
                'relationshipTransformCount' => $reference['relationshipTransformCount'],
                'canonicalizationTransformCount' => $reference['canonicalizationTransformCount'],
                'canonicalizationTransformAlgorithms' => $reference['canonicalizationTransformAlgorithms'],
                'canonicalizationTransforms' => $reference['canonicalizationTransforms'],
                'relationshipTransformFollowingCanonicalization' => $reference['relationshipTransformFollowingCanonicalization'],
                'relationshipTransformFollowedByCanonicalization' => $reference['relationshipTransformFollowedByCanonicalization'],
                'digestAlgorithm' => $reference['digestAlgorithm'],
                'digestValueDecodedBytes' => $reference['digestValueDecodedBytes'],
                'valid' => $reference['valid'],
                'issues' => $reference['issues'],
            ],
            $digitalSignatureSignedInfoReferences
        )),
        'signatureEnvelopedTransformGuard' => $signatureEnvelopedTransformGuards[0] ?? null,
        'signatureEnvelopedSignedInfoReferenceGuard' => isset($signatureEnvelopedSignedInfoReferences[0])
            ? [
                'uri' => $signatureEnvelopedSignedInfoReferences[0]['uri'],
                'targetPart' => $signatureEnvelopedSignedInfoReferences[0]['targetPart'],
                'relationshipPart' => $signatureEnvelopedSignedInfoReferences[0]['relationshipPart'],
                'transformAlgorithms' => $signatureEnvelopedSignedInfoReferences[0]['transformAlgorithms'],
                'relationshipTransformIndexes' => $signatureEnvelopedSignedInfoReferences[0]['relationshipTransformIndexes'],
                'canonicalizationTransformIndexes' => $signatureEnvelopedSignedInfoReferences[0]['canonicalizationTransformIndexes'],
                'relationshipTransformCount' => $signatureEnvelopedSignedInfoReferences[0]['relationshipTransformCount'],
                'canonicalizationTransformCount' => $signatureEnvelopedSignedInfoReferences[0]['canonicalizationTransformCount'],
                'relationshipTransformFollowedByCanonicalization' => $signatureEnvelopedSignedInfoReferences[0]['relationshipTransformFollowedByCanonicalization'],
                'valid' => $signatureEnvelopedSignedInfoReferences[0]['valid'],
                'issues' => $signatureEnvelopedSignedInfoReferences[0]['issues'],
            ]
            : null,
        'encryptedPackages' => array_values(array_map(
            static fn (array $encryptedPackage): array => [
                'id' => $encryptedPackage['id'],
                'source' => $encryptedPackage['source'],
                'targetPart' => $encryptedPackage['targetPart'],
                'contentType' => $encryptedPackage['contentType'],
                'expectedContentType' => $encryptedPackage['expectedContentType'],
                'sourceAllowed' => $encryptedPackage['sourceAllowed'],
                'valid' => $encryptedPackage['valid'],
                'issues' => $encryptedPackage['issues'],
            ],
            $encryptedPackages
        )),
        'embeddedPackageParts' => $embeddedPackageParts,
        'embeddedObjectParts' => $embeddedObjectParts,
        'nestedEmbeddedOfficeDocuments' => $nestedEmbeddedOfficeDocuments,
        'nestedEmbeddedRelationshipClosures' => $nestedEmbeddedRelationshipClosures,
        'internalSourceReferences' => $internalSourceReferences,
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
        15,
        3,
        'external-target-network-path-base-uri',
        'external-target-unsafe-scheme',
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
    $relationshipTransformXml = (string) ($summary['relationshipTransform']['relationshipXml'] ?? '');
    $relationshipTransformXmlBytes = strlen($relationshipTransformXml);
    $relationshipTransformXmlSha256 = hash('sha256', $relationshipTransformXml);
    $signatureRelationshipTransformXml = (string) ($summary['signatureRelationshipTransforms'][0]['relationshipXml'] ?? '');
    $signatureRelationshipTransformXmlBytes = strlen($signatureRelationshipTransformXml);
    $signatureRelationshipTransformXmlSha256 = hash('sha256', $signatureRelationshipTransformXml);
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
        || ($summary['embeddedPackageGraphs'][0]['nestedRelationshipClosure']['expandedSourceCount'] ?? null) !== 2
        || ($summary['embeddedPackageGraphs'][0]['nestedRelationshipClosure']['stopCount'] ?? null) !== 1
        || ($summary['embeddedPackageGraphs'][0]['nestedRelationshipClosure']['stops'][0]['id'] ?? null) !== 'rIdSheet1'
        || ($summary['embeddedPackageGraphs'][0]['nestedRelationshipClosure']['stops'][0]['stopReason'] ?? null) !== 'target-source-not-loaded'
        || ($summary['embeddedPackageGraphs'][0]['valid'] ?? null) !== true
        || ($summary['embeddedPackageGraphs'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['id'] ?? null) !== 'rIdEmbeddedWorkbook'
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['packagePart'] ?? null) !== '/word/embeddings/source workbook.xlsx'
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['officeDocumentPart'] ?? null) !== '/xl/workbook.xml'
        || ($summary['wordpressImport']['nestedEmbeddedOfficeDocuments'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['id'] ?? null) !== 'rIdEmbeddedWorkbook'
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['packagePart'] ?? null) !== '/word/embeddings/source workbook.xlsx'
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['expandedSourceCount'] ?? null) !== 2
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['stopCount'] ?? null) !== 1
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['unloadedStopCount'] ?? null) !== 1
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['sources'][1]['source'] ?? null) !== '/xl/workbook.xml'
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['stops'][0]['id'] ?? null) !== 'rIdSheet1'
        || ($summary['wordpressImport']['nestedEmbeddedRelationshipClosures'][0]['stops'][0]['stopReason'] ?? null) !== 'target-source-not-loaded'
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
        || ($summary['wordpressImport']['customXmlPropertyParts'] ?? null) !== ['/customXml/itemProps1.xml']
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['id'] ?? null) !== 'rIdReviewSourceProperties'
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['source'] ?? null) !== '/word/review source.xml'
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['sourceContentType'] ?? null) !== 'application/xml'
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['targetPart'] ?? null) !== '/customXml/itemProps1.xml'
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.customXmlProperties+xml'
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['expectedContentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.customXmlProperties+xml'
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['expectedSourceContentTypes'] ?? null) !== ['application/xml']
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['external'] ?? null) !== false
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['customXmlPropertyRelationships'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['id'] ?? null) !== 'rIdReviewSourceProperties'
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['source'] ?? null) !== '/word/review source.xml'
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['targetPart'] ?? null) !== '/customXml/itemProps1.xml'
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.customXmlProperties+xml'
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['rootName'] ?? null) !== 'datastoreItem'
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['rootNamespace'] ?? null) !== OpcRelationshipGraph::CUSTOM_XML_DATA_STORE_NAMESPACE_URI
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['itemId'] ?? null) !== '{11111111-2222-3333-4444-555555555555}'
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['itemIdValid'] ?? null) !== true
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['schemaRefCount'] ?? null) !== 2
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['schemaRefUris'] ?? null) !== ['urn:wordpress:review-packet', 'https://example.test/schema/review.xsd']
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['customXmlPropertyPayloads'][0]['issues'] ?? null) !== []
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
        || ($summary['digitalSignatureRelationshipRoles']['valid'] ?? null) !== true
        || ($summary['digitalSignatureRelationshipRoles']['originCount'] ?? null) !== 1
        || ($summary['digitalSignatureRelationshipRoles']['signatureCount'] ?? null) !== 1
        || ($summary['digitalSignatureRelationshipRoles']['allowedSignatureSources'] ?? null) !== ['/_xmlsignatures/origin.sigs']
        || ($summary['digitalSignatureRelationshipRoles']['roles'][0]['source'] ?? null) !== '/'
        || ($summary['digitalSignatureRelationshipRoles']['roles'][0]['role'] ?? null) !== 'digital-signature-origin'
        || ($summary['digitalSignatureRelationshipRoles']['roles'][0]['sourceAllowed'] ?? null) !== true
        || ($summary['digitalSignatureRelationshipRoles']['roles'][0]['valid'] ?? null) !== true
        || ($summary['digitalSignatureRelationshipRoles']['roles'][1]['source'] ?? null) !== '/_xmlsignatures/origin.sigs'
        || ($summary['digitalSignatureRelationshipRoles']['roles'][1]['role'] ?? null) !== 'digital-signature-signature'
        || ($summary['digitalSignatureRelationshipRoles']['roles'][1]['targetPart'] ?? null) !== '/_xmlsignatures/sig1.xml'
        || ($summary['digitalSignatureRelationshipRoles']['roles'][1]['sourceAllowed'] ?? null) !== true
        || ($summary['digitalSignatureRelationshipRoles']['roles'][1]['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['signaturePart'] ?? null) !== '/_xmlsignatures/sig1.xml'
        || ($summary['digitalSignatureMetadata']['objectCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['objectIds'] ?? null) !== ['idPackageSignatureObject']
        || ($summary['digitalSignatureMetadata']['duplicateObjectIds'] ?? null) !== []
        || ($summary['digitalSignatureMetadata']['certificateCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['issues'] ?? null) !== []
        || ($summary['digitalSignatureMetadata']['objects'][0]['id'] ?? null) !== 'idPackageSignatureObject'
        || ($summary['digitalSignatureMetadata']['objects'][0]['idDuplicate'] ?? null) !== false
        || ($summary['digitalSignatureMetadata']['objects'][0]['idOccurrenceCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['objects'][0]['signatureTimeValue'] ?? null) !== '2026-06-06T22:33:48Z'
        || ($summary['digitalSignatureMetadata']['objects'][0]['signatureTimeValid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['signaturePropertyCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['objects'][0]['signaturePropertyTargetCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['objects'][0]['signaturePropertyTargets'][0]['target'] ?? null) !== '#idPackageSignature'
        || ($summary['digitalSignatureMetadata']['objects'][0]['signaturePropertyTargets'][0]['targetMatched'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['signaturePropertyTargets'][0]['targetMatchedElementNames'] ?? null) !== ['Signature']
        || ($summary['digitalSignatureMetadata']['objects'][0]['signaturePropertyTargets'][0]['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['packageSignatureElements'] ?? null) !== ['SignatureTime', 'Format', 'Value']
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestIds'] ?? null) !== ['manifestPackageParts']
        || ($summary['digitalSignatureMetadata']['objects'][0]['duplicateManifestIds'] ?? null) !== []
        || ($summary['digitalSignatureMetadata']['objects'][0]['missingManifestIdCount'] ?? null) !== 0
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferenceCount'] ?? null) !== 3
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['objectIds'] ?? null) !== ['idPackageSignatureObject']
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['duplicateObjectIds'] ?? null) !== []
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['signaturePropertyTargets'][0]['targetFragment'] ?? null) !== 'idPackageSignature'
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['signaturePropertyTargets'][0]['targetMatchedElementNames'] ?? null) !== ['Signature']
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['manifestIds'] ?? null) !== ['manifestPackageParts']
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['duplicateManifestIds'] ?? null) !== []
        || ($summary['wordpressImport']['digitalSignatureObjectPolicy']['missingManifestIdCount'] ?? null) !== 0
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['manifestId'] ?? null) !== 'manifestPackageParts'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['uri'] ?? null) !== '/word/document.xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['digestAlgorithm'] ?? null) !== 'http://www.w3.org/2001/04/xmlenc#sha256'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['digestValueDecodedBytes'] ?? null) !== 5
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['relationshipTransformTargetMatched'] ?? null) !== false
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['relationshipTransformTargetMatchCount'] ?? null) !== 0
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][0]['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['uri'] ?? null) !== '/docProps/core.xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['targetPart'] ?? null) !== '/docProps/core.xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.core-properties+xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['digestAlgorithm'] ?? null) !== 'http://www.w3.org/2000/09/xmldsig#sha1'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['digestValueDecodedBytes'] ?? null) !== 6
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['relationshipTransformTargetMatched'] ?? null) !== false
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['relationshipTransformTargetMatchCount'] ?? null) !== 0
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][1]['valid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['uri'] ?? null) !== '/word/media/hero%20image.PNG'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['targetPart'] ?? null) !== '/word/media/hero image.PNG'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['contentType'] ?? null) !== 'image/png'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['digestAlgorithm'] ?? null) !== 'http://www.w3.org/2001/04/xmlenc#sha256'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['digestValueDecodedBytes'] ?? null) !== 3
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatched'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatchCount'] ?? null) !== 1
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformPayloadByteCounts'] ?? null) !== [$signatureRelationshipTransformXmlBytes]
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformPayloadSha256s'] ?? null) !== [$signatureRelationshipTransformXmlSha256]
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['id'] ?? null) !== 'rIdHero'
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['selectedBySourceId'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['selectedBySourceType'] ?? null) !== false
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipValid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['transformValid'] ?? null) !== true
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipXmlBytes'] ?? null) !== $signatureRelationshipTransformXmlBytes
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipXmlSha256'] ?? null) !== $signatureRelationshipTransformXmlSha256
        || ($summary['digitalSignatureMetadata']['objects'][0]['manifestReferences'][2]['valid'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][0]['uri'] ?? null) !== '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['digitalSignatureSignedInfoReferences'][0]['targetPart'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['digitalSignatureSignedInfoReferences'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['digitalSignatureSignedInfoReferences'][0]['relationshipPart'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][0]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['digitalSignatureSignedInfoReferences'][0]['referenceContentTypeMatches'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][0]['transformAlgorithms'] ?? null) !== [
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
        ]
        || ($summary['digitalSignatureSignedInfoReferences'][0]['relationshipTransformIndexes'] ?? null) !== [0]
        || ($summary['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransformIndexes'] ?? null) !== [1]
        || ($summary['digitalSignatureSignedInfoReferences'][0]['relationshipTransformCount'] ?? null) !== 1
        || ($summary['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransformCount'] ?? null) !== 1
        || ($summary['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransformAlgorithms'] ?? null) !== ['http://www.w3.org/TR/2001/REC-xml-c14n-20010315']
        || ($summary['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransforms'][0]['profile'] ?? null) !== 'inclusive-c14n-1.0'
        || ($summary['digitalSignatureSignedInfoReferences'][0]['relationshipTransformFollowingCanonicalization']['withComments'] ?? null) !== false
        || ($summary['digitalSignatureSignedInfoReferences'][0]['relationshipTransformFollowedByCanonicalization'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][0]['digestAlgorithm'] ?? null) !== 'http://www.w3.org/2001/04/xmlenc#sha256'
        || ($summary['digitalSignatureSignedInfoReferences'][0]['digestValueDecodedBytes'] ?? null) !== 5
        || ($summary['digitalSignatureSignedInfoReferences'][0]['valid'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][0]['issues'] ?? null) !== []
        || ($summary['digitalSignatureSignedInfoReferences'][1]['uri'] ?? null) !== '#manifestPackageParts'
        || ($summary['digitalSignatureSignedInfoReferences'][1]['targetPart'] ?? null) !== null
        || ($summary['digitalSignatureSignedInfoReferences'][1]['contentType'] ?? null) !== null
        || ($summary['digitalSignatureSignedInfoReferences'][1]['sameDocumentReference'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][1]['sameDocumentFragment'] ?? null) !== 'manifestPackageParts'
        || ($summary['digitalSignatureSignedInfoReferences'][1]['sameDocumentTargetMatched'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][1]['sameDocumentTargetMatchCount'] ?? null) !== 1
        || ($summary['digitalSignatureSignedInfoReferences'][1]['sameDocumentTargetMatchedElementNames'] ?? null) !== ['Manifest']
        || ($summary['digitalSignatureSignedInfoReferences'][1]['relationshipPart'] ?? null) !== false
        || ($summary['digitalSignatureSignedInfoReferences'][1]['relationshipTransformCount'] ?? null) !== 0
        || ($summary['digitalSignatureSignedInfoReferences'][1]['digestAlgorithm'] ?? null) !== 'http://www.w3.org/2001/04/xmlenc#sha256'
        || ($summary['digitalSignatureSignedInfoReferences'][1]['digestValueDecodedBytes'] ?? null) !== 5
        || ($summary['digitalSignatureSignedInfoReferences'][1]['valid'] ?? null) !== true
        || ($summary['digitalSignatureSignedInfoReferences'][1]['issues'] ?? null) !== []
        || ($summary['digitalSignatureMetadata']['certificates'][0]['decodedBytes'] ?? null) !== 17
        || ($summary['digitalSignatureMetadata']['certificates'][0]['sha256'] ?? null) !== '339af39211d5f1a9de3c16e229830accd22d7063980248a5ea57edf61cac6c6d'
        || ($summary['digitalSignatureMetadata']['certificates'][0]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureRoleIssues'] ?? null) !== []
        || ($summary['wordpressImport']['digitalSignatureCertificateCount'] ?? null) !== 1
        || ($summary['wordpressImport']['digitalSignatureTime'] ?? null) !== '2026-06-06T22:33:48Z'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][0]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][0]['relationshipTransformTargetMatched'] ?? null) !== false
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][1]['targetPart'] ?? null) !== '/docProps/core.xml'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][1]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.core-properties+xml'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][1]['relationshipTransformTargetMatched'] ?? null) !== false
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['targetPart'] ?? null) !== '/word/media/hero image.PNG'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['contentType'] ?? null) !== 'image/png'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatched'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatchCount'] ?? null) !== 1
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformPayloadByteCounts'] ?? null) !== [$signatureRelationshipTransformXmlBytes]
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformPayloadSha256s'] ?? null) !== [$signatureRelationshipTransformXmlSha256]
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['id'] ?? null) !== 'rIdHero'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['selectedBySourceId'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['selectedBySourceType'] ?? null) !== false
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipXmlBytes'] ?? null) !== $signatureRelationshipTransformXmlBytes
        || ($summary['wordpressImport']['digitalSignatureManifestReferences'][2]['relationshipTransformTargetMatches'][0]['relationshipXmlSha256'] ?? null) !== $signatureRelationshipTransformXmlSha256
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['targetPart'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['relationshipPart'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['relationshipTransformIndexes'] ?? null) !== [0]
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransformIndexes'] ?? null) !== [1]
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['relationshipTransformCount'] ?? null) !== 1
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransformCount'] ?? null) !== 1
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransformAlgorithms'] ?? null) !== ['http://www.w3.org/TR/2001/REC-xml-c14n-20010315']
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['canonicalizationTransforms'][0]['profile'] ?? null) !== 'inclusive-c14n-1.0'
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['relationshipTransformFollowingCanonicalization']['exclusive'] ?? null) !== false
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['relationshipTransformFollowedByCanonicalization'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['digestAlgorithm'] ?? null) !== 'http://www.w3.org/2001/04/xmlenc#sha256'
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['digestValueDecodedBytes'] ?? null) !== 5
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['uri'] ?? null) !== '#manifestPackageParts'
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['sameDocumentReference'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['sameDocumentFragment'] ?? null) !== 'manifestPackageParts'
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['sameDocumentTargetMatched'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['sameDocumentTargetMatchCount'] ?? null) !== 1
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['sameDocumentTargetMatchedElementNames'] ?? null) !== ['Manifest']
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['relationshipPart'] ?? null) !== false
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['relationshipTransformCount'] ?? null) !== 0
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['digitalSignatureSignedInfoReferences'][1]['issues'] ?? null) !== []
        || ($summary['encryptedPackages'][0]['id'] ?? null) !== 'rIdEncryptedPackage'
        || ($summary['encryptedPackages'][0]['role'] ?? null) !== 'encrypted-package'
        || ($summary['encryptedPackages'][0]['source'] ?? null) !== '/'
        || ($summary['encryptedPackages'][0]['targetPart'] ?? null) !== '/EncryptedPackage'
        || ($summary['encryptedPackages'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.encrypted-package'
        || ($summary['encryptedPackages'][0]['expectedContentType'] ?? null) !== 'application/vnd.openxmlformats-package.encrypted-package'
        || ($summary['encryptedPackages'][0]['expectedExternal'] ?? null) !== false
        || ($summary['encryptedPackages'][0]['sourceAllowed'] ?? null) !== true
        || ($summary['encryptedPackages'][0]['valid'] ?? null) !== true
        || ($summary['encryptedPackages'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['encryptedPackages'][0]['id'] ?? null) !== 'rIdEncryptedPackage'
        || ($summary['wordpressImport']['encryptedPackages'][0]['targetPart'] ?? null) !== '/EncryptedPackage'
        || ($summary['wordpressImport']['encryptedPackages'][0]['contentType'] ?? null) !== 'application/vnd.openxmlformats-package.encrypted-package'
        || ($summary['wordpressImport']['encryptedPackages'][0]['expectedContentType'] ?? null) !== 'application/vnd.openxmlformats-package.encrypted-package'
        || ($summary['wordpressImport']['encryptedPackages'][0]['sourceAllowed'] ?? null) !== true
        || ($summary['wordpressImport']['encryptedPackages'][0]['valid'] ?? null) !== true
        || ($summary['wordpressImport']['encryptedPackages'][0]['issues'] ?? null) !== []
        || ($summary['integrity']['emptySignatureOriginGuard']['id'] ?? null) !== 'rIdSignatureOrigin'
        || ($summary['integrity']['emptySignatureOriginGuard']['targetPart'] ?? null) !== '/_xmlsignatures/origin.sigs'
        || ($summary['integrity']['emptySignatureOriginGuard']['relationshipPartName'] ?? null) !== '/_xmlsignatures/_rels/origin.sigs.rels'
        || ($summary['integrity']['emptySignatureOriginGuard']['signatureCount'] ?? null) !== 0
        || ($summary['integrity']['emptySignatureOriginGuard']['valid'] ?? null) !== false
        || ($summary['integrity']['emptySignatureOriginGuard']['issues'] ?? null) !== ['missing-digital-signature-signature-relationships']
        || ($summary['integrity']['invalidSignatureOriginGuard']['allowedSignatureSources'] ?? null) !== []
        || ($summary['integrity']['invalidSignatureOriginGuard']['originValid'] ?? null) !== false
        || ($summary['integrity']['invalidSignatureOriginGuard']['originIssues'] ?? null) !== ['invalid-digital-signature-origin-content-type']
        || ($summary['integrity']['invalidSignatureOriginGuard']['signatureSourceAllowed'] ?? null) !== false
        || ($summary['integrity']['invalidSignatureOriginGuard']['signatureValid'] ?? null) !== false
        || ($summary['integrity']['invalidSignatureOriginGuard']['signatureIssues'] ?? null) !== ['digital-signature-signature-source-not-origin']
        || ($summary['relationships']['rIdInternalBookmark']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['relationships']['rIdInternalReviewState']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['id'] ?? null) !== 'rIdInternalBookmark'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['targetQuery'] ?? null) !== null
        || ($summary['wordpressImport']['internalSourceReferences'][0]['targetFragment'] ?? null) !== 'review-bookmark'
        || ($summary['wordpressImport']['internalSourceReferences'][0]['sameSourceReference'] ?? null) !== true
        || ($summary['wordpressImport']['internalSourceReferences'][0]['issues'] ?? null) !== []
        || ($summary['wordpressImport']['internalSourceReferences'][1]['id'] ?? null) !== 'rIdInternalReviewState'
        || ($summary['wordpressImport']['internalSourceReferences'][1]['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['internalSourceReferences'][1]['targetQuery'] ?? null) !== 'review=ready'
        || ($summary['wordpressImport']['internalSourceReferences'][1]['targetFragment'] ?? null) !== 'packet'
        || ($summary['wordpressImport']['internalSourceReferences'][1]['sameSourceReference'] ?? null) !== true
        || ($summary['wordpressImport']['internalSourceReferences'][1]['issues'] ?? null) !== []
        || ($summary['packageConsistency']['valid'] ?? null) !== false
        || ($summary['packageConsistency']['packagePartsValid'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverridesValid'] ?? null) !== false
        || ($summary['packageConsistency']['relationshipTargetsValid'] ?? null) !== false
        || ($summary['packageConsistency']['relationshipTypePoliciesValid'] ?? null) !== true
        || ($summary['packageConsistency']['summary'] ?? null) !== ($summary['wordpressImport']['packageConsistencySummary'] ?? null)
        || ($summary['packageConsistency']['summary']['packagePartCount'] ?? null) !== 34
        || ($summary['packageConsistency']['summary']['invalidPackagePartCount'] ?? null) !== 1
        || ($summary['packageConsistency']['summary']['contentTypeOverrideCount'] ?? null) !== 25
        || ($summary['packageConsistency']['summary']['invalidContentTypeOverrideCount'] ?? null) !== 2
        || ($summary['packageConsistency']['summary']['relationshipTargetCount'] ?? null) !== 26
        || ($summary['packageConsistency']['summary']['invalidRelationshipTargetCount'] ?? null) !== 3
        || ($summary['packageConsistency']['summary']['relationshipTypePolicyCount'] ?? null) !== 10
        || ($summary['packageConsistency']['summary']['invalidRelationshipTypePolicyCount'] ?? null) !== 0
        || ($summary['packageConsistency']['summary']['invalidPackagePartNames'] ?? null) !== ['/word/_rels/draft.xml.rels']
        || ($summary['packageConsistency']['summary']['invalidContentTypeOverrideParts'] ?? null) !== ['/word/_rels/draft.xml.rels', '/word/media/stale source.png']
        || ($summary['packageConsistency']['summary']['invalidRelationshipTargetKeys'] ?? null) !== [
            '/word/document.xml:rIdMalformedType',
            '/word/document.xml:rIdSchemeRelativeReviewer',
            '/word/document.xml:rIdUnsafeReviewer',
        ]
        || ($summary['packageConsistency']['summary']['issueCounts'] ?? null) !== [
            'external-target-network-path-base-uri' => 1,
            'external-target-unsafe-scheme' => 1,
            'invalid-relationship-content-type' => 2,
            'override-target-missing-part' => 1,
            'relationship-type-not-absolute-uri' => 1,
        ]
        || ($summary['packageConsistency']['summary']['sectionIssueCounts']['relationshipTargets'] ?? null) !== [
            'external-target-network-path-base-uri' => 1,
            'external-target-unsafe-scheme' => 1,
            'relationship-type-not-absolute-uri' => 1,
        ]
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['knownRole'] ?? null) !== 'office-document'
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['policyValid'] ?? null) !== true
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['policyIssues'] ?? null) !== []
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['knownRole'] ?? null) !== 'encrypted-package'
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['relationshipCount'] ?? null) !== 1
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['policyValid'] ?? null) !== true
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['policyIssues'] ?? null) !== []
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['knownRole'] ?? null) !== 'thumbnail'
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['relationshipCount'] ?? null) !== 1
        || ($summary['packageConsistency']['relationshipTypePolicies'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['policyValid'] ?? null) !== true
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/media/stale source.png']['exists'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/media/stale source.png']['issues'] ?? null) !== ['override-target-missing-part']
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/_rels/draft.xml.rels']['relationshipSourceLoaded'] ?? null) !== false
        || ($summary['packageConsistency']['contentTypeOverrides']['/word/_rels/draft.xml.rels']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdCore']['targetPart'] ?? null) !== '/docProps/core.xml'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['targetPart'] ?? null) !== '/docProps/thumbnail.png'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['contentType'] ?? null) !== 'image/png'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['contentTypeSource'] ?? null) !== 'override'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['contentTypeOverridePartName'] ?? null) !== '/docProps/thumbnail.png'
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdThumbnail']['issues'] ?? null) !== []
        || ($summary['packageConsistency']['relationshipTargets']['/:rIdSignatureOrigin']['targetPart'] ?? null) !== '/_xmlsignatures/origin.sigs'
        || ($summary['packageConsistency']['relationshipTargets']['/word/document.xml:rIdHero']['contentTypeSource'] ?? null) !== 'default'
        || ($summary['packageConsistency']['relationshipTargets']['/word/document.xml:rIdHero']['contentTypeDefaultExtension'] ?? null) !== 'png'
        || ($summary['packageConsistency']['relationshipTargets']['/word/review source.xml:rIdReviewSourceProperties']['targetPart'] ?? null) !== '/customXml/itemProps1.xml'
        || ($summary['packageConsistency']['relationshipTargets']['/word/review source.xml:rIdReviewSourceProperties']['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.customXmlProperties+xml'
        || ($summary['packageConsistency']['relationshipTargets']['/word/review source.xml:rIdReviewSourceProperties']['issues'] ?? null) !== []
        || ($summary['packageConsistency']['relationshipTargets']['/word/review source.xml:rIdReviewSourceImage']['targetPart'] ?? null) !== '/word/media/review source.png'
        || isset($summary['packageConsistency']['relationshipTargets']['/word/draft.xml:rIdDraftImage'])
        || $summary['integrity']['packagePartsValid'] !== false
        || $summary['relationshipSources'] !== ['/', '/_xmlsignatures/origin.sigs', '/word/document.xml', '/word/footnotes.xml', '/word/review source.xml']
        || ($summary['relationshipSourceClosure']['source'] ?? null) !== '/'
        || ($summary['relationshipSourceClosure']['relationshipType'] ?? null) !== OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
        || ($summary['relationshipSourceClosure']['valid'] ?? null) !== false
        || ($summary['relationshipSourceClosure']['issues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme', 'relationship-type-not-absolute-uri']
        || ($summary['relationshipSourceClosure']['expandedSourceCount'] ?? null) !== 4
        || ($summary['relationshipSourceClosure']['outsideSourceCount'] ?? null) !== 1
        || ($summary['relationshipSourceClosure']['stopCount'] ?? null) !== 16
        || ($summary['relationshipSourceClosure']['externalStopCount'] ?? null) !== 5
        || ($summary['relationshipSourceClosure']['invalidStopCount'] ?? null) !== 0
        || ($summary['relationshipSourceClosure']['missingStopCount'] ?? null) !== 0
        || ($summary['relationshipSourceClosure']['relationshipPartStopCount'] ?? null) !== 0
        || ($summary['relationshipSourceClosure']['cycleStopCount'] ?? null) !== 2
        || ($summary['relationshipSourceClosure']['unloadedStopCount'] ?? null) !== 9
        || ($summary['relationshipSourceClosure']['sources']['/']['closureAction'] ?? null) !== 'expanded'
        || ($summary['relationshipSourceClosure']['sources']['/']['depth'] ?? null) !== 0
        || ($summary['relationshipSourceClosure']['sources']['/word/document.xml']['closureAction'] ?? null) !== 'expanded'
        || ($summary['relationshipSourceClosure']['sources']['/word/document.xml']['depth'] ?? null) !== 1
        || ($summary['relationshipSourceClosure']['sources']['/word/footnotes.xml']['depth'] ?? null) !== 2
        || ($summary['relationshipSourceClosure']['sources']['/word/review source.xml']['depth'] ?? null) !== 2
        || ($summary['relationshipSourceClosure']['sources']['/_xmlsignatures/origin.sigs']['reachable'] ?? null) !== false
        || ($summary['relationshipSourceClosure']['sources']['/_xmlsignatures/origin.sigs']['closureAction'] ?? null) !== 'outside-selected-closure'
        || ($summary['relationshipSourceClosure']['stops']['rIdStyles']['stopReason'] ?? null) !== 'target-source-not-loaded'
        || ($summary['relationshipSourceClosure']['stops']['rIdStyles']['targetPart'] ?? null) !== '/word/styles.xml'
        || ($summary['relationshipSourceClosure']['stops']['rIdReviewer']['stopReason'] ?? null) !== 'external-target'
        || ($summary['relationshipSourceClosure']['stops']['rIdReviewer']['valid'] ?? null) !== true
        || ($summary['relationshipSourceClosure']['stops']['rIdInternalBookmark']['stopReason'] ?? null) !== 'cycle-target'
        || ($summary['relationshipSourceClosure']['stops']['rIdInternalBookmark']['targetPart'] ?? null) !== '/word/document.xml'
        || ($summary['relationshipSourceClosure']['stops']['rIdDraftReview']['stopReason'] ?? null) !== 'target-source-not-loaded'
        || ($summary['relationshipSourceClosure']['stops']['rIdDraftReview']['targetPart'] ?? null) !== '/word/draft.xml'
        || ($summary['relationshipSourceClosure']['stops']['rIdUnsafeReviewer']['stopReason'] ?? null) !== 'external-target'
        || ($summary['relationshipSourceClosure']['stops']['rIdUnsafeReviewer']['issues'] ?? null) !== ['external-target-unsafe-scheme']
        || ($summary['relationshipSourceClosure']['stops']['rIdMalformedType']['issues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['wordpressImport']['relationshipClosureReview']['expandedSourceCount'] ?? null) !== 4
        || ($summary['wordpressImport']['relationshipClosureReview']['stopCount'] ?? null) !== 16
        || ($summary['wordpressImport']['relationshipClosureCoverage']['valid'] ?? null) !== false
        || ($summary['wordpressImport']['relationshipClosureCoverage']['sourceCount'] ?? null) !== 5
        || ($summary['wordpressImport']['relationshipClosureCoverage']['expandedSourceCount'] ?? null) !== 4
        || ($summary['wordpressImport']['relationshipClosureCoverage']['outsideSourceCount'] ?? null) !== 1
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopCount'] ?? null) !== 16
        || ($summary['wordpressImport']['relationshipClosureCoverage']['expandedSourceNames'] ?? null) !== ['/', '/word/document.xml', '/word/footnotes.xml', '/word/review source.xml']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['outsideSourceNames'] ?? null) !== ['/_xmlsignatures/origin.sigs']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['sourceDepths'] ?? null) !== ['/' => 0, '/word/document.xml' => 1, '/word/footnotes.xml' => 2, '/word/review source.xml' => 2]
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopReasonCounts'] ?? null) !== ['cycle-target' => 2, 'external-target' => 5, 'target-source-not-loaded' => 9]
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopIdsByReason']['cycle-target'] ?? null) !== ['rIdInternalBookmark', 'rIdInternalReviewState']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopIdsByReason']['external-target'] ?? null) !== ['rIdMalformedType', 'rIdRelativeReviewer', 'rIdReviewer', 'rIdSchemeRelativeReviewer', 'rIdUnsafeReviewer']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopIdsByReason']['target-source-not-loaded'] ?? null) !== ['rIdDiagram', 'rIdDraftReview', 'rIdEmbeddedOle', 'rIdEmbeddedWorkbook', 'rIdFootnoteImage', 'rIdHero', 'rIdReviewSourceImage', 'rIdReviewSourceProperties', 'rIdStyles']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopTargetsByReason']['cycle-target'] ?? null) !== ['/word/document.xml']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopTargetsByReason']['external-target'] ?? null) !== ['//cdn.example.test/review/source.html', 'https://example.test/source-with-bad-type', 'https://example.test/wp-admin/post.php?post=42&action=edit', 'javascript:alert(1)', 'review/source.html#packet']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['stopTargetsByReason']['target-source-not-loaded'] ?? null) !== ['/customXml/itemProps1.xml', '/word/draft.xml', '/word/embeddings/oleObject1.bin', '/word/embeddings/source workbook.xlsx', '/word/media/footnote-source.png', '/word/media/hero image.PNG', '/word/media/review source.png', '/word/media/source diagram.svg', '/word/styles.xml']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['invalidStopCount'] ?? null) !== 3
        || ($summary['wordpressImport']['relationshipClosureCoverage']['invalidStopIds'] ?? null) !== ['rIdMalformedType', 'rIdSchemeRelativeReviewer', 'rIdUnsafeReviewer']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['missingTargetParts'] ?? null) !== []
        || ($summary['wordpressImport']['relationshipClosureCoverage']['relationshipPartTargetParts'] ?? null) !== []
        || ($summary['wordpressImport']['relationshipClosureCoverage']['unloadedTargetSources'] ?? null) !== ['/customXml/itemProps1.xml', '/word/draft.xml', '/word/embeddings/oleObject1.bin', '/word/embeddings/source workbook.xlsx', '/word/media/footnote-source.png', '/word/media/hero image.PNG', '/word/media/review source.png', '/word/media/source diagram.svg', '/word/styles.xml']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['externalTargets'] ?? null) !== ['//cdn.example.test/review/source.html', 'https://example.test/source-with-bad-type', 'https://example.test/wp-admin/post.php?post=42&action=edit', 'javascript:alert(1)', 'review/source.html#packet']
        || ($summary['wordpressImport']['relationshipClosureCoverage']['issues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme', 'relationship-type-not-absolute-uri']
        || ($summary['officeDocumentRelationshipReadiness']['valid'] ?? null) !== false
        || ($summary['officeDocumentRelationshipReadiness']['issues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme', 'internal-hyperlink-target', 'invalid-custom-xml-content-type', 'relationship-type-not-absolute-uri']
        || ($summary['officeDocumentRelationshipReadiness']['documentPart'] ?? null) !== '/word/document.xml'
        || ($summary['officeDocumentRelationshipReadiness']['documentRelationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['officeDocumentRelationshipReadiness']['documentRelationshipPartLoaded'] ?? null) !== true
        || ($summary['officeDocumentRelationshipReadiness']['relationshipRoleCount'] ?? null) !== 12
        || ($summary['officeDocumentRelationshipReadiness']['relationshipRoleCounts'] ?? null) !== ['custom-xml' => 3, 'footnotes' => 1, 'hyperlink' => 5, 'image' => 2, 'styles' => 1]
        || ($summary['officeDocumentRelationshipReadiness']['invalidRelationshipRoleCount'] ?? null) !== 4
        || ($summary['officeDocumentRelationshipReadiness']['invalidRelationshipRoleIssues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme', 'internal-hyperlink-target', 'invalid-custom-xml-content-type']
        || ($summary['officeDocumentRelationshipReadiness']['relationshipClosure']['expandedSourceCount'] ?? null) !== 4
        || ($summary['officeDocumentRelationshipReadiness']['relationshipClosure']['stopCount'] ?? null) !== 16
        || ($summary['officeDocumentRelationshipReadiness']['relationshipClosure']['issues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme', 'relationship-type-not-absolute-uri']
        || ($summary['wordpressImport']['officeDocumentRelationshipReadiness']['documentPart'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['officeDocumentRelationshipReadiness']['relationshipRoleCount'] ?? null) !== 12
        || ($summary['wordpressImport']['officeDocumentRelationshipReadiness']['invalidRelationshipRoleCount'] ?? null) !== 4
        || ($summary['wordpressImport']['officeDocumentRelationshipReadiness']['relationshipClosure']['unloadedStopCount'] ?? null) !== 9
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['valid'] ?? null) !== false
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['roleTargetCount'] ?? null) !== 25
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['validRoleTargetCount'] ?? null) !== 21
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['invalidRoleTargetCount'] ?? null) !== 4
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['roleCounts'] ?? null) !== [
            'core-properties' => 1,
            'custom-properties' => 1,
            'custom-xml' => 3,
            'custom-xml-properties' => 1,
            'digital-signature-origin' => 1,
            'digital-signature-signature' => 1,
            'embedded-object' => 1,
            'embedded-package' => 1,
            'encrypted-package' => 1,
            'extended-properties' => 1,
            'footnotes' => 1,
            'hyperlink' => 5,
            'image' => 4,
            'office-document' => 1,
            'styles' => 1,
            'thumbnail' => 1,
        ]
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['issueCounts'] ?? null) !== [
            'external-target-network-path-base-uri' => 1,
            'external-target-unsafe-scheme' => 1,
            'internal-hyperlink-target' => 1,
            'invalid-custom-xml-content-type' => 1,
        ]
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['issues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme', 'internal-hyperlink-target', 'invalid-custom-xml-content-type']
        || array_column($summary['wordpressImport']['relationshipRoleTargetPolicy']['invalidRelationships'] ?? [], 'id') !== ['rIdInternalBookmark', 'rIdInternalReviewState', 'rIdSchemeRelativeReviewer', 'rIdUnsafeReviewer']
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['invalidRelationships'][0]['role'] ?? null) !== 'hyperlink'
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['invalidRelationships'][0]['expectedExternal'] ?? null) !== true
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['invalidRelationships'][1]['expectedContentType'] ?? null) !== 'application/xml'
        || ($summary['wordpressImport']['relationshipRoleTargetPolicy']['invalidRelationships'][1]['contentType'] ?? null) !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['valid'] ?? null) !== true
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['knownRoleCount'] ?? null) !== 10
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['relationshipCount'] ?? null) !== 10
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['validPolicyCount'] ?? null) !== 10
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['invalidPolicyCount'] ?? null) !== 0
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['packageScopedCount'] ?? null) !== 6
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['sourceScopedCount'] ?? null) !== 4
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['unscopedCount'] ?? null) !== 0
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['packageSingletonCount'] ?? null) !== 6
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['sourceSingletonCount'] ?? null) !== 4
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['issueCounts'] ?? null) !== []
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['issues'] ?? null) !== []
        || ($summary['wordpressImport']['relationshipRolePolicySummary']['invalidRoles'] ?? null) !== []
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['valid'] ?? null) !== false
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['inventoryPartCount'] ?? null) !== 34
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['packagePartCount'] ?? null) !== 34
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['relationshipPartCount'] ?? null) !== 6
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['relationshipSourcePartCount'] ?? null) !== 5
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['directReferencePartCount'] ?? null) !== 19
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['reachableReferencePartCount'] ?? null) !== 12
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['directOnlyPartCount'] ?? null) !== 7
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['missingReferencedPartCount'] ?? null) !== 0
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['unreferencedPackagePartCount'] ?? null) !== 9
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['unreferencedRelationshipPartCount'] ?? null) !== 6
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['invalidPartCount'] ?? null) !== 1
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['externalDirectReferenceCount'] ?? null) !== 5
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['externalReachableReferenceCount'] ?? null) !== 5
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['invalidExternalReferenceCount'] ?? null) !== 3
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['directOnlyPartNames'] ?? null) !== [
            '/EncryptedPackage',
            '/_xmlsignatures/origin.sigs',
            '/_xmlsignatures/sig1.xml',
            '/docProps/app.xml',
            '/docProps/core.xml',
            '/docProps/custom.xml',
            '/docProps/thumbnail.png',
        ]
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['unreferencedPackagePartNames'] ?? null) !== [
            '/_xmlsignatures/sig-dot-segments.xml',
            '/_xmlsignatures/sig-duplicate-selector.xml',
            '/_xmlsignatures/sig-enveloped-transform.xml',
            '/_xmlsignatures/sig-fragment.xml',
            '/_xmlsignatures/sig-missing-rels.xml',
            '/_xmlsignatures/sig-reference-uri-kinds.xml',
            '/_xmlsignatures/sig-selector-shape.xml',
            '/_xmlsignatures/sig-unsafe-reference.xml',
            '/word/media/draft-hidden.png',
        ]
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['invalidPartNames'] ?? null) !== ['/word/_rels/draft.xml.rels']
        || ($summary['wordpressImport']['packagePartRelationshipCoverage']['issueCounts'] ?? null) !== [
            'external-target-network-path-base-uri' => 1,
            'external-target-unsafe-scheme' => 1,
            'invalid-relationship-content-type' => 1,
            'relationship-type-not-absolute-uri' => 1,
        ]
        || ($summary['packagePartRelationshipCoverage']['parts'][0]['coverage'] ?? null) !== 'direct-only'
        || ($summary['packagePartRelationshipCoverage']['parts'][1]['coverage'] ?? null) !== 'unreferenced-relationship-part'
        || ($summary['packagePartRelationshipCoverage']['parts'][13]['coverage'] ?? null) !== 'direct-and-reachable'
        || ($summary['packagePartRelationshipCoverage']['parts'][27]['coverage'] ?? null) !== 'unreferenced-package-part'
        || ($summary['relationshipRolePolicySummary']['source'] ?? null) !== null
        || array_column($summary['relationshipRolePolicySummary']['roles'] ?? [], 'role') !== [
            'core-properties',
            'custom-properties',
            'custom-xml-properties',
            'digital-signature-origin',
            'encrypted-package',
            'extended-properties',
            'footnotes',
            'office-document',
            'styles',
            'thumbnail',
        ]
        || ($summary['relationshipRolePolicySummary']['roles'][7]['targetParts'] ?? null) !== ['/word/document.xml']
        || ($summary['relationshipRolePolicySummary']['roles'][8]['singletonScope'] ?? null) !== 'source'
        || ($summary['relationshipRolePolicySummary']['roles'][9]['contentTypes'] ?? null) !== ['image/png']
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
        || ($summary['relationshipPartLoads']['/_rels/.rels']['relationshipCount'] ?? null) !== 7
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['relationshipSource'] ?? null) !== '/word/document.xml'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loaded'] ?? null) !== true
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loadAction'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['loadReason'] ?? null) !== 'loaded'
        || ($summary['relationshipPartLoads']['/word/_rels/document.xml.rels']['relationshipCount'] ?? null) !== 15
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
        || ($summary['relationshipPartLoadSummary']['valid'] ?? null) !== false
        || ($summary['relationshipPartLoadSummary']['relationshipPartCount'] ?? null) !== 6
        || ($summary['relationshipPartLoadSummary']['loadedCount'] ?? null) !== 5
        || ($summary['relationshipPartLoadSummary']['skippedCount'] ?? null) !== 1
        || ($summary['relationshipPartLoadSummary']['validCount'] ?? null) !== 5
        || ($summary['relationshipPartLoadSummary']['invalidCount'] ?? null) !== 1
        || ($summary['relationshipPartLoadSummary']['relationshipCount'] ?? null) !== 26
        || ($summary['relationshipPartLoadSummary']['loadActionCounts'] ?? null) !== ['loaded' => 5, 'skipped' => 1]
        || ($summary['relationshipPartLoadSummary']['loadReasonCounts'] ?? null) !== ['invalid-relationship-content-type' => 1, 'loaded' => 5]
        || ($summary['relationshipPartLoadSummary']['issueCounts'] ?? null) !== ['invalid-relationship-content-type' => 1]
        || ($summary['relationshipPartLoadSummary']['partNamesByIssue']['invalid-relationship-content-type'] ?? null) !== ['/word/_rels/draft.xml.rels']
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['relationshipPartCount'] ?? null) !== 6
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['loadedCount'] ?? null) !== 5
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['skippedCount'] ?? null) !== 1
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['invalidCount'] ?? null) !== 1
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['relationshipCount'] ?? null) !== 26
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['loadReasonCounts'] ?? null) !== ['invalid-relationship-content-type' => 1, 'loaded' => 5]
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['issueCounts'] ?? null) !== ['invalid-relationship-content-type' => 1]
        || ($summary['wordpressImport']['relationshipPartLoadSummary']['issues'] ?? null) !== ['invalid-relationship-content-type']
        || ($summary['directRelationshipContentTypeGuard']['source'] ?? null) !== '/word/draft.xml'
        || ($summary['directRelationshipContentTypeGuard']['relationshipPartName'] ?? null) !== '/word/_rels/draft.xml.rels'
        || ($summary['directRelationshipContentTypeGuard']['hasRelationshipsForSource'] ?? null) !== false
        || ($summary['directRelationshipContentTypeGuard']['fromPackageRejected'] ?? null) !== true
        || !str_contains((string) ($summary['directRelationshipContentTypeGuard']['fromPackageError'] ?? ''), 'OPC relationship part not found: /word/_rels/draft.xml.rels')
        || ($summary['directRelationshipContentTypeGuard']['preflightLoadReason'] ?? null) !== 'invalid-relationship-content-type'
        || ($summary['directRelationshipContentTypeGuard']['preflightIssues'] ?? null) !== ['invalid-relationship-content-type']
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
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/document.xml']['packagePartName'] ?? null) !== '/Word/Document.XML'
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/document.xml']['partNameExactMatch'] ?? null) !== false
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/document.xml']['partNameEquivalentMatch'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/document.xml']['valid'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/document.xml']['issues'] ?? null) !== []
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/styles.xml']['packagePartName'] ?? null) !== '/Word/Styles.XML'
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/styles.xml']['partNameExactMatch'] ?? null) !== false
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/styles.xml']['partNameEquivalentMatch'] ?? null) !== true
        || ($summary['integrity']['caseEquivalentContentTypeOverrides']['/word/styles.xml']['valid'] ?? null) !== true
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
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['officeDocumentContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml; Profile=Docx'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['corePropertiesValid'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['corePropertiesContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Core-Properties+Xml; Audit=Core'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['digitalSignatureValid'] ?? null) !== true
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['digitalSignatureOriginContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Digital-Signature-Origin; Profile=OPC'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['digitalSignatureContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Digital-Signature-XmlSignature+Xml; Profile=OPC'
        || ($summary['integrity']['caseInsensitiveRoleContentTypes']['signatureReferenceTargetContentType'] ?? null) !== 'Application/Vnd.Openxmlformats-Package.Relationships+Xml; Charset=UTF-8'
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
        || ($summary['integrity']['relationshipSerializationGuard']['xmlContainsEscapedInternalTarget'] ?? null) !== true
        || ($summary['integrity']['relationshipSerializationGuard']['xmlOmitsRawInternalSpace'] ?? null) !== true
        || ($summary['integrity']['relationshipSerializationGuard']['xmlEscapesExternalAmpersand'] ?? null) !== true
        || ($summary['integrity']['relationshipSerializationGuard']['xmlOmitsInternalTargetMode'] ?? null) !== true
        || ($summary['integrity']['relationshipSerializationGuard']['xmlKeepsExternalTargetMode'] ?? null) !== true
        || ($summary['integrity']['relationshipSerializationGuard']['roundTripInternalTarget'] ?? null) !== "/word/media/review source \u{00E9}preuve.png#crop"
        || ($summary['integrity']['relationshipSerializationGuard']['roundTripExternalTarget'] ?? null) !== 'https://example.test/source.html?post=42&action=edit'
        || ($summary['integrity']['relationshipSerializationGuard']['internalTargetRejections'] ?? null) !== [
            'encodedSlash' => true,
            'encodedDotSegment' => true,
            'encodedBackslash' => true,
            'rootFragment' => true,
        ]
        || ($summary['integrity']['relationshipSerializationGuard']['externalTargetRejections'] ?? null) !== [
            'rawSpace' => true,
            'badPercentEscape' => true,
            'encodedControlByte' => true,
            'unsafeScheme' => true,
        ]
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
        || ($summary['nestedRelationshipPayloadSegmentGuard']['partName'] ?? null) !== '/word/_rels/media/document.xml.rels'
        || !array_key_exists('relationshipSource', $summary['nestedRelationshipPayloadSegmentGuard'] ?? [])
        || ($summary['nestedRelationshipPayloadSegmentGuard']['relationshipSource'] ?? null) !== null
        || !array_key_exists('sourceExists', $summary['nestedRelationshipPayloadSegmentGuard'] ?? [])
        || ($summary['nestedRelationshipPayloadSegmentGuard']['sourceExists'] ?? null) !== null
        || ($summary['nestedRelationshipPayloadSegmentGuard']['loaded'] ?? null) !== false
        || ($summary['nestedRelationshipPayloadSegmentGuard']['loadAction'] ?? null) !== 'skipped'
        || ($summary['nestedRelationshipPayloadSegmentGuard']['loadReason'] ?? null) !== 'invalid-relationship-part-name'
        || ($summary['nestedRelationshipPayloadSegmentGuard']['relationshipCount'] ?? null) !== null
        || ($summary['nestedRelationshipPayloadSegmentGuard']['valid'] ?? null) !== false
        || ($summary['nestedRelationshipPayloadSegmentGuard']['issues'] ?? null) !== ['invalid-relationship-part-name']
        || !str_contains((string) ($summary['nestedRelationshipPayloadSegmentGuard']['parseError'] ?? ''), 'single .rels file inside a _rels directory')
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['kind'] ?? null) !== 'relative-reference'
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['allowed'] ?? null) !== true
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['requiresBaseUri'] ?? null) !== true
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['rewriteBasePart'] ?? null) !== null
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['rewriteReason'] ?? null) !== 'external-target-relative-reference'
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['valid'] ?? null) !== false
        || ($summary['packageRootExternalTargetGuards']['rIdPackageRelative']['issues'] ?? null) !== ['external-target-package-root-base-uri']
        || ($summary['packageRootExternalTargetGuards']['rIdPackageFragment']['kind'] ?? null) !== 'fragment-reference'
        || ($summary['packageRootExternalTargetGuards']['rIdPackageFragment']['requiresBaseUri'] ?? null) !== true
        || ($summary['packageRootExternalTargetGuards']['rIdPackageFragment']['rewriteBasePart'] ?? null) !== null
        || ($summary['packageRootExternalTargetGuards']['rIdPackageFragment']['rewriteReason'] ?? null) !== 'external-target-fragment-reference'
        || ($summary['packageRootExternalTargetGuards']['rIdPackageFragment']['valid'] ?? null) !== false
        || ($summary['packageRootExternalTargetGuards']['rIdPackageFragment']['issues'] ?? null) !== ['external-target-package-root-base-uri']
        || ($summary['externalTargetPercentGuards']['rIdGoodEncodedSpace']['allowed'] ?? null) !== true
        || ($summary['externalTargetPercentGuards']['rIdGoodEncodedSpace']['valid'] ?? null) !== true
        || ($summary['externalTargetPercentGuards']['rIdGoodEncodedSpace']['issues'] ?? null) !== []
        || ($summary['externalTargetPercentGuards']['rIdBadPercentEscape']['allowed'] ?? null) !== false
        || ($summary['externalTargetPercentGuards']['rIdBadPercentEscape']['valid'] ?? null) !== false
        || ($summary['externalTargetPercentGuards']['rIdBadPercentEscape']['issues'] ?? null) !== ['external-target-malformed-percent-escape']
        || ($summary['externalTargetPercentGuards']['rIdEncodedNul']['allowed'] ?? null) !== false
        || ($summary['externalTargetPercentGuards']['rIdEncodedNul']['valid'] ?? null) !== false
        || ($summary['externalTargetPercentGuards']['rIdEncodedNul']['issues'] ?? null) !== ['external-target-unsafe-percent-encoded-byte']
        || ($summary['wordpressImport']['externalTargetPercentGuards'][1]['id'] ?? null) !== 'rIdBadPercentEscape'
        || ($summary['wordpressImport']['externalTargetPercentGuards'][1]['issues'] ?? null) !== ['external-target-malformed-percent-escape']
        || ($summary['wordpressImport']['externalTargetPercentGuards'][2]['id'] ?? null) !== 'rIdEncodedNul'
        || ($summary['wordpressImport']['externalTargetPercentGuards'][2]['issues'] ?? null) !== ['external-target-unsafe-percent-encoded-byte']
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
        || ($summary['alternativeFormatImportPolicyGuard']['knownRole'] ?? null) !== 'alternative-format-import'
        || ($summary['alternativeFormatImportPolicyGuard']['sourceScope'] ?? null) !== 'any-source'
        || !array_key_exists('singletonScope', $summary['alternativeFormatImportPolicyGuard'] ?? [])
        || $summary['alternativeFormatImportPolicyGuard']['singletonScope'] !== null
        || ($summary['alternativeFormatImportPolicyGuard']['policyValid'] ?? null) !== true
        || ($summary['alternativeFormatImportPolicyGuard']['policyIssues'] ?? null) !== []
        || ($summary['alternativeFormatImportPolicyGuard']['relationshipCount'] ?? null) !== 2
        || ($summary['alternativeFormatImportPolicyGuard']['sourceCount'] ?? null) !== 1
        || ($summary['alternativeFormatImportPolicyGuard']['targetParts'] ?? null) !== ['/word/chunks/plain-review.txt', '/word/chunks/review.html']
        || ($summary['alternativeFormatImportPolicyGuard']['contentTypes'] ?? null) !== ['text/html', 'text/plain; charset=utf-8']
        || ($summary['wordpressImport']['alternativeFormatImportPolicy']['knownRole'] ?? null) !== 'alternative-format-import'
        || !array_key_exists('singletonScope', $summary['wordpressImport']['alternativeFormatImportPolicy'] ?? [])
        || $summary['wordpressImport']['alternativeFormatImportPolicy']['singletonScope'] !== null
        || ($summary['wordpressImport']['alternativeFormatImportPolicy']['policyValid'] ?? null) !== true
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
        || ($summary['reservedRelationshipDirectoryGuard']['packagePart'] ?? null) !== '/word/_rels/review-metadata.xml'
        || ($summary['reservedRelationshipDirectoryGuard']['packagePartContentType'] ?? null) !== 'application/xml'
        || ($summary['reservedRelationshipDirectoryGuard']['packagePartRelationshipPart'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['packagePartValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['packagePartIssues'] ?? null) !== ['reserved-relationship-directory-part']
        || ($summary['reservedRelationshipDirectoryGuard']['overridePart'] ?? null) !== '/word/_rels/review-metadata.xml'
        || ($summary['reservedRelationshipDirectoryGuard']['overrideExists'] ?? null) !== true
        || ($summary['reservedRelationshipDirectoryGuard']['overrideValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['overrideIssues'] ?? null) !== ['reserved-relationship-directory-override']
        || ($summary['reservedRelationshipDirectoryGuard']['targetPart'] ?? null) !== '/word/_rels/review-metadata.xml'
        || ($summary['reservedRelationshipDirectoryGuard']['targetExists'] ?? null) !== true
        || ($summary['reservedRelationshipDirectoryGuard']['targetValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['targetIssues'] ?? null) !== ['targets-reserved-relationship-directory-part']
        || ($summary['reservedRelationshipDirectoryGuard']['referenceValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['referenceIssues'] ?? null) !== ['reserved-relationship-directory-part', 'targets-reserved-relationship-directory-part']
        || ($summary['reservedRelationshipDirectoryGuard']['directReferenceIssues'] ?? null) !== ['targets-reserved-relationship-directory-part']
        || ($summary['reservedRelationshipDirectoryGuard']['packageConsistencyValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['packagePartsValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['contentTypeOverridesValid'] ?? null) !== false
        || ($summary['reservedRelationshipDirectoryGuard']['relationshipTargetsValid'] ?? null) !== false
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
        || ($summary['integrity']['markupCompatibilityAlternateContent']['sourceParts'] ?? null) !== ['/']
        || ($summary['integrity']['markupCompatibilityAlternateContent']['relationshipIds'] ?? null) !== ['rIdAlternateDocument', 'rIdAlternateAudit']
        || ($summary['integrity']['markupCompatibilityAlternateContent']['officeDocumentTargetPart'] ?? null) !== '/word/alternate-document.xml'
        || ($summary['integrity']['markupCompatibilityAlternateContent']['officeDocumentValid'] ?? null) !== true
        || ($summary['integrity']['markupCompatibilityAlternateContent']['auditContentType'] ?? null) !== 'application/xml'
        || ($summary['integrity']['markupCompatibilityAlternateContent']['fallbackOverrideSelected'] ?? null) !== false
        || ($summary['integrity']['markupCompatibilityAlternateContent']['hiddenRelationshipLoaded'] ?? null) !== false
        || $summary['packageParts']['/_rels/.rels']['relationshipSource'] !== '/'
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/_rels/.rels']['relationshipSourceLoaded'] !== true
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSource'] !== '/word/document.xml'
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceIsRelationshipPart'] !== false
        || $summary['packageParts']['/word/_rels/document.xml.rels']['relationshipSourceLoaded'] !== true
        || $summary['packageParts']['/word/_rels/document.xml.rels']['contentTypeSource'] !== 'default'
        || $summary['packageParts']['/word/_rels/document.xml.rels']['contentTypeDefaultExtension'] !== 'rels'
        || $summary['packageParts']['/word/document.xml']['contentTypeSource'] !== 'override'
        || $summary['packageParts']['/word/document.xml']['contentTypeOverridePartName'] !== '/word/document.xml'
        || $summary['packageParts']['/word/media/hero image.PNG']['contentType'] !== 'image/png'
        || $summary['packageParts']['/word/media/hero image.PNG']['contentTypeSource'] !== 'default'
        || $summary['packageParts']['/word/media/hero image.PNG']['contentTypeDefaultExtension'] !== 'png'
        || ($summary['wordpressImport']['mediaParts'][3] ?? null) !== '/word/media/review source.png'
        || ($summary['wordpressImport']['relationshipSourceReview'][2]['source'] ?? null) !== '/word/document.xml'
        || ($summary['wordpressImport']['relationshipSourceReview'][2]['valid'] ?? null) !== false
        || ($summary['wordpressImport']['relationshipSourceReview'][2]['invalidTargetCount'] ?? null) !== 3
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
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['relationshipCount'] ?? null) !== 5
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['externalCount'] ?? null) !== 4
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['internalCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink']['issues'] ?? null) !== ['external-target-network-path-base-uri', 'external-target-unsafe-scheme']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['knownRole'] ?? null) !== 'office-document'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['sourceScope'] ?? null) !== 'package-root'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['singletonScope'] ?? null) !== 'package'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['policyValid'] ?? null) !== true
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['policyIssues'] ?? null) !== []
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['relationshipCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['targetParts'] ?? null) !== ['/EncryptedPackage']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['contentTypes'] ?? null) !== ['application/vnd.openxmlformats-package.encrypted-package']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['knownRole'] ?? null) !== 'encrypted-package'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['sourceScope'] ?? null) !== 'package-root'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['singletonScope'] ?? null) !== 'package'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['policyValid'] ?? null) !== true
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE]['policyIssues'] ?? null) !== []
        || ($summary['relationshipTypeInventory']['officeDocument/relationships/hyperlink']['relationshipTypeValid'] ?? null) !== false
        || ($summary['relationshipTypeInventory']['officeDocument/relationships/hyperlink']['relationshipTypeIssues'] ?? null) !== ['relationship-type-not-absolute-uri']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE]['relationshipCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE]['sourceCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE]['sources'] ?? null) !== ['/word/review source.xml']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE]['targetParts'] ?? null) !== ['/customXml/itemProps1.xml']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE]['contentTypes'] ?? null) !== ['application/vnd.openxmlformats-officedocument.customXmlProperties+xml']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE]['issues'] ?? null) !== []
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]['targetParts'] ?? null) !== ['/word/embeddings/source workbook.xlsx']
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['relationshipCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['sourceCount'] ?? null) !== 1
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['knownRole'] ?? null) !== 'thumbnail'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['singletonScope'] ?? null) !== 'source'
        || ($summary['relationshipTypeInventory'][OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['policyValid'] ?? null) !== true
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
        || ($summary['contentTypeInventory']['application/vnd.openxmlformats-officedocument.customXmlProperties+xml']['parts'] ?? null) !== ['/customXml/itemProps1.xml']
        || ($summary['contentTypeInventory']['application/vnd.openxmlformats-officedocument.customXmlProperties+xml']['relationshipTargetParts'] ?? null) !== ['/customXml/itemProps1.xml']
        || ($summary['contentTypeInventory']['application/vnd.openxmlformats-officedocument.customXmlProperties+xml']['reachableTargetParts'] ?? null) !== ['/customXml/itemProps1.xml']
        || ($summary['contentTypeInventory']['application/vnd.openxmlformats-officedocument.customXmlProperties+xml']['issues'] ?? null) !== []
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
        || ($summary['packagePartReferences']['/customXml/itemProps1.xml']['directReferences'][0]['source'] ?? null) !== '/word/review source.xml'
        || ($summary['packagePartReferences']['/customXml/itemProps1.xml']['directReferences'][0]['id'] ?? null) !== 'rIdReviewSourceProperties'
        || ($summary['packagePartReferences']['/customXml/itemProps1.xml']['reachableReferences'][0]['depth'] ?? null) !== 2
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
        || ($summary['relationshipTransform']['relationshipXmlBytes'] ?? null) !== $relationshipTransformXmlBytes
        || ($summary['relationshipTransform']['relationshipXmlSha256'] ?? null) !== $relationshipTransformXmlSha256
        || strlen((string) ($summary['relationshipTransform']['relationshipXmlSha256'] ?? '')) !== 64
        || !ctype_xdigit((string) ($summary['relationshipTransform']['relationshipXmlSha256'] ?? ''))
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
        || ($summary['signatureRelationshipTransforms'][0]['duplicateSourceIds'] ?? null) !== []
        || ($summary['signatureRelationshipTransforms'][0]['duplicateSourceTypes'] ?? null) !== []
        || ($summary['signatureRelationshipTransforms'][0]['selectorDuplicateSourceIdCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransforms'][0]['selectorDuplicateSourceTypeCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransforms'][0]['selectorChildCount'] ?? null) !== 3
        || ($summary['signatureRelationshipTransforms'][0]['selectorRelationshipReferenceCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransforms'][0]['selectorRelationshipGroupReferenceCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransforms'][0]['selectorUnsupportedChildCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransforms'][0]['selectorUnsupportedContentCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransforms'][0]['followingCanonicalizationAlgorithm'] ?? null) !== 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'
        || ($summary['signatureRelationshipTransforms'][0]['followingCanonicalization']['profile'] ?? null) !== 'inclusive-c14n-1.0'
        || ($summary['signatureRelationshipTransforms'][0]['followingCanonicalization']['exclusive'] ?? null) !== false
        || ($summary['signatureRelationshipTransforms'][0]['followedByCanonicalization'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['relationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer']
        || ($summary['signatureRelationshipTransforms'][0]['relationshipCount'] ?? null) !== 3
        || ($summary['signatureRelationshipTransforms'][0]['selectorValid'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['valid'] ?? null) !== true
        || ($summary['signatureRelationshipTransforms'][0]['issues'] ?? null) !== []
        || ($summary['signatureRelationshipTransforms'][0]['relationshipXmlBytes'] ?? null) !== $signatureRelationshipTransformXmlBytes
        || ($summary['signatureRelationshipTransforms'][0]['relationshipXmlSha256'] ?? null) !== $signatureRelationshipTransformXmlSha256
        || strlen((string) ($summary['signatureRelationshipTransforms'][0]['relationshipXmlSha256'] ?? '')) !== 64
        || !ctype_xdigit((string) ($summary['signatureRelationshipTransforms'][0]['relationshipXmlSha256'] ?? ''))
        || !str_contains((string) ($summary['signatureRelationshipTransforms'][0]['relationshipXml'] ?? ''), 'Id="rIdEmbeddedWorkbook"')
        || str_contains((string) ($summary['signatureRelationshipTransforms'][0]['relationshipXml'] ?? ''), 'rIdDraftReview')
        || ($summary['signatureRelationshipTransformSummary']['valid'] ?? null) !== true
        || ($summary['signatureRelationshipTransformSummary']['transformCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransformSummary']['validTransformCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransformSummary']['invalidTransformCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformSummary']['relationshipPartNames'] ?? null) !== ['/word/_rels/document.xml.rels']
        || ($summary['signatureRelationshipTransformSummary']['sources'] ?? null) !== ['/word/document.xml']
        || ($summary['signatureRelationshipTransformSummary']['selectedRelationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer']
        || ($summary['signatureRelationshipTransformSummary']['selectedInternalTargetParts'] ?? null) !== ['/word/embeddings/source workbook.xlsx', '/word/media/hero image.PNG']
        || ($summary['signatureRelationshipTransformSummary']['selectedExternalTargets'] ?? null) !== ['https://example.test/wp-admin/post.php?post=42&action=edit']
        || ($summary['signatureRelationshipTransformSummary']['relationshipXmlSha256s'] ?? null) !== [$signatureRelationshipTransformXmlSha256]
        || ($summary['signatureRelationshipTransformSummary']['issues'] ?? null) !== []
        || ($summary['signatureRelationshipTransformSummary']['transforms'][0]['relationshipXmlBytes'] ?? null) !== $signatureRelationshipTransformXmlBytes
        || ($summary['signatureRelationshipTransformSummary']['transforms'][0]['relationshipXmlSha256'] ?? null) !== $signatureRelationshipTransformXmlSha256
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['valid'] ?? null) !== true
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['transformCount'] ?? null) !== 1
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['relationshipPartNames'] ?? null) !== ['/word/_rels/document.xml.rels']
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['selectedRelationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer']
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['selectedInternalTargetParts'] ?? null) !== ['/word/embeddings/source workbook.xlsx', '/word/media/hero image.PNG']
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['selectedExternalTargets'] ?? null) !== ['https://example.test/wp-admin/post.php?post=42&action=edit']
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['relationshipXmlSha256s'] ?? null) !== [$signatureRelationshipTransformXmlSha256]
        || ($summary['wordpressImport']['signatureRelationshipTransformSummary']['issues'] ?? null) !== []
        || ($summary['signedRelationshipPolicySummary']['valid'] ?? null) !== false
        || ($summary['signedRelationshipPolicySummary']['allowedRelationshipTypes'] ?? null) !== [
            OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
        ]
        || ($summary['signedRelationshipPolicySummary']['selectedRelationshipCount'] ?? null) !== 3
        || ($summary['signedRelationshipPolicySummary']['allowedRelationshipCount'] ?? null) !== 2
        || ($summary['signedRelationshipPolicySummary']['disallowedRelationshipCount'] ?? null) !== 1
        || ($summary['signedRelationshipPolicySummary']['externalRelationshipCount'] ?? null) !== 1
        || ($summary['signedRelationshipPolicySummary']['internalRelationshipCount'] ?? null) !== 2
        || ($summary['signedRelationshipPolicySummary']['invalidRelationshipCount'] ?? null) !== 0
        || ($summary['signedRelationshipPolicySummary']['selectedRelationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer']
        || ($summary['signedRelationshipPolicySummary']['selectedRelationshipTypes'] ?? null) !== [
            OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
        ]
        || ($summary['signedRelationshipPolicySummary']['disallowedRelationshipTypes'] ?? null) !== [OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE]
        || ($summary['signedRelationshipPolicySummary']['selectedInternalTargetParts'] ?? null) !== ['/word/embeddings/source workbook.xlsx', '/word/media/hero image.PNG']
        || ($summary['signedRelationshipPolicySummary']['selectedExternalTargets'] ?? null) !== ['https://example.test/wp-admin/post.php?post=42&action=edit']
        || ($summary['signedRelationshipPolicySummary']['issueCounts'] ?? null) !== [
            'external-signed-relationship' => 1,
            'signed-relationship-type-not-allowed' => 1,
        ]
        || ($summary['signedRelationshipPolicySummary']['issues'] ?? null) !== [
            'external-signed-relationship',
            'signed-relationship-type-not-allowed',
        ]
        || count($summary['signedRelationshipPolicySummary']['disallowedRelationships'] ?? []) !== 1
        || ($summary['signedRelationshipPolicySummary']['disallowedRelationships'][0]['id'] ?? null) !== 'rIdReviewer'
        || ($summary['signedRelationshipPolicySummary']['disallowedRelationships'][0]['target'] ?? null) !== 'https://example.test/wp-admin/post.php?post=42&action=edit'
        || ($summary['signedRelationshipPolicySummary']['disallowedRelationships'][0]['policyIssues'] ?? null) !== [
            'external-signed-relationship',
            'signed-relationship-type-not-allowed',
        ]
        || ($summary['wordpressImport']['signedRelationshipPolicy']['valid'] ?? null) !== false
        || ($summary['wordpressImport']['signedRelationshipPolicy']['selectedRelationshipCount'] ?? null) !== 3
        || ($summary['wordpressImport']['signedRelationshipPolicy']['allowedRelationshipCount'] ?? null) !== 2
        || ($summary['wordpressImport']['signedRelationshipPolicy']['disallowedRelationshipCount'] ?? null) !== 1
        || ($summary['wordpressImport']['signedRelationshipPolicy']['disallowedRelationshipTypes'] ?? null) !== [OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE]
        || ($summary['wordpressImport']['signedRelationshipPolicy']['issueCounts'] ?? null) !== [
            'external-signed-relationship' => 1,
            'signed-relationship-type-not-allowed' => 1,
        ]
        || ($summary['wordpressImport']['signedRelationshipPolicy']['disallowedRelationships'][0]['id'] ?? null) !== 'rIdReviewer'
        || ($summary['digitalSignatureDigestPolicySummary']['valid'] ?? null) !== false
        || ($summary['digitalSignatureDigestPolicySummary']['referenceCount'] ?? null) !== 5
        || ($summary['digitalSignatureDigestPolicySummary']['signedInfoReferenceCount'] ?? null) !== 2
        || ($summary['digitalSignatureDigestPolicySummary']['manifestReferenceCount'] ?? null) !== 3
        || ($summary['digitalSignatureDigestPolicySummary']['validDigestPolicyCount'] ?? null) !== 0
        || ($summary['digitalSignatureDigestPolicySummary']['invalidDigestPolicyCount'] ?? null) !== 5
        || ($summary['digitalSignatureDigestPolicySummary']['knownDigestAlgorithmCount'] ?? null) !== 5
        || ($summary['digitalSignatureDigestPolicySummary']['unknownDigestAlgorithmCount'] ?? null) !== 0
        || ($summary['digitalSignatureDigestPolicySummary']['digestValueLengthMismatchCount'] ?? null) !== 5
        || ($summary['digitalSignatureDigestPolicySummary']['algorithmCounts'] ?? null) !== [
            'http://www.w3.org/2000/09/xmldsig#sha1' => 1,
            'http://www.w3.org/2001/04/xmlenc#sha256' => 4,
        ]
        || ($summary['digitalSignatureDigestPolicySummary']['issueCounts'] ?? null) !== [
            'invalid-manifest-reference-digest-value-length' => 3,
            'invalid-signed-info-reference-digest-value-length' => 2,
        ]
        || ($summary['digitalSignatureDigestPolicySummary']['issues'] ?? null) !== [
            'invalid-manifest-reference-digest-value-length',
            'invalid-signed-info-reference-digest-value-length',
        ]
        || count($summary['digitalSignatureDigestPolicySummary']['invalidReferences'] ?? []) !== 5
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][0]['section'] ?? null) !== 'signed-info'
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][0]['referenceIndex'] ?? null) !== 0
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][0]['uri'] ?? null) !== '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][0]['targetPart'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][0]['digestExpectedDecodedBytes'] ?? null) !== 32
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][0]['digestValueDecodedBytes'] ?? null) !== 5
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][4]['section'] ?? null) !== 'manifest'
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][4]['targetPart'] ?? null) !== '/word/media/hero image.PNG'
        || ($summary['digitalSignatureDigestPolicySummary']['invalidReferences'][4]['digestValueDecodedBytes'] ?? null) !== 3
        || ($summary['wordpressImport']['digitalSignatureDigestPolicy']['valid'] ?? null) !== false
        || ($summary['wordpressImport']['digitalSignatureDigestPolicy']['referenceCount'] ?? null) !== 5
        || ($summary['wordpressImport']['digitalSignatureDigestPolicy']['invalidDigestPolicyCount'] ?? null) !== 5
        || ($summary['wordpressImport']['digitalSignatureDigestPolicy']['digestValueLengthMismatchCount'] ?? null) !== 5
        || ($summary['wordpressImport']['digitalSignatureDigestPolicy']['issueCounts'] ?? null) !== [
            'invalid-manifest-reference-digest-value-length' => 3,
            'invalid-signed-info-reference-digest-value-length' => 2,
        ]
        || count($summary['signatureRelationshipTransformGuards'] ?? []) !== 2
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
        || ($summary['signatureRelationshipTransformGuards'][0]['duplicateSourceIds'] ?? null) !== []
        || ($summary['signatureRelationshipTransformGuards'][0]['duplicateSourceTypes'] ?? null) !== []
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorDuplicateSourceIdCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorDuplicateSourceTypeCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorChildCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorRelationshipReferenceCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorRelationshipGroupReferenceCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorUnsupportedChildCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformGuards'][0]['selectorUnsupportedContentCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformGuards'][0]['relationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero']
        || ($summary['signatureRelationshipTransformGuards'][0]['relationshipCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransformGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureRelationshipTransformGuards'][0]['issues'] ?? null) !== [
            'unsupported-relationship-transform-selector-attribute',
            'unsupported-relationship-transform-selector-child',
            'unsupported-relationship-transform-selector-content',
        ]
        || ($summary['signatureRelationshipTransformGuards'][1]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-duplicate-selector.xml'
        || ($summary['signatureRelationshipTransformGuards'][1]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureRelationshipTransformGuards'][1]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureRelationshipTransformGuards'][1]['referenceRelationshipPartExists'] ?? null) !== true
        || ($summary['signatureRelationshipTransformGuards'][1]['source'] ?? null) !== '/word/document.xml'
        || ($summary['signatureRelationshipTransformGuards'][1]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureRelationshipTransformGuards'][1]['sourceTypes'] ?? null) !== [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]
        || ($summary['signatureRelationshipTransformGuards'][1]['duplicateSourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureRelationshipTransformGuards'][1]['duplicateSourceTypes'] ?? null) !== [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE]
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorDuplicateSourceIdCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorDuplicateSourceTypeCount'] ?? null) !== 1
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorChildCount'] ?? null) !== 4
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorRelationshipReferenceCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorRelationshipGroupReferenceCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorUnsupportedChildCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformGuards'][1]['selectorUnsupportedContentCount'] ?? null) !== 0
        || ($summary['signatureRelationshipTransformGuards'][1]['relationshipIds'] ?? null) !== ['rIdEmbeddedWorkbook', 'rIdHero']
        || ($summary['signatureRelationshipTransformGuards'][1]['relationshipCount'] ?? null) !== 2
        || ($summary['signatureRelationshipTransformGuards'][1]['valid'] ?? null) !== false
        || ($summary['signatureRelationshipTransformGuards'][1]['issues'] ?? null) !== ['duplicate-source-id', 'duplicate-source-type']
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
        || count($summary['signatureRelationshipPartContentTypeGuards'] ?? []) !== 3
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-relationship-part-content-type.xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['referenceUri'] ?? null) !== '/word/_rels/comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['relationshipPartName'] ?? null) !== '/word/_rels/comments.xml.rels'
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['referenceRelationshipPartExists'] ?? null) !== true
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['referenceTargetContentType'] ?? null) !== 'application/xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['referenceContentTypeMatches'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['source'] ?? null) !== '/word/comments.xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['sourceIds'] ?? null) !== ['rIdCommentImage']
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['relationshipIds'] ?? null) !== []
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['relationshipCount'] ?? null) !== 0
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['selectorValid'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][0]['issues'] ?? null) !== [
            'reference-relationship-content-type-invalid',
            'reference-content-type-mismatch',
            'relationship-source-not-loaded',
            'unmatched-source-id',
        ]
        || !array_key_exists('relationshipXml', $summary['signatureRelationshipPartContentTypeGuards'][0] ?? [])
        || $summary['signatureRelationshipPartContentTypeGuards'][0]['relationshipXml'] !== null
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['referenceUri'] ?? null) !== '/word/_rels/footnotes.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['relationshipPartName'] ?? null) !== '/word/_rels/footnotes.xml.rels'
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['referenceRelationshipPartExists'] ?? null) !== true
        || !array_key_exists('referenceTargetContentType', $summary['signatureRelationshipPartContentTypeGuards'][1] ?? [])
        || $summary['signatureRelationshipPartContentTypeGuards'][1]['referenceTargetContentType'] !== null
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['referenceContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['referenceContentTypeMatches'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['source'] ?? null) !== '/word/footnotes.xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['sourceIds'] ?? null) !== ['rIdFootnoteImage']
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['relationshipIds'] ?? null) !== []
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['relationshipCount'] ?? null) !== 0
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['selectorValid'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['valid'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][1]['issues'] ?? null) !== [
            'reference-relationship-content-type-missing',
            'reference-content-type-mismatch',
            'relationship-source-not-loaded',
            'unmatched-source-id',
        ]
        || !array_key_exists('relationshipXml', $summary['signatureRelationshipPartContentTypeGuards'][1] ?? [])
        || $summary['signatureRelationshipPartContentTypeGuards'][1]['relationshipXml'] !== null
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['referenceUri'] ?? null) !== '/word/_rels/settings.xml.rels?ContentType=application/xml%20bad'
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['relationshipPartName'] ?? null) !== '/word/_rels/settings.xml.rels'
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['referenceRelationshipPartExists'] ?? null) !== true
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['referenceTargetContentType'] ?? null) !== 'application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['referenceContentType'] ?? null) !== 'application/xml bad'
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['referenceContentTypeMatches'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['source'] ?? null) !== '/word/settings.xml'
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['sourceIds'] ?? null) !== ['rIdSettingsImage']
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['relationshipIds'] ?? null) !== ['rIdSettingsImage']
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['relationshipCount'] ?? null) !== 1
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['selectorValid'] ?? null) !== true
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['valid'] ?? null) !== false
        || ($summary['signatureRelationshipPartContentTypeGuards'][2]['issues'] ?? null) !== [
            'invalid-reference-content-type-query',
            'reference-content-type-mismatch',
        ]
        || !array_key_exists('relationshipXml', $summary['signatureRelationshipPartContentTypeGuards'][2] ?? [])
        || !str_contains((string) ($summary['signatureRelationshipPartContentTypeGuards'][2]['relationshipXml'] ?? ''), 'Id="rIdSettingsImage"')
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
        || count($summary['signatureEnvelopedTransformGuards'] ?? []) !== 1
        || ($summary['signatureEnvelopedTransformGuards'][0]['signaturePart'] ?? null) !== '/_xmlsignatures/sig-enveloped-transform.xml'
        || ($summary['signatureEnvelopedTransformGuards'][0]['referenceUri'] ?? null) !== '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml'
        || ($summary['signatureEnvelopedTransformGuards'][0]['relationshipPartName'] ?? null) !== '/word/_rels/document.xml.rels'
        || ($summary['signatureEnvelopedTransformGuards'][0]['source'] ?? null) !== '/word/document.xml'
        || ($summary['signatureEnvelopedTransformGuards'][0]['sourceIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureEnvelopedTransformGuards'][0]['followingCanonicalizationAlgorithm'] ?? null) !== 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'
        || ($summary['signatureEnvelopedTransformGuards'][0]['followedByCanonicalization'] ?? null) !== true
        || ($summary['signatureEnvelopedTransformGuards'][0]['relationshipIds'] ?? null) !== ['rIdHero']
        || ($summary['signatureEnvelopedTransformGuards'][0]['relationshipCount'] ?? null) !== 1
        || ($summary['signatureEnvelopedTransformGuards'][0]['selectorValid'] ?? null) !== true
        || ($summary['signatureEnvelopedTransformGuards'][0]['relationshipTargetsValid'] ?? null) !== true
        || ($summary['signatureEnvelopedTransformGuards'][0]['valid'] ?? null) !== false
        || ($summary['signatureEnvelopedTransformGuards'][0]['issues'] ?? null) !== ['relationship-transform-with-enveloped-signature-transform']
        || count($summary['signatureEnvelopedSignedInfoReferences'] ?? []) !== 1
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['transformAlgorithms'] ?? null) !== [
            OpcRelationshipGraph::RELATIONSHIP_TRANSFORM_ALGORITHM,
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
        ]
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['relationshipTransformIndexes'] ?? null) !== [0]
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['canonicalizationTransformIndexes'] ?? null) !== [1]
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['relationshipTransformCount'] ?? null) !== 1
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['canonicalizationTransformCount'] ?? null) !== 1
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['relationshipTransformFollowedByCanonicalization'] ?? null) !== true
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['valid'] ?? null) !== false
        || ($summary['signatureEnvelopedSignedInfoReferences'][0]['issues'] ?? null) !== ['signed-info-relationship-transform-with-enveloped-signature-transform']
        || ($summary['wordpressImport']['signatureEnvelopedTransformGuard']['issues'] ?? null) !== ['relationship-transform-with-enveloped-signature-transform']
        || ($summary['wordpressImport']['signatureEnvelopedSignedInfoReferenceGuard']['issues'] ?? null) !== ['signed-info-relationship-transform-with-enveloped-signature-transform']
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
        || ($summary['wordpressImport']['externalTargets'][4]['id'] ?? null) !== 'rIdSchemeRelativeReviewer'
        || ($summary['wordpressImport']['externalTargets'][4]['kind'] ?? null) !== 'network-path-reference'
        || ($summary['wordpressImport']['externalTargets'][4]['scheme'] ?? null) !== null
        || ($summary['wordpressImport']['externalTargets'][4]['allowed'] ?? null) !== true
        || ($summary['wordpressImport']['externalTargets'][4]['requiresBaseUri'] ?? null) !== true
        || ($summary['wordpressImport']['externalTargets'][4]['rewriteBasePart'] ?? null) !== null
        || ($summary['wordpressImport']['externalTargets'][4]['rewriteReason'] ?? null) !== 'external-target-network-path-reference'
        || ($summary['wordpressImport']['externalTargets'][4]['issues'] ?? null) !== ['external-target-network-path-base-uri']
        || ($summary['integrity']['issues'][2]['id'] ?? null) !== 'rIdSchemeRelativeReviewer'
        || ($summary['integrity']['issues'][2]['issues'] ?? null) !== ['external-target-network-path-base-uri']
    ) {
        throw new RuntimeException('OPC DOCX preflight self-test failed');
    }

    echo "opc docx preflight self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
