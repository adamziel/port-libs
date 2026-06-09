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
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Shape review </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="81" name="Reviewer callout" descr="DrawingML text property audit"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                <wps:wsp>
                  <a:txBody>
                    <a:bodyPr/>
                    <a:p>
                      <a:pPr algn="ctr" lvl="1">
                        <a:buChar char="•"/>
                      </a:pPr>
                      <a:r>
                        <a:rPr b="1" i="1" u="sng" sz="1200" lang="en-US">
                          <a:solidFill><a:srgbClr val="2F5597"/></a:solidFill>
                          <a:latin typeface="Aptos"/>
                        </a:rPr>
                        <a:t>Preserve callout styling</a:t>
                      </a:r>
                    </a:p>
                  </a:txBody>
                </wps:wsp>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> for import.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $paragraph = $document->children[0] ?? null;
    if (!$paragraph instanceof AstNode || $paragraph->type !== 'paragraph') {
        throw new RuntimeException('DOCX DrawingML text property example did not import the paragraph');
    }

    $shapeText = $paragraph->children[1] ?? null;
    if (!$shapeText instanceof AstNode || $shapeText->type !== 'span') {
        throw new RuntimeException('DOCX DrawingML text property example did not import a shape text span');
    }

    $classes = $shapeText->attr('classes');
    if (!is_array($classes) || !in_array('docx-drawing-text', $classes, true)) {
        throw new RuntimeException('DOCX DrawingML text property example did not expose the drawing text class');
    }

    if (!str_contains($blocks, 'class="docx-drawing-text-paragraph-properties docx-drawing-text-align-ctr docx-drawing-text-level docx-drawing-text-bullet-char"')) {
        throw new RuntimeException('DOCX DrawingML text property example did not render paragraph property metadata');
    }
    if (!str_contains($blocks, 'class="docx-drawing-text-run-properties docx-drawing-text-bold docx-drawing-text-italic docx-drawing-text-underline docx-drawing-text-underline-sng docx-drawing-text-fill docx-drawing-text-font"')) {
        throw new RuntimeException('DOCX DrawingML text property example did not render run property metadata');
    }
    if (!str_contains($blocks, 'data-docx-drawing-text-fill-color="srgb:2F5597"')) {
        throw new RuntimeException('DOCX DrawingML text property example did not expose run fill color metadata');
    }

    echo "wordpress-docx-drawing-text-properties-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
