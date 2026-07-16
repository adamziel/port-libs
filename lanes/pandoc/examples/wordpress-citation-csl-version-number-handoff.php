<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# CSL Version Number Citation Review

Versioned imports [@versioned-manual; @version-range; @named-version] keep release numbers visible.
MARKDOWN;

$cslJson = <<<'JSON'
[
  {
    "id": "versioned-manual",
    "type": "book",
    "title": "Versioned Import Manual",
    "author": [
      {"family": "Smith", "given": "Ada"}
    ],
    "issued": {"date-parts": [[2026]]},
    "version": "2"
  },
  {
    "id": "version-range",
    "type": "dataset",
    "title": "Versioned Export Data",
    "author": [
      {"family": "Ng", "given": "Nia"}
    ],
    "issued": {"date-parts": [[2025]]},
    "version": "2-4"
  },
  {
    "id": "named-version",
    "type": "software",
    "title": "Named Channel Build",
    "author": [
      {"family": "Roe", "given": "Pat"}
    ],
    "issued": {"date-parts": [[2024]]},
    "version": "release candidate"
  }
]
JSON;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress Citation Version Number Review</title>
    <id>https://example.test/styles/wordpress-citation-version-number-review</id>
    <updated>2026-06-08T20:05:13+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <choose>
          <if is-numeric="version">
            <group delimiter=" ">
              <label variable="version" form="short"/>
              <number variable="version" form="ordinal"/>
            </group>
          </if>
          <else>
            <text variable="version" prefix="version "/>
          </else>
        </choose>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <group delimiter=" ">
        <label variable="version"/>
        <text variable="version" form="roman"/>
      </group>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromJson($cslJson)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    $children = $summary['citationRendering'][0]['children'] ?? [];
    $branches = $children[1]['branches'] ?? [];
    $versionBranchChildren = $branches[0]['children'][0]['children'] ?? [];

    if (($branches[0]['isNumeric'] ?? null) !== ['version']) {
        throw new RuntimeException('CSL version number handoff did not preserve is-numeric metadata');
    }
    if (($versionBranchChildren[0]['variable'] ?? null) !== 'version' || ($versionBranchChildren[0]['form'] ?? null) !== 'short') {
        throw new RuntimeException('CSL version number handoff did not preserve label metadata');
    }
    if (($versionBranchChildren[1]['variable'] ?? null) !== 'version' || ($versionBranchChildren[1]['form'] ?? null) !== 'ordinal') {
        throw new RuntimeException('CSL version number handoff did not preserve number metadata');
    }

    foreach ([
        '<p>Versioned imports (Smith ver. 2nd; Ng vers. 2nd-4th; Roe version release candidate) keep release numbers visible.</p>',
        '<dt>Smith 2026</dt><dd>Versioned Import Manual :: version ii</dd>',
        '<dt>Ng 2025</dt><dd>Versioned Export Data :: versions ii-iv</dd>',
        '<dt>Roe 2024</dt><dd>Named Channel Build :: version release candidate</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('CSL version number handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-citation-csl-version-number-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
