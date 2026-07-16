<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Sort Shorthand Review

Reviewers cite @zeta-shorthand and [@alpha-shorthand] while preserving list-of-shorthands sort keys.
MARKDOWN;

$bibtex = <<<'BIB'
@book{zeta-shorthand,
  author         = {Zed, Zoe},
  title          = {Zeta Source Manual},
  date           = {2026},
  publisher      = {Review Press},
  shorthand      = {Z-10},
  sortshorthand  = {010 zeta source},
  shorthandintro = {listed as Zeta Source}
}

@book{alpha-shorthand,
  author         = {Adams, Ada},
  title          = {Alpha Source Manual},
  date           = {2025},
  publisher      = {Review Press},
  shorthand      = {A-2},
  sort-shorthand = {002 alpha source}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $zeta = $processor->item('zeta-shorthand');
    $alpha = $processor->item('alpha-shorthand');
    if (($zeta['sortShorthand'] ?? null) !== '010 zeta source') {
        throw new RuntimeException('BibTeX CSL sort shorthand handoff did not preserve zeta sortshorthand metadata');
    }
    if (($zeta['shorthandListSortKey'] ?? null) !== '010 zeta source') {
        throw new RuntimeException('BibTeX CSL sort shorthand handoff did not preserve zeta list sort key metadata');
    }
    if (($alpha['sortShorthand'] ?? null) !== '002 alpha source') {
        throw new RuntimeException('BibTeX CSL sort shorthand handoff did not preserve alpha sort-shorthand metadata');
    }

    foreach ([
        '<p>Reviewers cite Z-10 and (A-2) while preserving list-of-shorthands sort keys.</p>',
        '<dt>Z-10</dt><dd>Zed, Zoe. Zeta Source Manual. Review Press, 2026. Sort shorthand: 010 zeta source.</dd>',
        '<dt>A-2</dt><dd>Adams, Ada. Alpha Source Manual. Review Press, 2025. Sort shorthand: 002 alpha source.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL sort shorthand self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-sort-shorthand-handoff self-test passed\n";
    exit(0);
}

echo $blocks;
