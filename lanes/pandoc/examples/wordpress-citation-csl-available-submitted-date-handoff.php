<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Availability review cites [@available-review; @submitted-review] before posting source notes.';

$items = [
    [
        'id' => 'available-review',
        'type' => 'webpage',
        'title' => 'Available Source Packet',
        'author' => [
            ['family' => 'Smith', 'given' => 'Ada'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'available-date' => [
            'date-parts' => [[2026, 6]],
            'uncertain' => true,
            'raw' => '2026-06?',
        ],
        'submitted' => [
            'date-parts' => [[2026, 5, 28]],
            'time' => '09:30',
        ],
    ],
    [
        'id' => 'submitted-review',
        'type' => 'article-journal',
        'title' => 'Submitted Review Packet',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2025]]],
        'availableDate' => ['literal' => 'early access queue'],
        'submitted' => [
            'date-parts' => [[2024]],
            'circa' => true,
            'raw' => '2024~',
        ],
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Available Submitted Date Review</title>
    <id>https://example.test/styles/wordpress-citation-available-submitted-date-review</id>
    <updated>2026-06-08T19:50:04+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <choose>
          <if is-uncertain-date="available-date">
            <text value="available?"/>
          </if>
          <else-if is-circa-date="submitted">
            <text value="submitted circa"/>
          </else-if>
          <else>
            <text value="dated"/>
          </else>
        </choose>
        <date variable="available-date" form="text" date-parts="year-month"/>
        <date variable="submitted"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="submitted"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="available-date"/>
      <text variable="available-date-status"/>
      <date variable="submitted"/>
      <text variable="submitted-status"/>
      <text variable="date-marker-summary"/>
      <text variable="date-time-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Availability Sources');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][1]['branches'][0]['isUncertainDate'] ?? null) !== ['available-date']) {
        throw new RuntimeException('CSL available/submitted date handoff did not preserve available-date uncertainty metadata');
    }
    if (($summary['citationRendering'][0]['children'][1]['branches'][1]['isCircaDate'] ?? null) !== ['submitted']) {
        throw new RuntimeException('CSL available/submitted date handoff did not preserve submitted circa metadata');
    }
    if (($summary['bibliographySort'][0]['variable'] ?? null) !== 'submitted') {
        throw new RuntimeException('CSL available/submitted date handoff did not preserve submitted bibliography sort metadata');
    }

    foreach ([
        '<p>Availability review cites (Smith | available? | June 2026 | 2026-05-28; Ng | submitted circa | early access queue | 2024) before posting source notes.</p>',
        '<dt>Ng 2025</dt><dd>Submitted Review Packet :: early access queue :: 2024 :: circa :: Date markers: submitted circa (2024~)</dd>',
        '<dt>Smith 2026</dt><dd>Available Source Packet :: 2026-06 :: uncertain :: 2026-05-28 :: Date markers: available-date uncertain (2026-06?) :: Date times: submitted 09:30</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL available/submitted date handoff missing expected snippet: ' . $snippet);
        }
    }

    if (strpos($blocks, 'Submitted Review Packet') > strpos($blocks, 'Available Source Packet')) {
        throw new RuntimeException('CSL available/submitted date handoff did not sort bibliography by submitted date');
    }

    echo "wordpress-citation-csl-available-submitted-date-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
