<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Alias Review

Alias source @legacy-alias and canonical [@canonical-alias-review] keep source-era citation keys visible.
MARKDOWN;

$bibtex = <<<'BIB'
@online{canonical-alias-review,
  author = {{Alias Review Desk}},
  title  = {Canonical Alias Packet},
  date   = {2026},
  url    = {https://example.test/canonical-alias},
  ids    = {legacy-alias, migrated-source-alias}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $canonical = $processor->item('canonical-alias-review');
    $legacyAlias = $processor->item('legacy-alias');

    if (($canonical['citationAliases'] ?? null) !== ['legacy-alias', 'migrated-source-alias']) {
        throw new RuntimeException('BibTeX CSL citation-alias handoff did not preserve canonical alias list');
    }
    if (($canonical['citationAliasSummary'] ?? null) !== 'legacy-alias; migrated-source-alias') {
        throw new RuntimeException('BibTeX CSL citation-alias handoff did not preserve alias summary');
    }
    if (($legacyAlias['id'] ?? null) !== 'canonical-alias-review') {
        throw new RuntimeException('BibTeX CSL citation-alias handoff did not resolve legacy alias to canonical id');
    }
    if (($legacyAlias['citationAlias'] ?? null) !== 'legacy-alias') {
        throw new RuntimeException('BibTeX CSL citation-alias handoff did not preserve active alias provenance');
    }

    foreach ([
        '<p>Alias source Alias Review Desk (2026) and canonical (Alias Review Desk 2026) keep source-era citation keys visible.</p>',
        '<dt>Alias Review Desk 2026</dt><dd>Alias Review Desk. Canonical Alias Packet. 2026. Citation aliases: legacy-alias; migrated-source-alias. https://example.test/canonical-alias.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL citation-alias self-test missing expected snippet: ' . $snippet);
        }
    }

    if (substr_count($blocks, '<dt>Alias Review Desk 2026</dt>') !== 1) {
        throw new RuntimeException('BibTeX CSL citation-alias self-test rendered duplicate alias bibliography entries');
    }

    echo "wordpress-bibtex-csl-citation-alias-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
