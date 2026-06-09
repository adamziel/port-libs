<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Bibliography Options Review

Review packets cite [@options-source; @options-followup] before bibliography metadata is handed off.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "options-source",
    "type": "report",
    "title": "Options Source Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "options-followup",
    "type": "report",
    "title": "Options Followup Packet",
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
    <title>WordPress Bibliography Options Review</title>
    <id>https://example.test/styles/wordpress-bibliography-options-review</id>
    <updated>2026-06-09T01:19:50+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography hanging-indent="true" entry-spacing="0" line-spacing="2" second-field-align="margin">
    <sort>
      <key variable="issued" sort="descending"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['bibliographyOptions']['secondFieldAlign'] ?? null) !== 'margin') {
        throw new RuntimeException('CSL bibliography options handoff did not preserve second-field-align metadata');
    }
    if (($summary['bibliographyOptions']['entrySpacing'] ?? null) !== 0 || ($summary['bibliographyOptions']['lineSpacing'] ?? null) !== 2) {
        throw new RuntimeException('CSL bibliography options handoff did not preserve spacing metadata');
    }

    foreach ([
        '<p>Review packets cite (Smith 2026; Ng 2025) before bibliography metadata is handed off.</p>',
        '<dl class="pandoc-csl-bibliography" data-csl-hanging-indent="true" data-csl-entry-spacing="0" data-csl-line-spacing="2" data-csl-second-field-align="margin">',
        '<dt>Smith 2026</dt><dd>Options Source Packet :: 2026</dd>',
        '<dt>Ng 2025</dt><dd>Options Followup Packet :: 2025</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL bibliography options self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-bibliography-options-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
