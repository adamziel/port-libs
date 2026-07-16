<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Given/Add-Names/Year Disambiguation Review

Review cites [@smith-ada-doe-a; @smith-ada-doe-b; @smith-alan-doe; @smith-ada-ng] before publishing imported source notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith-ada-doe-a",
    "type": "report",
    "title": "Ada Doe First Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Doe", "given": "Jane"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/ada-doe-a"
  },
  {
    "id": "smith-ada-doe-b",
    "type": "report",
    "title": "Ada Doe Second Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Doe", "given": "Jane"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/ada-doe-b"
  },
  {
    "id": "smith-alan-doe",
    "type": "report",
    "title": "Alan Doe Packet",
    "author": [
      {"family": "Smith", "given": "Alan"},
      {"family": "Doe", "given": "Jane"},
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/alan-doe"
  },
  {
    "id": "smith-ada-ng",
    "type": "report",
    "title": "Ada Ng Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Ng", "given": "Nia"},
      {"family": "Rao", "given": "Raj"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/ada-ng"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Given Add Names Year Disambiguation Review</title>
    <id>https://example.test/styles/wordpress-citation-given-add-names-year-disambiguation-review</id>
    <updated>2026-06-08T10:01:34+00:00</updated>
  </info>
  <citation disambiguate-add-givenname="true" disambiguate-add-names="true" disambiguate-add-year-suffix="true" collapse="year-suffix">
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
    if (($summary['citationOptions']['disambiguateAddGivenName'] ?? null) !== true) {
        throw new RuntimeException('CSL mixed disambiguation handoff did not preserve add-givenname');
    }
    if (($summary['citationOptions']['disambiguateAddNames'] ?? null) !== true) {
        throw new RuntimeException('CSL mixed disambiguation handoff did not preserve add-names');
    }
    if (($summary['citationOptions']['disambiguateAddYearSuffix'] ?? null) !== true) {
        throw new RuntimeException('CSL mixed disambiguation handoff did not preserve add-year-suffix');
    }

    foreach ([
        '<p>Review cites (Ada Smith, Doe, et al. 2026a,b; Alan Smith et al. 2026; Ada Smith, Ng, et al. 2026) before publishing imported source notes.</p>',
        '<dt>Smith et al. 2026a</dt><dd>Smith, A.; Doe, J.; Roe, P. 2026a. Ada Doe First Packet. https://example.test/ada-doe-a.</dd>',
        '<dt>Smith et al. 2026b</dt><dd>Smith, A.; Doe, J.; Roe, P. 2026b. Ada Doe Second Packet. https://example.test/ada-doe-b.</dd>',
        '<dt>Smith et al. 2026</dt><dd>Smith, A.; Doe, J.; Roe, P. 2026. Alan Doe Packet. https://example.test/alan-doe.</dd>',
        '<dt>Smith et al. 2026</dt><dd>Smith, A.; Ng, N.; Rao, R. 2026. Ada Ng Packet. https://example.test/ada-ng.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL mixed disambiguation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-given-add-names-year-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
