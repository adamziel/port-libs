<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Archive Collection Review

BibTeX archives [@archive-city; @archive-field] preserve collection folders.
MARKDOWN;

$bibtex = <<<'BIB'
@misc{archive-city,
  author            = {Smith, Ada},
  title             = {City Archive Packet},
  date              = {2026},
  archive           = {City Archive},
  archivecollection = {Migration Papers},
  archiveplace      = {Portland},
  archive_location  = {Box 4}
}

@misc{archive-field,
  author             = {Ng, Nia},
  title              = {Field Notes Packet},
  date               = {2025},
  archive            = {Field Notes Library},
  archive-collection = {Audit Series},
  archive-location   = {Folder 2}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX Archive Collection Review</title>
    <id>https://example.test/styles/wordpress-bibtex-archive-collection-review</id>
    <updated>2026-06-09T08:35:13+00:00</updated>
  </info>
  <citation>
    <sort>
      <key variable="archive_collection"/>
    </sort>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="archive"/>
        <text variable="archive_collection"/>
        <text variable="archive-place"/>
        <text variable="archive_location"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="archive_collection"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="archive-collection"/>
      <text variable="archive-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = (new MarkdownReader())->read($markdown);
$blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));

if (($argv[1] ?? '') === '--self-test') {
    $city = $processor->item('archive-city');
    $field = $processor->item('archive-field');

    if (($city['archiveCollection'] ?? null) !== 'Migration Papers') {
        throw new RuntimeException('BibTeX archivecollection field was not mapped into CSL archiveCollection metadata');
    }

    if (($field['archiveCollection'] ?? null) !== 'Audit Series') {
        throw new RuntimeException('BibTeX archive-collection field was not mapped into CSL archiveCollection metadata');
    }

    if (($city['archiveSummary'] ?? null) !== 'City Archive:Migration Papers:Box 4 [Portland]') {
        throw new RuntimeException('BibTeX archive collection was not included in archive summary metadata');
    }

    foreach ([
        '<p>BibTeX archives (Ng | Field Notes Library | Audit Series | Folder 2; Smith | City Archive | Migration Papers | Portland | Box 4) preserve collection folders.</p>',
        '<dt>Ng 2025</dt><dd>Field Notes Packet :: Audit Series :: Field Notes Library:Audit Series:Folder 2</dd>',
        '<dt>Smith 2026</dt><dd>City Archive Packet :: Migration Papers :: City Archive:Migration Papers:Box 4 [Portland]</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX archive collection handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-archive-collection-handoff self-test passed\n";
    return;
}

echo $blocks;
