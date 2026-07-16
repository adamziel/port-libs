<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Reference Crossref Review

Reference children cite @source-term and [@embedded-source] with inherited container context for source review.
MARKDOWN;

$bibtex = <<<'BIB'
@reference{migration-reference,
  options   = {dataonly},
  editor    = {Curator, Eli},
  title     = {Migration Reference Desk},
  date      = {2026},
  publisher = {Review Press}
}

@inreference{source-term,
  author   = {Ng, Nia},
  title    = {Import Source Term},
  pages    = {42--43},
  crossref = {migration-reference}
}

@bookinbook{embedded-source,
  author   = {Roe, Pat},
  title    = {Embedded Audit Leaf},
  pages    = {9--11},
  crossref = {migration-reference}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $term = $processor->item('source-term');
    if (($term['type'] ?? null) !== 'entry-encyclopedia') {
        throw new RuntimeException('BibTeX CSL reference-crossref handoff did not preserve inreference CSL type');
    }
    if (($term['containerTitle'] ?? null) !== 'Migration Reference Desk') {
        throw new RuntimeException('BibTeX CSL reference-crossref handoff did not inherit reference title as container title');
    }
    if (($term['biblatexOptions'] ?? null) !== []) {
        throw new RuntimeException('BibTeX CSL reference-crossref handoff leaked data-only parent options into child metadata');
    }

    $embedded = $processor->item('embedded-source');
    if (($embedded['type'] ?? null) !== 'chapter') {
        throw new RuntimeException('BibTeX CSL reference-crossref handoff did not normalize bookinbook as chapter');
    }
    if (($embedded['containerTitle'] ?? null) !== 'Migration Reference Desk') {
        throw new RuntimeException('BibTeX CSL reference-crossref handoff did not inherit bookinbook container title');
    }

    foreach ([
        '<p>Reference children cite Ng (2026) and (Roe 2026) with inherited container context for source review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Import Source Term. Migration Reference Desk. Review Press, 2026. 42-43.</dd>',
        '<dt>Roe 2026</dt><dd>Roe, Pat. Embedded Audit Leaf. Migration Reference Desk. Review Press, 2026. 9-11.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL reference-crossref self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-reference-crossref-handoff self-test passed\n";
    exit(0);
}

echo $blocks . "\n";
