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
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"/>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Import source <text:database-display text:database-name="ImportDS" text:table-name="wp_posts" text:table-type="table" text:column-name="post_title">Imported post title</text:database-display> moved to row <text:database-row-number text:database-name="ImportDS" text:table-name="wp_posts" text:row-number="12"/>.</text:p>
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
    if (($result['importReport']['content']['fieldCount'] ?? 0) !== 2) {
        throw new RuntimeException('Expected ODT database fields to be counted in the import report');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-database-display" data-odf-field-type="database-display" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-table-type="table" data-odf-field-column-name="post_title">Imported post title</span>')) {
        throw new RuntimeException('Expected ODT database-display field metadata to render in WordPress blocks');
    }
    if (!str_contains($blocks, '<span class="odf-field odf-field-database-row-number" data-odf-field-type="database-row-number" data-odf-field-database-name="ImportDS" data-odf-field-table-name="wp_posts" data-odf-field-row-number="12">12</span>')) {
        throw new RuntimeException('Expected ODT database-row-number fallback value to render in WordPress blocks');
    }

    echo "odf database field handoff self-test ok\n";
    return;
}

echo $blocks;
