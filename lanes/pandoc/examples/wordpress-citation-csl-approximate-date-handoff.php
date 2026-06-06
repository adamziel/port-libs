<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Approximate Date Review

Review cites [@circa-issued; @circa-accessed; @stable-source] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "circa-issued",
    "type": "report",
    "title": "Approximate Issued Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]], "circa": true, "raw": "2026~"}
  },
  {
    "id": "circa-accessed",
    "type": "webpage",
    "title": "Approximate Access Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "accessed": {"date-parts": [[2026, 6, 6]], "circa": true, "raw": "2026-06-06~"},
    "URL": "https://example.test/circa-accessed"
  },
  {
    "id": "stable-source",
    "type": "report",
    "title": "Stable Source Packet",
    "author": [
      {"literal": "Review Desk"}
    ],
    "issued": {"date-parts": [[2024]]},
    "accessed": {"date-parts": [[2026, 6, 1]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Approximate Date Review</title>
    <id>https://example.test/styles/wordpress-citation-approximate-date-review</id>
    <updated>2026-06-06T05:18:02+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if is-uncertain-date="issued" match="all">
          <group delimiter=" ">
            <names variable="author"/>
            <group delimiter="">
              <text term="circa" form="short" suffix=" "/>
              <date variable="issued"><date-part name="year"/></date>
            </group>
          </group>
        </if>
        <else-if is-uncertain-date="accessed" match="any">
          <group delimiter=" ">
            <names variable="author"/>
            <text value="accessed"/>
            <group delimiter="">
              <text term="circa" form="short" suffix=" "/>
              <date variable="accessed"/>
            </group>
          </group>
        </else-if>
        <else>
          <group delimiter=" ">
            <names variable="author"/>
            <date variable="issued"><date-part name="year"/></date>
          </group>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <choose>
        <if is-uncertain-date="issued" match="any">
          <group delimiter="">
            <text term="circa" form="short" suffix=" "/>
            <date variable="issued"/>
          </group>
        </if>
        <else-if is-uncertain-date="accessed" match="any">
          <text variable="date-marker-summary"/>
        </else-if>
        <else>
          <text value="stable date"/>
        </else>
      </choose>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['branches'][0]['isUncertainDate'] ?? null) !== ['issued']) {
        throw new RuntimeException('CSL approximate-date handoff did not preserve issued uncertain-date branch metadata');
    }
    if (($summary['citationRendering'][0]['branches'][1]['isUncertainDate'] ?? null) !== ['accessed']) {
        throw new RuntimeException('CSL approximate-date handoff did not preserve accessed uncertain-date branch metadata');
    }

    foreach ([
        '<p>Review cites (Smith c. 2026; Ng accessed c. 2026-06-06; Review Desk 2024) before publishing imported source notes.</p>',
        '<dt>Smith 2026</dt><dd>Approximate Issued Packet :: c. 2026</dd>',
        '<dt>Ng 2025</dt><dd>Approximate Access Packet :: Date markers: accessed circa (2026-06-06~)</dd>',
        '<dt>Review Desk 2024</dt><dd>Stable Source Packet :: stable date</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL approximate-date handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-approximate-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
