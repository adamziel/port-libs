<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Add Names Disambiguation Review

Review cites [@smith-doe-2026; @smith-ng-2026; @garcia-2026] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith-doe-2026",
    "type": "report",
    "title": "Smith Doe Import Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Doe", "given": "Jane"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/smith-doe"
  },
  {
    "id": "smith-ng-2026",
    "type": "report",
    "title": "Smith Ng Import Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Rao", "given": "Raj"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/smith-ng"
  },
  {
    "id": "garcia-2026",
    "type": "report",
    "title": "Garcia Import Packet",
    "author": [
      {"family": "Garcia", "given": "Gia"},
      {"family": "Cruz", "given": "Ana"},
      {"family": "Iyer", "given": "Ira"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/garcia"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Add Names Disambiguation Review</title>
    <id>https://example.test/styles/wordpress-citation-add-names-disambiguation-review</id>
    <updated>2026-06-08T08:33:26+00:00</updated>
  </info>
  <citation disambiguate-add-names="true">
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" et-al-min="3" et-al-use-first="1">
          <name initialize-with=". "/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <date variable="issued"><date-part name="year"/></date>
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
    if (($summary['citationOptions']['disambiguateAddNames'] ?? null) !== true) {
        throw new RuntimeException('CSL add-names disambiguation handoff did not preserve the citation option');
    }
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['etAlUseFirst'] ?? null) !== 1) {
        throw new RuntimeException('CSL add-names disambiguation handoff did not preserve the base et-al-use-first count');
    }

    foreach ([
        '<p>Review cites (Smith, Doe, et al. 2026; Smith, Ng, et al. 2026; Garcia et al. 2026) before publishing imported source notes.</p>',
        '<dt>Smith et al. 2026</dt><dd>Smith, A.; Doe, J.; Roe, P. 2026. Smith Doe Import Packet. https://example.test/smith-doe.</dd>',
        '<dt>Smith et al. 2026</dt><dd>Smith, A.; Ng, N.; Rao, R. 2026. Smith Ng Import Packet. https://example.test/smith-ng.</dd>',
        '<dt>Garcia et al. 2026</dt><dd>Garcia, G.; Cruz, A.; Iyer, I. 2026. Garcia Import Packet. https://example.test/garcia.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL add-names disambiguation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-add-names-disambiguation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
