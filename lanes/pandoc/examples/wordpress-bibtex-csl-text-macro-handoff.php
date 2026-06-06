<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Text Macro Review

Macro-wrapped source @macro-source keeps bibliography text readable for import review.
MARKDOWN;

$bibtex = <<<'BIB'
@article{macro-source,
  author       = {Smith, Ada},
  title        = {\mkbibemph{\textsc{Packet}} \textit{Review} \textsuperscript{Draft} \textsubscript{v2}},
  journaltitle = {\mkbibquote{Import \textbf{Desk}}},
  publisher    = {\textnormal{Review} \textsf{Press}},
  note         = {\mkbibparens{\texttt{macro-wrapper} source}},
  date         = {2026},
  url          = {https://example.test/macro-source}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('macro-source');
    if (($item['title'] ?? null) !== 'Packet Review Draft v2') {
        throw new RuntimeException('BibTeX CSL text macro handoff leaked title wrapper macros');
    }
    if (($item['containerTitle'] ?? null) !== 'Import Desk') {
        throw new RuntimeException('BibTeX CSL text macro handoff leaked container-title wrapper macros');
    }
    if (($item['note'] ?? null) !== 'macro-wrapper source') {
        throw new RuntimeException('BibTeX CSL text macro handoff leaked note wrapper macros');
    }

    foreach ([
        '<p>Macro-wrapped source Smith (2026) keeps bibliography text readable for import review.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Packet Review Draft v2. Import Desk. Review Press, 2026. Note: macro-wrapper source. https://example.test/macro-source.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL text macro handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-text-macro-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
