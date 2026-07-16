<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Volume And Part Title Review

Volume-part source [@volume-part-source] keeps source divisions visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{volume-part-source,
  author = {Smith, Ada},
  title = {Migration Packet Leaf},
  maintitle = {Migration Source Set},
  volumetitle = {Review Volume},
  volume = {2},
  parttitle = {Archive Part},
  part = {1},
  date = {2026}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>WordPress BibTeX Volume Part Title Review</title>
    <id>https://example.test/styles/wordpress-bibtex-volume-part-title-review</id>
    <updated>2026-06-09T05:59:07+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="volume-title"/>
        <text variable="part-title"/>
        <text variable="volume"/>
        <text variable="part"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="volume-title"/>
      <text variable="part-title"/>
      <text variable="volume"/>
      <text variable="part"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<p>Volume-part source [Smith | Review Volume | Archive Part | 2 | 1] keeps source divisions visible.</p>',
        '<dt>Smith 2026</dt><dd>Migration Packet Leaf :: Review Volume :: Archive Part :: 2 :: 1</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX volume/part title self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-volume-part-title-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
