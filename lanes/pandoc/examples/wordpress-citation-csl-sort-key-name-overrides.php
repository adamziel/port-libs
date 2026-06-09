<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Sort Key Name Override Review

Reviewer packets cite [@smith-zed-alpha; @smith-adams-zulu; @ng-chen-beta] while preserving CSL name-list sort overrides.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "smith-zed-alpha",
    "type": "report",
    "title": "First Cited Name Sort Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Zed", "given": "Zoe"},
      {"family": "Alpha", "given": "Ari"}
    ],
    "issued": {"date-parts": [[2026]]}
  },
  {
    "id": "smith-adams-zulu",
    "type": "report",
    "title": "Second Cited Name Sort Packet",
    "author": [
      {"family": "Smith", "given": "Ada"},
      {"family": "Adams", "given": "Ari"},
      {"family": "Zulu", "given": "Zoe"}
    ],
    "issued": {"date-parts": [[2025]]}
  },
  {
    "id": "ng-chen-beta",
    "type": "report",
    "title": "Leading Ng Name Sort Packet",
    "author": [
      {"family": "Ng", "given": "Nia"},
      {"family": "Chen", "given": "Kai"},
      {"family": "Beta", "given": "Bea"}
    ],
    "issued": {"date-parts": [[2024]]}
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress CSL Sort Key Name Override Review</title>
    <id>https://example.test/styles/wordpress-csl-sort-key-name-override-review</id>
    <updated>2026-06-09T05:41:34+00:00</updated>
  </info>
  <macro name="creator-sort">
    <names variable="author"><name form="short"/></names>
  </macro>
  <citation>
    <sort>
      <key variable="author" names-min="3" names-use-first="1"/>
    </sort>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <text macro="creator-sort"/>
        <text variable="title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key macro="creator-sort" names-min="3" names-use-first="1" names-use-last="true"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text macro="creator-sort"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();

    if (($summary['citationSort'][0]['namesMin'] ?? null) !== 3 || ($summary['citationSort'][0]['namesUseFirst'] ?? null) !== 1) {
        throw new RuntimeException('CSL sort-key name override handoff did not preserve citation names-min/names-use-first metadata');
    }
    if (($summary['bibliographySort'][0]['macro'] ?? null) !== 'creator-sort' || ($summary['bibliographySort'][0]['namesUseLast'] ?? null) !== true) {
        throw new RuntimeException('CSL sort-key name override handoff did not preserve bibliography macro names-use-last metadata');
    }

    $expectedCitation = '<p>Reviewer packets cite (Ng et al. | Leading Ng Name Sort Packet; Smith et al. | First Cited Name Sort Packet; Smith et al. | Second Cited Name Sort Packet) while preserving CSL name-list sort overrides.</p>';
    if (!str_contains($blocks, $expectedCitation)) {
        throw new RuntimeException('CSL sort-key name override self-test missing sorted citation cluster');
    }

    $ng = strpos($blocks, '<dt>Ng et al. 2024</dt><dd>Leading Ng Name Sort Packet ::');
    $firstSmith = strpos($blocks, '<dt>Smith et al. 2026</dt><dd>First Cited Name Sort Packet ::');
    $secondSmith = strpos($blocks, '<dt>Smith et al. 2025</dt><dd>Second Cited Name Sort Packet ::');
    if (!(is_int($ng) && is_int($firstSmith) && is_int($secondSmith) && $ng < $firstSmith && $firstSmith < $secondSmith)) {
        throw new RuntimeException('CSL sort-key name override handoff did not preserve bibliography order');
    }

    echo "wordpress-citation-csl-sort-key-name-overrides self-test passed\n";
    return;
}

echo $blocks . "\n";
