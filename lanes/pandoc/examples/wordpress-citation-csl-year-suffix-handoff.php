<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Year Suffix Review

Review cites @smith-post and later [@smith-media; @ng-2026] before the bibliography.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith-post",
    "type": "report",
    "title": "Post Import Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/post-import"
  },
  {
    "id": "smith-media",
    "type": "report",
    "title": "Media Import Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/media-import"
  },
  {
    "id": "ng-2026",
    "type": "report",
    "title": "Ng Import Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/ng-import"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Year Suffix Review</title>
    <id>https://example.test/styles/wordpress-citation-year-suffix-review</id>
    <updated>2026-06-05T07:53:00+00:00</updated>
  </info>
  <citation disambiguate-add-year-suffix="true">
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <group delimiter="">
          <date variable="issued"><date-part name="year"/></date>
          <text variable="year-suffix"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <group delimiter="">
        <date variable="issued"><date-part name="year"/></date>
        <text variable="year-suffix"/>
      </group>
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
        throw new RuntimeException('CSL year-suffix handoff did not preserve citation disambiguation metadata');
    }

    foreach ([
        '<p>Review cites Smith (2026a) and later (Smith 2026b; Ng 2026) before the bibliography.</p>',
        '<dt>Smith 2026a</dt><dd>Smith, A. 2026a. Post Import Packet. https://example.test/post-import.</dd>',
        '<dt>Smith 2026b</dt><dd>Smith, A. 2026b. Media Import Packet. https://example.test/media-import.</dd>',
        '<dt>Ng 2026</dt><dd>Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL year-suffix handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-year-suffix-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
