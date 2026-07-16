<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Sidecar identity packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$scriptXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Main
End Sub</script:module>
XML;

$configurationXml = <<<'XML'
<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="Review"/>
XML;

$rdfXml = <<<'XML'
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <rdf:Description rdf:about="content.xml">
    <dc:title>Sidecar identity packet</dc:title>
  </rdf:Description>
</rdf:RDF>
XML;

$signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>
XML;

$thumbnailBytes = 'THUMBNAILPNG';
$fontBytes = 'WOFF2-FONT-BYTES';
$replacementBytes = 'REPLACEMENTPNG';
$layoutCacheBytes = 'LAYOUT-CACHE-BYTES';
$chartContentXml = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"><office:body/></office:document-content>';
$chartPreviewBytes = 'CHARTPNG';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
XML;
$manifestXml .= '  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png" manifest:size="' . strlen($thumbnailBytes) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml" manifest:size="' . strlen($signatureXml) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="' . strlen($scriptXml) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="' . strlen($configurationXml) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Fonts/ReviewSans.woff2" manifest:media-type="font/woff2" manifest:size="' . strlen($fontBytes) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml" manifest:size="' . strlen($rdfXml) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="ObjectReplacements/preview.png" manifest:media-type="image/png" manifest:size="' . strlen($replacementBytes) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . strlen($layoutCacheBytes) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml" manifest:size="' . strlen($chartContentXml) . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="Object%20Chart/Pictures/preview.png" manifest:media-type="image/png" manifest:size="' . strlen($chartPreviewBytes) . '"/>' . "\n"
    . '</manifest:manifest>';

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationXml, 'compressionMethod' => 0],
    ['name' => 'Fonts/ReviewSans.woff2', 'data' => $fontBytes, 'compressionMethod' => 0],
    ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
    ['name' => 'ObjectReplacements/preview.png', 'data' => $replacementBytes, 'compressionMethod' => 0],
    ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
    ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Object Chart/content.xml', 'data' => $chartContentXml, 'compressionMethod' => 0],
    ['name' => 'Object Chart/Pictures/preview.png', 'data' => $chartPreviewBytes, 'compressionMethod' => 0],
], 'odt sidecar identity counts');

return [
    'lifts ODT package sidecar counts into compact and rich identity summaries' => static function (TestRunner $t) use ($package): void {
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactInventory = $compactSummary['packageInventory'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];

        $t->same($compactInventory['corePackagePartCount'], $compactIdentity['corePackagePartCount']);
        $t->same($compactInventory['mediaResourcePartCount'], $compactIdentity['mediaResourcePartCount']);
        $t->same(1, $compactIdentity['packageThumbnailPartCount']);
        $t->same(1, $compactIdentity['packageSignaturePartCount']);
        $t->same(1, $compactIdentity['embeddedObjectPackageRootCount']);
        $t->same(2, $compactIdentity['embeddedObjectPackagePartCount']);
        $t->same(1, $compactIdentity['objectReplacementPartCount']);
        $t->same(1, $compactIdentity['scriptPackagePartCount']);
        $t->same(1, $compactIdentity['configurationPackagePartCount']);
        $t->same(1, $compactIdentity['fontPackagePartCount']);
        $t->same(1, $compactIdentity['rdfMetadataPartCount']);
        $t->same(1, $compactIdentity['layoutCachePartCount']);

        $t->same($richProvenance['corePackagePartCount'], $richIdentity['corePackagePartCount']);
        $t->same($richProvenance['mediaResourcePartCount'], $richIdentity['mediaResourcePartCount']);
        $t->same(1, $richIdentity['packageThumbnailPartCount']);
        $t->same(1, $richIdentity['packageSignaturePartCount']);
        $t->same(1, $richIdentity['embeddedObjectPackageCount']);
        $t->same(2, $richIdentity['embeddedObjectContainedPartCount']);
        $t->same(1, $richIdentity['objectReplacementPartCount']);
        $t->same(1, $richIdentity['scriptPackagePartCount']);
        $t->same(1, $richIdentity['configurationPackagePartCount']);
        $t->same(1, $richIdentity['packageFontPartCount']);
        $t->same(1, $richIdentity['rdfMetadataPartCount']);
        $t->same(1, $richIdentity['layoutCachePartCount']);

        $t->same($richProvenance, $richResult['document']->attr('manifest')['packageProvenance']);
        $t->same(false, $compactIdentity['canExposeBytes']);
        $t->same(false, $richIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $compactIdentity['byteExposurePolicy']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
    },
];
