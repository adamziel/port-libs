<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Delimiter Review

Delimiter source [@three-name-source] and paired source @two-name-source keep CSL name-list punctuation visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "three-name-source",
    "type": "report",
    "title": "Three Name Source Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Smith", "given": "Sam"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "two-name-source",
    "type": "report",
    "title": "Two Name Source Packet",
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
    <title>WordPress Citation Delimiter Review</title>
    <id>https://example.test/styles/wordpress-citation-delimiter-review</id>
    <updated>2026-06-05T19:54:33+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", " delimiter-precedes-last="never" et-al-min="4">
          <name and="text"/>
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
    if (($summary['nameRendering']['citation']['delimiterPrecedesLast'] ?? null) !== 'never') {
        throw new RuntimeException('CSL delimiter-precedes-last handoff did not preserve citation metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['delimiterPrecedesLast'] ?? null) !== 'always') {
        throw new RuntimeException('CSL delimiter-precedes-last handoff did not preserve bibliography element metadata');
    }

    foreach ([
        '<p>Delimiter source [de la Cruz, Ng and Smith 2026] and paired source Roe and Patel (2025) keep CSL name-list punctuation visible.</p>',
        '<dt>de la Cruz, Ng and Smith 2026</dt><dd>de la Cruz, A. M., Ng, N., &amp; Smith, S. :: Three Name Source Packet</dd>',
        '<dt>Roe and Patel 2025</dt><dd>Roe, P., &amp; Patel, I. :: Two Name Source Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL delimiter-precedes-last self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-delimiter-last-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
