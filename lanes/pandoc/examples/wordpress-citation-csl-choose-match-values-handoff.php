<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Choose Match Values Review

Choose conditions [@book-packet, p. 7; @article-packet, sec. 2; @web-packet, vol. 1] keep all/any/none branch semantics.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "book-packet",
    "type": "book",
    "title": "Book Source Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "article-packet",
    "type": "article-journal",
    "title": "Article Source Packet",
    "author": [
      {"family": "Doe", "given": "Jane"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "web-packet",
    "type": "webpage",
    "title": "Web Source Packet",
    "author": [
      {"literal": "Archive Desk"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress Citation Choose Match Values Review</title>
    <id>https://example.test/styles/wordpress-citation-choose-match-values-review</id>
    <updated>2026-06-07T02:58:15+00:00</updated>
  </info>
  <macro name="type-route">
    <choose>
      <if type="book article-journal">
        <text value="type-all"/>
      </if>
      <else-if type="book article-journal" match="any">
        <text value="type-any"/>
      </else-if>
      <else-if type="book article-journal" match="none">
        <text value="type-none"/>
      </else-if>
    </choose>
  </macro>
  <macro name="locator-route">
    <choose>
      <if locator="page section">
        <group delimiter=" ">
          <text value="locator-all"/>
          <text variable="locator"/>
        </group>
      </if>
      <else-if locator="page section" match="any">
        <group delimiter=" ">
          <text value="locator-any"/>
          <text variable="locator"/>
        </group>
      </else-if>
      <else-if locator="page section" match="none">
        <group delimiter=" ">
          <text value="locator-none"/>
          <text variable="locator"/>
        </group>
      </else-if>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="type-route"/>
        <text macro="locator-route"/>
        <names variable="author"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". ">
      <group delimiter=" | ">
        <text macro="type-route"/>
        <text variable="title"/>
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
    if (($summary['macros']['type-route'][0]['branches'][0]['match'] ?? null) !== 'all') {
        throw new RuntimeException('CSL choose match-values handoff did not preserve default all-match type metadata');
    }
    if (($summary['macros']['type-route'][0]['branches'][0]['types'] ?? null) !== ['book', 'article-journal']) {
        throw new RuntimeException('CSL choose match-values handoff did not preserve type list metadata');
    }
    if (($summary['macros']['type-route'][0]['branches'][1]['match'] ?? null) !== 'any') {
        throw new RuntimeException('CSL choose match-values handoff did not preserve any-match type metadata');
    }
    if (($summary['macros']['locator-route'][0]['branches'][0]['locators'] ?? null) !== ['page', 'section']) {
        throw new RuntimeException('CSL choose match-values handoff did not preserve locator list metadata');
    }

    foreach ([
        '<p>Choose conditions (type-any | locator-any 7 | Smith; type-any | locator-any 2 | Doe; type-none | locator-none 1 | Archive Desk) keep all/any/none branch semantics.</p>',
        '<dt>Smith 2026</dt><dd>type-any | Book Source Packet</dd>',
        '<dt>Archive Desk 2024</dt><dd>type-none | Web Source Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL choose match-values handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-choose-match-values-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
