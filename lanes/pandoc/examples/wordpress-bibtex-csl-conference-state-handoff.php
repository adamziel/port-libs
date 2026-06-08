<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Conference Publication-State Review

Imported conference records cite [@forthcoming-poster; @published-paper] while keeping unpublished event venue and publication state visible.
MARKDOWN;

$bibtex = <<<'BIB'
@unpublished{forthcoming-poster,
  author     = {Roe, Pat},
  title      = {Forthcoming Poster Packet},
  type       = {Poster},
  eventtitle = {WordPress Import Summit},
  eventdate  = {2026-06-06},
  venue      = {Portland},
  pubstate   = {forthcoming},
  date       = {2026}
}

@inproceedings{published-paper,
  author     = {Ng, Nia},
  title      = {Published Proceedings Paper},
  booktitle  = {WordPress Import Proceedings},
  eventtitle = {WordPress Import Summit},
  eventdate  = {2025-05-02},
  venue      = {Seattle},
  pubstate   = {inpress},
  date       = {2025},
  pages      = {9--12}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibTeX CSL Conference Publication-State Review</title>
    <id>https://example.test/styles/wordpress-bibtex-csl-conference-state-review</id>
    <updated>2026-06-08T23:12:23+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="type"/>
        <text variable="title"/>
        <text variable="event-place" prefix="event "/>
        <text variable="publisher-place" prefix="publisher "/>
        <text variable="status" prefix="state "/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="event-place" prefix="event "/>
      <text variable="publisher-place" prefix="publisher "/>
      <text variable="status" prefix="state "/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $poster = $processor->item('forthcoming-poster');
    if (($poster['type'] ?? null) !== 'speech') {
        throw new RuntimeException('Conference state handoff did not map unpublished event entry to speech');
    }
    if (($poster['status'] ?? null) !== 'forthcoming') {
        throw new RuntimeException('Conference state handoff did not preserve unpublished pubstate');
    }
    if (($poster['eventPlace'] ?? null) !== 'Portland') {
        throw new RuntimeException('Conference state handoff did not preserve unpublished venue as event place');
    }
    if (($poster['publisherPlace'] ?? null) !== '') {
        throw new RuntimeException('Conference state handoff leaked unpublished venue into publisher place');
    }

    foreach ([
        '<p>Imported conference records cite [speech | Forthcoming Poster Packet | event Portland | state forthcoming; paper-conference | Published Proceedings Paper | event Seattle | publisher Seattle | state inpress] while keeping unpublished event venue and publication state visible.</p>',
        '<dt>Roe 2026</dt><dd>Forthcoming Poster Packet :: event Portland :: state forthcoming</dd>',
        '<dt>Ng 2025</dt><dd>Published Proceedings Paper :: event Seattle :: publisher Seattle :: state inpress</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL conference state self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-conference-state-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
