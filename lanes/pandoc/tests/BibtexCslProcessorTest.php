<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibtexCslProcessor;
use PortLibs\Pandoc\CitationCslProcessor;
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
  categories     = {review queue; source package},
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
        $t->same(['review queue', 'source package'], $item['categories']);
        $t->same('Bounded CSL handoff for reviewer archives.', $item['abstract']);
        $t->same('Import note attached', $item['note']);
        $t->same('Nia Ng. Obscure Archive Packet: Source Review Appendix. 2026. https://example.test/preprint.', $bibliography);
    },
    'carries biblatex primary class archive aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{primary-class-preprint,
  author        = {Ng, Nia},
  title         = {Primary Class Preprint},
  archivePrefix = {arXiv},
  primaryClass  = {cs.CL},
  eprint        = {2606.10001},
  date          = {2026},
  url           = {https://example.test/primary-class-preprint}
}

@online{hyphen-primary-class-preprint,
  author         = {Roe, Pat},
  title          = {Hyphen Primary Class Preprint},
  archiveprefix  = {arXiv},
  primary-class  = {math.AG},
  eprint         = {2606.10002},
  date           = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $primary = $items['primary-class-preprint'];
        $hyphen = $items['hyphen-primary-class-preprint'];

        $t->same('arXiv', $primary['archive']);
        $t->same('cs.CL', $primary['archive-place']);
        $t->same('2606.10001', $primary['archive_location']);
        $t->same('arXiv:cs.CL:2606.10001', $primary['archive-summary']);
        $t->same('cs.CL', $primary['rawBibtex']['fields']['primaryclass']);
        $t->same('math.AG', $hyphen['archive-place']);
        $t->same('math.AG', $hyphen['rawBibtex']['fields']['primary-class']);
        $t->same('arXiv:math.AG:2606.10002', $hyphen['archive-summary']);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Primary Class Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-primary-class-review</id>
    <updated>2026-06-30T10:35:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="archive"/>
        <text variable="archive-place"/>
        <text variable="archive_location"/>
        <text variable="archive-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="archive-place"/>
      <text variable="archive-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledPrimary = $styled->item('primary-class-preprint');
        $styledHyphen = $styled->item('hyphen-primary-class-preprint');
        $t->same('Bounded Legacy BibLaTeX Primary Class Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('cs.CL', $styledPrimary['archivePlace'] ?? null);
        $t->same('math.AG', $styledHyphen['archivePlace'] ?? null);
        $t->same('[Primary Class Preprint | arXiv | cs.CL | 2606.10001 | arXiv:cs.CL:2606.10001; Hyphen Primary Class Preprint | arXiv | math.AG | 2606.10002 | arXiv:math.AG:2606.10002]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'primary-class-preprint', 'text' => '[@primary-class-preprint]']),
            new AstNode('citation', ['id' => 'hyphen-primary-class-preprint', 'text' => '[@hyphen-primary-class-preprint]']),
        ]));
        $t->same('Primary Class Preprint :: cs.CL :: arXiv:cs.CL:2606.10001', $styled->renderBibliographyEntry('primary-class-preprint'));
        $t->same('Hyphen Primary Class Preprint :: math.AG :: arXiv:math.AG:2606.10002', $styled->renderBibliographyEntry('hyphen-primary-class-preprint'));

        $document = (new MarkdownReader())->read('Primary class sources [@hyphen-primary-class-preprint; @primary-class-preprint] keep arXiv classes visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['hyphen-primary-class-preprint', 'primary-class-preprint'], $handoff['citedKeys']);
        $t->same('math.AG', $handoff['items'][0]['archive-place'] ?? null);
        $t->same('cs.CL', $handoff['bibliography']->children[1]->attr('cslItem')['archive-place'] ?? null);
        $t->contains('<p>Primary class sources [Hyphen Primary Class Preprint | arXiv | math.AG | 2606.10002 | arXiv:math.AG:2606.10002; Primary Class Preprint | arXiv | cs.CL | 2606.10001 | arXiv:cs.CL:2606.10001] keep arXiv classes visible.</p>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Hyphen Primary Class Preprint :: math.AG :: arXiv:math.AG:2606.10002</dd>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Primary Class Preprint :: cs.CL :: arXiv:cs.CL:2606.10001</dd>', $blocks);
    },
    'carries compact biblatex csl aliases in legacy handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{alias-journal,
  author              = {Doe, Jane},
  title               = {Alias Journal Packet},
  title-short         = {Alias packet},
  title-addon         = {review appendix},
  journal-title       = {Journal of Alias Imports},
  journal-subtitle    = {Source Desk},
  journal-title-addon = {online dossier},
  date                = {2026},
  pages               = {A12--A18},
  eISSN               = {EISSN 2468 1357},
  url                 = {https://example.test/alias-journal},
  url-description     = {archived review copy}
}

@incollection{alias-chapter,
  author                = {Ng, Nia},
  title                 = {Alias Chapter Packet},
  book-title            = {Migration Alias Handbook},
  book-subtitle         = {Reviewer Edition},
  book-title-addon      = {internal packet},
  collection-title-text = {Alias Review Series},
  date                  = {2025},
  pages                 = {77--81},
  isbn13                = {ISBN-13: 978-1-4028-9462-6}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $journal = $items['alias-journal'];
        $chapter = $items['alias-chapter'];

        $t->same('article-journal', $journal['type']);
        $t->same('Alias packet', $journal['short-title']);
        $t->same('review appendix', $journal['title-addon']);
        $t->same('Journal of Alias Imports: Source Desk', $journal['container-title']);
        $t->same('online dossier', $journal['container-title-addon']);
        $t->same('A12-A18', $journal['page']);
        $t->same('A12', $journal['page-first']);
        $t->same('2468-1357', $journal['ISSN']);
        $t->same('archived review copy', $journal['URL-label']);
        $t->same('Journal of Alias Imports', $journal['rawBibtex']['fields']['journal-title']);
        $t->same('EISSN 2468 1357', $journal['rawBibtex']['fields']['eissn']);
        $t->same('chapter', $chapter['type']);
        $t->same('Migration Alias Handbook: Reviewer Edition', $chapter['container-title']);
        $t->same('internal packet', $chapter['container-title-addon']);
        $t->same('Alias Review Series', $chapter['collection-title']);
        $t->same('77', $chapter['page-first']);
        $t->same('978-1-4028-9462-6', $chapter['ISBN']);
        $t->same('Migration Alias Handbook', $chapter['rawBibtex']['fields']['book-title']);
        $t->same('ISBN-13: 978-1-4028-9462-6', $chapter['rawBibtex']['fields']['isbn13']);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-alias-review</id>
    <updated>2026-06-29T00:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="short-title"/>
        <text variable="title-addon"/>
        <text variable="container-title"/>
        <text variable="container-title-addon"/>
        <text variable="collection-title"/>
        <text variable="page-first"/>
        <text variable="ISSN"/>
        <text variable="ISBN"/>
        <text variable="url-label"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="container-title"/>
      <text variable="container-title-addon"/>
      <text variable="collection-title"/>
      <text variable="page-first"/>
      <text variable="ISSN"/>
      <text variable="ISBN"/>
      <text variable="url-label"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledJournal = $styled->item('alias-journal');
        $styledChapter = $styled->item('alias-chapter');
        $t->same('Bounded Legacy BibLaTeX Alias Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('A12', $styledJournal['pageFirst'] ?? null);
        $t->same('2468-1357', $styledJournal['issn'] ?? null);
        $t->same('archived review copy', $styledJournal['urlLabel'] ?? null);
        $t->same('77', $styledChapter['pageFirst'] ?? null);
        $t->same('978-1-4028-9462-6', $styledChapter['isbn'] ?? null);
        $t->same('[Doe | Alias packet | review appendix | Journal of Alias Imports: Source Desk | online dossier | A12 | 2468-1357 | archived review copy; Ng | Migration Alias Handbook: Reviewer Edition | internal packet | Alias Review Series | 77 | 978-1-4028-9462-6]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'alias-journal', 'text' => '[@alias-journal]']),
            new AstNode('citation', ['id' => 'alias-chapter', 'text' => '[@alias-chapter]']),
        ]));
        $t->same('Alias Journal Packet :: Journal of Alias Imports: Source Desk :: online dossier :: A12 :: 2468-1357 :: archived review copy', $styled->renderBibliographyEntry('alias-journal'));
        $t->same('Alias Chapter Packet :: Migration Alias Handbook: Reviewer Edition :: internal packet :: Alias Review Series :: 77 :: 978-1-4028-9462-6', $styled->renderBibliographyEntry('alias-chapter'));

        $document = (new MarkdownReader())->read('Alias journal @alias-journal and chapter [@alias-chapter] keep compact CSL metadata.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['alias-journal', 'alias-chapter'], $handoff['citedKeys']);
        $t->same('A12', $handoff['bibliography']->children[0]->attr('cslItem')['page-first'] ?? null);
        $t->same('978-1-4028-9462-6', $handoff['bibliography']->children[1]->attr('cslItem')['ISBN'] ?? null);
        $t->contains('<p>Alias journal Doe (2026) and chapter [Ng | Migration Alias Handbook: Reviewer Edition | internal packet | Alias Review Series | 77 | 978-1-4028-9462-6] keep compact CSL metadata.</p>', $blocks);
        $t->contains('<dt>Doe 2026</dt><dd>Alias Journal Packet :: Journal of Alias Imports: Source Desk :: online dossier :: A12 :: 2468-1357 :: archived review copy</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Alias Chapter Packet :: Migration Alias Handbook: Reviewer Edition :: internal packet :: Alias Review Series :: 77 :: 978-1-4028-9462-6</dd>', $blocks);
    },
    'carries legacy biblatex registry identifiers in bibliography handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-pubmed,
  author       = {Ng, Nia},
  title        = {Legacy PubMed Packet},
  journaltitle = {Legacy Import Medicine},
  date         = {2026},
  doi          = {10.5555/legacy-pubmed},
  pubmed       = {34567890},
  pmc-id       = {PMC3456789},
  jstor-id     = {10.2307/legacy}
}

@music{legacy-score,
  author = {Curator, Eli},
  title  = {Legacy Score Packet},
  date   = {2025},
  ismn   = {979-0-060-11561-5},
  iswc   = {T-034.524.680-1}
}

@report{legacy-report,
  author      = {Roe, Pat},
  title       = {Legacy Registry Report},
  institution = {Migration Desk},
  date        = {2024},
  isrn        = {NISTIR 8202},
  hdl-id      = {20.500/legacy},
  lccn-number = {2026123987},
  oclc-number = {99887766}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $pubmed = $items['legacy-pubmed'];
        $score = $items['legacy-score'];
        $report = $items['legacy-report'];

        $t->same('34567890', $pubmed['PMID']);
        $t->same('PMC3456789', $pubmed['PMCID']);
        $t->same('10.2307/legacy', $pubmed['JSTOR']);
        $t->same('979-0-060-11561-5', $score['ISMN']);
        $t->same('T-034.524.680-1', $score['ISWC']);
        $t->same('NISTIR 8202', $report['ISRN']);
        $t->same('20.500/legacy', $report['HDL']);
        $t->same('2026123987', $report['LCCN']);
        $t->same('99887766', $report['OCLC']);
        $t->same('34567890', $pubmed['rawBibtex']['fields']['pubmed']);
        $t->same('PMC3456789', $pubmed['rawBibtex']['fields']['pmc-id']);
        $t->same('20.500/legacy', $report['rawBibtex']['fields']['hdl-id']);
        $t->same(
            'Nia Ng. Legacy PubMed Packet. Legacy Import Medicine. 2026. doi:10.5555/legacy-pubmed. PMID 34567890. PMCID PMC3456789. JSTOR 10.2307/legacy.',
            $processor->renderBibliographyText($pubmed)
        );
        $t->same(
            'Pat Roe. Legacy Registry Report. Migration Desk. 2024. ISRN NISTIR 8202. HDL 20.500/legacy. LCCN 2026123987. OCLC 99887766.',
            $processor->renderBibliographyText($report)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="pubmed-id"/>
        <text variable="pmc-id"/>
        <text variable="jstor-id"/>
        <text variable="ISMN"/>
        <text variable="ISWC"/>
        <text variable="ISRN"/>
        <text variable="handle-id"/>
        <text variable="lccn-number"/>
        <text variable="oclc-number"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="PMID"/>
      <text variable="PMCID"/>
      <text variable="ISRN"/>
      <text variable="handle"/>
      <text variable="OCLC"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[Legacy PubMed Packet | 34567890 | PMC3456789 | 10.2307/legacy; Legacy Score Packet | 979-0-060-11561-5 | T-034.524.680-1; Legacy Registry Report | NISTIR 8202 | 20.500/legacy | 2026123987 | 99887766]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-pubmed', 'text' => '[@legacy-pubmed]']),
            new AstNode('citation', ['id' => 'legacy-score', 'text' => '[@legacy-score]']),
            new AstNode('citation', ['id' => 'legacy-report', 'text' => '[@legacy-report]']),
        ]));
        $t->same('Legacy PubMed Packet :: 34567890 :: PMC3456789', $styled->renderBibliographyEntry('legacy-pubmed'));
        $t->same('Legacy Registry Report :: NISTIR 8202 :: 20.500/legacy :: 99887766', $styled->renderBibliographyEntry('legacy-report'));

        $document = (new MarkdownReader())->read('Legacy identifiers cite @legacy-pubmed and [@legacy-report].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-pubmed', 'legacy-report'], $handoff['citedKeys']);
        $t->same('34567890', $handoff['items'][0]['PMID']);
        $t->same('20.500/legacy', $handoff['items'][1]['HDL']);
        $t->contains('PMID 34567890', $blocks);
        $t->contains('HDL 20.500/legacy', $blocks);
    },
    'normalizes prefixed biblatex identifiers without losing raw fields' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{identifier-normalization,
  author = {Ng, Nia},
  title  = {Identifier Normalization Packet},
  date   = {2026-06-05},
  doi    = {https://doi.org/10.5555/MIGRATION.CAPS},
  isbn   = {ISBN-13: 978 1 4028 9462 6},
  issn   = {ISSN 2049 3630},
  pubmed = {PMID: 34 567 890},
  pmc-id = {pmcid: pmc3456789},
  url    = {<https://example.test/identifier-normalization>}
}
BIB;

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($source)['identifier-normalization'];
        $bibliography = $processor->renderBibliographyText($item);

        $t->same('10.5555/migration.caps', $item['DOI']);
        $t->same('9781402894626', $item['ISBN']);
        $t->same('2049-3630', $item['ISSN']);
        $t->same('34567890', $item['PMID']);
        $t->same('PMC3456789', $item['PMCID']);
        $t->same('https://example.test/identifier-normalization', $item['URL']);
        $t->same('https://doi.org/10.5555/MIGRATION.CAPS', $item['rawBibtex']['fields']['doi']);
        $t->same('ISBN-13: 978 1 4028 9462 6', $item['rawBibtex']['fields']['isbn']);
        $t->same('PMID: 34 567 890', $item['rawBibtex']['fields']['pubmed']);
        $t->same(
            'Nia Ng. Identifier Normalization Packet. 2026. doi:10.5555/migration.caps. PMID 34567890. PMCID PMC3456789. https://example.test/identifier-normalization.',
            $bibliography
        );

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="doi"/>
        <text variable="isbn"/>
        <text variable="issn"/>
        <text variable="pubmed-id"/>
        <text variable="pmc-id"/>
        <text variable="url"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="DOI"/>
      <text variable="ISBN"/>
      <text variable="PMID"/>
      <text variable="PMCID"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('identifier-normalization');
        $t->same('10.5555/migration.caps', $styledItem['doi'] ?? null);
        $t->same('9781402894626', $styledItem['isbn'] ?? null);
        $t->same('34567890', $styledItem['pmid'] ?? null);
        $t->same(
            '[Identifier Normalization Packet | 10.5555/migration.caps | 9781402894626 | 2049-3630 | 34567890 | PMC3456789 | https://example.test/identifier-normalization]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'identifier-normalization', 'text' => '[@identifier-normalization]']),
            ])
        );
        $t->same('Identifier Normalization Packet :: 10.5555/migration.caps :: 9781402894626 :: 34567890 :: PMC3456789', $styled->renderBibliographyEntry('identifier-normalization'));
    },
    'carries legacy biblatex authority identifiers in bibliography handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-authority-person,
  author   = {Smith, Ada},
  title    = {Legacy Authority Identifier Packet},
  date     = {2026},
  orcid    = {0000-0002-1825-0097},
  isni     = {0000000121032683},
  viaf     = {12345678},
  wikidata = {Q42}
}

@report{legacy-authority-organization,
  author      = {{Migration Review Institute}},
  title       = {Legacy Organization Identifier Packet},
  institution = {Migration Desk},
  date        = {2025},
  ror         = {https://ror.org/01abcde23}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $person = $items['legacy-authority-person'];
        $organization = $items['legacy-authority-organization'];

        $t->same('0000-0002-1825-0097', $person['ORCID']);
        $t->same('0000000121032683', $person['ISNI']);
        $t->same('12345678', $person['VIAF']);
        $t->same('Q42', $person['Wikidata']);
        $t->same('https://ror.org/01abcde23', $organization['ROR']);
        $t->same('0000-0002-1825-0097', $person['rawBibtex']['fields']['orcid']);
        $t->same('https://ror.org/01abcde23', $organization['rawBibtex']['fields']['ror']);
        $t->same(
            'Ada Smith. Legacy Authority Identifier Packet. 2026. ORCID 0000-0002-1825-0097. ISNI 0000000121032683. VIAF 12345678. Wikidata Q42.',
            $processor->renderBibliographyText($person)
        );
        $t->same(
            'Migration Review Institute. Legacy Organization Identifier Packet. Migration Desk. 2025. ROR https://ror.org/01abcde23.',
            $processor->renderBibliographyText($organization)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Authority Identifier Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-authority-identifier-review</id>
    <updated>2026-06-27T00:24:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="authority-identifiers"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="ORCID"/>
      <text variable="ISNI"/>
      <text variable="VIAF"/>
      <text variable="ROR"/>
      <text variable="Wikidata"/>
      <text variable="authority-identifier-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Authority Identifier Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Smith | ORCID 0000-0002-1825-0097; ISNI 0000000121032683; VIAF 12345678; Wikidata Q42; Institute | ROR https://ror.org/01abcde23]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-authority-person', 'text' => '[@legacy-authority-person]']),
            new AstNode('citation', ['id' => 'legacy-authority-organization', 'text' => '[@legacy-authority-organization]']),
        ]));
        $t->same('Legacy Authority Identifier Packet :: 0000-0002-1825-0097 :: 0000000121032683 :: 12345678 :: Q42 :: ORCID 0000-0002-1825-0097; ISNI 0000000121032683; VIAF 12345678; Wikidata Q42', $styled->renderBibliographyEntry('legacy-authority-person'));
        $t->same('Legacy Organization Identifier Packet :: https://ror.org/01abcde23 :: ROR https://ror.org/01abcde23', $styled->renderBibliographyEntry('legacy-authority-organization'));

        $document = (new MarkdownReader())->read('Legacy authority identifiers cite @legacy-authority-person and [@legacy-authority-organization].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-authority-person', 'legacy-authority-organization'], $handoff['citedKeys']);
        $t->same('0000-0002-1825-0097', $handoff['items'][0]['ORCID']);
        $t->same('https://ror.org/01abcde23', $handoff['items'][1]['ROR']);
        $t->contains('ORCID 0000-0002-1825-0097', $blocks);
        $t->contains('ROR https://ror.org/01abcde23', $blocks);
    },    'carries biblatex annotations separately from abstracts in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@misc{annotated-source,
  author     = {Roe, Pat},
  title      = {Annotated Legacy Packet},
  date       = {2026},
  abstract   = {Public summary for migration review.},
  annotation = {Internal reviewer annotation.},
  annote     = {Legacy catalog fallback note.},
  url        = {https://example.test/annotated-legacy}
}

@misc{annote-only-source,
  author = {Ng, Nia},
  title  = {Legacy Annote Packet},
  date   = {2025},
  annote = {Legacy annote preserved as annotation.}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $bibliography = $processor->renderBibliographyText($items['annotated-source']);

        $t->same('Public summary for migration review.', $items['annotated-source']['abstract']);
        $t->same('Internal reviewer annotation.', $items['annotated-source']['annotation']);
        $t->same('Legacy annote preserved as annotation.', $items['annote-only-source']['abstract']);
        $t->same('Legacy annote preserved as annotation.', $items['annote-only-source']['annotation']);
        $t->same('Internal reviewer annotation.', $items['annotated-source']['rawBibtex']['fields']['annotation']);
        $t->same('Legacy catalog fallback note.', $items['annotated-source']['rawBibtex']['fields']['annote']);
        $t->same(
            'Pat Roe. Annotated Legacy Packet. 2026. Annotation: Internal reviewer annotation. https://example.test/annotated-legacy.',
            $bibliography
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Annote Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-annote-review</id>
    <updated>2026-06-15T12:44:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="abstract"/>
        <text variable="annote"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="abstract"/>
      <text variable="annotation"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Annote Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same(
            '[Annotated Legacy Packet | Public summary for migration review. | Internal reviewer annotation.; Legacy Annote Packet | Legacy annote preserved as annotation. | Legacy annote preserved as annotation.]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'annotated-source', 'text' => '[@annotated-source]']),
                new AstNode('citation', ['id' => 'annote-only-source', 'text' => '[@annote-only-source]']),
            ])
        );
        $t->same('Annotated Legacy Packet :: Public summary for migration review. :: Internal reviewer annotation.', $styled->renderBibliographyEntry('annotated-source'));

        $document = (new MarkdownReader())->read('Legacy annote source @annotated-source keeps annotation metadata visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['annotated-source'], $handoff['citedKeys']);
        $t->same('Internal reviewer annotation.', $handoff['items'][0]['annotation']);
        $t->contains('Annotation: Internal reviewer annotation', $blocks);
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
    'carries biblatex author role qualifiers in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{compiled-source-manual,
  author       = {Roe, Pat and {{Migration Desk}}},
  authortype   = {compiler},
  entrysubtype = {migration handbook},
  title        = {Compiled Source Manual},
  date         = {2026},
  publisher    = {Review Press}
}

@incollection{container-type-chapter,
  author         = {Ng, Nia},
  bookauthor     = {Smith, Ada and Curator, Eli},
  bookauthortype = {source volume author},
  entry-subtype  = {source chapter},
  title          = {Container Type Chapter},
  booktitle      = {Migration Sourcebook},
  date           = {2025},
  pages          = {44--49}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $compiled = $items['compiled-source-manual'];
        $chapter = $items['container-type-chapter'];

        $t->same('migration handbook', $compiled['entry-subtype']);
        $t->same('migration handbook', $compiled['genre']);
        $t->same('compiler', $compiled['author-type']);
        $t->same('compiler', $compiled['rawBibtex']['fields']['authortype']);
        $t->same('source chapter', $chapter['entry-subtype']);
        $t->same('source volume author', $chapter['container-author-type']);
        $t->same('Smith', $chapter['container-author'][0]['family'] ?? null);
        $t->same('Curator', $chapter['container-author'][1]['family'] ?? null);
        $t->same('source volume author', $chapter['rawBibtex']['fields']['bookauthortype']);
        $t->same(
            'Pat Roe and Migration Desk. Compiled Source Manual. Review Press. 2026. Entry subtype: migration handbook. Author type: compiler.',
            $processor->renderBibliographyText($compiled)
        );
        $t->contains('Container author type: source volume author', $processor->renderBibliographyText($chapter));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Author Role Qualifier Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-author-role-qualifier-review</id>
    <updated>2026-06-30T04:15:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="entry-subtype"/>
        <text variable="authortype"/>
        <text variable="container-author-type"/>
        <text variable="bookauthortype"/>
        <names variable="container-author"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="entry-subtype"/>
      <text variable="author-type"/>
      <text variable="container-author-type"/>
      <names variable="container-author"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledCompiled = $styled->item('compiled-source-manual');
        $styledChapter = $styled->item('container-type-chapter');
        $t->same('Bounded Legacy BibLaTeX Author Role Qualifier Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('migration handbook', $styledCompiled['entrySubtype'] ?? null);
        $t->same('compiler', $styledCompiled['authorType'] ?? null);
        $t->same('source volume author', $styledChapter['containerAuthorType'] ?? null);
        $t->same('Smith', $styledChapter['containerAuthors'][0]['family'] ?? null);
        $t->same('[Roe and Desk | migration handbook | compiler; Ng | source chapter | source volume author | source volume author | Smith and Curator]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'compiled-source-manual', 'text' => '[@compiled-source-manual]']),
            new AstNode('citation', ['id' => 'container-type-chapter', 'text' => '[@container-type-chapter]']),
        ]));
        $t->same('Compiled Source Manual :: migration handbook :: compiler', $styled->renderBibliographyEntry('compiled-source-manual'));
        $t->same('Container Type Chapter :: source chapter :: source volume author :: Smith, Ada; Curator, Eli', $styled->renderBibliographyEntry('container-type-chapter'));

        $document = (new MarkdownReader())->read('Legacy role qualifiers [@compiled-source-manual; @container-type-chapter] stay visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['compiled-source-manual', 'container-type-chapter'], $handoff['citedKeys']);
        $t->same('compiler', $handoff['items'][0]['author-type'] ?? null);
        $t->same('source volume author', $handoff['bibliography']->children[1]->attr('cslItem')['container-author-type'] ?? null);
        $t->contains('<p>Legacy role qualifiers [Roe and Desk | migration handbook | compiler; Ng | source chapter | source volume author | source volume author | Smith and Curator] stay visible.</p>', $blocks);
        $t->contains('Compiled Source Manual :: migration handbook :: compiler', $blocks);
        $t->contains('Container Type Chapter :: source chapter :: source volume author :: Smith, Ada; Curator, Eli', $blocks);
    },
    'carries biblatex legal and patent authority metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@patent{bounded-patent,
  author    = {Inventor, Ivy},
  title     = {Bounded Patent Packet},
  type      = {patentus},
  number    = {US-11111111},
  authority = {USPTO and Review Board},
  location  = {US},
  status    = {granted},
  year      = {2026}
}

@jurisdiction{tribunal-note,
  title        = {Migration Tribunal Note},
  court        = {Source Review Court},
  jurisdiction = {CA},
  number       = {MT-2026-04},
  date         = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $patent = $items['bounded-patent'];
        $case = $items['tribunal-note'];

        $t->same('patent', $patent['type']);
        $t->same('US-11111111', $patent['number']);
        $t->same('patentus', $patent['patent-type']);
        $t->same('U.S. patent', $patent['patent-type-label']);
        $t->same('USPTO; Review Board', $patent['authority']);
        $t->same(['USPTO', 'Review Board'], $patent['authority-list']);
        $t->same('US', $patent['jurisdiction']);
        $t->same('granted', $patent['status']);
        $t->same('legal_case', $case['type']);
        $t->same('MT-2026-04', $case['number']);
        $t->same('Source Review Court', $case['authority']);
        $t->same('CA', $case['jurisdiction']);
        $t->same('patentus', $patent['rawBibtex']['fields']['type']);
        $t->same('Source Review Court', $case['rawBibtex']['fields']['court']);
        $t->same(
            'Ivy Inventor. Bounded Patent Packet. 2026. U.S. patent US-11111111. Authority: USPTO; Review Board. Jurisdiction: US. Status: granted.',
            $processor->renderBibliographyText($patent)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Legal Authority Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-legal-authority-review</id>
    <updated>2026-06-28T14:25:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="patent-type-label"/>
        <text variable="number"/>
        <text variable="authority"/>
        <text variable="jurisdiction"/>
        <text variable="status"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="patent-type"/>
      <text variable="number"/>
      <text variable="authority-list"/>
      <text variable="jurisdiction"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same(
            '[Bounded Patent Packet | U.S. patent | US-11111111 | USPTO; Review Board | US | granted; Migration Tribunal Note | MT-2026-04 | Source Review Court | CA]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'bounded-patent', 'text' => '[@bounded-patent]']),
                new AstNode('citation', ['id' => 'tribunal-note', 'text' => '[@tribunal-note]']),
            ])
        );
        $t->same('Bounded Patent Packet :: patentus :: US-11111111 :: USPTO; Review Board :: US', $styled->renderBibliographyEntry('bounded-patent'));
        $t->same('Migration Tribunal Note :: MT-2026-04 :: Source Review Court :: CA', $styled->renderBibliographyEntry('tribunal-note'));

        $document = (new MarkdownReader())->read('Legal sources cite @bounded-patent and [@tribunal-note].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['bounded-patent', 'tribunal-note'], $handoff['citedKeys']);
        $t->same('USPTO; Review Board', $handoff['items'][0]['authority']);
        $t->same('Source Review Court', $handoff['bibliography']->children[1]->attr('cslItem')['authority'] ?? null);
        $t->contains('U.S. patent US-11111111', $blocks);
        $t->contains('Authority: Source Review Court', $blocks);
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
    'maps biblatex speech entry aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@talk{legacy-talk,
  author          = {Ng, Nia},
  title           = {Legacy Talk Packet},
  eventtitle      = {Migration Review Summit},
  eventtitleaddon = {plenary queue},
  eventtype       = {talk},
  eventdate       = {2026-06-15},
  venue           = {Remote Hall},
  date            = {2026}
}

@lecture{legacy-lecture,
  author     = {Roe, Pat},
  title      = {Legacy Lecture Packet},
  eventtitle = {Archive Review School},
  eventtype  = {lecture},
  eventdate  = {2025-11-03},
  venue      = {Seminar Room},
  date       = {2025}
}

@presentation{legacy-presentation,
  author      = {Diaz, Dana},
  title       = {Legacy Presentation Packet},
  event-title = {Package Handoff Forum},
  event-type  = {presentation},
  event-date  = {2024-04-02},
  event-place = {Review Stage},
  date        = {2024}
}

@unpublished{legacy-evented-draft,
  author     = {Smith, Ada},
  title      = {Evented Draft Packet},
  eventtitle = {Source Review Forum},
  eventdate  = {2023-01-09},
  venue      = {Archive Room},
  year       = {2023}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $talk = $items['legacy-talk'];
        $lecture = $items['legacy-lecture'];
        $presentation = $items['legacy-presentation'];
        $draft = $items['legacy-evented-draft'];

        $t->same('speech', $talk['type']);
        $t->same('speech', $lecture['type']);
        $t->same('speech', $presentation['type']);
        $t->same('speech', $draft['type']);
        $t->same('Migration Review Summit', $talk['event']);
        $t->same('plenary queue', $talk['event-title-addon']);
        $t->same('talk', $talk['event-type']);
        $t->same([2026, 6, 15], $talk['event-date']['date-parts'][0]);
        $t->same('Remote Hall', $talk['event-place']);
        $t->same('Remote Hall', $talk['rawBibtex']['fields']['venue']);
        $t->same('Archive Review School', $lecture['event']);
        $t->same('lecture', $lecture['event-type']);
        $t->same('Package Handoff Forum', $presentation['event']);
        $t->same('presentation', $presentation['event-type']);
        $t->same('Review Stage', $presentation['event-place']);
        $t->same('Evented Draft Packet', $draft['title']);
        $t->same('Source Review Forum', $draft['event']);
        $t->same('Archive Room', $draft['event-place']);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Legacy BibLaTeX Speech Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-speech-alias-review</id>
    <updated>2026-06-15T04:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="event"/>
        <date variable="event-date"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="event-place"/>
      <text variable="event-type"/>
      <date variable="event-date"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Speech Alias Review', $summary['title'] ?? null);
        $t->same('event', $summary['citationRendering'][0]['children'][1]['variable'] ?? null);
        $t->same('[Legacy Talk Packet | Migration Review Summit | 2026; Evented Draft Packet | Source Review Forum | 2023]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-talk', 'text' => '[@legacy-talk]']),
            new AstNode('citation', ['id' => 'legacy-evented-draft', 'text' => '[@legacy-evented-draft]']),
        ]));
        $t->same('Legacy Presentation Packet :: Review Stage :: presentation :: 2024-04-02', $styled->renderBibliographyEntry('legacy-presentation'));

        $document = (new MarkdownReader())->read('Legacy speech aliases [@legacy-talk; @legacy-lecture; @legacy-presentation; @legacy-evented-draft] stay reviewable.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Legacy speech aliases [Legacy Talk Packet | Migration Review Summit | 2026; Legacy Lecture Packet | Archive Review School | 2025; Legacy Presentation Packet | Package Handoff Forum | 2024; Evented Draft Packet | Source Review Forum | 2023] stay reviewable.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Legacy Talk Packet :: Remote Hall :: talk :: 2026-06-15</dd>', $blocks);
        $t->contains('<dt>Smith 2023</dt><dd>Evented Draft Packet :: Archive Room :: 2023-01-09</dd>', $blocks);
    },
    'carries secondary csl contributor names in legacy biblatex handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@collection{secondary-credits,
  author            = {Writer, Willa},
  compiler          = {Compiler, Cal},
  editorialdirector = {Director, Edna},
  editortranslator  = {Translator, Theo},
  redactor          = {Redactor, Rae},
  commentator       = {Commentator, Cam},
  annotator         = {Annotator, Ada},
  founder           = {Founder, Fran},
  continuator       = {Continuator, Chen},
  reviser           = {Reviser, Remy},
  collaborator      = {Collaborator, Cora and Partner, Priya},
  seriescreator     = {Series, Stella},
  seriescreator+an  = {1=series imported from legacy catalog},
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
        $t->same('Translator', $item['editor-translator'][0]['family']);
        $t->same('Redactor', $item['redactor'][0]['family']);
        $t->same('Commentator', $item['commentator'][0]['family']);
        $t->same('Annotator', $item['annotator'][0]['family']);
        $t->same('Founder', $item['founder'][0]['family']);
        $t->same('Continuator', $item['continuator'][0]['family']);
        $t->same('Reviser', $item['reviser'][0]['family']);
        $t->same('Collaborator', $item['collaborator'][0]['family']);
        $t->same('Partner', $item['collaborator'][1]['family']);
        $t->same('Series', $item['series-creator'][0]['family']);
        $t->same([['part' => 'name', 'value' => 'series imported from legacy catalog']], $item['series-creator'][0]['annotations'] ?? null);
        $t->same('Intro', $item['introduction'][0]['family']);
        $t->same('Foreword', $item['foreword'][0]['family']);
        $t->same('Afterword', $item['afterword'][0]['family']);
        $t->same('Translator, Theo', $item['rawBibtex']['fields']['editortranslator']);
        $t->same('Director, Edna', $item['rawBibtex']['fields']['editorialdirector']);
        $t->same('Willa Writer. Secondary Credit Packet. 2026. Name annotations: Series creator 1: series imported from legacy catalog.', $bibliography);

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="editor-translator"/>
        <names variable="series-creator"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="editor-translator"/>
      <names variable="series-creator"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML);
        $styledItem = $styled->item('secondary-credits');
        $t->same('Translator', $styledItem['editorTranslators'][0]['family'] ?? null);
        $t->same('Series', $styledItem['seriesCreators'][0]['family'] ?? null);
        $t->same('series imported from legacy catalog', $styledItem['seriesCreators'][0]['annotations'][0]['value'] ?? null);
        $t->same('[Translator | Series]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'secondary-credits', 'text' => '[@secondary-credits]']),
        ]));
        $t->same('Secondary Credit Packet :: Translator, Theo :: Series, Stella :: Series creator 1: series imported from legacy catalog', $styled->renderBibliographyEntry('secondary-credits'));

        $document = (new MarkdownReader())->read('Secondary legacy credits [@secondary-credits] stay reviewable.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Secondary legacy credits [Translator | Series] stay reviewable.</p>', $blocks);
        $t->contains('<dt>Writer 2026</dt><dd>Secondary Credit Packet :: Translator, Theo :: Series, Stella :: Series creator 1: series imported from legacy catalog</dd>', $blocks);
    },
    'carries biblatex secondary editor roles in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@collection{legacy-secondary-editor-roles,
  author      = {Smith, Ada},
  editor      = {Primary, Pat},
  editortype  = {compiler},
  editora     = {Roe, Riley},
  editora+an  = {1=compiled packet notes},
  editoratype = {redactor},
  editorb     = {Ng, Nia},
  editorbtype = {editorialdirector},
  editorc     = {Cruz, Ana Maria},
  editorctype = {foreword},
  title       = {Legacy Secondary Editor Packet},
  publisher   = {Review Press},
  year        = {2026}
}
BIB;

        $items = (new BibtexCslProcessor())->cslItems($source);
        $item = $items['legacy-secondary-editor-roles'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('Primary', $item['compiler'][0]['family']);
        $t->same('Pat', $item['compiler'][0]['given']);
        $t->same('Roe', $item['redactor'][0]['family']);
        $t->same('compiled packet notes', $item['redactor'][0]['annotations'][0]['value'] ?? null);
        $t->same('Ng', $item['editorial-director'][0]['family']);
        $t->same('Cruz', $item['foreword'][0]['family']);
        $t->same('editor', $item['editorial-roles'][0]['field'] ?? null);
        $t->same('compiler', $item['editorial-roles'][0]['type'] ?? null);
        $t->same('editora', $item['editorial-roles'][1]['field'] ?? null);
        $t->same('redactor', $item['editorial-roles'][1]['type'] ?? null);
        $t->same('editorial-director', $item['editorial-roles'][2]['type'] ?? null);
        $t->same('foreword', $item['editorial-roles'][3]['type'] ?? null);
        $t->same(false, isset($item['biblatex-field-annotations']['editora']));
        $t->contains('Name annotations: Redactor 1: compiled packet notes', $bibliography);
        $t->contains('BibLaTeX editorial roles: editor compiler: Primary, Pat; editora redactor: Roe, Riley; editorb editorial director: Ng, Nia; editorc foreword: Cruz, Ana Maria', $bibliography);

        $processor = CitationCslProcessor::fromItems(array_values($items));
        $normalized = $processor->item('legacy-secondary-editor-roles');
        $t->same('Primary', $normalized['compilers'][0]['family'] ?? null);
        $t->same('Roe', $normalized['redactors'][0]['family'] ?? null);
        $t->same('compiled packet notes', $normalized['redactors'][0]['annotations'][0]['value'] ?? null);
        $t->same('Ng', $normalized['editorialDirectors'][0]['family'] ?? null);
        $t->same('Cruz', $normalized['forewordAuthors'][0]['family'] ?? null);

        $styled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="compiler"/>
        <names variable="redactor"/>
        <names variable="editorial-director"/>
        <names variable="foreword"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="editorial-role-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $citation = new AstNode('citation', ['id' => 'legacy-secondary-editor-roles', 'text' => '[@legacy-secondary-editor-roles]']);
        $t->same('[Primary | Roe | Ng | Cruz]', $styled->renderCitationCluster([$citation]));
        $t->same('Legacy Secondary Editor Packet :: Compiled by Primary, Pat. Redacted by Roe, Riley. Editorial direction by Ng, Nia. Foreword by Cruz, Ana Maria.', $styled->renderBibliographyEntry('legacy-secondary-editor-roles'));

        $document = (new MarkdownReader())->read('Legacy secondary editor roles [@legacy-secondary-editor-roles] stay reviewable.');
        $blocks = (new WordPressBlockWriter())->write($processor->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Legacy secondary editor roles (Smith 2026) stay reviewable.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Smith, Ada. Legacy Secondary Editor Packet. Review Press, 2026. Name annotations: Redactor 1: compiled packet notes. Compiled by Primary, Pat. Redacted by Roe, Riley. Editorial direction by Ng, Nia. Foreword by Cruz, Ana Maria.</dd>', $blocks);
    },
    'carries biblatex media and participant creator roles in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@video{legacy-production,
  producer          = {Producer, Pia},
  performer         = {Performer, Pat and Ensemble, Archive},
  narrator          = {Narrator, Nia},
  executiveproducer = {Executive, Eli},
  scriptwriter      = {Writer, Sam},
  title             = {Legacy Production Packet},
  year              = {2026}
}

@audio{legacy-conversation,
  host  = {Host, Hugo},
  guest = {Guest, Gia and Roe, Pat},
  title = {Legacy Conversation Packet},
  year  = {2025}
}

@proceedings{legacy-participants,
  chair       = {Committee, Program},
  composer    = {Morton, Mia},
  contributor = {Contributors, Migration},
  curator     = {Curator, Eli},
  title       = {Legacy Participant Packet},
  year        = {2024}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $production = $items['legacy-production'];
        $conversation = $items['legacy-conversation'];
        $participants = $items['legacy-participants'];

        $t->same('motion_picture', $production['type']);
        $t->same('song', $conversation['type']);
        $t->same('paper-conference', $participants['type']);
        $t->same('Producer', $production['producer'][0]['family']);
        $t->same('Performer', $production['performer'][0]['family']);
        $t->same('Ensemble', $production['performer'][1]['family']);
        $t->same('Narrator', $production['narrator'][0]['family']);
        $t->same('Executive', $production['executive-producer'][0]['family']);
        $t->same('Writer', $production['script-writer'][0]['family']);
        $t->same('Host', $conversation['host'][0]['family']);
        $t->same('Guest', $conversation['guest'][0]['family']);
        $t->same('Roe', $conversation['guest'][1]['family']);
        $t->same('Committee', $participants['chair'][0]['family']);
        $t->same('Morton', $participants['composer'][0]['family']);
        $t->same('Contributors', $participants['contributor'][0]['family']);
        $t->same('Curator', $participants['curator'][0]['family']);
        $t->same('Executive, Eli', $production['rawBibtex']['fields']['executiveproducer']);
        $t->same('Writer, Sam', $production['rawBibtex']['fields']['scriptwriter']);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Legacy BibLaTeX Media Creator Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-media-creator-review</id>
    <updated>2026-06-14T09:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="producer"/>
        <names variable="performer"/>
        <names variable="host"/>
        <names variable="guest"/>
        <names variable="chair"/>
        <names variable="curator"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="producer"/>
      <names variable="executive-producer"/>
      <names variable="script-writer"/>
      <names variable="host"/>
      <names variable="guest"/>
      <names variable="chair"/>
      <names variable="composer"/>
      <names variable="contributor"/>
      <names variable="curator"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Media Creator Review', $summary['title'] ?? null);
        $t->same('producer', $summary['citationRendering'][0]['children'][0]['variable'] ?? null);
        $t->same('(Producer | Performer and Ensemble | 2026; Host | Guest and Roe | 2025; Committee | Curator | 2024)', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-production', 'text' => '[@legacy-production]']),
            new AstNode('citation', ['id' => 'legacy-conversation', 'text' => '[@legacy-conversation]']),
            new AstNode('citation', ['id' => 'legacy-participants', 'text' => '[@legacy-participants]']),
        ]));
        $t->same('Legacy Production Packet :: Producer, Pia :: Executive, Eli :: Writer, Sam', $styled->renderBibliographyEntry('legacy-production'));
        $t->same('Legacy Conversation Packet :: Host, Hugo :: Guest, Gia; Roe, Pat', $styled->renderBibliographyEntry('legacy-conversation'));
        $t->same('Legacy Participant Packet :: Committee, Program :: Morton, Mia :: Contributors, Migration :: Curator, Eli', $styled->renderBibliographyEntry('legacy-participants'));

        $document = (new MarkdownReader())->read('Legacy media credits [@legacy-production; @legacy-conversation; @legacy-participants] keep creator roles visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Legacy media credits (Producer | Performer and Ensemble | 2026; Host | Guest and Roe | 2025; Committee | Curator | 2024) keep creator roles visible.</p>', $blocks);
        $t->contains('<dt>Legacy Participant Packet 2024</dt><dd>Legacy Participant Packet :: Committee, Program :: Morton, Mia :: Contributors, Migration :: Curator, Eli</dd>', $blocks);
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

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($source)['translated-manual'];
        $bibliography = $processor->renderBibliographyText($item);

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
        $t->same('Gia Garcia. Migration Manual. Review Press. 2026. Collection: Review Sources. Collection abbreviation: RS. Collection number: 7. Edition: 2. Version: 2.1.0. Status: revised. Medium: print-on-demand packet.', $bibliography);

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="collection-title"/>
        <text variable="collection-title" form="short"/>
        <text variable="collection-number"/>
        <text variable="edition"/>
        <text variable="version"/>
        <text variable="status"/>
        <text variable="medium"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="collection-title"/>
      <text variable="collection-title" form="short"/>
      <text variable="collection-number"/>
      <text variable="edition"/>
      <text variable="version"/>
      <text variable="status"/>
      <text variable="medium"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[Migration Manual | Review Sources | RS | 7 | 2 | 2.1.0 | revised | print-on-demand packet]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'translated-manual', 'text' => '[@translated-manual]']),
        ]));
        $t->same('Migration Manual :: Review Sources :: RS :: 7 :: 2 :: 2.1.0 :: revised :: print-on-demand packet', $styled->renderBibliographyEntry('translated-manual'));

        $document = (new MarkdownReader())->read('Release state [@translated-manual] stays visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Release state [Migration Manual | Review Sources | RS | 7 | 2 | 2.1.0 | revised | print-on-demand packet] stays visible.</p>', $blocks);
        $t->contains('<dt>Garcia 2026</dt><dd>Migration Manual :: Review Sources :: RS :: 7 :: 2 :: 2.1.0 :: revised :: print-on-demand packet</dd>', $blocks);
    },
    'carries compact biblatex original publication aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{compact-original,
  author                 = {Diaz, Dana},
  title                  = {Migration Manual Reissue},
  originaltitle          = {Manual de Migracion},
  originalsubtitle       = {Appendix de Archivo},
  originaltitleaddon     = {compact source note},
  originalyear           = {2019},
  originalmonth          = {7},
  originalday            = {2},
  originalpublisher      = {Archivo Desk},
  originalpublisherplace = {Seville},
  publisher              = {Review Press},
  date                   = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($source)['compact-original'];

        $t->same('Migration Manual Reissue', $item['title']);
        $t->same('Manual de Migracion: Appendix de Archivo', $item['original-title']);
        $t->same('compact source note', $item['original-title-addon']);
        $t->same([2019, 7, 2], $item['original-date']['date-parts'][0]);
        $t->same('Archivo Desk', $item['original-publisher']);
        $t->same('Seville', $item['original-publisher-place']);
        $t->same('Manual de Migracion', $item['rawBibtex']['fields']['originaltitle']);
        $t->same('2019', $item['rawBibtex']['fields']['originalyear']);

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Compact Original Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-compact-original-review</id>
    <updated>2026-06-29T22:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="original-title"/>
        <text variable="original-title-addon"/>
        <date variable="original-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-title"/>
      <text variable="original-title-addon"/>
      <date variable="original-date"/>
      <text variable="original-publisher"/>
      <text variable="original-publisher-place"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('compact-original');
        $t->same('Bounded Legacy BibLaTeX Compact Original Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('Manual de Migracion: Appendix de Archivo', $styledItem['originalTitle'] ?? null);
        $t->same('compact source note', $styledItem['originalTitleAddon'] ?? null);
        $t->same('2019-07-02', $styledItem['originalDate']['display'] ?? null);
        $t->same('[Migration Manual Reissue | Manual de Migracion: Appendix de Archivo | compact source note | 2019-07-02]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'compact-original', 'text' => '[@compact-original]']),
        ]));
        $t->same('Migration Manual Reissue :: Manual de Migracion: Appendix de Archivo :: compact source note :: 2019-07-02 :: Archivo Desk :: Seville', $styled->renderBibliographyEntry('compact-original'));

        $document = (new MarkdownReader())->read('Compact original publication [@compact-original] remains visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['compact-original'], $handoff['citedKeys']);
        $t->same([[2019, 7, 2]], $handoff['bibliography']->children[0]->attr('cslItem')['original-date']['date-parts'] ?? null);
        $t->contains('<p>Compact original publication [Migration Manual Reissue | Manual de Migracion: Appendix de Archivo | compact source note | 2019-07-02] remains visible.</p>', $blocks);
        $t->contains('<dt>Diaz 2026</dt><dd>Migration Manual Reissue :: Manual de Migracion: Appendix de Archivo :: compact source note :: 2019-07-02 :: Archivo Desk :: Seville</dd>', $blocks);
    },
    'carries biblatex reprint title and original genre in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{legacy-facsimile,
  author       = {Garcia, Gia},
  title        = {Migration Manual},
  origtitle    = {Manual de Migracion},
  origtype     = {field manual},
  reprinttitle = {Facsimile Source Packet},
  publisher    = {Review Press},
  date         = {2026}
}

@article{legacy-archive-reprint,
  author         = {Ng, Nia},
  title          = {Archive Reprint Notice},
  journaltitle   = {Review Journal},
  original-genre = {archive bulletin},
  reprint-title  = {Updated Source Reprint},
  date           = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $facsimile = $items['legacy-facsimile'];
        $archive = $items['legacy-archive-reprint'];

        $t->same('field manual', $facsimile['original-genre']);
        $t->same('Facsimile Source Packet', $facsimile['reprint-title']);
        $t->same('field manual', $facsimile['rawBibtex']['fields']['origtype']);
        $t->same('Facsimile Source Packet', $facsimile['rawBibtex']['fields']['reprinttitle']);
        $t->same('archive bulletin', $archive['original-genre']);
        $t->same('Updated Source Reprint', $archive['reprint-title']);
        $t->same('archive bulletin', $archive['rawBibtex']['fields']['original-genre']);
        $t->same('Updated Source Reprint', $archive['rawBibtex']['fields']['reprint-title']);
        $t->contains('Original genre: field manual', $processor->renderBibliographyText($facsimile));
        $t->contains('Reprint title: Facsimile Source Packet', $processor->renderBibliographyText($facsimile));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Reprint Provenance Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-reprint-provenance-review</id>
    <updated>2026-06-29T15:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="original-genre"/>
        <text variable="reprint-title"/>
        <text variable="origtype"/>
        <text variable="reprinttitle"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="original-genre"/>
      <text variable="reprint-title"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledFacsimile = $styled->item('legacy-facsimile');
        $styledArchive = $styled->item('legacy-archive-reprint');
        $t->same('Bounded Legacy BibLaTeX Reprint Provenance Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('field manual', $styledFacsimile['originalGenre'] ?? null);
        $t->same('Facsimile Source Packet', $styledFacsimile['reprintTitle'] ?? null);
        $t->same('archive bulletin', $styledArchive['originalGenre'] ?? null);
        $t->same('Updated Source Reprint', $styledArchive['reprintTitle'] ?? null);
        $t->same('[Garcia | field manual | Facsimile Source Packet | field manual | Facsimile Source Packet; Ng | archive bulletin | Updated Source Reprint | archive bulletin | Updated Source Reprint]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-facsimile', 'text' => '[@legacy-facsimile]']),
            new AstNode('citation', ['id' => 'legacy-archive-reprint', 'text' => '[@legacy-archive-reprint]']),
        ]));
        $t->same('Migration Manual :: field manual :: Facsimile Source Packet', $styled->renderBibliographyEntry('legacy-facsimile'));
        $t->same('Archive Reprint Notice :: archive bulletin :: Updated Source Reprint', $styled->renderBibliographyEntry('legacy-archive-reprint'));

        $document = (new MarkdownReader())->read('Legacy reprint sources [@legacy-facsimile; @legacy-archive-reprint] retain provenance.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-facsimile', 'legacy-archive-reprint'], $handoff['citedKeys']);
        $t->same('field manual', $handoff['items'][0]['original-genre'] ?? null);
        $t->same('Updated Source Reprint', $handoff['bibliography']->children[1]->attr('cslItem')['reprint-title'] ?? null);
        $t->contains('<p>Legacy reprint sources [Garcia | field manual | Facsimile Source Packet | field manual | Facsimile Source Packet; Ng | archive bulletin | Updated Source Reprint | archive bulletin | Updated Source Reprint] retain provenance.</p>', $blocks);
        $t->contains('<dt>Garcia 2026</dt><dd>Migration Manual :: field manual :: Facsimile Source Packet</dd>', $blocks);
        $t->contains('<dt>Ng 2025</dt><dd>Archive Reprint Notice :: archive bulletin :: Updated Source Reprint</dd>', $blocks);
    },
    'carries biblatex translated title aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{translated-title-source,
  author              = {Garcia, Gia},
  title               = {Migration Manual},
  titleTranslation    = {Manual de Migracion},
  subtitleTranslation = {Apendice de Archivo},
  publisher           = {Review Press},
  date                = {2026}
}

@incollection{translated-hyphen-source,
  author              = {Roe, Rae},
  title               = {Chapter Packet},
  booktitle           = {Translation Review Sourcebook},
  translated-title    = {Paquete de Capitulo},
  translated-subtitle = {Anexo},
  pages               = {9--12},
  date                = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $translatedTitle = $items['translated-title-source'];
        $hyphenTitle = $items['translated-hyphen-source'];

        $t->same('Manual de Migracion', $translatedTitle['translated-title']);
        $t->same('Apendice de Archivo', $translatedTitle['translated-subtitle']);
        $t->same('Manual de Migracion', $translatedTitle['rawBibtex']['fields']['titletranslation']);
        $t->same('Apendice de Archivo', $translatedTitle['rawBibtex']['fields']['subtitletranslation']);
        $t->same('Paquete de Capitulo', $hyphenTitle['translated-title']);
        $t->same('Paquete de Capitulo', $hyphenTitle['rawBibtex']['fields']['translated-title']);
        $t->same('Gia Garcia. Migration Manual. Translated title: Manual de Migracion: Apendice de Archivo. Review Press. 2026.', $processor->renderBibliographyText($translatedTitle));

        $document = (new MarkdownReader())->read('Translation review [@translated-title-source; @translated-hyphen-source] keeps translated title metadata visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $t->same(['translated-title-source', 'translated-hyphen-source'], $handoff['citedKeys']);
        $t->same('Manual de Migracion', $handoff['bibliography']->children[0]->attr('cslItem')['translated-title'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Translated Title Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-translated-title-review</id>
    <updated>2026-06-15T04:30:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="translated-title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="translated-title"/>
      <text variable="translated-subtitle"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same('Bounded Legacy BibLaTeX Translated Title Review', $summary['title'] ?? null);
        $t->same('(Garcia | Manual de Migracion: Apendice de Archivo | 2026; Roe | Paquete de Capitulo: Anexo | 2025)', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'translated-title-source', 'text' => '[@translated-title-source]']),
            new AstNode('citation', ['id' => 'translated-hyphen-source', 'text' => '[@translated-hyphen-source]']),
        ]));
        $t->same('Migration Manual :: Manual de Migracion: Apendice de Archivo :: Apendice de Archivo', $styled->renderBibliographyEntry('translated-title-source'));
        $t->contains('<p>Translation review (Garcia | Manual de Migracion: Apendice de Archivo | 2026; Roe | Paquete de Capitulo: Anexo | 2025) keeps translated title metadata visible.</p>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Chapter Packet :: Paquete de Capitulo: Anexo :: Anexo</dd>', $blocks);
    },
    'carries biblatex title family aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@incollection{legacy-title-family,
  author          = {Ng, Nia},
  title           = {Source Chapter},
  booktitle       = {Migration Handbook},
  maintitle       = {Migration Source Corpus},
  mainsubtitle    = {Archive Desk},
  maintitleaddon  = {source addendum},
  volumetitle     = {Review Volume},
  volumesubtitle  = {Appendix},
  shortvolumetitle = {RV},
  parttitle       = {Part Ledger},
  partsubtitle    = {Field Notes},
  issuetitle      = {Special Issue},
  issuesubtitle   = {Source Reports},
  issuetitleaddon = {editorial packet},
  pages           = {21--23},
  date            = {2026}
}

@book{legacy-title-family-compact,
  author             = {Roe, Rae},
  title              = {Compact Title Family Packet},
  main-title-text    = {Compact Main Text},
  volume-title-text  = {Compact Volume Text},
  part-title-text    = {Alpha Compact Part},
  issue-title-text   = {Compact Issue},
  date               = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $legacy = $items['legacy-title-family'];
        $compact = $items['legacy-title-family-compact'];

        $t->same('Migration Source Corpus: Archive Desk', $legacy['main-title']);
        $t->same('source addendum', $legacy['main-title-addon']);
        $t->same('Review Volume: Appendix', $legacy['volume-title']);
        $t->same('RV', $legacy['volume-title-short']);
        $t->same('Part Ledger: Field Notes', $legacy['part-title']);
        $t->same('Special Issue: Source Reports', $legacy['issue-title']);
        $t->same('editorial packet', $legacy['issue-title-addon']);
        $t->same('Migration Source Corpus', $legacy['rawBibtex']['fields']['maintitle']);
        $t->same('RV', $legacy['rawBibtex']['fields']['shortvolumetitle']);
        $t->same('Special Issue', $legacy['rawBibtex']['fields']['issuetitle']);
        $t->same('Compact Main Text', $compact['main-title']);
        $t->same('Compact Volume Text', $compact['volume-title']);
        $t->same('Alpha Compact Part', $compact['part-title']);
        $t->same('Compact Issue', $compact['issue-title']);
        $t->contains('Main title: Migration Source Corpus: Archive Desk', $processor->renderBibliographyText($legacy));
        $t->contains('Issue title addendum: editorial packet', $processor->renderBibliographyText($legacy));

        $document = (new MarkdownReader())->read('Legacy title family [@legacy-title-family; @legacy-title-family-compact] keeps imported title metadata visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $t->same(['legacy-title-family', 'legacy-title-family-compact'], $handoff['citedKeys']);
        $t->same('Part Ledger: Field Notes', $handoff['bibliography']->children[0]->attr('cslItem')['part-title'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Title Family Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-title-family-review</id>
    <updated>2026-06-15T10:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="main-title"/>
        <text variable="main-title-addon"/>
        <text variable="volume-title-short"/>
        <text variable="part-title"/>
        <text variable="issue-title"/>
        <text variable="issue-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="container-title"/>
      <text variable="main-title"/>
      <text variable="main-title-addon"/>
      <text variable="volume-title"/>
      <text variable="volume-title-short"/>
      <text variable="part-title"/>
      <text variable="issue-title"/>
      <text variable="issue-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $t->same('Bounded Legacy BibLaTeX Title Family Review', $summary['title'] ?? null);
        $t->same('main-title', $citationChildren[1]['variable'] ?? null);
        $t->same('volume-title-short', $citationChildren[3]['variable'] ?? null);
        $t->same('[Ng | Migration Source Corpus: Archive Desk | source addendum | RV | Part Ledger: Field Notes | Special Issue: Source Reports | editorial packet; Roe | Compact Main Text | Alpha Compact Part | Compact Issue]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-title-family', 'text' => '[@legacy-title-family]']),
            new AstNode('citation', ['id' => 'legacy-title-family-compact', 'text' => '[@legacy-title-family-compact]']),
        ]));
        $t->same('Source Chapter :: Migration Handbook :: Migration Source Corpus: Archive Desk :: source addendum :: Review Volume: Appendix :: RV :: Part Ledger: Field Notes :: Special Issue: Source Reports :: editorial packet', $styled->renderBibliographyEntry('legacy-title-family'));
        $t->same('Compact Title Family Packet :: Compact Main Text :: Compact Volume Text :: Alpha Compact Part :: Compact Issue', $styled->renderBibliographyEntry('legacy-title-family-compact'));

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Legacy title family [Ng | Migration Source Corpus: Archive Desk | source addendum | RV | Part Ledger: Field Notes | Special Issue: Source Reports | editorial packet; Roe | Compact Main Text | Alpha Compact Part | Compact Issue] keeps imported title metadata visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Source Chapter :: Migration Handbook :: Migration Source Corpus: Archive Desk :: source addendum :: Review Volume: Appendix :: RV :: Part Ledger: Field Notes :: Special Issue: Source Reports :: editorial packet</dd>', $blocks);
    },
    'carries biblatex status taxonomy aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-status-hyphen,
  author             = {Ng, Nia},
  title              = {Legacy Status Hyphen Packet},
  journaltitle       = {Migration Review},
  date               = {2026},
  publication-status = {accepted},
  keyword-list       = {source audit; release queue},
  category-list      = {biblatex, taxonomy}
}

@report{legacy-status-camel,
  author            = {Roe, Rae},
  title             = {Legacy Status Camel Packet},
  institution       = {Archive Desk},
  date              = {2025},
  publicationStatus = {in press},
  keywordList       = {review queue, compact alias},
  categoryList      = {handoff; csl}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $hyphen = $items['legacy-status-hyphen'];
        $camel = $items['legacy-status-camel'];
        $t->same('accepted', $hyphen['status']);
        $t->same(['source audit', 'release queue'], $hyphen['keyword']);
        $t->same(['biblatex', 'taxonomy'], $hyphen['categories']);
        $t->same('accepted', $hyphen['rawBibtex']['fields']['publication-status']);
        $t->same('source audit; release queue', $hyphen['rawBibtex']['fields']['keyword-list']);
        $t->same('biblatex, taxonomy', $hyphen['rawBibtex']['fields']['category-list']);
        $t->same('in press', $camel['status']);
        $t->same(['review queue', 'compact alias'], $camel['keyword']);
        $t->same(['handoff', 'csl'], $camel['categories']);
        $t->same('in press', $camel['rawBibtex']['fields']['publicationstatus']);
        $t->same('review queue, compact alias', $camel['rawBibtex']['fields']['keywordlist']);
        $t->same('handoff; csl', $camel['rawBibtex']['fields']['categorylist']);

        $document = (new MarkdownReader())->read('Legacy status source @legacy-status-hyphen and [@legacy-status-camel] keep alias metadata.');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['legacy-status-hyphen', 'legacy-status-camel'], $handoff['citedKeys']);
        $t->same([], $handoff['missingKeys']);
        $t->same('accepted', $handoff['items'][0]['status']);
        $t->same(['source audit', 'release queue'], $handoff['items'][0]['keyword']);
        $t->same('in press', $handoff['bibliography']->children[1]->attr('cslItem')['status'] ?? null);
        $t->contains('<dt>legacy-status-hyphen</dt>', $blocks);
        $t->contains('Legacy Status Camel Packet', $blocks);
    },
    'carries biblatex entry subtype metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-entry-subtype,
  author       = {Ng, Nia},
  title        = {Legacy Entry Subtype Packet},
  journaltitle = {Migration Review},
  date         = {2026},
  type         = {review article},
  entrysubtype = {source-note},
  status       = {forthcoming}
}

@online{hyphen-entry-subtype,
  author        = {Roe, Rae},
  title         = {Hyphen Entry Subtype Packet},
  date          = {2025},
  entry-subtype = {archive-update},
  pubstate      = {prepublished},
  url           = {https://example.test/entry-subtype}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $legacy = $items['legacy-entry-subtype'];
        $hyphen = $items['hyphen-entry-subtype'];

        $t->same('review article', $legacy['genre']);
        $t->same('source-note', $legacy['entry-subtype']);
        $t->same('archive-update', $hyphen['entry-subtype']);
        $t->same('source-note', $legacy['rawBibtex']['fields']['entrysubtype']);
        $t->same('archive-update', $hyphen['rawBibtex']['fields']['entry-subtype']);
        $t->contains('Entry subtype: source-note', $processor->renderBibliographyText($legacy));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Entry Subtype Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-entry-subtype-review</id>
    <updated>2026-06-30T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="genre"/>
        <text variable="entry-subtype"/>
        <text variable="status"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="entry-subtype"/>
      <text variable="status"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledLegacy = $styled->item('legacy-entry-subtype');
        $styledHyphen = $styled->item('hyphen-entry-subtype');
        $t->same('Bounded Legacy BibLaTeX Entry Subtype Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('source-note', $styledLegacy['entrySubtype'] ?? null);
        $t->same('archive-update', $styledHyphen['entrySubtype'] ?? null);
        $t->same('[Legacy Entry Subtype Packet | review article | source-note | forthcoming; Hyphen Entry Subtype Packet | archive-update | prepublished]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-entry-subtype', 'text' => '[@legacy-entry-subtype]']),
            new AstNode('citation', ['id' => 'hyphen-entry-subtype', 'text' => '[@hyphen-entry-subtype]']),
        ]));
        $t->same('Legacy Entry Subtype Packet :: source-note :: forthcoming', $styled->renderBibliographyEntry('legacy-entry-subtype'));
        $t->same('Hyphen Entry Subtype Packet :: archive-update :: prepublished', $styled->renderBibliographyEntry('hyphen-entry-subtype'));

        $document = (new MarkdownReader())->read('Subtype metadata [@legacy-entry-subtype; @hyphen-entry-subtype] stays reviewable.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-entry-subtype', 'hyphen-entry-subtype'], $handoff['citedKeys']);
        $t->same('source-note', $handoff['items'][0]['entry-subtype'] ?? null);
        $t->same('archive-update', $handoff['bibliography']->children[1]->attr('cslItem')['entry-subtype'] ?? null);
        $t->contains('<p>Subtype metadata [Legacy Entry Subtype Packet | review article | source-note | forthcoming; Hyphen Entry Subtype Packet | archive-update | prepublished] stays reviewable.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Legacy Entry Subtype Packet :: source-note :: forthcoming</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Hyphen Entry Subtype Packet :: archive-update :: prepublished</dd>', $blocks);
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
    'carries biblatex extent number metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@inbook{extent-handoff,
  author    = {Chapter, Casey},
  title     = {Extent Review Chapter},
  booktitle = {Migration Extent Handbook},
  date      = {2026},
  volume    = {2},
  volumes   = {4},
  chapter   = {7},
  pages     = {101--120},
  pagetotal = {320}
}
BIB;

        $item = (new BibtexCslProcessor())->cslItems($source)['extent-handoff'];
        $bibliography = (new BibtexCslProcessor())->renderBibliographyText($item);

        $t->same('chapter', $item['type']);
        $t->same('2', $item['volume']);
        $t->same('4', $item['number-of-volumes']);
        $t->same('7', $item['chapter-number']);
        $t->same('101-120', $item['page']);
        $t->same('320', $item['number-of-pages']);
        $t->same('Casey Chapter. Extent Review Chapter. Migration Extent Handbook 2. 2026. 101-120.', $bibliography);
    },
    'carries biblatex division part printing and supplement numbers in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{production-review,
  author             = {Ng, Nia},
  title              = {Production Review Manual},
  date               = {2026},
  division           = {appendix},
  partnumber         = {3},
  printing-number    = {2},
  supplementnumber   = {1}
}

@report{subdivision-review,
  author             = {Roe, Pat},
  title              = {Subdivision Review Packet},
  date               = {2025},
  subdivision        = {field report},
  part               = {A},
  printnumber        = {advance},
  supplement-number  = {S-2}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $production = $items['production-review'];
        $subdivision = $items['subdivision-review'];

        $t->same('appendix', $production['division']);
        $t->same('3', $production['part']);
        $t->same('2', $production['printing-number']);
        $t->same('1', $production['supplement-number']);
        $t->same('field report', $subdivision['division']);
        $t->same('A', $subdivision['part']);
        $t->same('advance', $subdivision['printing-number']);
        $t->same('S-2', $subdivision['supplement-number']);
        $t->same('3', $production['rawBibtex']['fields']['partnumber']);
        $t->same('2', $production['rawBibtex']['fields']['printing-number']);
        $t->same('field report', $subdivision['rawBibtex']['fields']['subdivision']);
        $t->same('S-2', $subdivision['rawBibtex']['fields']['supplement-number']);
        $t->same(
            'Nia Ng. Production Review Manual. 2026. Division: appendix. Part: 3. Printing number: 2. Supplement number: 1.',
            $processor->renderBibliographyText($production)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="division"/>
        <text variable="part-number"/>
        <text variable="printing-number"/>
        <text variable="supplement-number"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="division"/>
      <text variable="part-number"/>
      <text variable="printing-number"/>
      <text variable="supplement-number"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledProduction = $styled->item('production-review');
        $t->same('appendix', $styledProduction['division'] ?? null);
        $t->same('3', $styledProduction['part'] ?? null);
        $t->same('2', $styledProduction['printingNumber'] ?? null);
        $t->same('1', $styledProduction['supplementNumber'] ?? null);
        $t->same('[Production Review Manual | appendix | 3 | 2 | 1; Subdivision Review Packet | field report | A | advance | S-2]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'production-review', 'text' => '[@production-review]']),
            new AstNode('citation', ['id' => 'subdivision-review', 'text' => '[@subdivision-review]']),
        ]));
        $t->same('Production Review Manual :: appendix :: 3 :: 2 :: 1', $styled->renderBibliographyEntry('production-review'));
        $t->same('Subdivision Review Packet :: field report :: A :: advance :: S-2', $styled->renderBibliographyEntry('subdivision-review'));

        $document = (new MarkdownReader())->read('Legacy production metadata [@production-review; @subdivision-review] stays visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['production-review', 'subdivision-review'], $handoff['citedKeys']);
        $t->same('2', $handoff['items'][0]['printing-number']);
        $t->same('S-2', $handoff['items'][1]['supplement-number']);
        $t->contains('<p>Legacy production metadata [Production Review Manual | appendix | 3 | 2 | 1; Subdivision Review Packet | field report | A | advance | S-2] stays visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Production Review Manual :: appendix :: 3 :: 2 :: 1</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Subdivision Review Packet :: field report :: A :: advance :: S-2</dd>', $blocks);
    },
    'carries biblatex pagination unit metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-pagination-review,
  author         = {Ng, Nia},
  title          = {Column Pagination Review},
  journaltitle   = {Source Unit Ledger},
  date           = {2026},
  pages          = {12--14},
  pagination     = {column},
  bookpagination = {section}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-pagination-review'];

        $t->same('12-14', $item['page']);
        $t->same('column', $item['pagination']);
        $t->same('section', $item['book-pagination']);
        $t->same('column', $item['rawBibtex']['fields']['pagination']);
        $t->same('section', $item['rawBibtex']['fields']['bookpagination']);
        $t->same(
            'Nia Ng. Column Pagination Review. Source Unit Ledger. 2026. 12-14. Pagination: column. Book pagination: section.',
            $processor->renderBibliographyText($item)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" ">
        <label variable="page" form="long"/>
        <text variable="page"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <label variable="page" form="short"/>
      <text variable="page"/>
      <text variable="book-pagination"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('legacy-pagination-review');
        $t->same('column', $styledItem['pagination'] ?? null);
        $t->same('section', $styledItem['bookPagination'] ?? null);
        $t->same('columns 12-14', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-pagination-review', 'text' => '[@legacy-pagination-review]']),
        ]));
        $t->same('Column Pagination Review | cols. | 12-14 | section', $styled->renderBibliographyEntry('legacy-pagination-review'));

        $document = (new MarkdownReader())->read('Pagination review [@legacy-pagination-review] keeps page-unit labels visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Pagination review columns 12-14 keeps page-unit labels visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Column Pagination Review | cols. | 12-14 | section</dd>', $blocks);
    },
    'carries legacy biblatex journal abbreviation and article number metadata' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-journal-id,
  author       = {Roe, Pat},
  title        = {Electronic Article Packet},
  journaltitle = {Journal of Legacy Imports},
  shortjournal = {J. Legacy Import.},
  date         = {2026},
  eid          = {e2026-42},
  doi          = {10.5555/legacy-eid}
}

@article{legacy-short-alias,
  author              = {Ng, Nia},
  title               = {Explicit Article Number Packet},
  journal             = {Migration Review Quarterly},
  journalabbreviation = {Migr. Rev. Q.},
  year                = {2025},
  article-number      = {A-77}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $journal = $items['legacy-journal-id'];
        $alias = $items['legacy-short-alias'];

        $t->same('J. Legacy Import.', $journal['container-title-short']);
        $t->same('J. Legacy Import.', $journal['journal-abbreviation']);
        $t->same('e2026-42', $journal['article-number']);
        $t->same('e2026-42', $journal['rawBibtex']['fields']['eid']);
        $t->same('Migr. Rev. Q.', $alias['container-title-short']);
        $t->same('Migr. Rev. Q.', $alias['journal-abbreviation']);
        $t->same('A-77', $alias['article-number']);
        $t->same('A-77', $alias['rawBibtex']['fields']['article-number']);
        $t->same(
            'Pat Roe. Electronic Article Packet. Journal of Legacy Imports. 2026. Journal abbreviation: J. Legacy Import. Article number: e2026-42. doi:10.5555/legacy-eid.',
            $processor->renderBibliographyText($journal)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Legacy BibLaTeX Journal Identifier Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-journal-identifier-review</id>
    <updated>2026-06-15T12:45:00+00:00</updated>
  </info>
  <citation>
    <layout delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="container-title-short"/>
        <text variable="journal-abbreviation"/>
        <text variable="article-number"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="container-title-short"/>
      <text variable="article-number"/>
      <text variable="DOI"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Journal Identifier Review', $summary['title'] ?? null);
        $t->same('container-title-short', $summary['citationRendering'][0]['children'][1]['variable'] ?? null);
        $t->same(
            'Electronic Article Packet | J. Legacy Import. | J. Legacy Import. | e2026-42; Explicit Article Number Packet | Migr. Rev. Q. | Migr. Rev. Q. | A-77',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'legacy-journal-id', 'text' => '[@legacy-journal-id]']),
                new AstNode('citation', ['id' => 'legacy-short-alias', 'text' => '[@legacy-short-alias]']),
            ])
        );
        $t->same('Electronic Article Packet :: J. Legacy Import. :: e2026-42 :: 10.5555/legacy-eid', $styled->renderBibliographyEntry('legacy-journal-id'));
        $t->same('Explicit Article Number Packet :: Migr. Rev. Q. :: A-77', $styled->renderBibliographyEntry('legacy-short-alias'));

        $document = (new MarkdownReader())->read('Legacy journal identifiers [@legacy-journal-id; @legacy-short-alias] stay visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Legacy journal identifiers Electronic Article Packet | J. Legacy Import. | J. Legacy Import. | e2026-42; Explicit Article Number Packet | Migr. Rev. Q. | Migr. Rev. Q. | A-77 stay visible.</p>', $blocks);
        $t->contains('<dt>Roe 2026</dt><dd>Electronic Article Packet :: J. Legacy Import. :: e2026-42 :: 10.5555/legacy-eid</dd>', $blocks);
    },
    'carries biblatex source locator metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@misc{source-locator,
  author     = {Roe, Pat},
  title      = {Source Locator Packet},
  date       = {2026},
  source     = {Migration Appendix},
  section    = {Review Shelf A},
  supplement = {Plate 4}
}

@misc{source-title-alias,
  author       = {Ng, Nia},
  title        = {Source Title Alias Packet},
  year         = {2025},
  source-title = {Archive Guide}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $locator = $items['source-locator'];
        $alias = $items['source-title-alias'];

        $t->same('Migration Appendix', $locator['source']);
        $t->same('Review Shelf A', $locator['section']);
        $t->same('Plate 4', $locator['supplement']);
        $t->same('Archive Guide', $alias['source']);
        $t->same('Archive Guide', $alias['rawBibtex']['fields']['source-title']);
        $t->same('Pat Roe. Source Locator Packet. 2026. Source: Migration Appendix. Section: Review Shelf A. Supplement: Plate 4.', $processor->renderBibliographyText($locator));

        $document = (new MarkdownReader())->read('Source locator review cites @source-locator and [@source-title-alias].');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $markdown = (new MarkdownWriter())->write($bibliographyDocument);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same('Migration Appendix', $handoff['bibliography']->children[0]->attr('cslItem')['source'] ?? null);
        $t->same('Archive Guide', $handoff['bibliography']->children[1]->attr('cslItem')['source'] ?? null);
        $t->contains('Source: Migration Appendix', $markdown);
        $t->contains('Section: Review Shelf A', $blocks);
        $t->contains('Source: Archive Guide', $blocks);
    },
    'carries archive collection and call number aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@misc{compact-shelfmark,
  author            = {{Archive Catalog Desk}},
  title             = {Compact Archive Shelfmark Packet},
  date              = {2026},
  archive           = {City Archive},
  archiveCollection = {Migration Papers},
  archiveLocation   = {Box 4 Folder 2},
  shelfmark         = {MS 42 Box 4}
}

@book{library-manual,
  author    = {Lee, Lin},
  title     = {Reading Room Manual},
  date      = {2025},
  publisher = {Review Press},
  library   = {Reading Room Shelf B/12},
  url       = {https://example.test/reading-room-manual}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $compact = $items['compact-shelfmark'];
        $manual = $items['library-manual'];

        $t->same('City Archive', $compact['archive']);
        $t->same('Migration Papers', $compact['archive-collection']);
        $t->same('Box 4 Folder 2', $compact['archive_location']);
        $t->same('City Archive:Migration Papers:Box 4 Folder 2', $compact['archive-summary']);
        $t->same('MS 42 Box 4', $compact['call-number']);
        $t->same('Reading Room Shelf B/12', $manual['call-number']);
        $t->same('Archive Catalog Desk. Compact Archive Shelfmark Packet. 2026. Call number: MS 42 Box 4.', $processor->renderBibliographyText($compact));
        $t->same('Lin Lee. Reading Room Manual. Review Press. 2025. Call number: Reading Room Shelf B/12. https://example.test/reading-room-manual.', $processor->renderBibliographyText($manual));

        $document = (new MarkdownReader())->read('Archive review cites @compact-shelfmark and [@library-manual].');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $markdown = (new MarkdownWriter())->write($bibliographyDocument);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same('MS 42 Box 4', $handoff['bibliography']->children[0]->attr('cslItem')['call-number'] ?? null);
        $t->same('Reading Room Shelf B/12', $handoff['bibliography']->children[1]->attr('cslItem')['call-number'] ?? null);
        $t->contains('Call number: MS 42 Box 4', $markdown);
        $t->contains('Call number: Reading Room Shelf B/12', $blocks);
    },
    'carries biblatex thesis school and type aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@phdthesis{doctoral-review,
  author = {Smith, Ada},
  title  = {Doctoral Import Study},
  school = {Migration University},
  type   = {Doctoral dissertation},
  date   = {2026},
  url    = {https://example.test/doctoral-review}
}

@mathesis{masters-review,
  author = {Ng, Nia},
  title  = {Masters Import Study},
  school = {Source University},
  date   = {2025}
}

@thesis{explicit-thesis,
  author     = {Roe, Pat},
  title      = {Explicit Thesis Packet},
  institution = {Archive Institute},
  thesistype = {Licentiate thesis},
  year       = {2024}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $doctoral = $items['doctoral-review'];
        $masters = $items['masters-review'];
        $explicit = $items['explicit-thesis'];

        $t->same(['doctoral-review', 'masters-review', 'explicit-thesis'], array_keys($items));
        $t->same('thesis', $doctoral['type']);
        $t->same('Migration University', $doctoral['publisher']);
        $t->same('Doctoral dissertation', $doctoral['thesis-type']);
        $t->same('Doctoral dissertation', $doctoral['genre']);
        $t->same('Migration University', $doctoral['rawBibtex']['fields']['school']);
        $t->same('thesis', $masters['type']);
        $t->same('mathesis', $masters['thesis-type']);
        $t->same('Source University', $masters['publisher']);
        $t->same('Licentiate thesis', $explicit['thesis-type']);
        $t->same('Archive Institute', $explicit['publisher']);
        $t->same('Licentiate thesis', $explicit['rawBibtex']['fields']['thesistype']);
        $t->same('Ada Smith. Doctoral Import Study. Migration University. 2026. Thesis type: Doctoral dissertation. https://example.test/doctoral-review.', $processor->renderBibliographyText($doctoral));
        $t->same('Nia Ng. Masters Import Study. Source University. 2025. Thesis type: mathesis.', $processor->renderBibliographyText($masters));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Legacy BibLaTeX Thesis Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-thesis-alias-review</id>
    <updated>2026-06-15T09:50:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="school"/>
        <text variable="thesis-type"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="school"/>
      <text variable="thesistype"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Thesis Alias Review', $summary['title'] ?? null);
        $t->same('thesis-type', $summary['citationRendering'][0]['children'][2]['variable'] ?? null);
        $t->same('(Smith | Migration University | Doctoral dissertation; Ng | Source University | mathesis)', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'doctoral-review', 'text' => '[@doctoral-review]']),
            new AstNode('citation', ['id' => 'masters-review', 'text' => '[@masters-review]']),
        ]));
        $t->same('Explicit Thesis Packet :: Archive Institute :: Licentiate thesis', $styled->renderBibliographyEntry('explicit-thesis'));

        $document = (new MarkdownReader())->read('Thesis aliases [@doctoral-review; @masters-review; @explicit-thesis] stay visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['doctoral-review', 'masters-review', 'explicit-thesis'], $handoff['citedKeys']);
        $t->same('Doctoral dissertation', $handoff['bibliography']->children[0]->attr('cslItem')['thesis-type'] ?? null);
        $t->contains('<p>Thesis aliases (Smith | Migration University | Doctoral dissertation; Ng | Source University | mathesis; Roe | Archive Institute | Licentiate thesis) stay visible.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Doctoral Import Study :: Migration University :: Doctoral dissertation</dd>', $blocks);
        $t->contains('<dt>Roe 2024</dt><dd>Explicit Thesis Packet :: Archive Institute :: Licentiate thesis</dd>', $blocks);
    },
    'carries biblatex review title hierarchy aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@review{review-handoff,
  author            = {Critic, Casey},
  title             = {Legacy Review Packet},
  reviewed-title    = {Source Manual},
  reviewed-subtitle = {Field Appendix},
  reviewed-genre    = {migration handbook},
  maintitle         = {Collected Review Set},
  mainsubtitle      = {Legacy Volume},
  maintitleaddon    = {archive set},
  volume-title      = {Volume Packet},
  volume-subtitle   = {Review Notes},
  short-volume-title = {Vol. Pkt.},
  parttitle         = {Part Source},
  partsubtitle      = {Chapter Notes},
  issue-title       = {Special Issue},
  issue-subtitle    = {Audit Week},
  issue-title-addon = {guest-edited dossier},
  journaltitle      = {Review Journal},
  volume            = {7},
  number            = {2},
  date              = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['review-handoff'];

        $t->same('article', $item['type']);
        $t->same('Source Manual: Field Appendix', $item['reviewed-title']);
        $t->same('migration handbook', $item['reviewed-genre']);
        $t->same('Collected Review Set: Legacy Volume', $item['main-title']);
        $t->same('archive set', $item['main-title-addon']);
        $t->same('Volume Packet: Review Notes', $item['volume-title']);
        $t->same('Vol. Pkt.', $item['volume-title-short']);
        $t->same('Part Source: Chapter Notes', $item['part-title']);
        $t->same('Special Issue: Audit Week', $item['issue-title']);
        $t->same('guest-edited dossier', $item['issue-title-addon']);
        $t->same('Source Manual', $item['rawBibtex']['fields']['reviewed-title']);
        $t->same('Review Notes', $item['rawBibtex']['fields']['volume-subtitle']);
        $t->same('Casey Critic. Legacy Review Packet. Review Journal 7(2). Issue title: Special Issue: Audit Week. Issue title addendum: guest-edited dossier. 2026. Reviewed title: Source Manual: Field Appendix. Reviewed genre: migration handbook. Main title: Collected Review Set: Legacy Volume. Main title addendum: archive set. Volume title: Volume Packet: Review Notes. Volume title abbreviation: Vol. Pkt. Part title: Part Source: Chapter Notes.', $processor->renderBibliographyText($item));

        $document = (new MarkdownReader())->read('Review hierarchy [@review-handoff] stays visible.');
        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Review Title Hierarchy</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-review-title-hierarchy</id>
    <updated>2026-06-15T11:12:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="reviewed-title"/>
        <text variable="reviewed-genre"/>
        <text variable="main-title"/>
        <text variable="main-title-addon"/>
        <text variable="volume-title"/>
        <text variable="volume-title-short"/>
        <text variable="part-title"/>
        <text variable="issue-title"/>
        <text variable="issue-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="reviewed-title"/>
      <text variable="reviewed-genre"/>
      <text variable="main-title"/>
      <text variable="main-title-addon"/>
      <text variable="volume-title"/>
      <text variable="volume-title-short"/>
      <text variable="part-title"/>
      <text variable="issue-title"/>
      <text variable="issue-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Review Title Hierarchy', $summary['title'] ?? null);
        $t->same('reviewed-title', $summary['citationRendering'][0]['children'][1]['variable'] ?? null);
        $t->same('[Critic | Source Manual: Field Appendix | migration handbook | Collected Review Set: Legacy Volume | archive set | Volume Packet: Review Notes | Vol. Pkt. | Part Source: Chapter Notes | Special Issue: Audit Week | guest-edited dossier]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'review-handoff', 'text' => '[@review-handoff]']),
        ]));
        $t->same('Legacy Review Packet :: Source Manual: Field Appendix :: migration handbook :: Collected Review Set: Legacy Volume :: archive set :: Volume Packet: Review Notes :: Vol. Pkt. :: Part Source: Chapter Notes :: Special Issue: Audit Week :: guest-edited dossier', $styled->renderBibliographyEntry('review-handoff'));

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Review hierarchy [Critic | Source Manual: Field Appendix | migration handbook | Collected Review Set: Legacy Volume | archive set | Volume Packet: Review Notes | Vol. Pkt. | Part Source: Chapter Notes | Special Issue: Audit Week | guest-edited dossier] stays visible.</p>', $blocks);
        $t->contains('<dt>Critic 2026</dt><dd>Legacy Review Packet :: Source Manual: Field Appendix :: migration handbook :: Collected Review Set: Legacy Volume :: archive set :: Volume Packet: Review Notes :: Vol. Pkt. :: Part Source: Chapter Notes :: Special Issue: Audit Week :: guest-edited dossier</dd>', $blocks);
    },
    'carries biblatex reviewed references dimensions and scale in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@review{legacy-review-physical,
  author       = {Roe, Pat},
  title        = {Legacy Review Metadata Packet},
  reviewtitle  = {Imported Source Atlas},
  references   = {Smith 2024, pp. 12-18},
  dimensions   = {24 x 32 cm},
  scale        = {1:50000},
  date         = {2026},
  journaltitle = {Journal of Source Imports}
}

@misc{legacy-dimension-alias,
  author         = {Ng, Nia},
  title          = {Legacy Dimension Alias Packet},
  reviewed-title = {Compact Source Atlas},
  references     = {Archive ref 42},
  dimension      = {A4},
  scale          = {1:2500},
  date           = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $physical = $items['legacy-review-physical'];
        $alias = $items['legacy-dimension-alias'];

        $t->same('Imported Source Atlas', $physical['reviewed-title']);
        $t->same('Smith 2024, pp. 12-18', $physical['references']);
        $t->same('24 x 32 cm', $physical['dimensions']);
        $t->same('1:50000', $physical['scale']);
        $t->same('Compact Source Atlas', $alias['reviewed-title']);
        $t->same('Archive ref 42', $alias['references']);
        $t->same('A4', $alias['dimensions']);
        $t->same('1:2500', $alias['scale']);
        $t->same('A4', $alias['rawBibtex']['fields']['dimension']);
        $t->same(
            'Pat Roe. Legacy Review Metadata Packet. Journal of Source Imports. 2026. Reviewed title: Imported Source Atlas. References: Smith 2024, pp. 12-18. Dimensions: 24 x 32 cm. Scale: 1:50000.',
            $processor->renderBibliographyText($physical)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Physical Review Metadata</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-physical-review-metadata</id>
    <updated>2026-06-27T11:35:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="reviewed-title"/>
        <text variable="references"/>
        <text variable="dimensions"/>
        <text variable="scale"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="reviewed-title"/>
      <text variable="references"/>
      <text variable="dimensions"/>
      <text variable="scale"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Physical Review Metadata', $summary['title'] ?? null);
        $t->same('references', $summary['citationRendering'][0]['children'][2]['variable'] ?? null);
        $t->same('[Roe | Imported Source Atlas | Smith 2024, pp. 12-18 | 24 x 32 cm | 1:50000; Ng | Compact Source Atlas | Archive ref 42 | A4 | 1:2500]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-review-physical', 'text' => '[@legacy-review-physical]']),
            new AstNode('citation', ['id' => 'legacy-dimension-alias', 'text' => '[@legacy-dimension-alias]']),
        ]));
        $t->same('Legacy Review Metadata Packet :: Imported Source Atlas :: Smith 2024, pp. 12-18 :: 24 x 32 cm :: 1:50000', $styled->renderBibliographyEntry('legacy-review-physical'));
        $t->same('Legacy Dimension Alias Packet :: Compact Source Atlas :: Archive ref 42 :: A4 :: 1:2500', $styled->renderBibliographyEntry('legacy-dimension-alias'));

        $document = (new MarkdownReader())->read('Legacy physical review metadata cites @legacy-review-physical and [@legacy-dimension-alias].');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $markdown = (new MarkdownWriter())->write($bibliographyDocument);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-review-physical', 'legacy-dimension-alias'], $handoff['citedKeys']);
        $t->same('Smith 2024, pp. 12-18', $handoff['items'][0]['references'] ?? null);
        $t->same('A4', $handoff['bibliography']->children[1]->attr('cslItem')['dimensions'] ?? null);
        $t->contains('References: Smith 2024, pp. 12-18', $markdown);
        $t->contains('<p>Legacy physical review metadata cites Roe (2026) and [Ng | Compact Source Atlas | Archive ref 42 | A4 | 1:2500].</p>', $blocks);
        $t->contains('<dt>Roe 2026</dt><dd>Legacy Review Metadata Packet :: Imported Source Atlas :: Smith 2024, pp. 12-18 :: 24 x 32 cm :: 1:50000</dd>', $blocks);
    },
    'inherits legacy biblatex crossref and xdata metadata into csl items' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@xdata{shared-review-source,
  publisher = {Review Press},
  location  = {Portland},
  rights    = {Internal review only},
  keywords  = {source packet; inheritance}
}

@proceedings{review-proceedings,
  title      = {Source Review Proceedings},
  subtitle   = {Package Track},
  year       = {2026},
  eventtitle = {Open Source Review Summit},
  venue      = {Berlin},
  xdata      = {shared-review-source}
}

@inproceedings{crossref-paper,
  author   = {Ng, Nia},
  title    = {Packet Audit Trails},
  pages    = {12--18},
  crossref = {review-proceedings}
}

@periodical{journal-parent,
  title    = {Journal of Import Packets},
  subtitle = {Audit Notes},
  year     = {2025}
}

@article{crossref-article,
  author   = {Roe, Pat},
  title    = {Journal Child Packet},
  pages    = {7--9},
  crossref = {journal-parent}
}

@online{xdata-child,
  author = {Desk, Archive},
  title  = {Inherited Source Packet},
  url    = {https://example.test/source-packet},
  xdata  = {shared-review-source}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $paper = $items['crossref-paper'];
        $article = $items['crossref-article'];
        $xdataChild = $items['xdata-child'];

        $t->same('paper-conference', $paper['type']);
        $t->same('Source Review Proceedings: Package Track', $paper['container-title']);
        $t->same('Open Source Review Summit', $paper['event']);
        $t->same('Berlin', $paper['event-place']);
        $t->same('Review Press', $paper['publisher']);
        $t->same('Portland', $paper['publisher-place']);
        $t->same('Internal review only', $paper['rights']);
        $t->same(['source packet', 'inheritance'], $paper['keyword']);
        $t->same([2026], $paper['issued']['date-parts'][0]);
        $t->same('12-18', $paper['page']);
        $t->same(['shared-review-source'], $paper['xdataKeys']);
        $t->same('shared-review-source', $paper['xdataSummary']);
        $t->same(['review-proceedings'], $paper['crossrefKeys']);
        $t->same('Source Review Proceedings: Package Track', $paper['crossrefItems'][0]['title'] ?? null);
        $t->same([2026], $paper['crossrefItems'][0]['issued']['date-parts'][0] ?? null);
        $t->same('Source Review Proceedings: Package Track (2026)', $paper['crossrefSummary']);
        $t->same('review-proceedings', $paper['rawBibtex']['fields']['crossref']);
        $t->same('Source Review Proceedings', $paper['rawBibtex']['fields']['booktitle']);
        $t->same('Package Track', $paper['rawBibtex']['fields']['booksubtitle']);
        $t->same('Journal of Import Packets: Audit Notes', $article['container-title']);
        $t->same([2025], $article['issued']['date-parts'][0]);
        $t->same('Review Press', $xdataChild['publisher']);
        $t->same('Portland', $xdataChild['publisher-place']);
        $t->same('Internal review only', $xdataChild['rights']);
        $t->same(['shared-review-source'], $xdataChild['xdataKeys']);
        $t->same(true, $xdataChild['xdataItems'][0]['dataOnly'] ?? null);
        $t->same('shared-review-source', $xdataChild['xdataSummary']);
        $t->same('shared-review-source', $xdataChild['rawBibtex']['fields']['xdata']);
        $t->same(
            'Nia Ng. Packet Audit Trails. Source Review Proceedings: Package Track. 2026. 12-18. Rights: Internal review only. BibLaTeX crossref parent: Source Review Proceedings: Package Track (2026). BibLaTeX xdata packets: shared-review-source.',
            $processor->renderBibliographyText($paper)
        );
        $t->same(
            'Archive Desk. Inherited Source Packet. Review Press. Rights: Internal review only. BibLaTeX xdata packets: shared-review-source. https://example.test/source-packet.',
            $processor->renderBibliographyText($xdataChild)
        );

        $document = (new MarkdownReader())->read('Inherited metadata cites @crossref-paper and [@xdata-child].');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['crossref-paper', 'xdata-child'], $handoff['citedKeys']);
        $t->same([], $handoff['missingKeys']);
        $t->same('Source Review Proceedings: Package Track', $handoff['items'][0]['container-title']);
        $t->same('Review Press', $handoff['items'][1]['publisher']);
        $t->contains('<dt>crossref-paper</dt><dd>Nia Ng. Packet Audit Trails. Source Review Proceedings: Package Track. 2026. 12-18. Rights: Internal review only. BibLaTeX crossref parent: Source Review Proceedings: Package Track (2026). BibLaTeX xdata packets: shared-review-source.</dd>', $blocks);
        $t->contains('<dt>xdata-child</dt><dd>Archive Desk. Inherited Source Packet. Review Press. Rights: Internal review only. BibLaTeX xdata packets: shared-review-source. https://example.test/source-packet.</dd>', $blocks);
    },
    'carries biblatex xdata and entryset provenance in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@xdata{review-policy,
  title = {Shared Review Policy},
  date  = {2026-06-05},
  note  = {metadata only}
}

@inproceedings{audit-paper,
  author    = {Ng, Nia},
  title     = {Packet Audit Trails},
  booktitle = {Migration Futures Conference},
  date      = {2026},
  options   = {dataonly}
}

@online{archived-site,
  author = {{Archive Team}},
  title  = {Archive Site},
  date   = {2026-05-31},
  url    = {https://example.test/archive}
}

@set{review-set,
  title    = {Review Source Set},
  date     = {2026},
  entryset = {audit-paper, archived-site, missing-source}
}

@online{xdata-provenance,
  author = {Roe, Pat},
  title  = {Xdata Source Packet},
  date   = {2026-06-07},
  url    = {https://example.test/xdata},
  xdata  = {review-policy, missing-policy}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $set = $items['review-set'];
        $xdata = $items['xdata-provenance'];

        $t->same('entry', $set['type']);
        $t->same(['audit-paper', 'archived-site', 'missing-source'], $set['entrySet']);
        $t->same('audit-paper', $set['entrySetItems'][0]['id'] ?? null);
        $t->same(true, $set['entrySetItems'][0]['dataOnly'] ?? null);
        $t->same('Archive', $set['entrySetItems'][1]['author'][0]['given'] ?? null);
        $t->same('Team', $set['entrySetItems'][1]['author'][0]['family'] ?? null);
        $t->same(['missing-source'], $set['missingEntrySetKeys']);
        $t->same('Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source', $set['entrySetSummary']);
        $t->same(['review-policy', 'missing-policy'], $xdata['xdataKeys']);
        $t->same('Shared Review Policy', $xdata['xdataItems'][0]['title'] ?? null);
        $t->same(true, $xdata['xdataItems'][0]['dataOnly'] ?? null);
        $t->same(['missing-policy'], $xdata['missingXdataKeys']);
        $t->same('Shared Review Policy (2026-06-05); missing: missing-policy', $xdata['xdataSummary']);
        $t->same('review-policy, missing-policy', $xdata['rawBibtex']['fields']['xdata']);
        $t->same(
            'Review Source Set. 2026. BibLaTeX entry set: Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source.',
            $processor->renderBibliographyText($set)
        );
        $t->same(
            'Pat Roe. Xdata Source Packet. 2026. BibLaTeX xdata packets: Shared Review Policy (2026-06-05); missing: missing-policy. https://example.test/xdata.',
            $processor->renderBibliographyText($xdata)
        );

        $document = (new MarkdownReader())->read('Set review cites @review-set and [@xdata-provenance].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['review-set', 'xdata-provenance'], $handoff['citedKeys']);
        $t->same('Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source', $handoff['items'][0]['entrySetSummary'] ?? null);
        $t->same('Shared Review Policy (2026-06-05); missing: missing-policy', $handoff['items'][1]['xdataSummary'] ?? null);
        $t->contains('BibLaTeX entry set: Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source', $blocks);
        $t->contains('BibLaTeX xdata packets: Shared Review Policy (2026-06-05); missing: missing-policy', $blocks);
    },
    'carries biblatex citation aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{canonical-source,
  ids    = {legacy-source, alt-source},
  author = {Ng, Nia},
  title  = {Alias Source Manual},
  date   = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['canonical-source'];

        $t->same(['canonical-source'], array_keys($items));
        $t->same(['legacy-source', 'alt-source'], $item['citation-aliases']);
        $t->same('Nia Ng. Alias Source Manual. Citation aliases: legacy-source; alt-source. 2026.', $processor->renderBibliographyText($item));

        $document = (new MarkdownReader())->read('Alias review cites @legacy-source, [@alt-source], and @canonical-source.');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $markdown = (new MarkdownWriter())->write($bibliographyDocument);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['legacy-source', 'alt-source', 'canonical-source'], $handoff['citedKeys']);
        $t->same([], $handoff['missingKeys']);
        $t->same(['canonical-source'], array_map(static fn (array $item): string => (string) $item['id'], $handoff['items']));
        $t->same(['legacy-source', 'alt-source'], $handoff['bibliography']->children[0]->attr('cslItem')['citation-aliases'] ?? null);
        $t->contains('Citation aliases: legacy-source; alt-source', $markdown);
        $t->contains('<dt>canonical-source</dt>', $blocks);
        $t->contains('Citation aliases: legacy-source; alt-source', $blocks);

        $styled = CitationCslProcessor::fromItems(array_values($items));
        $t->same('Alias Source Manual', $styled->item('legacy-source')['title'] ?? null);
        $t->same('(Ng 2026)', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'alt-source', 'text' => '[@alt-source]']),
        ]));
    },
    'carries biblatex custom fields lists and names in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@misc{legacy-custom-review,
  author = {Curator, Eli},
  title  = {Legacy Custom Packet},
  date   = {2026},
  usera  = {migration batch 42},
  userf  = {reviewer escalation},
  verba  = {wp shortcode [gallery]},
  lista  = {migration batch and review desk},
  listc  = {archive queue and internal QA},
  namea  = {Roe, Pat and Ng, Nia},
  namec  = {Archive, Desk}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-custom-review'];

        $t->same([
            'usera' => 'migration batch 42',
            'userf' => 'reviewer escalation',
            'verba' => 'wp shortcode [gallery]',
        ], $item['biblatex-custom-fields']);
        $t->same([
            'lista' => ['migration batch', 'review desk'],
            'listc' => ['archive queue', 'internal QA'],
        ], $item['biblatex-custom-lists']);
        $t->same('Roe', $item['biblatex-custom-names']['namea'][0]['family'] ?? null);
        $t->same('Pat', $item['biblatex-custom-names']['namea'][0]['given'] ?? null);
        $t->same('Ng', $item['biblatex-custom-names']['namea'][1]['family'] ?? null);
        $t->same('Archive, Desk', $item['rawBibtex']['fields']['namec']);
        $t->same(
            'Eli Curator. Legacy Custom Packet. 2026. BibLaTeX custom fields: usera: migration batch 42; userf: reviewer escalation; verba: wp shortcode [gallery]. BibLaTeX custom lists: lista: migration batch; review desk; listc: archive queue; internal QA. BibLaTeX custom names: namea: Roe, Pat; Ng, Nia; namec: Archive, Desk.',
            $processor->renderBibliographyText($item)
        );

        $document = (new MarkdownReader())->read('Legacy custom fields [@legacy-custom-review] stay visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-custom-review'], $handoff['citedKeys']);
        $t->same('migration batch 42', $handoff['items'][0]['biblatex-custom-fields']['usera'] ?? null);
        $t->same(['archive queue', 'internal QA'], $handoff['bibliography']->children[0]->attr('cslItem')['biblatex-custom-lists']['listc'] ?? null);
        $t->contains('BibLaTeX custom fields: usera: migration batch 42', $blocks);
        $t->contains('BibLaTeX custom names: namea: Roe, Pat; Ng, Nia; namec: Archive, Desk', $blocks);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Custom Field Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-custom-field-review</id>
    <updated>2026-06-27T00:30:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="usera"/>
        <text variable="lista"/>
        <names variable="namea"/>
        <names variable="namec"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="biblatex-custom-field-summary"/>
      <text variable="biblatex-custom-list-summary"/>
      <text variable="biblatex-custom-name-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Custom Field Review', $summary['title'] ?? null);
        $t->same('[Curator | migration batch 42 | migration batch; review desk | Roe and Ng | Archive]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-custom-review', 'text' => '[@legacy-custom-review]']),
        ]));
        $t->same(
            'Legacy Custom Packet :: usera: migration batch 42; userf: reviewer escalation; verba: wp shortcode [gallery] :: lista: migration batch; review desk; listc: archive queue; internal QA :: namea: Roe, Pat; Ng, Nia; namec: Archive, Desk',
            $styled->renderBibliographyEntry('legacy-custom-review')
        );

        $styledBlocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Legacy custom fields [Curator | migration batch 42 | migration batch; review desk | Roe and Ng | Archive] stay visible.</p>', $styledBlocks);
        $t->contains('<dt>Curator 2026</dt><dd>Legacy Custom Packet :: usera: migration batch 42; userf: reviewer escalation; verba: wp shortcode [gallery] :: lista: migration batch; review desk; listc: archive queue; internal QA :: namea: Roe, Pat; Ng, Nia; namec: Archive, Desk</dd>', $styledBlocks);
    },
    'carries biblatex name annotations and name addendum in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{legacy-name-annotations,
  author     = {Smith, Ada and Ng, Nia},
  author+an  = {1=primary source author; 2:family=family name verified},
  editor     = {Curator, Eli},
  editor+an:role = {1=review editor},
  title      = {Name Annotation Packet},
  date       = {2026},
  publisher  = {Review Press},
  nameaddon  = {Imported source names verified}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-name-annotations'];

        $t->same('Imported source names verified', $item['name-addon']);
        $t->same([['part' => 'name', 'value' => 'primary source author']], $item['author'][0]['annotations'] ?? null);
        $t->same([['part' => 'family', 'value' => 'family name verified']], $item['author'][1]['annotations'] ?? null);
        $t->same([['part' => 'role', 'value' => 'review editor']], $item['editor'][0]['annotations'] ?? null);
        $t->same('1=primary source author; 2:family=family name verified', $item['rawBibtex']['fields']['author+an']);
        $t->same('1=review editor', $item['rawBibtex']['fields']['editor+an:role']);
        $t->same(
            'Ada Smith and Nia Ng. Name Annotation Packet. Review Press. 2026. Name addendum: Imported source names verified. Name annotations: Author 1: primary source author; Author 2 family: family name verified; Editor 1 role: review editor.',
            $processor->renderBibliographyText($item)
        );

        $document = (new MarkdownReader())->read('Name metadata source @legacy-name-annotations stays reviewable.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-name-annotations'], $handoff['citedKeys']);
        $t->same('primary source author', $handoff['items'][0]['author'][0]['annotations'][0]['value'] ?? null);
        $t->same('family', $handoff['bibliography']->children[0]->attr('cslItem')['author'][1]['annotations'][0]['part'] ?? null);
        $t->contains('Name addendum: Imported source names verified', $blocks);
        $t->contains('Name annotations: Author 1: primary source author; Author 2 family: family name verified; Editor 1 role: review editor', $blocks);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="name-addon"/>
        <text variable="name-annotation-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="name-addon"/>
      <text variable="name-annotation-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('legacy-name-annotations');
        $t->same('Imported source names verified', $styledItem['nameAddon'] ?? null);
        $t->same('primary source author', $styledItem['authors'][0]['annotations'][0]['value'] ?? null);
        $t->same('review editor', $styledItem['editors'][0]['annotations'][0]['value'] ?? null);
        $t->same('[Smith and Ng | Imported source names verified | Author 1: primary source author; Author 2 family: family name verified; Editor 1 role: review editor]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-name-annotations', 'text' => '[@legacy-name-annotations]']),
        ]));
        $t->same('Name Annotation Packet :: Imported source names verified :: Author 1: primary source author; Author 2 family: family name verified; Editor 1 role: review editor', $styled->renderBibliographyEntry('legacy-name-annotations'));
    },
    'carries biblatex entry options reference context and field annotations in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{legacy-review-context,
  author     = {Smith, Ada},
  title      = {Legacy Review Context},
  title+an   = {=title verified; source=OCR headline normalized},
  publisher  = {Review Press},
  date       = {2026},
  url        = {https://example.test/legacy-review-context},
  url+an:source = {=archived before WordPress import},
  langid     = {spanish},
  langidopts = {variant=mexican, hyphenation=traditional},
  options    = {skipbib=false, useprefix=true, maxnames=3},
  refsection = {2},
  refsegment = {migration-import},
  gender     = {feminine}
}

@online{legacy-suppressed-context,
  author     = {{Archive Desk}},
  title      = {Suppressed Context Snapshot},
  date       = {2025},
  url        = {https://example.test/suppressed-context},
  options    = {skipbib=true, dashed=false},
  refsegment = {media-audit}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $context = $items['legacy-review-context'];
        $suppressed = $items['legacy-suppressed-context'];

        $t->same(['skipbib=false', 'useprefix=true', 'maxnames=3'], $context['biblatex-options']);
        $t->same(['variant=mexican', 'hyphenation=traditional'], $context['biblatex-language-options']);
        $t->same('2', $context['biblatex-refsection']);
        $t->same('migration-import', $context['biblatex-refsegment']);
        $t->same('feminine', $context['gender']);
        $t->same([
            'title' => [
                ['name' => 'default', 'value' => 'title verified'],
                ['name' => 'source', 'value' => 'OCR headline normalized'],
            ],
            'url' => [
                ['name' => 'source', 'value' => 'archived before WordPress import'],
            ],
        ], $context['biblatex-field-annotations']);
        $t->same('=title verified; source=OCR headline normalized', $context['rawBibtex']['fields']['title+an']);
        $t->same('=archived before WordPress import', $context['rawBibtex']['fields']['url+an:source']);
        $t->same(['skipbib=true', 'dashed=false'], $suppressed['biblatex-options']);
        $t->same('media-audit', $suppressed['biblatex-refsegment']);
        $t->contains('BibLaTeX field annotations: title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import', $processor->renderBibliographyText($context));
        $t->contains('BibLaTeX options: skipbib=false; useprefix=true; maxnames=3', $processor->renderBibliographyText($context));
        $t->contains('BibLaTeX language options: variant=mexican; hyphenation=traditional', $processor->renderBibliographyText($context));
        $t->contains('BibLaTeX reference context: refsection 2; refsegment migration-import', $processor->renderBibliographyText($context));
        $t->contains('BibLaTeX gender: feminine', $processor->renderBibliographyText($context));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Context Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-context-review</id>
    <updated>2026-06-27T01:08:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="biblatex-options"/>
        <text variable="biblatex-language-options"/>
        <text variable="refsection"/>
        <text variable="refsegment"/>
        <text variable="biblatex-field-annotation-summary"/>
        <text variable="gender"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="biblatex-options"/>
      <text variable="biblatex-language-option-summary"/>
      <text variable="reference-context"/>
      <text variable="biblatex-field-annotations"/>
      <text variable="biblatex-gender"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('legacy-review-context');
        $t->same(['skipbib=false', 'useprefix=true', 'maxnames=3'], $normalized['biblatexOptions'] ?? null);
        $t->same('variant=mexican; hyphenation=traditional', $normalized['biblatexLanguageOptionSummary'] ?? null);
        $t->same('refsection 2; refsegment migration-import', $normalized['biblatexReferenceContextSummary'] ?? null);
        $t->same('title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import', $normalized['biblatexFieldAnnotationSummary'] ?? null);
        $t->same('feminine', $normalized['biblatexGender'] ?? null);
        $t->same('[Smith | skipbib=false, useprefix=true, maxnames=3 | variant=mexican, hyphenation=traditional | 2 | migration-import | title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import | feminine]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-review-context', 'text' => '[@legacy-review-context]']),
        ]));
        $t->same('Legacy Review Context :: skipbib=false, useprefix=true, maxnames=3 :: variant=mexican; hyphenation=traditional :: refsection 2; refsegment migration-import :: title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import :: feminine', $styled->renderBibliographyEntry('legacy-review-context'));

        $document = (new MarkdownReader())->read('Legacy context [@legacy-review-context; @legacy-suppressed-context] keeps review metadata visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('<p>Legacy context [Smith | skipbib=false, useprefix=true, maxnames=3 | variant=mexican, hyphenation=traditional | 2 | migration-import | title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import | feminine; Desk | skipbib=true, dashed=false | media-audit] keeps review metadata visible.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Legacy Review Context :: skipbib=false, useprefix=true, maxnames=3 :: variant=mexican; hyphenation=traditional :: refsection 2; refsegment migration-import :: title default: title verified; title source: OCR headline normalized; url source: archived before WordPress import :: feminine</dd>', $blocks);
        $t->true(!str_contains($blocks, '<dt>Desk 2025</dt>'), 'skipbib=true legacy BibLaTeX entries must stay out of appended bibliographies');
    },
    'carries biblatex issue title aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-issue-title,
  author          = {Doe, Jane},
  title           = {Source Packet Study},
  journaltitle    = {Journal of Imports},
  issuetitle      = {Migration Special Issue},
  issuesubtitle   = {Import Desk Reports},
  issuetitleaddon = {Editorial packet supplement},
  date            = {2026},
  pages           = {30--35}
}

@article{legacy-issue-text,
  author            = {Roe, Pat},
  title             = {Issue Text Packet},
  journal           = {Migration Notes},
  issue-title-text  = {Hyphen Issue Text},
  issue-subtitle    = {Source Reports},
  issue-title-addon = {queue note},
  year              = {2025},
  pages             = {7--9}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $special = $items['legacy-issue-title'];
        $hyphen = $items['legacy-issue-text'];

        $t->same('article-journal', $special['type']);
        $t->same('Migration Special Issue: Import Desk Reports', $special['issue-title']);
        $t->same('Editorial packet supplement', $special['issue-title-addon']);
        $t->same('Hyphen Issue Text: Source Reports', $hyphen['issue-title']);
        $t->same('queue note', $hyphen['issue-title-addon']);
        $t->same('Hyphen Issue Text', $hyphen['rawBibtex']['fields']['issue-title-text']);
        $t->same(
            'Jane Doe. Source Packet Study. Journal of Imports. Issue title: Migration Special Issue: Import Desk Reports. Issue title addendum: Editorial packet supplement. 2026. 30-35.',
            $processor->renderBibliographyText($special)
        );

        $document = (new MarkdownReader())->read('Special issue @legacy-issue-title and archive issue [@legacy-issue-text] keep issue metadata visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $t->same(['legacy-issue-title', 'legacy-issue-text'], $handoff['citedKeys']);
        $t->same('Migration Special Issue: Import Desk Reports', $handoff['bibliography']->children[0]->attr('cslItem')['issue-title'] ?? null);
        $t->same('queue note', $handoff['bibliography']->children[1]->attr('cslItem')['issue-title-addon'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Issue Title Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-issue-title-review</id>
    <updated>2026-06-15T11:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="issue-title"/>
        <text variable="issue-title-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="issue-title"/>
      <text variable="issue-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Issue Title Review', $summary['title'] ?? null);
        $t->same('[Doe | Migration Special Issue: Import Desk Reports | Editorial packet supplement; Roe | Hyphen Issue Text: Source Reports | queue note]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-issue-title', 'text' => '[@legacy-issue-title]']),
            new AstNode('citation', ['id' => 'legacy-issue-text', 'text' => '[@legacy-issue-text]']),
        ]));
        $t->same('Source Packet Study :: Migration Special Issue: Import Desk Reports :: Editorial packet supplement', $styled->renderBibliographyEntry('legacy-issue-title'));
        $t->same('Issue Text Packet :: Hyphen Issue Text: Source Reports :: queue note', $styled->renderBibliographyEntry('legacy-issue-text'));

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Special issue Doe (2026) and archive issue [Roe | Hyphen Issue Text: Source Reports | queue note] keep issue metadata visible.</p>', $blocks);
        $t->contains('<dt>Doe 2026</dt><dd>Source Packet Study :: Migration Special Issue: Import Desk Reports :: Editorial packet supplement</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Issue Text Packet :: Hyphen Issue Text: Source Reports :: queue note</dd>', $blocks);
    },
    'carries biblatex shorthand sort and label metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{legacy-labels,
  author         = {Smith, Ada},
  title          = {Legacy Label Manual},
  date           = {2026},
  shorthand      = {LLM},
  shorthandintro = {cited as Legacy Label Manual},
  sortshorthand  = {010 legacy label},
  presort        = {aa},
  sortkey        = {900-smith},
  sortname       = {Archive Desk},
  sorttitle      = {Label Manual Legacy},
  sortyear       = {2025},
  labelprefix    = {WP},
  labelalpha     = {Smi26},
  labeltitle     = {legacy label},
  extraalpha     = {b}
}

@book{fallback-shorthand,
  title     = {Fallback Shorthand Manual},
  date      = {2025},
  shorthand = {FSH}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $labels = $items['legacy-labels'];
        $fallback = $items['fallback-shorthand'];

        $t->same('LLM', $labels['citation-label']);
        $t->same('LLM', $labels['shorthand']);
        $t->same('cited as Legacy Label Manual', $labels['shorthand-intro']);
        $t->same('010 legacy label', $labels['sort-shorthand']);
        $t->same('010 legacy label', $labels['shorthand-list-sort-key']);
        $t->same('aa', $labels['presort']);
        $t->same('900-smith', $labels['sort-key']);
        $t->same('Archive Desk', $labels['sort-name']);
        $t->same('Label Manual Legacy', $labels['sort-title']);
        $t->same('2025', $labels['sort-year']);
        $t->same('WP', $labels['label-prefix']);
        $t->same('Smi26', $labels['label-alpha']);
        $t->same('legacy label', $labels['label-title']);
        $t->same('b', $labels['extra-alpha']);
        $t->same('LLM', $labels['rawBibtex']['fields']['shorthand']);
        $t->same('010 legacy label', $labels['rawBibtex']['fields']['sortshorthand']);
        $t->same('FSH', $fallback['citation-label']);
        $t->same('FSH', $fallback['shorthand-list-sort-key']);
        $t->same(
            'Ada Smith. Legacy Label Manual. 2026. Citation label: LLM. Shorthand intro: cited as Legacy Label Manual. Sort shorthand: 010 legacy label. Presort: aa. Sort key: 900-smith. Label prefix: WP. Extra alpha: b.',
            $processor->renderBibliographyText($labels)
        );

        $document = (new MarkdownReader())->read('Legacy label source @legacy-labels and [@fallback-shorthand] keep shorthand review metadata.');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['legacy-labels', 'fallback-shorthand'], $handoff['citedKeys']);
        $t->same('010 legacy label', $handoff['bibliography']->children[0]->attr('cslItem')['shorthand-list-sort-key'] ?? null);
        $t->same('FSH', $handoff['bibliography']->children[1]->attr('cslItem')['shorthand-list-sort-key'] ?? null);
        $t->contains('Citation label: LLM', $blocks);
        $t->contains('Fallback Shorthand Manual', $blocks);
    },
    'carries biblatex index title aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{legacy-index-title,
  author         = {Smith, Ada},
  title          = {Legacy Index Manual},
  date           = {2026},
  indextitle     = {Manual, Legacy Index},
  indexsorttitle = {0001 legacy index manual}
}

@book{hyphen-index-title,
  author           = {Roe, Pat},
  title            = {Hyphen Index Packet},
  date             = {2025},
  index-title      = {Packet, Hyphen Index},
  index-sort-title = {0002 hyphen index packet}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $legacy = $items['legacy-index-title'];
        $hyphen = $items['hyphen-index-title'];

        $t->same('Manual, Legacy Index', $legacy['index-title']);
        $t->same('0001 legacy index manual', $legacy['index-sort-title']);
        $t->same('Packet, Hyphen Index', $hyphen['index-title']);
        $t->same('0002 hyphen index packet', $hyphen['index-sort-title']);
        $t->same('Manual, Legacy Index', $legacy['rawBibtex']['fields']['indextitle']);
        $t->same('0002 hyphen index packet', $hyphen['rawBibtex']['fields']['index-sort-title']);
        $t->same(
            'Ada Smith. Legacy Index Manual. 2026. Index title: Manual, Legacy Index. Index sort title: 0001 legacy index manual.',
            $processor->renderBibliographyText($legacy)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Index Title Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-index-title-review</id>
    <updated>2026-06-27T22:30:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="index-title"/>
        <text variable="index-sort-title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="index-title"/>
      <text variable="index-sort-title"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Index Title Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Manual, Legacy Index | 0001 legacy index manual; Packet, Hyphen Index | 0002 hyphen index packet]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-index-title', 'text' => '[@legacy-index-title]']),
            new AstNode('citation', ['id' => 'hyphen-index-title', 'text' => '[@hyphen-index-title]']),
        ]));
        $t->same('Legacy Index Manual :: Manual, Legacy Index :: 0001 legacy index manual', $styled->renderBibliographyEntry('legacy-index-title'));
        $t->same('Hyphen Index Packet :: Packet, Hyphen Index :: 0002 hyphen index packet', $styled->renderBibliographyEntry('hyphen-index-title'));

        $document = (new MarkdownReader())->read('Index titles [@legacy-index-title; @hyphen-index-title] keep sort labels visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-index-title', 'hyphen-index-title'], $handoff['citedKeys']);
        $t->same('Manual, Legacy Index', $handoff['bibliography']->children[0]->attr('cslItem')['index-title'] ?? null);
        $t->same('0002 hyphen index packet', $handoff['bibliography']->children[1]->attr('cslItem')['index-sort-title'] ?? null);
        $t->contains('<p>Index titles [Manual, Legacy Index | 0001 legacy index manual; Packet, Hyphen Index | 0002 hyphen index packet] keep sort labels visible.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Legacy Index Manual :: Manual, Legacy Index :: 0001 legacy index manual</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Hyphen Index Packet :: Packet, Hyphen Index :: 0002 hyphen index packet</dd>', $blocks);
    },
    'carries biblatex relation aliases and explicit shorthand list metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{relation-manual,
  author                = {Ng, Nia},
  title                 = {Relation Review Manual},
  date                  = {2026},
  ids                   = {legacy-relation, migrated-relation},
  shorthand             = {RRM},
  shorthandintro        = {cited as Relation Review Manual},
  sortshorthand         = {010 relation manual},
  listshorthand         = {005 explicit relation list},
  related               = {source-appendix, source-license},
  relatedtype           = {updated-by},
  relatedstring         = {Updated source},
  relatedoptions        = {dataonly; skipbib},
  crossref              = {source-proceedings}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['relation-manual'];

        $t->same(['legacy-relation', 'migrated-relation'], $item['citation-aliases']);
        $t->same('RRM', $item['shorthand']);
        $t->same('cited as Relation Review Manual', $item['shorthand-intro']);
        $t->same('010 relation manual', $item['sort-shorthand']);
        $t->same('005 explicit relation list', $item['shorthand-list-sort-key']);
        $t->same('source-appendix, source-license', $item['related']);
        $t->same('updated-by', $item['related-type']);
        $t->same('Updated source', $item['related-string']);
        $t->same('dataonly; skipbib', $item['related-options']);
        $t->same('source-proceedings', $item['xref']);

        $document = (new MarkdownReader())->read('Relation source [@legacy-relation] keeps relation handoff metadata.');
        $handoff = $processor->citationHandoff($document, $source);

        $t->same(['legacy-relation'], $handoff['citedKeys']);
        $t->same('relation-manual', $handoff['bibliography']->children[0]->attr('cslItem')['id'] ?? null);
        $t->same('005 explicit relation list', $handoff['bibliography']->children[0]->attr('cslItem')['shorthand-list-sort-key'] ?? null);
        $t->same('source-proceedings', $handoff['bibliography']->children[0]->attr('cslItem')['xref'] ?? null);
    },
    'carries biblatex related entry provenance in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{source-packet,
  author = {Roe, Pat},
  title  = {Source Packet},
  date   = {2025-04-01}
}

@book{license-packet,
  options = {dataonly},
  title   = {License Packet},
  date    = {2024}
}

@book{related-review,
  author         = {Mapper, Mia},
  title          = {Related Review Manual},
  date           = {2026},
  related        = {source-packet, license-packet, missing-related},
  relatedtype    = {reviewof},
  relatedstring  = {Reviews source packet},
  relatedoptions = {skipbib=true, dataonly=false}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['related-review'];

        $t->same(['source-packet', 'license-packet', 'missing-related'], $item['relatedKeys']);
        $t->same('Source Packet', $item['relatedItems'][0]['title'] ?? null);
        $t->same([2025, 4, 1], $item['relatedItems'][0]['issued']['date-parts'][0] ?? null);
        $t->same('license-packet', $item['relatedItems'][1]['id'] ?? null);
        $t->same(true, $item['relatedItems'][1]['dataOnly'] ?? null);
        $t->same(['missing-related'], $item['missingRelatedKeys']);
        $t->same('Source Packet (2025-04-01); License Packet (2024); missing: missing-related', $item['relatedSummary']);
        $t->same('reviewof', $item['related-type']);
        $t->same('Reviews source packet', $item['related-string']);
        $t->same('skipbib=true, dataonly=false', $item['related-options']);
        $t->same('source-packet, license-packet, missing-related', $item['rawBibtex']['fields']['related']);
        $t->same(
            'Mia Mapper. Related Review Manual. 2026. BibLaTeX related sources: Reviews source packet (reviewof): Source Packet (2025-04-01); License Packet (2024); missing: missing-related.',
            $processor->renderBibliographyText($item)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Related Entry Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-related-entry-review</id>
    <updated>2026-06-28T14:30:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="related-type"/>
        <text variable="related"/>
        <text variable="related-options"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="related-summary"/>
      <text variable="related-options"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('related-review');
        $t->same(['source-packet', 'license-packet', 'missing-related'], $styledItem['relatedKeys'] ?? null);
        $t->same(['skipbib=true', 'dataonly=false'], $styledItem['relatedOptions'] ?? null);
        $t->same('License Packet', $styledItem['relatedItems'][1]['title'] ?? null);
        $t->same('License Packet (2024)', $styledItem['relatedItems'][1]['display'] ?? null);
        $t->same('[Related Review Manual | reviewof | Source Packet (2025-04-01); License Packet (2024); missing: missing-related | skipbib=true, dataonly=false]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'related-review', 'text' => '[@related-review]']),
        ]));
        $t->same('Related Review Manual :: Reviews source packet (reviewof): Source Packet (2025-04-01); License Packet (2024); missing: missing-related :: skipbib=true, dataonly=false', $styled->renderBibliographyEntry('related-review'));

        $document = (new MarkdownReader())->read('Related legacy source [@related-review] stays reviewable.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['related-review'], $handoff['citedKeys']);
        $t->same('Source Packet (2025-04-01); License Packet (2024); missing: missing-related', $handoff['items'][0]['relatedSummary'] ?? null);
        $t->same(['missing-related'], $handoff['bibliography']->children[0]->attr('cslItem')['missingRelatedKeys'] ?? null);
        $t->contains('BibLaTeX related sources: Reviews source packet (reviewof): Source Packet (2025-04-01); License Packet (2024); missing: missing-related', $blocks);
    },
    'carries biblatex literal publisher language and event lists in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{literal-list-review,
  author        = {Ng, Nia},
  title         = {Literal List Review Manual},
  date          = {2026},
  publisher     = {Review Press and Archive Desk},
  location      = {New York and London},
  origpublisher = {Archivo Press and Migration Desk},
  origlocation  = {Madrid and Barcelona},
  origlanguage  = {spanish and basque},
  language      = {english and french},
  url           = {https://example.test/literal-list}
}

@proceedings{event-list-review,
  author     = {Curator, Eli},
  title      = {Event List Proceedings},
  eventtitle = {Import Review Summit},
  venue      = {Portland Convention Center and Remote Stream},
  date       = {2025},
  publisher  = {Migration Desk}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $literal = $items['literal-list-review'];
        $event = $items['event-list-review'];

        $t->same(['Review Press', 'Archive Desk'], $literal['publisher-list']);
        $t->same(['New York', 'London'], $literal['publisher-place-list']);
        $t->same(['Archivo Press', 'Migration Desk'], $literal['original-publisher-list']);
        $t->same(['Madrid', 'Barcelona'], $literal['original-publisher-place-list']);
        $t->same(['spanish', 'basque'], $literal['original-language-list']);
        $t->same(['english', 'french'], $literal['language-list']);
        $t->same(['Portland Convention Center', 'Remote Stream'], $event['event-place-list']);
        $t->same('New York and London', $literal['rawBibtex']['fields']['location']);
        $t->same('Portland Convention Center and Remote Stream', $event['rawBibtex']['fields']['venue']);
        $t->contains('Publisher list: Review Press; Archive Desk', $processor->renderBibliographyText($literal));
        $t->contains('Original languages: spanish; basque', $processor->renderBibliographyText($literal));

        $document = (new MarkdownReader())->read('Literal list review [@literal-list-review; @event-list-review] keeps literal lists visible.');
        $handoff = $processor->citationHandoff($document, $source);

        $t->same(['literal-list-review', 'event-list-review'], $handoff['citedKeys']);
        $t->same(['Review Press', 'Archive Desk'], $handoff['bibliography']->children[0]->attr('cslItem')['publisher-list'] ?? null);
        $t->same(['Portland Convention Center', 'Remote Stream'], $handoff['bibliography']->children[1]->attr('cslItem')['event-place-list'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="publisher-list"/>
        <text variable="publisher-place-list"/>
        <text variable="original-publisher-list"/>
        <text variable="original-publisher-place-list"/>
        <text variable="original-language-list"/>
        <text variable="language-list"/>
        <text variable="event-place-list"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="publisher-list"/>
      <text variable="publisher-place-list"/>
      <text variable="original-publisher-list"/>
      <text variable="original-publisher-place-list"/>
      <text variable="original-language-list"/>
      <text variable="language-list"/>
      <text variable="event-place-list"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledLiteral = $styled->item('literal-list-review');
        $styledEvent = $styled->item('event-list-review');
        $t->same(['Review Press', 'Archive Desk'], $styledLiteral['publisherList'] ?? null);
        $t->same(['Madrid', 'Barcelona'], $styledLiteral['originalPublisherPlaceList'] ?? null);
        $t->same(['Portland Convention Center', 'Remote Stream'], $styledEvent['eventPlaceList'] ?? null);
        $t->same('[Ng | Review Press; Archive Desk | New York; London | Archivo Press; Migration Desk | Madrid; Barcelona | spanish; basque | english; french; Curator | Migration Desk | Portland Convention Center; Remote Stream]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'literal-list-review', 'text' => '[@literal-list-review]']),
            new AstNode('citation', ['id' => 'event-list-review', 'text' => '[@event-list-review]']),
        ]));
        $t->same('Literal List Review Manual :: Review Press; Archive Desk :: New York; London :: Archivo Press; Migration Desk :: Madrid; Barcelona :: spanish; basque :: english; french', $styled->renderBibliographyEntry('literal-list-review'));
        $t->same('Event List Proceedings :: Migration Desk :: Portland Convention Center; Remote Stream', $styled->renderBibliographyEntry('event-list-review'));

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Literal list review [Ng | Review Press; Archive Desk | New York; London | Archivo Press; Migration Desk | Madrid; Barcelona | spanish; basque | english; french; Curator | Migration Desk | Portland Convention Center; Remote Stream] keeps literal lists visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Literal List Review Manual :: Review Press; Archive Desk :: New York; London :: Archivo Press; Migration Desk :: Madrid; Barcelona :: spanish; basque :: english; french</dd>', $blocks);
        $t->contains('<dt>Curator 2025</dt><dd>Event List Proceedings :: Migration Desk :: Portland Convention Center; Remote Stream</dd>', $blocks);
    },
    'carries biblatex available and submitted dates in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{availability-packet,
  author            = {Ng, Nia},
  title             = {Legacy Availability Packet},
  date              = {2026},
  availabledate     = {2025-04-03/2025-04-05},
  submittedyear     = {2024},
  submittedmonth    = {3},
  submittedendyear  = {2024},
  submittedendmonth = {4},
  url               = {https://example.test/availability-packet}
}

@article{submitted-literal,
  author         = {Roe, Pat},
  title          = {Submitted Literal Packet},
  journaltitle   = {Migration Availability Review},
  date           = {2025},
  availableyear  = {2025},
  availablemonth = {1},
  submitted      = {2025-02-10}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $availability = $items['availability-packet'];
        $submitted = $items['submitted-literal'];

        $t->same(['date-parts' => [[2025, 4, 3], [2025, 4, 5]]], $availability['available-date']);
        $t->same(['date-parts' => [[2024, 3], [2024, 4]]], $availability['submitted']);
        $t->same(['date-parts' => [[2025, 1]]], $submitted['available-date']);
        $t->same(['date-parts' => [[2025, 2, 10]]], $submitted['submitted']);
        $t->same('2025-04-03/2025-04-05', $availability['rawBibtex']['fields']['availabledate']);
        $t->same('2024', $availability['rawBibtex']['fields']['submittedendyear']);
        $t->same('2025-02-10', $submitted['rawBibtex']['fields']['submitted']);
        $t->same(
            'Nia Ng. Legacy Availability Packet. 2026. Available date: 2025-04-03/2025-04-05. Submitted date: 2024-03/2024-04. https://example.test/availability-packet.',
            $processor->renderBibliographyText($availability)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Availability Date Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-availability-date-review</id>
    <updated>2026-06-29T00:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <date variable="available-date"/>
        <date variable="submitted"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="available-date"/>
      <date variable="submitted"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledAvailability = $styled->item('availability-packet');
        $styledSubmitted = $styled->item('submitted-literal');
        $t->same('2025-04-03/2025-04-05', $styledAvailability['availableDate']['display'] ?? null);
        $t->same('2024-03/2024-04', $styledAvailability['submittedDate']['display'] ?? null);
        $t->same('2025-01', $styledSubmitted['availableDate']['display'] ?? null);
        $t->same('2025-02-10', $styledSubmitted['submittedDate']['display'] ?? null);
        $t->same('[Ng | 2025-04-03/2025-04-05 | 2024-03/2024-04; Roe | 2025-01 | 2025-02-10]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'availability-packet', 'text' => '[@availability-packet]']),
            new AstNode('citation', ['id' => 'submitted-literal', 'text' => '[@submitted-literal]']),
        ]));
        $t->same('Legacy Availability Packet :: 2025-04-03/2025-04-05 :: 2024-03/2024-04', $styled->renderBibliographyEntry('availability-packet'));
        $t->same('Submitted Literal Packet :: 2025-01 :: 2025-02-10', $styled->renderBibliographyEntry('submitted-literal'));

        $document = (new MarkdownReader())->read('Availability review [@availability-packet; @submitted-literal] stays visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['availability-packet', 'submitted-literal'], $handoff['citedKeys']);
        $t->same([[2025, 4, 3], [2025, 4, 5]], $handoff['items'][0]['available-date']['date-parts']);
        $t->same([[2025, 2, 10]], $handoff['bibliography']->children[1]->attr('cslItem')['submitted']['date-parts'] ?? null);
        $t->contains('<p>Availability review [Ng | 2025-04-03/2025-04-05 | 2024-03/2024-04; Roe | 2025-01 | 2025-02-10] stays visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Legacy Availability Packet :: 2025-04-03/2025-04-05 :: 2024-03/2024-04</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Submitted Literal Packet :: 2025-01 :: 2025-02-10</dd>', $blocks);
    },
    'carries biblatex date addendum aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{legacy-date-addendum,
  author             = {Ng, Nia},
  title              = {Date Addendum Legacy Packet},
  date               = {2026-06-05},
  dateaddon          = {first source capture},
  origdate           = {2020},
  origdateaddon      = {legacy packet date},
  reprintdate        = {2024},
  reprintdateaddon   = {review facsimile release},
  publisher          = {Review Press},
  url                = {https://example.test/date-addendum-legacy},
  urldate            = {2026-06-06},
  urldateaddon       = {reviewer accessed archive}
}

@proceedings{legacy-event-date-addendum,
  editor         = {Curator, Eli},
  title          = {Event Addendum Legacy Proceedings},
  eventtitle     = {Hybrid Review Clinic},
  eventdate      = {2025-05-01},
  eventdateaddon = {hybrid review window},
  date           = {2025},
  publisher      = {Migration Desk}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $date = $items['legacy-date-addendum'];
        $event = $items['legacy-event-date-addendum'];

        $t->same('webpage', $date['type']);
        $t->same('first source capture', $date['date-addon']);
        $t->same('legacy packet date', $date['original-date-addon']);
        $t->same([2020], $date['original-date']['date-parts'][0]);
        $t->same([2024], $date['reprint-date']['date-parts'][0]);
        $t->same('review facsimile release', $date['reprint-date-addon']);
        $t->same([2026, 6, 6], $date['accessed']['date-parts'][0]);
        $t->same('reviewer accessed archive', $date['accessed-date-addon']);
        $t->same('first source capture', $date['rawBibtex']['fields']['dateaddon']);
        $t->same('review facsimile release', $date['rawBibtex']['fields']['reprintdateaddon']);
        $t->same('reviewer accessed archive', $date['rawBibtex']['fields']['urldateaddon']);
        $t->same('hybrid review window', $event['event-date-addon']);
        $t->same([2025, 5, 1], $event['event-date']['date-parts'][0]);
        $t->same('hybrid review window', $event['rawBibtex']['fields']['eventdateaddon']);
        $t->same(
            'Nia Ng. Date Addendum Legacy Packet. Review Press. 2026. Date addendum: first source capture. Original date addendum: legacy packet date. Reprint date addendum: review facsimile release. Accessed date addendum: reviewer accessed archive. https://example.test/date-addendum-legacy.',
            $processor->renderBibliographyText($date)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="date-addon"/>
        <text variable="original-date-addon"/>
        <text variable="reprint-date-addon"/>
        <text variable="accessed-date-addon"/>
        <text variable="event-date-addon"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="date-addon"/>
      <text variable="original-date-addon"/>
      <text variable="reprint-date-addon"/>
      <text variable="accessed-date-addon"/>
      <text variable="event-date-addon"/>
    </layout>
  </bibliography>
</style>
XML);
        $styledDate = $styled->item('legacy-date-addendum');
        $styledEvent = $styled->item('legacy-event-date-addendum');
        $t->same('first source capture', $styledDate['dateAddon'] ?? null);
        $t->same('review facsimile release', $styledDate['reprintDateAddon'] ?? null);
        $t->same('hybrid review window', $styledEvent['eventDateAddon'] ?? null);
        $t->same('[Date Addendum Legacy Packet | first source capture | legacy packet date | review facsimile release | reviewer accessed archive; Event Addendum Legacy Proceedings | hybrid review window]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-date-addendum', 'text' => '[@legacy-date-addendum]']),
            new AstNode('citation', ['id' => 'legacy-event-date-addendum', 'text' => '[@legacy-event-date-addendum]']),
        ]));
        $t->same('Date Addendum Legacy Packet :: first source capture :: legacy packet date :: review facsimile release :: reviewer accessed archive', $styled->renderBibliographyEntry('legacy-date-addendum'));
        $t->same('Event Addendum Legacy Proceedings :: hybrid review window', $styled->renderBibliographyEntry('legacy-event-date-addendum'));

        $document = (new MarkdownReader())->read('Legacy date addenda cite @legacy-date-addendum and [@legacy-event-date-addendum].');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['legacy-date-addendum', 'legacy-event-date-addendum'], $handoff['citedKeys']);
        $t->same('review facsimile release', $handoff['bibliography']->children[0]->attr('cslItem')['reprint-date-addon'] ?? null);
        $t->contains('Date addendum: first source capture', $blocks);
        $t->contains('Event date addendum: hybrid review window', $blocks);
    },
    'carries legacy biblatex label date metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{legacy-label-date,
  author       = {Smith, Ada},
  title        = {Label Date Review Packet},
  journaltitle = {Source Dating Review},
  date         = {2026},
  labeldate    = {2025-12-31}
}

@report{split-label-date,
  author     = {Ng, Nia},
  title      = {Split Label Date Packet},
  institution = {Review Desk},
  year       = {2024},
  labelyear  = {2023},
  labelmonth = {4}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $legacy = $items['legacy-label-date'];
        $split = $items['split-label-date'];

        $t->same([2025, 12, 31], $legacy['label-date']['date-parts'][0]);
        $t->same([2023, 4], $split['label-date']['date-parts'][0]);
        $t->same('2025-12-31', $legacy['rawBibtex']['fields']['labeldate']);
        $t->same('4', $split['rawBibtex']['fields']['labelmonth']);
        $t->same(
            'Ada Smith. Label Date Review Packet. Source Dating Review. 2026. Label date: 2025-12-31.',
            $processor->renderBibliographyText($legacy)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <date variable="label-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="label-date"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledLegacy = $styled->item('legacy-label-date');
        $styledSplit = $styled->item('split-label-date');
        $t->same('2025-12-31', $styledLegacy['labelDate']['display'] ?? null);
        $t->same('2023-04', $styledSplit['labelDate']['display'] ?? null);
        $t->same('[Smith | 2025-12-31; Ng | 2023-04]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-label-date', 'text' => '[@legacy-label-date]']),
            new AstNode('citation', ['id' => 'split-label-date', 'text' => '[@split-label-date]']),
        ]));
        $t->same('Label Date Review Packet :: 2025-12-31', $styled->renderBibliographyEntry('legacy-label-date'));
        $t->same('Split Label Date Packet :: 2023-04', $styled->renderBibliographyEntry('split-label-date'));

        $document = (new MarkdownReader())->read('Label dates [@legacy-label-date; @split-label-date] stay reviewable.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-label-date', 'split-label-date'], $handoff['citedKeys']);
        $t->same([2025, 12, 31], $handoff['items'][0]['label-date']['date-parts'][0] ?? null);
        $t->same([2023, 4], $handoff['bibliography']->children[1]->attr('cslItem')['label-date']['date-parts'][0] ?? null);
        $t->contains('<p>Label dates [Smith | 2025-12-31; Ng | 2023-04] stay reviewable.</p>', $blocks);
        $t->contains('<dt>Smith 2026</dt><dd>Label Date Review Packet :: 2025-12-31</dd>', $blocks);
        $t->contains('<dt>Ng 2024</dt><dd>Split Label Date Packet :: 2023-04</dd>', $blocks);
    },
    'carries biblatex available submitted and label dates in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{legacy-review-window,
  author        = {Roe, Pat},
  title         = {Availability Window Packet},
  date          = {2026-06-12},
  availabledate = {2025-04-03},
  submitteddate = {2024-03-09},
  labeldate     = {2026-05},
  url           = {https://example.test/availability-window}
}

@report{legacy-split-window,
  author         = {Ng, Nia},
  title          = {Split Window Packet},
  year           = {2025},
  availableyear  = {2025},
  availablemonth = {4},
  availableday   = {5},
  submittedyear  = {2024},
  submittedmonth = {3},
  labelyear      = {2023},
  publisher      = {Review Press}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $window = $items['legacy-review-window'];
        $split = $items['legacy-split-window'];

        $t->same([[2025, 4, 3]], $window['available-date']['date-parts']);
        $t->same([[2024, 3, 9]], $window['submitted']['date-parts']);
        $t->same([2026, 5], $window['label-date']['date-parts'][0]);
        $t->same([[2025, 4, 5]], $split['available-date']['date-parts']);
        $t->same([[2024, 3]], $split['submitted']['date-parts']);
        $t->same([2023], $split['label-date']['date-parts'][0]);
        $t->same('2025-04-03', $window['rawBibtex']['fields']['availabledate']);
        $t->same('2024', $split['rawBibtex']['fields']['submittedyear']);
        $t->contains('Available date: 2025-04-03', $processor->renderBibliographyText($window));
        $t->contains('Submitted date: 2024-03-09', $processor->renderBibliographyText($window));
        $t->contains('Label date: 2026-05', $processor->renderBibliographyText($window));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Review Window Date Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-review-window-date-review</id>
    <updated>2026-06-29T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="available-date"/>
        <date variable="submitted"/>
        <date variable="label-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="available-date"/>
      <date variable="submitted"/>
      <date variable="label-date"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $styledWindow = $styled->item('legacy-review-window');
        $styledSplit = $styled->item('legacy-split-window');
        $t->same('Bounded Legacy BibLaTeX Review Window Date Review', $summary['title'] ?? null);
        $t->same([2025, 4, 3], $styledWindow['availableDate']['parts'] ?? null);
        $t->same([2024, 3], $styledSplit['submittedDate']['parts'] ?? null);
        $t->same('[Availability Window Packet | 2025-04-03 | 2024-03-09 | 2026-05; Split Window Packet | 2025-04-05 | 2024-03 | 2023]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-review-window', 'text' => '[@legacy-review-window]']),
            new AstNode('citation', ['id' => 'legacy-split-window', 'text' => '[@legacy-split-window]']),
        ]));
        $t->same('Availability Window Packet :: 2025-04-03 :: 2024-03-09 :: 2026-05', $styled->renderBibliographyEntry('legacy-review-window'));
        $t->same('Split Window Packet :: 2025-04-05 :: 2024-03 :: 2023', $styled->renderBibliographyEntry('legacy-split-window'));

        $document = (new MarkdownReader())->read('Review windows cite @legacy-review-window and [@legacy-split-window].');
        $handoff = $processor->citationHandoff($document, $source);
        $bibliographyDocument = new AstNode('document', [], [$handoff['bibliography']]);
        $blocks = (new WordPressBlockWriter())->write($bibliographyDocument);

        $t->same(['legacy-review-window', 'legacy-split-window'], $handoff['citedKeys']);
        $t->same([[2025, 4, 3]], $handoff['bibliography']->children[0]->attr('cslItem')['available-date']['date-parts'] ?? null);
        $t->same([[2024, 3]], $handoff['bibliography']->children[1]->attr('cslItem')['submitted']['date-parts'] ?? null);
        $t->contains('Available date: 2025-04-03', $blocks);
        $t->contains('Submitted date: 2024-03', $blocks);
        $t->contains('Label date: 2023', $blocks);
    },
    'carries legacy biblatex date markers times seasons and eras in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{legacy-date-flags,
  author              = {Ng, Nia},
  title               = {Date Flag Legacy Packet},
  date                = {2026-06?},
  dateera             = {CE},
  availableyear       = {2025},
  availablemonth      = {21},
  availablehour       = {9},
  availableminute     = {30},
  availabletimezone   = {Z},
  submitted           = {2024-03-01%/2024-04-02?},
  submittedhour       = {14},
  submittedminute     = {45},
  submittedtimezone   = {+0100},
  submittedendhour    = {16},
  submittedendminute  = {0},
  eventdate           = {2025-24},
  eventdateera        = {CE},
  labeldate           = {2023%},
  url                 = {https://example.test/date-flags}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-date-flags'];

        $t->same([[2026, 6]], $item['issued']['date-parts']);
        $t->same(true, $item['issued']['uncertain'] ?? null);
        $t->same('2026-06?', $item['issued']['raw'] ?? null);
        $t->same('ce', $item['issued']['era'] ?? null);
        $t->same([[2025]], $item['available-date']['date-parts']);
        $t->same(1, $item['available-date']['season'] ?? null);
        $t->same('09:30Z', $item['available-date']['time'] ?? null);
        $t->same([[2024, 3, 1], [2024, 4, 2]], $item['submitted']['date-parts']);
        $t->same(true, $item['submitted']['circa'] ?? null);
        $t->same(true, $item['submitted']['uncertain'] ?? null);
        $t->same('14:45+01:00', $item['submitted']['time'] ?? null);
        $t->same('16:00', $item['submitted']['end-time'] ?? null);
        $t->same(4, $item['event-date']['season'] ?? null);
        $t->same('ce', $item['event-date']['era'] ?? null);
        $t->same(true, $item['label-date']['circa'] ?? null);
        $t->same(true, $item['label-date']['uncertain'] ?? null);
        $t->contains('Date markers: issued uncertain (2026-06?); submitted circa and uncertain (2024-03-01%/2024-04-02?); label-date circa and uncertain (2023%)', $processor->renderBibliographyText($item));
        $t->contains('Date times: available-date 09:30Z; submitted 14:45+01:00/16:00', $processor->renderBibliographyText($item));
        $t->contains('Date seasons: available-date Spring; event-date Winter', $processor->renderBibliographyText($item));
        $t->contains('Date eras: issued ce; event-date ce', $processor->renderBibliographyText($item));

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Date Metadata Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-date-metadata-review</id>
    <updated>2026-06-30T00:00:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="date-marker-summary"/>
        <text variable="time-summary"/>
        <text variable="season-summary"/>
        <text variable="era-summary"/>
        <date variable="available-date"/>
        <text variable="submitted-time"/>
        <text variable="submitted-end-time"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="date-marker-summary"/>
      <text variable="time-summary"/>
      <text variable="season-summary"/>
      <text variable="era-summary"/>
      <date variable="available-date"/>
      <text variable="submitted-time"/>
      <text variable="submitted-end-time"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('legacy-date-flags');
        $t->same('Bounded Legacy BibLaTeX Date Metadata Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('Spring 2025', $styledItem['availableDate']['display'] ?? null);
        $t->same('14:45+01:00', $styledItem['submittedDate']['time'] ?? null);
        $t->same('16:00', $styledItem['submittedDate']['endTime'] ?? null);
        $t->same('Date seasons: available-date Spring; event-date Winter', $styledItem['dateSeasonSummary'] ?? null);
        $t->same('[Date Flag Legacy Packet | Date markers: issued uncertain (2026-06?); submitted circa and uncertain (2024-03-01%/2024-04-02?); label-date circa and uncertain (2023%) | Date times: available-date 09:30Z; submitted 14:45+01:00/16:00 | Date seasons: available-date Spring; event-date Winter | Date eras: issued ce; event-date ce | Spring 2025 | 14:45+01:00 | 16:00]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-date-flags', 'text' => '[@legacy-date-flags]']),
        ]));
        $t->same('Date Flag Legacy Packet :: Date markers: issued uncertain (2026-06?); submitted circa and uncertain (2024-03-01%/2024-04-02?); label-date circa and uncertain (2023%) :: Date times: available-date 09:30Z; submitted 14:45+01:00/16:00 :: Date seasons: available-date Spring; event-date Winter :: Date eras: issued ce; event-date ce :: Spring 2025 :: 14:45+01:00 :: 16:00', $styled->renderBibliographyEntry('legacy-date-flags'));

        $document = (new MarkdownReader())->read('Date flags [@legacy-date-flags] stay reviewable.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-date-flags'], $handoff['citedKeys']);
        $t->same('09:30Z', $handoff['items'][0]['available-date']['time'] ?? null);
        $t->same(4, $handoff['bibliography']->children[0]->attr('cslItem')['event-date']['season'] ?? null);
        $t->contains('<p>Date flags [Date Flag Legacy Packet | Date markers: issued uncertain (2026-06?); submitted circa and uncertain (2024-03-01%/2024-04-02?); label-date circa and uncertain (2023%) | Date times: available-date 09:30Z; submitted 14:45+01:00/16:00 | Date seasons: available-date Spring; event-date Winter | Date eras: issued ce; event-date ce | Spring 2025 | 14:45+01:00 | 16:00] stay reviewable.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Date Flag Legacy Packet :: Date markers: issued uncertain (2026-06?); submitted circa and uncertain (2024-03-01%/2024-04-02?); label-date circa and uncertain (2023%) :: Date times: available-date 09:30Z; submitted 14:45+01:00/16:00 :: Date seasons: available-date Spring; event-date Winter :: Date eras: issued ce; event-date ce :: Spring 2025 :: 14:45+01:00 :: 16:00</dd>', $blocks);
    },
    'carries legacy biblatex source file attachment policy metadata' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{legacy-file-source,
  author = {Ng, Nia},
  title  = {Legacy File Source},
  date   = {2026-06-17},
  url    = {https://example.test/legacy-file-source},
  file   = {Review PDF:attachments/legacy%20audit.pdf:application/pdf; Remote PDF:https://example.test/legacy.pdf:application/pdf; Traversal PDF:../private/legacy.pdf:application/pdf; Missing::application/pdf},
  pdf    = {PDF Mirror:attachments/legacy-mirror.pdf:application/pdf}
}

@report{legacy-pdf-source,
  author = {Roe, Pat},
  title  = {Legacy PDF Alias Source},
  date   = {2025},
  pdf    = {PDF Alias:attachments/pdf-alias.pdf:application/pdf}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $fileItem = $items['legacy-file-source'];
        $pdfItem = $items['legacy-pdf-source'];

        $t->same([
            ['label' => 'Review PDF', 'path' => 'attachments/legacy audit.pdf', 'mediaType' => 'application/pdf'],
            ['label' => 'PDF Mirror', 'path' => 'attachments/legacy-mirror.pdf', 'mediaType' => 'application/pdf'],
        ], $fileItem['sourceFiles'] ?? null);
        $t->same(['remote-uri', 'path-traversal', 'missing-path'], array_column($fileItem['sourceFileDiagnostics'] ?? [], 'reason'));
        $t->same('Remote PDF', $fileItem['sourceFileDiagnostics'][0]['label'] ?? null);
        $t->same('https://example.test/legacy.pdf', $fileItem['sourceFileDiagnostics'][0]['path'] ?? null);
        $t->same(false, $fileItem['sourceFileDiagnostics'][0]['importable'] ?? null);
        $t->same('Missing', $fileItem['sourceFileDiagnostics'][2]['label'] ?? null);
        $t->same('application/pdf', $fileItem['sourceFileDiagnostics'][2]['mediaType'] ?? null);
        $t->same([
            ['label' => 'PDF Alias', 'path' => 'attachments/pdf-alias.pdf', 'mediaType' => 'application/pdf'],
        ], $pdfItem['sourceFiles'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Source File Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-source-file-review</id>
    <updated>2026-06-26T18:34:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="source-file-summary"/>
        <text variable="source-file-diagnostic-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="source-file-labels"/>
      <text variable="source-file-paths"/>
      <text variable="source-file-diagnostic-reasons"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('Bounded Legacy BibLaTeX Source File Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('[Legacy File Source | Review PDF: attachments/legacy audit.pdf (application/pdf); PDF Mirror: attachments/legacy-mirror.pdf (application/pdf) | Remote PDF: remote-uri (https://example.test/legacy.pdf); Traversal PDF: path-traversal (../private/legacy.pdf); Missing: missing-path; Legacy PDF Alias Source | PDF Alias: attachments/pdf-alias.pdf (application/pdf)]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-file-source', 'text' => '[@legacy-file-source]']),
            new AstNode('citation', ['id' => 'legacy-pdf-source', 'text' => '[@legacy-pdf-source]']),
        ]));
        $t->same('Legacy File Source :: Review PDF; PDF Mirror :: attachments/legacy audit.pdf; attachments/legacy-mirror.pdf :: remote-uri; path-traversal; missing-path', $styled->renderBibliographyEntry('legacy-file-source'));
        $t->same('Legacy PDF Alias Source :: PDF Alias :: attachments/pdf-alias.pdf', $styled->renderBibliographyEntry('legacy-pdf-source'));

        $document = (new MarkdownReader())->read('Legacy attachments [@legacy-file-source; @legacy-pdf-source] stay visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Legacy attachments [Legacy File Source | Review PDF: attachments/legacy audit.pdf (application/pdf); PDF Mirror: attachments/legacy-mirror.pdf (application/pdf) | Remote PDF: remote-uri (https://example.test/legacy.pdf); Traversal PDF: path-traversal (../private/legacy.pdf); Missing: missing-path; Legacy PDF Alias Source | PDF Alias: attachments/pdf-alias.pdf (application/pdf)] stay visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Legacy File Source :: Review PDF; PDF Mirror :: attachments/legacy audit.pdf; attachments/legacy-mirror.pdf :: remote-uri; path-traversal; missing-path</dd>', $blocks);
        $t->contains('<dt>Roe 2025</dt><dd>Legacy PDF Alias Source :: PDF Alias :: attachments/pdf-alias.pdf</dd>', $blocks);

        $handoff = $processor->citationHandoff($document, $source);
        $t->same(['legacy-file-source', 'legacy-pdf-source'], $handoff['citedKeys']);
        $t->same('attachments/legacy audit.pdf', $handoff['items'][0]['sourceFiles'][0]['path'] ?? null);
        $t->same('attachments/legacy-mirror.pdf', $handoff['items'][0]['sourceFiles'][1]['path'] ?? null);
        $t->same('remote-uri', $handoff['items'][0]['sourceFileDiagnostics'][0]['reason'] ?? null);
        $t->same('attachments/pdf-alias.pdf', $handoff['items'][1]['sourceFiles'][0]['path'] ?? null);
    },
    'coalesces markdown bracket citation runs for legacy csl wordpress handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@misc{cluster-alpha,
  author = {Alpha, Ada},
  title  = {Alpha Cluster Packet},
  date   = {2026}
}

@misc{cluster-beta,
  author = {Beta, Bea},
  title  = {Beta Cluster Packet},
  date   = {2025}
}
BIB;

        $items = (new BibtexCslProcessor())->cslItems($source);
        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="issued"><date-part name="year"/></date>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued"><date-part name="year"/></date>
    </layout>
  </bibliography>
</style>
XML);

        $document = (new MarkdownReader())->read('Cluster review [@cluster-alpha; @cluster-beta] stays resolved.');
        $rawBlocks = (new WordPressBlockWriter())->write($document);
        $processed = $styled->apply($document);
        $group = $processed->children[0]->children[1];
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->contains('class="pandoc-citation"', $rawBlocks);
        $t->same('citation_group', $group->type);
        $t->same('[Alpha Cluster Packet | 2026; Beta Cluster Packet | 2025]', $group->attr('rendered'));
        $t->contains('<p>Cluster review [Alpha Cluster Packet | 2026; Beta Cluster Packet | 2025] stays resolved.</p>', $blocks);
        $t->contains('<dt>Alpha 2026</dt><dd>Alpha Cluster Packet :: 2026</dd>', $blocks);
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
