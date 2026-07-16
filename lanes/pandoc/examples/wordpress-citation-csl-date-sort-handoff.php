<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Date Sort Review

Reviewer packets cite [@late-access; @early-early-event; @early-late-event] while preserving access and event chronology.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "late-access",
    "type": "report",
    "title": "Late Access Packet",
    "accessed": {"date-parts": [[2026, 6, 10]]},
    "event-date": {"date-parts": [[2026, 6, 2]]},
    "original-date": {"date-parts": [[2019]]},
    "author": [
      {"family": "Zed", "given": "Zoe"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "early-early-event",
    "type": "report",
    "title": "Early Event Packet",
    "accessed": {"date-parts": [[2026, 6, 1]]},
    "event-date": {"date-parts": [[2026, 6, 3]]},
    "original-date": {"date-parts": [[2018]]},
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2024]]}
  },
  {
    "id": "early-late-event",
    "type": "report",
    "title": "Late Event Packet",
    "accessed": {"date-parts": [[2026, 6, 1]]},
    "event-date": {"date-parts": [[2026, 6, 9]]},
    "original-date": {"date-parts": [[2021]]},
    "author": [
      {"family": "Adams", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Date Sort Review</title>
    <id>https://example.test/styles/wordpress-csl-date-sort-review</id>
    <updated>2026-06-08T23:51:50+00:00</updated>
  </info>
  <citation>
    <sort>
      <key variable="accessed"/>
      <key variable="event-date" sort="descending"/>
      <key variable="original-date"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <number variable="citation-number"/>
        <text variable="title"/>
        <date variable="accessed"/>
        <date variable="event-date"/>
        <date variable="original-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="accessed"/>
      <key variable="event-date" sort="descending"/>
      <key variable="original-date"/>
    </sort>
    <layout delimiter=" :: ">
      <number variable="citation-number"/>
      <text variable="title"/>
      <date variable="accessed"/>
      <date variable="event-date"/>
      <date variable="original-date"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();

    if (($summary['citationSort'][0]['variable'] ?? null) !== 'accessed') {
        throw new RuntimeException('CSL date-sort handoff did not preserve citation accessed sort metadata');
    }
    if (($summary['citationSort'][1]['variable'] ?? null) !== 'event-date') {
        throw new RuntimeException('CSL date-sort handoff did not preserve citation event-date sort metadata');
    }
    if (($summary['citationSort'][1]['sort'] ?? null) !== 'descending') {
        throw new RuntimeException('CSL date-sort handoff did not preserve descending event-date metadata');
    }
    if (($summary['bibliographySort'][2]['variable'] ?? null) !== 'original-date') {
        throw new RuntimeException('CSL date-sort handoff did not preserve bibliography original-date sort metadata');
    }

    foreach ([
        '<p>Reviewer packets cite [1 | Late Event Packet | 2026-06-01 | 2026-06-09 | 2021; 2 | Early Event Packet | 2026-06-01 | 2026-06-03 | 2018; 3 | Late Access Packet | 2026-06-10 | 2026-06-02 | 2019] while preserving access and event chronology.</p>',
        '<dt>Adams 2025</dt><dd>1 :: Late Event Packet :: 2026-06-01 :: 2026-06-09 :: 2021</dd>',
        '<dt>Ng 2024</dt><dd>2 :: Early Event Packet :: 2026-06-01 :: 2026-06-03 :: 2018</dd>',
        '<dt>Zed 2026</dt><dd>3 :: Late Access Packet :: 2026-06-10 :: 2026-06-02 :: 2019</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL date-sort self-test missing expected snippet: ' . $snippet);
        }
    }

    $lateEvent = strpos($blocks, '<dt>Adams 2025</dt>');
    $earlyEvent = strpos($blocks, '<dt>Ng 2024</dt>');
    $lateAccess = strpos($blocks, '<dt>Zed 2026</dt>');

    if (!(is_int($lateEvent) && is_int($earlyEvent) && is_int($lateAccess) && $lateEvent < $earlyEvent && $earlyEvent < $lateAccess)) {
        throw new RuntimeException('CSL date-sort handoff did not preserve sorted bibliography order');
    }

    echo "wordpress-citation-csl-date-sort-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
