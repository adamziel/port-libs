<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation CSL Count Label Review

Count labels [@page-count-one; @page-count-many; @page-count-range] stay contextual for import review.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "page-count-one",
    "type": "report",
    "title": "Single Page Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "number-of-pages": "1",
    "number-of-volumes": "1",
    "volume": "2",
    "chapter-number": "7"
  },
  {
    "id": "page-count-many",
    "type": "report",
    "title": "Long Review Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "number-of-pages": "20",
    "number-of-volumes": "4"
  },
  {
    "id": "page-count-range",
    "type": "report",
    "title": "Range Review Packet",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2024]]},
    "number-of-pages": "11-12",
    "number-of-volumes": "2-3"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Count Label Review</title>
    <id>https://example.test/styles/wordpress-citation-count-label-review</id>
    <updated>2026-06-09T03:09:46+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <group delimiter=" ">
          <label variable="number-of-pages" form="short" plural="contextual"/>
          <number variable="number-of-pages"/>
        </group>
        <group delimiter=" ">
          <label variable="number-of-volumes" form="short" plural="contextual"/>
          <number variable="number-of-volumes"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="number-of-pages" form="short" plural="contextual"/>
        <text variable="number-of-pages"/>
      </group>
      <group delimiter=" ">
        <label variable="number-of-volumes" form="short" plural="contextual"/>
        <text variable="number-of-volumes"/>
      </group>
      <group delimiter=" ">
        <label variable="volume" form="short" plural="contextual"/>
        <number variable="volume"/>
      </group>
      <group delimiter=" ">
        <label variable="chapter-number" form="short" plural="contextual"/>
        <number variable="chapter-number"/>
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
    $citationPagesLabel = $summary['citationRendering'][0]['children'][1]['children'][0] ?? [];
    $citationVolumesLabel = $summary['citationRendering'][0]['children'][2]['children'][0] ?? [];
    $bibliographyPagesLabel = $summary['bibliographyRendering'][1]['children'][0] ?? [];
    $bibliographyVolumesLabel = $summary['bibliographyRendering'][2]['children'][0] ?? [];
    if (($citationPagesLabel['plural'] ?? null) !== 'contextual') {
        throw new RuntimeException('CSL count label handoff did not preserve contextual page-count label metadata');
    }
    if (($citationVolumesLabel['plural'] ?? null) !== 'contextual') {
        throw new RuntimeException('CSL count label handoff did not preserve contextual volume-count label metadata');
    }
    if (($bibliographyPagesLabel['variable'] ?? null) !== 'number-of-pages') {
        throw new RuntimeException('CSL count label handoff did not preserve bibliography page-count label variable');
    }
    if (($bibliographyVolumesLabel['variable'] ?? null) !== 'number-of-volumes') {
        throw new RuntimeException('CSL count label handoff did not preserve bibliography volume-count label variable');
    }

    $cluster = $processor->renderCitationCluster([
        new AstNode('citation', ['id' => 'page-count-one', 'text' => '[@page-count-one]']),
        new AstNode('citation', ['id' => 'page-count-many', 'text' => '[@page-count-many]']),
        new AstNode('citation', ['id' => 'page-count-range', 'text' => '[@page-count-range]']),
    ]);
    if ($cluster !== '(Smith p. 1 vol. 1; Ng pp. 20 vols. 4; Roe pp. 11-12 vols. 2-3)') {
        throw new RuntimeException('CSL count label handoff rendered count labels incorrectly: ' . $cluster);
    }

    foreach ([
        '<p>Count labels (Smith p. 1 vol. 1; Ng pp. 20 vols. 4; Roe pp. 11-12 vols. 2-3) stay contextual for import review.</p>',
        '<dt>Smith 2026</dt><dd>Single Page Packet :: p. 1 :: vol. 1 :: vol. 2 :: chap. 7</dd>',
        '<dt>Ng 2025</dt><dd>Long Review Packet :: pp. 20 :: vols. 4</dd>',
        '<dt>Roe 2024</dt><dd>Range Review Packet :: pp. 11-12 :: vols. 2-3</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL count label self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-count-label-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
