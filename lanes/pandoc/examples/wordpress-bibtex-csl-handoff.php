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

Accented .bib names such as @accented-source remain readable in bibliography review.

The xdata-backed glossary entry @source-glossary keeps reviewer packet metadata attached.

A BibLaTeX entry set @migration-review-set keeps data-only member summaries available for review.

The related manual @related-manual keeps companion entry metadata attached to the source packet.

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

@article{accented-source,
  author       = {M{\"u}ller, Mia and Garc{\'i}a, Gia and {{S{\o}ren Archive Team}}},
  editor       = {Fran{\c c}ois, Ren{\'e}e},
  title        = {{\'E}tude of Jalape{\~n}o Source Packets},
  journaltitle = {Cr{\`e}me Br{\^u}l{\'e}e Review},
  publisher    = {Rev{\"u} Press},
  date         = {2026-06-05},
  pages        = {7--9},
  url          = {https://example.test/accented}
}

@xdata{shared-review-packet,
  publisher = {Migration Desk},
  date      = {2026-06-05},
  keywords  = {wordpress, import, reviewer},
  abstract  = {Reviewer summary for source packet handoff.}
}

@xdata{attachment-review-packet,
  langid = {english},
  file   = {Review PDF:attachments/source-audit.pdf:application/pdf; Source HTML:attachments/source-audit.html:text/html}
}

@inreference{source-glossary,
  author    = {Ng, Nia},
  title     = {Import Glossary},
  booktitle = {Migration Reference},
  url       = {https://example.test/glossary},
  xdata     = {shared-review-packet, attachment-review-packet}
}

@set{migration-review-set,
  title    = {Migration Review Set},
  date     = {2026-06-05},
  entryset = {set-audit-paper, set-archived-site, missing-source}
}

@proceedings{set-conf2026,
  options   = {dataonly},
  title     = {Migration Futures Conference},
  date      = {2026},
  publisher = {Review Press}
}

@inproceedings{set-audit-paper,
  options  = {dataonly},
  author   = {Smith, Ada},
  title    = {Packet Audit Trails},
  pages    = {12--18},
  crossref = {set-conf2026}
}

@online{set-archived-site,
  options = {dataonly},
  author  = {{Archive Team}},
  title   = {Archive Site},
  date    = {2026-05-31},
  url     = {https://example.test/archive-site}
}

@book{related-manual,
  author        = {Curator, Eli},
  title         = {Migration Manual},
  date          = {2024},
  related       = {migration-review-set, missing-related},
  relatedtype   = {companion},
  relatedstring = {Companion review set}
}
BIB;

$processor = CitationCslProcessor::fromBibtex($bibtex);
$document = $processor->appendBibliography((new MarkdownReader())->read($markdown), 'Works Cited');
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    $sourceGlossary = $processor->item('source-glossary');
    if (($sourceGlossary['language'] ?? null) !== 'english') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not inherit source-glossary language metadata');
    }
    if (($sourceGlossary['keywords'] ?? null) !== ['wordpress', 'import', 'reviewer']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not inherit source-glossary keywords metadata');
    }
    if (($sourceGlossary['sourceFiles'][0]['path'] ?? null) !== 'attachments/source-audit.pdf') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not inherit source-glossary attachment metadata');
    }
    $reviewSet = $processor->item('migration-review-set');
    if (($reviewSet['raw']['entrySet'] ?? null) !== ['set-audit-paper', 'set-archived-site', 'missing-source']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve migration-review-set entry keys');
    }
    if (($reviewSet['raw']['entrySetItems'][0]['container-title'] ?? null) !== 'Migration Futures Conference') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize set member crossref metadata');
    }
    if (($reviewSet['raw']['missingEntrySetKeys'] ?? null) !== ['missing-source']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve missing entry-set keys');
    }
    $relatedManual = $processor->item('related-manual');
    if (($relatedManual['raw']['relatedType'] ?? null) !== 'companion') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve related manual relationship type');
    }
    if (($relatedManual['raw']['missingRelatedKeys'] ?? null) !== ['missing-related']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve missing related keys');
    }

    foreach ([
        '<p>The source packet cites (see Smith 1899; Doe and Roe 2020, pp. 55-60).</p>',
        '<p>The reviewer queue keeps de la Cruz (2026) attached to imported source access notes.</p>',
        '<p>A proceedings child entry inherits Smith (2026) conference metadata for reviewer bibliographies.</p>',
        '<p>Accented .bib names such as Müller et al. (2026) remain readable in bibliography review.</p>',
        '<p>The xdata-backed glossary entry Ng (2026) keeps reviewer packet metadata attached.</p>',
        '<p>A BibLaTeX entry set Migration Review Set (2026) keeps data-only member summaries available for review.</p>',
        '<p>The related manual Curator (2024) keeps companion entry metadata attached to the source packet.</p>',
        '<dt>Doe and Roe 2020</dt><dd>Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes. Accessed 2026-06-04.</dd>',
        '<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria, Jr. Source Packet. 2026. https://example.test/source-packet.</dd>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Packet Audit Trails. Migration Futures Conference. Review Press, 2026. 12-18.</dd>',
        '<dt>Müller et al. 2026</dt><dd>Müller, Mia; García, Gia; Søren Archive Team. Étude of Jalapeño Source Packets. Crème Brûlée Review. Revü Press, 2026. 7-9. https://example.test/accented.</dd>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Import Glossary. Migration Reference. Migration Desk, 2026. https://example.test/glossary.</dd>',
        '<dt>Migration Review Set 2026</dt><dd>Migration Review Set. 2026.</dd>',
        '<dt>Curator 2024</dt><dd>Curator, Eli. Migration Manual. 2024.</dd>',
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
