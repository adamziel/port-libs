<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX digital signature issue rollups' => static function (TestRunner $t): void {
        $originType = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
        $signatureType = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';
        $signatureXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml">
      <ds:DigestValue>digest</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:SignatureValue>value</ds:SignatureValue>
</ds:Signature>
XML;

        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML,
            '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs?audit=1#origin"/>
  <Relationship Id="rMissingSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/missing-origin.sigs"/>
  <Relationship Id="rExternalSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="https://example.test/origin.sigs" TargetMode="External"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Digital signature issue rollups.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            '_xmlsignatures/origin.sigs' => 'signature origin bytes',
            '_xmlsignatures/_rels/origin.sigs.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
  <Relationship Id="rMissingSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="missing.sig"/>
  <Relationship Id="rExternalSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="https://example.test/sig.xml" TargetMode="External"/>
  <Relationship Id="rBadSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="bad-signature.xml"/>
</Relationships>
XML,
            '_xmlsignatures/sig1.xml' => $signatureXml,
            '_xmlsignatures/bad-signature.xml' => '<notSignature xmlns="urn:example"/>',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $signatures = $package['digitalSignatures'];

        $expectedCodeCounts = [
            'external-signature-origin' => 1,
            'external-signature-target' => 1,
            'missing-origin-content-type' => 1,
            'missing-origin-part' => 1,
            'missing-signature-content-type' => 1,
            'missing-signature-part' => 1,
            'unexpected-signature-content-type' => 1,
            'unexpected-signature-root' => 1,
        ];
        $expectedOriginIdsByCode = [
            'external-signature-origin' => ['rExternalSignatureOrigin'],
            'missing-origin-content-type' => ['rMissingSignatureOrigin'],
            'missing-origin-part' => ['rMissingSignatureOrigin'],
        ];
        $expectedSignatureIdsByCode = [
            'external-signature-target' => ['rExternalSignature'],
            'missing-signature-content-type' => ['rMissingSignature'],
            'missing-signature-part' => ['rMissingSignature'],
            'unexpected-signature-content-type' => ['rBadSignature'],
            'unexpected-signature-root' => ['rBadSignature'],
        ];
        $expectedOriginPartsByCode = [
            'missing-origin-content-type' => ['_xmlsignatures/missing-origin.sigs'],
            'missing-origin-part' => ['_xmlsignatures/missing-origin.sigs'],
        ];
        $expectedSignaturePartsByCode = [
            'missing-signature-content-type' => ['_xmlsignatures/missing.sig'],
            'missing-signature-part' => ['_xmlsignatures/missing.sig'],
            'unexpected-signature-content-type' => ['_xmlsignatures/bad-signature.xml'],
            'unexpected-signature-root' => ['_xmlsignatures/bad-signature.xml'],
        ];
        $expectedExternalTargetsByCode = [
            'external-signature-origin' => ['https://example.test/origin.sigs'],
            'external-signature-target' => ['https://example.test/sig.xml'],
        ];

        $t->same(3, $signatures['originCount']);
        $t->same(4, $signatures['signatureCount']);
        $t->same(5, $signatures['issueCount']);
        $t->same($expectedCodeCounts, $signatures['issueCodeCounts']);
        $t->same($expectedOriginIdsByCode, $signatures['issueOriginRelationshipIdsByCode']);
        $t->same($expectedSignatureIdsByCode, $signatures['issueSignatureRelationshipIdsByCode']);
        $t->same($expectedOriginPartsByCode, $signatures['issueOriginPartsByCode']);
        $t->same($expectedSignaturePartsByCode, $signatures['issueSignaturePartsByCode']);
        $t->same($expectedExternalTargetsByCode, $signatures['issueExternalTargetsByCode']);

        $t->same($expectedCodeCounts, $summary['digitalSignatureIssueCodeCounts']);
        $t->same($expectedOriginIdsByCode, $summary['digitalSignatureIssueOriginRelationshipIdsByCode']);
        $t->same($expectedSignatureIdsByCode, $summary['digitalSignatureIssueSignatureRelationshipIdsByCode']);
        $t->same($expectedOriginPartsByCode, $summary['digitalSignatureIssueOriginPartsByCode']);
        $t->same($expectedSignaturePartsByCode, $summary['digitalSignatureIssueSignaturePartsByCode']);
        $t->same($expectedExternalTargetsByCode, $summary['digitalSignatureIssueExternalTargetsByCode']);
        $t->same(3, $summary['relationshipTypeCounts'][$originType]);
        $t->same(4, $summary['relationshipTypeCounts'][$signatureType]);

        json_encode([$signatures, $summary], JSON_THROW_ON_ERROR);
    },
];
