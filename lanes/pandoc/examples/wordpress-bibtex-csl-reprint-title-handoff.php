<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Reprint Title Review

Reprint source @reprint-manual keeps facsimile title metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{reprint-manual,
  author        = {Smith, Ada},
  title         = {Migration Manual},
  date          = {2026},
  publisher     = {Review Press},
  origtitle     = {Original Migration Manual},
  origdate      = {1998},
  origpublisher = {Archive Desk},
  origlocation  = {London},
  reprinttitle  = {Facsimile Source Packet}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('reprint-manual');
    if (($item['reprintTitle'] ?? null) !== 'Facsimile Source Packet') {
        throw new RuntimeException('BibTeX CSL reprint-title handoff did not preserve normalized metadata');
    }
    if (($item['raw']['reprint-title'] ?? null) !== 'Facsimile Source Packet') {
        throw new RuntimeException('BibTeX CSL reprint-title handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Reprint source Smith (2026) keeps facsimile title metadata visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Migration Manual. Review Press, 2026. Reprint title: Facsimile Source Packet. Original title: Original Migration Manual. Original work published 1998. Original publisher: Archive Desk, London.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL reprint-title self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-reprint-title-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
