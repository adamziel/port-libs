<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Name Sort Review

Reviewer-visible citations [@sort-citation-source; @single-source] keep initialized creator names.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "sort-citation-source",
    "type": "report",
    "title": "Citation Sort Name Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/citation-sort-name"
  },
  {
    "id": "single-source",
    "type": "report",
    "title": "Single Citation Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Name Sort Order Review</title>
    <id>https://example.test/styles/wordpress-citation-name-sort-order-review</id>
    <updated>2026-06-08T04:57:01+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", " delimiter-precedes-last="always">
          <name initialize-with=". " name-as-sort-order="all" sort-separator=", "/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author" delimiter=", " delimiter-precedes-last="always">
        <name initialize-with=". " name-as-sort-order="all" sort-separator=", "/>
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
    $citationNames = $summary['citationRendering'][0]['children'][0]['nameRendering'] ?? [];
    if (($citationNames['nameAsSortOrder'] ?? null) !== 'all') {
        throw new RuntimeException('CSL citation name sort-order metadata was not preserved');
    }
    if (($citationNames['sortSeparator'] ?? null) !== ', ') {
        throw new RuntimeException('CSL citation sort separator metadata was not preserved');
    }

    foreach ([
        '<p>Reviewer-visible citations (Smith, A., and Roe, P. 2026; Ng, N. 2025) keep initialized creator names.</p>',
        '<dt>Smith, A., and Roe, P. 2026</dt><dd>Smith, A., and Roe, P. Citation Sort Name Packet. https://example.test/citation-sort-name.</dd>',
        '<dt>Ng, N. 2025</dt><dd>Ng, N. Single Citation Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL citation name sort-order self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-name-sort-order-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
