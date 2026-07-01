<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries original publication location aliases through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{hyphen-original-location,
  author            = {Roe, Pat},
  title             = {Hyphen Original Location Packet},
  original-location = {{Paris} and {Lyon}},
  date              = {2026}
}

@book{compact-original-place,
  author    = {Ng, Nia},
  title     = {Compact Original Place Packet},
  origplace = {Madrid},
  date      = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $hyphen = $items['hyphen-original-location'];
        $compact = $items['compact-original-place'];

        $t->same('Paris; Lyon', $hyphen['original-publisher-place']);
        $t->same(['Paris', 'Lyon'], $hyphen['original-publisher-place-list']);
        $t->same('Paris and Lyon', $hyphen['rawBibtex']['fields']['original-location']);
        $t->same('Madrid', $compact['original-publisher-place']);
        $t->same('Madrid', $compact['rawBibtex']['fields']['origplace']);

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('Paris; Lyon', $parserItems[0]['original-publisher-place'] ?? null);
        $t->same(['Paris', 'Lyon'], $parserItems[0]['original-publisher-place-list'] ?? null);
        $t->same('Madrid', $parserItems[1]['original-publisher-place'] ?? null);

        $core = CitationCslProcessor::fromBibtex($biblatex);
        $coreHyphen = $core->item('hyphen-original-location');
        $t->same('Paris; Lyon', $coreHyphen['originalPublisherPlace'] ?? null);
        $t->same(['Paris', 'Lyon'], $coreHyphen['originalPublisherPlaceList'] ?? null);
        $t->contains('Original publisher places: Paris; Lyon.', $core->renderBibliographyEntry('hyphen-original-location'));

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-original-location',
            'title' => 'Direct Original Location Packet',
            'originalLocation' => 'Lisbon',
            'issued' => ['date-parts' => [[2024]]],
        ]]);
        $directItem = $direct->item('direct-original-location');
        $t->same('Lisbon', $directItem['originalPublisherPlace'] ?? null);
        $t->same('Direct Original Location Packet. 2024. Original publisher place: Lisbon.', $direct->renderBibliographyEntry('direct-original-location'));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Original Location Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-original-location-alias-review</id>
    <updated>2026-07-01T20:05:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-location"/>
        <text variable="origplace"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-location"/>
      <text variable="original-location-list"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Original Location Alias Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Roe | Paris; Lyon | Paris; Lyon; Ng | Madrid | Madrid]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'hyphen-original-location', 'text' => '[@hyphen-original-location]']),
            new AstNode('citation', ['id' => 'compact-original-place', 'text' => '[@compact-original-place]']),
        ]));
        $t->same('Hyphen Original Location Packet :: Paris; Lyon :: Paris; Lyon', $styled->renderBibliographyEntry('hyphen-original-location'));

        $document = (new MarkdownReader())->read('Original location aliases cite @hyphen-original-location and [@compact-original-place].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['hyphen-original-location', 'compact-original-place'], $handoff['citedKeys']);
        $t->same('Paris; Lyon', $handoff['items'][0]['original-publisher-place']);
        $t->same('Madrid', $handoff['bibliography']->children[1]->attr('cslItem')['original-publisher-place'] ?? null);
        $t->contains('<dt>hyphen-original-location</dt>', $blocks);
        $t->contains('Hyphen Original Location Packet', $blocks);
    },
];
