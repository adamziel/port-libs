<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps bounded biblatex short booktitle aliases into csl container short titles' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@inproceedings{short-booktitle,
  author         = {Ng, Nia},
  title          = {Short Booktitle Packet},
  booktitle      = {Proceedings of Package Review Sources},
  shortbooktitle = {Proc. Package Rev.},
  date           = {2026}
}

@incollection{book-title-short,
  author           = {Roe, Pat},
  title            = {Hyphenated Short Book Title},
  booktitle        = {Archive Handbook of Review Sources},
  book-title-short = {Arch. Handbook},
  date             = {2025}
}
BIB;

        $legacyProcessor = new BibtexCslProcessor();
        $legacyItems = $legacyProcessor->cslItems($source);
        $parserItems = CitationCslProcessor::bibtexItems($source);

        $t->same('Proc. Package Rev.', $legacyItems['short-booktitle']['container-title-short']);
        $t->same('Proc. Package Rev.', $legacyItems['short-booktitle']['journal-abbreviation']);
        $t->same('Arch. Handbook', $legacyItems['book-title-short']['container-title-short']);
        $t->same('Arch. Handbook', $legacyItems['book-title-short']['journal-abbreviation']);
        $t->same('Proc. Package Rev.', $parserItems[0]['container-title-short']);
        $t->same('Proc. Package Rev.', $parserItems[0]['journalAbbreviation']);
        $t->same('Arch. Handbook', $parserItems[1]['container-title-short']);
        $t->same('Arch. Handbook', $parserItems[1]['journalAbbreviation']);
        $t->same('Proc. Package Rev.', $legacyItems['short-booktitle']['rawBibtex']['fields']['shortbooktitle']);
        $t->same('Arch. Handbook', $legacyItems['book-title-short']['rawBibtex']['fields']['book-title-short']);
        $t->contains('Journal abbreviation: Proc. Package Rev', $legacyProcessor->renderBibliographyText($legacyItems['short-booktitle']));

        $document = (new MarkdownReader())->read('Short proceedings [@short-booktitle; @book-title-short] keep review abbreviations.');
        $handoff = $legacyProcessor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['short-booktitle', 'book-title-short'], $handoff['citedKeys']);
        $t->same('Proc. Package Rev.', $handoff['items'][0]['container-title-short'] ?? null);
        $t->same('Arch. Handbook', $handoff['bibliography']->children[1]->attr('cslItem')['container-title-short'] ?? null);
        $t->contains('Journal abbreviation: Proc. Package Rev', $blocks);
        $t->contains('Journal abbreviation: Arch. Handbook', $blocks);

        $styled = CitationCslProcessor::fromBibtex($source)->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Short Booktitle Alias Review</title>
    <id>https://example.test/styles/bounded-biblatex-short-booktitle-alias-review</id>
    <updated>2026-07-01T16:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="container-title"/>
        <text variable="container-title-short"/>
        <text variable="shortbooktitle"/>
        <text variable="book-title-short"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="container-title-short"/>
      <text variable="shortbooktitle"/>
      <text variable="book-title-short"/>
      <text variable="journal-abbreviation"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded BibLaTeX Short Booktitle Alias Review', $summary['title'] ?? null);
        $t->same('shortbooktitle', $summary['citationRendering'][0]['children'][3]['variable'] ?? null);
        $t->same(
            '[Ng | Proceedings of Package Review Sources | Proc. Package Rev. | Proc. Package Rev. | Proc. Package Rev.; Roe | Archive Handbook of Review Sources | Arch. Handbook | Arch. Handbook | Arch. Handbook]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'short-booktitle', 'text' => '[@short-booktitle]']),
                new AstNode('citation', ['id' => 'book-title-short', 'text' => '[@book-title-short]']),
            ])
        );
        $t->same(
            'Short Booktitle Packet :: Proc. Package Rev. :: Proc. Package Rev. :: Proc. Package Rev. :: Proc. Package Rev.',
            $styled->renderBibliographyEntry('short-booktitle')
        );
    },
];
