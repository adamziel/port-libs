<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Series Abbreviation Review

Series sources @series-short-detail and [@series-short-alias] keep abbreviated collection metadata visible for review.
MARKDOWN;

$bibtex = <<<'BIB'
@book{series-short-detail,
  author       = {Curator, Eli},
  title        = {Source Series Handbook},
  date         = {2026},
  series       = {Migration Review Studies},
  shortseries  = {Migr. Rev. Stud.},
  seriesnumber = {5},
  publisher    = {Review Press}
}

@incollection{series-short-alias,
  author       = {Ng, Nia},
  title        = {Attachment Series Notes},
  booktitle    = {Import Handbook},
  date         = {2025},
  series       = {Source Notes Series},
  series-short = {Src. Notes Ser.},
  seriesnumber = {12}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $series = $processor->item('series-short-detail');
    $alias = $processor->item('series-short-alias');
    if (($series['collectionTitleShort'] ?? null) !== 'Migr. Rev. Stud.') {
        throw new RuntimeException('BibTeX CSL series-short handoff did not preserve shortseries metadata');
    }
    if (($alias['collectionTitleShort'] ?? null) !== 'Src. Notes Ser.') {
        throw new RuntimeException('BibTeX CSL series-short handoff did not preserve series-short metadata');
    }

    foreach ([
        '<p>Series sources Curator (2026) and (Ng 2025) keep abbreviated collection metadata visible for review.</p>',
        '<dt>Curator 2026</dt><dd>Curator, Eli. Source Series Handbook. Migration Review Studies, no. 5. Series abbreviation: Migr. Rev. Stud. Review Press, 2026.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Attachment Series Notes. Import Handbook. Source Notes Series, no. 12. Series abbreviation: Src. Notes Ser. 2025.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL series-short self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-series-short-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
