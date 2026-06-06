<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Locale Precedence Review

Review cites [@locale-source; @undated-locale-source] for localized bibliography handoff.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "locale-source",
    "type": "webpage",
    "title": "Locale Source Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026]]},
    "accessed": {"date-parts": [[2026, 6, 6]]},
    "URL": "https://example.test/locale-source"
  },
  {
    "id": "undated-locale-source",
    "type": "report",
    "title": "Undated Locale Packet",
    "issued": {}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Locale Precedence Review</title>
    <id>https://example.test/styles/wordpress-citation-locale-precedence-review</id>
    <updated>2026-06-06T08:35:56+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <terms>
      <term name="and">and exact</term>
      <term name="accessed">Inspected</term>
      <term name="no date">exact n.d.</term>
    </terms>
  </locale>
  <locale xml:lang="en">
    <terms>
      <term name="and">and generic</term>
      <term name="accessed">Retrieved</term>
      <term name="no date">generic n.d.</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; "/>
  </citation>
  <bibliography>
    <layout/>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['terms']['accessed'] ?? null) !== 'Inspected') {
        throw new RuntimeException('CSL exact locale handoff did not preserve the accessed term');
    }
    if (($summary['terms']['noDate'] ?? null) !== 'exact n.d.') {
        throw new RuntimeException('CSL exact locale handoff did not preserve the no-date term');
    }

    foreach ([
        '<p>Review cites (Smith and exact Ng 2026; Undated Locale Packet exact n.d.) for localized bibliography handoff.</p>',
        '<dt>Smith and exact Ng 2026</dt><dd>Smith, Ada; Ng, Nia. Locale Source Packet. 2026. https://example.test/locale-source. Inspected 2026-06-06.</dd>',
        '<dt>Undated Locale Packet exact n.d.</dt><dd>Undated Locale Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL locale precedence self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-locale-precedence-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
