<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX List of Shorthands Review

Reviewers cite @zeta-shorthand, @alpha-shorthand, and [@fallback-shorthand] while keeping a source shorthand list available for editorial review.
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

@online{fallback-shorthand,
  title     = {Fallback Shorthand Packet},
  date      = {2024},
  shorthand = {B-3},
  url       = {https://example.test/fallback-shorthand}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$document = $processor->appendShorthandList($document, 'List of Shorthands');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $listBlocks = $processor->shorthandListBlocks('List of Shorthands');
    if (count($listBlocks) !== 2 || $listBlocks[1]->type !== 'definition_list') {
        throw new RuntimeException('BibTeX CSL shorthand list did not emit a heading and definition list');
    }

    $list = $listBlocks[1];
    $terms = array_map(
        static fn (AstNode $item): string => (string) $item->children[0]->attr('text'),
        $list->children
    );
    if ($terms !== ['A-2', 'Z-10', 'B-3']) {
        throw new RuntimeException('BibTeX CSL shorthand list did not apply list sort keys before fallback shorthand ordering');
    }

    foreach ([
        '<h2 id="list-of-shorthands">List of Shorthands</h2>',
        '<dt>A-2</dt><dd>Alpha Source Manual.</dd>',
        '<dt>Z-10</dt><dd>listed as Zeta Source. Zeta Source Manual.</dd>',
        '<dt>B-3</dt><dd>Fallback Shorthand Packet.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL shorthand list self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-shorthand-list-handoff self-test passed\n";
    exit(0);
}

echo $blocks;
