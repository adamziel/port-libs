<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Rights Review

Rights source @rights-dataset and copyright snapshot [@copyright-snapshot] keep source-use metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@dataset{rights-dataset,
  author = {Ng, Nia},
  title  = {Rights Review Dataset},
  date   = {2026},
  rights = {CC BY-SA 4.0 review required},
  doi    = {10.5555/rights-data}
}

@online{copyright-snapshot,
  author    = {{Archive Desk}},
  title     = {Copyright Snapshot},
  date      = {2025},
  copyright = {Copyright 2025 Source Archive},
  url       = {https://example.test/copyright-snapshot}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $dataset = $processor->item('rights-dataset');
    if (($dataset['rights'] ?? null) !== 'CC BY-SA 4.0 review required') {
        throw new RuntimeException('BibTeX CSL rights handoff did not preserve rights metadata');
    }

    $snapshot = $processor->item('copyright-snapshot');
    if (($snapshot['rights'] ?? null) !== 'Copyright 2025 Source Archive') {
        throw new RuntimeException('BibTeX CSL rights handoff did not preserve copyright metadata as rights');
    }

    foreach ([
        '<p>Rights source Ng (2026) and copyright snapshot (Archive Desk 2025) keep source-use metadata visible.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Rights Review Dataset. 2026. Rights: CC BY-SA 4.0 review required. DOI 10.5555/rights-data.</dd>',
        '<dt>Archive Desk 2025</dt><dd>Archive Desk. Copyright Snapshot. 2025. Rights: Copyright 2025 Source Archive. https://example.test/copyright-snapshot.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL rights self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-rights-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
