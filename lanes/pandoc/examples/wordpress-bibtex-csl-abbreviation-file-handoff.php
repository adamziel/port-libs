<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Abbreviation Review

Imported source packets cite [@abbrev-bib-source] while preserving reviewer-supplied compact labels from CSL abbreviations JSON.
MARKDOWN;

$bibtex = <<<'BIB'
@article{abbrev-bib-source,
  author       = {Vale, Vera},
  title        = {Migration Review Source Packet},
  journaltitle = {Journal of Imported Source Packets},
  series       = {Migration Review Series},
  publisher    = {WordPress Migration Press},
  location     = {New York},
  type         = {technical report},
  date         = {2026}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX CSL Abbreviation JSON Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-abbreviation-json-review</id>
    <updated>2026-06-08T21:52:59+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title" form="short"/>
        <text variable="container-title" form="short"/>
        <text variable="collection-title" form="short"/>
        <text variable="publisher" form="short"/>
        <text variable="publisher-place" form="short"/>
        <text variable="genre" form="short"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title" form="short"/>
      <text variable="container-title" form="short"/>
      <text variable="collection-title" form="short"/>
      <text variable="publisher" form="short"/>
      <text variable="publisher-place" form="short"/>
      <text variable="genre" form="short"/>
    </layout>
  </bibliography>
</style>
XML;

$abbreviationJson = <<<'JSON'
{
  "default": {
    "title": {
      "Migration Review Source Packet": "Migr. Rev. Source"
    },
    "container-title": {
      "Journal of Imported Source Packets": "J. Imported Source Packets"
    },
    "collection-title": {
      "Migration Review Series": "Migr. Rev. Ser."
    },
    "institution": {
      "WordPress Migration Press": "WP Migr. Press"
    },
    "place": {
      "New York": "N.Y."
    },
    "genre": {
      "technical report": "tech. rep."
    }
  }
}
JSON;

$processor = CitationCslProcessor::fromBibtex($bibtex)
    ->withCslStyle($styleXml)
    ->withCslAbbreviationsJson($abbreviationJson);

$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $summary = $processor->cslStyleSummary();
    if (($summary['abbreviations']['container-title']['Journal of Imported Source Packets'] ?? null) !== 'J. Imported Source Packets') {
        throw new RuntimeException('BibTeX CSL abbreviation JSON handoff did not preserve container-title abbreviations');
    }

    foreach ([
        '<p>Imported source packets cite [Migr. Rev. Source | J. Imported Source Packets | Migr. Rev. Ser. | WP Migr. Press | N.Y. | tech. rep.] while preserving reviewer-supplied compact labels from CSL abbreviations JSON.</p>',
        '<dt>Vale 2026</dt><dd>Migr. Rev. Source :: J. Imported Source Packets :: Migr. Rev. Ser. :: WP Migr. Press :: N.Y. :: tech. rep.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL abbreviation JSON self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-abbreviation-file-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
