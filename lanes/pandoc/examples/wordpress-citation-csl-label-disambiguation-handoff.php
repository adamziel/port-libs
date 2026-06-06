<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Label Disambiguation Review

Labelled review packets [@label-post; @label-media] keep source labels stable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "label-post",
    "type": "report",
    "title": "Post Import Packet",
    "citation-label": "WP-POST",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/post-import"
  },
  {
    "id": "label-media",
    "type": "report",
    "title": "Media Import Packet",
    "citation-label": "WP-MEDIA",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/media-import"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Label Disambiguation Review</title>
    <id>https://example.test/styles/wordpress-citation-label-disambiguation-review</id>
    <updated>2026-06-06T11:49:46+00:00</updated>
  </info>
  <citation disambiguate-add-year-suffix="true">
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter="">
        <text variable="citation-label"/>
        <text variable="year-suffix"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="citation-label"/>
      <text variable="year-suffix"/>
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
    if (($summary['citationOptions']['disambiguateAddYearSuffix'] ?? null) !== true) {
        throw new RuntimeException('CSL label disambiguation handoff did not preserve year-suffix option metadata');
    }
    if (($summary['citationRendering'][0]['children'][0]['variable'] ?? null) !== 'citation-label') {
        throw new RuntimeException('CSL label disambiguation handoff did not preserve citation-label rendering metadata');
    }

    foreach ([
        '<p>Labelled review packets [WP-POST; WP-MEDIA] keep source labels stable.</p>',
        '<dt>WP-POST</dt><dd>WP-POST :: Post Import Packet :: https://example.test/post-import</dd>',
        '<dt>WP-MEDIA</dt><dd>WP-MEDIA :: Media Import Packet :: https://example.test/media-import</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL label disambiguation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-label-disambiguation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
