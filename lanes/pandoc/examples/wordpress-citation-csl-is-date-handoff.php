<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Date Condition Review

Date routes [@full-date-packet; @issued-packet; @original-packet; @undated-packet] remain visible for source review.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "full-date-packet",
    "type": "report",
    "title": "Full Date Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026, 6, 5]]},
    "accessed": {"date-parts": [[2026, 6, 8]]}
  },
  {
    "id": "issued-packet",
    "type": "report",
    "title": "Issued Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "original-packet",
    "type": "manuscript",
    "title": "Original Packet",
    "author": [
      {"literal": "Archive Desk"}
    ],
    "original-date": {"literal": "undated source letter"}
  },
  {
    "id": "undated-packet",
    "type": "report",
    "title": "Undated Packet",
    "author": [
      {"literal": "Review Desk"}
    ]
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Date Condition Review</title>
    <id>https://example.test/styles/wordpress-citation-date-condition-review</id>
    <updated>2026-06-08T16:07:14+00:00</updated>
  </info>
  <macro name="date-route">
    <choose>
      <if is-date="issued accessed" match="all">
        <text value="both-dates"/>
      </if>
      <else-if is-date="issued original-date" match="any">
        <text value="source-date"/>
      </else-if>
      <else-if is-date="issued accessed original-date" match="none">
        <text value="no-date"/>
      </else-if>
    </choose>
  </macro>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text macro="date-route"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text macro="date-route"/>
      <date variable="original-date"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $branches = $summary['macros']['date-route'][0]['branches'] ?? [];
    if (($branches[0]['isDate'] ?? null) !== ['issued', 'accessed']) {
        throw new RuntimeException('CSL is-date handoff did not preserve all-match issued/accessed metadata');
    }
    if (($branches[1]['isDate'] ?? null) !== ['issued', 'original-date']) {
        throw new RuntimeException('CSL is-date handoff did not preserve any-match issued/original-date metadata');
    }
    if (($branches[2]['match'] ?? null) !== 'none') {
        throw new RuntimeException('CSL is-date handoff did not preserve none-match branch metadata');
    }

    foreach ([
        '<p>Date routes [Full Date Packet | both-dates; Issued Packet | source-date; Original Packet | source-date; Undated Packet | no-date] remain visible for source review.</p>',
        '<dt>Smith 2026</dt><dd>Full Date Packet :: both-dates</dd>',
        '<dt>Ng 2025</dt><dd>Issued Packet :: source-date</dd>',
        '<dt>Archive Desk n.d.</dt><dd>Original Packet :: source-date :: undated source letter</dd>',
        '<dt>Review Desk n.d.</dt><dd>Undated Packet :: no-date</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL is-date handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-is-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
