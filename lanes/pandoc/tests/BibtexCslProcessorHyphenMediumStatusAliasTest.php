<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries hyphenated biblatex medium and pub-state aliases through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@misc{hyphen-source-packet,
  author        = {Bell, Bea},
  title         = {Hyphen Medium Status Packet},
  how-published = {institutional handout},
  pub-state     = {forthcoming},
  date          = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $item = $items['hyphen-source-packet'];

        $t->same('institutional handout', $item['medium']);
        $t->same('forthcoming', $item['status']);
        $t->same('institutional handout', $item['rawBibtex']['fields']['how-published']);
        $t->same('forthcoming', $item['rawBibtex']['fields']['pub-state']);
        $t->contains('Medium: institutional handout.', $processor->renderBibliographyText($item));
        $t->contains('Status: forthcoming.', $processor->renderBibliographyText($item));

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('institutional handout', $parserItems[0]['medium'] ?? null);
        $t->same('forthcoming', $parserItems[0]['status'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded BibLaTeX Hyphen Medium Status Alias Review</title>
    <id>https://example.test/styles/bounded-biblatex-hyphen-medium-status-alias-review</id>
    <updated>2026-07-01T20:30:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="how-published"/>
        <text variable="pub-state"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="how-published"/>
      <text variable="pub-state"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('hyphen-source-packet');
        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];

        $t->same('institutional handout', $normalized['medium'] ?? null);
        $t->same('forthcoming', $normalized['status'] ?? null);
        $t->same('Bounded BibLaTeX Hyphen Medium Status Alias Review', $summary['title'] ?? null);
        $t->same('how-published', $citationChildren[1]['variable'] ?? null);
        $t->same('pub-state', $citationChildren[2]['variable'] ?? null);
        $t->same('[Bell | institutional handout | forthcoming]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'hyphen-source-packet', 'text' => '[@hyphen-source-packet]']),
        ]));
        $t->same('Hyphen Medium Status Packet :: institutional handout :: forthcoming', $styled->renderBibliographyEntry('hyphen-source-packet'));

        $document = (new MarkdownReader())->read('Hyphen source [@hyphen-source-packet] stays visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Hyphen source [Bell | institutional handout | forthcoming] stays visible.</p>', $blocks);
        $t->contains('<dt>Bell 2026</dt><dd>Hyphen Medium Status Packet :: institutional handout :: forthcoming</dd>', $blocks);
    },
];
