<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Numeric Conditional Review

Review cites [@numeric-source, p. 12-14; @alpha-source, appendix A] while preserving numeric CSL branches.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "numeric-source",
    "type": "report",
    "title": "Numeric Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"}
    ],
    "issued": {"date-parts": [[2026]]},
    "number": "2 - 4",
    "page": "12-14"
  },
  {
    "id": "alpha-source",
    "type": "report",
    "title": "Alpha Packet",
    "author": [
      {"literal": "Archive Team"}
    ],
    "issued": {"date-parts": [[2025]]},
    "number": "Appendix A",
    "page": "A7"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Numeric Conditional Review</title>
    <id>https://example.test/styles/wordpress-citation-numeric-conditional-review</id>
    <updated>2026-06-06T01:07:36+00:00</updated>
  </info>
  <macro name="locator-review">
    <choose>
      <if is-numeric="locator" match="all">
        <group delimiter=" ">
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </if>
      <else>
        <text variable="locator" prefix="loc "/>
      </else>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=", ">
        <names variable="author"/>
        <text macro="locator-review"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout>
      <group delimiter=". " suffix=".">
        <text variable="title"/>
        <choose>
          <if is-numeric="number" match="any">
            <group delimiter=" ">
              <label variable="number" form="short"/>
              <number variable="number" form="ordinal"/>
            </group>
          </if>
          <else>
            <text variable="number" prefix="review number "/>
          </else>
        </choose>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['macros']['locator-review'][0]['branches'][0]['isNumeric'] ?? null) !== ['locator']) {
        throw new RuntimeException('CSL is-numeric handoff did not preserve locator branch metadata');
    }
    if (($summary['bibliographyRendering'][0]['children'][1]['branches'][0]['isNumeric'] ?? null) !== ['number']) {
        throw new RuntimeException('CSL is-numeric handoff did not preserve bibliography number branch metadata');
    }

    foreach ([
        '<p>Review cites (de la Cruz, pp. 12-14; Archive Team, loc appendix A) while preserving numeric CSL branches.</p>',
        '<dt>de la Cruz 2026</dt><dd>Numeric Packet. nos. 2nd-4th.</dd>',
        '<dt>Archive Team 2025</dt><dd>Alpha Packet. review number Appendix A.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL is-numeric handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-is-numeric-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
