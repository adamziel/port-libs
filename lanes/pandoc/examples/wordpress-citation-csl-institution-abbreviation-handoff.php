<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Institution Abbreviation Review

Reviewer packets cite [@who-source; @desk-source] while keeping organization author abbreviations visible for review.
MARKDOWN;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Institution Abbreviation Review</title>
    <id>https://example.test/styles/wordpress-csl-institution-abbreviation-review</id>
    <updated>2026-06-09T04:54:15+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author" delimiter=", ">
          <name initialize-with=". "/>
          <institution institution-parts="short">
            <institution-part name="short" prefix="org " strip-periods="true" text-case="uppercase"/>
          </institution>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="author" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
        <institution institution-parts="long-short" delimiter=" / ">
          <institution-part name="long" prefix="Institution: " strip-periods="true" text-case="capitalize-all"/>
          <institution-part name="short" prefix="abbr " text-case="uppercase"/>
        </institution>
      </names>
      <text variable="title"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems([
    [
        'id' => 'who-source',
        'type' => 'webpage',
        'title' => 'Global Health Review Packet',
        'author' => [
            ['literal' => 'World Health Organization'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'URL' => 'https://example.test/who-source',
    ],
    [
        'id' => 'desk-source',
        'type' => 'report',
        'title' => 'Migration Desk Review Packet',
        'author' => [
            ['literal' => 'W.P. Migration Desk', 'short' => 'WPMD'],
        ],
        'issued' => ['date-parts' => [[2025]]],
        'URL' => 'https://example.test/desk-source',
    ],
])->withCslStyle($styleXml)->withCslAbbreviations([
    'default' => [
        'institution' => [
            'World Health Organization' => 'WHO',
            'W.P. Migration Desk' => 'Mapped WPMD',
        ],
    ],
]);

$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['abbreviations']['institution']['World Health Organization'] ?? null) !== 'WHO') {
        throw new RuntimeException('CSL institution abbreviation handoff did not preserve the WHO abbreviation metadata');
    }

    foreach ([
        '<p>Reviewer packets cite (org WHO 2026; org WPMD 2025) while keeping organization author abbreviations visible for review.</p>',
        '<dt>org WHO 2026</dt><dd>Institution: World Health Organization / abbr WHO :: Global Health Review Packet :: https://example.test/who-source</dd>',
        '<dt>org WPMD 2025</dt><dd>Institution: WP Migration Desk / abbr WPMD :: Migration Desk Review Packet :: https://example.test/desk-source</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL institution abbreviation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-institution-abbreviation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
