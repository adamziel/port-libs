<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
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
    'labels unadorned known biblatex related types for csl handoff' => static function (TestRunner $t) use ($citation): void {
        $bibtex = <<<'BIB'
@book{source-packet,
  options = {dataonly},
  title   = {Source Packet},
  date    = {2025-04-01}
}

@book{translation-source,
  options = {dataonly},
  title   = {Translation Source},
  date    = {2024}
}

@book{update-packet,
  options = {dataonly},
  title   = {Update Packet},
  date    = {2023}
}

@book{review-manual,
  author      = {Mapper, Mia},
  title       = {Review Manual},
  date        = {2026},
  related     = {source-packet, missing-review},
  relatedtype = {reviewof}
}

@book{translation-manual,
  author       = {Translator, Theo},
  title        = {Translation Manual},
  date         = {2026},
  related      = {translation-source},
  related-type = {translation-of}
}

@book{update-manual,
  author      = {Curator, Eli},
  title       = {Update Manual},
  date        = {2026},
  related     = {update-packet},
  relatedtype = {updated-by}
}
BIB;

        $items = CitationCslProcessor::bibtexItems($bibtex);
        $t->same(3, count($items));
        $t->same(['review-manual', 'translation-manual', 'update-manual'], array_column($items, 'id'));
        $t->same('reviewof', $items[0]['relatedType'] ?? null);
        $t->same(null, $items[0]['relatedString'] ?? null);
        $t->same('Source Packet', $items[0]['relatedItems'][0]['title'] ?? null);
        $t->same(['missing-review'], $items[0]['missingRelatedKeys'] ?? null);
        $t->same('translation-of', $items[1]['relatedType'] ?? null);
        $t->same('updated-by', $items[2]['relatedType'] ?? null);

        $processor = CitationCslProcessor::fromBibtex($bibtex);
        $review = $processor->item('review-manual');

        $t->same('reviewof', $review['relatedType'] ?? null);
        $t->same(
            'Mapper, Mia. Review Manual. 2026. Review of: Source Packet (2025-04-01); missing: missing-review.',
            $processor->renderBibliographyEntry('review-manual')
        );
        $t->same(
            'Translator, Theo. Translation Manual. 2026. Translation of: Translation Source (2024).',
            $processor->renderBibliographyEntry('translation-manual')
        );
        $t->same(
            'Curator, Eli. Update Manual. 2026. Updated by: Update Packet (2023).',
            $processor->renderBibliographyEntry('update-manual')
        );

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="related-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="related-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same(
            '[Review Manual | Review of: Source Packet (2025-04-01); missing: missing-review; Translation Manual | Translation of: Translation Source (2024); Update Manual | Updated by: Update Packet (2023)]',
            $styled->renderCitationCluster([
                $citation('review-manual', '[@review-manual]'),
                $citation('translation-manual', '[@translation-manual]'),
                $citation('update-manual', '[@update-manual]'),
            ])
        );
        $t->same('Review Manual :: Review of: Source Packet (2025-04-01); missing: missing-review', $styled->renderBibliographyEntry('review-manual'));

        $direct = CitationCslProcessor::fromItems([[
            'id' => 'direct-reprint',
            'title' => 'Direct Reprint Manual',
            'related-type' => 'reprint-of',
            'relatedItems' => [[
                'title' => 'Earlier Manual',
                'issued' => ['date-parts' => [[2001]]],
            ]],
        ]]);
        $t->same('Direct Reprint Manual. Reprint of: Earlier Manual (2001).', $direct->renderBibliographyEntry('direct-reprint'));

        $document = (new MarkdownReader())->read('Related labels [@review-manual; @translation-manual; @update-manual] stay visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Related labels [Review Manual | Review of: Source Packet (2025-04-01); missing: missing-review; Translation Manual | Translation of: Translation Source (2024); Update Manual | Updated by: Update Packet (2023)] stay visible.</p>', $blocks);
        $t->contains('<dt>Mapper 2026</dt><dd>Review Manual :: Review of: Source Packet (2025-04-01); missing: missing-review</dd>', $blocks);
        $t->contains('<dt>Translator 2026</dt><dd>Translation Manual :: Translation of: Translation Source (2024)</dd>', $blocks);
        $t->contains('<dt>Curator 2026</dt><dd>Update Manual :: Updated by: Update Packet (2023)</dd>', $blocks);
    },
];
