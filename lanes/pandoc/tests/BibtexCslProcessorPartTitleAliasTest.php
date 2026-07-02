<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex part title short and addendum metadata through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@incollection{part-alias-compact,
  author         = {Ng, Nia},
  title          = {Compact Part Packet},
  booktitle      = {Migration Handbook},
  parttitle      = {Part Ledger},
  partsubtitle   = {Field Notes},
  shortparttitle = {PL},
  parttitleaddon = {archive divider},
  date           = {2026}
}

@incollection{part-alias-hyphen,
  author           = {Roe, Rae},
  title            = {Hyphen Part Packet},
  booktitle        = {Migration Handbook},
  part-title       = {Hyphen Ledger},
  part-subtitle    = {Review Notes},
  part-title-short = {HL},
  part-title-addon = {review divider},
  date             = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $compact = $items['part-alias-compact'];
        $hyphen = $items['part-alias-hyphen'];

        $t->same('Part Ledger: Field Notes', $compact['part-title']);
        $t->same('PL', $compact['part-title-short']);
        $t->same('archive divider', $compact['part-title-addon']);
        $t->same('HL', $hyphen['part-title-short']);
        $t->same('review divider', $hyphen['part-title-addon']);
        $t->same('PL', $compact['rawBibtex']['fields']['shortparttitle']);
        $t->same('review divider', $hyphen['rawBibtex']['fields']['part-title-addon']);
        $t->contains('Part title abbreviation: PL.', $processor->renderBibliographyText($compact));
        $t->contains('Part title addendum: archive divider.', $processor->renderBibliographyText($compact));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Part Title Alias Review</title>
    <id>https://example.test/styles/bounded-biblatex-part-title-alias-review</id>
    <updated>2026-07-01T18:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="part-title"/>
        <text variable="part-title-short"/>
        <text variable="part-title" form="short"/>
        <text variable="part-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="part-title"/>
      <text variable="part-title-short"/>
      <text variable="part-title" form="short"/>
      <text variable="part-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('part-alias-compact');
        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $t->same('Bounded BibLaTeX Part Title Alias Review', $summary['title'] ?? null);
        $t->same('Part Ledger: Field Notes', $normalized['partTitle'] ?? null);
        $t->same('PL', $normalized['partTitleShort'] ?? null);
        $t->same('archive divider', $normalized['partTitleAddon'] ?? null);
        $t->same('part-title-short', $citationChildren[2]['variable'] ?? null);
        $t->same('[Ng | Part Ledger: Field Notes | PL | PL | archive divider; Roe | Hyphen Ledger: Review Notes | HL | HL | review divider]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'part-alias-compact', 'text' => '[@part-alias-compact]']),
            new AstNode('citation', ['id' => 'part-alias-hyphen', 'text' => '[@part-alias-hyphen]']),
        ]));
        $t->same('Compact Part Packet :: Part Ledger: Field Notes :: PL :: PL :: archive divider', $styled->renderBibliographyEntry('part-alias-compact'));
        $t->same('Hyphen Part Packet :: Hyphen Ledger: Review Notes :: HL :: HL :: review divider', $styled->renderBibliographyEntry('part-alias-hyphen'));

        $document = (new MarkdownReader())->read('Part title aliases cite @part-alias-compact and [@part-alias-hyphen].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['part-alias-compact', 'part-alias-hyphen'], $handoff['citedKeys']);
        $t->same('PL', $handoff['items'][0]['part-title-short']);
        $t->same('review divider', $handoff['bibliography']->children[1]->attr('cslItem')['part-title-addon'] ?? null);
        $t->contains('Part title abbreviation: PL.', $blocks);
        $t->contains('Part title addendum: review divider.', $blocks);
    },
];
