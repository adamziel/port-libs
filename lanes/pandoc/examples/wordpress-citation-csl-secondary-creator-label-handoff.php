<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Secondary Creator Label Review

Secondary creator credits cite [@secondary-credit-packet] while keeping plural CSL role labels visible for review.
MARKDOWN;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Secondary Creator Label Review</title>
    <id>https://example.test/styles/wordpress-csl-secondary-creator-label-review</id>
    <updated>2026-06-09T05:10:12+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")">
      <group delimiter=" | ">
        <names variable="commentator"><name/><label form="long" prefix=", "/></names>
        <names variable="annotator"><name/><label form="long" prefix=", "/></names>
        <names variable="redactor"><name/><label form="long" plural="always" prefix=", "/></names>
        <names variable="collaborator"><name/><label form="long" prefix=", "/></names>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="founder"><name initialize-with=". " name-as-sort-order="all"/><label form="long" plural="always" prefix=", "/></names>
      <names variable="continuator"><name initialize-with=". " name-as-sort-order="all"/><label form="long" prefix=", "/></names>
      <names variable="reviser"><name initialize-with=". " name-as-sort-order="all"/><label form="long" prefix=", "/></names>
      <names variable="introduction"><name initialize-with=". " name-as-sort-order="all"/><label form="long" prefix=", "/></names>
      <names variable="foreword"><name initialize-with=". " name-as-sort-order="all"/><label form="long" prefix=", "/></names>
      <names variable="afterword"><name initialize-with=". " name-as-sort-order="all"/><label form="long" prefix=", "/></names>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems([
    [
        'id' => 'secondary-credit-packet',
        'type' => 'book',
        'title' => 'Secondary Credit Packet',
        'issued' => ['date-parts' => [[2026]]],
        'commentator' => [
            ['family' => 'Roe', 'given' => 'Pat'],
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'annotator' => [
            ['family' => 'Lee', 'given' => 'Ira'],
            ['family' => 'Kim', 'given' => 'Mina'],
        ],
        'redactor' => [
            ['family' => 'Diaz', 'given' => 'Ana'],
        ],
        'collaborator' => [
            ['literal' => 'Review Desk'],
            ['family' => 'Iqbal', 'given' => 'Iman'],
        ],
        'founder' => [
            ['literal' => 'Archive Founders Guild'],
        ],
        'continuator' => [
            ['family' => 'Singh', 'given' => 'Tara'],
            ['family' => 'Park', 'given' => 'Eva'],
        ],
        'reviser' => [
            ['family' => 'Cruz', 'given' => 'Cam'],
            ['family' => 'Lopez', 'given' => 'Luz'],
        ],
        'introduction' => [
            ['family' => 'Khan', 'given' => 'Noor'],
            ['family' => 'Stone', 'given' => 'Sam'],
        ],
        'foreword' => [
            ['family' => 'Mills', 'given' => 'Mo'],
            ['family' => 'Nash', 'given' => 'Nia'],
        ],
        'afterword' => [
            ['family' => 'Young', 'given' => 'Yara'],
            ['family' => 'Zed', 'given' => 'Zoe'],
        ],
    ],
])->withCslStyle($styleXml);

$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['title'] ?? null) !== 'WordPress CSL Secondary Creator Label Review') {
        throw new RuntimeException('CSL secondary creator label handoff did not preserve the style summary title');
    }

    foreach ([
        '<p>Secondary creator credits cite (Roe and Ng, commentators | Lee and Kim, annotators | Diaz, redactors | Review Desk and Iqbal, collaborators | 2026) while keeping plural CSL role labels visible for review.</p>',
        '<dt>Secondary Credit Packet 2026</dt><dd>Secondary Credit Packet :: Archive Founders Guild, founders :: Singh, T.; Park, E., continuators :: Cruz, C.; Lopez, L., revisers :: Khan, N.; Stone, S., introductions :: Mills, M.; Nash, N., forewords :: Young, Y.; Zed, Z., afterwords</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL secondary creator label self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-secondary-creator-label-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
