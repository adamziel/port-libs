<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Numeric Sort Review

Volume review cites [@volume-ten; @volume-two; @volume-range; @volume-special] before import.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "volume-ten",
    "type": "report",
    "title": "Tenth Volume Packet",
    "author": [
      {"family": "Zed", "given": "Zoe"}
    ],
    "issued": {"date-parts": [[2026]]},
    "volume": "10"
  },
  {
    "id": "volume-two",
    "type": "report",
    "title": "Second Volume Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "volume": "2"
  },
  {
    "id": "volume-range",
    "type": "report",
    "title": "Range Volume Packet",
    "author": [
      {"family": "Diaz", "given": "Dev"}
    ],
    "issued": {"date-parts": [[2024]]},
    "volume": "2-3"
  },
  {
    "id": "volume-special",
    "type": "report",
    "title": "Special Volume Packet",
    "author": [
      {"family": "Vale", "given": "Vic"}
    ],
    "issued": {"date-parts": [[2023]]},
    "volume": "Special edition"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Numeric Sort Review</title>
    <id>https://example.test/styles/wordpress-csl-numeric-sort-review</id>
    <updated>2026-06-09T03:55:25+00:00</updated>
  </info>
  <citation>
    <sort>
      <key variable="volume"/>
    </sort>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <label variable="volume" form="short"/>
        <number variable="volume"/>
        <text variable="title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="volume"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="volume" form="short"/>
        <number variable="volume"/>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationSort'][0]['variable'] ?? null) !== 'volume') {
        throw new RuntimeException('CSL numeric-sort handoff did not preserve citation volume sort metadata');
    }
    if (($summary['bibliographySort'][0]['variable'] ?? null) !== 'volume') {
        throw new RuntimeException('CSL numeric-sort handoff did not preserve bibliography volume sort metadata');
    }

    foreach ([
        '<p>Volume review cites (vol. 2 Second Volume Packet; vols. 2-3 Range Volume Packet; vol. 10 Tenth Volume Packet; vol. Special edition Special Volume Packet) before import.</p>',
        '<dt>Ng 2025</dt><dd>Second Volume Packet :: vol. 2</dd>',
        '<dt>Diaz 2024</dt><dd>Range Volume Packet :: vols. 2-3</dd>',
        '<dt>Zed 2026</dt><dd>Tenth Volume Packet :: vol. 10</dd>',
        '<dt>Vale 2023</dt><dd>Special Volume Packet :: vol. Special edition</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL numeric-sort self-test missing expected snippet: ' . $snippet);
        }
    }

    $second = strpos($blocks, '<dt>Ng 2025</dt>');
    $range = strpos($blocks, '<dt>Diaz 2024</dt>');
    $tenth = strpos($blocks, '<dt>Zed 2026</dt>');
    $special = strpos($blocks, '<dt>Vale 2023</dt>');

    if (!(is_int($second) && is_int($range) && is_int($tenth) && is_int($special) && $second < $range && $range < $tenth && $tenth < $special)) {
        throw new RuntimeException('CSL numeric-sort handoff did not preserve numeric bibliography order');
    }

    echo "wordpress-citation-csl-numeric-sort-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
