<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Standard Name Suffix Review

Standard suffix names [@standard-suffix-review] keep reviewer credit metadata attached.
MARKDOWN;

$bibtex = <<<'BIB'
@book{standard-suffix-review,
  author    = {Smith, Jr., Ada and de la Cruz, III, Ana Maria},
  editor    = {Doe, Sr., Jane},
  title     = {Standard Name Packet},
  publisher = {Review Press},
  year      = {2026}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $item = $processor->item('standard-suffix-review');

    if (($item['authors'][0]['given'] ?? null) !== 'Ada' || ($item['authors'][0]['suffix'] ?? null) !== 'Jr.') {
        throw new RuntimeException('BibTeX CSL standard name suffix handoff did not map Smith, Jr., Ada correctly');
    }
    if (($item['authors'][1]['given'] ?? null) !== 'Ana Maria' || ($item['authors'][1]['suffix'] ?? null) !== 'III') {
        throw new RuntimeException('BibTeX CSL standard name suffix handoff did not map de la Cruz, III, Ana Maria correctly');
    }
    if (($item['editors'][0]['given'] ?? null) !== 'Jane' || ($item['editors'][0]['suffix'] ?? null) !== 'Sr.') {
        throw new RuntimeException('BibTeX CSL standard name suffix handoff did not map editor suffix metadata correctly');
    }

    foreach ([
        '<p>Standard suffix names (Smith and de la Cruz 2026) keep reviewer credit metadata attached.</p>',
        '<dt>Smith and de la Cruz 2026</dt><dd>Smith, Ada, Jr.; de la Cruz, Ana Maria, III. Standard Name Packet. Review Press, 2026.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL standard name suffix self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-standard-name-suffix-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
