<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Skipbib Review

Visible source @visible-manual cites supplemental packets [@suppressed-snapshot; @bare-skip-source] without listing hidden packets.
MARKDOWN;

$bibtex = <<<'BIB'
@book{visible-manual,
  author    = {Smith, Ada},
  title     = {Visible Review Manual},
  date      = {2026},
  publisher = {Review Press},
  options   = {skipbib=false, useprefix=true}
}

@online{suppressed-snapshot,
  author  = {Desk, Review},
  title   = {Suppressed Review Snapshot},
  date    = {2025},
  url     = {https://example.test/suppressed},
  options = {skipbib=true, dashed=false}
}

@misc{bare-skip-source,
  author  = {Ng, Nia},
  title   = {Bare Skip Source},
  date    = {2024},
  options = {skipbib}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $visible = $processor->item('visible-manual');
    $suppressed = $processor->item('suppressed-snapshot');
    $bare = $processor->item('bare-skip-source');
    if (($visible['biblatexSkipBibliography'] ?? null) !== false || ($visible['biblatexBibliographyVisibility'] ?? null) !== 'include') {
        throw new RuntimeException('BibTeX CSL skipbib handoff did not mark visible entries for bibliography inclusion');
    }
    if (($suppressed['biblatexSkipBibliography'] ?? null) !== true || ($suppressed['biblatexBibliographyVisibility'] ?? null) !== 'omit') {
        throw new RuntimeException('BibTeX CSL skipbib handoff did not mark skipbib=true entries for bibliography omission');
    }
    if (($bare['biblatexSkipBibliography'] ?? null) !== true || ($bare['biblatexBibliographyVisibility'] ?? null) !== 'omit') {
        throw new RuntimeException('BibTeX CSL skipbib handoff did not mark bare skipbib entries for bibliography omission');
    }

    $direct = $processor->renderBibliographyEntry('suppressed-snapshot');
    if (!str_contains($direct, 'BibLaTeX options: skipbib=true; dashed=false.')) {
        throw new RuntimeException('BibTeX CSL skipbib handoff hid direct review rendering for skipped entries');
    }

    foreach ([
        '<p>Visible source Smith (2026) cites supplemental packets (Desk 2025; Ng 2024) without listing hidden packets.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Visible Review Manual. Review Press, 2026. BibLaTeX options: skipbib=false; useprefix=true.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL skipbib self-test missing expected snippet: ' . $snippet);
        }
    }

    foreach (['<dt>Desk 2025</dt>', '<dt>Ng 2024</dt>'] as $hiddenSnippet) {
        if (str_contains($blocks, $hiddenSnippet)) {
            throw new RuntimeException('BibTeX CSL skipbib self-test exposed hidden bibliography snippet: ' . $hiddenSnippet);
        }
    }

    echo "wordpress-bibtex-csl-skipbib-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
