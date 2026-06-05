<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Et Al Last Review

Review cites @last-source before bibliography handoff.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "last-source",
    "type": "report",
    "title": "Et Al Last Source Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Okafor", "given": "Ola"},
      {"literal": "Migration Desk"},
      {"family": "Smith", "given": "Sam"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/et-al-last-source"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Et Al Use Last Review</title>
    <id>https://example.test/styles/wordpress-citation-et-al-use-last-review</id>
    <updated>2026-06-05T15:33:02+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", " et-al-min="4" et-al-use-first="2" et-al-use-last="true">
          <name/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author" delimiter="; " et-al-min="4" et-al-use-first="2" et-al-use-last="true">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <text variable="title"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['nameRendering']['citation']['etAlUseLast'] ?? null) !== true) {
        throw new RuntimeException('CSL et-al-use-last handoff did not preserve citation metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['etAlUseLast'] ?? null) !== true) {
        throw new RuntimeException('CSL et-al-use-last handoff did not preserve bibliography rendering metadata');
    }

    $ellipsis = "\u{2026}";
    foreach ([
        '<p>Review cites de la Cruz, Ng, ' . $ellipsis . ', Smith (2026) before bibliography handoff.</p>',
        '<dt>de la Cruz, Ng, ' . $ellipsis . ', Smith 2026</dt><dd>de la Cruz, A. M.; Ng, N.; ' . $ellipsis . '; Smith, S. Et Al Last Source Packet. https://example.test/et-al-last-source.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL et-al-use-last self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-et-al-use-last-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
