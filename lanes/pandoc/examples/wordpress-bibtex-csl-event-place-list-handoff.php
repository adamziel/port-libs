<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Distributed Event Place Review

The import packet cites @multi-venue-paper and [@multi-venue-proceedings] so reviewers can keep in-person and remote venue metadata visible.
MARKDOWN;

$bibtex = <<<'BIB'
@proceedings{multi-venue-proceedings,
  editor          = {Curator, Eli},
  title           = {Multi Venue Proceedings},
  eventtitle      = {WordCamp Review Summit},
  eventvenue      = {{Portland Convention Center} and {Remote Stream}},
  eventdate       = {2026-06-04/2026-06-05},
  date            = {2026},
  publisher       = {Migration Desk}
}

@inproceedings{multi-venue-paper,
  author   = {Ng, Nia},
  title    = {Distributed Venue Review},
  pages    = {60--64},
  crossref = {multi-venue-proceedings}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $paper = $processor->item('multi-venue-paper');
    if (($paper['eventPlace'] ?? null) !== 'Portland Convention Center; Remote Stream') {
        throw new RuntimeException('BibTeX CSL event-place-list handoff did not preserve scalar event place');
    }
    if (($paper['eventPlaceList'] ?? null) !== ['Portland Convention Center', 'Remote Stream']) {
        throw new RuntimeException('BibTeX CSL event-place-list handoff did not preserve event place list');
    }
    foreach ([
        '<p>The import packet cites Ng (2026) and (Curator 2026) so reviewers can keep in-person and remote venue metadata visible.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Distributed Venue Review. Multi Venue Proceedings. Event: WordCamp Review Summit. Event places: Portland Convention Center; Remote Stream. Event date 2026-06-04/2026-06-05. Migration Desk, 2026. 60-64.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL event-place-list self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-event-place-list-handoff self-test passed\n";
    return;
}

echo $blocks;
