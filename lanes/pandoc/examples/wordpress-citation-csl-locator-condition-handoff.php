<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Locator Conditional Review

Review cites [@locator-condition-source, p. 7; @chapter-condition-source, chap. 2; @locator-condition-source, sec. 4-5; @locator-condition-source, vol. 3] while preserving CSL locator branches.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "locator-condition-source",
    "type": "report",
    "title": "Locator Condition Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "chapter-condition-source",
    "type": "book",
    "title": "Chapter Locator Packet",
    "author": [
      {"literal": "Archive Team"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Locator Conditional Review</title>
    <id>https://example.test/styles/wordpress-citation-locator-conditional-review</id>
    <updated>2026-06-06T06:23:40+00:00</updated>
  </info>
  <macro name="locator-route">
    <choose>
      <if locator="chapter" match="any">
        <group delimiter=" ">
          <text value="chapter-route"/>
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </if>
      <else-if locator="section paragraph" match="any">
        <group delimiter=" ">
          <text value="section-route"/>
          <label variable="locator" form="symbol"/>
          <text variable="locator"/>
        </group>
      </else-if>
      <else-if locator="page" match="any">
        <group delimiter=" ">
          <text value="page-route"/>
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </else-if>
      <else>
        <text variable="locator" prefix="other-locator "/>
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
    if (($branches[0]['locators'] ?? null) !== ['chapter']) {
        throw new RuntimeException('CSL locator condition handoff did not preserve chapter branch metadata');
    }
    if (($branches[1]['locators'] ?? null) !== ['section', 'paragraph']) {
        throw new RuntimeException('CSL locator condition handoff did not preserve section branch metadata');
    }
    if (($branches[2]['locators'] ?? null) !== ['page']) {
        throw new RuntimeException('CSL locator condition handoff did not preserve page branch metadata');
    }

    foreach ([
        '<p>Review cites (de la Cruz, page-route p. 7; Archive Team, chapter-route chap. 2; de la Cruz, section-route §§ 4-5; de la Cruz, other-locator 3) while preserving CSL locator branches.</p>',
        '<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria. Locator Condition Packet.</dd>',
        '<dt>Archive Team 2025</dt><dd>Archive Team. Chapter Locator Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL locator condition handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-locator-condition-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
