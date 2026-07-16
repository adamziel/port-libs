<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex original edition metadata through legacy csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{original-facsimile,
  author      = {Ng, Nia},
  title       = {Archive Facsimile Manual},
  origtitle   = {Manual Fuente},
  origedition = {second facsimile edition},
  origdate    = {1998},
  publisher   = {Review Press},
  date        = {2026}
}

@book{original-hyphen-edition,
  author           = {Roe, Pat},
  title            = {Hyphen Original Packet},
  original-title   = {Source Packet},
  original-edition = {archive proof edition},
  date             = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $facsimile = $items['original-facsimile'];
        $hyphen = $items['original-hyphen-edition'];

        $t->same('Manual Fuente', $facsimile['original-title']);
        $t->same('second facsimile edition', $facsimile['original-edition']);
        $t->same('second facsimile edition', $facsimile['rawBibtex']['fields']['origedition']);
        $t->same('archive proof edition', $hyphen['original-edition']);
        $t->same('archive proof edition', $hyphen['rawBibtex']['fields']['original-edition']);
        $t->contains('Original edition: second facsimile edition.', $processor->renderBibliographyText($facsimile));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Original Edition Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-original-edition-review</id>
    <updated>2026-07-01T18:05:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-title"/>
        <text variable="original-edition"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-title"/>
      <text variable="original-edition"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('original-facsimile');
        $t->same('Bounded Legacy BibLaTeX Original Edition Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('second facsimile edition', $normalized['originalEdition'] ?? null);
        $t->same('[Ng | Manual Fuente | second facsimile edition; Roe | Source Packet | archive proof edition]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'original-facsimile', 'text' => '[@original-facsimile]']),
            new AstNode('citation', ['id' => 'original-hyphen-edition', 'text' => '[@original-hyphen-edition]']),
        ]));
        $t->same('Archive Facsimile Manual :: Manual Fuente :: second facsimile edition', $styled->renderBibliographyEntry('original-facsimile'));
        $t->same('Hyphen Original Packet :: Source Packet :: archive proof edition', $styled->renderBibliographyEntry('original-hyphen-edition'));

        $document = (new MarkdownReader())->read('Original editions cite @original-facsimile and [@original-hyphen-edition].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['original-facsimile', 'original-hyphen-edition'], $handoff['citedKeys']);
        $t->same('second facsimile edition', $handoff['items'][0]['original-edition']);
        $t->same('archive proof edition', $handoff['bibliography']->children[1]->attr('cslItem')['original-edition'] ?? null);
        $t->contains('Original edition: second facsimile edition.', $blocks);
        $t->contains('Original edition: archive proof edition.', $blocks);
    },
];
