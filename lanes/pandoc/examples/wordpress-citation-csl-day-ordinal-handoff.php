<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Day Ordinal Review

Review cites [@first-day-source; @second-day-source] before publishing date-sensitive import notes.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "first-day-source",
    "type": "report",
    "title": "First Day Packet",
    "author": [
      {"literal": "Date Desk"}
    ],
    "issued": {"date-parts": [[2026, 6, 1]]}
  },
  {
    "id": "second-day-source",
    "type": "report",
    "title": "Second Day Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026, 6, 2]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Day Ordinal Review</title>
    <id>https://example.test/styles/wordpress-citation-day-ordinal-review</id>
    <updated>2026-06-06T07:30:18+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <style-options limit-day-ordinals-to-day-1="true"/>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued" delimiter=" ">
          <date-part name="month" form="long"/>
          <date-part name="day" form="ordinal"/>
          <date-part name="year"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued" delimiter=" ">
        <date-part name="month" form="short"/>
        <date-part name="day" form="ordinal"/>
        <date-part name="year"/>
      </date>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['localeOptions']['limitDayOrdinalsToDay1'] ?? null) !== true) {
        throw new RuntimeException('CSL day-ordinal handoff did not preserve locale option metadata');
    }

    foreach ([
        '<p>Review cites (Date Desk June 1st 2026; Ng June 2 2026) before publishing date-sensitive import notes.</p>',
        '<dt>Date Desk 2026</dt><dd>First Day Packet :: Jun. 1st 2026</dd>',
        '<dt>Ng 2026</dt><dd>Second Day Packet :: Jun. 2 2026</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL day-ordinal self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-day-ordinal-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
