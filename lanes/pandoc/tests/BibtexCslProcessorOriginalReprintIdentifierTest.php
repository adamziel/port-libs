<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries original and reprint isbn provenance into csl styles' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{facsimile-identifiers,
  author       = {Garcia, Gia},
  title        = {Migration Manual Reissue},
  origtitle    = {Manual de Migracion},
  origisbn     = {978-0-0000-0001-1},
  reprintisbn  = {978-0-0000-0002-8},
  isbn         = {978-0-0000-0003-5},
  publisher    = {Review Press},
  date         = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['facsimile-identifiers'];

        $t->same('978-0-0000-0001-1', $item['original-ISBN'] ?? null);
        $t->same('978-0-0000-0002-8', $item['reprint-ISBN'] ?? null);
        $t->same('978-0-0000-0003-5', $item['ISBN'] ?? null);
        $t->same('978-0-0000-0001-1', $item['rawBibtex']['fields']['origisbn'] ?? null);
        $t->same('978-0-0000-0002-8', $item['rawBibtex']['fields']['reprintisbn'] ?? null);

        $bibliography = $processor->renderBibliographyText($item);
        $t->contains('Original ISBN: 978-0-0000-0001-1', $bibliography);
        $t->contains('Reprint ISBN: 978-0-0000-0002-8', $bibliography);

        $parsedItems = CitationCslProcessor::bibtexItems($source);
        $t->same('978-0-0000-0001-1', $parsedItems[0]['original-ISBN'] ?? null);
        $t->same('978-0-0000-0002-8', $parsedItems[0]['reprint-ISBN'] ?? null);

        $style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Original Reprint Identifier Review</title>
    <id>https://example.test/styles/bounded-original-reprint-identifier-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-ISBN"/>
        <text variable="reprint-ISBN"/>
        <text variable="ISBN"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-ISBN"/>
      <text variable="reprint-ISBN"/>
      <text variable="ISBN"/>
    </layout>
  </bibliography>
</style>
XML;

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle($style);
        $styledItem = $styled->item('facsimile-identifiers');
        $t->same('978-0-0000-0001-1', $styledItem['originalIsbn'] ?? null);
        $t->same('978-0-0000-0002-8', $styledItem['reprintIsbn'] ?? null);
        $t->same('Bounded Original Reprint Identifier Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Garcia | 978-0-0000-0001-1 | 978-0-0000-0002-8 | 978-0-0000-0003-5]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'facsimile-identifiers', 'text' => '[@facsimile-identifiers]']),
        ]));
        $t->same('Migration Manual Reissue :: 978-0-0000-0001-1 :: 978-0-0000-0002-8 :: 978-0-0000-0003-5', $styled->renderBibliographyEntry('facsimile-identifiers'));

        $fromBibtex = CitationCslProcessor::fromBibtex($source)->withCslStyle($style);
        $t->same('[Garcia | 978-0-0000-0001-1 | 978-0-0000-0002-8 | 978-0-0000-0003-5]', $fromBibtex->renderCitationCluster([
            new AstNode('citation', ['id' => 'facsimile-identifiers', 'text' => '[@facsimile-identifiers]']),
        ]));

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-identifiers',
            'title' => 'Direct Identifier Packet',
            'originalISBN' => '978-1-1111-1111-1',
            'reprintISBN' => '978-2-2222-2222-2',
            'ISBN' => '978-3-3333-3333-3',
        ]])->withCslStyle($style);
        $t->same('Direct Identifier Packet :: 978-1-1111-1111-1 :: 978-2-2222-2222-2 :: 978-3-3333-3333-3', $direct->renderBibliographyEntry('direct-identifiers'));

        $default = CitationCslProcessor::fromBibtex($source)->renderBibliographyEntry('facsimile-identifiers');
        $t->contains('Original ISBN: 978-0-0000-0001-1.', $default);
        $t->contains('Reprint ISBN: 978-0-0000-0002-8.', $default);

        $document = (new MarkdownReader())->read('Identifier provenance [@facsimile-identifiers] survives handoff.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['facsimile-identifiers'], $handoff['citedKeys']);
        $t->same('978-0-0000-0001-1', $handoff['items'][0]['original-ISBN'] ?? null);
        $t->same('978-0-0000-0002-8', $handoff['bibliography']->children[0]->attr('cslItem')['reprint-ISBN'] ?? null);
        $t->contains('<p>Identifier provenance [Garcia | 978-0-0000-0001-1 | 978-0-0000-0002-8 | 978-0-0000-0003-5] survives handoff.</p>', $blocks);
        $t->contains('<dt>Garcia 2026</dt><dd>Migration Manual Reissue :: 978-0-0000-0001-1 :: 978-0-0000-0002-8 :: 978-0-0000-0003-5</dd>', $blocks);
    },
];
