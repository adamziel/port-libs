<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Entry Options Review

Entry options @entry-options-manual keep bounded BibLaTeX switches visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{entry-options-manual,
  author    = {Smith, Ada},
  title     = {Options Review Manual},
  date      = {2026},
  publisher = {Review Press},
  options   = {skipbib=false, useprefix=true, maxnames=3}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('entry-options-manual');
    if (($item['biblatexOptions'] ?? null) !== ['skipbib=false', 'useprefix=true', 'maxnames=3']) {
        throw new RuntimeException('BibTeX CSL entry-options handoff did not preserve normalized options');
    }
    if (($item['biblatexOptionSummary'] ?? null) !== 'skipbib=false; useprefix=true; maxnames=3') {
        throw new RuntimeException('BibTeX CSL entry-options handoff did not preserve the display summary');
    }
    if (($item['raw']['biblatex-options'] ?? null) !== ['skipbib=false', 'useprefix=true', 'maxnames=3']) {
        throw new RuntimeException('BibTeX CSL entry-options handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Entry options Smith (2026) keep bounded BibLaTeX switches visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Options Review Manual. Review Press, 2026. BibLaTeX options: skipbib=false; useprefix=true; maxnames=3.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL entry-options self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-entry-options-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
