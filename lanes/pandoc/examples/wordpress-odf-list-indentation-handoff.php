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
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ReviewQuote" style:family="paragraph" style:display-name="Review Quote">
      <style:paragraph-properties fo:margin-left="8mm"/>
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
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="ReviewQuote">Top-level source note remains quoted.</text:p>
      <text:list text:style-name="ReviewSteps">
        <text:list-item><text:p text:style-name="ReviewQuote">Indented checklist paragraph remains a list item.</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:meta/>
</office:document-meta>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml],
]);

$result = (new OdfReader())->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (in_array('--self-test', $argv, true)) {
    if (($result['importReport']['content']['blockquoteCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected only the top-level indented ODF paragraph to be counted as a blockquote');
    }
    if (!str_contains($blocks, '<blockquote class="wp-block-quote"><p>Top-level source note remains quoted.</p></blockquote>')) {
        throw new RuntimeException('Expected the top-level ODF quote-width paragraph to render as a WordPress quote');
    }
    if (!str_contains($blocks, '<ol><li>Indented checklist paragraph remains a list item.</li></ol>')) {
        throw new RuntimeException('Expected the indented ODF list paragraph to remain a normal WordPress list item');
    }
    if (str_contains($blocks, '<li><blockquote')) {
        throw new RuntimeException('ODF list paragraph indentation must not render as a nested WordPress quote');
    }

    echo "odf list indentation handoff self-test ok\n";
    return;
}

echo $blocks;
