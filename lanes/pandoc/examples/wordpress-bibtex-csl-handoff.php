<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibTeX Citation Import Review

The source packet cites [see @smith1899; @doe2020, pp. 55-60].

The reviewer queue keeps @particle-source attached to imported source access notes.

A proceedings child entry inherits @source-audit conference metadata for reviewer bibliographies.

Missing bibliography keys such as [@missing-source] remain visible for follow-up.
MARKDOWN;

$bibtex = <<<'BIB'
@string{packet = "Packet"}

@book{smith1899,
  author    = {Smith, Ada},
  title     = {Migration Patterns},
  year      = {1899},
  publisher = {Archive Press},
  doi       = {10.1234/source}
}

@article{doe2020,
  author       = {Doe, Jane and Roe, Pat},
  title        = {Field Notes},
  journaltitle = {Journal of Imports},
  date         = {2020-06-01},
  pages        = {55--60},
  url          = {https://example.test/field-notes},
  urldate      = {2026-06-04}
}

@online{particle-source,
  author = {de la Cruz, Ana Maria, Jr.},
  title  = "Source " # packet,
  year   = {2026},
  month  = jun,
  day    = {4},
  url    = {https://example.test/source-packet}
}

@proceedings{conf2026,
  editor    = {Curator, Eli and de la Cruz, Ana Maria},
  title     = {Migration Futures Conference},
  year      = {2026},
  publisher = {Review Press}
}

@inproceedings{source-audit,
  author   = {Smith, Ada},
  title    = {Packet Audit Trails},
  pages    = {12--18},
  crossref = {conf2026}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<p>The source packet cites (see Smith 1899; Doe and Roe 2020, pp. 55-60).</p>',
        '<p>The reviewer queue keeps de la Cruz (2026) attached to imported source access notes.</p>',
        '<p>A proceedings child entry inherits Smith (2026) conference metadata for reviewer bibliographies.</p>',
        '<dt>Doe and Roe 2020</dt><dd>Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes. Accessed 2026-06-04.</dd>',
        '<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria, Jr. Source Packet. 2026. https://example.test/source-packet.</dd>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Packet Audit Trails. Migration Futures Conference. Review Press, 2026. 12-18.</dd>',
        '<p>Missing bibliography keys such as [@missing-source] remain visible for follow-up.</p>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
