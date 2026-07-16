<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Localized date override [@localized-date-source] and range [@localized-range-source] stay reviewable.';

$items = [
    [
        'id' => 'localized-date-source',
        'type' => 'report',
        'title' => 'Localized Date Packet',
        'author' => [
            ['literal' => 'Date Override Desk'],
        ],
        'issued' => ['date-parts' => [[2026, 6, 5]]],
        'accessed' => ['date-parts' => [[2026, 6, 6], [2026, 6, 7]]],
    ],
    [
        'id' => 'localized-range-source',
        'type' => 'report',
        'title' => 'Localized Range Packet',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2020, 5, 9], [2020, 6, 11]]],
    ],
];

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Localized Date Override Review Style</title>
    <id>https://example.test/styles/bounded-localized-date-override-review</id>
    <updated>2026-06-09T08:20:04+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="month-05" form="short">May</term>
      <term name="month-06" form="short">Jun.</term>
      <term name="month-07" form="short">Jul.</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <date variable="issued" form="text">
          <date-part name="month" form="short" strip-periods="true"/>
          <date-part name="day" form="ordinal"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued" form="text" date-parts="year-month">
        <date-part name="day" form="ordinal"/>
        <date-part name="month" form="short" strip-periods="true" range-delimiter=" to "/>
      </date>
      <date variable="accessed" form="numeric">
        <date-part name="month" form="numeric-leading-zeros"/>
        <date-part name="day" form="numeric-leading-zeros" range-delimiter=" through "/>
      </date>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $citationDate = $summary['citationRendering'][0]['children'][1] ?? [];
    $bibliographyIssuedDate = $summary['bibliographyRendering'][1] ?? [];
    if (($citationDate['form'] ?? null) !== 'text') {
        throw new RuntimeException('Localized CSL date handoff did not preserve the date element form');
    }
    if (($citationDate['dateParts'][0]['form'] ?? null) !== 'short') {
        throw new RuntimeException('Localized CSL date handoff did not preserve the month override form');
    }
    if (($bibliographyIssuedDate['datePartsSelection'] ?? null) !== 'year-month') {
        throw new RuntimeException('Localized CSL date handoff did not preserve selected localized date parts');
    }
    foreach ([
        '<p>Localized date override (Date Override Desk Jun 5th, 2026) and range (Ng May 9th, 2020/Jun 11th, 2020) stay reviewable.</p>',
        '<dt>Date Override Desk 2026</dt><dd>Localized Date Packet :: Jun 2026 :: 06/06/2026 through 06/07/2026</dd>',
        '<dt>Ng 2020</dt><dd>Localized Range Packet :: May 2020 to Jun 2020</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Localized CSL date handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-localized-date-part-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
