<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Extended Creator List Review

Extended creator credits cite [@extended-credit-packet] while keeping grouped CSL role labels visible for review.
MARKDOWN;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Extended Creator List Review</title>
    <id>https://example.test/styles/wordpress-csl-extended-creator-list-review</id>
    <updated>2026-06-09T05:27:46+00:00</updated>
  </info>
  <locale>
    <terms>
      <term name="founder" form="verb">founded by</term>
      <term name="continuator" form="verb">continued by</term>
      <term name="reviser" form="verb">revised by</term>
      <term name="collaborator" form="verb">with</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")">
      <group delimiter=" | ">
        <names variable="founder continuator reviser collaborator" delimiter="; ">
          <label form="verb" suffix=" "/>
          <name/>
        </names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="founder continuator reviser collaborator" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
        <label form="long" plural="contextual" prefix=", "/>
      </names>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems([
    [
        'id' => 'extended-credit-packet',
        'type' => 'book',
        'title' => 'Extended Credit Packet',
        'issued' => ['date-parts' => [[2026]]],
        'founder' => [
            ['family' => 'Roe', 'given' => 'Pat'],
        ],
        'continuator' => [
            ['family' => 'Ng', 'given' => 'Nia'],
            ['family' => 'Park', 'given' => 'Eva'],
        ],
        'reviser' => [
            ['literal' => 'Revision Desk'],
        ],
        'collaborator' => [
            ['literal' => 'Source Review Desk'],
            ['family' => 'Iqbal', 'given' => 'Iman'],
        ],
    ],
])->withCslStyle($styleXml);

$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['title'] ?? null) !== 'WordPress CSL Extended Creator List Review') {
        throw new RuntimeException('CSL extended creator list handoff did not preserve the style summary title');
    }

    $citationNames = $summary['citationRendering'][0]['children'][0] ?? [];
    if (($citationNames['variable'] ?? null) !== 'founder continuator reviser collaborator') {
        throw new RuntimeException('CSL extended creator list handoff did not preserve the multi-variable names element');
    }

    if (($citationNames['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL extended creator list handoff did not preserve the names child label');
    }

    foreach ([
        '<p>Extended creator credits cite (founded by Roe; continued by Ng and Park; revised by Revision Desk; with Source Review Desk and Iqbal | 2026) while keeping grouped CSL role labels visible for review.</p>',
        '<dt>Extended Credit Packet 2026</dt><dd>Extended Credit Packet :: Roe, P., founder; Ng, N.; Park, E., continuators; Revision Desk, reviser; Source Review Desk; Iqbal, I., collaborators</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL extended creator list self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-extended-creator-list-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
