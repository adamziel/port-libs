<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Accepted Revised Date Range Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-accepted-revised-date-range-review</id>
    <updated>2026-07-01T20:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="accepted-date"/>
        <date variable="revised-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="date-accepted"/>
      <date variable="revision-date"/>
    </layout>
  </bibliography>
</style>
XML;

return [
    'records mapped legacy biblatex accepted revised date range case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedLegacyBiblatexAcceptedRevisedDateRangeCases'] ?? null);
        $t->same(29, $manifest['legacyBiblatexAcceptedRevisedDateRangeAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedLegacyBiblatexAcceptedRevisedDateRangeCases'] ?? null);
        $t->same(29, $manifest['benchmarkDenominator']['breakdown']['legacyBiblatexAcceptedRevisedDateRangeAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedLegacyBiblatexAcceptedRevisedDateRangeCases'] ?? null);
        $t->same(29, $manifest['benchmarkDenominator']['inventory']['legacyBiblatexAcceptedRevisedDateRangeAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedLegacyBiblatexAcceptedRevisedDateRangeCases'] ?? null);
        $t->same(29, $manifest['inventory']['legacyBiblatexAcceptedRevisedDateRangeAssertions'] ?? null);
    },

    'carries legacy biblatex accepted and revised split date ranges in csl handoff' => static function (TestRunner $t) use ($styleXml): void {
        $source = <<<'BIB'
@article{publication-state-ranges,
  author           = {Ng, Nia},
  title            = {Publication State Range Packet},
  journaltitle     = {Migration Review},
  date             = {2026},
  acceptedyear     = {2026},
  acceptedmonth    = {6},
  acceptedday      = {1},
  acceptedendyear  = {2026},
  acceptedendmonth = {6},
  acceptedendday   = {15},
  revisedyear      = {2026},
  revisedmonth     = {7},
  revisedday       = {4},
  revisedendyear   = {2026},
  revisedendmonth  = {8},
  revisedendday    = {8},
  status           = {accepted}
}

@report{publication-state-hyphen-ranges,
  author             = {Roe, Pat},
  title              = {Hyphen Split Publication State Packet},
  date               = {2025},
  accepted-year      = {2025},
  accepted-month     = {2},
  accepted-day       = {3},
  accepted-end-year  = {2025},
  accepted-end-month = {3},
  accepted-end-day   = {4},
  revised-year       = {2025},
  revised-month      = {4},
  revised-day        = {5},
  revised-end-year   = {2025},
  revised-end-month  = {5},
  revised-end-day    = {6}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $range = $items['publication-state-ranges'];
        $hyphen = $items['publication-state-hyphen-ranges'];

        $t->same([[2026, 6, 1], [2026, 6, 15]], $range['accepted-date']['date-parts']);
        $t->same([[2026, 7, 4], [2026, 8, 8]], $range['revised-date']['date-parts']);
        $t->same([[2025, 2, 3], [2025, 3, 4]], $hyphen['accepted-date']['date-parts']);
        $t->same([[2025, 4, 5], [2025, 5, 6]], $hyphen['revised-date']['date-parts']);
        $t->same('15', $range['rawBibtex']['fields']['acceptedendday']);
        $t->same('6', $hyphen['rawBibtex']['fields']['revised-end-day']);
        $t->contains('Accepted date: 2026-06-01/2026-06-15.', $processor->renderBibliographyText($range));
        $t->contains('Revised date: 2026-07-04/2026-08-08.', $processor->renderBibliographyText($range));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle($styleXml);
        $normalized = $styled->item('publication-state-ranges');

        $t->same('Bounded Legacy BibLaTeX Accepted Revised Date Range Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same(
            '[Publication State Range Packet | 2026-06-01/2026-06-15 | 2026-07-04/2026-08-08; Hyphen Split Publication State Packet | 2025-02-03/2025-03-04 | 2025-04-05/2025-05-06]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'publication-state-ranges', 'text' => '[@publication-state-ranges]']),
                new AstNode('citation', ['id' => 'publication-state-hyphen-ranges', 'text' => '[@publication-state-hyphen-ranges]']),
            ])
        );
        $t->same(
            'Hyphen Split Publication State Packet :: 2025-02-03/2025-03-04 :: 2025-04-05/2025-05-06',
            $styled->renderBibliographyEntry('publication-state-hyphen-ranges')
        );
        $t->same('2026-06-01/2026-06-15', $normalized['acceptedDate']['display'] ?? null);
        $t->same('2026-07-04/2026-08-08', $normalized['revisedDate']['display'] ?? null);

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-publication-state-range',
            'title' => 'Direct Publication State Range',
            'accepted-date' => ['date-parts' => [[2024, 1], [2024, 2]]],
            'revised-date' => ['date-parts' => [[2024, 3, 4], [2024, 4, 5]]],
        ]])->withCslStyle($styleXml);
        $directItem = $direct->item('direct-publication-state-range');

        $t->same('2024-01/2024-02', $directItem['acceptedDate']['display'] ?? null);
        $t->same('2024-03-04/2024-04-05', $directItem['revisedDate']['display'] ?? null);
        $t->same(
            '[Direct Publication State Range | 2024-01/2024-02 | 2024-03-04/2024-04-05]',
            $direct->renderCitationCluster([
                new AstNode('citation', ['id' => 'direct-publication-state-range', 'text' => '[@direct-publication-state-range]']),
            ])
        );

        $document = (new MarkdownReader())->read('Publication state ranges cite @publication-state-ranges and [@publication-state-hyphen-ranges].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['publication-state-ranges', 'publication-state-hyphen-ranges'], $handoff['citedKeys']);
        $t->same([[2026, 6, 1], [2026, 6, 15]], $handoff['items'][0]['accepted-date']['date-parts']);
        $t->same([[2025, 4, 5], [2025, 5, 6]], $handoff['bibliography']->children[1]->attr('cslItem')['revised-date']['date-parts'] ?? null);
        $t->contains('Accepted date: 2026-06-01/2026-06-15', $blocks);
        $t->contains('Revised date: 2025-04-05/2025-05-06', $blocks);
    },
];
