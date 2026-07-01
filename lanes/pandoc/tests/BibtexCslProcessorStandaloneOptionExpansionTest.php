<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'carries expanded standalone biblatex entry option fields in csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{expanded-options,
  author        = {Smith, Ada},
  title         = {Legacy Expanded Options},
  date          = {2026},
  publisher     = {Review Press},
  options       = {maxbibnames=9, usevenue=false, uniquetitle=false},
  maxalphanames = {5},
  maxbibnames   = {4},
  maxcitenames  = {3},
  minalphanames = {2},
  minbibnames   = {1},
  mincitenames  = {1},
  mergedate     = {compact},
  singletitle   = {false},
  usetitle      = {true},
  usevenue      = {true},
  uniquetitle   = {init}
}

@online{hyphen-expanded-options,
  author         = {Desk, Review},
  title          = {Hyphen Option Snapshot},
  date           = {2025},
  max-bib-names  = {6},
  min-cite-names = {2},
  merge-date     = {maximum},
  single-title   = {true},
  use-title      = {false},
  use-venue      = {false},
  unique-title   = {false},
  url            = {https://example.test/hyphen-options}
}
BIB;

        $manualOptions = [
            'maxalphanames=5',
            'maxbibnames=4',
            'maxcitenames=3',
            'mergedate=compact',
            'minalphanames=2',
            'minbibnames=1',
            'mincitenames=1',
            'singletitle=false',
            'usetitle=true',
            'usevenue=true',
            'uniquetitle=init',
        ];
        $hyphenOptions = [
            'maxbibnames=6',
            'mergedate=maximum',
            'mincitenames=2',
            'singletitle=true',
            'usetitle=false',
            'usevenue=false',
            'uniquetitle=false',
        ];

        $directItems = CitationCslProcessor::bibtexItems($source);
        $t->same(2, count($directItems));
        $t->same($manualOptions, $directItems[0]['biblatex-options'] ?? null);
        $t->same('4', $directItems[0]['rawBibtex']['fields']['maxbibnames'] ?? null);
        $t->same($hyphenOptions, $directItems[1]['biblatex-options'] ?? null);
        $t->same('6', $directItems[1]['rawBibtex']['fields']['max-bib-names'] ?? null);

        $directProcessor = CitationCslProcessor::fromBibtex($source);
        $t->same($manualOptions, $directProcessor->item('expanded-options')['biblatexOptions'] ?? null);
        $t->same($hyphenOptions, $directProcessor->item('hyphen-expanded-options')['biblatexOptions'] ?? null);

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $manual = $items['expanded-options'];
        $hyphen = $items['hyphen-expanded-options'];

        $t->same($manualOptions, $manual['biblatex-options']);
        $t->same('maxbibnames=9, usevenue=false, uniquetitle=false', $manual['rawBibtex']['fields']['options']);
        $t->same('4', $manual['rawBibtex']['fields']['maxbibnames']);
        $t->same('true', $manual['rawBibtex']['fields']['usevenue']);
        $t->same($hyphenOptions, $hyphen['biblatex-options']);
        $t->same('6', $hyphen['rawBibtex']['fields']['max-bib-names']);
        $t->same('false', $hyphen['rawBibtex']['fields']['unique-title']);

        $citationProcessor = CitationCslProcessor::fromItems(array_values($items));
        $normalizedManual = $citationProcessor->item('expanded-options');
        $normalizedHyphen = $citationProcessor->item('hyphen-expanded-options');
        $t->same($manualOptions, $normalizedManual['biblatexOptions'] ?? null);
        $t->same(implode('; ', $manualOptions), $normalizedManual['biblatexOptionSummary'] ?? null);
        $t->same($hyphenOptions, $normalizedHyphen['biblatexOptions'] ?? null);
        $t->contains('BibLaTeX options: ' . implode('; ', $manualOptions), $processor->renderBibliographyText($manual));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Expanded Option Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-expanded-option-review</id>
    <updated>2026-07-01T18:10:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="biblatex-option-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="biblatex-options-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Expanded Option Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Smith | ' . implode('; ', $manualOptions) . '; Desk | ' . implode('; ', $hyphenOptions) . ']', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'expanded-options', 'text' => '[@expanded-options]']),
            new AstNode('citation', ['id' => 'hyphen-expanded-options', 'text' => '[@hyphen-expanded-options]']),
        ]));
        $t->same('Legacy Expanded Options :: ' . implode('; ', $manualOptions), $styled->renderBibliographyEntry('expanded-options'));
        $t->same('Hyphen Option Snapshot :: ' . implode('; ', $hyphenOptions), $styled->renderBibliographyEntry('hyphen-expanded-options'));

        $document = (new MarkdownReader())->read('Expanded options [@expanded-options; @hyphen-expanded-options] stay visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['expanded-options', 'hyphen-expanded-options'], $handoff['citedKeys']);
        $t->same($manualOptions, $handoff['bibliography']->children[0]->attr('cslItem')['biblatex-options'] ?? null);
        $t->same($hyphenOptions, $handoff['bibliography']->children[1]->attr('cslItem')['biblatex-options'] ?? null);
        $t->contains('<p>Expanded options [Smith | ' . implode('; ', $manualOptions) . '; Desk | ' . implode('; ', $hyphenOptions) . '] stay visible.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Legacy Expanded Options :: ' . implode('; ', $manualOptions) . '</dd>', $blocks);
        $t->contains('<dt>Desk 2025</dt><dd>Hyphen Option Snapshot :: ' . implode('; ', $hyphenOptions) . '</dd>', $blocks);
    },
];
