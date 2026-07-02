<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'records mapped legacy biblatex reprint title case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedLegacyBiblatexReprintTitleCases'] ?? null);
        $t->same(28, $manifest['legacyBiblatexReprintTitleAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedLegacyBiblatexReprintTitleCases'] ?? null);
        $t->same(28, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexReprintTitleAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedLegacyBiblatexReprintTitleCases'] ?? null);
        $t->same(28, $manifest['benchmarkDenominator']['inventory']['legacyBiblatexReprintTitleAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedLegacyBiblatexReprintTitleCases'] ?? null);
        $t->same(28, $manifest['inventory']['legacyBiblatexReprintTitleAssertions'] ?? null);
    },

    'carries legacy biblatex reprint title metadata through csl handoff' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{legacy-reprint-title,
  author              = {Ng, Nia},
  title               = {Migration Manual},
  date                = {2026},
  reprinttitle        = {Facsimile Source Packet},
  reprintdate         = {2001-04-05},
  reprintdateaddendum = {photostat source release}
}

@book{hyphen-reprint-title,
  author        = {Roe, Pat},
  title         = {Hyphen Reprint Packet},
  date          = {2025},
  reprint-title = {Archive Reprint Sheet}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($biblatex);
        $legacy = $items['legacy-reprint-title'];
        $hyphen = $items['hyphen-reprint-title'];

        $t->same(2, count($items));
        $t->same('Facsimile Source Packet', $legacy['reprint-title']);
        $t->same('Facsimile Source Packet', $legacy['rawBibtex']['fields']['reprinttitle']);
        $t->same('Archive Reprint Sheet', $hyphen['reprint-title']);
        $t->same('Archive Reprint Sheet', $hyphen['rawBibtex']['fields']['reprint-title']);
        $t->contains('Reprint title: Facsimile Source Packet.', $processor->renderBibliographyText($legacy));

        $parserItems = CitationCslProcessor::bibtexItems($biblatex);
        $t->same('Facsimile Source Packet', $parserItems[0]['reprint-title'] ?? null);
        $t->same('Archive Reprint Sheet', $parserItems[1]['reprint-title'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Reprint Title Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-reprint-title-review</id>
    <updated>2026-07-02T00:00:00+00:00</updated>
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

        $normalizedLegacy = $styled->item('legacy-reprint-title');
        $normalizedHyphen = $styled->item('hyphen-reprint-title');

        $t->same('Bounded Legacy BibLaTeX Reprint Title Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('Facsimile Source Packet', $normalizedLegacy['reprintTitle'] ?? null);
        $t->same('Archive Reprint Sheet', $normalizedHyphen['reprintTitle'] ?? null);
        $t->same('2001-04-05', $normalizedLegacy['reprintDate']['display'] ?? null);
        $t->same(
            '[Ng | Migration Manual | Facsimile Source Packet | 2001-04-05 | photostat source release; Roe | Hyphen Reprint Packet | Archive Reprint Sheet]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'legacy-reprint-title', 'text' => '[@legacy-reprint-title]']),
                new AstNode('citation', ['id' => 'hyphen-reprint-title', 'text' => '[@hyphen-reprint-title]']),
            ])
        );
        $t->same('Migration Manual :: Facsimile Source Packet :: 2001-04-05 :: photostat source release', $styled->renderBibliographyEntry('legacy-reprint-title'));
        $t->same('Hyphen Reprint Packet :: Archive Reprint Sheet', $styled->renderBibliographyEntry('hyphen-reprint-title'));

        $document = (new MarkdownReader())->read('Reprint titles cite @legacy-reprint-title and [@hyphen-reprint-title].');
        $handoff = $processor->citationHandoff($document, $biblatex);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-reprint-title', 'hyphen-reprint-title'], $handoff['citedKeys']);
        $t->same('Facsimile Source Packet', $handoff['items'][0]['reprint-title']);
        $t->same('Archive Reprint Sheet', $handoff['bibliography']->children[1]->attr('cslItem')['reprint-title'] ?? null);
        $t->contains('Reprint title: Facsimile Source Packet.', $blocks);
        $t->contains('Reprint title: Archive Reprint Sheet.', $blocks);
    },
];
