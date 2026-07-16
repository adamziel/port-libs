<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Symbol And Review

Localized symbol source [@symbol-source; @fallback-source] keeps source names joined for reviewer copy.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "symbol-source",
    "type": "report",
    "title": "Symbol Join Source Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "fallback-source",
    "type": "report",
    "title": "Fallback Join Source Packet",
    "author": [
      {"family": "Roe", "given": "Pat"},
      {"family": "Patel", "given": "Ira"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Symbol And Review</title>
    <id>https://example.test/styles/wordpress-citation-symbol-and-review</id>
    <updated>2026-06-08T21:34:49+00:00</updated>
  </info>
  <locale>
    <terms>
      <term name="and" form="symbol">+</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", ">
          <name and="symbol"/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="author" delimiter=", " delimiter-precedes-last="always">
        <name initialize-with=". " name-as-sort-order="all" and="symbol"/>
      </names>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['and'] ?? null) !== 'symbol') {
        throw new RuntimeException('CSL symbol-and handoff did not preserve citation and=symbol metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['and'] ?? null) !== 'symbol') {
        throw new RuntimeException('CSL symbol-and handoff did not preserve bibliography and=symbol metadata');
    }

    foreach ([
        '<p>Localized symbol source (Smith + Ng 2026; Roe + Patel 2025) keeps source names joined for reviewer copy.</p>',
        '<dt>Smith + Ng 2026</dt><dd>Smith, A., + Ng, N. :: Symbol Join Source Packet</dd>',
        '<dt>Roe + Patel 2025</dt><dd>Roe, P., + Patel, I. :: Fallback Join Source Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL symbol-and self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-symbol-and-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
