<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:h text:outline-level="2" text:style-name="Heading_20_2">Migration Packet</text:h>
      <text:p text:style-name="Text_20_body">Imported <text:a xlink:href="https://example.test/source">source</text:a><text:s text:c="2"/>packet<text:line-break/>continued.</text:p>
      <text:p text:style-name="Text_20_body"><draw:frame draw:name="hero"><draw:image xlink:href="Pictures/hero.png"><svg:title>Hero image</svg:title></draw:image></draw:frame></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="Text_20_body" style:display-name="Text body" style:family="paragraph"/>
    <style:style style:name="Heading_20_2" style:display-name="Heading 2" style:family="paragraph" style:parent-style-name="Heading"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  office:version="1.3">
  <office:meta>
    <meta:generator>Pandoc/3.8-test</meta:generator>
    <dc:title>Migration Packet</dc:title>
    <dc:description>Imported ODT for WordPress review</dc:description>
    <meta:keyword>import, odt, review</meta:keyword>
    <dc:creator>Reviewer</dc:creator>
    <meta:user-defined meta:name="source-system" meta:value-type="string">LibreOffice export</meta:user-defined>
  </office:meta>
</office:document-meta>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA'],
]);

$odt = OpenDocumentPackage::fromPackage($package);
$document = $odt->readContentDocument();
$blocks = (new WordPressBlockWriter())->write($document);
$summary = $odt->summarize();

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['metadata']['title'] ?? null) !== 'Migration Packet') {
        throw new RuntimeException('ODT metadata title was not mapped');
    }

    if (($summary['mediaParts'][0]['path'] ?? null) !== 'Pictures/hero.png') {
        throw new RuntimeException('ODT image manifest part was not mapped');
    }

    if (!str_contains($blocks, '<h2>Migration Packet</h2>')) {
        throw new RuntimeException('ODT heading was not rendered to WordPress blocks');
    }

    if (!str_contains($blocks, '<img src="Pictures/hero.png" alt="Hero image"/>')) {
        throw new RuntimeException('ODT image was not rendered to WordPress blocks');
    }

    echo "odt package preflight self-test ok\n";
    return;
}

echo json_encode([
    'summary' => $summary,
    'wordpressBlocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
