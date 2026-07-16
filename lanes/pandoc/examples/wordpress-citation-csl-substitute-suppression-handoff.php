<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Substitute Suppression Review

Substitute suppression cites [@named-source; @title-source] before review.
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
    <title>WordPress Citation Substitute Suppression Review</title>
    <id>https://example.test/styles/wordpress-citation-substitute-suppression-review</id>
    <updated>2026-06-08T14:00:35+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author">
          <substitute>
            <text variable="title"/>
          </substitute>
        </names>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
        <substitute>
          <text variable="title"/>
        </substitute>
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
    if (($summary['title'] ?? null) !== 'WordPress Citation Substitute Suppression Review') {
        throw new RuntimeException('CSL substitute suppression handoff did not preserve style title metadata');
    }
    if (($summary['citationRendering'][0]['children'][0]['substitute'][0]['variable'] ?? null) !== 'title') {
        throw new RuntimeException('CSL substitute suppression handoff did not expose citation substitute variable metadata');
    }
    if (($summary['bibliographyRendering'][0]['substitute'][0]['variable'] ?? null) !== 'title') {
        throw new RuntimeException('CSL substitute suppression handoff did not expose bibliography substitute variable metadata');
    }

    foreach ([
        '<p>Substitute suppression cites (Diaz | Named Source Packet | 2026; Title Only Packet | 2025) before review.</p>',
        '<dt>Diaz 2026</dt><dd>Diaz, R. :: Named Source Packet :: 2026</dd>',
        '<dt>Title Only Packet 2025</dt><dd>Title Only Packet :: 2025</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL substitute suppression handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-substitute-suppression-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
