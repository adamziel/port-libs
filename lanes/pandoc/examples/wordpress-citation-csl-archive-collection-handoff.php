<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Archive Collection Review

Archive collections [@archive-city; @archive-field; @archive-legacy] keep reviewer locations visible.
MARKDOWN;

$items = [
    [
        'id' => 'archive-city',
        'type' => 'manuscript',
        'title' => 'City Archive Packet',
        'author' => [
            ['family' => 'Smith', 'given' => 'Ada'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'archive' => 'City Archive',
        'archive-collection' => 'Migration Papers',
        'archive-place' => 'Portland',
        'archive_location' => 'Box 4',
    ],
    [
        'id' => 'archive-field',
        'type' => 'manuscript',
        'title' => 'Field Notes Packet',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2025]]],
        'archive' => 'Field Notes Library',
        'archiveCollection' => 'Audit Series',
        'archive-location' => 'Folder 2',
    ],
    [
        'id' => 'archive-legacy',
        'type' => 'document',
        'title' => 'Legacy Ledger Packet',
        'author' => [
            ['literal' => 'Repository Desk'],
        ],
        'issued' => ['date-parts' => [[2024]]],
        'archive_collection' => 'Legacy Ledgers',
        'archive_location' => 'Shelf 8',
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Archive Collection Review</title>
    <id>https://example.test/styles/wordpress-citation-archive-collection-review</id>
    <updated>2026-06-09T07:17:50+00:00</updated>
  </info>
  <citation>
    <sort>
      <key variable="archive_collection"/>
    </sort>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="archive"/>
        <text variable="archive_collection"/>
        <text variable="archive-place"/>
        <text variable="archive_location"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="archive_collection"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="archive-collection"/>
      <text variable="archive-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = (new MarkdownReader())->read($markdown);
$blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));

if (($argv[1] ?? '') === '--self-test') {
    $city = $processor->item('archive-city');
    $field = $processor->item('archive-field');
    $legacy = $processor->item('archive-legacy');

    if (($city['archiveCollection'] ?? null) !== 'Migration Papers') {
        throw new RuntimeException('CSL archive collection handoff did not preserve dashed archive collection metadata');
    }
    if (($field['archiveCollection'] ?? null) !== 'Audit Series') {
        throw new RuntimeException('CSL archive collection handoff did not preserve camelCase archive collection metadata');
    }
    if (($legacy['archiveCollection'] ?? null) !== 'Legacy Ledgers') {
        throw new RuntimeException('CSL archive collection handoff did not preserve underscored archive collection metadata');
    }
    if (($city['archiveSummary'] ?? null) !== 'City Archive:Migration Papers:Box 4 [Portland]') {
        throw new RuntimeException('CSL archive collection handoff did not include collection in archive summary metadata');
    }

    foreach ([
        '<p>Archive collections (Ng | Field Notes Library | Audit Series | Folder 2; Repository Desk | Legacy Ledgers | Shelf 8; Smith | City Archive | Migration Papers | Portland | Box 4) keep reviewer locations visible.</p>',
        '<dt>Ng 2025</dt><dd>Field Notes Packet :: Audit Series :: Field Notes Library:Audit Series:Folder 2</dd>',
        '<dt>Repository Desk 2024</dt><dd>Legacy Ledger Packet :: Legacy Ledgers :: Legacy Ledgers:Shelf 8</dd>',
        '<dt>Smith 2026</dt><dd>City Archive Packet :: Migration Papers :: City Archive:Migration Papers:Box 4 [Portland]</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL archive collection handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-archive-collection-handoff self-test passed\n";
    return;
}

echo $blocks;
