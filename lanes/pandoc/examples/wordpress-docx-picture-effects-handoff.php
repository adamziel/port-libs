<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPictureEffects" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/effects-picture.png"/>
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
            <wp:docPr id="32" name="Picture effects" descr="Picture effects alt" title="Picture effects title"/>
            <a:graphic>
              <a:graphicData>
                <pic:pic>
                  <pic:blipFill>
                    <a:blip r:embed="rIdPictureEffects">
                      <a:alphaModFix amt="65000"/>
                      <a:lum bright="12000" contrast="-8000"/>
                      <a:duotone><a:srgbClr val="336699"/><a:schemeClr val="accent2"/></a:duotone>
                    </a:blip>
                  </pic:blipFill>
                  <pic:spPr>
                    <a:effectLst>
                      <a:outerShdw blurRad="57150" dist="38100" dir="5400000" algn="ctr" rotWithShape="0"><a:srgbClr val="112233"/></a:outerShdw>
                      <a:softEdge rad="19050"/>
                      <a:reflection blurRad="63500" dist="12700" dir="2700000" stA="45000" endA="0"/>
                    </a:effectLst>
                  </pic:spPr>
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
    ['name' => 'word/media/effects-picture.png', 'data' => 'EFFECTPNG'],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);
$image = $document->children[0]->children[0] ?? null;

if (in_array('--self-test', $argv, true)) {
    if (!$image instanceof AstNode || $image->type !== 'image') {
        throw new RuntimeException('DOCX picture effects example did not import an image node');
    }

    $attrs = $image->attr('attributes');
    if (!is_array($attrs) || ($attrs['data-docx-picture-blip-effects'] ?? null) !== 'alphaModFix lum duotone') {
        throw new RuntimeException('DOCX picture effects example did not expose blip effect metadata');
    }
    if (($attrs['data-docx-picture-shadow-outer-color'] ?? null) !== 'srgb:112233') {
        throw new RuntimeException('DOCX picture effects example did not expose outer shadow color metadata');
    }
    if (($attrs['data-docx-picture-reflection-start-alpha'] ?? null) !== '45000') {
        throw new RuntimeException('DOCX picture effects example did not expose reflection alpha metadata');
    }
    if (!str_contains($blocks, 'class="docx-picture-effect docx-picture-blip-effect')) {
        throw new RuntimeException('DOCX picture effects example did not render WordPress review classes');
    }
    if (!str_contains($blocks, 'data-docx-picture-soft-edge-radius-emu="19050"')) {
        throw new RuntimeException('DOCX picture effects example did not render soft-edge metadata');
    }

    echo "wordpress-docx-picture-effects-handoff self-test passed\n";
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
