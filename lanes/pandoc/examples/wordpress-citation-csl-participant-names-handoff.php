<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Participant sources [@participant-source; @recipient-source] keep CSL role names visible.';

$items = [
    [
        'id' => 'participant-source',
        'type' => 'book',
        'title' => 'Participant Source Packet',
        'chair' => [
            ['literal' => 'Program Committee', 'annotations' => [['part' => 'name', 'value' => 'agenda verified']]],
        ],
        'collection-editor' => [
            ['family' => 'Curator', 'given' => 'Eli'],
        ],
        'composer' => [
            ['family' => 'Morton', 'given' => 'Mia'],
        ],
        'contributor' => [
            ['literal' => 'Migration Contributors'],
        ],
        'editor-translator' => [
            ['family' => 'Garcia', 'given' => 'Gia'],
        ],
        'recipient' => [
            ['family' => 'Reader', 'given' => 'Rhea', 'annotations' => [['part' => 'family', 'value' => 'recipient family verified']]],
        ],
        'issued' => ['date-parts' => [[2026]]],
    ],
    [
        'id' => 'recipient-source',
        'type' => 'personal_communication',
        'title' => 'Recipient Source Packet',
        'recipient' => [
            ['literal' => 'Editorial Desk'],
        ],
        'issued' => ['date-parts' => [[2025]]],
    ],
];

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Participant Name Variable Review Style</title>
    <id>https://example.test/styles/bounded-participant-name-variable-review</id>
    <updated>2026-06-07T14:21:35+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="chair"/>
        <names variable="collection-editor"/>
        <names variable="composer"/>
        <names variable="contributor"/>
        <names variable="editor-translator"/>
        <names variable="recipient"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="chair"/>
      <names variable="collection-editor"/>
      <names variable="composer"/>
      <names variable="contributor"/>
      <names variable="editor-translator"/>
      <names variable="recipient"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<p>Participant sources (Program Committee | Curator | Morton | Migration Contributors | Garcia | Reader; Editorial Desk) keep CSL role names visible.</p>',
        '<dt>Participant Source Packet 2026</dt><dd>Participant Source Packet :: Program Committee :: Curator, Eli :: Morton, Mia :: Migration Contributors :: Garcia, Gia :: Reader, Rhea :: Chair 1: agenda verified; Recipient 1 family: recipient family verified</dd>',
        '<dt>Recipient Source Packet 2025</dt><dd>Recipient Source Packet :: Editorial Desk</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL participant-name handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-participant-names-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
