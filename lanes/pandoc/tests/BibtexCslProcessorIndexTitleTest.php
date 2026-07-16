<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries biblatex index title metadata through legacy csl bibliography handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{index-manual,
  author         = {Ng, Nia},
  title          = {The Source Audit Companion},
  indextitle     = {Source Audit Companion, The},
  indexsorttitle = {Source Audit Companion},
  date           = {2026}
}

@report{index-fallback,
  author     = {Roe, Pat},
  title      = {Archive Index Packet},
  indextitle = {Archive Index Packet, The},
  date       = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $manual = $items['index-manual'];
        $fallback = $items['index-fallback'];

        $t->same('Source Audit Companion, The', $manual['index-title']);
        $t->same('Source Audit Companion', $manual['index-sort-title']);
        $t->same('Archive Index Packet, The', $fallback['index-title']);
        $t->same('Archive Index Packet, The', $fallback['index-sort-title']);
        $t->same('Source Audit Companion, The', $manual['rawBibtex']['fields']['indextitle']);
        $t->same('Source Audit Companion', $manual['rawBibtex']['fields']['indexsorttitle']);
        $t->contains('Index title: Source Audit Companion, The.', $processor->renderBibliographyText($manual));
        $t->contains('Index sort title: Source Audit Companion.', $processor->renderBibliographyText($manual));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="index-title"/>
        <text variable="index-sort-title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="index-title"/>
      <text variable="index-sort-title"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[Ng | Source Audit Companion, The | Source Audit Companion; Roe | Archive Index Packet, The | Archive Index Packet, The]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'index-manual', 'text' => '[@index-manual]']),
            new AstNode('citation', ['id' => 'index-fallback', 'text' => '[@index-fallback]']),
        ]));
        $t->same('The Source Audit Companion :: Source Audit Companion, The :: Source Audit Companion', $styled->renderBibliographyEntry('index-manual'));
        $t->same('Archive Index Packet :: Archive Index Packet, The :: Archive Index Packet, The', $styled->renderBibliographyEntry('index-fallback'));

        $document = (new MarkdownReader())->read('Index titles cite @index-manual and [@index-fallback].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same('Source Audit Companion, The', $handoff['items'][0]['index-title']);
        $t->same('Archive Index Packet, The', $handoff['items'][1]['index-sort-title']);
        $t->contains('Index title: Source Audit Companion, The.', $blocks);
        $t->contains('Index sort title: Archive Index Packet, The.', $blocks);
    },
];
