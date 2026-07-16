<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Substitute Rule Review

Review cites @source-a, @source-b, and @source-c in sequence.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-a",
    "type": "report",
    "title": "First Packet",
    "author": [
      {"family": "Doe"},
      {"family": "Stevens"},
      {"family": "Miller"}
    ],
    "issued": {"date-parts": [[2004]]}
  },
  {
    "id": "source-b",
    "type": "report",
    "title": "Second Packet",
    "author": [
      {"family": "Doe"},
      {"family": "Stevens"},
      {"family": "Clark"}
    ],
    "issued": {"date-parts": [[2005]]}
  },
  {
    "id": "source-c",
    "type": "report",
    "title": "Third Packet",
    "author": [
      {"family": "Doe"},
      {"family": "Clark"}
    ],
    "issued": {"date-parts": [[2006]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Subsequent Author Rule Review</title>
    <id>https://example.test/styles/wordpress-citation-subsequent-author-rule-review</id>
    <updated>2026-06-05T18:41:52+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography subsequent-author-substitute="---" subsequent-author-substitute-rule="partial-each">
    <layout delimiter=". " suffix=".">
      <names variable="author" delimiter="; ">
        <name name-as-sort-order="all"/>
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
    if (($summary['bibliographyOptions']['subsequentAuthorSubstitute'] ?? null) !== '---') {
        throw new RuntimeException('CSL subsequent-author rule handoff did not preserve substitute text');
    }
    if (($summary['bibliographyOptions']['subsequentAuthorSubstituteRule'] ?? null) !== 'partial-each') {
        throw new RuntimeException('CSL subsequent-author rule handoff did not preserve partial-each rule');
    }

    foreach ([
        '<dt>Doe et al. 2004</dt><dd>Doe; Stevens; Miller. First Packet.</dd>',
        '<dt>Doe et al. 2005</dt><dd>---; ---; Clark. Second Packet.</dd>',
        '<dt>Doe and Clark 2006</dt><dd>---; Clark. Third Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL subsequent-author rule handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-subsequent-author-rule-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
