<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries legacy biblatex reprint title metadata through legacy csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{legacy-reprint-source,
  author             = {Ng, Nia},
  title              = {Migration Manual Reprint},
  reprinttitle       = {Facsimile Source Packet},
  reprintdate        = {2001-04-05},
  reprintdateaddon   = {photostat release},
  publisher          = {Review Press},
  date               = {2026}
}

@book{hyphen-reprint-source,
  author             = {Roe, Pat},
  title              = {Hyphen Reprint Packet},
  reprint-title      = {Bound Reviewer Facsimile},
  reprint-date       = {2002-06},
  reprint-date-addon = {archive desk proof},
  date               = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $legacy = $items['legacy-reprint-source'];
        $hyphen = $items['hyphen-reprint-source'];

        $t->same('Facsimile Source Packet', $legacy['reprint-title']);
        $t->same('Bound Reviewer Facsimile', $hyphen['reprint-title']);
        $t->same('Facsimile Source Packet', $legacy['rawBibtex']['fields']['reprinttitle']);
        $t->same('Bound Reviewer Facsimile', $hyphen['rawBibtex']['fields']['reprint-title']);
        $t->same([2001, 4, 5], $legacy['reprint-date']['date-parts'][0]);
        $t->same([2002, 6], $hyphen['reprint-date']['date-parts'][0]);
        $t->same('photostat release', $legacy['reprint-date-addon']);
        $t->contains('Reprint title: Facsimile Source Packet.', $processor->renderBibliographyText($legacy));
        $t->contains('Reprint title: Bound Reviewer Facsimile.', $processor->renderBibliographyText($hyphen));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Reprint Title Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-reprint-title-review</id>
    <updated>2026-07-01T20:10:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="title"/>
        <text variable="reprint-title"/>
        <date variable="reprint-date"/>
        <text variable="reprint-date-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="reprint-title"/>
      <date variable="reprint-date"/>
      <text variable="reprint-date-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('legacy-reprint-source');
        $t->same('Bounded Legacy BibLaTeX Reprint Title Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('Facsimile Source Packet', $normalized['reprintTitle'] ?? null);
        $t->same('[Ng | Migration Manual Reprint | Facsimile Source Packet | 2001-04-05 | photostat release; Roe | Hyphen Reprint Packet | Bound Reviewer Facsimile | 2002-06 | archive desk proof]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-reprint-source', 'text' => '[@legacy-reprint-source]']),
            new AstNode('citation', ['id' => 'hyphen-reprint-source', 'text' => '[@hyphen-reprint-source]']),
        ]));
        $t->same('Migration Manual Reprint :: Facsimile Source Packet :: 2001-04-05 :: photostat release', $styled->renderBibliographyEntry('legacy-reprint-source'));
        $t->same('Hyphen Reprint Packet :: Bound Reviewer Facsimile :: 2002-06 :: archive desk proof', $styled->renderBibliographyEntry('hyphen-reprint-source'));

        $document = (new MarkdownReader())->read('Reprint sources cite @legacy-reprint-source and [@hyphen-reprint-source].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-reprint-source', 'hyphen-reprint-source'], $handoff['citedKeys']);
        $t->same('Facsimile Source Packet', $handoff['items'][0]['reprint-title']);
        $t->same('Bound Reviewer Facsimile', $handoff['bibliography']->children[1]->attr('cslItem')['reprint-title'] ?? null);
        $t->contains('Reprint title: Facsimile Source Packet.', $blocks);
        $t->contains('Reprint title: Bound Reviewer Facsimile.', $blocks);
    },
];
