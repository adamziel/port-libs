<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries original and reprint locator provenance into csl styles' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{facsimile-locator,
  author         = {Garcia, Gia},
  title          = {Migration Manual Reissue},
  origtitle      = {Manual de Migracion},
  origpages      = {12--18},
  origvolume     = {III},
  origissue      = {2},
  orignumber     = {A-7},
  origedition    = {first source printing},
  reprinttitle   = {Facsimile Packet},
  reprintpages   = {101--109},
  reprintvolume  = {R2},
  reprintissue   = {4},
  reprintnumber  = {F-12},
  reprintedition = {annotated reprint},
  publisher      = {Review Press},
  date           = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['facsimile-locator'];

        $t->same('12-18', $item['original-page'] ?? null);
        $t->same('12', $item['original-page-first'] ?? null);
        $t->same('III', $item['original-volume'] ?? null);
        $t->same('2', $item['original-issue'] ?? null);
        $t->same('A-7', $item['original-number'] ?? null);
        $t->same('first source printing', $item['original-edition'] ?? null);
        $t->same('101-109', $item['reprint-page'] ?? null);
        $t->same('101', $item['reprint-page-first'] ?? null);
        $t->same('R2', $item['reprint-volume'] ?? null);
        $t->same('4', $item['reprint-issue'] ?? null);
        $t->same('F-12', $item['reprint-number'] ?? null);
        $t->same('annotated reprint', $item['reprint-edition'] ?? null);
        $t->same('12--18', $item['rawBibtex']['fields']['origpages'] ?? null);
        $t->same('101--109', $item['rawBibtex']['fields']['reprintpages'] ?? null);

        $bibliography = $processor->renderBibliographyText($item);
        $t->contains('Original pages: 12-18', $bibliography);
        $t->contains('Original first page: 12', $bibliography);
        $t->contains('Reprint pages: 101-109', $bibliography);
        $t->contains('Reprint first page: 101', $bibliography);

        $style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Original Reprint Locator Review</title>
    <id>https://example.test/styles/bounded-original-reprint-locator-review</id>
    <updated>2026-07-01T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-page"/>
        <text variable="original-page-first"/>
        <text variable="origvolume"/>
        <text variable="origissue"/>
        <text variable="orignumber"/>
        <text variable="origedition"/>
        <text variable="reprint-page"/>
        <text variable="reprint-page-first"/>
        <text variable="reprintvolume"/>
        <text variable="reprintissue"/>
        <text variable="reprintnumber"/>
        <text variable="reprintedition"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-page"/>
      <text variable="original-page-first"/>
      <text variable="original-volume"/>
      <text variable="original-issue"/>
      <text variable="original-number"/>
      <text variable="original-edition"/>
      <text variable="reprint-page"/>
      <text variable="reprint-page-first"/>
      <text variable="reprint-volume"/>
      <text variable="reprint-issue"/>
      <text variable="reprint-number"/>
      <text variable="reprint-edition"/>
    </layout>
  </bibliography>
</style>
XML;

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle($style);
        $styledItem = $styled->item('facsimile-locator');
        $t->same('12-18', $styledItem['originalPage'] ?? null);
        $t->same('12', $styledItem['originalPageFirst'] ?? null);
        $t->same('101-109', $styledItem['reprintPage'] ?? null);
        $t->same('101', $styledItem['reprintPageFirst'] ?? null);
        $t->same('Bounded Original Reprint Locator Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Garcia | 12-18 | 12 | III | 2 | A-7 | first source printing | 101-109 | 101 | R2 | 4 | F-12 | annotated reprint]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'facsimile-locator', 'text' => '[@facsimile-locator]']),
        ]));
        $t->same('Migration Manual Reissue :: 12-18 :: 12 :: III :: 2 :: A-7 :: first source printing :: 101-109 :: 101 :: R2 :: 4 :: F-12 :: annotated reprint', $styled->renderBibliographyEntry('facsimile-locator'));

        $parsedItems = CitationCslProcessor::bibtexItems($source);
        $t->same('12-18', $parsedItems[0]['original-page'] ?? null);
        $t->same('12', $parsedItems[0]['original-page-first'] ?? null);
        $t->same('101-109', $parsedItems[0]['reprint-page'] ?? null);
        $t->same('101', $parsedItems[0]['reprint-page-first'] ?? null);

        $fromBibtex = CitationCslProcessor::fromBibtex($source)->withCslStyle($style);
        $t->same('[Garcia | 12-18 | 12 | III | 2 | A-7 | first source printing | 101-109 | 101 | R2 | 4 | F-12 | annotated reprint]', $fromBibtex->renderCitationCluster([
            new AstNode('citation', ['id' => 'facsimile-locator', 'text' => '[@facsimile-locator]']),
        ]));

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-locator',
            'title' => 'Direct Locator Packet',
            'originalPage' => '20--24',
            'originalVolume' => 'IV',
            'originalIssue' => '3',
            'originalNumber' => 'D-9',
            'originalEdition' => 'direct source printing',
            'reprintPage' => '201--209',
            'reprintVolume' => 'R4',
            'reprintIssue' => '6',
            'reprintNumber' => 'R-22',
            'reprintEdition' => 'direct reprint',
        ]])->withCslStyle($style);
        $t->same('Direct Locator Packet :: 20-24 :: 20 :: IV :: 3 :: D-9 :: direct source printing :: 201-209 :: 201 :: R4 :: 6 :: R-22 :: direct reprint', $direct->renderBibliographyEntry('direct-locator'));

        $document = (new MarkdownReader())->read('Locator provenance [@facsimile-locator] survives handoff.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['facsimile-locator'], $handoff['citedKeys']);
        $t->same('12-18', $handoff['items'][0]['original-page'] ?? null);
        $t->same('101-109', $handoff['bibliography']->children[0]->attr('cslItem')['reprint-page'] ?? null);
        $t->contains('<p>Locator provenance [Garcia | 12-18 | 12 | III | 2 | A-7 | first source printing | 101-109 | 101 | R2 | 4 | F-12 | annotated reprint] survives handoff.</p>', $blocks);
        $t->contains('<dt>Garcia 2026</dt><dd>Migration Manual Reissue :: 12-18 :: 12 :: III :: 2 :: A-7 :: first source printing :: 101-109 :: 101 :: R2 :: 4 :: F-12 :: annotated reprint</dd>', $blocks);
    },
];
