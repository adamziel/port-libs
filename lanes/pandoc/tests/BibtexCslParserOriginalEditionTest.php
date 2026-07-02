<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex original edition through direct csl parser handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{direct-original-edition,
  author      = {Ng, Nia},
  title       = {Direct Parser Facsimile Manual},
  origtitle   = {Manual Fuente},
  origedition = {second archive edition},
  date        = {2026}
}

@book{direct-hyphen-original-edition,
  author           = {Roe, Pat},
  title            = {Direct Parser Hyphen Packet},
  original-title   = {Source Packet},
  original-edition = {facsimile proof edition},
  date             = {2025}
}
BIB;

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('second archive edition', $parserItems[0]['original-edition'] ?? null);
        $t->same('facsimile proof edition', $parserItems[1]['original-edition'] ?? null);
        $t->same('second archive edition', $parserItems[0]['rawBibtex']['fields']['origedition'] ?? null);
        $t->same('facsimile proof edition', $parserItems[1]['rawBibtex']['fields']['original-edition'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($biblatex);
        $manual = $processor->item('direct-original-edition');
        $hyphen = $processor->item('direct-hyphen-original-edition');
        $t->same('second archive edition', $manual['originalEdition'] ?? null);
        $t->same('facsimile proof edition', $hyphen['originalEdition'] ?? null);
        $t->contains('Original edition: second archive edition.', $processor->renderBibliographyEntry('direct-original-edition'));
        $t->contains('Original edition: facsimile proof edition.', $processor->renderBibliographyEntry('direct-hyphen-original-edition'));

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Direct BibLaTeX Original Edition Review</title>
    <id>https://example.test/styles/bounded-direct-biblatex-original-edition-review</id>
    <updated>2026-07-02T02:35:00+00:00</updated>
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

        $t->same('Bounded Direct BibLaTeX Original Edition Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Ng | Manual Fuente | second archive edition; Roe | Source Packet | facsimile proof edition]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'direct-original-edition', 'text' => '[@direct-original-edition]']),
            new AstNode('citation', ['id' => 'direct-hyphen-original-edition', 'text' => '[@direct-hyphen-original-edition]']),
        ]));
        $t->same('Direct Parser Facsimile Manual :: Manual Fuente :: second archive edition', $styled->renderBibliographyEntry('direct-original-edition'));
        $t->same('Direct Parser Hyphen Packet :: Source Packet :: facsimile proof edition', $styled->renderBibliographyEntry('direct-hyphen-original-edition'));

        $document = (new MarkdownReader())->read('Direct parser editions cite @direct-original-edition and [@direct-hyphen-original-edition].');
        $bibliography = null;
        foreach ($processor->appendBibliography($document)->children as $child) {
            if ($child->type === 'definition_list') {
                $bibliography = $child;
                break;
            }
        }
        if (!$bibliography instanceof AstNode) {
            $t->same('definition_list', null);

            return;
        }

        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$bibliography]));
        $firstEntry = $bibliography->children[0]->children[1]->children[0]->children[0]->attr('text');
        $secondEntry = $bibliography->children[1]->children[1]->children[0]->children[0]->attr('text');

        $t->contains('Original edition: second archive edition.', $firstEntry);
        $t->contains('Original edition: facsimile proof edition.', $secondEntry);
        $t->contains('Original edition: second archive edition.', $blocks);
        $t->contains('Original edition: facsimile proof edition.', $blocks);
    },
];
