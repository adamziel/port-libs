<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Label Prefix Review

Reviewers cite [@label-prefix-zed; @label-prefix-adams] while preserving generated bibliography grouping metadata.
MARKDOWN;

$bibtex = <<<'BIB'
@book{label-prefix-zed,
  author       = {Zed, Zoe},
  title        = {Zed Prefix Source},
  date         = {2026},
  labelprefix  = {WP},
  extraalpha   = {c},
  sortinit     = {Z},
  sortinithash = {hash-zed},
  sortkey      = {900-zed}
}

@book{label-prefix-adams,
  author       = {Adams, Ada},
  title        = {Adams Prefix Source},
  date         = {2025},
  label-prefix = {Media},
  extra-alpha  = {a},
  sort-initial = {A},
  sort-initial-hash = {hash-adams},
  sortkey      = {001-adams}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <sort>
      <key variable="label-prefix"/>
      <key variable="sort-initial"/>
      <key variable="sort-key"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="label-prefix"/>
        <text variable="extra-alpha"/>
        <text variable="sort-initial"/>
        <text variable="sort-initial-hash"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="label-prefix"/>
      <key variable="sort-initial"/>
      <key variable="sort-key"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="labelprefix"/>
      <text variable="extraalpha"/>
      <text variable="sortinit"/>
      <text variable="sortinithash"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $zed = $processor->item('label-prefix-zed');
    $adams = $processor->item('label-prefix-adams');
    if (($zed['labelPrefix'] ?? null) !== 'WP' || ($zed['extraAlpha'] ?? null) !== 'c') {
        throw new RuntimeException('BibTeX CSL label-prefix handoff did not preserve zed label metadata');
    }
    if (($zed['sortInitial'] ?? null) !== 'Z' || ($zed['sortInitialHash'] ?? null) !== 'hash-zed') {
        throw new RuntimeException('BibTeX CSL label-prefix handoff did not preserve zed sort-initial metadata');
    }
    if (($adams['labelPrefix'] ?? null) !== 'Media' || ($adams['extraAlpha'] ?? null) !== 'a') {
        throw new RuntimeException('BibTeX CSL label-prefix handoff did not preserve adams label metadata');
    }

    foreach ([
        '<p>Reviewers cite [Adams | Media | a | A | hash-adams; Zed | WP | c | Z | hash-zed] while preserving generated bibliography grouping metadata.</p>',
        '<dt>Adams 2025</dt><dd>Adams Prefix Source :: Media :: a :: A :: hash-adams</dd>',
        '<dt>Zed 2026</dt><dd>Zed Prefix Source :: WP :: c :: Z :: hash-zed</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL label-prefix self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-label-prefix-sortinit-handoff self-test passed\n";
    exit(0);
}

echo $blocks;
