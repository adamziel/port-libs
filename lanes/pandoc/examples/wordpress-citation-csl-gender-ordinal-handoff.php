<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Gendered Ordinal Review

Gendered ordinal imports [@edition-one; @edition-three] keep localized edition and month labels visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "edition-one",
    "type": "book",
    "title": "First Edition Packet",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026, 1, 1]]},
    "edition": "1",
    "chapter-number": "1"
  },
  {
    "id": "edition-three",
    "type": "book",
    "title": "Third Edition Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026, 4, 1]]},
    "edition": "3",
    "chapter-number": "1"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="fr-FR">
  <info>
    <title>WordPress Citation Gendered Ordinal Review</title>
    <id>https://example.test/styles/wordpress-citation-gendered-ordinal-review</id>
    <updated>2026-06-08T20:54:18+00:00</updated>
  </info>
  <locale xml:lang="fr-FR">
    <terms>
      <term name="edition" gender="feminine"><single>édition</single><multiple>éditions</multiple></term>
      <term name="edition" form="short">éd.</term>
      <term name="chapter" gender="masculine"><single>chapitre</single><multiple>chapitres</multiple></term>
      <term name="month-01" gender="masculine">janvier</term>
      <term name="month-04" gender="feminine">avril</term>
      <term name="ordinal">e</term>
      <term name="ordinal-01" gender-form="feminine" match="whole-number">re</term>
      <term name="ordinal-01" gender-form="masculine" match="whole-number">er</term>
      <term name="long-ordinal-01" gender-form="masculine">premier</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <number variable="edition" form="ordinal"/>
        <number variable="chapter-number" form="long-ordinal"/>
        <date variable="issued" delimiter=" ">
          <date-part name="day" form="ordinal"/>
          <date-part name="month" form="long"/>
          <date-part name="year"/>
        </date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="edition" form="short"/>
        <number variable="edition" form="ordinal"/>
      </group>
      <group delimiter=" ">
        <label variable="chapter-number"/>
        <number variable="chapter-number" form="long-ordinal"/>
      </group>
      <date variable="issued" delimiter=" ">
        <date-part name="day" form="ordinal"/>
        <date-part name="month" form="long"/>
        <date-part name="year"/>
      </date>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['title'] ?? '') !== 'WordPress Citation Gendered Ordinal Review') {
        throw new RuntimeException('CSL gendered ordinal handoff did not preserve style metadata');
    }

    foreach ([
        '<p>Gendered ordinal imports (Smith | 1re | premier | 1er janvier 2026; Ng | 3e | premier | 1re avril 2026) keep localized edition and month labels visible.</p>',
        '<dt>Smith 2026</dt><dd>First Edition Packet :: éd. 1re :: chapitre premier :: 1er janvier 2026</dd>',
        '<dt>Ng 2026</dt><dd>Third Edition Packet :: éd. 3e :: chapitre premier :: 1re avril 2026</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL gendered ordinal self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-gender-ordinal-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
