<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation CSL Contextual Label Review

Section labels [@multi-section; @single-section] stay contextual for import review.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "locator-list",
    "type": "report",
    "title": "Locator List Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2024]]}
  },
  {
    "id": "multi-section",
    "type": "report",
    "title": "Multi Section Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]},
    "section": "front matter; migration notes"
  },
  {
    "id": "single-section",
    "type": "article-newspaper",
    "title": "Single Section Notice",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2025]]},
    "section": "metro review"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Label Pluralization Review</title>
    <id>https://example.test/styles/wordpress-citation-label-pluralization-review</id>
    <updated>2026-06-09T01:32:29+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued">
          <date-part name="year"/>
        </date>
        <group delimiter=" ">
          <label variable="locator" form="short" plural="contextual"/>
          <text variable="locator"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="section" form="short" plural="contextual"/>
        <text variable="section"/>
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
    $citationLocatorLabel = $summary['citationRendering'][0]['children'][2]['children'][0] ?? [];
    $bibliographySectionLabel = $summary['bibliographyRendering'][1]['children'][0] ?? [];
    if (($citationLocatorLabel['plural'] ?? null) !== 'contextual') {
        throw new RuntimeException('CSL label pluralization handoff did not preserve contextual citation label metadata');
    }
    if (($bibliographySectionLabel['plural'] ?? null) !== 'contextual') {
        throw new RuntimeException('CSL label pluralization handoff did not preserve contextual bibliography label metadata');
    }

    $locatorCluster = $processor->renderCitationCluster([
        new AstNode('citation', ['id' => 'locator-list', 'text' => '[@locator-list]', 'locatorLabel' => 'sub-verbo', 'locatorValue' => 'migration; media']),
        new AstNode('citation', ['id' => 'locator-list', 'text' => '[@locator-list]', 'locatorLabel' => 'sub-verbo', 'locatorValue' => 'migration']),
        new AstNode('citation', ['id' => 'locator-list', 'text' => '[@locator-list]', 'locatorLabel' => 'page', 'locatorValue' => '3; 4']),
    ]);
    if ($locatorCluster !== '(Smith 2024 s.vv. migration; media; Smith 2024 s.v. migration; Smith 2024 pp. 3; 4)') {
        throw new RuntimeException('CSL label pluralization handoff rendered locator labels incorrectly: ' . $locatorCluster);
    }

    foreach ([
        '<p>Section labels (Ng 2026; Roe 2025) stay contextual for import review.</p>',
        '<dt>Ng 2026</dt><dd>Multi Section Packet :: secs. front matter; migration notes</dd>',
        '<dt>Roe 2025</dt><dd>Single Section Notice :: sec. metro review</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL label pluralization self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-label-pluralization-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
