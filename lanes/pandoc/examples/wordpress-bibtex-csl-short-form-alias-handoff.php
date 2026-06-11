<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Alias short forms [@hyphen-short-title; @title-short-alias; @container-short-alias] stay compact.';

$bibtex = <<<'BIB'
@book{hyphen-short-title,
  author      = {Curator, Eli},
  title       = {Migration Manual},
  short-title = {Migration Guide},
  date        = {2026},
  publisher   = {Review Press}
}

@book{title-short-alias,
  author      = {Ng, Nia},
  title       = {Compact Handbook},
  title-short = {Compact Handbook},
  date        = {2025}
}

@article{container-short-alias,
  author                = {Doe, Jane},
  title                 = {Article Packet},
  journaltitle          = {Journal of Short Sources},
  container-title-short = {J. Short Sources},
  date                  = {2024}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX Short Form Alias Review</title>
    <id>https://example.test/styles/wordpress-bibtex-short-form-alias-review</id>
    <updated>2026-06-11T11:05:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="title" form="short"/>
        <text variable="short-title"/>
        <text variable="container-title" form="short"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="title" form="short"/>
      <text variable="container-title"/>
      <text variable="container-title" form="short"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $items = CitationCslProcessor::bibtexItems($bibtex);
    if (($items[0]['short-title'] ?? null) !== 'Migration Guide') {
        throw new RuntimeException('BibTeX short-title alias did not map to CSL short-title metadata');
    }
    if (($items[1]['short-title'] ?? null) !== 'Compact Handbook') {
        throw new RuntimeException('BibTeX title-short alias did not map to CSL short-title metadata');
    }
    if (($items[2]['container-title-short'] ?? null) !== 'J. Short Sources') {
        throw new RuntimeException('BibTeX container-title-short alias did not map to CSL container-title-short metadata');
    }

    $summary = $processor->cslStyleSummary();
    if (($summary['citationRendering'][0]['children'][1]['form'] ?? null) !== 'short') {
        throw new RuntimeException('BibTeX short-form alias handoff did not preserve title short-form metadata');
    }
    if (($summary['citationRendering'][0]['children'][2]['variable'] ?? null) !== 'short-title') {
        throw new RuntimeException('BibTeX short-form alias handoff did not preserve short-title variable metadata');
    }

    foreach ([
        '<p>Alias short forms [Curator | Migration Guide | Migration Guide; Ng | Compact Handbook | Compact Handbook; Doe | Article Packet | J. Short Sources] stay compact.</p>',
        '<dt>Curator 2026</dt><dd>Migration Manual :: Migration Guide</dd>',
        '<dt>Doe 2024</dt><dd>Article Packet :: Article Packet :: Journal of Short Sources :: J. Short Sources</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX short-form alias handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-short-form-alias-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
