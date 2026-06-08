<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Unpublished Speech Review

Imported review notes cite [@migration-talk] beside manuscript-only notes [@field-note] while preserving the Pandoc BibLaTeX unpublished event handoff.
MARKDOWN;

$bibtex = <<<'BIB'
@unpublished{migration-talk,
  author     = {Curator, Eli},
  title      = {Migration Review Talk},
  type       = {Paper},
  eventtitle = {WordPress Import Summit},
  eventdate  = {2026-06-04},
  venue      = {Portland},
  date       = {2026}
}

@unpublished{field-note,
  author = {Ng, Nia},
  title  = {Unpublished Field Notes},
  date   = {2025}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibTeX CSL Unpublished Speech Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-unpublished-speech-review</id>
    <updated>2026-06-08T22:23:57+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <choose>
        <if type="speech">
          <group delimiter=" | ">
            <text value="speech"/>
            <text variable="title"/>
            <text variable="event"/>
            <text variable="genre"/>
            <text variable="event-place"/>
            <date variable="event-date"/>
          </group>
        </if>
        <else-if type="manuscript">
          <group delimiter=" | ">
            <text value="manuscript"/>
            <text variable="title"/>
          </group>
        </else-if>
      </choose>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="type"/>
      <text variable="title"/>
      <text variable="event"/>
      <text variable="genre"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        'migration-talk' => 'speech',
        'field-note' => 'manuscript',
    ] as $id => $type) {
        if (($processor->item($id)['type'] ?? null) !== $type) {
            throw new RuntimeException('BibTeX CSL unpublished speech handoff produced unexpected type for ' . $id);
        }
    }

    foreach ([
        '<p>Imported review notes cite [speech | Migration Review Talk | WordPress Import Summit | Paper | Portland | 2026-06-04] beside manuscript-only notes [manuscript | Unpublished Field Notes] while preserving the Pandoc BibLaTeX unpublished event handoff.</p>',
        '<dt>Curator 2026</dt><dd>speech :: Migration Review Talk :: WordPress Import Summit :: Paper :: 2026-06-04</dd>',
        '<dt>Ng 2025</dt><dd>manuscript :: Unpublished Field Notes</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL unpublished speech self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-unpublished-speech-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
