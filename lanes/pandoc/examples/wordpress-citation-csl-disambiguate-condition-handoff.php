<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Disambiguate Condition Review

Review cites [@smith-post; @smith-media; @ng-2026] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith-post",
    "type": "report",
    "title": "Post Import Packet",
    "short-title": "Post",
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
    "short-title": "Media",
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
    "short-title": "Ng",
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
    <title>WordPress Citation Disambiguate Condition Review</title>
    <id>https://example.test/styles/wordpress-citation-disambiguate-condition-review</id>
    <updated>2026-06-06T10:12:51+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <choose>
          <if disambiguate="true">
            <text variable="title" form="short" prefix="[" suffix="]"/>
          </if>
        </choose>
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
    if (($summary['citationRendering'][0]['children'][1]['branches'][0]['disambiguate'] ?? null) !== true) {
        throw new RuntimeException('CSL disambiguate condition handoff did not preserve branch metadata');
    }

    foreach ([
        '<p>Review cites (Smith [Post] 2026; Smith [Media] 2026; Ng 2026) before publishing imported source notes.</p>',
        '<dt>Smith 2026</dt><dd>Smith, A. 2026. Post Import Packet. https://example.test/post-import.</dd>',
        '<dt>Smith 2026</dt><dd>Smith, A. 2026. Media Import Packet. https://example.test/media-import.</dd>',
        '<dt>Ng 2026</dt><dd>Ng, N. 2026. Ng Import Packet. https://example.test/ng-import.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL disambiguate condition self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-disambiguate-condition-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
