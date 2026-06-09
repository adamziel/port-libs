<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = 'Legacy access dates [@lastchecked-source; @lastaccessed-source; @visited-source] stay reviewable.';

$bibtex = <<<'BIB'
@online{lastchecked-source,
  author      = {Ng, Nia},
  title       = {Last Checked Source},
  date        = {2026},
  url         = {https://example.test/lastchecked-source},
  lastchecked = {2026-06-07}
}

@online{lastaccessed-source,
  author       = {{Review Desk}},
  title        = {Last Accessed Source},
  date         = {2025},
  url          = {https://example.test/lastaccessed-source},
  lastaccessed = {2026-06?}
}

@misc{visited-source,
  author  = {Curator, Eli},
  title   = {Visited Source},
  date    = {2024},
  url     = {https://example.test/visited-source},
  visited = {review queue}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $lastChecked = $processor->item('lastchecked-source');
    $lastAccessed = $processor->item('lastaccessed-source');
    $visited = $processor->item('visited-source');

    if (($lastChecked['accessedDate']['display'] ?? null) !== '2026-06-07') {
        throw new RuntimeException('BibTeX CSL access-date alias handoff did not map lastchecked into accessed date metadata');
    }
    if (($lastAccessed['accessedDate']['uncertain'] ?? null) !== true) {
        throw new RuntimeException('BibTeX CSL access-date alias handoff did not preserve lastaccessed uncertainty');
    }
    if (($visited['accessedDate']['literal'] ?? null) !== 'review queue') {
        throw new RuntimeException('BibTeX CSL access-date alias handoff did not preserve literal visited metadata');
    }
    if (($lastChecked['raw']['rawBibtex']['fields']['lastchecked'] ?? null) !== '2026-06-07') {
        throw new RuntimeException('BibTeX CSL access-date alias handoff did not preserve raw lastchecked provenance');
    }

    foreach ([
        '<p>Legacy access dates (Ng 2026; Review Desk 2025; Curator 2024) stay reviewable.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Last Checked Source. 2026. https://example.test/lastchecked-source. Accessed 2026-06-07.</dd>',
        '<dt>Review Desk 2025</dt><dd>Review Desk. Last Accessed Source. 2025. Date markers: accessed uncertain (2026-06?). https://example.test/lastaccessed-source. Accessed 2026-06.</dd>',
        '<dt>Curator 2024</dt><dd>Curator, Eli. Visited Source. 2024. https://example.test/visited-source. Accessed review queue.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL access-date alias handoff missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-access-date-alias-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
