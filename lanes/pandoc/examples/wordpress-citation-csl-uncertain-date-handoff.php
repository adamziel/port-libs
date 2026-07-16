<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Uncertain Date Review

Review cites [@uncertain-issued; @uncertain-accessed; @stable-source] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "uncertain-issued",
    "type": "report",
    "title": "Uncertain Issued Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"}
    ],
    "issued": {"date-parts": [[2026]], "uncertain": true, "raw": "2026?"},
    "accessed": {"date-parts": [[2026, 6, 5]]}
  },
  {
    "id": "uncertain-accessed",
    "type": "webpage",
    "title": "Uncertain Access Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "accessed": {"date-parts": [[2026, 6, 4]], "uncertain": true, "raw": "2026-06-04?"}
  },
  {
    "id": "stable-source",
    "type": "report",
    "title": "Stable Date Packet",
    "author": [
      {"literal": "Review Desk"}
    ],
    "issued": {"date-parts": [[2023]]},
    "accessed": {"date-parts": [[2026, 6, 1]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Uncertain Date Review</title>
    <id>https://example.test/styles/wordpress-citation-uncertain-date-review</id>
    <updated>2026-06-06T01:35:52+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if is-uncertain-date="issued" match="all">
          <group delimiter=" ">
            <names variable="author"/>
            <text value="uncertain"/>
            <date variable="issued"><date-part name="year"/></date>
          </group>
        </if>
        <else-if is-uncertain-date="accessed" match="any">
          <group delimiter=" ">
            <names variable="author"/>
            <text value="accessed?"/>
            <date variable="accessed"/>
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
          <text variable="date-marker-summary"/>
        </if>
        <else-if is-uncertain-date="accessed" match="none">
          <text value="stable access date"/>
        </else-if>
        <else>
          <text value="access date uncertain"/>
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
        throw new RuntimeException('CSL uncertain-date handoff did not preserve issued branch metadata');
    }
    if (($summary['citationRendering'][0]['branches'][1]['isUncertainDate'] ?? null) !== ['accessed']) {
        throw new RuntimeException('CSL uncertain-date handoff did not preserve accessed branch metadata');
    }
    if (($summary['bibliographyRendering'][1]['branches'][1]['match'] ?? null) !== 'none') {
        throw new RuntimeException('CSL uncertain-date handoff did not preserve none-match bibliography branch');
    }

    foreach ([
        '<p>Review cites (de la Cruz uncertain 2026; Ng accessed? 2026-06-04; Review Desk 2023) before publishing imported source notes.</p>',
        '<dt>de la Cruz 2026</dt><dd>Uncertain Issued Packet :: Date markers: issued uncertain (2026?)</dd>',
        '<dt>Ng 2025</dt><dd>Uncertain Access Packet :: access date uncertain</dd>',
        '<dt>Review Desk 2023</dt><dd>Stable Date Packet :: stable access date</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL uncertain-date handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-uncertain-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
