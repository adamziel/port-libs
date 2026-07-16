<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# BibLaTeX Crossref Title Parts Review

Crossref child @crossref-subtitle-paper keeps inherited proceedings subtitles as container metadata.

Journal child [@crossref-subtitle-article] keeps inherited periodical addenda out of its own title.
MARKDOWN;

$bibtex = <<<'BIB'
@proceedings{review-proceedings,
  options    = {dataonly},
  editor     = {Curator, Eli},
  title      = {Source Review Proceedings},
  subtitle   = {Reviewer Packet Track},
  titleaddon = {Proceedings supplement},
  publisher  = {Review Press},
  date       = {2026}
}

@inproceedings{crossref-subtitle-paper,
  author   = {Ng, Nia},
  title    = {Packet Audit Trails},
  pages    = {12--18},
  crossref = {review-proceedings}
}

@periodical{review-journal,
  options    = {dataonly},
  title      = {Journal of Import Reviews},
  subtitle   = {Source Desk Notes},
  titleaddon = {Online supplement},
  date       = {2025}
}

@article{crossref-subtitle-article,
  author   = {Roe, Pat},
  title    = {Journal Child Packet},
  pages    = {7--9},
  crossref = {review-journal}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $paper = $processor->item('crossref-subtitle-paper');
    $article = $processor->item('crossref-subtitle-article');
    if (($paper['title'] ?? null) !== 'Packet Audit Trails') {
        throw new RuntimeException('BibTeX CSL crossref title-parts handoff leaked proceedings subtitle into child title');
    }
    if (($paper['containerTitle'] ?? null) !== 'Source Review Proceedings: Reviewer Packet Track') {
        throw new RuntimeException('BibTeX CSL crossref title-parts handoff did not compose proceedings container title');
    }
    if (($paper['containerTitleAddon'] ?? null) !== 'Proceedings supplement') {
        throw new RuntimeException('BibTeX CSL crossref title-parts handoff did not map proceedings title addendum');
    }
    if (($article['title'] ?? null) !== 'Journal Child Packet') {
        throw new RuntimeException('BibTeX CSL crossref title-parts handoff leaked periodical subtitle into article title');
    }
    if (($article['containerTitle'] ?? null) !== 'Journal of Import Reviews: Source Desk Notes') {
        throw new RuntimeException('BibTeX CSL crossref title-parts handoff did not compose periodical container title');
    }
    if (($article['containerTitleAddon'] ?? null) !== 'Online supplement') {
        throw new RuntimeException('BibTeX CSL crossref title-parts handoff did not map periodical title addendum');
    }

    foreach ([
        '<p>Crossref child Ng (2026) keeps inherited proceedings subtitles as container metadata.</p>',
        '<p>Journal child (Roe 2025) keeps inherited periodical addenda out of its own title.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Packet Audit Trails. Source Review Proceedings: Reviewer Packet Track. Proceedings supplement. Review Press, 2026. 12-18.</dd>',
        '<dt>Roe 2025</dt><dd>Roe, Pat. Journal Child Packet. Journal of Import Reviews: Source Desk Notes. Online supplement. 2025. 7-9.</dd>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL crossref title-parts self-test missing expected snippet: ' . $snippet);
        }
    }

    echo "wordpress-bibtex-csl-crossref-title-parts-handoff self-test passed\n";
    exit(0);
}

echo $blocks;
