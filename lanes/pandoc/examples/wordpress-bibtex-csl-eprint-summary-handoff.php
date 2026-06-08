<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Eprint Review

Repository source [@eprint-source] keeps archive identifiers visible.
MARKDOWN;

$bibtex = <<<'BIB'
@article{eprint-source,
  author      = {Doe, Jane},
  title       = {Repository Packet},
  date        = {2026},
  eprint      = {2401.01234},
  eprinttype  = {arXiv},
  eprintclass = {cs.DL}
}
BIB;

$style = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="eprint-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="archive-summary"/>
    </layout>
  </bibliography>
</style>
XML;

$processor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle($style);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('eprint-source');
    if (($item['archiveSummary'] ?? null) !== 'arXiv:2401.01234 [cs.DL]') {
        throw new RuntimeException('BibTeX CSL eprint summary handoff did not preserve normalized archive summary');
    }
    if (($item['archive'] ?? null) !== 'arXiv') {
        throw new RuntimeException('BibTeX CSL eprint summary handoff did not preserve archive source');
    }
    if (($item['archiveLocation'] ?? null) !== '2401.01234') {
        throw new RuntimeException('BibTeX CSL eprint summary handoff did not preserve archive location');
    }

    foreach ([
        '<p>Repository source [Doe | arXiv:2401.01234 [cs.DL]] keeps archive identifiers visible.</p>',
        '<dt>Doe 2026</dt><dd>Repository Packet :: arXiv:2401.01234 [cs.DL]</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL eprint summary self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-eprint-summary-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
