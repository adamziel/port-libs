<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads docx package body metadata notes headers footers and review spans into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/><Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/><Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test" TargetMode="External"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:outlineLvl w:val="0"/></w:pPr></w:style><w:style w:type="character" w:styleId="StrongStyle"><w:rPr><w:b/></w:rPr></w:style></w:styles>');
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>DOCX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded DOCX reader smoke.</dc:description><dcterms:created>2026-06-18T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('word/footnotes.xml', '<?xml version="1.0"?><w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:footnote w:id="1"><w:p><w:r><w:t>Footnote body.</w:t></w:r></w:p></w:footnote></w:footnotes>');
        $zip->addFromString('word/endnotes.xml', '<?xml version="1.0"?><w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:endnote w:id="2"><w:p><w:r><w:t>Endnote body.</w:t></w:r></w:p></w:endnote></w:endnotes>');
        $zip->addFromString('word/comments.xml', '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="3" w:author="Reviewer" w:date="2026-06-18T00:00:00Z"><w:p><w:r><w:t>Comment body.</w:t></w:r></w:p></w:comment></w:comments>');
        $zip->addFromString('word/header1.xml', '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header text.</w:t></w:r></w:p></w:hdr>');
        $zip->addFromString('word/footer1.xml', '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Footer text.</w:t></w:r></w:p></w:ftr>');
        $zip->addFromString('word/media/image1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>DOCX Reader Demo</w:t></w:r></w:p><w:p><w:r><w:t>A </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r><w:r><w:t> and </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r><w:r><w:t> run with </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>a link</w:t></w:r></w:hyperlink><w:r><w:t>, a footnote</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r><w:r><w:t>, an endnote</w:t></w:r><w:r><w:endnoteReference w:id="2"/></w:r><w:r><w:t>, and a comment</w:t></w:r><w:r><w:commentReference w:id="3"/></w:r><w:r><w:t>.</w:t></w:r><w:ins w:author="Editor" w:date="2026-06-18T00:00:00Z"><w:r><w:t> inserted text</w:t></w:r></w:ins><w:del w:author="Editor" w:date="2026-06-18T00:00:00Z"><w:r><w:delText> removed text</w:delText></w:r></w:del></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>First list item</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Second list item</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rId2"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'docx', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('DOCX Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded DOCX reader smoke.', $meta['description']);
        $t->same(1, $meta['docxFootnotes']);
        $t->same(1, $meta['docxEndnotes']);
        $t->same(1, $meta['docxComments']);
        $t->same(1, $meta['docxHeaders']);
        $t->same(1, $meta['docxFooters']);
        $t->contains('class="docx-header"', $blocks);
        $t->contains('Header text.', $blocks);
        $t->contains('Footer text.', $blocks);
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<a href="https://example.test">a link</a>', $blocks);
        $t->contains('Footnote body.', $blocks);
        $t->contains('Endnote body.', $blocks);
        $t->contains('Comment body.', $blocks);
        $t->contains('<ins', $blocks);
        $t->contains('<del', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('word/media/image1.png', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'reads docx bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Byte DOCX</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary DOCX package');
            }
            $document = PandocConverter::read($bytes, 'docx');
        } finally {
            @unlink($path);
        }

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Byte DOCX', $document->children[0]->attr('text'));
    },
    'preserves docx numbering levels styles starts and delimiters' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="42"><w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl><w:lvl w:ilvl="1"><w:numFmt w:val="upperRoman"/><w:lvlText w:val="(%2)"/></w:lvl></w:abstractNum><w:num w:numId="7"><w:abstractNumId w:val="42"/></w:num></w:numbering>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Alpha three</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Nested roman</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Alpha four</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'docx', 'blocks');
        } finally {
            @unlink($path);
        }

        $list = $document->children[0];
        $firstItem = $list->children[0];
        $nested = $firstItem->children[1];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('lower_alpha', $list->attr('style'));
        $t->same('one_paren', $list->attr('delimiter'));
        $t->same('list_item', $firstItem->type);
        $t->same('Alpha three', $firstItem->children[0]->attr('text'));
        $t->same('ordered_list', $nested->type);
        $t->same('upper_roman', $nested->attr('style'));
        $t->same('two_parens', $nested->attr('delimiter'));
        $t->same('Nested roman', $nested->children[0]->children[0]->attr('text'));
        $t->same('Alpha four', $list->children[1]->children[0]->attr('text'));
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3" type="a" data-pandoc-list-style="lower_alpha" data-pandoc-list-delimiter="one_paren">', $blocks);
        $t->contains('<ol type="I" data-pandoc-list-style="upper_roman" data-pandoc-list-delimiter="two_parens">', $blocks);
        $t->contains('<ol start="3" type="a"><li>Alpha three<ol type="I"><li>Nested roman</li></ol></li><li>Alpha four</li></ol>', $converterBlocks);
    },
    'reads docx bookmarks reference fields and omml equations into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><w:body><w:p><w:bookmarkStart w:id="7" w:name="_RefEquation"/><w:r><w:t>Equation target</w:t></w:r><w:bookmarkEnd w:id="7"/></w:p><w:p><w:r><w:t>See </w:t></w:r><w:fldSimple w:instr=" REF _RefEquation \h "><w:r><w:t>Equation target</w:t></w:r></w:fldSimple><w:r><w:t>: </w:t></w:r><m:oMath><m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup><m:r><m:t>+y</m:t></m:r></m:oMath></w:p><m:oMathPara><m:oMath><m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>n</m:t></m:r></m:den></m:f></m:oMath></m:oMathPara></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $target = $document->children[0];
        $reference = $document->children[1];
        $display = $document->children[2];

        $t->same('raw_inline', $target->children[0]->type);
        $t->same('openxml', $target->children[0]->attr('format'));
        $t->contains('w:name="_RefEquation"', $target->children[0]->attr('text'));
        $t->same('raw_inline', $target->children[2]->type);
        $t->same('link', $reference->children[1]->type);
        $t->same('#_RefEquation', $reference->children[1]->attr('url'));
        $t->same('math', $reference->children[3]->type);
        $t->same('x^{2}+y', $reference->children[3]->attr('text'));
        $t->same('plain', $display->type);
        $t->same('math', $display->children[0]->type);
        $t->same(true, $display->children[0]->attr('display'));
        $t->same('\\frac{1}{n}', $display->children[0]->attr('text'));
        $t->contains('class="pandoc-openxml-bookmark-start"', $blocks);
        $t->contains('data-pandoc-bookmark-name="_RefEquation"', $blocks);
        $t->contains('<a href="#_RefEquation"', $blocks);
        $t->contains('>Equation target</a>', $blocks);
        $t->contains('<span class="math inline">\\(x^{2}+y\\)</span>', $blocks);
        $t->contains('<span class="math display">\\[\\frac{1}{n}\\]</span>', $blocks);
    },
    'preserves docx comment ranges moves table merges styles and image metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/chart.png"/></Relationships>');
        $zip->addFromString('word/comments.xml', '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="5" w:author="Range Reviewer" w:date="2026-06-26T00:00:00Z"><w:p><w:r><w:t>Range body.</w:t></w:r></w:p></w:comment></w:comments>');
        $zip->addFromString('word/media/chart.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body><w:p><w:commentRangeStart w:id="5"/><w:r><w:t>Ranged comment</w:t></w:r><w:commentRangeEnd w:id="5"/><w:r><w:commentReference w:id="5"/></w:r></w:p><w:p><w:moveFrom w:id="8" w:author="Mover" w:date="2026-06-26T00:00:00Z"><w:r><w:delText>old spot</w:delText></w:r></w:moveFrom><w:r><w:t> to </w:t></w:r><w:moveTo w:id="8" w:author="Mover" w:date="2026-06-26T00:01:00Z"><w:r><w:t>new spot</w:t></w:r></w:moveTo><w:r><w:rPr><w:u w:val="single"/><w:strike/><w:vertAlign w:val="superscript"/></w:rPr><w:t> styled</w:t></w:r></w:p><w:tbl><w:tblPr><w:tblStyle w:val="ReviewTable"/></w:tblPr><w:tr><w:tc><w:tcPr><w:vMerge w:val="restart"/><w:shd w:fill="FFFF00"/><w:vAlign w:val="center"/></w:tcPr><w:p><w:r><w:t>Group</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Top</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p><w:r><w:t>Skipped continuation</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Bottom</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:drawing><wp:inline><wp:extent cx="1828800" cy="914400"/><wp:docPr id="9" name="Chart 1" descr="Chart alt" title="Chart title"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $comment = $document->children[0];
        $move = $document->children[1];
        $table = $document->children[2];
        $image = $document->children[3]->children[0];

        $t->same('span', $comment->children[0]->type);
        $t->same(['comment-start'], $comment->children[0]->attr('classes'));
        $t->same('5', $comment->children[0]->attr('attributes')['id']);
        $t->same('Range Reviewer', $comment->children[0]->attr('attributes')['author']);
        $t->same(['comment-end'], $comment->children[2]->attr('classes'));
        $t->same(['deletion', 'move-from'], $move->children[0]->attr('classes'));
        $t->same(['insertion', 'move-to'], $move->children[2]->attr('classes'));
        $t->same('superscript', $move->children[3]->type);
        $t->same('strikeout', $move->children[3]->children[0]->type);
        $t->same('underline', $move->children[3]->children[0]->children[0]->type);
        $t->same('ReviewTable', $table->attr('htmlAttributes')['data-docx-table-style']);
        $firstCell = $table->children[1]->children[0]->children[0];
        $secondRow = $table->children[1]->children[1];
        $t->same(2, $firstCell->attr('rowspan'));
        $t->same('restart', $firstCell->attr('htmlAttributes')['data-docx-vmerge']);
        $t->same('background-color:#FFFF00; vertical-align:middle', $firstCell->attr('htmlAttributes')['style']);
        $t->same(1, count($secondRow->children));
        $t->same('Bottom', $secondRow->children[0]->attr('text'));
        $t->same('Chart alt', $image->attr('alt'));
        $t->same('Chart title', $image->attr('title'));
        $t->same('2in', $image->attr('width'));
        $t->same('1in', $image->attr('height'));
        $t->same('Chart 1', $image->attr('attributes')['data-docx-image-name']);
        $t->same('9', $image->attr('attributes')['data-docx-image-id']);
        $t->contains('class="comment-start" data-pandoc-comment-id="5" data-pandoc-comment-author="Range Reviewer"', $blocks);
        $t->contains('<del class="deletion move-from" data-pandoc-change-author="Mover"', $blocks);
        $t->contains('<ins class="insertion move-to" data-pandoc-change-author="Mover"', $blocks);
        $t->contains('<sup><del><u> styled</u></del></sup>', $blocks);
        $t->contains('<table data-docx-table-style="ReviewTable">', $blocks);
        $t->contains('data-docx-vmerge="restart" rowspan="2" style="background-color:#FFFF00; vertical-align:middle"', $blocks);
        $t->contains('<td><p>Bottom</p></td>', $blocks);
        $t->contains('alt="Chart alt" title="Chart title" data-pandoc-width="2in" data-pandoc-height="1in"', $blocks);
        $t->contains('data-docx-image-name="Chart 1"', $blocks);
    },
];
