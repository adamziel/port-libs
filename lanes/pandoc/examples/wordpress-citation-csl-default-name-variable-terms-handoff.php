<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Default Name Variable Terms

Role terms [@counted-author-packet; @composer-packet; @container-author-packet; @original-author-packet] stay reviewable.
MARKDOWN;

$items = [
    [
        'id' => 'counted-author-packet',
        'type' => 'report',
        'title' => 'Counted Author Packet',
        'author' => [
            ['family' => 'Cruz', 'given' => 'Ana'],
            ['family' => 'Ng', 'given' => 'Nia'],
        ],
        'issued' => ['date-parts' => [[2026]]],
    ],
    [
        'id' => 'composer-packet',
        'type' => 'song',
        'title' => 'Composer Review Score',
        'composer' => [
            ['family' => 'Morton', 'given' => 'Mia'],
        ],
        'issued' => ['date-parts' => [[2025]]],
    ],
    [
        'id' => 'container-author-packet',
        'type' => 'chapter',
        'title' => 'Container Author Chapter',
        'container-author' => [
            ['family' => 'Container', 'given' => 'Casey'],
        ],
        'issued' => ['date-parts' => [[2024]]],
    ],
    [
        'id' => 'original-author-packet',
        'type' => 'book',
        'title' => 'Original Author Translation',
        'original-author' => [
            ['family' => 'Original', 'given' => 'Ora'],
        ],
        'issued' => ['date-parts' => [[2023]]],
    ],
];

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Default Name Variable Term Review</title>
    <id>https://example.test/styles/wordpress-csl-default-name-variable-term-review</id>
    <updated>2026-06-09T02:39:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <choose>
          <if variable="author">
            <names variable="author">
              <name form="count"/>
              <label prefix=" "/>
            </names>
          </if>
        </choose>
        <names variable="composer">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". "/>
        </names>
        <names variable="container-author">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". "/>
        </names>
        <names variable="original-author">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". "/>
        </names>
        <text variable="title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <group delimiter=" | ">
        <choose>
          <if variable="author">
            <names variable="author">
              <name form="count"/>
              <label prefix=" "/>
            </names>
          </if>
        </choose>
        <names variable="composer">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". " name-as-sort-order="all"/>
        </names>
        <names variable="container-author">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". " name-as-sort-order="all"/>
        </names>
        <names variable="original-author">
          <label form="verb" suffix=" "/>
          <name form="long" initialize-with=". " name-as-sort-order="all"/>
        </names>
      </group>
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
    if (($summary['citationRendering'][0]['children'][0]['branches'][0]['children'][0]['nameRendering']['form'] ?? null) !== 'count') {
        throw new RuntimeException('CSL default name-variable term handoff did not preserve author count rendering metadata');
    }
    if (($summary['citationRendering'][0]['children'][1]['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL default name-variable term handoff did not preserve composer verb label metadata');
    }
    if (($summary['bibliographyRendering'][0]['children'][2]['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL default name-variable term handoff did not preserve container-author verb label metadata');
    }

    foreach ([
        '<p>Role terms [2 | Counted Author Packet; composed by Morton | Composer Review Score; by Container | Container Author Chapter; by Original | Original Author Translation] stay reviewable.</p>',
        '<dt>2 2026</dt><dd>2 :: Counted Author Packet</dd>',
        '<dt>Composer Review Score 2025</dt><dd>composed by Morton, M. :: Composer Review Score</dd>',
        '<dt>Container Author Chapter 2024</dt><dd>by Container, C. :: Container Author Chapter</dd>',
        '<dt>Original Author Translation 2023</dt><dd>by Original, O. :: Original Author Translation</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL default name-variable term self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-default-name-variable-terms-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
