<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Extended Locator Review

Extended locators [@extended-locator-source, fig. 2; @extended-locator-source, table 4-5; @extended-locator-source, appendix A; @extended-locator-source, note 7; @extended-locator-source, line 10-12] remain explicit for WordPress review.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "extended-locator-source",
    "type": "report",
    "title": "Extended Locator Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Extended Locator Review</title>
    <id>https://example.test/styles/wordpress-citation-extended-locator-review</id>
    <updated>2026-06-07T13:50:17+00:00</updated>
  </info>
  <macro name="locator-route">
    <choose>
      <if locator="appendix equation figure line note table" match="any">
        <group delimiter=" ">
          <text value="extended"/>
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </if>
      <else>
        <text variable="locator" prefix="fallback "/>
      </else>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=", ">
        <names variable="author"/>
        <text macro="locator-route"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author"/>
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
    $branches = $summary['macros']['locator-route'][0]['branches'] ?? [];
    if (($branches[0]['locators'] ?? null) !== ['appendix', 'equation', 'figure', 'line', 'note', 'table']) {
        throw new RuntimeException('CSL extended locator handoff did not preserve locator branch metadata');
    }

    foreach ([
        '<p>Extended locators (Smith, extended fig. 2; Smith, extended tbls. 4–5; Smith, extended app. A; Smith, extended n. 7; Smith, extended ll. 10–12) remain explicit for WordPress review.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Extended Locator Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL extended locator handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-extended-locator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
