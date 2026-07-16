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
  <Relationship Id="rIdLinkedHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/linked-hero.png"/>
  <Relationship Id="rIdUnsafeHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/unsafe-hero.png"/>
  <Relationship Id="rIdHeroClick" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source-packet?media=hero" TargetMode="External"/>
  <Relationship Id="rIdHeroHover" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source-packet/preview" TargetMode="External"/>
  <Relationship Id="rIdUnsafeClick" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="25" name="Linked hero" descr="Linked hero alt" title="Hero drawing title">
              <a:hlinkClick r:id="rIdHeroClick" tooltip="Open source packet" history="1" highlightClick="1"/>
              <a:hlinkHover r:id="rIdHeroHover" tooltip="Preview source packet"/>
            </wp:docPr>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdLinkedHero"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
          <wp:inline>
            <wp:docPr id="26" name="Unsafe linked hero" descr="Unsafe click alt" title="Unsafe drawing title">
              <a:hlinkClick r:id="rIdUnsafeClick" tooltip="Unsafe source packet"/>
            </wp:docPr>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdUnsafeHero"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
    ['name' => 'word/media/linked-hero.png', 'data' => 'LINKEDHERO'],
    ['name' => 'word/media/unsafe-hero.png', 'data' => 'UNSAFEHERO'],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'linked image anchor' => '<a href="https://example.test/source-packet?media=hero" title="Open source packet" class="docx-drawing-hyperlink docx-drawing-hyperlink-click" data-docx-drawing-hyperlink="click" data-docx-relationship-id="rIdHeroClick"',
        'nested linked image' => '<img src="word/media/linked-hero.png" alt="Linked hero alt" title="Hero drawing title"/>',
        'hover metadata' => 'data-docx-drawing-hover-url="https://example.test/source-packet/preview"',
        'unsafe image remains visible' => '<img src="word/media/unsafe-hero.png" alt="Unsafe click alt" title="Unsafe drawing title"/>',
    ] as $name => $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX DrawingML hyperlink handoff missing ' . $name);
        }
    }

    if (str_contains($blocks, 'javascript:alert')) {
        throw new RuntimeException('DOCX DrawingML hyperlink handoff rendered unsafe URL');
    }

    echo "wordpress-docx-drawing-hyperlink-handoff self-test passed\n";
    return;
}

echo json_encode([
    'childCount' => count($document->children),
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
