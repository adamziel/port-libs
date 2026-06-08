<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Abbreviation Review

Reviewer packets cite [@source-packet] while preserving compact source labels from a supplied CSL abbreviation list.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "source-packet",
    "type": "report",
    "title": "Migration Review Source Packet",
    "container-title": "Journal of Imported Source Packets",
    "collection-title": "Migration Review Series",
    "publisher": "WordPress Migration Press",
    "publisher-place": "New York",
    "genre": "technical report",
    "author": [
      {"family": "Vale", "given": "Vera"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Abbreviation Review</title>
    <id>https://example.test/styles/wordpress-csl-abbreviation-review</id>
    <updated>2026-06-08T21:20:45+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title" form="short"/>
        <text variable="container-title" form="short"/>
        <text variable="collection-title" form="short"/>
        <text variable="publisher" form="short"/>
        <text variable="publisher-place" form="short"/>
        <text variable="genre" form="short"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title" form="short"/>
      <text variable="container-title" form="short"/>
      <text variable="collection-title" form="short"/>
      <text variable="publisher" form="short"/>
      <text variable="publisher-place" form="short"/>
      <text variable="genre" form="short"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)
    ->withCslStyle($styleXml)
    ->withCslAbbreviations([
        'default' => [
            'title' => [
                'Migration Review Source Packet' => 'Migr. Rev. Source',
            ],
            'container-title' => [
                'Journal of Imported Source Packets' => 'J. Imported Source Packets',
            ],
            'collection-title' => [
                'Migration Review Series' => 'Migr. Rev. Ser.',
            ],
            'publisher' => [
                'WordPress Migration Press' => 'WP Migr. Press',
            ],
            'place' => [
                'New York' => 'N.Y.',
            ],
            'genre' => [
                'technical report' => 'tech. rep.',
            ],
        ],
    ]);

$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['abbreviations']['title']['Migration Review Source Packet'] ?? null) !== 'Migr. Rev. Source') {
        throw new RuntimeException('CSL abbreviation handoff did not preserve title abbreviation metadata');
    }
    if (($summary['abbreviations']['place']['New York'] ?? null) !== 'N.Y.') {
        throw new RuntimeException('CSL abbreviation handoff did not preserve place abbreviation metadata');
    }

    foreach ([
        '<p>Reviewer packets cite [Migr. Rev. Source | J. Imported Source Packets | Migr. Rev. Ser. | WP Migr. Press | N.Y. | tech. rep.] while preserving compact source labels from a supplied CSL abbreviation list.</p>',
        '<dt>Vale 2026</dt><dd>Migr. Rev. Source :: J. Imported Source Packets :: Migr. Rev. Ser. :: WP Migr. Press :: N.Y. :: tech. rep.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL abbreviation self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-abbreviation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
