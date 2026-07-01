<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\WordPressBlockWriter;

$citation = static function (string $id, string $text): AstNode {
    return new AstNode('citation', [
        'id' => $id,
        'text' => $text,
    ], [
        new AstNode('text', ['text' => $text]),
    ]);
};

return [
    'splits semicolon biblatex key lists for relation provenance handoff' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@xdata{shared-publisher,
  publisher = {Review Press},
  location  = {Portland},
  keywords  = {source packet; semicolon list}
}

@book{audit-paper,
  options = {dataonly},
  author  = {Smith, Ada},
  title   = {Packet Audit Trails},
  date    = {2026}
}

@online{archived-site,
  options = {dataonly},
  author  = {{Archive Team}},
  title   = {Archive Site},
  date    = {2026-05-31},
  url     = {https://example.test/archive-site}
}

@set{semicolon-review-set,
  title    = {Semicolon Review Set},
  date     = {2026-06-05},
  entryset = {audit-paper; archived-site; missing-entry}
}

@book{semicolon-manual,
  author        = {Curator, Eli},
  title         = {Semicolon Relation Manual},
  date          = {2024},
  ids           = {legacy-manual; migrated-manual},
  xdata         = {shared-publisher; missing-xdata},
  related       = {semicolon-review-set; audit-paper; missing-related},
  relatedtype   = {companion},
  relatedstring = {Semicolon companions},
  xref          = {archived-site; missing-xref}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $set = $items[0];
        $manual = $items[1];

        $t->same(2, count($items));
        $t->same(['audit-paper', 'archived-site', 'missing-entry'], $set['entrySet'] ?? null);
        $t->same(['missing-entry'], $set['missingEntrySetKeys'] ?? null);
        $t->same('audit-paper', $set['entrySetItems'][0]['id'] ?? null);
        $t->same('Packet Audit Trails', $set['entrySetItems'][0]['title'] ?? null);
        $t->same(true, $set['entrySetItems'][0]['dataOnly'] ?? null);
        $t->same('archived-site', $set['entrySetItems'][1]['id'] ?? null);
        $t->same('Archive Site', $set['entrySetItems'][1]['title'] ?? null);
        $t->same('audit-paper; archived-site; missing-entry', $set['rawBibtex']['fields']['entryset'] ?? null);

        $t->same(['legacy-manual', 'migrated-manual'], $manual['citation-aliases'] ?? null);
        $t->same(['shared-publisher', 'missing-xdata'], $manual['xdataKeys'] ?? null);
        $t->same(['missing-xdata'], $manual['missingXdataKeys'] ?? null);
        $t->same('shared-publisher', $manual['xdataItems'][0]['id'] ?? null);
        $t->same('Review Press', $manual['publisher'] ?? null);
        $t->same('Portland', $manual['publisher-place'] ?? null);
        $t->same(['source packet', 'semicolon list'], $manual['keyword'] ?? null);
        $t->same(['semicolon-review-set', 'audit-paper', 'missing-related'], $manual['relatedKeys'] ?? null);
        $t->same(['missing-related'], $manual['missingRelatedKeys'] ?? null);
        $t->same('semicolon-review-set', $manual['relatedItems'][0]['id'] ?? null);
        $t->same('audit-paper', $manual['relatedItems'][1]['id'] ?? null);
        $t->same(['archived-site', 'missing-xref'], $manual['xrefKeys'] ?? null);
        $t->same(['missing-xref'], $manual['missingXrefKeys'] ?? null);
        $t->same('archived-site', $manual['xrefItems'][0]['id'] ?? null);
        $t->same('legacy-manual; migrated-manual', $manual['rawBibtex']['fields']['ids'] ?? null);
        $t->same('shared-publisher; missing-xdata', $manual['rawBibtex']['fields']['xdata'] ?? null);
        $t->same('semicolon-review-set; audit-paper; missing-related', $manual['rawBibtex']['fields']['related'] ?? null);
        $t->same('archived-site; missing-xref', $manual['rawBibtex']['fields']['xref'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $normalizedSet = $processor->item('semicolon-review-set');
        $normalizedManual = $processor->item('semicolon-manual');
        $aliasManual = $processor->item('legacy-manual');

        $t->same('Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-entry', $normalizedSet['entrySetSummary'] ?? null);
        $t->same('shared-publisher; missing: missing-xdata', $normalizedManual['xdataSummary'] ?? null);
        $t->same('Review Press', $normalizedManual['publisher'] ?? null);
        $t->same(['source packet', 'semicolon list'], $normalizedManual['keywords'] ?? null);
        $t->same('semicolon-manual', $aliasManual['id'] ?? null);
        $t->same('legacy-manual', $aliasManual['citationAlias'] ?? null);
        $t->same('(Curator 2024; Semicolon Review Set 2026)', $processor->renderCitationCluster([
            $citation('legacy-manual', '[@legacy-manual]'),
            $citation('semicolon-review-set', '[@semicolon-review-set]'),
        ]));
        $t->same(
            'Curator, Eli. Semicolon Relation Manual. Review Press, 2024. Citation aliases: legacy-manual; migrated-manual. Keywords: source packet; semicolon list. Xdata packets: shared-publisher; missing: missing-xdata. Semicolon companions (companion): Semicolon Review Set (2026-06-05); Packet Audit Trails (2026); missing: missing-related. Xref: Archive Site (2026-05-31); missing: missing-xref.',
            $processor->renderBibliographyEntry('semicolon-manual')
        );
        $t->same(
            'Semicolon Review Set. 2026. Entry set: Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-entry.',
            $processor->renderBibliographyEntry('semicolon-review-set')
        );

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Semicolon relation ']),
                new AstNode('citation_group', ['text' => '[@legacy-manual; @semicolon-review-set]'], [
                    $citation('legacy-manual', '[@legacy-manual]'),
                    $citation('semicolon-review-set', '[@semicolon-review-set]'),
                ]),
                new AstNode('text', ['text' => '.']),
            ]),
        ]);
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Semicolon relation (Curator 2024; Semicolon Review Set 2026).</p>', $blocks);
        $t->contains('<dt>Curator 2024</dt><dd>Curator, Eli. Semicolon Relation Manual. Review Press, 2024. Citation aliases: legacy-manual; migrated-manual. Keywords: source packet; semicolon list. Xdata packets: shared-publisher; missing: missing-xdata. Semicolon companions (companion): Semicolon Review Set (2026-06-05); Packet Audit Trails (2026); missing: missing-related. Xref: Archive Site (2026-05-31); missing: missing-xref.</dd>', $blocks);
        $t->contains('<dt>Semicolon Review Set 2026</dt><dd>Semicolon Review Set. 2026. Entry set: Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-entry.</dd>', $blocks);
    },
];
