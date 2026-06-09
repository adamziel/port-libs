<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Supplement Number Citation Review

Supplemented imports [@supplement-source; @supplement-range; @named-supplement] keep source supplement numbers visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "supplement-source",
    "type": "report",
    "title": "Migration Supplement Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "supplement": "2"
  },
  {
    "id": "supplement-range",
    "type": "report",
    "title": "Range Supplement Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "supplement": "3-4"
  },
  {
    "id": "named-supplement",
    "type": "report",
    "title": "Named Supplement Packet",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2024]]},
    "supplement": "appendix packet"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Supplement Number Review</title>
    <id>https://example.test/styles/wordpress-citation-supplement-number-review</id>
    <updated>2026-06-09T01:59:28+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <choose>
          <if is-numeric="supplement">
            <group delimiter=" ">
              <label variable="supplement" form="short"/>
              <number variable="supplement" form="ordinal"/>
              <text variable="supplement" form="roman" prefix="roman "/>
            </group>
          </if>
          <else>
            <text variable="supplement" prefix="supplement "/>
          </else>
        </choose>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="supplement"/>
        <text variable="supplement" form="long-ordinal"/>
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
    $supplementBranchChildren = $branches[0]['children'][0]['children'] ?? [];

    if (($branches[0]['isNumeric'] ?? null) !== ['supplement']) {
        throw new RuntimeException('CSL supplement number handoff did not preserve is-numeric metadata');
    }
    if (($supplementBranchChildren[0]['variable'] ?? null) !== 'supplement' || ($supplementBranchChildren[0]['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL supplement number handoff did not preserve label metadata');
    }
    if (($supplementBranchChildren[1]['variable'] ?? null) !== 'supplement' || ($supplementBranchChildren[1]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL supplement number handoff did not preserve number metadata');
    }
    if (($supplementBranchChildren[2]['variable'] ?? null) !== 'supplement' || ($supplementBranchChildren[2]['form'] ?? null) !== 'roman') {
        throw new RuntimeException('CSL supplement number handoff did not preserve text form metadata');
    }

    foreach ([
        '<p>Supplemented imports (Smith supp. 2nd roman ii; Ng supps. 3rd-4th roman iii-iv; Roe supplement appendix packet) keep source supplement numbers visible.</p>',
        '<dt>Smith 2026</dt><dd>Migration Supplement Packet :: supplement second</dd>',
        '<dt>Ng 2025</dt><dd>Range Supplement Packet :: supplements third-fourth</dd>',
        '<dt>Roe 2024</dt><dd>Named Supplement Packet :: supplement appendix packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL supplement number handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-supplement-number-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
