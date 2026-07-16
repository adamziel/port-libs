<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
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
  <Relationship Id="rIdPictureNv" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/nonvisual-picture.png"/>
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
            <wp:docPr id="31" name=""/>
            <a:graphic>
              <a:graphicData>
                <pic:pic>
                  <pic:nvPicPr>
                    <pic:cNvPr id="501" name="Picture source metadata" descr="Picture-level alt text" title="Picture-level title" hidden="1"/>
                    <pic:cNvPicPr preferRelativeResize="0">
                      <a:picLocks noChangeAspect="1" noCrop="true" noMove="0" noResize="1" noSelect="false"/>
                    </pic:cNvPicPr>
                  </pic:nvPicPr>
                  <pic:blipFill>
                    <a:blip r:embed="rIdPictureNv"/>
                  </pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
    ['name' => 'word/media/nonvisual-picture.png', 'data' => 'PICTURENV'],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);
$image = $document->children[0]->children[0] ?? null;

if (in_array('--self-test', $argv, true)) {
    if (!$image instanceof AstNode || $image->type !== 'image') {
        throw new RuntimeException('DOCX picture nonvisual example did not import an image node');
    }
    if ($image->attr('alt') !== 'Picture-level alt text' || $image->attr('title') !== 'Picture-level title') {
        throw new RuntimeException('DOCX picture nonvisual example did not fall back to picture-level alt/title metadata');
    }
    if (!in_array('docx-picture-hidden', $image->attr('classes') ?? [], true)) {
        throw new RuntimeException('DOCX picture nonvisual example did not expose hidden-state class metadata');
    }
    if (($image->attr('attributes')['data-docx-picture-nv-name'] ?? null) !== 'Picture source metadata') {
        throw new RuntimeException('DOCX picture nonvisual example did not expose cNvPr name metadata');
    }
    if (($image->attr('attributes')['data-docx-picture-lock-no-resize'] ?? null) !== 'true') {
        throw new RuntimeException('DOCX picture nonvisual example did not expose picture lock metadata');
    }
    if (!str_contains($blocks, 'class="docx-picture-nonvisual docx-picture-hidden docx-picture-locks')) {
        throw new RuntimeException('DOCX picture nonvisual example did not render review classes into WordPress blocks');
    }
    if (!str_contains($blocks, 'data-docx-picture-lock-no-select="false"')) {
        throw new RuntimeException('DOCX picture nonvisual example did not render false lock-state metadata');
    }

    echo "wordpress-docx-picture-nonvisual-handoff self-test passed\n";
    return;
}

echo json_encode([
    'alt' => $image instanceof AstNode ? $image->attr('alt') : null,
    'title' => $image instanceof AstNode ? $image->attr('title') : null,
    'classes' => $image instanceof AstNode ? $image->attr('classes') : null,
    'attributes' => $image instanceof AstNode ? $image->attr('attributes') : null,
    'blocks' => $blocks,
    'importReport' => $result['importReport']['media'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
