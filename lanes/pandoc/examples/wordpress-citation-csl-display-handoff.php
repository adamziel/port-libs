<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Display Review

The source packet cites @source-packet before the bibliography is reviewed.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-packet",
    "type": "report",
    "title": "Source Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"}
    ],
    "issued": {"date-parts": [[2026]]},
    "note": "Attachment needs review.",
    "URL": "https://example.test/source-packet"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Display Review</title>
    <id>https://example.test/styles/wordpress-citation-display-review</id>
    <updated>2026-06-05T07:25:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <layout delimiter=" ">
      <text variable="citation-key" display="left-margin" prefix="[" suffix="]"/>
      <group display="right-inline" delimiter=". " suffix=".">
        <names variable="author">
          <name initialize-with=". " name-as-sort-order="all"/>
        </names>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
      <text variable="note" display="indent" prefix="Review note: "/>
      <text variable="URL" display="block" prefix="Source: "/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['bibliographyRendering'][0]['display'] ?? null) !== 'left-margin') {
        throw new RuntimeException('CSL display handoff did not preserve left-margin metadata');
    }
    if (($summary['bibliographyRendering'][1]['display'] ?? null) !== 'right-inline') {
        throw new RuntimeException('CSL display handoff did not preserve right-inline metadata');
    }
    foreach ([
        '<p>The source packet cites de la Cruz (2026) before the bibliography is reviewed.</p>',
        '<dt>de la Cruz 2026</dt><dd><div class="csl-entry"><div class="csl-left-margin">[source-packet]</div><div class="csl-right-inline">de la Cruz, A. M. Source Packet. 2026.</div><div class="csl-indent">Review note: Attachment needs review.</div><div class="csl-block">Source: https://example.test/source-packet</div></div></dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL display handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-display-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
