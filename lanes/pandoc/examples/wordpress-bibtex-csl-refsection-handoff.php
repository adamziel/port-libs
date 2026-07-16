<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Reference Context Review

Reference context @sectioned-manual and segment [@segment-snapshot] keep imported bibliography partitions visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{sectioned-manual,
  author     = {Smith, Ada},
  title      = {Sectioned Review Manual},
  date       = {2026},
  publisher  = {Review Press},
  refsection = {2},
  refsegment = {migration-import}
}

@online{segment-snapshot,
  author     = {{Archive Desk}},
  title      = {Segment Snapshot},
  date       = {2025},
  url        = {https://example.test/segment-snapshot},
  refsegment = {media-audit}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $manual = $processor->item('sectioned-manual');
    if (($manual['biblatexRefsection'] ?? null) !== '2') {
        throw new RuntimeException('BibTeX CSL refsection handoff did not preserve the section id');
    }
    if (($manual['biblatexRefsegment'] ?? null) !== 'migration-import') {
        throw new RuntimeException('BibTeX CSL refsection handoff did not preserve the segment id');
    }
    if (($manual['biblatexReferenceContextSummary'] ?? null) !== 'refsection 2; refsegment migration-import') {
        throw new RuntimeException('BibTeX CSL refsection handoff did not preserve the reference context summary');
    }
    if (($manual['raw']['biblatex-refsection'] ?? null) !== '2') {
        throw new RuntimeException('BibTeX CSL refsection handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Reference context Smith (2026) and segment (Archive Desk 2025) keep imported bibliography partitions visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Sectioned Review Manual. Review Press, 2026. BibLaTeX reference context: refsection 2; refsegment migration-import.</dd>',
        '<dt>Archive Desk 2025</dt><dd>Archive Desk. Segment Snapshot. 2025. BibLaTeX reference context: refsegment media-audit. https://example.test/segment-snapshot.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL refsection self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-refsection-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
