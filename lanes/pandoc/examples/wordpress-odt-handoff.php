<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpenDocumentReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="Heading_20_2" style:family="paragraph" style:display-name="Heading 2"/>
    <style:style style:name="ReviewHeading" style:family="paragraph" style:display-name="Migration Review" style:parent-style-name="Heading_20_2"/>
    <style:style style:name="StrongEmphasis" style:family="text">
      <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3" style:num-suffix=")"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:h text:outline-level="1">ODT source packet</text:h>
      <text:p text:style-name="ReviewHeading">Reviewer checklist</text:p>
      <text:p>Import reviewer keeps <text:span text:style-name="StrongEmphasis">source formatting</text:span> and <text:a xlink:href="https://example.test/source.odt">source link</text:a> visible.<text:note text:id="n1" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote import note.</text:p></text:note-body></text:note></text:p>
      <text:list text:style-name="ReviewSteps" text:start-value="3">
        <text:list-item><text:p>Confirm media map</text:p></text:list-item>
        <text:list-item><text:p>Publish packet</text:p></text:list-item>
      </text:list>
      <text:p><draw:frame draw:name="Review image"><svg:title>Review media</svg:title><svg:desc>Review media alt</svg:desc><draw:image xlink:href="Pictures/review.png" xlink:type="simple"/></draw:frame></text:p>
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
    <dc:title>WordPress ODT handoff</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <meta:creation-date>2026-06-04T05:39:23Z</meta:creation-date>
  </office:meta>
</office:document-meta>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentReader::ODT_MEDIA_TYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
    ['name' => 'Pictures/review.png', 'data' => 'PNGDATA'],
]);

$result = (new OpenDocumentReader())->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);
$summary = [
    'metadata' => $result['metadata'],
    'manifestMediaType' => $result['manifest']['/']['mediaType'],
    'blockCount' => count($result['document']->children),
    'wordpressBlocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['metadata']['title'] ?? '') !== 'WordPress ODT handoff') {
        throw new RuntimeException('ODT handoff self-test missing metadata title');
    }

    foreach ([
        '<h1 id="odt-source-packet">ODT source packet</h1>',
        '<h2 id="reviewer-checklist">Reviewer checklist</h2>',
        '<strong><em>source formatting</em></strong>',
        '<a href="https://example.test/source.odt">source link</a>',
        '<ol start="3" type="a"><li>Confirm media map</li><li>Publish packet</li></ol>',
        '<img src="Pictures/review.png" alt="Review media alt" title="Review media"/>',
        'ODT footnote import note.',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('ODT handoff self-test missing: ' . $needle);
        }
    }

    echo "odt handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
