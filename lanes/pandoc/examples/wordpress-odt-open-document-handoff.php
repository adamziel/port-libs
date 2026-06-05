<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:h text:outline-level="1">ODT import packet</text:h>
      <text:p>Reviewer<text:s/><text:span text:style-name="StrongText">summary</text:span><text:s/>keeps <text:a xlink:href="https://example.test/source-odt">source link</text:a>.</text:p>
      <text:list text:style-name="ReviewSteps" text:start-value="2">
        <text:list-item><text:p>Confirm media mapping</text:p></text:list-item>
        <text:list-item><text:p>Publish WordPress blocks</text:p></text:list-item>
      </text:list>
      <table:table table:name="ODT review matrix">
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2"><text:p>Scope</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Media</text:p></table:table-cell>
          <table:table-cell><text:p>Links</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
      <text:p><draw:frame draw:name="Review hero" svg:width="5cm" svg:height="3cm"><draw:image xlink:href="Pictures/review.png" xlink:type="simple"/></draw:frame></text:p>
      <text:p><draw:frame draw:name="Missing legacy image"><draw:image xlink:href="Pictures/missing.png" xlink:type="simple"/></draw:frame></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="StrongText" style:family="text">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="2"/>
    </text:list-style>
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
    <dc:title>WordPress ODT review packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <meta:creation-date>2026-06-04T12:00:00Z</meta:creation-date>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png" manifest:size="7"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdtReader::ODT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'Pictures/review.png', 'data' => 'PNGDATA'],
]);

$reader = new OdtReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

$summary = [
    'metadata' => $result['metadata'],
    'blockCount' => count($result['document']->children),
    'manifestEntryCount' => $result['importReport']['manifestEntryCount'],
    'media' => $result['importReport']['media'],
    'wordpressBlocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['metadata']['title'] ?? '') !== 'WordPress ODT review packet') {
        throw new RuntimeException('ODT handoff self-test missing metadata title');
    }
    if (($summary['media']['embeddedCount'] ?? 0) !== 1 || ($summary['media']['missingCount'] ?? 0) !== 1) {
        throw new RuntimeException('ODT handoff self-test missing media import report');
    }
    if (!str_contains($blocks, '<h1 id="odt-import-packet">ODT import packet</h1>')) {
        throw new RuntimeException('ODT handoff self-test missing heading block');
    }
    if (!str_contains($blocks, '<figcaption class="wp-element-caption">ODT review matrix</figcaption>')) {
        throw new RuntimeException('ODT handoff self-test missing table caption');
    }
    if (!str_contains($blocks, '<img src="Pictures/review.png" alt="Review hero" title="Review hero"/>')) {
        throw new RuntimeException('ODT handoff self-test missing image block');
    }

    fwrite(STDOUT, "ODT OpenDocument handoff self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
