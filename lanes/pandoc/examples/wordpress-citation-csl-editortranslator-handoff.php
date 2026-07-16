<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Editortranslator Review

Review cites [@edited-translated-source] for combined editor and translator metadata.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "edited-translated-source",
    "type": "book",
    "title": "Edited and Translated Packet",
    "editor": [
      {"family": "Curator", "given": "Eli"},
      {"family": "Ng", "given": "Nia"}
    ],
    "translator": [
      {"family": "Curator", "given": "Eli"},
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Editortranslator Review</title>
    <id>https://example.test/styles/wordpress-citation-editortranslator-review</id>
    <updated>2026-06-08T22:05:18+00:00</updated>
  </info>
  <locale>
    <terms>
      <term name="editortranslator" form="verb">edited and translated by</term>
      <term name="editortranslator" form="short">
        <single>ed. &amp; trans.</single>
        <multiple>eds. &amp; trans.</multiple>
      </term>
      <term name="editor" form="verb">edited by</term>
      <term name="translator" form="verb">translated by</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")">
      <group delimiter=" ">
        <names variable="editor translator" delimiter="; ">
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
      <names variable="editor translator" delimiter="; ">
        <name initialize-with=". " name-as-sort-order="all"/>
        <label form="short" plural="contextual" prefix=", "/>
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
    $citationNames = $summary['citationRendering'][0]['children'][0] ?? [];
    $bibliographyNames = $summary['bibliographyRendering'][0] ?? [];
    if (($citationNames['variable'] ?? null) !== 'editor translator') {
        throw new RuntimeException('CSL editortranslator handoff did not preserve citation variable list');
    }
    if (($citationNames['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL editortranslator handoff did not preserve citation verb label metadata');
    }
    if (($bibliographyNames['nameRendering']['label']['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL editortranslator handoff did not preserve bibliography short label metadata');
    }

    foreach ([
        '<p>Review cites (edited and translated by Curator and Ng 2026) for combined editor and translator metadata.</p>',
        '<dt>Curator and Ng 2026</dt><dd>Curator, E.; Ng, N., eds. &amp; trans. | Edited and Translated Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL editortranslator self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-editortranslator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
