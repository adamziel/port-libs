<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Et Al Review

Review cites @source-packet and [@editor-packet] before the bibliography.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-packet",
    "type": "report",
    "title": "Et Al Source Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Okafor", "given": "Ola"},
      {"family": "Smith", "given": "Sam"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "editor-packet",
    "type": "book",
    "title": "Editor Et Al Packet",
    "editor": [
      {"family": "Curator", "given": "Eli", "suffix": "III", "comma-suffix": true},
      {"family": "Reviewer", "given": "Rae"},
      {"literal": "Migration Desk"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Et Al Review</title>
    <id>https://example.test/styles/wordpress-citation-et-al-review</id>
    <updated>2026-06-05T09:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author editor" delimiter=", " et-al-min="3" et-al-use-first="1" delimiter-precedes-et-al="always">
          <name/>
          <et-al term="and others" prefix="[" suffix="]" text-case="uppercase"/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author editor" delimiter="; " et-al-min="3" et-al-use-first="2">
        <name initialize-with=". " name-as-sort-order="first" delimiter-precedes-et-al="after-inverted-name"/>
        <et-al term="and others" prefix="more: " strip-periods="true" text-case="capitalize-first"/>
      </names>
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
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['etAl']['term'] ?? null) !== 'and others') {
        throw new RuntimeException('CSL et-al handoff did not preserve the and others term');
    }
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['delimiterPrecedesEtAl'] ?? null) !== 'always') {
        throw new RuntimeException('CSL et-al handoff did not preserve the citation delimiter policy');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['delimiterPrecedesEtAl'] ?? null) !== 'after-inverted-name') {
        throw new RuntimeException('CSL et-al handoff did not preserve the bibliography delimiter policy');
    }

    foreach ([
        '<p>Review cites de la Cruz, [AND OTHERS] (2026) and (Curator, [AND OTHERS] 2025) before the bibliography.</p>',
        '<dt>de la Cruz, [AND OTHERS] 2026</dt><dd>de la Cruz, A. M.; N. Ng more: And others. Et Al Source Packet.</dd>',
        '<dt>Curator, [AND OTHERS] 2025</dt><dd>Curator, E., III; R. Reviewer more: And others. Editor Et Al Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL et-al handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-et-al-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
