<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$generatedFieldContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$generatedFieldPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$generatedFieldDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Generated contents </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> TOC \o "1-3" \h \z \u \t "Appendix,1,Review Heading,2" \n "2-3" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Contents preview</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Generated index </w:t></w:r>
      <w:fldSimple w:instr=' INDEX \b ReviewPacket \c "2" \e ", " \* MERGEFORMAT '>
        <w:r><w:t>A, source packet 4</w:t></w:r>
      </w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Generated citation </w:t></w:r>
      <w:fldSimple w:instr=' CITATION Smith2024 \l 1033 \v "42" '>
        <w:r><w:t>Smith 2024, 42</w:t></w:r>
      </w:fldSimple>
      <w:r><w:t xml:space="preserve"> and bibliography </w:t></w:r>
      <w:fldSimple w:instr=' BIBLIOGRAPHY \l 1033 \f 0 '>
        <w:r><w:t>Smith, Source Packet.</w:t></w:r>
      </w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$buildGeneratedFieldPackage = static function () use (
    $generatedFieldContentTypesXml,
    $generatedFieldPackageRelationshipsXml,
    $generatedFieldDocumentXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $generatedFieldContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $generatedFieldPackageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $generatedFieldDocumentXml],
    ]);
};

return [
    'preserves DOCX generated table of contents and index field provenance' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $tocParagraph = $document->children[0];
        $toc = $tocParagraph->children[1];
        $t->same('span', $toc->type);
        $t->same([
            'docx-field',
            'docx-field-toc',
            'docx-generated-field',
            'docx-generated-field-toc',
            'docx-field-omit-page-numbers',
            'docx-field-hyperlink',
            'docx-field-outline-levels',
            'docx-field-hide-web-layout',
        ], $toc->attr('classes'));
        $tocAttrs = $toc->attr('attributes');
        $t->same('toc', $tocAttrs['data-docx-field']);
        $t->same('TOC \o "1-3" \h \z \u \t "Appendix,1,Review Heading,2" \n "2-3"', $tocAttrs['data-docx-field-instruction']);
        $t->same('table-of-contents', $tocAttrs['data-docx-generated-field-type']);
        $t->same('1-3', $tocAttrs['data-docx-field-outline-levels']);
        $t->same('Appendix,1,Review Heading,2', $tocAttrs['data-docx-field-style-levels']);
        $t->same('true', $tocAttrs['data-docx-field-hyperlink']);
        $t->same('true', $tocAttrs['data-docx-field-use-outline-levels']);
        $t->same('true', $tocAttrs['data-docx-field-hide-web-layout']);
        $t->same('true', $tocAttrs['data-docx-field-omit-page-numbers']);
        $t->same('2-3', $tocAttrs['data-docx-field-omit-page-number-levels']);
        $t->same('Contents preview', $toc->children[0]->attr('text'));

        $indexParagraph = $document->children[1];
        $index = $indexParagraph->children[1];
        $t->same('span', $index->type);
        $t->same([
            'docx-field',
            'docx-field-index',
            'docx-generated-field',
            'docx-generated-field-index',
        ], $index->attr('classes'));
        $indexAttrs = $index->attr('attributes');
        $t->same('index', $indexAttrs['data-docx-field']);
        $t->same('document-index', $indexAttrs['data-docx-generated-field-type']);
        $t->same('ReviewPacket', $indexAttrs['data-docx-field-bookmark']);
        $t->same('2', $indexAttrs['data-docx-field-columns']);
        $t->same(', ', $indexAttrs['data-docx-field-entry-separator']);
        $t->same('MERGEFORMAT', $indexAttrs['data-docx-field-format']);
        $t->same('A, source packet 4', $index->children[0]->attr('text'));

        $t->contains('[Contents preview]{.docx-field .docx-field-toc .docx-generated-field .docx-generated-field-toc', $markdown);
        $t->contains('data-docx-field-outline-levels="1-3"', $markdown);
        $t->contains('data-docx-field-style-levels="Appendix,1,Review Heading,2"', $markdown);
        $t->contains('[A, source packet 4]{.docx-field .docx-field-index .docx-generated-field .docx-generated-field-index', $markdown);
        $t->contains('data-docx-field-bookmark="ReviewPacket"', $markdown);

        $t->contains('<span class="docx-field docx-field-toc docx-generated-field docx-generated-field-toc docx-field-omit-page-numbers docx-field-hyperlink docx-field-outline-levels docx-field-hide-web-layout"', $blocks);
        $t->contains('data-docx-field-style-levels="Appendix,1,Review Heading,2"', $blocks);
        $t->contains('<span class="docx-field docx-field-index docx-generated-field docx-generated-field-index"', $blocks);
        $t->contains('data-docx-field-entry-separator=", "', $blocks);
    },
    'preserves DOCX bibliography and citation field provenance' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[2];
        $citation = $paragraph->children[1];
        $t->same('span', $citation->type);
        $t->same([
            'docx-field',
            'docx-field-citation',
            'docx-generated-field',
            'docx-generated-field-citation',
        ], $citation->attr('classes'));
        $citationAttrs = $citation->attr('attributes');
        $t->same('citation', $citationAttrs['data-docx-field']);
        $t->same('citation', $citationAttrs['data-docx-generated-field-type']);
        $t->same('Smith2024', $citationAttrs['data-docx-field-target']);
        $t->same('1033', $citationAttrs['data-docx-field-locale-id']);
        $t->same('42', $citationAttrs['data-docx-field-citation-volume']);
        $t->same('Smith 2024, 42', $citation->children[0]->attr('text'));

        $bibliography = $paragraph->children[3];
        $t->same('span', $bibliography->type);
        $t->same([
            'docx-field',
            'docx-field-bibliography',
            'docx-generated-field',
            'docx-generated-field-bibliography',
        ], $bibliography->attr('classes'));
        $bibliographyAttrs = $bibliography->attr('attributes');
        $t->same('bibliography', $bibliographyAttrs['data-docx-field']);
        $t->same('bibliography', $bibliographyAttrs['data-docx-generated-field-type']);
        $t->same('1033', $bibliographyAttrs['data-docx-field-locale-id']);
        $t->same('0', $bibliographyAttrs['data-docx-field-entry-type']);
        $t->true(!isset($bibliographyAttrs['data-docx-field-target']), 'BIBLIOGRAPHY should not invent a target from switch values');
        $t->same('Smith, Source Packet.', $bibliography->children[0]->attr('text'));

        $t->contains('[Smith 2024, 42]{.docx-field .docx-field-citation .docx-generated-field .docx-generated-field-citation', $markdown);
        $t->contains('data-docx-field-target="Smith2024"', $markdown);
        $t->contains('[Smith, Source Packet.]{.docx-field .docx-field-bibliography .docx-generated-field .docx-generated-field-bibliography', $markdown);
        $t->contains('data-docx-field-locale-id="1033"', $markdown);

        $t->contains('<span class="docx-field docx-field-citation docx-generated-field docx-generated-field-citation"', $blocks);
        $t->contains('data-docx-field-target="Smith2024"', $blocks);
        $t->contains('<span class="docx-field docx-field-bibliography docx-generated-field docx-generated-field-bibliography"', $blocks);
        $t->contains('Smith, Source Packet.</span>.', $blocks);
    },
];
