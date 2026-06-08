<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Numeric Text Variable Citation Review

Initial source note.[^a]

Bridge source note.[^b]

Repeated source note.[^c]

[^a]: Initial footnote cites [@source-a].

[^b]: Bridge footnote cites [@source-b].

[^c]: Repeated footnote cites [@source-a, p. 9].
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-a",
    "type": "report",
    "title": "Numeric Text Source A",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "page": "12, 18 & 20",
    "edition": "3",
    "number": "2-4"
  },
  {
    "id": "source-b",
    "type": "report",
    "title": "Numeric Text Source B",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "page": "A7",
    "edition": "Second",
    "number": "Review 8"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="note" default-locale="en-US">
  <info>
    <title>WordPress Citation Numeric Text Variable Review</title>
    <id>https://example.test/styles/wordpress-citation-numeric-text-variable-review</id>
    <updated>2026-06-08T13:18:04+00:00</updated>
  </info>
  <macro name="source-key">
    <group delimiter=" ">
      <names variable="author"/>
      <date variable="issued"><date-part name="year"/></date>
    </group>
  </macro>
  <citation>
    <layout delimiter="; ">
      <choose>
        <if position="subsequent" match="any">
          <group delimiter=" ">
            <text value="first-note"/>
            <text variable="first-reference-note-number" form="long-ordinal"/>
            <text variable="locator" form="ordinal" prefix="locator "/>
            <text variable="edition" form="roman" prefix="edition "/>
          </group>
        </if>
        <else>
          <text macro="source-key"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <text variable="title"/>
      <group delimiter=" ">
        <text value="pages"/>
        <text variable="page" form="roman"/>
      </group>
      <group delimiter=" ">
        <text value="edition"/>
        <text variable="edition" form="long-ordinal"/>
      </group>
      <group delimiter=" ">
        <text value="number"/>
        <text variable="number" form="ordinal"/>
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
    $subsequentChildren = $summary['citationRendering'][0]['branches'][0]['children'][0]['children'] ?? [];
    if (($subsequentChildren[1]['variable'] ?? null) !== 'first-reference-note-number' || ($subsequentChildren[1]['form'] ?? null) !== 'long-ordinal') {
        throw new RuntimeException('CSL numeric text form handoff did not preserve first-reference-note-number metadata');
    }
    if (($subsequentChildren[2]['variable'] ?? null) !== 'locator' || ($subsequentChildren[2]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL numeric text form handoff did not preserve locator metadata');
    }
    if (($summary['bibliographyRendering'][1]['children'][1]['form'] ?? null) !== 'roman') {
        throw new RuntimeException('CSL numeric text form handoff did not preserve bibliography page metadata');
    }

    foreach ([
        '<li id="fn-1"><p>Initial footnote cites Smith 2026.</p>',
        '<li id="fn-3"><p>Repeated footnote cites first-note first locator 9th edition iii.</p>',
        '<dt>Smith 2026</dt><dd>Numeric Text Source A. pages xii, xviii &amp; xx. edition third. number 2nd-4th.</dd>',
        '<dt>Ng 2025</dt><dd>Numeric Text Source B. pages A7. edition Second. number Review 8.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL numeric text form handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-numeric-text-form-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
