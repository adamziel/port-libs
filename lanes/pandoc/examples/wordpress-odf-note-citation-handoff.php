<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
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
      <text:p>Import note<text:note text:id="source-note" text:note-class="footnote"><text:note-citation>F<text:s text:c="2"/>7<text:tab/>b<text:line-break/>continued</text:note-citation><text:note-body><text:p>Reviewer citation marker came from the source ODT.</text:p></text:note-body></text:note> keeps review metadata.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ODF note citation handoff</dc:title>
  </office:meta>
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
$note = null;
foreach ($document->children as $block) {
    foreach ($block->children as $child) {
        if ($child instanceof AstNode && $child->type === 'note') {
            $note = $child;
            break 2;
        }
    }
}

if (in_array('--self-test', $argv, true)) {
    if (!$note instanceof AstNode || $note->attr('citation') !== "F  7 b\ncontinued") {
        throw new RuntimeException('Expected ODT note citation to preserve generated spaces, tab normalization, and line break metadata');
    }
    if (($result['importReport']['content']['noteCount'] ?? 0) !== 1) {
        throw new RuntimeException('Expected the ODT source note to be counted in the import report');
    }
    if (!str_contains($blocks, '<li id="fn-1"><p>Reviewer citation marker came from the source ODT.</p>')) {
        throw new RuntimeException('Expected ODT note body to render in WordPress footnote review output');
    }

    echo "odf note citation handoff self-test ok\n";
    return;
}

echo "ODF note citation handoff:\n";
echo 'citation=' . ($note instanceof AstNode ? str_replace("\n", '\\n', (string) $note->attr('citation', '')) : '') . "\n";
echo $blocks;
