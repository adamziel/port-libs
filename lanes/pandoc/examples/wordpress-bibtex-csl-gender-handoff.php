<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Gender Review

Gendered source @gendered-manual keeps BibLaTeX driver grammar metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{gendered-manual,
  author    = {Smith, Ada},
  title     = {Gendered Driver Manual},
  date      = {2026},
  publisher = {Review Press},
  gender    = {feminine}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('gendered-manual');
    if (($item['gender'] ?? null) !== 'feminine') {
        throw new RuntimeException('BibTeX CSL gender handoff did not preserve the CSL gender alias');
    }
    if (($item['biblatexGender'] ?? null) !== 'feminine') {
        throw new RuntimeException('BibTeX CSL gender handoff did not preserve normalized BibLaTeX gender');
    }
    if (($item['raw']['gender'] ?? null) !== 'feminine') {
        throw new RuntimeException('BibTeX CSL gender handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Gendered source Smith (2026) keeps BibLaTeX driver grammar metadata visible.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Gendered Driver Manual. Review Press, 2026. BibLaTeX gender: feminine.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL gender self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-gender-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
