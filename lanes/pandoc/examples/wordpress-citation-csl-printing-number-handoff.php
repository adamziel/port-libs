<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Printing Number Citation Review

Printing metadata [@printing-source; @range-source; @named-source] stays reviewable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "printing-source",
    "type": "book",
    "title": "Second Printing Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "printing-number": "2",
    "supplement-number": "1"
  },
  {
    "id": "range-source",
    "type": "report",
    "title": "Range Supplement Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "printingNumber": "3-4",
    "supplementNumber": "2-3"
  },
  {
    "id": "named-source",
    "type": "report",
    "title": "Named Supplement Packet",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2024]]},
    "printing-number": "advance press",
    "supplement": "legacy appendix"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Printing Supplement Number Review</title>
    <id>https://example.test/styles/wordpress-csl-printing-supplement-number-review</id>
    <updated>2026-06-09T03:37:51+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <choose>
          <if is-numeric="printing-number supplement-number" match="all">
            <group delimiter=" ">
              <label variable="printing-number" form="short"/>
              <number variable="printing-number" form="ordinal"/>
              <label variable="supplement-number" form="short"/>
              <number variable="supplement-number" form="roman"/>
            </group>
          </if>
          <else>
            <group delimiter=" ">
              <text variable="printing-number" prefix="printing "/>
              <text variable="supplement-number" prefix="supplement "/>
            </group>
          </else>
        </choose>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="printing-number" plural="contextual"/>
        <text variable="printing-number" form="long-ordinal"/>
      </group>
      <group delimiter=" ">
        <label variable="supplement-number" plural="contextual"/>
        <text variable="supplement-number" form="roman"/>
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
    $numericBranchChildren = $branches[0]['children'][0]['children'] ?? [];

    if (($branches[0]['isNumeric'] ?? null) !== ['printing-number', 'supplement-number']) {
        throw new RuntimeException('CSL printing-number handoff did not preserve is-numeric metadata');
    }
    if (($numericBranchChildren[0]['variable'] ?? null) !== 'printing-number' || ($numericBranchChildren[1]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL printing-number handoff did not preserve printing number metadata');
    }
    if (($numericBranchChildren[2]['variable'] ?? null) !== 'supplement-number' || ($numericBranchChildren[3]['form'] ?? null) !== 'roman') {
        throw new RuntimeException('CSL printing-number handoff did not preserve supplement number metadata');
    }

    foreach ([
        '<p>Printing metadata (Smith printing no. 2nd supp. no. i; Ng printing nos. 3rd-4th supp. nos. ii-iii; Roe printing advance press supplement legacy appendix) stays reviewable.</p>',
        '<dt>Smith 2026</dt><dd>Second Printing Packet :: printing number second :: supplement number i</dd>',
        '<dt>Ng 2025</dt><dd>Range Supplement Packet :: printing numbers third-fourth :: supplement numbers ii-iii</dd>',
        '<dt>Roe 2024</dt><dd>Named Supplement Packet :: printing number advance press :: supplement number legacy appendix</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL printing-number self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-printing-number-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
