<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBackground" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/page-bg.png"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:o="urn:schemas-microsoft-com:office:office">
  <w:background w:color="DDEEFF" w:themeColor="accent1" w:themeTint="33">
    <v:background id="_x0000_s4096" style="mso-background-themecolor:accent1">
      <v:fill r:id="rIdBackground" o:title="Watermark texture" type="frame" color2="FFFFFF" recolor="t"/>
    </v:background>
  </w:background>
  <w:body>
    <w:p><w:r><w:t>Background packet body.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
    ['name' => 'word/media/page-bg.png', 'data' => 'BGPAYLOAD'],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);
$background = $result['metadata']['docxBackground'] ?? [];

if (in_array('--self-test', $argv, true)) {
    if (($background['cssBackgroundColor'] ?? null) !== '#DDEEFF') {
        throw new RuntimeException('DOCX background handoff did not preserve the CSS-safe background color');
    }
    if (($background['fill']['title'] ?? null) !== 'Watermark texture') {
        throw new RuntimeException('DOCX background handoff did not preserve VML fill title metadata');
    }
    if (($background['image']['targetPart'] ?? null) !== '/word/media/page-bg.png') {
        throw new RuntimeException('DOCX background handoff did not preserve the background image target part');
    }
    if (($background['image']['exists'] ?? null) !== true || ($background['image']['bytes'] ?? null) !== 9) {
        throw new RuntimeException('DOCX background handoff did not preflight the embedded background image bytes');
    }
    if (!str_contains($blocks, '<p>Background packet body.</p>')) {
        throw new RuntimeException('DOCX background handoff did not render the document body into WordPress blocks');
    }
    if (str_contains($blocks, 'Watermark texture')) {
        throw new RuntimeException('DOCX background handoff rendered review-only background metadata as visible content');
    }

    echo "wordpress-docx-background-handoff self-test passed\n";
    return;
}

echo json_encode([
    'background' => $background,
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
