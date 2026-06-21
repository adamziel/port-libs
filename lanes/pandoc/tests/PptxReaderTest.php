<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$buildPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rId2"/>
    <p:sldId id="444" r:id="rId3"/>
    <p:sldId id="459" r:id="rId4"/>
    <p:sldId id="462" r:id="rId5"/>
  </p:sldIdLst>
  <p:sldSz cx="12192000" cy="6858000"/>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide2.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide3.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide4.xml"/>
</Relationships>
XML);

    $slideOpen = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
XML;
    $slideClose = <<<'XML'
  </p:spTree></p:cSld>
</p:sld>
XML;
    $titleShape = static fn (string $title): string => <<<XML
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$title}</a:t></a:r></a:p></p:txBody>
    </p:sp>
XML;

    $zip->addFromString('ppt/slides/slide1.xml', $slideOpen . $titleShape('LLMs') . <<<'XML'
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Content Placeholder 2"/><p:cNvSpPr/><p:nvPr><p:ph idx="1"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>Provider </a:t></a:r><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>&#61664; Available LLMs &#8211; who manages? How?</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>EW maintained list of &#8220;approved&#8221; LLMs for Universal workers</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Rebuilding of UWs to the &#8220;Newgen&#8221; thing completely</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Streaming support</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Multimodal (voice streaming) models?</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
XML . $slideClose);

    $zip->addFromString('ppt/slides/slide2.xml', $slideOpen . <<<'XML'
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Everworker</a:t></a:r><a:r><a:t> </a:t></a:r><a:r><a:t>venn</a:t></a:r><a:r><a:t> </a:t></a:r><a:r><a:t>diagram</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="4" name="Oval 3"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>SKILLS</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Specialized Workers / Workflows:</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>n8n, UI Path, </a:t></a:r></a:p>
        <a:p><a:r><a:t>other RPA</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="5" name="Oval 4"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>BRAINS</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Universal Workers / AI Agents:</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>openai , anthropic,</a:t></a:r></a:p>
        <a:p><a:r><a:t>Crew AI, other </a:t></a:r></a:p>
        <a:p><a:r><a:t>&#8220;AI natives&#8221;</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="6" name="Oval 5"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>KNOWLEDGE </a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Data / </a:t></a:r></a:p>
        <a:p><a:r><a:t>RAG Pipelines</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Vector DBs, specialized data prep vendors, &#8230;</a:t></a:r></a:p>
        <a:p><a:r><a:t>glean</a:t></a:r></a:p>
        <a:p><a:r><a:t>EW</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
XML . $slideClose);

    $zip->addFromString('ppt/slides/slide3.xml', $slideOpen . $titleShape('Table') . <<<'XML'
    <p:graphicFrame>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl>
        <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Col1</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Col2</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Col3</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
        <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Name</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Anton</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Antich</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
        <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Age</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>23</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>years</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
      </a:tbl></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Picture 6" descr=""/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rId2"/></p:blipFill>
    </p:pic>
XML . $slideClose);
    $zip->addFromString('ppt/slides/_rels/slide3.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/image1.png', 'fake-png-bytes');

    $zip->addFromString('ppt/slides/slide4.xml', $slideOpen . $titleShape('Smart Art') . <<<'XML'
    <p:graphicFrame>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" r:dm="rId2" r:lo="rId3"/></a:graphicData></a:graphic>
    </p:graphicFrame>
XML . $slideClose);
    $zip->addFromString('ppt/slides/_rels/slide4.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/chevron2"><dgm:title val=""/></dgm:layoutDef>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="0" type="doc"><dgm:t><a:p/></dgm:t></dgm:pt>
    <dgm:pt modelId="1"><dgm:t><a:p><a:r><a:t>First</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="11"><dgm:t><a:p><a:r><a:t>another</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="12"><dgm:t><a:p><a:r><a:t>subtitle</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="2"><dgm:t><a:p><a:r><a:t>Second</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="21"><dgm:t><a:p><a:r><a:t>and yet again</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="22"><dgm:t><a:p><a:r><a:t>yet more</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="0" destId="1"/>
    <dgm:cxn srcId="1" destId="11"/>
    <dgm:cxn srcId="1" destId="12"/>
    <dgm:cxn srcId="0" destId="2"/>
    <dgm:cxn srcId="2" destId="21"/>
    <dgm:cxn srcId="2" destId="22"/>
  </dgm:cxnLst>
</dgm:dataModel>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$nodesOfType = static function (AstNode $node, string $type) use (&$nodesOfType): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$nodesOfType($child, $type));
    }

    return $nodes;
};

return [
    'matches pinned upstream pptx reader basic fixture semantics' => static function (TestRunner $t) use ($buildPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');
        $blocks = (new WordPressBlockWriter())->write($document);
        $tables = $nodesOfType($document, 'table');
        $images = $nodesOfType($document, 'image');
        $divs = $nodesOfType($document, 'div');

        $t->same('pptx', $document->attr('sourceFormat'));
        $t->same([], $document->attr('meta'));
        $t->same(1, $review['upstreamEvidence']['denominator'] ?? null);
        $t->same(['test/pptx-reader/basic.pptx', 'test/pptx-reader/basic.native'], $review['upstreamEvidence']['fixtures'] ?? null);
        $t->same(4, $review['slideCount'] ?? null);

        $t->same('heading', $document->children[0]->type);
        $t->same('slide-1', $document->children[0]->attr('id'));
        $t->same('LLMs', $document->children[0]->attr('text'));
        $t->same('bullet_list', $document->children[1]->type);
        $t->same(5, count($document->children[1]->children));
        $t->contains('Provider', $document->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->contains('Available LLMs', $document->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('slide-2', $document->children[2]->attr('id'));
        $t->same('Everworker   venn   diagram', $document->children[2]->attr('text'));
        $t->same('SKILLS', $document->children[3]->attr('text'));

        $t->same(1, count($tables));
        $t->same(['Col1', 'Col2', 'Col3'], array_map(static fn (AstNode $cell): string => (string) $cell->attr('text'), $tables[0]->children[0]->children[0]->children));
        $t->same('Name', $tables[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('23', $tables[0]->children[1]->children[1]->children[1]->attr('text'));
        $t->same(1, count($images));
        $t->same('ppt/media/image1.png', $images[0]->attr('url'));
        $t->same('Picture 6', $images[0]->attr('title'));

        $t->same(1, count($divs));
        $t->same(['smartart', 'chevron2'], $divs[0]->attr('classes'));
        $t->same(['layout' => 'chevron2'], $divs[0]->attr('attributes'));
        $t->same('strong', $divs[0]->children[0]->children[0]->type);
        $t->same('First', $divs[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('another', $divs[0]->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Second', $divs[0]->children[2]->children[0]->children[0]->attr('text'));

        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "LLMs" ]', $native);
        $t->contains('BulletList', $native);
        $t->contains('Table ( "" , [  ] , [  ] )', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [  ] ( "ppt/media/image1.png" , "Picture 6" )', $native);
        $t->contains('Div ( "" , [ "smartart" , "chevron2" ] , [ ( "layout" , "chevron2" ) ] )', $native);
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('<th>Col1</th>', $blocks);
        $t->contains('ppt/media/image1.png', $blocks);
    },

    'reads pptx bytes through the converter input path' => static function (TestRunner $t) use ($buildPptxPackage): void {
        $document = PandocConverter::read($buildPptxPackage(), 'pptx');
        $html = PandocConverter::write($document, 'html');

        $t->same('pptx', $document->attr('sourceFormat'));
        $t->same('LLMs', $document->children[0]->attr('text'));
        $t->contains('<h2 id="slide-1">LLMs</h2>', $html);
        $t->contains('<th>Col1</th>', $html);
        $t->contains('<img src="ppt/media/image1.png"', $html);
    },

    'rejects pptx packages without a presentation relationship' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($bytes));
    },
];
