<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Index Title Review

Index-title source @index-title-manual and chapter [@inherited-index-chapter] keep generated source indexes reviewable.
MARKDOWN;

$bibtex = <<<'BIB'
@book{index-title-manual,
  author         = {Smith, Ada},
  title          = {The Source Audit Companion},
  indextitle     = {Source Audit Companion, The},
  indexsorttitle = {Source Audit Companion},
  date           = {2026},
  publisher      = {Review Press}
}

@inbook{inherited-index-chapter,
  author   = {Ng, Nia},
  title    = {Checklist Chapter},
  pages    = {12--18},
  crossref = {index-title-manual}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $manual = $processor->item('index-title-manual');
    if (($manual['indexTitle'] ?? null) !== 'Source Audit Companion, The') {
        throw new RuntimeException('BibTeX CSL index-title handoff did not preserve indextitle');
    }
    if (($manual['indexSortTitle'] ?? null) !== 'Source Audit Companion') {
        throw new RuntimeException('BibTeX CSL index-title handoff did not preserve indexsorttitle');
    }

    $chapter = $processor->item('inherited-index-chapter');
    if (($chapter['indexTitle'] ?? null) !== 'Source Audit Companion, The') {
        throw new RuntimeException('BibTeX CSL index-title handoff did not inherit indextitle from crossref parent');
    }
    if (($chapter['indexSortTitle'] ?? null) !== 'Source Audit Companion') {
        throw new RuntimeException('BibTeX CSL index-title handoff did not inherit indexsorttitle from crossref parent');
    }

    foreach ([
        '<p>Index-title source Smith (2026) and chapter (Ng 2026) keep generated source indexes reviewable.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. The Source Audit Companion. Review Press, 2026. Index title: Source Audit Companion, The. Index sort title: Source Audit Companion.</dd>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Checklist Chapter. The Source Audit Companion. Review Press, 2026. 12-18. Index title: Source Audit Companion, The. Index sort title: Source Audit Companion.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL index-title self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-index-title-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
