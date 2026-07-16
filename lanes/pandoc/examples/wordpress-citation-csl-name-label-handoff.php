<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Name Label Review

Review cites [@editor-source; @translator-source] for role-sensitive source packets.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "editor-source",
    "type": "book",
    "title": "Edited Review Packet",
    "editor": [
      {"family": "Curator", "given": "Eli"},
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "translator-source",
    "type": "book",
    "title": "Translated Review Packet",
    "translator": [
      {"family": "Translator", "given": "Tia"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Name Label Review</title>
    <id>https://example.test/styles/wordpress-citation-name-label-review</id>
    <updated>2026-06-07T02:24:56+00:00</updated>
  </info>
  <locale>
    <terms>
      <term name="editor" form="verb">edited by</term>
      <term name="translator" form="verb">translated by</term>
      <term name="translator" form="verb-short">trans.</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="editor translator" delimiter=", ">
          <label form="verb" suffix=" "/>
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
        <label form="short" plural="always" prefix=", "/>
      </names>
      <names variable="translator">
        <label form="verb-short" suffix=" "/>
        <name initialize-with=". " name-as-sort-order="all"/>
      </names>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][0]['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL name-label handoff did not preserve citation verb label metadata');
    }
    if (($summary['bibliographyRendering'][0]['nameRendering']['label']['position'] ?? null) !== 'after') {
        throw new RuntimeException('CSL name-label handoff did not preserve bibliography label position metadata');
    }
    if (($summary['bibliographyRendering'][1]['nameRendering']['label']['form'] ?? null) !== 'verb-short') {
        throw new RuntimeException('CSL name-label handoff did not preserve translator verb-short metadata');
    }

    foreach ([
        '<p>Review cites (edited by Curator and Ng 2026; translated by Translator 2025) for role-sensitive source packets.</p>',
        '<dt>Curator and Ng 2026</dt><dd>Curator, E.; Ng, N., eds. | Edited Review Packet</dd>',
        '<dt>Translator 2025</dt><dd>trans. Translator, T. | Translated Review Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL name-label self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-name-label-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
