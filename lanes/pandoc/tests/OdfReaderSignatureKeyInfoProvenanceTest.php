<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Signature KeyInfo provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;

$keyName = 'Review Signing Key';
$certificateA = 'MIIDREVIEWCERTIFICATEA';
$certificateB = 'MIIDREVIEWCERTIFICATEB';
$subjectName = 'CN=Review Signer,O=Example Test';
$issuerName = 'CN=Review CA,O=Example Test';
$serialNumber = '42';
$signatureXml = <<<XML
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
  <dsig:Signature Id="key-info-signature">
    <dsig:SignedInfo>
      <dsig:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <dsig:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <dsig:Reference URI="content.xml">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>contentdigest</dsig:DigestValue>
      </dsig:Reference>
    </dsig:SignedInfo>
    <dsig:SignatureValue>signature-bytes</dsig:SignatureValue>
    <dsig:KeyInfo>
      <dsig:KeyName>{$keyName}</dsig:KeyName>
      <dsig:X509Data>
        <dsig:X509SubjectName>{$subjectName}</dsig:X509SubjectName>
        <dsig:X509Certificate>{$certificateA}</dsig:X509Certificate>
        <dsig:X509Certificate>{$certificateB}</dsig:X509Certificate>
        <dsig:X509IssuerSerial>
          <dsig:X509IssuerName>{$issuerName}</dsig:X509IssuerName>
          <dsig:X509SerialNumber>{$serialNumber}</dsig:X509SerialNumber>
        </dsig:X509IssuerSerial>
      </dsig:X509Data>
    </dsig:KeyInfo>
  </dsig:Signature>
  <dsig:Signature Id="no-key-info-signature">
    <dsig:SignedInfo>
      <dsig:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <dsig:Reference URI="Pictures/hero.png">
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>picturedigest</dsig:DigestValue>
      </dsig:Reference>
    </dsig:SignedInfo>
    <dsig:SignatureValue>other-signature-bytes</dsig:SignatureValue>
  </dsig:Signature>
</dsig:document-signatures>
XML;

return [
    'summarizes ODT XML signature KeyInfo as metadata-only provenance' => static function (TestRunner $t) use (
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml,
        $signatureXml,
        $keyName,
        $certificateA,
        $certificateB,
        $subjectName,
        $issuerName,
        $serialNumber
    ): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml],
        ], 'odt signature key-info provenance'));

        $signatures = $result['signatureMetadata'];
        $part = $signatures['parts'][0];
        $withKeyInfo = $part['signatures'][0];
        $withoutKeyInfo = $part['signatures'][1];

        $t->same($signatures, $result['document']->attr('signatureMetadata'));
        $t->same($signatures, $result['importReport']['signatureMetadata']);
        $t->same(1, $signatures['partCount']);
        $t->same(1, $signatures['parsedPartCount']);
        $t->same(2, $signatures['signatureCount']);
        $t->same(2, $signatures['referenceCount']);
        $t->same(1, $signatures['keyInfoSignatureCount']);
        $t->same(['dsig:KeyName' => 1, 'dsig:X509Data' => 1], $signatures['keyInfoChildElementNameCounts']);
        $t->same(1, $signatures['keyNameCount']);
        $t->same(1, $signatures['x509DataCount']);
        $t->same(2, $signatures['x509CertificateCount']);
        $t->same(1, $signatures['x509SubjectNameCount']);
        $t->same(1, $signatures['x509IssuerSerialCount']);

        $t->same($signatures['keyInfoSignatureCount'], $part['keyInfoSignatureCount']);
        $t->same($signatures['keyInfoChildElementNameCounts'], $part['keyInfoChildElementNameCounts']);
        $t->same($signatures['x509CertificateCount'], $part['x509CertificateCount']);
        $t->same(['Pictures/hero.png', 'content.xml'], $signatures['signedParts']);

        $t->same('key-info-signature', $withKeyInfo['id']);
        $t->same(true, $withKeyInfo['hasKeyInfo']);
        $t->same('xml-signature-key-info-metadata-only', $withKeyInfo['keyInfoReviewPolicy']);
        $t->same(false, $withKeyInfo['canExposeKeyInfoValues']);
        $t->same(2, $withKeyInfo['keyInfoChildElementCount']);
        $t->same(['dsig:KeyName', 'dsig:X509Data'], $withKeyInfo['keyInfoChildElementNames']);
        $t->same(['dsig:KeyName' => 1, 'dsig:X509Data' => 1], $withKeyInfo['keyInfoChildElementNameCounts']);
        $t->same(1, $withKeyInfo['keyNameCount']);
        $t->same([strlen($keyName)], $withKeyInfo['keyNameLengths']);
        $t->same([hash('sha256', $keyName)], $withKeyInfo['keyNameSha256s']);
        $t->same(1, $withKeyInfo['x509DataCount']);
        $t->same(2, $withKeyInfo['x509CertificateCount']);
        $t->same([strlen($certificateA), strlen($certificateB)], $withKeyInfo['x509CertificateLengths']);
        $t->same([hash('sha256', $certificateA), hash('sha256', $certificateB)], $withKeyInfo['x509CertificateSha256s']);
        $t->same(1, $withKeyInfo['x509SubjectNameCount']);
        $t->same([strlen($subjectName)], $withKeyInfo['x509SubjectNameLengths']);
        $t->same([hash('sha256', $subjectName)], $withKeyInfo['x509SubjectNameSha256s']);
        $t->same(1, $withKeyInfo['x509IssuerSerialCount']);
        $t->same([strlen($issuerName)], $withKeyInfo['x509IssuerNameLengths']);
        $t->same([hash('sha256', $issuerName)], $withKeyInfo['x509IssuerNameSha256s']);
        $t->same([strlen($serialNumber)], $withKeyInfo['x509SerialNumberLengths']);
        $t->same([hash('sha256', $serialNumber)], $withKeyInfo['x509SerialNumberSha256s']);
        $t->same(false, array_key_exists('keyNames', $withKeyInfo));
        $t->same(false, array_key_exists('x509Certificates', $withKeyInfo));

        $t->same('no-key-info-signature', $withoutKeyInfo['id']);
        $t->same(false, $withoutKeyInfo['hasKeyInfo']);
        $t->same(false, array_key_exists('keyInfoChildElementNames', $withoutKeyInfo));
    },
];
