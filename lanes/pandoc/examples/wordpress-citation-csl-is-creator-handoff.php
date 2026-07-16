<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Creator Condition Review

Creator routing [@source-packet; @title-only-packet; @translation-packet] stays visible for review.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-packet",
    "type": "report",
    "title": "Source Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "editor": [
      {"literal": "Review Desk"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "translation-packet",
    "type": "report",
    "title": "Translation Packet",
    "translator": [
      {"literal": "Translator Team"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "title-only-packet",
    "type": "report",
    "title": "Title Only Packet",
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress Creator Condition Review</title>
    <id>https://example.test/styles/wordpress-creator-condition-review</id>
    <updated>2026-06-07T15:19:00+00:00</updated>
  </info>
  <macro name="creator-route">
    <choose>
      <if is-creator="author editor">
        <text value="author-editor"/>
      </if>
      <else-if is-creator="translator" match="any">
        <text value="translator-creator"/>
      </else-if>
      <else-if is-creator="author editor translator" match="none">
        <text value="creator-none"/>
      </else-if>
    </choose>
  </macro>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="creator-route"/>
        <names variable="author editor translator">
          <substitute>
            <text variable="title"/>
          </substitute>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text macro="creator-route"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $branches = $summary['macros']['creator-route'][0]['branches'] ?? [];
    if (($branches[0]['isCreator'] ?? null) !== ['author', 'editor']) {
        throw new RuntimeException('CSL is-creator handoff did not preserve author/editor condition metadata');
    }
    if (($branches[2]['match'] ?? null) !== 'none') {
        throw new RuntimeException('CSL is-creator handoff did not preserve match=none metadata');
    }
    foreach ([
        '<p>Creator routing (author-editor | Smith | 2026; creator-none | Title Only Packet | 2024; translator-creator | Translator Team | 2025) stays visible for review.</p>',
        '<dt>Smith 2026</dt><dd>Source Packet :: author-editor</dd>',
        '<dt>Title Only Packet 2024</dt><dd>Title Only Packet :: creator-none</dd>',
        '<dt>Translator Team 2025</dt><dd>Translation Packet :: translator-creator</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL is-creator handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-is-creator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
