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
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Signature key provenance packet.</text:p>
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

$certificateBase64 = 'QUJDREVGR0g=';
$signatureXml = <<<'XML'
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
      <dsig:KeyName>Review Signing Key</dsig:KeyName>
      <dsig:RetrievalMethod URI="certs/reviewer.cer" Type="http://www.w3.org/2000/09/xmldsig#rawX509Certificate"/>
      <dsig:X509Data>
        <dsig:X509SubjectName>CN=ODF Reviewer,O=Example</dsig:X509SubjectName>
        <dsig:X509IssuerSerial>
          <dsig:X509IssuerName>CN=Example CA,O=Example</dsig:X509IssuerName>
          <dsig:X509SerialNumber>42</dsig:X509SerialNumber>
        </dsig:X509IssuerSerial>
        <dsig:X509Certificate>
          QUJD
          REVGR0g=
        </dsig:X509Certificate>
      </dsig:X509Data>
    </dsig:KeyInfo>
  </dsig:Signature>
</dsig:document-signatures>
XML;

return [
    'preserves ODT signature KeyInfo X509 review metadata without exposing certificate bytes' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml, $signatureXml, $certificateBase64): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml],
        ], 'odt signature key info provenance'));
        $signatures = $result['signatureMetadata'];
        $part = $signatures['parts'][0];
        $signature = $part['signatures'][0];
        $keyInfo = $signature['keyInfo'];
        $x509 = $keyInfo['x509Data'][0];

        $t->same($signatures, $result['document']->attr('signatureMetadata'));
        $t->same($signatures, $result['importReport']['signatureMetadata']);
        $t->same(1, $signatures['keyInfoCount']);
        $t->same(1, $signatures['keyNameCount']);
        $t->same(1, $signatures['keyInfoRetrievalMethodCount']);
        $t->same(1, $signatures['x509DataCount']);
        $t->same(1, $signatures['x509CertificateCount']);
        $t->same(strlen($certificateBase64), $signatures['x509CertificateBase64CharLength']);
        $t->same(1, $signatures['x509IssuerSerialCount']);
        $t->same(1, $signatures['x509SubjectNameCount']);
        $t->same(['Review Signing Key'], $signatures['keyInfoKeyNames']);
        $t->same(['CN=ODF Reviewer,O=Example'], $signatures['x509SubjectNames']);

        $t->same(1, $part['keyInfoCount']);
        $t->same(1, $part['keyNameCount']);
        $t->same(1, $part['keyInfoRetrievalMethodCount']);
        $t->same(1, $part['x509DataCount']);
        $t->same(1, $part['x509CertificateCount']);
        $t->same(strlen($certificateBase64), $part['x509CertificateBase64CharLength']);

        $t->same(true, $signature['hasKeyInfo']);
        $t->same(1, $signature['keyInfoCount']);
        $t->same(1, $signature['keyNameCount']);
        $t->same(1, $signature['keyInfoRetrievalMethodCount']);
        $t->same(1, $signature['x509DataCount']);
        $t->same(1, $signature['x509CertificateCount']);
        $t->same(strlen($certificateBase64), $signature['x509CertificateBase64CharLength']);

        $t->same('odf-signature-key-info-metadata-only', $keyInfo['byteExposurePolicy']);
        $t->same(false, $keyInfo['canExposeBytes']);
        $t->same(['Review Signing Key'], $keyInfo['keyNames']);
        $t->same([
            [
                'uri' => 'certs/reviewer.cer',
                'type' => 'http://www.w3.org/2000/09/xmldsig#rawX509Certificate',
            ],
        ], $keyInfo['retrievalMethods']);
        $t->same(1, $x509['certificateCount']);
        $t->same(strlen($certificateBase64), $x509['certificateBase64CharLength']);
        $t->same([
            [
                'base64CharLength' => strlen($certificateBase64),
                'base64Sha256' => hash('sha256', $certificateBase64),
            ],
        ], $x509['certificates']);
        $t->same(false, str_contains(json_encode($keyInfo, JSON_THROW_ON_ERROR), $certificateBase64));
        $t->same(['CN=ODF Reviewer,O=Example'], $x509['subjectNames']);
        $t->same([
            [
                'issuerName' => 'CN=Example CA,O=Example',
                'serialNumber' => '42',
            ],
        ], $x509['issuerSerials']);
    },
];
