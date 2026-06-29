<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$buildDocxReaderPackageBytes = static function (string $documentXml): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary DOCX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary DOCX package');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary DOCX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

/**
 * @param array<string, string> $parts
 * @param array<string, int> $compressionMethodsByName
 */
$buildDocxReaderNativeZipPackageBytes = static function (array $parts, array $compressionMethodsByName = []): string {
    $body = '';
    $central = '';
    $entryCount = 0;

    foreach ($parts as $name => $contents) {
        $method = $compressionMethodsByName[$name] ?? 8;
        $compressed = match ($method) {
            0 => $contents,
            8 => gzdeflate($contents),
            default => $contents,
        };
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate DOCX fixture entry {$name}");
        }

        $crc32 = (int) sprintf('%u', crc32($contents));
        $offset = strlen($body);
        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc32,
            strlen($compressed),
            strlen($contents),
            strlen($name),
            0,
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc32,
            strlen($compressed),
            strlen($contents),
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset,
        );
        $central .= $name;
        ++$entryCount;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), strlen($body), 0);
};

return [
    'resolves docx tracked revisions by configured revision mode' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Base </w:t></w:r><w:ins w:author="Insert Reviewer" w:date="2026-06-26T12:00:00Z"><w:r><w:t xml:space="preserve">inserted </w:t></w:r></w:ins><w:del w:author="Delete Reviewer" w:date="2026-06-26T12:01:00Z"><w:r><w:delText xml:space="preserve">deleted </w:delText></w:r></w:del><w:moveFrom w:id="9" w:author="Move Reviewer" w:date="2026-06-26T12:02:00Z"><w:r><w:delText xml:space="preserve">moved-from </w:delText></w:r></w:moveFrom><w:moveTo w:id="9" w:author="Move Reviewer" w:date="2026-06-26T12:03:00Z"><w:r><w:t xml:space="preserve">moved-to </w:t></w:r></w:moveTo><w:r><w:t>tail</w:t></w:r></w:p></w:body></w:document>');

        $preserveDocument = (new DocxReader(['revisionMode' => 'preserve']))->read($bytes);
        $preserve = $preserveDocument->children[0];
        $preserveBlocks = (new WordPressBlockWriter())->write($preserveDocument);
        $t->same('Base inserted deleted moved-from moved-to tail', $preserve->attr('text'));
        $t->same(['insertion'], $preserve->children[1]->attr('classes'));
        $t->same(['deletion'], $preserve->children[2]->attr('classes'));
        $t->same(['deletion', 'move-from'], $preserve->children[3]->attr('classes'));
        $t->same(['insertion', 'move-to'], $preserve->children[4]->attr('classes'));
        $t->contains('<ins class="insertion" data-pandoc-change-author="Insert Reviewer"', $preserveBlocks);
        $t->contains('<del class="deletion" data-pandoc-change-author="Delete Reviewer"', $preserveBlocks);
        $t->contains('<del class="deletion move-from" data-pandoc-change-author="Move Reviewer"', $preserveBlocks);
        $t->contains('<ins class="insertion move-to" data-pandoc-change-author="Move Reviewer"', $preserveBlocks);

        $acceptDocument = (new DocxReader(['revisionMode' => 'accept']))->read($bytes);
        $accept = $acceptDocument->children[0];
        $acceptBlocks = (new WordPressBlockWriter())->write($acceptDocument);
        $t->same('Base inserted moved-to tail', $accept->attr('text'));
        $t->same(1, count($accept->children));
        $t->same('text', $accept->children[0]->type);
        $t->true(!str_contains($acceptBlocks, '<ins'), 'accepted revisions should not preserve insertion spans');
        $t->true(!str_contains($acceptBlocks, '<del'), 'accepted revisions should not preserve deletion spans');
        $t->true(!str_contains($acceptBlocks, 'deleted'), 'accepted revisions should drop deleted text');
        $t->true(!str_contains($acceptBlocks, 'moved-from'), 'accepted revisions should drop moveFrom text');

        $rejectDocument = PandocConverter::read($bytes, 'docx', ['revisionMode' => 'reject']);
        $reject = $rejectDocument->children[0];
        $rejectBlocks = (new WordPressBlockWriter())->write($rejectDocument);
        $t->same('Base deleted moved-from tail', $reject->attr('text'));
        $t->same(1, count($reject->children));
        $t->same('text', $reject->children[0]->type);
        $t->true(!str_contains($rejectBlocks, '<ins'), 'rejected revisions should not preserve insertion spans');
        $t->true(!str_contains($rejectBlocks, '<del'), 'rejected revisions should not preserve deletion spans');
        $t->true(!str_contains($rejectBlocks, 'inserted'), 'rejected revisions should drop inserted text');
        $t->true(!str_contains($rejectBlocks, 'moved-to'), 'rejected revisions should drop moveTo text');

        $t->throws(InvalidArgumentException::class, static fn (): DocxReader => new DocxReader(['revisionMode' => 'merge']));
    },
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
    'selects section-specific docx header and footer references' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdHeader1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="rIdFooter1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/><Relationship Id="rIdHeader2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/><Relationship Id="rIdFooter2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer2.xml"/><Relationship Id="rIdHeaderUnused" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header3.xml"/><Relationship Id="rIdFooterUnused" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer3.xml"/></Relationships>'],
            ['name' => 'word/header1.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section one header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/header2.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section two even header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/header3.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Unreferenced header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/footer1.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section one first footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/footer2.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section two footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/footer3.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Unreferenced footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Section one body</w:t></w:r><w:pPr><w:sectPr><w:headerReference w:type="default" r:id="rIdHeader1"/><w:footerReference w:type="first" r:id="rIdFooter1"/></w:sectPr></w:pPr></w:p><w:p><w:r><w:t>Section two body</w:t></w:r></w:p><w:sectPr><w:headerReference w:type="even" r:id="rIdHeader2"/><w:footerReference w:type="default" r:id="rIdFooter2"/></w:sectPr></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');

        $t->same(2, $meta['docxHeaders']);
        $t->same(2, $meta['docxFooters']);
        $t->same(3, $meta['docxHeaderPartCount']);
        $t->same(3, $meta['docxFooterPartCount']);
        $t->same(4, $meta['docxSectionReferenceCount']);
        $t->same(['word/header1.xml', 'word/header2.xml'], $meta['docxAppliedHeaderFiles']);
        $t->same(['word/footer1.xml', 'word/footer2.xml'], $meta['docxAppliedFooterFiles']);
        $t->same('default', $meta['docxSectionReferences'][0]['headers'][0]['type']);
        $t->same('first', $meta['docxSectionReferences'][0]['footers'][0]['type']);
        $t->same('even', $meta['docxSectionReferences'][1]['headers'][0]['type']);

        $t->same('div', $document->children[0]->type);
        $t->same('word/header1.xml', $document->children[0]->attr('attributes')['data-docx-part']);
        $t->same('1', $document->children[0]->attr('attributes')['data-docx-section-index']);
        $t->same('word/header2.xml', $document->children[1]->attr('attributes')['data-docx-part']);
        $t->same('2', $document->children[1]->attr('attributes')['data-docx-section-index']);
        $t->same('Section one body', $document->children[2]->attr('text'));
        $t->same('Section two body', $document->children[3]->attr('text'));
        $t->same('word/footer1.xml', $document->children[4]->attr('attributes')['data-docx-part']);
        $t->same('word/footer2.xml', $document->children[5]->attr('attributes')['data-docx-part']);

        $t->contains('Section one header', $blocks);
        $t->contains('data-docx-section-index="2"', $blocks);
        $t->contains('data-docx-section-reference-type="even"', $blocks);
        $t->contains('Section two footer', $blocks);
        $t->true(!str_contains($blocks, 'Unreferenced header'), 'Unreferenced header parts should not be emitted when section references are available');
        $t->true(!str_contains($blocks, 'Unreferenced footer'), 'Unreferenced footer parts should not be emitted when section references are available');
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
    'reads native zip docx bytes while leaving unsupported media entries metadata-only' => static function (TestRunner $t) use ($buildDocxReaderNativeZipPackageBytes): void {
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Native ZIP DOCX </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="7" name="Unsupported media" title="Unsupported media title" descr="Unsupported media alt"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML;
        $relationshipsXml = <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/unsupported.bin"/>
</Relationships>
XML;
        $contentTypesXml = <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
        $bytes = $buildDocxReaderNativeZipPackageBytes(
            [
                '[Content_Types].xml' => $contentTypesXml,
                'word/document.xml' => $documentXml,
                'word/_rels/document.xml.rels' => $relationshipsXml,
                'word/media/unsupported.bin' => 'metadata-only unsupported compression payload',
            ],
            [
                '[Content_Types].xml' => 0,
                'word/media/unsupported.bin' => 12,
            ]
        );

        $packageDocument = (new DocxReader())->readDocument(ZipPackage::fromString($bytes));
        $converterDocument = PandocConverter::read($bytes, 'docx');
        $blocks = (new WordPressBlockWriter())->write($converterDocument);
        $meta = $converterDocument->attr('meta');
        $image = $converterDocument->children[0]->children[1];

        $t->same('Native ZIP DOCX', $packageDocument->children[0]->attr('text'));
        $t->same('Native ZIP DOCX', $converterDocument->children[0]->attr('text'));
        $t->same(4, $meta['docxPackageEntries']);
        $t->same(['word/media/unsupported.bin'], $meta['docxMediaFiles']);
        $t->same(1, $meta['docxRelationshipCount']);
        $t->same('image', $image->type);
        $t->same('word/media/unsupported.bin', $image->attr('url'));
        $t->same('Unsupported media alt', $image->attr('alt'));
        $t->contains('word/media/unsupported.bin', $blocks);
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
    'resolves docx numbering definitions through document relationships' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rNumberingCustom" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering/custom-numbering.xml?profile=review#defs"/>
</Relationships>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>
  <w:num w:numId="23"><w:abstractNumId w:val="1"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/numbering/custom-numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="9">
    <w:lvl w:ilvl="0"><w:start w:val="5"/><w:numFmt w:val="lowerRoman"/><w:lvlText w:val="%1)"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="23"><w:abstractNumId w:val="9"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="23"/></w:numPr></w:pPr><w:r><w:t>Relationship-selected five</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="23"/></w:numPr></w:pPr><w:r><w:t>Relationship-selected six</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($document);
        $meta = $document->attr('meta');
        $list = $document->children[0];

        $t->same('word/numbering/custom-numbering.xml', $meta['docxNumberingPart']);
        $t->same('rNumberingCustom', $meta['docxNumberingRelationshipId']);
        $t->same('numbering/custom-numbering.xml?profile=review#defs', $meta['docxNumberingRelationshipTarget']);
        $t->same(1, $meta['docxNumberingDefinitions']);
        $t->same('ordered_list', $list->type);
        $t->same(5, $list->attr('start'));
        $t->same('lower_roman', $list->attr('style'));
        $t->same('one_paren', $list->attr('delimiter'));
        $t->same('Relationship-selected five', $list->children[0]->children[0]->attr('text'));
        $t->same('Relationship-selected six', $list->children[1]->children[0]->attr('text'));
        $t->contains('<ol start="5" type="i" data-pandoc-list-style="lower_roman" data-pandoc-list-delimiter="one_paren">', $blocks);
    },
    'derives docx numbering from paragraph styles and respects numId zero suppression' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Checklist"><w:name w:val="Checklist"/></w:style>
  <w:style w:type="paragraph" w:styleId="DerivedChecklist"><w:basedOn w:val="Checklist"/><w:name w:val="Derived Checklist"/></w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="4">
    <w:lvl w:ilvl="0"><w:pStyle w:val="Checklist"/><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="12"><w:abstractNumId w:val="4"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="DerivedChecklist"/></w:pPr><w:r><w:t>Inherited checklist item</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Checklist"/><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Suppressed checklist item</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $list = $document->children[0];
        $suppressed = $document->children[1];

        $t->same('bullet_list', $list->type);
        $t->same('12', $list->attr('numId'));
        $t->same(0, $list->attr('level'));
        $t->same('Inherited checklist item', $list->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $suppressed->type);
        $t->same('Suppressed checklist item', $suppressed->attr('text'));
        $t->contains('<ul><li>Inherited checklist item</li></ul>', $blocks);
        $t->contains('<p>Suppressed checklist item</p>', $blocks);
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
    'preserves docx content controls with block inline and table metadata' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:sdt><w:sdtPr><w:alias w:val="Customer Name"/><w:tag w:val="customer.name"/><w:id w:val="42"/><w:lock w:val="sdtContentLocked"/><w:placeholder><w:docPart w:val="CustomerPlaceholder"/></w:placeholder><w:dataBinding w:xpath="/root/customer/name" w:storeItemID="{11111111-1111-1111-1111-111111111111}" w:prefixMappings="xmlns:c=&apos;urn:customer&apos;"/><w:text w:multiLine="1"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Ada Lovelace</w:t></w:r></w:p></w:sdtContent></w:sdt><w:p><w:r><w:t>Status: </w:t></w:r><w:sdt><w:sdtPr><w:alias w:val="Status choice"/><w:tag w:val="status"/><w:id w:val="43"/><w:dropDownList><w:listItem w:displayText="Draft" w:value="draft"/><w:listItem w:displayText="Approved" w:value="approved"/></w:dropDownList></w:sdtPr><w:sdtContent><w:r><w:t>Approved</w:t></w:r></w:sdtContent></w:sdt></w:p><w:tbl><w:tr><w:tc><w:sdt><w:sdtPr><w:alias w:val="Signed date"/><w:tag w:val="signed.date"/><w:id w:val="44"/><w:date w:fullDate="2026-06-26T00:00:00Z"><w:dateFormat w:val="MMMM d, yyyy"/><w:lid w:val="en-US"/></w:date></w:sdtPr><w:sdtContent><w:p><w:r><w:t>June 26, 2026</w:t></w:r></w:p></w:sdtContent></w:sdt></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $blockControl = $document->children[0];
        $blockAttrs = $blockControl->attr('attributes');
        $paragraph = $document->children[1];
        $inlineControl = $paragraph->children[1];
        $inlineAttrs = $inlineControl->attr('attributes');
        $table = $document->children[2];
        $tableCell = $table->children[1]->children[0]->children[0];
        $dateControl = $tableCell->children[0];
        $dateAttrs = $dateControl->attr('attributes');

        $t->same('div', $blockControl->type);
        $t->same(['docx-content-control', 'docx-content-control-block'], $blockControl->attr('classes'));
        $t->same('block', $blockAttrs['data-docx-content-control-display']);
        $t->same('text', $blockAttrs['data-docx-content-control-type']);
        $t->same('Customer Name', $blockAttrs['data-docx-content-control-alias']);
        $t->same('customer.name', $blockAttrs['data-docx-content-control-tag']);
        $t->same('42', $blockAttrs['data-docx-content-control-id']);
        $t->same('sdtContentLocked', $blockAttrs['data-docx-content-control-lock']);
        $t->same('CustomerPlaceholder', $blockAttrs['data-docx-content-control-placeholder-doc-part']);
        $t->same('/root/customer/name', $blockAttrs['data-docx-content-control-binding-xpath']);
        $t->same('{11111111-1111-1111-1111-111111111111}', $blockAttrs['data-docx-content-control-binding-store-item-id']);
        $t->same("xmlns:c='urn:customer'", $blockAttrs['data-docx-content-control-binding-prefix-mappings']);
        $t->same('true', $blockAttrs['data-docx-content-control-text-multiline']);
        $t->same('Ada Lovelace', $blockControl->children[0]->attr('text'));

        $t->same('Status: Approved', $paragraph->attr('text'));
        $t->same('span', $inlineControl->type);
        $t->same(['docx-content-control', 'docx-content-control-inline'], $inlineControl->attr('classes'));
        $t->same('inline', $inlineAttrs['data-docx-content-control-display']);
        $t->same('dropDownList', $inlineAttrs['data-docx-content-control-type']);
        $t->same('Status choice', $inlineAttrs['data-docx-content-control-alias']);
        $t->same('2', $inlineAttrs['data-docx-content-control-list-item-count']);
        $t->same('Draft Approved', $inlineAttrs['data-docx-content-control-list-display-texts']);
        $t->same('draft approved', $inlineAttrs['data-docx-content-control-list-values']);

        $t->same('June 26, 2026', $tableCell->attr('text'));
        $t->same('div', $dateControl->type);
        $t->same('date', $dateAttrs['data-docx-content-control-type']);
        $t->same('Signed date', $dateAttrs['data-docx-content-control-alias']);
        $t->same('2026-06-26T00:00:00Z', $dateAttrs['data-docx-content-control-date-full']);
        $t->same('MMMM d, yyyy', $dateAttrs['data-docx-content-control-date-format']);
        $t->same('en-US', $dateAttrs['data-docx-content-control-date-language-id']);

        $t->contains('<div class="docx-content-control docx-content-control-block" data-docx-content-control-display="block"', $blocks);
        $t->contains('data-docx-content-control-binding-xpath="/root/customer/name"', $blocks);
        $t->contains('<span class="docx-content-control docx-content-control-inline" data-docx-content-control-display="inline"', $blocks);
        $t->contains('data-docx-content-control-list-values="draft approved"', $blocks);
        $t->contains('data-docx-content-control-date-full="2026-06-26T00:00:00Z"', $blocks);
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
    'reads docx text boxes vml object images and inherited table style metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdObject" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/object-preview.png"/></Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="character" w:styleId="BaseChar"><w:name w:val="Base Character"/><w:rPr><w:b/></w:rPr></w:style><w:style w:type="character" w:styleId="DerivedChar"><w:name w:val="Derived Character"/><w:basedOn w:val="BaseChar"/><w:rPr><w:i/></w:rPr></w:style><w:style w:type="table" w:styleId="BaseTable"><w:name w:val="Base Table"/><w:tblPr><w:shd w:fill="D9EAF7"/><w:jc w:val="center"/></w:tblPr></w:style><w:style w:type="table" w:styleId="DerivedTable"><w:name w:val="Derived Table"/><w:basedOn w:val="BaseTable"/></w:style></w:styles>');
        $zip->addFromString('word/media/object-preview.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office"><w:body><w:p><w:r><w:t>Before </w:t></w:r><w:r><w:pict><v:shape id="TextBox1" type="#_x0000_t202" style="width:120pt;height:40pt"><v:textbox><w:txbxContent><w:p><w:r><w:t>Boxed </w:t></w:r><w:r><w:rPr><w:rStyle w:val="DerivedChar"/></w:rPr><w:t>strong italic</w:t></w:r></w:p></w:txbxContent></v:textbox></v:shape></w:pict></w:r><w:r><w:t> after.</w:t></w:r></w:p><w:p><w:r><w:object><v:shape id="_x0000_i1025" type="#_x0000_t75" style="width:48pt;height:24pt"><v:imagedata r:id="rIdObject" o:title="Object preview"/></v:shape><w:dxaOrig w:val="960"/><w:dyaOrig w:val="480"/></w:object></w:r></w:p><w:tbl><w:tblPr><w:tblStyle w:val="DerivedTable"/></w:tblPr><w:tr><w:tc><w:p><w:r><w:t>Styled cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[0];
        $textBox = $paragraph->children[1];
        $styledText = $textBox->children[1];
        $image = $document->children[1]->children[0];
        $table = $document->children[2];
        $tableAttributes = $table->attr('htmlAttributes');

        $t->same('Before Boxed strong italic after.', $paragraph->attr('text'));
        $t->same('span', $textBox->type);
        $t->same(['docx-textbox'], $textBox->attr('classes'));
        $t->same('vml-pict', $textBox->attr('attributes')['data-docx-textbox-source']);
        $t->same('TextBox1', $textBox->attr('attributes')['data-docx-vml-shape-id']);
        $t->same('emph', $styledText->type);
        $t->same('strong', $styledText->children[0]->type);
        $t->same('strong italic', $styledText->children[0]->children[0]->attr('text'));

        $t->same('image', $image->type);
        $t->same('word/media/object-preview.png', $image->attr('url'));
        $t->same('Object preview', $image->attr('alt'));
        $t->same('Object preview', $image->attr('title'));
        $t->same('48pt', $image->attr('width'));
        $t->same('24pt', $image->attr('height'));
        $t->same('vml-object', $image->attr('attributes')['data-docx-image-source']);
        $t->same('rIdObject', $image->attr('attributes')['data-docx-image-relationship-id']);
        $t->same('_x0000_i1025', $image->attr('attributes')['data-docx-vml-shape-id']);
        $t->same('960', $image->attr('attributes')['data-docx-object-dxa-orig']);
        $t->same('480', $image->attr('attributes')['data-docx-object-dya-orig']);

        $t->same('DerivedTable', $tableAttributes['data-docx-table-style']);
        $t->same('Derived Table', $tableAttributes['data-docx-table-style-name']);
        $t->same('BaseTable', $tableAttributes['data-docx-table-style-based-on']);
        $t->same('BaseTable DerivedTable', $tableAttributes['data-docx-table-style-chain']);
        $t->same('D9EAF7', $tableAttributes['data-docx-table-style-fill']);
        $t->same('center', $tableAttributes['data-docx-table-style-align']);

        $t->contains('<span class="docx-textbox" data-docx-textbox-source="vml-pict"', $blocks);
        $t->contains('Boxed <em><strong>strong italic</strong></em>', $blocks);
        $t->contains('data-docx-image-source="vml-object"', $blocks);
        $t->contains('data-docx-vml-shape-id="_x0000_i1025"', $blocks);
        $t->contains('data-docx-table-style-chain="BaseTable DerivedTable"', $blocks);
        $t->contains('data-docx-table-style-fill="D9EAF7"', $blocks);
    },
];
