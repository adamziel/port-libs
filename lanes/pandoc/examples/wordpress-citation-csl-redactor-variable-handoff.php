<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Redactor Variable Review

Editorial packet [@redactor-text-source] keeps CSL text and names redactor output aligned.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "redactor-text-source",
    "type": "manuscript",
    "title": "Redacted Source Dossier",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "redactor": [
      {"family": "Roe", "given": "Pat"},
      {"literal": "Migration Desk"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress Redactor Variable Review</title>
    <id>https://example.test/styles/wordpress-redactor-variable-review</id>
    <updated>2026-06-08T12:39:34+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text value="redactor-text"/>
        <text variable="redactor"/>
        <names variable="redactor"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="redactor"/>
      <names variable="redactor"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
    if (($citationChildren[1]['type'] ?? null) !== 'text' || ($citationChildren[1]['variable'] ?? null) !== 'redactor') {
        throw new RuntimeException('CSL redactor text variable handoff did not preserve text variable metadata');
    }
    if (($citationChildren[2]['type'] ?? null) !== 'names' || ($citationChildren[2]['variable'] ?? null) !== 'redactor') {
        throw new RuntimeException('CSL redactor text variable handoff did not preserve names variable metadata');
    }
    foreach ([
        '<p>Editorial packet [redactor-text | Roe and Migration Desk | Roe and Migration Desk] keeps CSL text and names redactor output aligned.</p>',
        '<dt>Smith 2026</dt><dd>Redacted Source Dossier :: Roe, Pat; Migration Desk :: Roe, Pat; Migration Desk</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL redactor variable handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-redactor-variable-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
