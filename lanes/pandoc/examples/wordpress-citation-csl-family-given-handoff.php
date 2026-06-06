<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Family-Given Review

Family-order source @mao-source and review cluster [@latin-source; @yamada-source] keep reviewer names readable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "mao-source",
    "type": "book",
    "title": "Chinese Review Packet",
    "author": [
      {"family": "毛", "given": "泽东"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "yamada-source",
    "type": "report",
    "title": "Japanese Review Packet",
    "author": [
      {"family": "山田", "given": "太郎"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "latin-source",
    "type": "report",
    "title": "Latin Review Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Family-Given Review</title>
    <id>https://example.test/styles/wordpress-citation-family-given-review</id>
    <updated>2026-06-06T02:42:32+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name name-as-sort-order="all" sort-separator=", "/>
      </names>
      <text variable="title"/>
      <date variable="issued"><date-part name="year"/></date>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['nameRendering']['bibliography']['nameAsSortOrder'] ?? null) !== 'all') {
        throw new RuntimeException('CSL family-given handoff did not preserve bibliography name-as-sort-order metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['nameAsSortOrder'] ?? null) !== 'all') {
        throw new RuntimeException('CSL family-given handoff did not preserve rendering-element name-as-sort-order metadata');
    }

    foreach ([
        '<p>Family-order source 毛 (2026) and review cluster (Smith 2024; 山田 2025) keep reviewer names readable.</p>',
        '<dt>毛 2026</dt><dd>毛泽东. Chinese Review Packet. 2026.</dd>',
        '<dt>Smith 2024</dt><dd>Smith, Ada. Latin Review Packet. 2024.</dd>',
        '<dt>山田 2025</dt><dd>山田太郎. Japanese Review Packet. 2025.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL family-given self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-family-given-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
