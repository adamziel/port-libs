<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Entry Set Review

Reviewers cite @migration-review-set to keep source bundle members visible before import.
MARKDOWN;

$bibtex = <<<'BIB'
@set{migration-review-set,
  title    = {Migration Review Set},
  date     = {2026-06-05},
  entryset = {audit-paper, archived-site, missing-source}
}

@inproceedings{audit-paper,
  options = {dataonly},
  author  = {Smith, Ada},
  title   = {Packet Audit Trails},
  date    = {2026},
  pages   = {12--18}
}

@online{archived-site,
  options = {dataonly},
  author  = {{Archive Team}},
  title   = {Archive Site},
  date    = {2026-05-31},
  url     = {https://example.test/archive-site}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $set = $processor->item('migration-review-set');

    if (($set['entrySetKeys'] ?? null) !== ['audit-paper', 'archived-site', 'missing-source']) {
        throw new RuntimeException('BibTeX CSL entry-set handoff did not normalize member keys');
    }

    if (($set['entrySetSummary'] ?? null) !== 'Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source') {
        throw new RuntimeException('BibTeX CSL entry-set handoff did not summarize members and missing keys');
    }

    foreach ([
        '<p>Reviewers cite Migration Review Set (2026) to keep source bundle members visible before import.</p>',
        '<dt>Migration Review Set 2026</dt><dd>Migration Review Set. 2026. Entry set: Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL entry-set self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-entryset-handoff self-test passed\n";
    exit(0);
}

echo $blocks . "\n";
