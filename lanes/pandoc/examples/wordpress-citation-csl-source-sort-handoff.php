<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Source Sort Review

Reviewer packets cite [@late-source; @early-source; @middle-source] while preserving source-provenance sort order.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "late-source",
    "type": "report",
    "title": "Late Imported Packet",
    "source": "zeta import queue",
    "author": [
      {"family": "Zed", "given": "Zoe"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "early-source",
    "type": "report",
    "title": "Early Imported Packet",
    "source": "alpha import queue",
    "author": [
      {"family": "Adams", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "middle-source",
    "type": "report",
    "title": "Middle Imported Packet",
    "source": "middle import queue",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Source Sort Review</title>
    <id>https://example.test/styles/wordpress-csl-source-sort-review</id>
    <updated>2026-06-08T23:31:35+00:00</updated>
  </info>
  <citation>
    <sort>
      <key variable="source"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <number variable="citation-number"/>
        <names variable="author"/>
        <text variable="source"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="source"/>
      <key variable="title"/>
    </sort>
    <layout delimiter=" :: ">
      <number variable="citation-number"/>
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

    if (($summary['citationSort'][0]['variable'] ?? null) !== 'source') {
        throw new RuntimeException('CSL source-sort handoff did not preserve citation source sort metadata');
    }
    if (($summary['bibliographySort'][0]['variable'] ?? null) !== 'source') {
        throw new RuntimeException('CSL source-sort handoff did not preserve bibliography source sort metadata');
    }

    foreach ([
        '<p>Reviewer packets cite [1 | Adams | alpha import queue; 2 | Ng | middle import queue; 3 | Zed | zeta import queue] while preserving source-provenance sort order.</p>',
        '<dt>Adams 2025</dt><dd>1 :: Early Imported Packet :: alpha import queue</dd>',
        '<dt>Ng 2024</dt><dd>2 :: Middle Imported Packet :: middle import queue</dd>',
        '<dt>Zed 2026</dt><dd>3 :: Late Imported Packet :: zeta import queue</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL source-sort self-test missing expected snippet: ' . $snippet);
        }
    }

    $early = strpos($blocks, '<dt>Adams 2025</dt>');
    $middle = strpos($blocks, '<dt>Ng 2024</dt>');
    $late = strpos($blocks, '<dt>Zed 2026</dt>');

    if (!(is_int($early) && is_int($middle) && is_int($late) && $early < $middle && $middle < $late)) {
        throw new RuntimeException('CSL source-sort handoff did not preserve sorted bibliography order');
    }

    echo "wordpress-citation-csl-source-sort-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
