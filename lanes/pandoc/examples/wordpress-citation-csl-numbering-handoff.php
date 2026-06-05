<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Numbered Citation Review

Review cites [@zeta; @alpha, p. 9; @middle] while preserving source-numbered bibliography order.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "zeta",
    "type": "report",
    "title": "Zeta Packet",
    "author": [
      {"family": "Zeta", "given": "Zoe"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "alpha",
    "type": "report",
    "title": "Alpha Packet",
    "author": [
      {"family": "Alpha", "given": "Ava"}
    ],
    "issued": {"date-parts": [[2024]]}
  },
  {
    "id": "middle",
    "type": "report",
    "title": "Middle Packet",
    "author": [
      {"family": "Middle", "given": "Mia"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Number Review</title>
    <id>https://example.test/styles/wordpress-citation-number-review</id>
    <updated>2026-06-05T09:29:29+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter=", ">
      <number variable="citation-number"/>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <sort>
      <key variable="author"/>
    </sort>
    <layout delimiter=" ">
      <number variable="citation-number" display="left-margin" prefix="[" suffix="]"/>
      <group display="right-inline" delimiter=". " suffix=".">
        <names variable="author">
          <name initialize-with=". " name-as-sort-order="all"/>
        </names>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Numbered Sources');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['variable'] ?? null) !== 'citation-number') {
        throw new RuntimeException('CSL citation-number handoff did not preserve citation rendering metadata');
    }
    if (($summary['bibliographyRendering'][0]['variable'] ?? null) !== 'citation-number') {
        throw new RuntimeException('CSL citation-number handoff did not preserve bibliography rendering metadata');
    }
    foreach ([
        '<p>Review cites [3, 1, p. 9, 2] while preserving source-numbered bibliography order.</p>',
        '<dt>Alpha 2024</dt><dd><div class="csl-entry"><div class="csl-left-margin">[1]</div><div class="csl-right-inline">Alpha, A. Alpha Packet. 2024.</div></div></dd>',
        '<dt>Zeta 2026</dt><dd><div class="csl-entry"><div class="csl-left-margin">[3]</div><div class="csl-right-inline">Zeta, Z. Zeta Packet. 2026.</div></div></dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL citation-number handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-numbering-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
