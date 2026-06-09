<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps docx core properties styles hyperlinks numbering and media from package parts' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_openxml_reader_fixture_parts());

        $meta = $document->attr('meta');
        $docx = $document->attr('docx');
        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $ordered = $document->children[2];
        $bullet = $document->children[3];
        $imageParagraph = $document->children[4];
        $table = $document->children[5];

        $t->same('Imported DOCX Batch', $meta['title']);
        $t->same(['Migration Editor'], $meta['authors']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $docx['contentTypes']['overrides']['word/document.xml']);
        $t->same('image/png', $docx['media']['word/media/review.png']['contentType']);
        $t->same(strlen('fake png bytes'), $docx['media']['word/media/review.png']['size']);

        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('imported-docx-heading', $heading->attr('id'));
        $t->same('Heading 1', $heading->attr('docxStyleName'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[3]->type);
        $t->same('underline', $paragraph->children[5]->type);
        $t->same('strikeout', $paragraph->children[7]->type);
        $t->same('superscript', $paragraph->children[9]->type);
        $t->same('link', $paragraph->children[11]->type);
        $t->same('https://example.test/source?post=42', $paragraph->children[11]->attr('url'));
        $t->same('rLink', $paragraph->children[11]->attr('relationshipId'));

        $t->same('ordered_list', $ordered->type);
        $t->same(3, $ordered->attr('start'));
        $t->same('upper_roman', $ordered->attr('style'));
        $t->same('one_paren', $ordered->attr('delimiter'));
        $t->same('First review step', $ordered->children[0]->children[0]->attr('text'));
        $t->same('Second review step', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->same('•', $bullet->attr('bulletChar'));
        $t->same('Bullet media note', $bullet->children[0]->children[0]->attr('text'));

        $image = $imageParagraph->children[1];
        $t->same('image', $image->type);
        $t->same('word/media/review.png', $image->attr('url'));
        $t->same('Review screenshot', $image->attr('alt'));
        $t->same('Review image', $image->attr('title'));
        $t->same('image/png', $image->attr('contentType'));
        $t->same('table', $table->type);
        $t->same('Reviewer', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same(2, $table->children[0]->children[0]->children[1]->attr('colspan'));
    },
    'reads a native zip docx package without shelling out' => static function (TestRunner $t): void {
        $path = docx_openxml_reader_temp_docx(docx_openxml_reader_fixture_parts());
        try {
            $document = (new DocxOpenXmlReader())->readFile($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $t->same('document', $document->type);
        $t->same('Imported DOCX Heading', $document->children[0]->attr('text'));
        $t->same('ordered_list', $document->children[2]->type);
        $t->same('word/media/review.png', $document->children[4]->children[1]->attr('mediaPath'));
    },
    'renders docx reader ast through markdown and wordpress writers' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_openxml_reader_fixture_parts());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# Imported DOCX Heading', $markdown);
        $t->contains('[source link](https://example.test/source?post=42)', $markdown);
        $t->contains('III) First review step', $markdown);
        $t->contains('![Review screenshot](word/media/review.png "Review image")', $markdown);
        $t->contains('<h1 id="imported-docx-heading">Imported DOCX Heading</h1>', $blocks);
        $t->contains('<a href="https://example.test/source?post=42">source link</a>', $blocks);
        $t->contains('<ol start="3" type="I">', $blocks);
        $t->contains('<img src="word/media/review.png" alt="Review screenshot" title="Review image"/>', $blocks);
        $t->contains('<td colspan="2"><p>Approved</p></td>', $blocks);
    },
    'rejects malformed or incomplete docx packages with bounded diagnostics' => static function (TestRunner $t): void {
        $reader = new DocxOpenXmlReader();

        $t->throws(RuntimeException::class, static fn (): AstNode => $reader->readPackage([]));
        $t->throws(RuntimeException::class, static fn (): AstNode => $reader->readPackage([
            'word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>',
        ]));
    },
];

/**
 * @return array<string, string>
 */
function docx_openxml_reader_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:title>Imported DOCX Batch</dc:title>
  <dc:creator>Migration Editor</dc:creator>
  <dc:description>WordPress DOCX import fixture</dc:description>
  <cp:keywords>docx,wordpress,review</cp:keywords>
  <dcterms:created>2026-06-09T12:00:00Z</dcterms:created>
  <dcterms:modified>2026-06-09T12:30:00Z</dcterms:modified>
</cp:coreProperties>
XML,
        'word/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="Heading 1"/>
    <w:pPr><w:outlineLvl w:val="0"/></w:pPr>
  </w:style>
</w:styles>
XML,
        'word/numbering.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="upperRoman"/><w:lvlText w:val="%1)"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="20">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7">
    <w:abstractNumId w:val="10"/>
    <w:lvlOverride w:ilvl="0"><w:startOverride w:val="3"/></w:lvlOverride>
  </w:num>
  <w:num w:numId="8"><w:abstractNumId w:val="20"/></w:num>
</w:numbering>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source?post=42" TargetMode="External"/>
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Imported DOCX Heading</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Plain </w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>underlined</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:strike/></w:rPr><w:t>removed</w:t></w:r>
      <w:r><w:t xml:space="preserve"> draft</w:t></w:r>
      <w:r><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:t>2</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>
    </w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>First review step</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Second review step</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="8"/></w:numPr></w:pPr><w:r><w:t>Bullet media note</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Inline media </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="1" name="Review image" title="Review image" descr="Review screenshot"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:tbl>
      <w:tr><w:tc><w:p><w:r><w:t>Reviewer</w:t></w:r></w:p></w:tc><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>Approved</w:t></w:r></w:p></w:tc></w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'fake png bytes',
    ];
}

/**
 * @param array<string, string> $parts
 */
function docx_openxml_reader_temp_docx(array $parts): string
{
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to allocate temporary DOCX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary DOCX package');
    }

    foreach ($parts as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    return $path;
}
