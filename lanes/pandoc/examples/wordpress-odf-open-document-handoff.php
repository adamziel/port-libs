<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source-hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ImportHeading" style:family="paragraph" style:display-name="Import Heading" style:default-outline-level="1"/>
    <style:style style:name="StrongSource" style:family="text">
      <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="1"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <text:h text:outline-level="1" text:style-name="ImportHeading">ODT source packet</text:h>
      <text:p>Reviewer <text:span text:style-name="StrongSource">summary</text:span> keeps <text:a xlink:href="https://example.test/odt-source">source URL</text:a> and annotations<office:annotation><dc:creator>Migration Desk</dc:creator><dc:date>2026-06-04T23:20:00Z</dc:date><text:p>Check imported captions before publishing.</text:p></office:annotation>.</text:p>
      <text:list text:style-name="ReviewSteps">
        <text:list-item><text:p>Match ODT media to WordPress attachments</text:p></text:list-item>
        <text:list-item><text:p>Review table spans</text:p></text:list-item>
      </text:list>
      <draw:frame draw:name="Source hero">
        <draw:image xlink:href="Pictures/source-hero.png">
          <svg:title>Source hero</svg:title>
          <svg:desc>ODT source hero alt</svg:desc>
        </draw:image>
      </draw:frame>
      <table:table table:name="Review">
        <table:table-row>
          <table:table-cell><text:p>Item</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2"><text:p>Ready for block import review</text:p></table:table-cell>
          <table:covered-table-cell/>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>WordPress ODT source packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta:keyword>odt</meta:keyword>
    <meta:document-statistic meta:page-count="1" meta:word-count="64" meta:image-count="1"/>
  </office:meta>
</office:document-meta>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Pictures/source-hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
]);

$reader = new OdfReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (($argv[1] ?? '') === '--self-test') {
    if ($result['metadata']['title'] !== 'WordPress ODT source packet') {
        throw new RuntimeException('Expected ODT title metadata');
    }
    if (($result['media'][0]['part'] ?? '') !== 'Pictures/source-hero.png') {
        throw new RuntimeException('Expected ODT image manifest media to be reported');
    }
    if (!str_contains($blocks, '<h1>ODT source packet</h1>')) {
        throw new RuntimeException('Expected ODT heading to render as a WordPress heading block');
    }
    if (!str_contains($blocks, '<a href="https://example.test/odt-source">source URL</a>')) {
        throw new RuntimeException('Expected ODT source link to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<section class="footnotes" role="doc-endnotes">')) {
        throw new RuntimeException('Expected ODT annotation to render as a review footnote');
    }
    if (!str_contains($blocks, '<td colspan="2"><p>Ready for block import review</p></td>')) {
        throw new RuntimeException('Expected ODT table colspan to survive WordPress table handoff');
    }

    echo "odf open document handoff self-test ok\n";
    exit(0);
}

echo "ODF OpenDocument handoff for WordPress import:\n";
echo 'title=' . ($result['metadata']['title'] ?? '') . "\n";
echo 'creator=' . ($result['metadata']['creator'] ?? '') . "\n";
echo 'manifestItems=' . count($result['manifest']) . "\n";
echo 'mediaItems=' . count($result['media']) . "\n";
echo 'styleCount=' . count($result['styles']) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
