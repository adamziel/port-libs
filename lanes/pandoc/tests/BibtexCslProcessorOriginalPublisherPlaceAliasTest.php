<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'records original publisher place alias manifest accounting' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
        $breakdown = $manifest['benchmarkDenominator']['breakdown'] ?? [];
        $inventory = $manifest['benchmarkDenominator']['inventory'] ?? [];

        $t->same(2317, $manifest['benchmarkDenominator']['mapped'] ?? null);
        $t->same(1, $manifest['legacyBiblatexOriginalPublisherPlaceAliasCases'] ?? null);
        $t->same(1, $manifest['mappedLegacyBiblatexOriginalPublisherPlaceAliasCases'] ?? null);
        $t->same(43, $manifest['legacyBiblatexOriginalPublisherPlaceAliasAssertions'] ?? null);
        $t->same(1, $breakdown['legacyBiblatexOriginalPublisherPlaceAliasCases'] ?? null);
        $t->same(1, $breakdown['mappedLegacyBiblatexOriginalPublisherPlaceAliasCases'] ?? null);
        $t->same(43, $breakdown['legacyBiblatexOriginalPublisherPlaceAliasAssertions'] ?? null);
        $t->same(1, $inventory['legacyBiblatexOriginalPublisherPlaceAliasCases'] ?? null);
        $t->same(1, $inventory['mappedLegacyBiblatexOriginalPublisherPlaceAliasCases'] ?? null);
        $t->same(43, $inventory['legacyBiblatexOriginalPublisherPlaceAliasAssertions'] ?? null);
    },

    'carries compact original publisher place aliases through csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{compact-orig-place,
  author             = {Garcia, Gia},
  title              = {Compact Original Place Packet},
  origpublisher      = {Legacy Press},
  origpublisherplace = {Lisbon},
  date               = {2026}
}

@book{hyphen-orig-place,
  author               = {Roe, Pat},
  title                = {Hyphen Original Place Packet},
  origpublisher        = {Archive Press},
  orig-publisher-place = {Madrid},
  date                 = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $compact = $items['compact-orig-place'];
        $hyphen = $items['hyphen-orig-place'];

        $t->same(2, count($items));
        $t->same('Lisbon', $compact['original-publisher-place'] ?? null);
        $t->same('Madrid', $hyphen['original-publisher-place'] ?? null);
        $t->same('Lisbon', $compact['rawBibtex']['fields']['origpublisherplace'] ?? null);
        $t->same('Madrid', $hyphen['rawBibtex']['fields']['orig-publisher-place'] ?? null);
        $t->same('Legacy Press', $compact['original-publisher'] ?? null);
        $t->same('Archive Press', $hyphen['original-publisher'] ?? null);

        $t->contains('Original publisher: Legacy Press, Lisbon.', $processor->renderBibliographyText($compact));
        $t->contains('Original publisher: Archive Press, Madrid.', $processor->renderBibliographyText($hyphen));

        $style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Original Publisher Place Alias Review</title>
    <id>https://example.test/styles/bounded-original-publisher-place-alias-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-publisher"/>
        <text variable="original-publisher-place"/>
        <text variable="origpublisherplace"/>
        <text variable="orig-publisher-place"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="origpublisher"/>
      <text variable="original-publisher-place"/>
      <text variable="origpublisherplace"/>
      <text variable="orig-publisher-place"/>
    </layout>
  </bibliography>
</style>
XML;

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle($style);
        $styledCompact = $styled->item('compact-orig-place');
        $styledHyphen = $styled->item('hyphen-orig-place');
        $t->same('Bounded Original Publisher Place Alias Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('Lisbon', $styledCompact['originalPublisherPlace'] ?? null);
        $t->same('Madrid', $styledHyphen['originalPublisherPlace'] ?? null);
        $t->same('[Garcia | Legacy Press | Lisbon | Lisbon | Lisbon; Roe | Archive Press | Madrid | Madrid | Madrid]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'compact-orig-place', 'text' => '[@compact-orig-place]']),
            new AstNode('citation', ['id' => 'hyphen-orig-place', 'text' => '[@hyphen-orig-place]']),
        ]));
        $t->same('Compact Original Place Packet :: Legacy Press :: Lisbon :: Lisbon :: Lisbon', $styled->renderBibliographyEntry('compact-orig-place'));
        $t->same('Hyphen Original Place Packet :: Archive Press :: Madrid :: Madrid :: Madrid', $styled->renderBibliographyEntry('hyphen-orig-place'));

        $parsedItems = CitationCslProcessor::bibtexItems($source);
        $t->same(2, count($parsedItems));
        $t->same('Lisbon', $parsedItems[0]['original-publisher-place'] ?? null);
        $t->same('Madrid', $parsedItems[1]['original-publisher-place'] ?? null);
        $t->same('Lisbon', $parsedItems[0]['rawBibtex']['fields']['origpublisherplace'] ?? null);

        $fromBibtex = CitationCslProcessor::fromBibtex($source)->withCslStyle($style);
        $t->same('Lisbon', $fromBibtex->item('compact-orig-place')['originalPublisherPlace'] ?? null);
        $t->same('Madrid', $fromBibtex->item('hyphen-orig-place')['originalPublisherPlace'] ?? null);
        $t->same('[Garcia | Legacy Press | Lisbon | Lisbon | Lisbon; Roe | Archive Press | Madrid | Madrid | Madrid]', $fromBibtex->renderCitationCluster([
            new AstNode('citation', ['id' => 'compact-orig-place', 'text' => '[@compact-orig-place]']),
            new AstNode('citation', ['id' => 'hyphen-orig-place', 'text' => '[@hyphen-orig-place]']),
        ]));

        $direct = CitationCslProcessor::fromItems([
            [
                'id' => 'direct-compact-place',
                'title' => 'Direct Compact Place Packet',
                'origpublisher' => 'Direct Press',
                'origpublisherplace' => 'Porto',
            ],
            [
                'id' => 'direct-list-place',
                'title' => 'Direct List Place Packet',
                'originalPublisher' => 'List Press',
                'origpublisherplacelist' => ['Coimbra', 'Porto'],
            ],
        ])->withCslStyle($style);
        $directCompact = $direct->item('direct-compact-place');
        $directList = $direct->item('direct-list-place');
        $t->same('Porto', $directCompact['originalPublisherPlace'] ?? null);
        $t->same('Coimbra; Porto', $directList['originalPublisherPlace'] ?? null);
        $t->same(['Coimbra', 'Porto'], $directList['originalPublisherPlaceList'] ?? null);
        $t->same('Direct Compact Place Packet :: Direct Press :: Porto :: Porto :: Porto', $direct->renderBibliographyEntry('direct-compact-place'));
        $t->same('Direct List Place Packet :: List Press :: Coimbra; Porto :: Coimbra; Porto :: Coimbra; Porto', $direct->renderBibliographyEntry('direct-list-place'));

        $document = (new MarkdownReader())->read('Original places [@compact-orig-place; @hyphen-orig-place] stay visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['compact-orig-place', 'hyphen-orig-place'], $handoff['citedKeys']);
        $t->same('Lisbon', $handoff['items'][0]['original-publisher-place'] ?? null);
        $t->same('Madrid', $handoff['bibliography']->children[1]->attr('cslItem')['original-publisher-place'] ?? null);
        $t->contains('<p>Original places [Garcia | Legacy Press | Lisbon | Lisbon | Lisbon; Roe | Archive Press | Madrid | Madrid | Madrid] stay visible.</p>', $blocks);
        $t->contains('<dt>Garcia 2026</dt><dd>Compact Original Place Packet :: Legacy Press :: Lisbon :: Lisbon :: Lisbon</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Hyphen Original Place Packet :: Archive Press :: Madrid :: Madrid :: Madrid</dd>', $blocks);
    },
];
