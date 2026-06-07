<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Original date text [@reprint-source; @literal-original] remains visible in review output.';

$items = [
    [
        'id' => 'reprint-source',
        'type' => 'book',
        'title' => 'Reprinted Packet',
        'author' => [
            ['family' => 'Smith', 'given' => 'Ada'],
        ],
        'issued' => ['date-parts' => [[2026]]],
        'original-date' => ['date-parts' => [[1910, 5, 1]]],
    ],
    [
        'id' => 'literal-original',
        'type' => 'manuscript',
        'title' => 'Literal Original Packet',
        'author' => [
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2025]]],
        'original-date' => ['literal' => 'undated manuscript'],
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress Citation Original Date Text Review</title>
    <id>https://example.test/styles/wordpress-citation-original-date-text-review</id>
    <updated>2026-06-07T16:36:53+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-date" prefix="orig "/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-date" prefix="original "/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][1]['variable'] ?? null) !== 'original-date') {
        throw new RuntimeException('Citation CSL original-date text handoff did not preserve citation variable metadata');
    }
    if (($summary['bibliographyRendering'][1]['prefix'] ?? null) !== 'original ') {
        throw new RuntimeException('Citation CSL original-date text handoff did not preserve bibliography prefix metadata');
    }

    foreach ([
        '<p>Original date text (Smith | orig 1910-05-01; Ng | orig undated manuscript) remains visible in review output.</p>',
        '<dt>Smith 2026</dt><dd>Reprinted Packet :: original 1910-05-01</dd>',
        '<dt>Ng 2025</dt><dd>Literal Original Packet :: original undated manuscript</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation CSL original-date text handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-original-date-text-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
