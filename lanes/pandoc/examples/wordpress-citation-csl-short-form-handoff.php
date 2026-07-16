<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Short Form Review

Reviewer packets cite [@reviewer-guide] and [@fallback-title] while preserving compact source labels.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "reviewer-guide",
    "type": "article-journal",
    "title": "Migration Manual: Reviewer Packet Guide",
    "title-short": "Reviewer Guide",
    "container-title": "Journal of Imported Sources",
    "container-title-short": "J. Import. Sources",
    "author": [
      {"family": "Curator", "given": "Eli"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "fallback-title",
    "type": "paper-conference",
    "title": "Full Report Packet",
    "container-title": "Migration Proceedings",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Short Form Review</title>
    <id>https://example.test/styles/wordpress-citation-short-form-review</id>
    <updated>2026-06-05T14:25:21+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title" form="short"/>
        <text variable="container-title" form="short"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title" form="short"/>
      <text variable="container-title" form="short"/>
      <text variable="title"/>
      <text variable="container-title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][0]['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL short-form handoff did not preserve title form metadata');
    }
    if (($summary['citationRendering'][0]['children'][1]['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL short-form handoff did not preserve container-title form metadata');
    }
    if (($processor->item('reviewer-guide')['shortTitle'] ?? null) !== 'Reviewer Guide') {
        throw new RuntimeException('CSL short-form handoff did not normalize title-short metadata');
    }

    foreach ([
        '<p>Reviewer packets cite [Reviewer Guide | J. Import. Sources] and [Full Report Packet | Migration Proceedings] while preserving compact source labels.</p>',
        '<dt>Curator 2026</dt><dd>Reviewer Guide :: J. Import. Sources :: Migration Manual: Reviewer Packet Guide :: Journal of Imported Sources</dd>',
        '<dt>Ng 2025</dt><dd>Full Report Packet :: Migration Proceedings :: Full Report Packet :: Migration Proceedings</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL short-form self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-short-form-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
