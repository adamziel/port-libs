<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Given Name Disambiguation Review

Review cites [@homer-2005; @bart-2005; @john-1950; @jane-1950] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "homer-2005",
    "type": "report",
    "title": "Homer Import Packet",
    "author": [
      {"family": "Simpson", "given": "Homer"}
    ],
    "issued": {"date-parts": [[2005]]},
    "URL": "https://example.test/homer-import"
  },
  {
    "id": "bart-2005",
    "type": "report",
    "title": "Bart Import Packet",
    "author": [
      {"family": "Simpson", "given": "Bart"}
    ],
    "issued": {"date-parts": [[2005]]},
    "URL": "https://example.test/bart-import"
  },
  {
    "id": "john-1950",
    "type": "report",
    "title": "John Archive Packet",
    "author": [
      {"family": "Doe", "given": "John"}
    ],
    "issued": {"date-parts": [[1950]]},
    "URL": "https://example.test/john-archive"
  },
  {
    "id": "jane-1950",
    "type": "report",
    "title": "Jane Archive Packet",
    "author": [
      {"family": "Doe", "given": "Jane"}
    ],
    "issued": {"date-parts": [[1950]]},
    "URL": "https://example.test/jane-archive"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Given Name Disambiguation Review</title>
    <id>https://example.test/styles/wordpress-citation-given-name-disambiguation-review</id>
    <updated>2026-06-06T10:42:09+00:00</updated>
  </info>
  <citation disambiguate-add-givenname="true" givenname-disambiguation-rule="by-cite">
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author">
          <name initialize-with=". "/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <date variable="issued"><date-part name="year"/></date>
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
    if (($summary['citationOptions']['disambiguateAddGivenName'] ?? null) !== true) {
        throw new RuntimeException('CSL given-name disambiguation handoff did not preserve the citation option');
    }
    if (($summary['citationOptions']['givenNameDisambiguationRule'] ?? null) !== 'by-cite') {
        throw new RuntimeException('CSL given-name disambiguation handoff did not preserve the by-cite rule');
    }

    foreach ([
        '<p>Review cites (H. Simpson 2005; B. Simpson 2005; John Doe 1950; Jane Doe 1950) before publishing imported source notes.</p>',
        '<dt>Simpson 2005</dt><dd>Simpson, H. 2005. Homer Import Packet. https://example.test/homer-import.</dd>',
        '<dt>Simpson 2005</dt><dd>Simpson, B. 2005. Bart Import Packet. https://example.test/bart-import.</dd>',
        '<dt>Doe 1950</dt><dd>Doe, J. 1950. John Archive Packet. https://example.test/john-archive.</dd>',
        '<dt>Doe 1950</dt><dd>Doe, J. 1950. Jane Archive Packet. https://example.test/jane-archive.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL given-name disambiguation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-givenname-disambiguation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
