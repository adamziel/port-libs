<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Part Number Citation Review

Part source [@part-source; @part-range] keeps part numbering.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "part-source",
    "type": "report",
    "title": "Migration Part Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "part": "2"
  },
  {
    "id": "part-range",
    "type": "report",
    "title": "Range Part Packet",
    "author": [
      {"family": "Doe", "given": "Jane"}
    ],
    "issued": {"date-parts": [[2025]]},
    "part": "3-4"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Part Number Review</title>
    <id>https://example.test/styles/wordpress-citation-part-number-review</id>
    <updated>2026-06-08T17:04:47+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <label variable="part-number" form="short"/>
        <number variable="part-number" form="roman"/>
        <text variable="part-number" form="ordinal" prefix="(" suffix=")"/>
        <names variable="author"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author"/>
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="part-number"/>
        <number variable="part-number" form="ordinal"/>
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
    $children = $summary['citationRendering'][0]['children'] ?? [];
    if (($children[0]['variable'] ?? null) !== 'part-number' || ($children[0]['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL part number handoff did not preserve label metadata');
    }
    if (($children[1]['variable'] ?? null) !== 'part-number' || ($children[1]['form'] ?? null) !== 'roman') {
        throw new RuntimeException('CSL part number handoff did not preserve number metadata');
    }
    if (($children[2]['variable'] ?? null) !== 'part-number' || ($children[2]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL part number handoff did not preserve text number metadata');
    }

    foreach ([
        '<p>Part source (pt. ii (2nd) Smith; pts. iii-iv (3rd-4th) Doe) keeps part numbering.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Migration Part Packet. part 2nd.</dd>',
        '<dt>Doe 2025</dt><dd>Doe, Jane. Range Part Packet. parts 3rd-4th.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL part number handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-part-number-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
