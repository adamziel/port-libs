<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Institution Review

Institution source [@institution-source; @person-source] keeps organization authors readable.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "institution-source",
    "type": "webpage",
    "title": "Institutional Reviewer Packet",
    "author": [
      {"literal": "W.P. Migration Desk"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/institution-source"
  },
  {
    "id": "person-source",
    "type": "report",
    "title": "Personal Reviewer Packet",
    "author": [
      {"family": "Cruz", "given": "Ana"}
    ],
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/person-source"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Institution Review</title>
    <id>https://example.test/styles/wordpress-citation-institution-review</id>
    <updated>2026-06-06T03:14:52+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author">
          <name initialize-with=". "/>
          <institution>
            <institution-part name="long" prefix="org " strip-periods="true" text-case="uppercase"/>
          </institution>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
        <institution>
          <institution-part name="long" prefix="Institution: " strip-periods="true" text-case="capitalize-all"/>
        </institution>
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
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['institution']['parts']['long']['textCase'] ?? null) !== 'uppercase') {
        throw new RuntimeException('CSL institution handoff did not preserve citation institution-part metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['institution']['parts']['long']['prefix'] ?? null) !== 'Institution: ') {
        throw new RuntimeException('CSL institution handoff did not preserve bibliography institution-part metadata');
    }

    foreach ([
        '<p>Institution source (org WP MIGRATION DESK 2026; Cruz 2025) keeps organization authors readable.</p>',
        '<dt>org WP MIGRATION DESK 2026</dt><dd>Institution: WP Migration Desk :: Institutional Reviewer Packet :: https://example.test/institution-source</dd>',
        '<dt>Cruz 2025</dt><dd>Cruz, A. :: Personal Reviewer Packet :: https://example.test/person-source</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL institution self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-institution-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
