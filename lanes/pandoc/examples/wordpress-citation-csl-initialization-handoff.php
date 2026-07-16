<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Initials Review

Hyphenated source @hyphen-given-source and family source [@hyphen-family-source] keep initials reviewable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "hyphen-given-source",
    "type": "report",
    "title": "Hyphen Given Packet",
    "author": [
      {"given": "Jean-Luc"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "hyphen-family-source",
    "type": "report",
    "title": "Hyphen Family Packet",
    "author": [
      {"family": "Source", "given": "Jean-Luc"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Initials Review</title>
    <id>https://example.test/styles/wordpress-citation-initials-review</id>
    <updated>2026-06-05T13:18:43+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author">
          <name initialize-with=". " initialize-with-hyphen="true"/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " initialize-with-hyphen="false" name-as-sort-order="all"/>
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
    if (($summary['nameRendering']['citation']['initializeWithHyphen'] ?? null) !== true) {
        throw new RuntimeException('CSL initialize-with-hyphen default was not preserved for citations');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['initializeWithHyphen'] ?? null) !== false) {
        throw new RuntimeException('CSL initialize-with-hyphen override was not preserved for bibliography names');
    }

    foreach ([
        '<p>Hyphenated source J.-L. (2026) and family source (Source 2025) keep initials reviewable.</p>',
        '<dt>J.-L. 2026</dt><dd>J. L. Hyphen Given Packet.</dd>',
        '<dt>Source 2025</dt><dd>Source, J. L. Hyphen Family Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL initialize-with-hyphen self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-initialization-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
