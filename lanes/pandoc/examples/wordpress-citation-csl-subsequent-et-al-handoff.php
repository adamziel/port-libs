<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Subsequent Et Al Review

Repeated source @team-source returns as [@team-source] for reviewer follow-up.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "team-source",
    "type": "report",
    "title": "Repeated Team Source Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Okafor", "given": "Ola"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/repeated-team-source"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Subsequent Et Al Review</title>
    <id>https://example.test/styles/wordpress-citation-subsequent-et-al-review</id>
    <updated>2026-06-05T15:01:44+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", " et-al-min="4" et-al-use-first="3" et-al-subsequent-min="3" et-al-subsequent-use-first="1">
          <name/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="first"/>
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
    if (($summary['nameRendering']['citation']['etAlSubsequentMin'] ?? null) !== 3) {
        throw new RuntimeException('CSL subsequent et-al handoff did not preserve et-al-subsequent-min');
    }
    if (($summary['nameRendering']['citation']['etAlSubsequentUseFirst'] ?? null) !== 1) {
        throw new RuntimeException('CSL subsequent et-al handoff did not preserve et-al-subsequent-use-first');
    }

    foreach ([
        '<p>Repeated source de la Cruz, Ng, and Okafor (2026) returns as (de la Cruz et al. 2026) for reviewer follow-up.</p>',
        '<dt>de la Cruz, Ng, and Okafor 2026</dt><dd>de la Cruz, A. M.; N. Ng; O. Okafor. Repeated Team Source Packet. https://example.test/repeated-team-source.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL subsequent et-al self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-subsequent-et-al-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
