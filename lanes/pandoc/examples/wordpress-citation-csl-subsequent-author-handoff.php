<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Substitute Review

Review cites @smith-post and @smith-media before @ng-2026.
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
    "issued": {"date-parts": [[2024]]},
    "URL": "https://example.test/post-import"
  },
  {
    "id": "smith-media",
    "type": "report",
    "title": "Media Import Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2025]]},
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
    <title>WordPress Citation Subsequent Author Review</title>
    <id>https://example.test/styles/wordpress-citation-subsequent-author-review</id>
    <updated>2026-06-05T10:04:51+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography subsequent-author-substitute="---" subsequent-author-substitute-rule="complete-all">
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
    if (($summary['bibliographyOptions']['subsequentAuthorSubstitute'] ?? null) !== '---') {
        throw new RuntimeException('CSL subsequent-author handoff did not preserve substitute text');
    }
    if (($summary['bibliographyOptions']['subsequentAuthorSubstituteRule'] ?? null) !== 'complete-all') {
        throw new RuntimeException('CSL subsequent-author handoff did not preserve substitute rule');
    }

    foreach ([
        '<p>Review cites Smith (2024) and Smith (2025) before Ng (2026).</p>',
        '<dt>Smith 2024</dt><dd>Smith, A. 2024. Post Import Packet. https://example.test/post-import.</dd>',
        '<dt>Smith 2025</dt><dd>---. 2025. Media Import Packet. https://example.test/media-import.</dd>',
        '<dt>Ng 2026</dt><dd>Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL subsequent-author handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-subsequent-author-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
