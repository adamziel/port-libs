<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Extended Creator Condition Review

Extended creator routes [@founder-source; @continuator-reviser-source; @collaborator-source; @plain-source] stay visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "founder-source",
    "type": "book",
    "title": "Founder Packet",
    "founder": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "continuator-reviser-source",
    "type": "book",
    "title": "Continuator Reviser Packet",
    "continuator": [
      {"family": "Ng", "given": "Nia"}
    ],
    "reviser": [
      {"literal": "Revision Desk"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "collaborator-source",
    "type": "book",
    "title": "Collaborator Packet",
    "collaborator": [
      {"literal": "Source Review Desk"}
    ],
    "issued": {"date-parts": [[2024]]}
  },
  {
    "id": "plain-source",
    "type": "book",
    "title": "Plain Packet",
    "issued": {"date-parts": [[2023]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Extended Creator Condition Review</title>
    <id>https://example.test/styles/wordpress-extended-creator-condition-review</id>
    <updated>2026-06-08T18:00:46+00:00</updated>
  </info>
  <macro name="extended-creator-route">
    <choose>
      <if is-creator="founder">
        <text value="founded"/>
      </if>
      <else-if is-creator="continuator reviser" match="all">
        <text value="continued-and-revised"/>
      </else-if>
      <else-if is-creator="collaborator" match="any">
        <text value="collaborated"/>
      </else-if>
      <else-if is-creator="founder continuator reviser collaborator" match="none">
        <text value="no-extended-creator"/>
      </else-if>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="extended-creator-route"/>
        <names variable="founder continuator reviser collaborator">
          <substitute>
            <text variable="title"/>
          </substitute>
        </names>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text macro="extended-creator-route"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $branches = $summary['macros']['extended-creator-route'][0]['branches'] ?? [];
    if (($branches[0]['isCreator'] ?? null) !== ['founder']) {
        throw new RuntimeException('CSL extended creator handoff did not preserve founder condition metadata');
    }
    if (($branches[1]['isCreator'] ?? null) !== ['continuator', 'reviser'] || ($branches[1]['match'] ?? null) !== 'all') {
        throw new RuntimeException('CSL extended creator handoff did not preserve continuator/reviser all-match metadata');
    }
    if (($branches[2]['isCreator'] ?? null) !== ['collaborator'] || ($branches[2]['match'] ?? null) !== 'any') {
        throw new RuntimeException('CSL extended creator handoff did not preserve collaborator any-match metadata');
    }

    foreach ([
        '<p>Extended creator routes (founded | Roe; continued-and-revised | Ng; collaborated | Source Review Desk; no-extended-creator | Plain Packet) stay visible.</p>',
        '<dt>Founder Packet 2026</dt><dd>Founder Packet :: founded</dd>',
        '<dt>Continuator Reviser Packet 2025</dt><dd>Continuator Reviser Packet :: continued-and-revised</dd>',
        '<dt>Collaborator Packet 2024</dt><dd>Collaborator Packet :: collaborated</dd>',
        '<dt>Plain Packet 2023</dt><dd>Plain Packet :: no-extended-creator</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL extended creator condition handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-extended-creator-condition-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
