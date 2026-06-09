<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Multi-Variable Substitute Review

Multi-variable substitute cites [@edited-translation; @translated-only] before review.
MARKDOWN;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Multi Variable Substitute Review</title>
    <id>https://example.test/styles/wordpress-csl-multi-variable-substitute-review</id>
    <updated>2026-06-09T06:28:40+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author">
          <substitute>
            <names variable="editor translator"/>
            <text variable="title"/>
          </substitute>
        </names>
        <names variable="translator" prefix="translated by "/>
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="author">
        <name initialize-with=". " name-as-sort-order="all"/>
        <substitute>
          <names variable="editor translator">
            <name initialize-with=". " name-as-sort-order="all"/>
          </names>
          <text variable="title"/>
        </substitute>
      </names>
      <names variable="translator" prefix="translated by ">
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <text variable="title"/>
      <date variable="issued"><date-part name="year"/></date>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromItems([
    [
        'id' => 'edited-translation',
        'type' => 'book',
        'title' => 'Translated Review Packet',
        'editor' => [
            ['family' => 'Curator', 'given' => 'Eli'],
        ],
        'translator' => [
            ['family' => 'Tran', 'given' => 'Mina'],
        ],
        'issued' => ['date-parts' => [[2026]]],
    ],
    [
        'id' => 'translated-only',
        'type' => 'book',
        'title' => 'Translated Only Packet',
        'translator' => [
            ['family' => 'Ivers', 'given' => 'Noa'],
        ],
        'issued' => ['date-parts' => [[2025]]],
    ],
])->withCslStyle($styleXml);

$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['title'] ?? null) !== 'WordPress CSL Multi Variable Substitute Review') {
        throw new RuntimeException('CSL multi-variable substitute handoff did not preserve the style summary title');
    }

    if (($summary['citationRendering'][0]['children'][0]['substitute'][0]['variable'] ?? null) !== 'editor translator') {
        throw new RuntimeException('CSL multi-variable substitute handoff did not preserve the names substitute variable list');
    }

    if (($summary['citationRendering'][0]['children'][1]['variable'] ?? null) !== 'translator') {
        throw new RuntimeException('CSL multi-variable substitute handoff did not preserve the later translator variable');
    }

    foreach ([
        '<p>Multi-variable substitute cites (Curator | translated by Tran | Translated Review Packet | 2026; Ivers | Translated Only Packet | 2025) before review.</p>',
        '<dt>Curator 2026</dt><dd>Curator, E. :: translated by Tran, M. :: Translated Review Packet :: 2026</dd>',
        '<dt>Ivers 2025</dt><dd>Ivers, N. :: Translated Only Packet :: 2025</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL multi-variable substitute handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-multi-variable-substitute-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
