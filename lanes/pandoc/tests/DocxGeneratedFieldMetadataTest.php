<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
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
    <w:p>
      <w:r><w:t xml:space="preserve">Index markers </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> XE "Source Packet" \t "See source dossier" \b \i \y "sosupaketto" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:fldSimple w:instr=' XE "Media Audit" \f "A" \r "media_bookmark" '/>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Citation manager </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> ADDIN ZOTERO_ITEM CSL_CITATION {"citationID":"source-note-1","citationItems":[{"id":"Smith2024"},{"id":"Jones2025"}],"properties":{"noteIndex":1}} </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>(Smith 2024; Jones 2025)</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> bibliography </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN ZOTERO_BIBL '><w:r><w:t>Smith. Source Packet.</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> mendeley </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN Mendeley Bibliography CSL_BIBLIOGRAPHY '><w:r><w:t>Mendeley source list.</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> endnote </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN EN.CITE &lt;Cite&gt;&lt;RecNum&gt;42&lt;/RecNum&gt;&lt;/Cite&gt; '><w:r><w:t>[EndNote 42]</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> refs </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN EN.REFLIST '><w:r><w:t>EndNote sources.</w:t></w:r></w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">List of figures </w:t></w:r>
      <w:fldSimple w:instr=' TOC \c "Figure" \h \p " - " '>
        <w:r><w:t>Figure 1 - Workflow diagram</w:t></w:r>
      </w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Table of authorities </w:t></w:r>
      <w:fldSimple w:instr=' TOA \c "2" \b LegalAuthorities \e ", " \p " - " \h '>
        <w:r><w:t>Cases - Source Authority</w:t></w:r>
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

        $t->contains('<span class="docx-field docx-field-toc docx-generated-field docx-generated-field-toc docx-field-omit-page-numbers docx-field-hyperlink docx-field-outline-levels docx-field-hide-web-layout"', $blocks);
        $t->contains('data-docx-field-style-levels="Appendix,1,Review Heading,2"', $blocks);
        $t->contains('<span class="docx-field docx-field-index docx-generated-field docx-generated-field-index"', $blocks);
        $t->contains('data-docx-field-entry-separator=", "', $blocks);
    },
    'preserves DOCX bibliography and citation field provenance' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
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

        $t->contains('<span class="docx-field docx-field-citation docx-generated-field docx-generated-field-citation"', $blocks);
        $t->contains('data-docx-field-target="Smith2024"', $blocks);
        $t->contains('<span class="docx-field docx-field-bibliography docx-generated-field docx-generated-field-bibliography"', $blocks);
        $t->contains('Smith, Source Packet.</span>.', $blocks);
    },
    'preserves hidden DOCX index entry fields as Pandoc indexref spans' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[3];
        $t->same('paragraph', $paragraph->type);
        $t->same('Index markers ', $paragraph->children[0]->attr('text'));

        $sourceEntry = $paragraph->children[1];
        $t->same('span', $sourceEntry->type);
        $t->same([], $sourceEntry->children);
        $t->same([
            'indexref',
            'docx-field',
            'docx-field-xe',
            'docx-index-entry',
            'docx-index-entry-cross-reference',
            'docx-index-entry-yomi',
            'docx-index-entry-bold',
            'docx-index-entry-italic',
        ], $sourceEntry->attr('classes'));
        $sourceAttrs = $sourceEntry->attr('attributes');
        $t->same('xe', $sourceAttrs['data-docx-field']);
        $t->same('XE "Source Packet" \t "See source dossier" \b \i \y "sosupaketto"', $sourceAttrs['data-docx-field-instruction']);
        $t->same('Source Packet', $sourceAttrs['entry']);
        $t->same('Source Packet', $sourceAttrs['data-docx-index-entry']);
        $t->same('Source Packet', $sourceAttrs['data-docx-field-entry']);
        $t->same('See source dossier', $sourceAttrs['crossref']);
        $t->same('See source dossier', $sourceAttrs['data-docx-field-cross-reference']);
        $t->same('sosupaketto', $sourceAttrs['yomi']);
        $t->same('sosupaketto', $sourceAttrs['data-docx-field-yomi']);
        $t->same('true', $sourceAttrs['bold']);
        $t->same('true', $sourceAttrs['italic']);
        $t->same('true', $sourceAttrs['data-docx-field-bold']);
        $t->same('true', $sourceAttrs['data-docx-field-italic']);

        $t->same(' and ', $paragraph->children[2]->attr('text'));
        $mediaEntry = $paragraph->children[3];
        $t->same('span', $mediaEntry->type);
        $t->same([], $mediaEntry->children);
        $mediaAttrs = $mediaEntry->attr('attributes');
        $t->same('Media Audit', $mediaAttrs['entry']);
        $t->same('A', $mediaAttrs['data-docx-field-entry-type']);
        $t->same('media_bookmark', $mediaAttrs['data-docx-field-bookmark']);
        $t->same('.', $paragraph->children[4]->attr('text'));

        $t->contains('<span class="indexref docx-field docx-field-xe docx-index-entry docx-index-entry-cross-reference docx-index-entry-yomi docx-index-entry-bold docx-index-entry-italic"', $blocks);
        $t->contains('data-docx-index-entry="Source Packet"', $blocks);
        $t->contains('data-docx-field-cross-reference="See source dossier"', $blocks);
        $t->contains('data-docx-field-yomi="sosupaketto"', $blocks);
        $t->contains('<span class="indexref docx-field docx-field-xe docx-index-entry" data-docx-field="xe" data-docx-field-instruction="XE &quot;Media Audit&quot; \f &quot;A&quot; \r &quot;media_bookmark&quot;" data-docx-index-entry="Media Audit"', $blocks);
    },
    'preserves DOCX ADDIN citation manager fields as review spans' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
        $blocks = (new WordPressBlockWriter())->write($document);

        $cslPayload = '{"citationID":"source-note-1","citationItems":[{"id":"Smith2024"},{"id":"Jones2025"}],"properties":{"noteIndex":1}}';
        $endNotePayload = '<Cite><RecNum>42</RecNum></Cite>';
        $paragraph = $document->children[4];
        $t->same('paragraph', $paragraph->type);
        $t->same('Citation manager ', $paragraph->children[0]->attr('text'));

        $citation = $paragraph->children[1];
        $t->same('span', $citation->type);
        $t->same([
            'docx-field',
            'docx-field-addin',
            'docx-addin-field',
            'docx-addin-csl-citation',
            'docx-addin-provider-zotero',
        ], $citation->attr('classes'));
        $citationAttrs = $citation->attr('attributes');
        $t->same('addin', $citationAttrs['data-docx-field']);
        $t->same('csl-citation', $citationAttrs['data-docx-addin-type']);
        $t->same('zotero', $citationAttrs['data-docx-addin-provider']);
        $t->same('json', $citationAttrs['data-docx-addin-payload-kind']);
        $t->same((string) strlen($cslPayload), $citationAttrs['data-docx-addin-payload-bytes']);
        $t->same(hash('sha256', $cslPayload), $citationAttrs['data-docx-addin-payload-sha256']);
        $t->same('true', $citationAttrs['data-docx-addin-csl-json-valid']);
        $t->same('source-note-1', $citationAttrs['data-docx-addin-citation-id']);
        $t->same('2', $citationAttrs['data-docx-addin-citation-item-count']);
        $t->same('Smith2024,Jones2025', $citationAttrs['data-docx-addin-citation-item-ids']);
        $t->same('(Smith 2024; Jones 2025)', $citation->children[0]->attr('text'));

        $zoteroBibliography = $paragraph->children[3];
        $t->same([
            'docx-field',
            'docx-field-addin',
            'docx-addin-field',
            'docx-addin-csl-bibliography',
            'docx-addin-provider-zotero',
        ], $zoteroBibliography->attr('classes'));
        $t->same('csl-bibliography', $zoteroBibliography->attr('attributes')['data-docx-addin-type']);
        $t->same('zotero', $zoteroBibliography->attr('attributes')['data-docx-addin-provider']);
        $t->same('Smith. Source Packet.', $zoteroBibliography->children[0]->attr('text'));

        $mendeleyBibliography = $paragraph->children[5];
        $t->same('mendeley', $mendeleyBibliography->attr('attributes')['data-docx-addin-provider']);
        $t->same('csl-bibliography', $mendeleyBibliography->attr('attributes')['data-docx-addin-type']);

        $endNoteCitation = $paragraph->children[7];
        $endNoteAttrs = $endNoteCitation->attr('attributes');
        $t->same('endnote-citation', $endNoteAttrs['data-docx-addin-type']);
        $t->same('endnote', $endNoteAttrs['data-docx-addin-provider']);
        $t->same('xml', $endNoteAttrs['data-docx-addin-payload-kind']);
        $t->same((string) strlen($endNotePayload), $endNoteAttrs['data-docx-addin-payload-bytes']);
        $t->same(hash('sha256', $endNotePayload), $endNoteAttrs['data-docx-addin-payload-sha256']);
        $t->same('[EndNote 42]', $endNoteCitation->children[0]->attr('text'));

        $endNoteReferenceList = $paragraph->children[9];
        $t->same('endnote-reference-list', $endNoteReferenceList->attr('attributes')['data-docx-addin-type']);
        $t->same('endnote', $endNoteReferenceList->attr('attributes')['data-docx-addin-provider']);
        $t->same('EndNote sources.', $endNoteReferenceList->children[0]->attr('text'));

        $t->contains('<span class="docx-field docx-field-addin docx-addin-field docx-addin-csl-citation docx-addin-provider-zotero"', $blocks);
        $t->contains('data-docx-addin-citation-item-ids="Smith2024,Jones2025"', $blocks);
        $t->contains('<span class="docx-field docx-field-addin docx-addin-field docx-addin-csl-bibliography docx-addin-provider-mendeley"', $blocks);
        $t->contains('<span class="docx-field docx-field-addin docx-addin-field docx-addin-endnote-reference-list docx-addin-provider-endnote"', $blocks);
    },
    'preserves DOCX table-of-figures TOC sequence switch provenance' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[5];
        $t->same('paragraph', $paragraph->type);
        $t->same('List of figures ', $paragraph->children[0]->attr('text'));

        $listOfFigures = $paragraph->children[1];
        $t->same('span', $listOfFigures->type);
        $t->same([
            'docx-field',
            'docx-field-toc',
            'docx-generated-field',
            'docx-generated-field-toc',
            'docx-field-hyperlink',
        ], $listOfFigures->attr('classes'));
        $attrs = $listOfFigures->attr('attributes');
        $t->same('toc', $attrs['data-docx-field']);
        $t->same('TOC \c "Figure" \h \p " - "', $attrs['data-docx-field-instruction']);
        $t->same('table-of-contents', $attrs['data-docx-generated-field-type']);
        $t->same('Figure', $attrs['data-docx-field-sequence']);
        $t->same(' - ', $attrs['data-docx-field-page-number-separator']);
        $t->same('true', $attrs['data-docx-field-hyperlink']);
        $t->true(!isset($attrs['data-docx-field-columns']), 'TOC \\c should not be reported as INDEX column metadata');
        $t->same('Figure 1 - Workflow diagram', $listOfFigures->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));

        $t->contains('<span class="docx-field docx-field-toc docx-generated-field docx-generated-field-toc docx-field-hyperlink"', $blocks);
        $t->contains('data-docx-field-sequence="Figure"', $blocks);
        $t->contains('data-docx-field-page-number-separator=" - "', $blocks);
        $t->true(!str_contains($blocks, 'data-docx-field-columns="Figure"'), 'WordPress output should not label TOC \\c as columns');
    },
    'preserves DOCX table of authorities generated field category provenance' => static function (TestRunner $t) use ($buildGeneratedFieldPackage): void {
        $document = (new DocxReader())->readDocument($buildGeneratedFieldPackage());
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[6];
        $t->same('paragraph', $paragraph->type);
        $t->same('Table of authorities ', $paragraph->children[0]->attr('text'));

        $authorities = $paragraph->children[1];
        $t->same('span', $authorities->type);
        $t->same([
            'docx-field',
            'docx-field-toa',
            'docx-generated-field',
            'docx-generated-field-toa',
            'docx-field-hyperlink',
        ], $authorities->attr('classes'));
        $attrs = $authorities->attr('attributes');
        $t->same('toa', $attrs['data-docx-field']);
        $t->same('TOA \c "2" \b LegalAuthorities \e ", " \p " - " \h', $attrs['data-docx-field-instruction']);
        $t->same('table-of-authorities', $attrs['data-docx-generated-field-type']);
        $t->same('2', $attrs['data-docx-field-category']);
        $t->same('LegalAuthorities', $attrs['data-docx-field-bookmark']);
        $t->same(', ', $attrs['data-docx-field-entry-separator']);
        $t->same(' - ', $attrs['data-docx-field-page-number-separator']);
        $t->same('true', $attrs['data-docx-field-hyperlink']);
        $t->true(!isset($attrs['data-docx-field-target']), 'TOA switch values should not be reported as a field target');
        $t->same('Cases - Source Authority', $authorities->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));

        $t->contains('<span class="docx-field docx-field-toa docx-generated-field docx-generated-field-toa docx-field-hyperlink"', $blocks);
        $t->contains('data-docx-field-category="2"', $blocks);
        $t->contains('data-docx-field-page-number-separator=" - "', $blocks);
    },
];
