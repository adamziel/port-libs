<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Source Attachment Review

Attachment review packets cite [@attachment-review; @manual-attachment] while keeping importable files and rejected paths visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "attachment-review",
    "type": "webpage",
    "title": "Attachment Review Packet",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2026, 6, 7]]},
    "sourceFiles": [
      {"label": "Review PDF", "path": "attachments/source-audit.pdf", "mediaType": "application/pdf"},
      {"label": "Reviewer Notes", "path": "attachments/reviewer notes.html", "mediaType": "text/html"},
      {"label": "Remote PDF", "path": "https://example.test/source-audit.pdf", "mediaType": "application/pdf"}
    ]
  },
  {
    "id": "manual-attachment",
    "type": "document",
    "title": "Manual Attachment Packet",
    "issued": {"date-parts": [[2025]]},
    "sourceFiles": [
      "attachments/manual.pdf"
    ]
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Source File Review</title>
    <id>https://example.test/styles/wordpress-csl-source-file-review</id>
    <updated>2026-06-09T07:33:58+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <choose>
        <if variable="source-file-summary" match="any">
          <group delimiter=" | ">
            <text variable="title"/>
            <text variable="source-file-summary"/>
            <text variable="source-file-diagnostic-summary"/>
          </group>
        </if>
        <else>
          <text value="missing-source-file-summary"/>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="source-file-paths"/>
      <text variable="source-file-diagnostic-reasons"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $branch = $summary['citationRendering'][0]['branches'][0] ?? [];

    if (($branch['variables'][0] ?? null) !== 'source-file-summary') {
        throw new RuntimeException('CSL source-file handoff did not preserve source-file-summary condition metadata');
    }
    if (($summary['bibliographyRendering'][1]['variable'] ?? null) !== 'source-file-paths') {
        throw new RuntimeException('CSL source-file handoff did not preserve bibliography source-file-paths metadata');
    }

    $attachment = $processor->item('attachment-review');
    if (($attachment['sourceFiles'][0]['path'] ?? null) !== 'attachments/source-audit.pdf') {
        throw new RuntimeException('CSL source-file handoff did not keep importable source attachment paths');
    }
    if (($attachment['sourceFileDiagnostics'][0]['reason'] ?? null) !== 'remote-uri') {
        throw new RuntimeException('CSL source-file handoff did not keep rejected source attachment diagnostics');
    }

    foreach ([
        '<p>Attachment review packets cite [Attachment Review Packet | Review PDF: attachments/source-audit.pdf (application/pdf); Reviewer Notes: attachments/reviewer notes.html (text/html) | Remote PDF: remote-uri (https://example.test/source-audit.pdf); Manual Attachment Packet | attachments/manual.pdf] while keeping importable files and rejected paths visible.</p>',
        '<dt>Ng 2026</dt><dd>Attachment Review Packet :: attachments/source-audit.pdf; attachments/reviewer notes.html :: remote-uri</dd>',
        '<dt>Manual Attachment Packet 2025</dt><dd>Manual Attachment Packet :: attachments/manual.pdf</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL source-file self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-source-file-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
