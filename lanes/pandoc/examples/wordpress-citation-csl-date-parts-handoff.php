<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Date precision [@month-precision-source] and range precision [@range-precision-source] stay reviewable.';

$items = [
    [
        'id' => 'month-precision-source',
        'type' => 'report',
        'title' => 'Month Precision Packet',
        'author' => [
            ['literal' => 'Date Parts Desk'],
        ],
        'issued' => ['date-parts' => [[2027, 3, 9]]],
        'accessed' => ['date-parts' => [[2027, 3, 10], [2027, 3, 11]]],
        'event-date' => ['date-parts' => [[2026, 12, 15]]],
    ],
    [
        'id' => 'range-precision-source',
        'type' => 'report',
        'title' => 'Range Precision Packet',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2020, 5, 9], [2021, 6, 11]]],
        'accessed' => ['date-parts' => [[2025, 1, 2]]],
    ],
];

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Date Form Date Parts Review Style</title>
    <id>https://example.test/styles/bounded-date-form-date-parts-review</id>
    <updated>2026-06-07T12:39:39+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued" form="text" date-parts="year-month"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued" form="text" date-parts="year"/>
      <date variable="accessed" form="numeric" date-parts="year-month" prefix="checked "/>
      <date variable="event-date" form="text" date-parts="year-month-day" prefix="event "/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][1]['datePartsSelection'] ?? null) !== 'year-month') {
        throw new RuntimeException('Citation CSL date-parts handoff did not preserve citation date-parts precision');
    }
    if (($summary['bibliographyRendering'][1]['datePartsSelection'] ?? null) !== 'year') {
        throw new RuntimeException('Citation CSL date-parts handoff did not preserve issued year precision');
    }
    foreach ([
        '<p>Date precision (Date Parts Desk March 2027) and range precision (Ng May 2020/June 2021) stay reviewable.</p>',
        '<dt>Date Parts Desk 2027</dt><dd>Month Precision Packet :: 2027 :: checked 3/2027 :: event December 15, 2026</dd>',
        '<dt>Ng 2020/2021</dt><dd>Range Precision Packet :: 2020/2021 :: checked 1/2025</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL date-parts handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-date-parts-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
