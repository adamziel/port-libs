<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Source Provenance Review

Imported citation packets [@source-provenance; @source-journal] keep collection provenance visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-provenance",
    "type": "report",
    "title": "Source Provenance Review",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "source": "Legacy Drupal export batch 42"
  },
  {
    "id": "source-journal",
    "type": "article-journal",
    "title": "Imported Extract",
    "container-title": "Migration Review",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "source": "Internet Archive snapshot"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Source Provenance Review</title>
    <id>https://example.test/styles/wordpress-citation-source-provenance-review</id>
    <updated>2026-06-08T23:09:38+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="source"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="source"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
    $bibliographyChildren = $summary['bibliographyRendering'] ?? [];

    if (($citationChildren[1]['variable'] ?? null) !== 'source') {
        throw new RuntimeException('CSL source handoff did not preserve citation source variable metadata');
    }
    if (($bibliographyChildren[1]['variable'] ?? null) !== 'source') {
        throw new RuntimeException('CSL source handoff did not preserve bibliography source variable metadata');
    }
    if (($processor->item('source-provenance')['source'] ?? null) !== 'Legacy Drupal export batch 42') {
        throw new RuntimeException('CSL source handoff did not normalize source metadata');
    }

    foreach ([
        '<p>Imported citation packets (Smith | Legacy Drupal export batch 42; Ng | Internet Archive snapshot) keep collection provenance visible.</p>',
        '<dt>Smith 2026</dt><dd>Source Provenance Review :: Legacy Drupal export batch 42</dd>',
        '<dt>Ng 2025</dt><dd>Imported Extract :: Internet Archive snapshot</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL source handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-source-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
