<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Split End Date Review

Split date fields [@split-date-packet; @split-event-proceedings] keep review windows visible.
MARKDOWN;

$bibtex = <<<'BIB'
@online{split-date-packet,
  author            = {Roe, Pat},
  title             = {Split Date Packet},
  year              = {2020},
  month             = {may},
  day               = {9},
  endyear           = {2021},
  endmonth          = {jun},
  endday            = {11},
  origyear          = {2018},
  origendyear       = {2019},
  url               = {https://example.test/split-date},
  urlyear           = {2026},
  urlmonth          = {6},
  urlday            = {1},
  urlendyear        = {2026},
  urlendmonth       = {6},
  urlendday         = {2},
  availableyear     = {2025},
  availablemonth    = {4},
  availableday      = {3},
  availableendyear  = {2025},
  availableendmonth = {4},
  availableendday   = {5},
  submittedyear     = {2024},
  submittedmonth    = {3},
  submittedendyear  = {2024},
  submittedendmonth = {4}
}

@proceedings{split-event-proceedings,
  editor        = {{Event Review Desk}},
  title         = {Split Event Proceedings},
  eventtitle    = {Migration Review Clinic},
  eventyear     = {2026},
  eventmonth    = {6},
  eventday      = {4},
  eventendyear  = {2026},
  eventendmonth = {6},
  eventendday   = {5},
  date          = {2026},
  publisher     = {Review Press}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded BibLaTeX Split End Date Review</title>
    <id>https://example.test/styles/bounded-biblatex-split-end-date-review</id>
    <updated>2026-06-09T05:27:46+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="issued"/>
        <date variable="available-date"/>
        <date variable="submitted"/>
        <date variable="event-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued"/>
      <date variable="original-date"/>
      <date variable="accessed"/>
      <date variable="available-date"/>
      <date variable="submitted"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $packet = $processor->item('split-date-packet');
    $event = $processor->item('split-event-proceedings');

    if (($packet['issuedDate']['display'] ?? null) !== '2020-05-09/2021-06-11') {
        throw new RuntimeException('BibLaTeX split end date handoff did not preserve issued date range metadata');
    }
    if (($packet['availableDate']['display'] ?? null) !== '2025-04-03/2025-04-05') {
        throw new RuntimeException('BibLaTeX split end date handoff did not preserve available date range metadata');
    }
    if (($packet['submittedDate']['display'] ?? null) !== '2024-03/2024-04') {
        throw new RuntimeException('BibLaTeX split end date handoff did not preserve submitted date range metadata');
    }
    if (($event['eventDate']['display'] ?? null) !== '2026-06-04/2026-06-05') {
        throw new RuntimeException('BibLaTeX split end date handoff did not preserve event date range metadata');
    }

    foreach ([
        '<p>Split date fields [Split Date Packet | 2020-05-09/2021-06-11 | 2025-04-03/2025-04-05 | 2024-03/2024-04; Split Event Proceedings | 2026 | 2026-06-04/2026-06-05] keep review windows visible.</p>',
        '<dt>Roe 2020/2021</dt><dd>Split Date Packet :: 2020-05-09/2021-06-11 :: 2018/2019 :: 2026-06-01/2026-06-02 :: 2025-04-03/2025-04-05 :: 2024-03/2024-04</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibLaTeX split end date self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-split-end-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
