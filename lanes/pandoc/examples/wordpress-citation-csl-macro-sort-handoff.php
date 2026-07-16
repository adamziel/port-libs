<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Macro Sort Review

Macro-sorted review cites [@visible-adams; @visible-zed; @visible-ng] while preserving visible citation text for reviewers.
MARKDOWN;

$items = [
    [
        'id' => 'visible-zed',
        'type' => 'report',
        'title' => 'Visible Zed Packet',
        'author' => [
            ['family' => 'Zed', 'given' => 'Zoe'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'collection-number' => '001',
    ],
    [
        'id' => 'visible-adams',
        'type' => 'report',
        'title' => 'Visible Adams Packet',
        'author' => [
            ['family' => 'Adams', 'given' => 'Ari'],
        ],
        'issued' => ['date-parts' => [[2020]]],
        'collection-number' => '900',
    ],
    [
        'id' => 'visible-ng',
        'type' => 'report',
        'title' => 'Visible Ng Packet',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2024]]],
        'collection-number' => '050',
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Macro Sort Review</title>
    <id>https://example.test/styles/wordpress-citation-macro-sort-review</id>
    <updated>2026-06-08T06:41:07+00:00</updated>
  </info>
  <macro name="review-bucket">
    <group delimiter="-">
      <text variable="collection-number"/>
      <names variable="author">
        <name form="short"/>
      </names>
    </group>
  </macro>
  <macro name="visible-review">
    <group delimiter=" | ">
      <names variable="author">
        <name form="short"/>
      </names>
      <date variable="issued"><date-part name="year"/></date>
      <text variable="title"/>
    </group>
  </macro>
  <citation>
    <sort>
      <key macro="review-bucket"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter="; ">
      <text macro="visible-review"/>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key macro="review-bucket" sort="descending"/>
    </sort>
    <layout delimiter=" :: ">
      <text macro="visible-review"/>
      <text macro="review-bucket"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationSort'][0]['macro'] ?? null) !== 'review-bucket') {
        throw new RuntimeException('CSL macro-sort handoff did not preserve citation sort macro metadata');
    }
    if (($summary['bibliographySort'][0]['macro'] ?? null) !== 'review-bucket') {
        throw new RuntimeException('CSL macro-sort handoff did not preserve bibliography sort macro metadata');
    }

    foreach ([
        '<p>Macro-sorted review cites [Zed | 2026 | Visible Zed Packet; Ng | 2024 | Visible Ng Packet; Adams | 2020 | Visible Adams Packet] while preserving visible citation text for reviewers.</p>',
        '<dt>Adams 2020</dt><dd>Adams | 2020 | Visible Adams Packet :: 900-Adams</dd><dt>Ng 2024</dt><dd>Ng | 2024 | Visible Ng Packet :: 050-Ng</dd><dt>Zed 2026</dt><dd>Zed | 2026 | Visible Zed Packet :: 001-Zed</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL macro-sort self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-macro-sort-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
