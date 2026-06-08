<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Initialize False Review

Initialize false keeps @given-only-source and [@family-given-source] names reviewable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "given-only-source",
    "type": "report",
    "title": "Full Given Packet",
    "author": [
      {"given": "James T"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/full-given"
  },
  {
    "id": "family-given-source",
    "type": "report",
    "title": "Family Given Packet",
    "author": [
      {"family": "Kirk", "given": "James T"}
    ],
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/family-given"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Initialize False Review</title>
    <id>https://example.test/styles/wordpress-citation-initialize-false-review</id>
    <updated>2026-06-08T21:49:33+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author">
          <name initialize-with=". " initialize="false"/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " initialize="false" name-as-sort-order="all"/>
      </names>
      <text variable="title"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['nameRendering']['citation']['initialize'] ?? null) !== false) {
        throw new RuntimeException('CSL initialize=false handoff did not preserve citation name rendering metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['initialize'] ?? null) !== false) {
        throw new RuntimeException('CSL initialize=false handoff did not preserve bibliography rendering element metadata');
    }

    foreach ([
        '<p>Initialize false keeps James T. (2026) and (Kirk 2025) names reviewable.</p>',
        '<dt>James T. 2026</dt><dd>James T. Full Given Packet. https://example.test/full-given.</dd>',
        '<dt>Kirk 2025</dt><dd>Kirk, James T. Family Given Packet. https://example.test/family-given.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL initialize=false self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-initialize-false-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
