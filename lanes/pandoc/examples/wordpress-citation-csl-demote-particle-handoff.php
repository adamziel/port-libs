<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Particle Review

Particle source @van-gogh-source and peer source [@van-der-wal-source] keep non-dropping particles reviewable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "van-gogh-source",
    "type": "report",
    "title": "Van Gogh Packet",
    "author": [
      {
        "family": "Gogh",
        "given": "Vincent",
        "non-dropping-particle": "van",
        "suffix": "III",
        "comma-suffix": true
      }
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/van-gogh"
  },
  {
    "id": "van-der-wal-source",
    "type": "report",
    "title": "Van Der Wal Packet",
    "author": [
      {
        "family": "Wal",
        "given": "Willem",
        "non-dropping-particle": "van der"
      }
    ],
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/van-der-wal"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US" demote-non-dropping-particle="display-and-sort">
  <info>
    <title>WordPress Citation Particle Review</title>
    <id>https://example.test/styles/wordpress-citation-particle-review</id>
    <updated>2026-06-05T16:04:20+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="author"/>
    </sort>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
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
    if (($summary['nameRendering']['citation']['demoteNonDroppingParticle'] ?? null) !== 'display-and-sort') {
        throw new RuntimeException('CSL demote non-dropping particle handoff did not preserve citation metadata');
    }
    if (($summary['nameRendering']['bibliography']['demoteNonDroppingParticle'] ?? null) !== 'display-and-sort') {
        throw new RuntimeException('CSL demote non-dropping particle handoff did not preserve bibliography metadata');
    }

    foreach ([
        '<p>Particle source van Gogh (2026) and peer source [van der Wal 2025] keep non-dropping particles reviewable.</p>',
        '<dt>van Gogh 2026</dt><dd>Gogh, V. van, III. Van Gogh Packet. https://example.test/van-gogh.</dd>',
        '<dt>van der Wal 2025</dt><dd>Wal, W. van der. Van Der Wal Packet. https://example.test/van-der-wal.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL demote non-dropping particle self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-demote-particle-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
