<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX CSL Source Locator Review

BibTeX source fields [@source-section-rule; @supplement-review] keep imported provenance and locator metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@legislation{source-section-rule,
  author  = {Smith, Ada},
  title   = {Sectioned Import Rule},
  year    = {2026},
  source  = {Legacy Drupal export batch 42},
  section = {2}
}

@report{supplement-review,
  author       = {Ng, Nia},
  title        = {Supplement Review Packet},
  year         = {2025},
  source-title = {MediaWiki export queue},
  supplement   = {3-4}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX CSL Source Locator Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-source-locator-review</id>
    <updated>2026-06-09T02:19:52+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="source"/>
        <choose>
          <if variable="section">
            <group delimiter=" ">
              <label variable="section" form="symbol"/>
              <number variable="section" form="ordinal"/>
            </group>
          </if>
          <else-if variable="supplement">
            <group delimiter=" ">
              <label variable="supplement" form="short"/>
              <number variable="supplement" form="ordinal"/>
            </group>
          </else-if>
        </choose>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="source"/>
      <choose>
        <if variable="section">
          <group delimiter=" ">
            <label variable="section" form="short"/>
            <text variable="section" form="long-ordinal"/>
          </group>
        </if>
        <else-if variable="supplement">
          <group delimiter=" ">
            <label variable="supplement"/>
            <text variable="supplement" form="long-ordinal"/>
          </group>
        </else-if>
      </choose>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (($processor->item('source-section-rule')['source'] ?? null) !== 'Legacy Drupal export batch 42') {
        throw new RuntimeException('BibTeX CSL source locator handoff did not preserve source metadata');
    }
    if (($processor->item('source-section-rule')['section'] ?? null) !== '2') {
        throw new RuntimeException('BibTeX CSL source locator handoff did not preserve section metadata');
    }
    if (($processor->item('supplement-review')['source'] ?? null) !== 'MediaWiki export queue') {
        throw new RuntimeException('BibTeX CSL source locator handoff did not preserve source-title metadata');
    }
    if (($processor->item('supplement-review')['supplement'] ?? null) !== '3-4') {
        throw new RuntimeException('BibTeX CSL source locator handoff did not preserve supplement metadata');
    }

    foreach ([
        '<p>BibTeX source fields (Smith | Legacy Drupal export batch 42 | § 2nd; Ng | MediaWiki export queue | supps. 3rd-4th) keep imported provenance and locator metadata visible.</p>',
        '<dt>Smith 2026</dt><dd>Sectioned Import Rule :: Legacy Drupal export batch 42 :: sec. second</dd>',
        '<dt>Ng 2025</dt><dd>Supplement Review Packet :: MediaWiki export queue :: supplements third-fourth</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL source locator self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-source-locator-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
