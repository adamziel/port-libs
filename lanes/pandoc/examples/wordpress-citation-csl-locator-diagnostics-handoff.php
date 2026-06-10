<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Locator Diagnostics Review

Review locators [@locator-diagnostics-source, p. 7; @locator-diagnostics-source, plate A; @locator-diagnostics-source, sec. 4-5] before publishing.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "locator-diagnostics-source",
    "type": "report",
    "title": "WordPress Locator Diagnostics Packet",
    "author": [
      {"family": "Vale", "given": "Rae"}
    ],
    "issued": {"date-parts": [[2026]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Locator Diagnostics Review</title>
    <id>https://example.test/styles/wordpress-citation-locator-diagnostics-review</id>
    <updated>2026-06-09T20:24:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=", ">
        <names variable="author"/>
        <group delimiter=" ">
          <label variable="locator" form="short"/>
          <text variable="locator"/>
        </group>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=". " suffix=".">
      <names variable="author"/>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$sourceDocument = (new MarkdownReader())->read($markdown);
$document = $processor->appendBibliography($sourceDocument, 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $diagnostics = $processor->citationLocatorDiagnostics($sourceDocument);
    if (count($diagnostics) !== 1) {
        throw new RuntimeException('Citation locator diagnostics handoff expected one inferred-locator diagnostic');
    }
    if (($diagnostics[0]['reason'] ?? null) !== 'citation-locator-unlabeled-page-fallback') {
        throw new RuntimeException('Citation locator diagnostics handoff did not flag unlabeled page fallback');
    }
    if (($diagnostics[0]['rawLocator'] ?? null) !== 'plate A') {
        throw new RuntimeException('Citation locator diagnostics handoff did not preserve raw locator text');
    }

    $unknownLabel = new AstNode('citation', [
        'id' => 'locator-diagnostics-source',
        'text' => '[@locator-diagnostics-source, scene intro]',
        'locatorLabel' => 'scene',
        'locatorValue' => 'intro',
    ]);
    $unknownDiagnostics = $processor->citationLocatorDiagnostics($unknownLabel);
    if (($unknownDiagnostics[0]['reason'] ?? null) !== 'citation-locator-unsupported-label') {
        throw new RuntimeException('Citation locator diagnostics handoff did not flag unsupported explicit labels');
    }

    $unsupportedLabelWithRawLocator = new AstNode('citation', [
        'id' => 'locator-diagnostics-source',
        'text' => '[@locator-diagnostics-source, scene intro]',
        'locatorLabel' => 'scene',
        'locator' => 'intro',
    ]);
    $unsupportedRawDiagnostics = $processor->citationLocatorDiagnostics($unsupportedLabelWithRawLocator);
    if (array_column($unsupportedRawDiagnostics, 'reason') !== [
        'citation-locator-label-without-explicit-value',
        'citation-locator-unsupported-label',
        'citation-locator-unlabeled-page-fallback',
    ]) {
        throw new RuntimeException('Citation locator diagnostics handoff did not flag unsupported explicit labels with raw locator text');
    }

    $defaultedPage = new AstNode('citation', [
        'id' => 'locator-diagnostics-source',
        'text' => '[@locator-diagnostics-source, appendix A]',
        'locatorValue' => 'appendix A',
    ]);
    $defaultedDiagnostics = $processor->citationLocatorDiagnostics($defaultedPage);
    if (($defaultedDiagnostics[0]['reason'] ?? null) !== 'citation-locator-explicit-value-defaulted-page') {
        throw new RuntimeException('Citation locator diagnostics handoff did not flag explicit values defaulting to page locators');
    }
    if (($defaultedDiagnostics[0]['locatorLabel'] ?? null) !== 'page' || ($defaultedDiagnostics[0]['locatorValue'] ?? null) !== 'appendix A') {
        throw new RuntimeException('Citation locator diagnostics handoff did not preserve explicit defaulted page locator metadata');
    }

    $labelWithoutValue = new AstNode('citation', [
        'id' => 'locator-diagnostics-source',
        'text' => '[@locator-diagnostics-source, fig.]',
        'locatorLabel' => 'fig.',
    ]);
    $labelOnlyDiagnostics = $processor->citationLocatorDiagnostics($labelWithoutValue);
    if (($labelOnlyDiagnostics[0]['reason'] ?? null) !== 'citation-locator-label-without-value') {
        throw new RuntimeException('Citation locator diagnostics handoff did not flag explicit labels without locator values');
    }
    if (($labelOnlyDiagnostics[0]['locatorLabel'] ?? null) !== 'figure' || ($labelOnlyDiagnostics[0]['locatorValue'] ?? null) !== '') {
        throw new RuntimeException('Citation locator diagnostics handoff did not preserve label-only locator metadata');
    }

    foreach ([
        '<p>Review locators (Vale, p. 7; Vale, p. plate A; Vale, secs. 4–5) before publishing.</p>',
        '<dt>Vale 2026</dt><dd>Vale, Rae. WordPress Locator Diagnostics Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Citation locator diagnostics handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-locator-diagnostics-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
