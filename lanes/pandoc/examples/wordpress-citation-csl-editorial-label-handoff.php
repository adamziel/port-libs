<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Editorial credits [@editorial-credit-packet; @review-credit-packet] keep CSL role labels visible.';

$items = [
    [
        'id' => 'editorial-credit-packet',
        'type' => 'book',
        'title' => 'Editorial Credit Packet',
        'issued' => ['date-parts' => [[2026]]],
        'compiler' => [
            ['family' => 'Roe', 'given' => 'Pat'],
            ['literal' => 'Migration Desk'],
        ],
        'curator' => [
            ['family' => 'Curator', 'given' => 'Eli'],
        ],
        'director' => [
            ['family' => 'Director', 'given' => 'Dia'],
        ],
        'illustrator' => [
            ['family' => 'Illustrator', 'given' => 'Iris'],
        ],
    ],
    [
        'id' => 'review-credit-packet',
        'type' => 'interview',
        'title' => 'Review Credit Packet',
        'issued' => ['date-parts' => [[2025]]],
        'chair' => [
            ['literal' => 'Review Chair'],
        ],
        'collection-editor' => [
            ['family' => 'Collection', 'given' => 'Casey'],
        ],
        'editorial-director' => [
            ['family' => 'Editorial', 'given' => 'Eden'],
        ],
        'contributor' => [
            ['literal' => 'Open Review Desk'],
        ],
        'interviewer' => [
            ['family' => 'Interviewer', 'given' => 'Inez'],
        ],
        'reviewed-author' => [
            ['family' => 'Reviewed', 'given' => 'Riley'],
        ],
        'recipient' => [
            ['family' => 'Reader', 'given' => 'Rhea'],
        ],
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Editorial Creator Label Review</title>
    <id>https://example.test/styles/wordpress-editorial-creator-label-review</id>
    <updated>2026-06-08T22:46:56+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="compiler"><label form="verb" suffix=" "/><name/></names>
        <names variable="curator"><label form="verb-short" suffix=" "/><name/></names>
        <names variable="director"><label form="short" suffix=" "/><name/></names>
        <names variable="interviewer"><label form="verb" suffix=" "/><name/></names>
        <names variable="reviewed-author"><label form="verb" suffix=" "/><name/></names>
        <names variable="recipient"><label form="verb" suffix=" "/><name/></names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="compiler"><name initialize-with=". " name-as-sort-order="all"/><label form="short" plural="always" prefix=", "/></names>
      <names variable="curator"><label form="long" plural="never" suffix=": "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="director"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="illustrator"><label form="verb-short" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="chair"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="collection-editor"><name initialize-with=". " name-as-sort-order="all"/><label form="short" plural="never" prefix=", "/></names>
      <names variable="editorial-director"><name initialize-with=". " name-as-sort-order="all"/><label form="short" plural="never" prefix=", "/></names>
      <names variable="contributor"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="interviewer"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="reviewed-author"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
      <names variable="recipient"><label form="verb" suffix=" "/><name initialize-with=". " name-as-sort-order="all"/></names>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems($items)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
    if (($citationChildren[0]['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL editorial creator label handoff did not preserve compiler verb label metadata');
    }

    if (($citationChildren[1]['nameRendering']['label']['form'] ?? null) !== 'verb-short') {
        throw new RuntimeException('CSL editorial creator label handoff did not preserve curator verb-short label metadata');
    }

    foreach ([
        '<p>Editorial credits (compiled by Roe and Migration Desk | cur. by Curator | dir. Director | 2026; interview by Interviewer | by Reviewed | to Reader | 2025) keep CSL role labels visible.</p>',
        '<dt>Editorial Credit Packet 2026</dt><dd>Editorial Credit Packet :: Roe, P.; Migration Desk, comps. :: curator: Curator, E. :: directed by Director, D. :: ill. by Illustrator, I.</dd>',
        '<dt>Review Credit Packet 2025</dt><dd>Review Credit Packet :: chaired by Review Chair :: Collection, C., ed. :: Editorial, E., ed. :: with Open Review Desk :: interview by Interviewer, I. :: by Reviewed, R. :: to Reader, R.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL editorial creator label handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-editorial-label-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
