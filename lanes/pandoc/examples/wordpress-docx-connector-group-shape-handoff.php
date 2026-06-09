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
  xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
  xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Review handoff </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:anchor distT="120" distB="240" simplePos="0" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">
            <wp:extent cx="914400" cy="457200"/>
            <wp:wrapNone/>
            <wp:docPr id="81" name="Connector audit canvas" descr="Review connector route" title="Connector title"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                <wps:cxnSp>
                  <wps:cNvPr id="901" name="Connector 901" descr="Approver path" title="Connector nonvisual title"/>
                  <wps:cNvCnPr>
                    <a:cxnSpLocks noChangeShapeType="1" noMove="1" noResize="0"/>
                    <a:stCxn id="301" idx="2"/>
                    <a:endCxn id="302" idx="4"/>
                  </wps:cNvCnPr>
                  <wps:spPr>
                    <a:xfrm rot="2700000" flipH="1">
                      <a:off x="1000" y="2000"/>
                      <a:ext cx="3000" cy="4000"/>
                    </a:xfrm>
                    <a:prstGeom prst="bentConnector3"><a:avLst/></a:prstGeom>
                    <a:ln w="12700" cap="rnd" cmpd="sng" algn="ctr">
                      <a:solidFill><a:srgbClr val="4472C4"/></a:solidFill>
                    </a:ln>
                  </wps:spPr>
                </wps:cxnSp>
              </a:graphicData>
            </a:graphic>
          </wp:anchor>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="1828800" cy="914400"/>
            <wp:docPr id="82" name="Grouped review shapes" descr="Review annotation group" title="Group title"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup">
                <wpg:wgp>
                  <wpg:cNvGrpSpPr>
                    <a:grpSpLocks noUngrp="1" noSelect="1" noRot="0"/>
                  </wpg:cNvGrpSpPr>
                  <wpg:grpSpPr>
                    <a:xfrm rot="5400000" flipV="1">
                      <a:off x="10000" y="20000"/>
                      <a:ext cx="900000" cy="450000"/>
                      <a:chOff x="500" y="600"/>
                      <a:chExt cx="800000" cy="400000"/>
                    </a:xfrm>
                  </wpg:grpSpPr>
                  <wps:wsp>
                    <wps:cNvPr id="301" name="Grouped callout" descr="Grouped callout note"/>
                    <wps:spPr/>
                  </wps:wsp>
                </wpg:wgp>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);
$connector = $document->children[0]->children[1] ?? null;
$group = $document->children[0]->children[3] ?? null;

if (in_array('--self-test', $argv, true)) {
    if (!$connector instanceof AstNode || $connector->type !== 'span') {
        throw new RuntimeException('DOCX connector/group example did not import a connector placeholder');
    }
    if (!$group instanceof AstNode || $group->type !== 'span') {
        throw new RuntimeException('DOCX connector/group example did not import a group-shape placeholder');
    }

    $connectorAttrs = $connector->attr('attributes');
    if (!is_array($connectorAttrs) || ($connectorAttrs['data-docx-connector-start-id'] ?? null) !== '301') {
        throw new RuntimeException('DOCX connector/group example did not expose connector start metadata');
    }
    if (($connectorAttrs['data-docx-connector-end-id'] ?? null) !== '302') {
        throw new RuntimeException('DOCX connector/group example did not expose connector end metadata');
    }
    if (($connectorAttrs['data-docx-connector-line-color'] ?? null) !== 'srgb:4472C4') {
        throw new RuntimeException('DOCX connector/group example did not expose connector line color metadata');
    }

    $groupAttrs = $group->attr('attributes');
    if (!is_array($groupAttrs) || ($groupAttrs['data-docx-group-shape-child-count'] ?? null) !== '1') {
        throw new RuntimeException('DOCX connector/group example did not expose group child count metadata');
    }
    if (($groupAttrs['data-docx-group-shape-child-width-emu'] ?? null) !== '800000') {
        throw new RuntimeException('DOCX connector/group example did not expose group child transform metadata');
    }

    foreach ([
        'class="docx-drawing-placeholder docx-drawing-connector',
        'data-docx-connector-start-id="301"',
        'data-docx-connector-line-color="srgb:4472C4"',
        'class="docx-drawing-placeholder docx-drawing-group-shape',
        'data-docx-group-shape-child-width-emu="800000"',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX connector/group example did not render expected WordPress metadata: ' . $expected);
        }
    }

    echo "wordpress-docx-connector-group-shape-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
    'connector' => $connector instanceof AstNode ? $connector->attr('attributes') : null,
    'groupShape' => $group instanceof AstNode ? $group->attr('attributes') : null,
    'media' => $result['importReport']['media'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
