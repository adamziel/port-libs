<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Sort Separator Review

Sort separator source @sort-separator-source and first-only source [@first-sort-separator-source] keep inverted reviewer names readable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "sort-separator-source",
    "type": "report",
    "title": "Sort Separator Packet",
    "author": [
      {"family": "Source", "given": "Ada Maria"},
      {"family": "Reviewer", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/sort-separator"
  },
  {
    "id": "first-sort-separator-source",
    "type": "report",
    "title": "First Sort Separator Packet",
    "author": [
      {"family": "Primary", "given": "Eli"},
      {"family": "Secondary", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/first-sort-separator"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Sort Separator Review</title>
    <id>https://example.test/styles/wordpress-citation-sort-separator-review</id>
    <updated>2026-06-05T13:52:03+00:00</updated>
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
        <name initialize-with=". " name-as-sort-order="all" sort-separator=" | "/>
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
    if (($summary['nameRendering']['bibliography']['sortSeparator'] ?? null) !== ' | ') {
        throw new RuntimeException('CSL sort-separator handoff did not preserve bibliography name rendering metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['sortSeparator'] ?? null) !== ' | ') {
        throw new RuntimeException('CSL sort-separator handoff did not preserve bibliography rendering element metadata');
    }

    foreach ([
        '<p>Sort separator source Source and Reviewer (2026) and first-only source (Primary and Secondary 2025) keep inverted reviewer names readable.</p>',
        '<dt>Source and Reviewer 2026</dt><dd>Source | A. M.; Reviewer | N. Sort Separator Packet. https://example.test/sort-separator.</dd>',
        '<dt>Primary and Secondary 2025</dt><dd>Primary | E.; Secondary | P. First Sort Separator Packet. https://example.test/first-sort-separator.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL sort-separator self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-sort-separator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
