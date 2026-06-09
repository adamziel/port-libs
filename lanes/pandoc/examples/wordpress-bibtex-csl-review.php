<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Citation Review

Reviewer note cites @lovelace1843 before @fielding2000, while @missing-key stays visible for the import log.
MARKDOWN;

$bibtex = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-bibtex-csl-review.bib');
$processor = new BibtexCslProcessor();
$document = (new MarkdownReader())->read($markdown);
$handoff = $processor->citationHandoff($document, $bibtex);
$documentWithBibliography = new AstNode('document', $document->attrs, [
    ...$document->children,
    new AstNode('heading', ['level' => 2, 'id' => 'references'], [
        new AstNode('text', ['text' => 'References']),
    ]),
    $handoff['bibliography'],
]);

$blocks = (new WordPressBlockWriter())->write($documentWithBibliography);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<h1 id="citation-review">Citation Review</h1>',
        '<dt>lovelace1843</dt>',
        'Ada Lovelace and Luigi Federico Menabrea. Notes on the Analytical Engine.',
        '<dt>fielding2000</dt>',
        'Missing bibliography entry: missing-key',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('BibTeX/CSL WordPress handoff missing expected output: ' . $needle);
        }
    }

    echo "BibTeX/CSL WordPress handoff self-test passed\n";
    exit(0);
}

echo $blocks;
