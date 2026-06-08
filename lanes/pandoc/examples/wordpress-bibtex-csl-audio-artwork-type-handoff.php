<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Audio and Artwork Type Review

Imported audio and artwork sources cite [@audio-source; @artwork-source] while keeping extended media entry types aligned with CSL type conditionals.
MARKDOWN;

$bibtex = <<<'BIB'
@audio{audio-source,
  author = {{Migration Audio Desk}},
  title  = {Migration Audio Review},
  date   = {2026}
}

@artwork{artwork-source,
  author = {{Archive Artwork Desk}},
  title  = {Migration Artwork},
  date   = {2025}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibTeX CSL Audio Artwork Type Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-audio-artwork-type-review</id>
    <updated>2026-06-08T22:47:17+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <choose>
        <if type="song">
          <group delimiter=" | ">
            <text value="audio"/>
            <text variable="title"/>
          </group>
        </if>
        <else-if type="graphic">
          <group delimiter=" | ">
            <text value="artwork"/>
            <text variable="title"/>
          </group>
        </else-if>
        <else>
          <group delimiter=" | ">
            <text value="source"/>
            <text variable="type"/>
            <text variable="title"/>
          </group>
        </else>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="type"/>
      <text variable="title"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        'audio-source' => 'song',
        'artwork-source' => 'graphic',
    ] as $id => $type) {
        if (($processor->item($id)['type'] ?? null) !== $type) {
            throw new RuntimeException('BibTeX CSL audio/artwork type handoff produced unexpected type for ' . $id);
        }
    }

    foreach ([
        '<p>Imported audio and artwork sources cite [audio | Migration Audio Review; artwork | Migration Artwork] while keeping extended media entry types aligned with CSL type conditionals.</p>',
        '<dt>Migration Audio Desk 2026</dt><dd>song :: Migration Audio Review</dd>',
        '<dt>Archive Artwork Desk 2025</dt><dd>graphic :: Migration Artwork</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL audio/artwork self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-audio-artwork-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
