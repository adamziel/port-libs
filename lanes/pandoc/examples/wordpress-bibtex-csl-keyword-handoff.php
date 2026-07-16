<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Keyword Review

Keyword source @keyword-manual and snapshot [@keyword-snapshot] keep imported review tags visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{keyword-manual,
  author    = {Smith, Ada},
  title     = {Keyword Review Manual},
  date      = {2026},
  publisher = {Review Press},
  keywords  = {wordpress, data liberation, source audit}
}

@online{keyword-snapshot,
  author   = {{Archive Desk}},
  title    = {Keyword Snapshot},
  date     = {2025},
  keyword  = {media audit; block imports; needs review},
  url      = {https://example.test/keyword-snapshot}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $manual = $processor->item('keyword-manual');
    if (($manual['keywords'] ?? null) !== ['wordpress', 'data liberation', 'source audit']) {
        throw new RuntimeException('BibTeX CSL keyword handoff did not preserve comma-separated keywords');
    }
    if (($manual['keywordSummary'] ?? null) !== 'wordpress; data liberation; source audit') {
        throw new RuntimeException('BibTeX CSL keyword handoff did not preserve the keyword summary');
    }
    $snapshot = $processor->item('keyword-snapshot');
    if (($snapshot['keywords'] ?? null) !== ['media audit', 'block imports', 'needs review']) {
        throw new RuntimeException('BibTeX CSL keyword handoff did not preserve semicolon-separated keywords');
    }

    foreach ([
        '<p>Keyword source Smith (2026) and snapshot (Archive Desk 2025) keep imported review tags visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Keyword Review Manual. Review Press, 2026. Keywords: wordpress; data liberation; source audit.</dd>',
        '<dt>Archive Desk 2025</dt><dd>Archive Desk. Keyword Snapshot. 2025. Keywords: media audit; block imports; needs review. https://example.test/keyword-snapshot.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL keyword self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-keyword-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
