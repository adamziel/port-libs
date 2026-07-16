<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Registry Identifier Review

Registry-backed sources [@math-review; @library-review] keep reviewer identifiers attached to the bibliography packet.
MARKDOWN;

$bibtex = <<<'BIB'
@article{math-review,
  author       = {Noether, Emmy},
  title        = {Invariant Review Packet},
  journaltitle = {Migration Mathematics Review},
  date         = {2026},
  mrnumber     = {MR1234567},
  mrclass      = {13A50},
  zbl          = {1234.56789},
  jstor        = {10.2307/9999999}
}

@book{library-review,
  author    = {{Archive Library Desk}},
  title     = {Catalog Review Manual},
  date      = {2025},
  publisher = {Review Press},
  hdl       = {20.500.12345/source-review},
  lccn      = {2026123456},
  oclc      = {987654321}
}
BIB;

$styleXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>WordPress BibLaTeX Registry Identifier Review</title>
    <id>https://example.test/styles/wordpress-biblatex-registry-identifier-review</id>
    <updated>2026-06-10T11:15:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="mrnumber"/>
        <text variable="zbl"/>
        <text variable="jstor"/>
        <text variable="hdl"/>
        <text variable="lccn"/>
        <text variable="oclc"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="mr-number"/>
      <text variable="zbmath"/>
      <text variable="jstor"/>
      <text variable="handle"/>
      <text variable="lccn"/>
      <text variable="oclc"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($styleXml);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        'math-review' => ['mrNumber' => 'MR1234567', 'zbl' => '1234.56789', 'jstor' => '10.2307/9999999'],
        'library-review' => ['hdl' => '20.500.12345/source-review', 'lccn' => '2026123456', 'oclc' => '987654321'],
    ] as $id => $expectations) {
        foreach ($expectations as $field => $value) {
            if (($processor->item($id)[$field] ?? null) !== $value) {
                throw new RuntimeException('Registry identifier handoff produced unexpected ' . $field . ' for ' . $id);
            }
        }
    }

    foreach ([
        '<p>Registry-backed sources [Noether | MR1234567 | 1234.56789 | 10.2307/9999999; Archive Library Desk | 20.500.12345/source-review | 2026123456 | 987654321] keep reviewer identifiers attached to the bibliography packet.</p>',
        '<dt>Noether 2026</dt><dd>Invariant Review Packet :: MR1234567 :: 1234.56789 :: 10.2307/9999999</dd>',
        '<dt>Archive Library Desk 2025</dt><dd>Catalog Review Manual :: 20.500.12345/source-review :: 2026123456 :: 987654321</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('Registry identifier self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-registry-identifiers-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
