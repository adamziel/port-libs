<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Nested Citation Display Review

The review packet cites @nested-display and @offline-display before bibliography import.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "nested-display",
    "type": "report",
    "title": "Nested Display Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/nested-display"
  },
  {
    "id": "offline-display",
    "type": "report",
    "title": "Offline Display Packet",
    "author": [
      {"family": "Olsen", "given": "Ira"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Nested Citation Display Review</title>
    <id>https://example.test/styles/wordpress-nested-citation-display-review</id>
    <updated>2026-06-06T15:15:00+00:00</updated>
  </info>
  <macro name="nested-entry-line">
    <group delimiter=". ">
      <names variable="author" display="left-margin">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <group display="right-inline" delimiter=". " suffix=".">
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
      <choose>
        <if variable="URL">
          <text variable="URL" display="block" prefix="Source: "/>
        </if>
        <else>
          <text value="No source URL" display="indent"/>
        </else>
      </choose>
    </group>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography second-field-align="flush">
    <layout delimiter=" ">
      <group>
        <text macro="nested-entry-line"/>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<p>The review packet cites Ng (2026) and Olsen (2025) before bibliography import.</p>',
        '<dt>Ng 2026</dt><dd><div class="csl-entry"><div class="csl-left-margin">Ng, N.</div><div class="csl-right-inline">Nested Display Packet. 2026.</div><div class="csl-block">Source: https://example.test/nested-display</div></div></dd>',
        '<dt>Olsen 2025</dt><dd><div class="csl-entry"><div class="csl-left-margin">Olsen, I.</div><div class="csl-right-inline">Offline Display Packet. 2025.</div><div class="csl-indent">No source URL</div></div></dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Nested CSL display handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-nested-display-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
