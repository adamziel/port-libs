<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Substitute Display Review

The review packet cites @named-source and @title-source before bibliography import.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "named-source",
    "type": "report",
    "title": "Named Source Packet",
    "author": [
      {"family": "Diaz", "given": "Rosa"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "title-source",
    "type": "webpage",
    "title": "Title Only Packet",
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/title-source"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Substitute Display Review</title>
    <id>https://example.test/styles/wordpress-citation-substitute-display-review</id>
    <updated>2026-06-08T08:18:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author">
          <substitute>
            <text variable="title"/>
          </substitute>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <layout delimiter=" ">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
        <substitute>
          <group display="left-margin" font-weight="bold">
            <text variable="title"/>
          </group>
          <text variable="URL" display="right-inline" prefix="Source: "/>
        </substitute>
      </names>
      <group display="right-inline" delimiter=". " suffix=".">
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
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
    if (($summary['bibliographyRendering'][0]['substitute'][0]['display'] ?? null) !== 'left-margin') {
        throw new RuntimeException('CSL substitute display handoff did not preserve left-margin substitute display metadata');
    }
    if (($summary['bibliographyRendering'][0]['substitute'][0]['formatting']['fontWeight'] ?? null) !== 'bold') {
        throw new RuntimeException('CSL substitute display handoff did not preserve substitute font-weight metadata');
    }

    foreach ([
        '<p>The review packet cites Diaz (2026) and Title Only Packet (2025) before bibliography import.</p>',
        '<dt>Diaz 2026</dt><dd><div class="csl-entry"><div class="csl-right-inline">Named Source Packet. 2026.</div></div></dd>',
        '<dt>Title Only Packet 2025</dt><dd><div class="csl-entry"><div class="csl-left-margin csl-font-weight-bold" style="font-weight:bold">Title Only Packet</div><div class="csl-right-inline">2025.</div></div></dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL substitute display handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-substitute-display-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
