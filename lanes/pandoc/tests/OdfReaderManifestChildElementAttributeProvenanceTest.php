<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest
  xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"
  xmlns:loext="urn:libreoffice:manifest"
  xmlns:wp="urn:wordpress:review"
  manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml">
    <wp:review-hint wp:state="manual" loext:source="content"/>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="7">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="hero-checksum"/>
    <loext:media-policy loext:role="review" wp:visibility="metadata-only"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest child element attribute provenance.</text:p>
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

return [
    'preserves ODT manifest child element attributes in metadata-only package provenance' => static function (TestRunner $t) use ($manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA'],
        ], 'odt manifest child attribute provenance'));

        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }

        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identityByPath = [];
        foreach ($provenance['packageIdentity']['manifestEntries'] as $item) {
            $identityByPath[$item['fullPath']] = $item;
        }

        $contentChild = $manifestByPart['content.xml']['customManifestChildElements'][0];
        $heroPolicy = $manifestByPart['Pictures/hero.png']['customManifestChildElements'][0];
        $heroEncryption = $manifestByPart['Pictures/hero.png']['manifestChildElements'][0];

        $t->same(['loext:source', 'wp:state'], $contentChild['attributeNames']);
        $t->same([
            'loext:source' => 'content',
            'wp:state' => 'manual',
        ], $contentChild['customAttributeMap']);
        $t->same('urn:wordpress:review', $contentChild['namespaceDeclarationMap']['xmlns:wp']);
        $t->same('urn:libreoffice:manifest', $contentChild['namespaceDeclarationMap']['xmlns:loext']);

        $t->same(true, $heroEncryption['structural']);
        $t->same(['manifest:checksum', 'manifest:checksum-type'], $heroEncryption['attributeNames']);
        $t->same([], $heroEncryption['customAttributeNames']);
        $t->same(true, $heroEncryption['attributes'][0]['structural']);
        $t->same('hero-checksum', $heroEncryption['attributes'][0]['value']);

        $t->same(false, $heroPolicy['structural']);
        $t->same([
            'loext:role' => 'review',
            'wp:visibility' => 'metadata-only',
        ], $heroPolicy['customAttributeMap']);

        $t->same(2, $provenance['manifestCustomChildElementCount']);
        $t->same('manual', $provenance['manifestCustomChildElementItems'][0]['customManifestChildElements'][0]['customAttributeMap']['wp:state']);
        $t->same('metadata-only', $provenance['manifestCustomChildElementItems'][1]['customManifestChildElements'][0]['customAttributeMap']['wp:visibility']);
        $t->same('content', $provenance['parts']['content.xml']['customManifestChildElements'][0]['customAttributeMap']['loext:source']);
        $t->same('review', $provenance['parts']['Pictures/hero.png']['customManifestChildElements'][0]['customAttributeMap']['loext:role']);
        $t->same('manual', $identityByPath['content.xml']['customManifestChildElements'][0]['customAttributeMap']['wp:state']);
        $t->same('metadata-only', $identityByPath['Pictures/hero.png']['customManifestChildElements'][0]['customAttributeMap']['wp:visibility']);
        $t->same(false, $provenance['packageIdentity']['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $provenance['packageIdentity']['byteExposurePolicy']);
    },
];
