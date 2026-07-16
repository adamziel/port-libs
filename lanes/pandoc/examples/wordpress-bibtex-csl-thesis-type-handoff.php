<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Thesis Type Review

Thesis sources @doctoral-import and [@masters-import] keep degree metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@phdthesis{doctoral-import,
  author = {Smith, Ada},
  title  = {Doctoral Import Study},
  date   = {2026},
  school = {Migration University},
  type   = {Doctoral dissertation},
  url    = {https://example.test/doctoral-import}
}

@mathesis{masters-import,
  author = {Ng, Nia},
  title  = {Masters Import Study},
  date   = {2025},
  school = {Source University}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $doctoral = $processor->item('doctoral-import');
    $masters = $processor->item('masters-import');
    if (($doctoral['type'] ?? null) !== 'thesis' || ($masters['type'] ?? null) !== 'thesis') {
        throw new RuntimeException('BibTeX CSL thesis handoff did not normalize thesis aliases to CSL thesis type');
    }
    if (($doctoral['thesisType'] ?? null) !== 'Doctoral dissertation') {
        throw new RuntimeException('BibTeX CSL thesis handoff did not preserve explicit thesis type');
    }
    if (($masters['thesisType'] ?? null) !== 'mathesis') {
        throw new RuntimeException('BibTeX CSL thesis handoff did not preserve mathesis alias metadata');
    }
    if (($masters['raw']['thesis-type'] ?? null) !== 'mathesis') {
        throw new RuntimeException('BibTeX CSL thesis handoff did not preserve raw CSL thesis-type metadata');
    }

    foreach ([
        '<p>Thesis sources Smith (2026) and (Ng 2025) keep degree metadata visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Doctoral Import Study. Migration University, 2026. Thesis type: Doctoral dissertation. https://example.test/doctoral-import.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Masters Import Study. Source University, 2025. Thesis type: mathesis.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL thesis-type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-thesis-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
