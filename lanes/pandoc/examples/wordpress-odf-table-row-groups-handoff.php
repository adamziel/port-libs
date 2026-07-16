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
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Import Status">
        <table:table-header-rows>
          <table:table-row>
            <table:table-cell><text:p>Post</text:p></table:table-cell>
            <table:table-cell><text:p>Status</text:p></table:table-cell>
          </table:table-row>
        </table:table-header-rows>
        <table:table-rows>
          <table:table-row>
            <table:table-cell><text:p>Draft packet</text:p></table:table-cell>
            <table:table-cell><text:p>Needs review</text:p></table:table-cell>
          </table:table-row>
          <table:table-row>
            <table:table-cell><text:p>Ready packet</text:p></table:table-cell>
            <table:table-cell><text:p>Ready</text:p></table:table-cell>
          </table:table-row>
        </table:table-rows>
        <table:table-row>
          <table:table-cell><text:p>Legal packet</text:p></table:table-cell>
          <table:table-cell><text:p>Legal</text:p></table:table-cell>
        </table:table-row>
        <table:table-footer-rows>
          <table:table-row>
            <table:table-cell><text:p>Total packets</text:p></table:table-cell>
            <table:table-cell><text:p>3</text:p></table:table-cell>
          </table:table-row>
        </table:table-footer-rows>
      </table:table>
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
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $table = $document->children[0] ?? null;
    if (!$table instanceof \PortLibs\Pandoc\AstNode || $table->type !== 'table') {
        throw new RuntimeException('Expected ODT row-group fixture to produce a table node');
    }

    $sections = array_map(static fn ($section): string => $section->type, $table->children);
    if ($sections !== ['table_head', 'table_body', 'table_foot']) {
        throw new RuntimeException('Expected ODT header, grouped body, and footer rows to produce table sections');
    }

    $geometry = $table->attr('tableGeometry');
    $rowGroups = is_array($geometry) ? ($geometry['rowGroups'] ?? []) : [];
    if (($rowGroups[1]['bodyRowCount'] ?? null) !== 3) {
        throw new RuntimeException('Expected grouped and direct ODT body rows to be preserved');
    }
    if (($rowGroups[2]['footRowCount'] ?? null) !== 1) {
        throw new RuntimeException('Expected ODT footer rows to be preserved');
    }
    if (!str_contains($blocks, '<tbody><tr><td><p>Draft packet</p></td><td><p>Needs review</p></td></tr><tr><td><p>Ready packet</p></td><td><p>Ready</p></td></tr><tr><td><p>Legal packet</p></td><td><p>Legal</p></td></tr></tbody>')) {
        throw new RuntimeException('Expected grouped ODT body rows to render in WordPress table body');
    }
    if (!str_contains($blocks, '<tfoot><tr><td><p>Total packets</p></td><td><p>3</p></td></tr></tfoot>')) {
        throw new RuntimeException('Expected ODT footer rows to render in WordPress table footer');
    }

    echo "odf table row groups handoff self-test ok\n";
    return;
}

echo $blocks;
