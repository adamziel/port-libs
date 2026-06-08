<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Media Type Review

Imported media sources cite [@film-source; @clip-source; @score-source; @still-source] while keeping BibLaTeX entry types aligned with CSL type conditionals.
MARKDOWN;

$bibtex = <<<'BIB'
@movie{film-source,
  author = {{Migration Film Desk}},
  title  = {Source Capture Reel},
  date   = {2026}
}

@video{clip-source,
  author = {{Field Video Desk}},
  title  = {Field Cut Clip},
  date   = {2025}
}

@music{score-source,
  author = {Curator, Eli},
  title  = {Migration Review Score},
  date   = {2025}
}

@image{still-source,
  author = {{Archive Image Desk}},
  title  = {Archive Still},
  date   = {2024}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibTeX CSL Media Type Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-media-type-review</id>
    <updated>2026-06-08T22:11:30+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <choose>
        <if type="motion_picture">
          <group delimiter=" | ">
            <text value="moving image"/>
            <text variable="title"/>
          </group>
        </if>
        <else-if type="song">
          <group delimiter=" | ">
            <text value="music"/>
            <text variable="title"/>
          </group>
        </else-if>
        <else-if type="graphic">
          <group delimiter=" | ">
            <text value="graphic"/>
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
        'film-source' => 'motion_picture',
        'clip-source' => 'motion_picture',
        'score-source' => 'song',
        'still-source' => 'graphic',
    ] as $id => $type) {
        if (($processor->item($id)['type'] ?? null) !== $type) {
            throw new RuntimeException('BibTeX CSL media type handoff produced unexpected type for ' . $id);
        }
    }

    foreach ([
        '<p>Imported media sources cite [moving image | Source Capture Reel; moving image | Field Cut Clip; music | Migration Review Score; graphic | Archive Still] while keeping BibLaTeX entry types aligned with CSL type conditionals.</p>',
        '<dt>Migration Film Desk 2026</dt><dd>motion_picture :: Source Capture Reel</dd>',
        '<dt>Curator 2025</dt><dd>song :: Migration Review Score</dd>',
        '<dt>Archive Image Desk 2024</dt><dd>graphic :: Archive Still</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL media type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-media-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
