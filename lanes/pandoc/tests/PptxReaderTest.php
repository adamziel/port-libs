<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads pptx package slides text media notes and tables into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/><Override PartName="/ppt/slides/slide2.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/><Override PartName="/ppt/notesSlides/notesSlide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>PPTX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded PPTX reader smoke.</dc:description><cp:keywords>slides,import</cp:keywords><dcterms:created>2026-06-25T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0"?>
<p:presentation
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldSz cx="12192000" cy="6858000"/>
  <p:sldIdLst>
    <p:sldId id="256" r:id="rId1"/>
    <p:sldId id="257" r:id="rId2"/>
  </p:sldIdLst>
</p:presentation>
XML);
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide2.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/><Relationship Id="rIdHyper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/pptx" TargetMode="External"/><Relationship Id="rIdNotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target="../notesSlides/notesSlide1.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0"?>
<p:sld
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld>
    <p:spTree>
      <p:nvGrpSpPr/><p:grpSpPr/>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="1" name="Title"/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Slide One</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Body"/><p:nvPr/></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/>
          <a:p><a:r><a:t>A </a:t></a:r><a:r><a:rPr b="1"><a:hlinkClick r:id="rIdHyper"/></a:rPr><a:t>linked bold</a:t></a:r><a:r><a:t> and </a:t></a:r><a:r><a:rPr i="1"/><a:t>italic</a:t></a:r><a:r><a:t> run.</a:t></a:r></a:p>
          <a:p><a:pPr><a:buChar char="•"/></a:pPr><a:r><a:t>Bullet one</a:t></a:r></a:p>
          <a:p><a:pPr><a:buChar char="•"/></a:pPr><a:r><a:t>Bullet two</a:t></a:r></a:p>
          <a:p><a:pPr><a:buAutoNum type="arabicPeriod" startAt="3"/></a:pPr><a:r><a:t>Step three</a:t></a:r></a:p>
        </p:txBody>
      </p:sp>
      <p:pic>
        <p:nvPicPr><p:cNvPr id="3" name="Pixel image" descr="Pixel alt"/></p:nvPicPr>
        <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
      </p:pic>
      <p:graphicFrame>
        <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table">
          <a:tbl>
            <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Head A</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Head B</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
            <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Cell A</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Cell B</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
          </a:tbl>
        </a:graphicData></a:graphic>
      </p:graphicFrame>
    </p:spTree>
  </p:cSld>
</p:sld>
XML);
        $zip->addFromString('ppt/notesSlides/notesSlide1.xml', <<<'XML'
<?xml version="1.0"?>
<p:notes
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree><p:sp><p:txBody><a:p><a:r><a:t>Speaker note text.</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld>
</p:notes>
XML);
        $zip->addFromString('ppt/slides/slide2.xml', <<<'XML'
<?xml version="1.0"?>
<p:sld
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree><p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Second Slide</a:t></a:r></a:p></p:txBody></p:sp><p:sp><p:txBody><a:p><a:r><a:t>Second slide body.</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld>
</p:sld>
XML);
        $zip->addFromString('ppt/media/image1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->close();

        try {
            $document = (new PptxReader())->readPptxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'pptx', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('PPTX Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded PPTX reader smoke.', $meta['description']);
        $t->same('slides,import', $meta['keywords']);
        $t->same(2, $meta['pptxSlideCount']);
        $t->same('ppt/presentation.xml', $meta['pptxPresentationPath']);
        $t->same(12192000, $meta['pptxSlideSize']['widthEmu']);
        $t->same(['ppt/media/image1.png'], $meta['pptxMediaFiles']);
        $t->same('div', $document->children[0]->type);
        $t->same(['pptx-slide'], $document->children[0]->attr('classes'));
        $t->same('Slide One', $document->children[0]->children[0]->attr('text'));
        $t->same('Second Slide', $document->children[1]->children[0]->attr('text'));
        $t->contains('class="pptx-slide"', $blocks);
        $t->contains('<h2 id="slide-1">Slide One</h2>', $blocks);
        $t->contains('<a href="https://example.test/pptx"><strong>linked bold</strong></a>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<ul><li>Bullet one</li><li>Bullet two</li></ul>', $blocks);
        $t->contains('<ol start="3"><li>Step three</li></ol>', $blocks);
        $t->contains('<img src="ppt/media/image1.png" alt="Pixel alt" title="Pixel image"', $blocks);
        $t->contains('Head A', $blocks);
        $t->contains('Cell B', $blocks);
        $t->contains('class="pptx-notes"', $blocks);
        $t->contains('Speaker note text.', $blocks);
        $t->contains('Second slide body.', $converterBlocks);
    },
    'reads pptx bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-bytes-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst></p:presentation>');
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Byte PPTX</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
            $document = PandocConverter::read($bytes, 'pptx');
        } finally {
            @unlink($path);
        }

        $t->same('div', $document->children[0]->type);
        $t->same('Byte PPTX', $document->children[0]->children[0]->attr('text'));
    },
];
