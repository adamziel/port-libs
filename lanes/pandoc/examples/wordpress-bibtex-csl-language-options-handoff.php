<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Language Options Review

Language option source @language-options-manual keeps BibLaTeX locale switches visible.
MARKDOWN;

$bibtex = <<<'BIB'
@book{language-options-manual,
  author     = {Garcia, Nia},
  title      = {Language Option Review Manual},
  date       = {2026},
  publisher  = {Review Press},
  langid     = {spanish},
  langidopts = {variant=mexican, hyphenation=traditional, sentencecase=false}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('language-options-manual');
    if (($item['language'] ?? null) !== 'spanish') {
        throw new RuntimeException('BibTeX CSL language-options handoff did not preserve langid');
    }
    if (($item['biblatexLanguageOptions'] ?? null) !== ['variant=mexican', 'hyphenation=traditional', 'sentencecase=false']) {
        throw new RuntimeException('BibTeX CSL language-options handoff did not preserve normalized options');
    }
    if (($item['biblatexLanguageOptionSummary'] ?? null) !== 'variant=mexican; hyphenation=traditional; sentencecase=false') {
        throw new RuntimeException('BibTeX CSL language-options handoff did not preserve the display summary');
    }
    if (($item['raw']['biblatex-language-options'] ?? null) !== ['variant=mexican', 'hyphenation=traditional', 'sentencecase=false']) {
        throw new RuntimeException('BibTeX CSL language-options handoff did not preserve raw CSL metadata');
    }

    foreach ([
        '<p>Language option source Garcia (2026) keeps BibLaTeX locale switches visible.</p>',
        '<dt>Garcia 2026</dt><dd>Garcia, Nia. Language Option Review Manual. Review Press, 2026. BibLaTeX language options: variant=mexican; hyphenation=traditional; sentencecase=false.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL language-options self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-language-options-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
