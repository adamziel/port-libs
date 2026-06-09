<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Event Organizer Terms

Organizer credits [@community-review; @solo-review] stay visible for import review.
MARKDOWN;

$items = [
    [
        'id' => 'community-review',
        'type' => 'event',
        'title' => 'Community Review Clinic',
        'event-organizer' => [
            ['literal' => 'Migration Desk'],
            ['family' => 'Curator', 'given' => 'Eli'],
        ],
        'issued' => ['date-parts' => [[2026]]],
    ],
    [
        'id' => 'solo-review',
        'type' => 'event',
        'title' => 'Solo Review Session',
        'event-organizer' => [
            ['family' => 'Organizer', 'given' => 'Ora'],
        ],
        'issued' => ['date-parts' => [[2025]]],
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Event Organizer Term Review</title>
    <id>https://example.test/styles/bounded-event-organizer-term-review</id>
    <updated>2026-06-09T06:13:46+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="event-organizer">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". "/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
        <text variable="title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="event-organizer">
        <name form="long" initialize-with=". " name-as-sort-order="all"/>
        <label form="short" plural="contextual" prefix=", "/>
      </names>
      <names variable="organizer">
        <label form="verb-short" suffix=" "/>
        <name form="long" initialize-with=". " name-as-sort-order="all"/>
      </names>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL event-organizer term handoff did not preserve verb label metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['label']['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL event-organizer term handoff did not preserve short label metadata');
    }
    if (($summary['bibliographyRendering'][1]['nameRendering']['label']['form'] ?? null) !== 'verb-short') {
        throw new RuntimeException('CSL organizer alias term handoff did not preserve verb-short label metadata');
    }

    foreach ([
        '<p>Organizer credits [organized by Migration Desk and Curator | 2026 | Community Review Clinic; organized by Organizer | 2025 | Solo Review Session] stay visible for import review.</p>',
        '<dt>Community Review Clinic 2026</dt><dd>Migration Desk; Curator, E., orgs. :: org. by Migration Desk; Curator, E. :: Community Review Clinic</dd>',
        '<dt>Solo Review Session 2025</dt><dd>Organizer, O., org. :: org. by Organizer, O. :: Solo Review Session</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL event-organizer term self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-event-organizer-terms-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
