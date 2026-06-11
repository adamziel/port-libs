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
    'carries extended biblatex creator roles in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@incollection{roles,
  author         = {Writer, Will},
  editor         = {Editor, Erin},
  translator     = {Curator, Eli and de la Cruz, Ana Maria},
  bookauthor     = {Source, Sam},
  origauthor     = {Garcia, Gia},
  recipient      = {Desk, Archive},
  reviewedauthor = {Reviewer, Robin},
  director       = {Director, Drew},
  illustrator    = {Ink, Inez},
  serieseditor   = {Series, Selma},
  title          = {Role Handoff Chapter},
  booktitle      = {Role Review Sourcebook},
  year           = {2026}
}
BIB;

        $item = (new BibtexCslProcessor())->cslItems($source)['roles'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('chapter', $item['type']);
        $t->same('Writer', $item['author'][0]['family']);
        $t->same('Editor', $item['editor'][0]['family']);
        $t->same('Curator', $item['translator'][0]['family']);
        $t->same('de la Cruz', $item['translator'][1]['family']);
        $t->same('Ana Maria', $item['translator'][1]['given']);
        $t->same('Source', $item['container-author'][0]['family']);
        $t->same('Garcia', $item['original-author'][0]['family']);
        $t->same('Desk', $item['recipient'][0]['family']);
        $t->same('Reviewer', $item['reviewed-author'][0]['family']);
        $t->same('Director', $item['director'][0]['family']);
        $t->same('Ink', $item['illustrator'][0]['family']);
        $t->same('Series', $item['collection-editor'][0]['family']);
        $t->same('Will Writer. Role Handoff Chapter. Role Review Sourcebook. 2026.', $bibliography);
    },
    'carries biblatex short author editor and holder names in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@patent{credit-roles,
  author      = {Inventor, Ivy},
  shortauthor = {Desk, Archive},
  shorteditor = {Curator, Eli and Summary, Sam},
  holder      = {Foundation, WordPress and Migration, Desk},
  title       = {Credit Role Patent},
  year        = {2026}
}
BIB;

        $item = (new BibtexCslProcessor())->cslItems($source)['credit-roles'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('patent', $item['type']);
        $t->same('Inventor', $item['author'][0]['family']);
        $t->same('Desk', $item['short-author'][0]['family']);
        $t->same('Archive', $item['short-author'][0]['given']);
        $t->same('Curator', $item['short-editor'][0]['family']);
        $t->same('Summary', $item['short-editor'][1]['family']);
        $t->same('Foundation', $item['holder'][0]['family']);
        $t->same('Migration', $item['holder'][1]['family']);
        $t->same('Desk, Archive', $item['rawBibtex']['fields']['shortauthor']);
        $t->same('Ivy Inventor. Credit Role Patent. 2026.', $bibliography);
    },
    'carries biblatex event metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@inproceedings{event-handoff,
  author          = {Speaker, Sam},
  title           = {Conference Packet},
  booktitle       = {Proceedings of Migration Review},
  eventtitle      = {Open Source Migration Summit},
  eventtitleaddon = {package track},
  eventtype       = {workshop},
  eventdate       = {2026-06-10},
  venue           = {Portland, OR},
  eventorganizer  = {Program, Pat and Review, Riley},
  date            = {2026},
  pages           = {11--13}
}
BIB;

        $item = (new BibtexCslProcessor())->cslItems($source)['event-handoff'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('paper-conference', $item['type']);
        $t->same('Open Source Migration Summit', $item['event']);
        $t->same('package track', $item['event-title-addon']);
        $t->same('workshop', $item['event-type']);
        $t->same([2026, 6, 10], $item['event-date']['date-parts'][0]);
        $t->same('Portland, OR', $item['event-place']);
        $t->same('Program', $item['event-organizer'][0]['family']);
        $t->same('Pat', $item['event-organizer'][0]['given']);
        $t->same('Review', $item['event-organizer'][1]['family']);
        $t->same('Riley', $item['event-organizer'][1]['given']);
        $t->same('11-13', $item['page']);
        $t->same('Sam Speaker. Conference Packet. Proceedings of Migration Review. 2026. 11-13.', $bibliography);
    },
    'carries secondary csl contributor names in legacy biblatex handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@collection{secondary-credits,
  author            = {Writer, Willa},
  compiler          = {Compiler, Cal},
  editorialdirector = {Director, Edna},
  redactor          = {Redactor, Rae},
  commentator       = {Commentator, Cam},
  annotator         = {Annotator, Ada},
  founder           = {Founder, Fran},
  continuator       = {Continuator, Chen},
  reviser           = {Reviser, Remy},
  collaborator      = {Collaborator, Cora and Partner, Priya},
  introduction      = {Intro, Ira},
  foreword          = {Foreword, Finn},
  afterword         = {Afterword, Ari},
  title             = {Secondary Credit Packet},
  year              = {2026}
}
BIB;

        $item = (new BibtexCslProcessor())->cslItems($source)['secondary-credits'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('book', $item['type']);
        $t->same('Writer', $item['author'][0]['family']);
        $t->same('Compiler', $item['compiler'][0]['family']);
        $t->same('Director', $item['editorial-director'][0]['family']);
        $t->same('Redactor', $item['redactor'][0]['family']);
        $t->same('Commentator', $item['commentator'][0]['family']);
        $t->same('Annotator', $item['annotator'][0]['family']);
        $t->same('Founder', $item['founder'][0]['family']);
        $t->same('Continuator', $item['continuator'][0]['family']);
        $t->same('Reviser', $item['reviser'][0]['family']);
        $t->same('Collaborator', $item['collaborator'][0]['family']);
        $t->same('Partner', $item['collaborator'][1]['family']);
        $t->same('Intro', $item['introduction'][0]['family']);
        $t->same('Foreword', $item['foreword'][0]['family']);
        $t->same('Afterword', $item['afterword'][0]['family']);
        $t->same('Director, Edna', $item['rawBibtex']['fields']['editorialdirector']);
        $t->same('Willa Writer. Secondary Credit Packet. 2026.', $bibliography);
    },
    'carries biblatex original publication and release state metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{translated-manual,
  author            = {Garcia, Gia},
  title             = {Migration Manual},
  origtitle         = {Manual de Migracion},
  origsubtitle      = {Archivo Appendix},
  origtitleaddon    = {facsimile source},
  origdate          = {2020-05},
  origpublisher     = {Archivo Press},
  origlocation      = {Madrid},
  origlanguage      = {spanish},
  publisher         = {Review Press},
  edition           = {2},
  series            = {Review Sources},
  shortseries       = {RS},
  seriesnumber      = {7},
  version           = {2.1.0},
  pubstate          = {revised},
  howpublished      = {print-on-demand packet},
  date              = {2026}
}
BIB;

        $item = (new BibtexCslProcessor())->cslItems($source)['translated-manual'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('book', $item['type']);
        $t->same('Migration Manual', $item['title']);
        $t->same('Manual de Migracion: Archivo Appendix', $item['original-title']);
        $t->same('facsimile source', $item['original-title-addon']);
        $t->same([2020, 5], $item['original-date']['date-parts'][0]);
        $t->same('Archivo Press', $item['original-publisher']);
        $t->same('Madrid', $item['original-publisher-place']);
        $t->same('spanish', $item['original-language']);
        $t->same('2', $item['edition']);
        $t->same('Review Sources', $item['collection-title']);
        $t->same('RS', $item['collection-title-short']);
        $t->same('7', $item['collection-number']);
        $t->same('2.1.0', $item['version']);
        $t->same('revised', $item['status']);
        $t->same('print-on-demand packet', $item['medium']);
        $t->same('Gia Garcia. Migration Manual. Review Press. 2026.', $bibliography);
    },
    'carries biblatex rights metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@dataset{rights-dataset,
  author = {Ng, Nia},
  title  = {Rights Review Dataset},
  date   = {2026},
  rights = {CC BY-SA 4.0 review required},
  doi    = {10.5555/rights-data}
}

@online{copyright-snapshot,
  author    = {{Archive Desk}},
  title     = {Copyright Snapshot},
  date      = {2025},
  copyright = {Copyright 2025 Source Archive},
  url       = {https://example.test/copyright-snapshot}
}

@misc{license-note,
  author  = {Roe, Pat},
  title   = {License Note Packet},
  date    = {2024},
  license = {Internal migration review only}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $rightsBibliography = $processor->renderBibliographyText($items['rights-dataset']);

        $t->same('CC BY-SA 4.0 review required', $items['rights-dataset']['rights']);
        $t->same('Copyright 2025 Source Archive', $items['copyright-snapshot']['rights']);
        $t->same('Internal migration review only', $items['license-note']['rights']);
        $t->same('Copyright 2025 Source Archive', $items['copyright-snapshot']['rawBibtex']['fields']['copyright']);
        $t->same('Internal migration review only', $items['license-note']['rawBibtex']['fields']['license']);
        $t->same(
            'Nia Ng. Rights Review Dataset. 2026. Rights: CC BY-SA 4.0 review required. doi:10.5555/rights-data.',
            $rightsBibliography
        );

        $document = (new MarkdownReader())->read('Rights source @rights-dataset and copyright snapshot [@copyright-snapshot] keep compact notices with license @license-note.');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $markdown = (new MarkdownWriter())->write($bibliographyDocument);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['rights-dataset', 'copyright-snapshot', 'license-note'], $handoff['citedKeys']);
        $t->same([], $handoff['missingKeys']);
        $t->same('CC BY-SA 4.0 review required', $handoff['items'][0]['rights']);
        $t->same('Copyright 2025 Source Archive', $handoff['items'][1]['rights']);
        $t->same('Internal migration review only', $handoff['items'][2]['rights']);
        $t->contains('Rights: CC BY-SA 4.0 review required', $markdown);
        $t->contains('Rights: Copyright 2025 Source Archive', $blocks);
        $t->contains('Rights: Internal migration review only', $blocks);
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
