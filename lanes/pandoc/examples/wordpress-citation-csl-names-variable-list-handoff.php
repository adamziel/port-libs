<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Names Variable List Review

Review cites [@edited-translation-source] for role-complete source metadata.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "edited-translation-source",
    "type": "book",
    "title": "Edited Translation Packet",
    "editor": [
      {"family": "Curator", "given": "Eli"},
      {"family": "Ng", "given": "Nia"}
    ],
    "translator": [
      {"family": "Translator", "given": "Tia"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Names Variable List Review</title>
    <id>https://example.test/styles/wordpress-citation-names-variable-list-review</id>
    <updated>2026-06-08T20:36:03+00:00</updated>
  </info>
  <locale>
    <terms>
      <term name="editor" form="verb">edited by</term>
      <term name="translator" form="verb">translated by</term>
      <term name="editor" form="short">
        <single>ed.</single>
        <multiple>eds.</multiple>
      </term>
      <term name="translator" form="short">trans.</term>
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
        throw new RuntimeException('CSL names variable-list handoff did not preserve citation variable order');
    }
    if (($citationNames['nameRendering']['label']['form'] ?? null) !== 'verb') {
        throw new RuntimeException('CSL names variable-list handoff did not preserve citation label metadata');
    }
    if (($bibliographyNames['nameRendering']['label']['position'] ?? null) !== 'after') {
        throw new RuntimeException('CSL names variable-list handoff did not preserve bibliography label position');
    }

    foreach ([
        '<p>Review cites (edited by Curator and Ng; translated by Translator 2026) for role-complete source metadata.</p>',
        '<dt>Curator and Ng 2026</dt><dd>Curator, E.; Ng, N., eds.; Translator, T., trans. | Edited Translation Packet</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL names variable-list self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-names-variable-list-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
