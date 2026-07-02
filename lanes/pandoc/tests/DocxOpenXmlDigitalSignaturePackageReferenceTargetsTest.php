<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'records docx digital signature package reference target mapped case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedDocxDigitalSignaturePackageReferenceTargetCases'] ?? null);
        $t->same(50, $manifest['docxDigitalSignaturePackageReferenceTargetAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxDigitalSignaturePackageReferenceTargetCases'] ?? null);
        $t->same(50, $manifest['benchmarkDenominator']['breakdown']['docxDigitalSignaturePackageReferenceTargetAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedDocxDigitalSignaturePackageReferenceTargetCases'] ?? null);
        $t->same(50, $manifest['benchmarkDenominator']['inventory']['docxDigitalSignaturePackageReferenceTargetAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedDocxDigitalSignaturePackageReferenceTargetCases'] ?? null);
        $t->same(50, $manifest['inventory']['docxDigitalSignaturePackageReferenceTargetAssertions'] ?? null);
    },

    'summarizes digital signature package reference targets for review handoff' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(
            docx_digital_signature_package_reference_targets_fixture_parts()
        );
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $signatures = $package['digitalSignatures'];
        $signature = $signatures['bySignatureRelationshipId']['rSignedTargets'];

        $expectedKindCounts = ['external' => 1, 'package-part' => 3, 'relative' => 1, 'same-document' => 1];
        $expectedTargetParts = ['word/document.xml', 'customXml/item1.xml'];
        $expectedSuffixes = ['?review=1#body', '#payload'];

        $t->same(true, $signatures['present']);
        $t->same(1, $signatures['originCount']);
        $t->same(1, $signatures['signatureCount']);
        $t->same(6, $signatures['referenceCount']);
        $t->same(3, $signatures['packageReferenceCount']);
        $t->same(2, $signatures['packageReferenceTargetPartCount']);
        $t->same($expectedTargetParts, $signatures['packageReferenceTargetParts']);
        $t->same(2, $signatures['packageReferenceTargetReferenceSuffixCount']);
        $t->same($expectedSuffixes, $signatures['packageReferenceTargetReferenceSuffixes']);
        $t->same($signatures['packageReferenceTargetPartCount'], $summary['digitalSignaturePackageReferenceTargetPartCount']);
        $t->same($signatures['packageReferenceTargetParts'], $summary['digitalSignaturePackageReferenceTargetParts']);
        $t->same(
            $signatures['packageReferenceTargetReferenceSuffixCount'],
            $summary['digitalSignaturePackageReferenceTargetReferenceSuffixCount']
        );
        $t->same(
            $signatures['packageReferenceTargetReferenceSuffixes'],
            $summary['digitalSignaturePackageReferenceTargetReferenceSuffixes']
        );
        $t->same(1, $summary['digitalSignatureSameDocumentReferenceCount']);
        $t->same(1, $summary['digitalSignatureRelativeReferenceCount']);
        $t->same(1, $summary['digitalSignatureExternalReferenceCount']);
        $t->same($expectedKindCounts, $summary['digitalSignatureReferenceUriKindCounts']);
        $t->same($expectedKindCounts, $signature['referenceUriKindCounts']);
        $t->same(3, $signature['packageReferenceCount']);
        $t->same(2, $signature['packageReferenceTargetPartCount']);
        $t->same($expectedTargetParts, $signature['packageReferenceTargetParts']);
        $t->same(2, $signature['packageReferenceTargetReferenceSuffixCount']);
        $t->same($expectedSuffixes, $signature['packageReferenceTargetReferenceSuffixes']);
        $t->same('word/document.xml', $signature['references'][0]['targetPart']);
        $t->same('review=1', $signature['references'][0]['targetQuery']);
        $t->same('body', $signature['references'][0]['targetFragment']);
        $t->same('?review=1#body', $signature['references'][0]['targetReferenceSuffix']);
        $t->same('customXml/item1.xml', $signature['references'][1]['targetPart']);
        $t->same('#payload', $signature['references'][1]['targetReferenceSuffix']);
        $t->same('word/document.xml', $signature['references'][2]['targetPart']);
        $t->same('?review=1#body', $signature['references'][2]['targetReferenceSuffix']);
        $t->same(null, $signature['references'][3]['targetPart']);
        $t->same('relative', $signature['references'][3]['uriKind']);
        $t->same('relative=1', $signature['references'][3]['targetQuery']);
        $t->same(null, $signature['references'][4]['targetPart']);
        $t->same('same-document', $signature['references'][4]['uriKind']);
        $t->same(true, $signature['references'][4]['sameDocument']);
        $t->same('external', $signature['references'][5]['uriKind']);
        $t->same(true, $signature['references'][5]['external']);
        $t->same(null, $signature['references'][5]['targetPart']);
        $t->same(false, $signature['cryptographicValidation']);
        $t->same('digital-signature-metadata-only', $signatures['reviewPolicy']);
    },
];

/**
 * @return array<string, string>
 */
function docx_digital_signature_package_reference_targets_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/item1.xml" ContentType="application/xml; profile=signature-target"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/signed-targets.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML,
        '_xmlsignatures/origin.sigs' => "origin signature relationships\n",
        '_xmlsignatures/_rels/origin.sigs.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSignedTargets" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="signed-targets.xml"/>
</Relationships>
XML,
        '_xmlsignatures/signed-targets.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml?review=1#body">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>bodyDigest</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/customXml/item1.xml#payload">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha512"/>
      <ds:DigestValue>payloadDigest</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/document.xml?review=1#body">
      <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
      <ds:DigestValue>repeatDigest</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="word/footer1.xml?relative=1#footer">
      <ds:DigestValue>relativeDigest</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#manifestPackageParts">
      <ds:DigestValue>sameDocDigest</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="https://example.test/signature-source.xml?remote=1#sig">
      <ds:DigestValue>remoteDigest</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:SignatureValue>signature</ds:SignatureValue>
</ds:Signature>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Digital signature package reference targets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'customXml/item1.xml' => '<item>payload</item>',
    ];
}
