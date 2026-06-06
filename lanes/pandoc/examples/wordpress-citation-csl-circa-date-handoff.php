<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Circa Date Review

Review cites [@circa-issued; @uncertain-issued; @circa-accessed; @stable-source] before publishing imported source notes.
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
    "issued": {"date-parts": [[2026]], "circa": true, "raw": "2026~"},
    "accessed": {"date-parts": [[2026, 6, 5]]}
  },
  {
    "id": "uncertain-issued",
    "type": "report",
    "title": "Uncertain Issued Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"}
    ],
    "issued": {"date-parts": [[2025]], "uncertain": true, "raw": "2025?"},
    "accessed": {"date-parts": [[2026, 6, 4]]}
  },
  {
    "id": "circa-accessed",
    "type": "webpage",
    "title": "Approximate Access Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2024]]},
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
    "issued": {"date-parts": [[2023]]},
    "accessed": {"date-parts": [[2026, 6, 1]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Circa Date Review</title>
    <id>https://example.test/styles/wordpress-citation-circa-date-review</id>
    <updated>2026-06-06T14:08:12+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <choose>
        <if is-circa-date="issued" match="all">
          <group delimiter=" ">
            <names variable="author"/>
            <text value="circa-issued"/>
            <date variable="issued"><date-part name="year"/></date>
          </group>
        </if>
        <else-if is-circa-date="accessed" match="any">
          <group delimiter=" ">
            <names variable="author"/>
            <text value="circa-accessed"/>
            <date variable="accessed"/>
          </group>
        </else-if>
        <else-if is-uncertain-date="issued" match="any">
          <group delimiter=" ">
            <names variable="author"/>
            <text value="uncertain-only"/>
            <date variable="issued"><date-part name="year"/></date>
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
        <if is-circa-date="issued" match="any">
          <text variable="date-marker-summary"/>
        </if>
        <else-if is-circa-date="accessed" match="any">
          <text value="access circa"/>
        </else-if>
        <else-if is-circa-date="issued accessed" match="none">
          <text value="no circa markers"/>
        </else-if>
        <else>
          <text value="circa fallback"/>
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
    if (($summary['citationRendering'][0]['branches'][0]['isCircaDate'] ?? null) !== ['issued']) {
        throw new RuntimeException('CSL circa-date handoff did not preserve issued is-circa-date branch metadata');
    }
    if (($summary['citationRendering'][0]['branches'][1]['isCircaDate'] ?? null) !== ['accessed']) {
        throw new RuntimeException('CSL circa-date handoff did not preserve accessed is-circa-date branch metadata');
    }
    if (($summary['citationRendering'][0]['branches'][2]['isUncertainDate'] ?? null) !== ['issued']) {
        throw new RuntimeException('CSL circa-date handoff did not keep uncertain-date fallback branch metadata');
    }

    foreach ([
        '<p>Review cites (Smith circa-issued 2026; de la Cruz uncertain-only 2025; Ng circa-accessed 2026-06-06; Review Desk 2023) before publishing imported source notes.</p>',
        '<dt>Smith 2026</dt><dd>Approximate Issued Packet :: Date markers: issued circa (2026~)</dd>',
        '<dt>de la Cruz 2025</dt><dd>Uncertain Issued Packet :: no circa markers</dd>',
        '<dt>Ng 2024</dt><dd>Approximate Access Packet :: access circa</dd>',
        '<dt>Review Desk 2023</dt><dd>Stable Source Packet :: no circa markers</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL circa-date handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-circa-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
