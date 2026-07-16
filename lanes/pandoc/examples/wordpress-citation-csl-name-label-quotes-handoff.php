<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Quoted role labels cite [@quoted-editor-source] before review.';

$items = [
    [
        'id' => 'quoted-editor-source',
        'type' => 'book',
        'title' => 'Quoted Label Packet',
        'editor' => [
            ['family' => 'Curator', 'given' => 'Eli'],
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2026]]],
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Name Label Quote Review</title>
    <id>https://example.test/styles/wordpress-citation-name-label-quote-review</id>
    <updated>2026-06-09T02:13:08+00:00</updated>
  </info>
  <locale>
    <terms>
      <term name="open-quote">"</term>
      <term name="close-quote">"</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")">
      <group delimiter=" ">
        <names variable="editor" delimiter=", ">
          <label form="short" plural="always" suffix=" " quotes="true"/>
          <name initialize-with=". "/>
        </names>
        <date variable="issued">
          <date-part name="year"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <names variable="editor" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
        <label form="short" plural="always" prefix=", " quotes="true"/>
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
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['label']['quotes'] ?? null) !== true) {
        throw new RuntimeException('CSL name-label quote handoff did not preserve citation label quote metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['label']['quotes'] ?? null) !== true) {
        throw new RuntimeException('CSL name-label quote handoff did not preserve bibliography label quote metadata');
    }

    foreach ([
        '<p>Quoted role labels cite (&quot;eds.&quot; Curator and Ng 2026) before review.</p>',
        '<dt>Curator and Ng 2026</dt><dd>Curator, E.; Ng, N., &quot;eds.&quot; | Quoted Label Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL name-label quote self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-name-label-quotes-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
