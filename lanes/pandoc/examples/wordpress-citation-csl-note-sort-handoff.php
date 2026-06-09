<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Note Sort Review

First note.[^z]

Second note.[^m]

Third note.[^a]

[^z]: Zeta first [@zeta-first].

[^m]: Middle second [@middle-second].

[^a]: Alpha late [@alpha-late].
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "alpha-late",
    "type": "report",
    "title": "Alpha Late Packet",
    "author": [
      {"family": "Alpha", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2024]]}
  },
  {
    "id": "middle-second",
    "type": "report",
    "title": "Middle Second Packet",
    "author": [
      {"family": "Middle", "given": "Mia"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "zeta-first",
    "type": "report",
    "title": "Zeta First Packet",
    "author": [
      {"family": "Zeta", "given": "Zoe"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="note" default-locale="en-US">
  <info>
    <title>WordPress CSL First Reference Note Sort Review</title>
    <id>https://example.test/styles/wordpress-csl-first-reference-note-sort-review</id>
    <updated>2026-06-09T04:31:40+00:00</updated>
  </info>
  <macro name="source-key">
    <group delimiter=" ">
      <names variable="author"/>
      <date variable="issued"><date-part name="year"/></date>
    </group>
  </macro>
  <citation>
    <layout delimiter="; ">
      <group delimiter=" ">
        <number variable="citation-number" prefix="#"/>
        <text macro="source-key"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="first-reference-note-number"/>
    </sort>
    <layout delimiter=" :: ">
      <number variable="first-reference-note-number"/>
      <number variable="citation-number" prefix="#"/>
      <names variable="author"/>
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

    if (($summary['class'] ?? null) !== 'note') {
        throw new RuntimeException('CSL note sort handoff did not preserve note-style class');
    }
    if (($summary['bibliographySort'][0]['variable'] ?? null) !== 'first-reference-note-number') {
        throw new RuntimeException('CSL note sort handoff did not preserve first-reference-note-number sort metadata');
    }

    foreach ([
        '<li id="fn-1"><p>Zeta first #1 Zeta 2026.</p>',
        '<li id="fn-2"><p>Middle second #2 Middle 2025.</p>',
        '<li id="fn-3"><p>Alpha late #3 Alpha 2024.</p>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL note sort self-test missing expected note snippet: ' . $snippet);
        }
    }

    $zetaPosition = strpos($blocks, '<dt>Zeta 2026</dt><dd>1 :: #1 :: Zeta, Zoe :: Zeta First Packet</dd>');
    $middlePosition = strpos($blocks, '<dt>Middle 2025</dt><dd>2 :: #2 :: Middle, Mia :: Middle Second Packet</dd>');
    $alphaPosition = strpos($blocks, '<dt>Alpha 2024</dt><dd>3 :: #3 :: Alpha, Ada :: Alpha Late Packet</dd>');

    if (!is_int($zetaPosition) || !is_int($middlePosition) || !is_int($alphaPosition)) {
        throw new RuntimeException('CSL note sort self-test missing expected bibliography entries');
    }
    if (!($zetaPosition < $middlePosition && $middlePosition < $alphaPosition)) {
        throw new RuntimeException('CSL note sort self-test did not order bibliography entries by first note');
    }

    echo "wordpress-citation-csl-note-sort-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
