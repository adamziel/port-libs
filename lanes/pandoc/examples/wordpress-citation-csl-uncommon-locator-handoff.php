<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation CSL Uncommon Locator Review

Editor notes [@uncommon-locator-source, bk. 2-3; @uncommon-locator-source, canon 4; @uncommon-locator-source, rule 5-6; @uncommon-locator-source, s.v. migration; @uncommon-locator-source, timestamp 01:02:03; @uncommon-locator-source, art. 3-4; @uncommon-locator-source, eloc 55; @uncommon-locator-source, part II] before publishing.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "uncommon-locator-source",
    "type": "report",
    "title": "WordPress Uncommon Locator Packet",
    "author": [
      {"family": "Rivera", "given": "Mica"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Uncommon Locator Review</title>
    <id>https://example.test/styles/wordpress-citation-uncommon-locator-review</id>
    <updated>2026-06-08T12:01:06+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="article-locator" form="short"><single>art.</single><multiple>arts.</multiple></term>
      <term name="book" form="short"><single>bk.</single><multiple>bks.</multiple></term>
      <term name="canon" form="short"><single>c.</single><multiple>cc.</multiple></term>
      <term name="elocation" form="short"><single>e-loc.</single><multiple>e-locs.</multiple></term>
      <term name="part" form="short"><single>pt.</single><multiple>pts.</multiple></term>
      <term name="rule" form="short"><single>r.</single><multiple>rr.</multiple></term>
      <term name="sub-verbo" form="short"><single>s.v.</single><multiple>s.vv.</multiple></term>
      <term name="timestamp" form="short"><single>ts.</single><multiple>ts.</multiple></term>
    </terms>
  </locale>
  <macro name="locator-route">
    <choose>
      <if locator="article-locator book canon elocation part rule sub-verbo timestamp" match="any">
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
    $locators = $summary['macros']['locator-route'][0]['branches'][0]['locators'] ?? null;
    if ($locators !== ['article-locator', 'book', 'canon', 'elocation', 'part', 'rule', 'sub-verbo', 'timestamp']) {
        throw new RuntimeException('CSL uncommon locator handoff did not preserve locator branch metadata');
    }

    foreach ([
        '<p>Editor notes (Rivera, extended bks. 2' . "\u{2013}" . '3; Rivera, extended c. 4; Rivera, extended rr. 5' . "\u{2013}" . '6; Rivera, extended s.v. migration; Rivera, extended ts. 01:02:03; Rivera, extended arts. 3' . "\u{2013}" . '4; Rivera, extended e-loc. 55; Rivera, extended pt. II) before publishing.</p>',
        '<dt>Rivera 2026</dt><dd>Rivera, Mica. WordPress Uncommon Locator Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL uncommon locator handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-uncommon-locator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
