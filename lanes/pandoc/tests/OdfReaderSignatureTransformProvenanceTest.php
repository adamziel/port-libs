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
      <text:p>Signature transform provenance packet.</text:p>
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

$signatureXml = <<<'XML'
<dsig:document-signatures
  xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"
  xmlns:xf="http://www.w3.org/2002/06/xmldsig-filter2">
  <dsig:Signature Id="transform-signature">
    <dsig:SignedInfo>
      <dsig:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <dsig:Reference URI="content.xml">
        <dsig:Transforms>
          <dsig:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
            <dsig:XPath>not(ancestor-or-self::office:annotation)</dsig:XPath>
          </dsig:Transform>
          <dsig:Transform Algorithm="http://www.w3.org/2002/06/xmldsig-filter2">
            <xf:XPath Filter="subtract">ancestor-or-self::office:annotation</xf:XPath>
          </dsig:Transform>
          <dsig:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
        </dsig:Transforms>
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>contentdigest</dsig:DigestValue>
      </dsig:Reference>
      <dsig:Reference URI="Pictures/hero.png">
        <dsig:Transforms>
          <dsig:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#base64"/>
        </dsig:Transforms>
        <dsig:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <dsig:DigestValue>picturedigest</dsig:DigestValue>
      </dsig:Reference>
    </dsig:SignedInfo>
    <dsig:SignatureValue>signature-bytes</dsig:SignatureValue>
  </dsig:Signature>
</dsig:document-signatures>
XML;

return [
    'preserves ODT XML signature transform XPath filters as review metadata' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml, $signatureXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml],
        ], 'odt signature transform provenance'));
        $signatures = $result['signatureMetadata'];
        $part = $signatures['parts'][0];
        $references = $part['signatures'][0]['references'];
        $content = $references[0];
        $image = $references[1];

        $t->same($signatures, $result['document']->attr('signatureMetadata'));
        $t->same($signatures, $result['importReport']['signatureMetadata']);
        $t->same(4, $signatures['transformCount']);
        $t->same(2, $signatures['xpathTransformCount']);
        $t->same(2, $signatures['xpathExpressionCount']);
        $t->same(4, $part['transformCount']);
        $t->same(2, $part['xpathTransformCount']);
        $t->same(2, $part['xpathExpressionCount']);

        $t->same(3, $content['transformCount']);
        $t->same([
            'http://www.w3.org/TR/1999/REC-xpath-19991116',
            'http://www.w3.org/2002/06/xmldsig-filter2',
            'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
        ], $content['transforms']);
        $t->same(2, $content['xpathTransformCount']);
        $t->same(2, $content['xpathExpressionCount']);
        $t->same('http://www.w3.org/TR/1999/REC-xpath-19991116', $content['transformItems'][0]['algorithm']);
        $t->same(1, $content['transformItems'][0]['xpathCount']);
        $t->same(['not(ancestor-or-self::office:annotation)'], $content['transformItems'][0]['xpaths']);
        $t->same([
            [
                'expression' => 'not(ancestor-or-self::office:annotation)',
                'namespaceUri' => 'http://www.w3.org/2000/09/xmldsig#',
            ],
        ], $content['transformItems'][0]['xpathItems']);
        $t->same('http://www.w3.org/2002/06/xmldsig-filter2', $content['transformItems'][1]['algorithm']);
        $t->same(1, $content['transformItems'][1]['xpathCount']);
        $t->same(['ancestor-or-self::office:annotation'], $content['transformItems'][1]['xpaths']);
        $t->same([
            [
                'expression' => 'ancestor-or-self::office:annotation',
                'namespaceUri' => 'http://www.w3.org/2002/06/xmldsig-filter2',
                'filter' => 'subtract',
            ],
        ], $content['transformItems'][1]['xpathItems']);
        $t->same('http://www.w3.org/2000/09/xmldsig#enveloped-signature', $content['transformItems'][2]['algorithm']);
        $t->same(false, array_key_exists('xpaths', $content['transformItems'][2]));

        $t->same(1, $image['transformCount']);
        $t->same(['http://www.w3.org/2000/09/xmldsig#base64'], $image['transforms']);
        $t->same(0, $image['xpathTransformCount']);
        $t->same(0, $image['xpathExpressionCount']);
        $t->same('Pictures/hero.png', $image['part']);
    },
];
