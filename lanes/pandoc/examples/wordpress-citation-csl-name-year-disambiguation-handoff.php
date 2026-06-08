<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Name Year Disambiguation Review

Review cites [@smith-doe-a; @smith-doe-b; @smith-ng] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith-doe-a",
    "type": "report",
    "title": "Smith Doe First Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Doe", "given": "Jane"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/smith-doe-a"
  },
  {
    "id": "smith-doe-b",
    "type": "report",
    "title": "Smith Doe Second Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Doe", "given": "Jane"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/smith-doe-b"
  },
  {
    "id": "smith-ng",
    "type": "report",
    "title": "Smith Ng Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Rao", "given": "Raj"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/smith-ng"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Name Year Disambiguation Review</title>
    <id>https://example.test/styles/wordpress-citation-name-year-disambiguation-review</id>
    <updated>2026-06-08T09:21:05+00:00</updated>
  </info>
  <citation disambiguate-add-names="true" disambiguate-add-year-suffix="true" collapse="year-suffix">
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" et-al-min="3" et-al-use-first="1">
          <name initialize-with=". "/>
        </names>
        <group delimiter="">
          <date variable="issued"><date-part name="year"/></date>
          <text variable="year-suffix"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <group delimiter="">
        <date variable="issued"><date-part name="year"/></date>
        <text variable="year-suffix"/>
      </group>
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
        throw new RuntimeException('CSL name/year disambiguation handoff did not preserve add-names');
    }
    if (($summary['citationOptions']['disambiguateAddYearSuffix'] ?? null) !== true) {
        throw new RuntimeException('CSL name/year disambiguation handoff did not preserve add-year-suffix');
    }
    if (($summary['citationOptions']['collapse'] ?? null) !== 'year-suffix') {
        throw new RuntimeException('CSL name/year disambiguation handoff did not preserve year-suffix collapse');
    }

    foreach ([
        '<p>Review cites (Smith, Doe, et al. 2026a,b; Smith, Ng, et al. 2026) before publishing imported source notes.</p>',
        '<dt>Smith et al. 2026a</dt><dd>Smith, A.; Doe, J.; Roe, P. 2026a. Smith Doe First Packet. https://example.test/smith-doe-a.</dd>',
        '<dt>Smith et al. 2026b</dt><dd>Smith, A.; Doe, J.; Roe, P. 2026b. Smith Doe Second Packet. https://example.test/smith-doe-b.</dd>',
        '<dt>Smith et al. 2026</dt><dd>Smith, A.; Ng, N.; Rao, R. 2026. Smith Ng Packet. https://example.test/smith-ng.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL name/year disambiguation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-name-year-disambiguation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
