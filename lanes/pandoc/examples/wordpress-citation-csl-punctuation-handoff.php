<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Quote Punctuation Review

The review packet cites @quote-source before the bibliography is inspected.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "quote-source",
    "type": "article-journal",
    "title": "source packet",
    "container-title": "Review Journal.",
    "author": [
      {"literal": "Quote Desk"}
    ],
    "issued": {"date-parts": [[2026]]},
    "publisher": "Review Press"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Quote Punctuation Review</title>
    <id>https://example.test/styles/wordpress-citation-quote-punctuation-review</id>
    <updated>2026-06-05T11:08:28+00:00</updated>
  </info>
  <locale xml:lang="en-US">
    <style-options punctuation-in-quote="true"/>
    <terms>
      <term name="open-quote">“</term>
      <term name="close-quote">”</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=", ">
        <names variable="author"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". ">
      <text variable="title" quotes="true" text-case="title"/>
      <text variable="container-title" quotes="true"/>
      <text variable="publisher"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['localeOptions']['punctuationInQuote'] ?? null) !== true) {
        throw new RuntimeException('CSL punctuation-in-quote option was not preserved');
    }

    foreach ([
        '<p>The review packet cites Quote Desk (2026) before the bibliography is inspected.</p>',
        '<dt>Quote Desk 2026</dt><dd>“Source Packet.” “Review Journal.” Review Press</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL punctuation-in-quote self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-punctuation-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
