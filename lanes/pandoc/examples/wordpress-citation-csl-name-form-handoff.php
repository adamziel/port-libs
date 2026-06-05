<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Name Form Review

Reviewer packets cite [@compact-source] and [@literal-source] with compact creator labels.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "compact-source",
    "type": "report",
    "title": "Compact Reviewer Packet",
    "author": [
      {"family": "Cruz", "given": "Ana Maria", "non-dropping-particle": "de la", "suffix": "Jr.", "comma-suffix": true},
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]},
    "URL": "https://example.test/compact-source"
  },
  {
    "id": "literal-source",
    "type": "webpage",
    "title": "Literal Reviewer Packet",
    "author": [
      {"literal": "Migration Desk"}
    ],
    "issued": {"date-parts": [[2025]]},
    "URL": "https://example.test/literal-source"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Name Form Review</title>
    <id>https://example.test/styles/wordpress-citation-name-form-review</id>
    <updated>2026-06-05T16:34:38+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author" delimiter=", ">
          <name form="short"/>
        </names>
        <group delimiter=" ">
          <names variable="author">
            <name form="count"/>
          </names>
          <text value="contributors"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <names variable="author" delimiter="; ">
        <name form="short" name-as-sort-order="all">
          <name-part name="family" text-case="uppercase"/>
        </name>
      </names>
      <names variable="author">
        <name form="count"/>
      </names>
      <text variable="title"/>
      <text variable="URL"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['nameRendering']['citation']['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL name-form handoff did not preserve citation short form metadata');
    }
    if (($summary['bibliographyRendering'][1]['nameRendering']['form'] ?? null) !== 'count') {
        throw new RuntimeException('CSL name-form handoff did not preserve bibliography count form metadata');
    }

    foreach ([
        '<p>Reviewer packets cite [de la Cruz and Ng | 2 contributors] and [Migration Desk | 1 contributors] with compact creator labels.</p>',
        '<dt>de la Cruz and Ng 2026</dt><dd>DE LA CRUZ; NG :: 2 :: Compact Reviewer Packet :: https://example.test/compact-source</dd>',
        '<dt>Migration Desk 2025</dt><dd>Migration Desk :: 1 :: Literal Reviewer Packet :: https://example.test/literal-source</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL name-form self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-name-form-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
