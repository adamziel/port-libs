<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Author Type Review

Author-type source @compiled-source-manual and chapter [@container-type-chapter] keep BibLaTeX role qualifiers visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{compiled-source-manual,
  author     = {Roe, Pat and {{Migration Desk}}},
  authortype = {compiler},
  title      = {Compiled Source Manual},
  date       = {2026},
  publisher  = {Review Press}
}

@incollection{container-type-chapter,
  author         = {Ng, Nia},
  bookauthor     = {Smith, Ada and Curator, Eli},
  bookauthortype = {source volume author},
  title          = {Container Type Chapter},
  booktitle      = {Migration Sourcebook},
  date           = {2025},
  pages          = {44--49}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $compiled = $processor->item('compiled-source-manual');
    $chapter = $processor->item('container-type-chapter');

    if (($compiled['authorType'] ?? null) !== 'compiler') {
        throw new RuntimeException('BibTeX CSL author-type handoff did not preserve normalized authortype metadata');
    }
    if (($chapter['containerAuthorType'] ?? null) !== 'source volume author') {
        throw new RuntimeException('BibTeX CSL author-type handoff did not preserve normalized bookauthortype metadata');
    }
    if (($compiled['raw']['rawBibtex']['fields']['authortype'] ?? null) !== 'compiler') {
        throw new RuntimeException('BibTeX CSL author-type handoff did not preserve raw authortype metadata');
    }
    if (($chapter['raw']['rawBibtex']['fields']['bookauthortype'] ?? null) !== 'source volume author') {
        throw new RuntimeException('BibTeX CSL author-type handoff did not preserve raw bookauthortype metadata');
    }
    if (($chapter['containerAuthors'][0]['family'] ?? null) !== 'Smith') {
        throw new RuntimeException('BibTeX CSL author-type handoff did not preserve container author names');
    }

    foreach ([
        '<p>Author-type source Roe and Migration Desk (2026) and chapter (Ng 2025) keep BibLaTeX role qualifiers visible.</p>',
        '<dt>Roe and Migration Desk 2026</dt><dd>Roe, Pat; Migration Desk. Compiled Source Manual. Review Press, 2026. Author type: compiler.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Container Type Chapter. Migration Sourcebook. 2025. 44-49. Container author type: source volume author. Container author: Smith, Ada; Curator, Eli.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL author-type self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-author-type-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
