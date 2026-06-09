<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Custom Creator Condition Review

Custom name routes [@custom-name-packet; @plain-name-packet] remain visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "custom-name-packet",
    "type": "report",
    "title": "Custom Name Packet",
    "namea": [
      {"literal": "Review Desk"}
    ],
    "nameb": [
      {"literal": "Migration Desk"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "custom-name-c-packet",
    "type": "report",
    "title": "Name C Packet",
    "namec": [
      {"literal": "Archive Desk"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "plain-name-packet",
    "type": "report",
    "title": "Plain Packet",
    "author": [
      {"literal": "Plain Desk"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress Custom Creator Condition Review</title>
    <id>https://example.test/styles/wordpress-custom-creator-condition-review</id>
    <updated>2026-06-09T07:02:57+00:00</updated>
  </info>
  <macro name="custom-name-route">
    <choose>
      <if is-creator="namea nameb" match="all">
        <text value="custom-both"/>
      </if>
      <else-if is-creator="namea namec" match="any">
        <text value="custom-any"/>
      </else-if>
      <else-if is-creator="namea nameb namec" match="none">
        <text value="custom-none"/>
      </else-if>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="custom-name-route"/>
        <names variable="namea nameb namec author" delimiter="; "/>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text macro="custom-name-route"/>
      <names variable="namea nameb namec author" delimiter="; "/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $branches = $summary['macros']['custom-name-route'][0]['branches'] ?? [];
    if (($branches[0]['isCreator'] ?? null) !== ['namea', 'nameb']) {
        throw new RuntimeException('Custom creator condition handoff did not preserve namea/nameb metadata');
    }
    if (($branches[2]['match'] ?? null) !== 'none') {
        throw new RuntimeException('Custom creator condition handoff did not preserve match=none metadata');
    }
    foreach ([
        '<p>Custom name routes (custom-both | Review Desk | Custom Name Packet | 2026; custom-none | Plain Desk | Plain Packet | 2024) remain visible.</p>',
        '<dd>Custom Name Packet :: custom-both :: Review Desk</dd>',
        '<dd>Plain Packet :: custom-none :: Plain Desk</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Custom creator condition handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-custom-name-is-creator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
