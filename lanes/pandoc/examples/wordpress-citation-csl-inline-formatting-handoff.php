<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Inline Formatting Review

The reviewer packet cites [@format-source; @escaped-source] before import.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "format-source",
    "type": "report",
    "title": "Formatted Source Packet",
    "author": [
      {"family": "Vale", "given": "Vera"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "escaped-source",
    "type": "report",
    "title": "Escaped Source Packet",
    "author": [
      {"family": "Ng & Sons", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Inline Formatting Review</title>
    <id>https://example.test/styles/wordpress-citation-inline-formatting-review</id>
    <updated>2026-06-09T02:55:45+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" " font-style="italic">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author"/>
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
    if (($summary['citationRendering'][0]['formatting']['fontStyle'] ?? null) !== 'italic') {
        throw new RuntimeException('CSL inline formatting handoff did not preserve citation font-style metadata');
    }

    $cluster = $document->children[1]->children[1] ?? null;
    $parts = is_object($cluster) && method_exists($cluster, 'attr') ? $cluster->attr('cslInlineParts') : null;
    if (!is_array($parts) || (($parts[1]['formatting']['fontStyle'] ?? null) !== 'italic')) {
        throw new RuntimeException('CSL inline formatting handoff did not expose formatted citation parts');
    }

    foreach ([
        '<p>The reviewer packet cites (<span class="csl-font-style-italic" style="font-style:italic">Vale 2026</span>; <span class="csl-font-style-italic" style="font-style:italic">Ng &amp; Sons 2025</span>) before import.</p>',
        '<dt>Ng &amp; Sons 2025</dt><dd>Ng &amp; Sons, Nia. Escaped Source Packet. 2025.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL inline formatting handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-inline-formatting-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
