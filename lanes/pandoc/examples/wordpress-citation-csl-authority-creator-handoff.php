<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Authority Creator Review

Legal import packets [@scalar-authority; @name-authority; @plain-source] keep issuing authorities routeable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "scalar-authority",
    "type": "legislation",
    "title": "Migration Review Act",
    "authority": "Oregon Legislature",
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "name-authority",
    "type": "legal_case",
    "title": "Review Board v. Archive Desk",
    "authority": [
      {"literal": "Migration Review Court"},
      {"literal": "Appeals Board"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "plain-source",
    "type": "report",
    "title": "Plain Source",
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Authority Creator Review</title>
    <id>https://example.test/styles/wordpress-authority-creator-review</id>
    <updated>2026-06-09T02:25:57+00:00</updated>
  </info>
  <macro name="authority-route">
    <choose>
      <if is-creator="authority">
        <text value="authority-creator"/>
      </if>
      <else>
        <text value="no-authority"/>
      </else>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="authority-route"/>
        <names variable="authority">
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
      <text variable="authority"/>
      <names variable="authority"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $branches = $summary['macros']['authority-route'][0]['branches'] ?? [];
    if (($branches[0]['isCreator'] ?? null) !== ['authority']) {
        throw new RuntimeException('CSL authority creator handoff did not preserve authority creator condition metadata');
    }
    if (($processor->item('scalar-authority')['authorities'][0]['literal'] ?? null) !== 'Oregon Legislature') {
        throw new RuntimeException('CSL authority creator handoff did not expose scalar authority as a literal name');
    }
    if (($processor->item('name-authority')['authority'] ?? null) !== 'Migration Review Court; Appeals Board') {
        throw new RuntimeException('CSL authority creator handoff did not preserve authority text display');
    }

    foreach ([
        '<p>Legal import packets (authority-creator | Oregon Legislature; authority-creator | Migration Review Court and Appeals Board; no-authority | Plain Source) keep issuing authorities routeable.</p>',
        '<dt>Migration Review Act 2026</dt><dd>Migration Review Act :: Oregon Legislature :: Oregon Legislature</dd>',
        '<dt>Review Board v. Archive Desk 2025</dt><dd>Review Board v. Archive Desk :: Migration Review Court; Appeals Board :: Migration Review Court; Appeals Board</dd>',
        '<dt>Plain Source 2024</dt><dd>Plain Source</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL authority creator handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-authority-creator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
