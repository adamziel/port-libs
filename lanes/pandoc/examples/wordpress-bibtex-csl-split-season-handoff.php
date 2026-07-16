<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Split Season Review

Split season fields @split-season-source and [@split-season-event] keep CSL season labels.
MARKDOWN;

$bibtex = <<<'BIB'
@book{split-season-source,
  author    = {{Split Season Desk}},
  title     = {Split Season Packet},
  year      = {2026},
  month     = {21},
  origyear  = {1999},
  origmonth = {23},
  publisher = {Review Press},
  url       = {https://example.test/split-season-source},
  urlyear   = {2026},
  urlmonth  = {24}
}

@inproceedings{split-season-event,
  author     = {Ng, Nia},
  title      = {Split Season Event Packet},
  booktitle  = {Migration Conference},
  eventyear  = {2025},
  eventmonth = {22},
  year       = {2025}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded BibLaTeX Split Season Review</title>
    <id>https://example.test/styles/bounded-biblatex-split-season-review</id>
    <updated>2026-06-09T08:34:32+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <date variable="issued" form="text"/>
        <text variable="issued-season-name"/>
        <date variable="accessed" form="text"/>
        <date variable="original-date" form="text"/>
        <date variable="event-date" form="text"/>
        <text variable="date-season-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued" form="text"/>
      <text variable="issued-season-name"/>
      <date variable="accessed" form="text"/>
      <date variable="original-date" form="text"/>
      <date variable="event-date" form="text"/>
      <text variable="date-season-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $source = $processor->item('split-season-source');
    $event = $processor->item('split-season-event');

    if (($source['issuedDate']['seasonName'] ?? null) !== 'Spring') {
        throw new RuntimeException('BibLaTeX split season handoff did not preserve issued season metadata');
    }
    if (($source['accessedDate']['seasonName'] ?? null) !== 'Winter') {
        throw new RuntimeException('BibLaTeX split season handoff did not preserve accessed season metadata');
    }
    if (($source['originalDate']['seasonName'] ?? null) !== 'Autumn') {
        throw new RuntimeException('BibLaTeX split season handoff did not preserve original-date season metadata');
    }
    if (($event['eventDate']['seasonName'] ?? null) !== 'Summer') {
        throw new RuntimeException('BibLaTeX split season handoff did not preserve event season metadata');
    }

    foreach ([
        '<p>Split season fields Split Season Desk (2026) and [Ng | 2025 | Summer 2025 | Date seasons: event-date Summer] keep CSL season labels.</p>',
        '<dt>Split Season Desk 2026</dt><dd>Split Season Packet :: Spring 2026 :: Spring :: Winter 2026 :: Autumn 1999 :: Date seasons: issued Spring; accessed Winter; original-date Autumn</dd>',
        '<dt>Ng 2025</dt><dd>Split Season Event Packet :: 2025 :: Summer 2025 :: Date seasons: event-date Summer</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibLaTeX split season self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-split-season-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
