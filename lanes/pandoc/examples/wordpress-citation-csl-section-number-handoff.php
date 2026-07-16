<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Section Number Citation Review

Sectioned sources [@sectioned-rule; @section-range; @named-section] keep legal and newspaper sections visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "sectioned-rule",
    "type": "legislation",
    "title": "Sectioned Import Rule",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "section": "2"
  },
  {
    "id": "section-range",
    "type": "report",
    "title": "Section Range Review",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "section": "3-5"
  },
  {
    "id": "named-section",
    "type": "article-newspaper",
    "title": "Named Section Notice",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2024]]},
    "section": "metro review"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Section Number Review</title>
    <id>https://example.test/styles/wordpress-citation-section-number-review</id>
    <updated>2026-06-08T20:23:22+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <choose>
          <if is-numeric="section">
            <group delimiter=" ">
              <label variable="section" form="symbol"/>
              <number variable="section" form="ordinal"/>
              <text variable="section" form="roman" prefix="roman "/>
            </group>
          </if>
          <else>
            <text variable="section" prefix="section "/>
          </else>
        </choose>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="section" form="short"/>
        <text variable="section" form="long-ordinal"/>
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
    $children = $summary['citationRendering'][0]['children'] ?? [];
    $branches = $children[1]['branches'] ?? [];
    $sectionBranchChildren = $branches[0]['children'][0]['children'] ?? [];

    if (($branches[0]['isNumeric'] ?? null) !== ['section']) {
        throw new RuntimeException('CSL section number handoff did not preserve is-numeric metadata');
    }
    if (($sectionBranchChildren[0]['variable'] ?? null) !== 'section' || ($sectionBranchChildren[0]['form'] ?? null) !== 'symbol') {
        throw new RuntimeException('CSL section number handoff did not preserve label metadata');
    }
    if (($sectionBranchChildren[1]['variable'] ?? null) !== 'section' || ($sectionBranchChildren[1]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL section number handoff did not preserve number metadata');
    }
    if (($sectionBranchChildren[2]['variable'] ?? null) !== 'section' || ($sectionBranchChildren[2]['form'] ?? null) !== 'roman') {
        throw new RuntimeException('CSL section number handoff did not preserve text form metadata');
    }

    foreach ([
        '<p>Sectioned sources (Smith § 2nd roman ii; Ng §§ 3rd-5th roman iii-v; Roe section metro review) keep legal and newspaper sections visible.</p>',
        '<dt>Smith 2026</dt><dd>Sectioned Import Rule :: sec. second</dd>',
        '<dt>Ng 2025</dt><dd>Section Range Review :: secs. third-fifth</dd>',
        '<dt>Roe 2024</dt><dd>Named Section Notice :: sec. metro review</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL section number handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-section-number-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
