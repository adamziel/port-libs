<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'parses bibtex entries into csl item metadata' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-bibtex-csl-review.bib');
        $items = (new BibtexCslProcessor())->cslItems($fixture);

        $t->same(['lovelace1843', 'fielding2000'], array_keys($items));
        $t->same('article-journal', $items['lovelace1843']['type']);
        $t->same('Notes on the Analytical Engine', $items['lovelace1843']['title']);
        $t->same('Journal of WordPress Migration Review', $items['lovelace1843']['container-title']);
        $t->same([1843, 9], $items['lovelace1843']['issued']['date-parts'][0]);
        $t->same('691-731', $items['lovelace1843']['page']);
        $t->same('10.1000/analytical', $items['lovelace1843']['DOI']);
        $t->same('Lovelace', $items['lovelace1843']['author'][0]['family']);
        $t->same('Ada', $items['lovelace1843']['author'][0]['given']);
        $t->same('book', $items['fielding2000']['type']);
        $t->same([2000], $items['fielding2000']['issued']['date-parts'][0]);
        $t->same('Irvine', $items['fielding2000']['publisher-place']);
    },
    'supports quoted values comments and month macros for biblatex handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@comment{ignored by parser}
@book{manual,
  author = "Knuth, Donald Ervin and others",
  title = "{The} TeXbook",
  publisher = {Addison\&Wesley},
  year = 1984,
  month = jan
}
BIB;

        $items = (new BibtexCslProcessor())->cslItems($source);

        $t->same(['manual'], array_keys($items));
        $t->same('The TeXbook', $items['manual']['title']);
        $t->same('Addison&Wesley', $items['manual']['publisher']);
        $t->same([1984, 1], $items['manual']['issued']['date-parts'][0]);
        $t->same('Knuth', $items['manual']['author'][0]['family']);
        $t->same('Donald Ervin', $items['manual']['author'][0]['given']);
        $t->same('et al.', $items['manual']['author'][1]['literal']);
    },
    'carries biblatex subtitle eprint identifiers and access metadata into csl items' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{preprint,
  author         = {Ng, Nia},
  title          = {Obscure Archive Packet},
  subtitle       = {Source Review Appendix},
  titleaddon     = {migration note},
  date           = {2026-06-09},
  url            = {https://example.test/preprint},
  urldate        = {2026-06-10},
  eprinttype     = {arXiv},
  eprintclass    = {cs.DL},
  eprint         = {2606.00001},
  isbn           = {978-1-4028-9462-6},
  issn           = {2049-3630},
  langid         = {en-US},
  keywords       = {pandoc, wordpress; archive},
  abstract       = {Bounded CSL handoff for reviewer archives.},
  addendum       = {Import note attached}
}
BIB;

        $items = (new BibtexCslProcessor())->cslItems($source);
        $item = $items['preprint'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('webpage', $item['type']);
        $t->same('Obscure Archive Packet: Source Review Appendix', $item['title']);
        $t->same('migration note', $item['title-addon']);
        $t->same([2026, 6, 9], $item['issued']['date-parts'][0]);
        $t->same([2026, 6, 10], $item['accessed']['date-parts'][0]);
        $t->same('arXiv', $item['archive']);
        $t->same('cs.DL', $item['archive-place']);
        $t->same('2606.00001', $item['archive_location']);
        $t->same('arXiv:cs.DL:2606.00001', $item['archive-summary']);
        $t->same('978-1-4028-9462-6', $item['ISBN']);
        $t->same('2049-3630', $item['ISSN']);
        $t->same('en-US', $item['language']);
        $t->same(['pandoc', 'wordpress', 'archive'], $item['keyword']);
        $t->same('Bounded CSL handoff for reviewer archives.', $item['abstract']);
        $t->same('Import note attached', $item['note']);
        $t->same('Nia Ng. Obscure Archive Packet: Source Review Appendix. 2026. https://example.test/preprint.', $bibliography);
    },
    'maps obscure biblatex entry aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@software{tool,
  author = {Ng, Nia},
  title  = {Converter Tool},
  year   = {2026},
  url    = {https://example.test/tool}
}
@dataset{set,
  author = {Roe, Pat},
  title  = {Fixture Dataset},
  year   = {2025},
  doi    = {10.5555/dataset}
}
@inreference{term,
  author    = {Curator, Eli},
  title     = {Glossary Term},
  booktitle = {Migration Reference},
  year      = {2024},
  pages     = {7--8}
}
@letter{mail,
  author = {Smith, Ada},
  title  = {Review Letter},
  year   = {2023},
  note   = {Mailbox import}
}
@jurisdiction{case-note,
  title = {Migration Tribunal Note},
  year  = {2022}
}
@unpublished{draft,
  title = {Detached Draft},
  year  = {2021}
}
BIB;

        $items = (new BibtexCslProcessor())->cslItems($source);
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($items['tool']);

        $t->same('software', $items['tool']['type']);
        $t->same('dataset', $items['set']['type']);
        $t->same('entry-encyclopedia', $items['term']['type']);
        $t->same('personal_communication', $items['mail']['type']);
        $t->same('legal_case', $items['case-note']['type']);
        $t->same('manuscript', $items['draft']['type']);
        $t->same('Migration Reference', $items['term']['container-title']);
        $t->same('7-8', $items['term']['page']);
        $t->same('Mailbox import', $items['mail']['note']);
        $t->same('Nia Ng. Converter Tool. 2026. https://example.test/tool.', $bibliography);
    },
    'collects cited keys in document order with missing bibliography diagnostics' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('Review @fielding2000 before @missing and [@lovelace1843]. Repeat @fielding2000.');
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-bibtex-csl-review.bib');
        $handoff = (new BibtexCslProcessor())->citationHandoff($document, $fixture);

        $t->same(['fielding2000', 'missing', 'lovelace1843'], $handoff['citedKeys']);
        $t->same(['missing'], $handoff['missingKeys']);
        $t->same(['fielding2000', 'lovelace1843'], array_map(static fn (array $item): string => (string) $item['id'], $handoff['items']));
        $t->same('definition_list', $handoff['bibliography']->type);
        $t->same(['missing'], $handoff['bibliography']->attr('missingCitationKeys'));
        $t->same(3, count($handoff['bibliography']->children));
        $t->true((bool) $handoff['bibliography']->children[2]->attr('missing'), 'Missing citation should be represented as a reviewable bibliography item');
    },
    'renders bibliography nodes through markdown and wordpress writers' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('Reviewer note cites @lovelace1843 and @fielding2000.');
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-bibtex-csl-review.bib');
        $handoff = (new BibtexCslProcessor())->citationHandoff($document, $fixture);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $markdown = (new MarkdownWriter())->write($bibliographyDocument);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->contains('lovelace1843', $markdown);
        $t->contains('Ada Lovelace and Luigi Federico Menabrea. Notes on the Analytical Engine.', $markdown);
        $t->contains('fielding2000', $markdown);
        $t->contains('<dl>', $blocks);
        $t->contains('<dt>lovelace1843</dt>', $blocks);
        $t->contains('Journal of WordPress Migration Review 3(29). 1843. 691-731.', $blocks);
        $t->contains('University of California Irvine. 2000.', $blocks);
    },
    'accepts explicit citation nodes with multiple ids for csl cluster handoff' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Cluster: ']),
                new AstNode('citation', [
                    'ids' => ['lovelace1843', 'fielding2000', 'lovelace1843'],
                    'text' => '[@lovelace1843; @fielding2000]',
                ]),
            ]),
        ]);
        $keys = (new BibtexCslProcessor())->citedKeys($document);

        $t->same(['lovelace1843', 'fielding2000'], $keys);
    },
];
