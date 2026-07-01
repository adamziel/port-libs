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
    },
    'carries legacy biblatex issuing authority names in csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@report{issuing-report,
  title             = {Authority Name Report},
  issuing-authority = {Board, Migration Review and Council, Standards},
  number            = {R-42},
  date              = {2026}
}

@legislation{authority-statute,
  title     = {Authority Statute},
  authority = {Assembly, Migration},
  number    = {Act 12},
  date      = {2025}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $report = $items['issuing-report'];
        $statute = $items['authority-statute'];

        $t->same('report', $report['type']);
        $t->same('legislation', $statute['type']);
        $t->same('Board', $report['authority'][0]['family']);
        $t->same('Migration Review', $report['authority'][0]['given']);
        $t->same('Council', $report['authority'][1]['family']);
        $t->same('Standards', $report['authority'][1]['given']);
        $t->same('Assembly', $statute['authority'][0]['family']);
        $t->same('Migration', $statute['authority'][0]['given']);
        $t->same('Board, Migration Review and Council, Standards', $report['rawBibtex']['fields']['issuing-authority']);
        $t->same('Assembly, Migration', $statute['rawBibtex']['fields']['authority']);
        $t->same(
            'Migration Review Board and Standards Council. Authority Name Report. 2026. Number: R-42.',
            $processor->renderBibliographyText($report)
        );
        $t->same(
            'Authority Statute. 2025. Number: Act 12. Authority: Migration Assembly.',
            $processor->renderBibliographyText($statute)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Issuing Authority Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-issuing-authority-review</id>
    <updated>2026-07-01T15:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="authority"/>
        <names variable="authority"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <names variable="issuing-authority"/>
      <text variable="authority"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledReport = $styled->item('issuing-report');
        $t->same('Migration Review Board; Standards Council', $styledReport['authority'] ?? null);
        $t->same('Board', $styledReport['authorities'][0]['family'] ?? null);
        $t->same('Standards', $styledReport['authorities'][1]['given'] ?? null);
        $t->same(
            '[Authority Name Report | Migration Review Board; Standards Council | Board and Council; Authority Statute | Migration Assembly | Assembly]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'issuing-report', 'text' => '[@issuing-report]']),
                new AstNode('citation', ['id' => 'authority-statute', 'text' => '[@authority-statute]']),
            ])
        );
        $t->same(
            'Authority Name Report :: Board, Migration Review; Council, Standards :: Migration Review Board; Standards Council',
            $styled->renderBibliographyEntry('issuing-report')
        );

        $document = (new MarkdownReader())->read('Authority names cite @issuing-report and [@authority-statute].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['issuing-report', 'authority-statute'], $handoff['citedKeys']);
        $t->same('Board', $handoff['items'][0]['authority'][0]['family'] ?? null);
        $t->same('Assembly', $handoff['bibliography']->children[1]->attr('cslItem')['authority'][0]['family'] ?? null);
        $t->contains('Migration Review Board and Standards Council. Authority Name Report', $blocks);
        $t->contains('Authority: Migration Assembly', $blocks);
    },
    'carries biblatex annotations separately from abstracts in legacy csl handoff' => static function (TestRunner $t): void {
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
    'carries biblatex standard type and number metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@standard{migration-standard,
  author       = {Ng, Nia},
  title        = {Migration Package Standard},
  organization = {Standards Office},
  date         = {2026},
  number       = {STD-1581},
  status       = {approved},
  langid       = {en},
  series       = {Review Standards Series},
  urldescription = {canonical standard URL},
  url          = {https://example.test/standard}
}

@article{journal-number,
  author       = {Roe, Pat},
  title        = {Journal Number Packet},
  journaltitle = {Migration Standards Review},
  date         = {2025},
  number       = {3}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $standard = $items['migration-standard'];
        $journal = $items['journal-number'];

        $t->same('standard', $standard['type']);
        $t->same('STD-1581', $standard['number']);
        $t->same(false, array_key_exists('issue', $standard));
        $t->same('approved', $standard['status']);
        $t->same('en', $standard['language']);
        $t->same('Review Standards Series', $standard['collection-title']);
        $t->same('canonical standard URL', $standard['URL-label']);
        $t->same('STD-1581', $standard['rawBibtex']['fields']['number']);
        $t->same('article-journal', $journal['type']);
        $t->same('3', $journal['issue']);
        $t->same(false, array_key_exists('number', $journal));
        $t->same(
            'Nia Ng. Migration Package Standard. Standards Office. 2026. Number: STD-1581. Collection title: Review Standards Series. Status: approved. URL label: canonical standard URL. https://example.test/standard.',
            $processor->renderBibliographyText($standard)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="type"/>
        <text variable="number"/>
        <text variable="issue"/>
        <text variable="status"/>
        <text variable="language"/>
        <text variable="collection-title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="type"/>
      <text variable="number"/>
      <text variable="issue"/>
      <text variable="status"/>
      <text variable="collection-title"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same('[standard | STD-1581 | approved | en | Review Standards Series; article-journal | 3]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'migration-standard', 'text' => '[@migration-standard]']),
            new AstNode('citation', ['id' => 'journal-number', 'text' => '[@journal-number]']),
        ]));
        $t->same('Migration Package Standard :: standard :: STD-1581 :: approved :: Review Standards Series', $styled->renderBibliographyEntry('migration-standard'));

        $document = (new MarkdownReader())->read('Standard review @migration-standard keeps document numbers distinct from journal issues.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['migration-standard'], $handoff['citedKeys']);
        $t->same('STD-1581', $handoff['items'][0]['number']);
        $t->same(false, array_key_exists('issue', $handoff['items'][0]));
        $t->contains('Number: STD-1581', $blocks);
        $t->contains('Collection title: Review Standards Series', $blocks);
        $t->contains('Status: approved', $blocks);
        $t->contains('URL label: canonical standard URL', $blocks);
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
    'carries biblatex auxiliary editorial roles in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@collection{editorial-role-packet,
  editor      = {Primary, Pat},
  editortype  = {editorialdirector},
  editora     = {Compile, Cora and Build, Ben},
  editoratype = {compiler},
  editorb     = {Curate, Eli},
  editorbtype = {curator},
  editorc     = {Review, Robin},
  editorctype = {reviewedauthor},
  title       = {Editorial Role Packet},
  date        = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($source)['editorial-role-packet'];
        $bibliography = $processor->renderBibliographyText($item);

        $t->same('book', $item['type']);
        $t->same('Primary', $item['editor'][0]['family']);
        $t->same('Primary', $item['editorial-director'][0]['family']);
        $t->same('Compile', $item['compiler'][0]['family']);
        $t->same('Build', $item['compiler'][1]['family']);
        $t->same('Curate', $item['curator'][0]['family']);
        $t->same('Review', $item['reviewed-author'][0]['family']);
        $t->same('editor', $item['editorial-roles'][0]['field']);
        $t->same('editorial-director', $item['editorial-roles'][0]['type']);
        $t->same('Editorial director', $item['editorial-roles'][0]['label']);
        $t->same('editora', $item['editorial-roles'][1]['field']);
        $t->same('compiler', $item['editorial-roles'][1]['type']);
        $t->same('Compiler', $item['editorial-roles'][1]['label']);
        $t->same('editorb', $item['editorial-roles'][2]['field']);
        $t->same('curator', $item['editorial-roles'][2]['type']);
        $t->same('editorc', $item['editorial-roles'][3]['field']);
        $t->same('reviewed-author', $item['editorial-roles'][3]['type']);
        $t->same('reviewedauthor', $item['rawBibtex']['fields']['editorctype']);
        $t->contains(
            'BibLaTeX editorial roles: editor Editorial director: Pat Primary; editora Compiler: Cora Compile and Ben Build; editorb Curator: Eli Curate; editorc Reviewed author: Robin Review',
            $bibliography
        );

        $styled = CitationCslProcessor::fromItems([$item])->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="editorial-role-summary"/>
        <names variable="compiler"/>
        <names variable="curator"/>
        <names variable="reviewed-author"/>
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

        $styledItem = $styled->item('editorial-role-packet');
        $t->same('Primary', $styledItem['editorialDirectors'][0]['family']);
        $t->same('Compile', $styledItem['compilers'][0]['family']);
        $t->same('Build', $styledItem['compilers'][1]['family']);
        $t->same('Curate', $styledItem['curators'][0]['family']);
        $t->same('Review', $styledItem['reviewedAuthors'][0]['family']);
        $t->same('editorial-director', $styledItem['editorialRoles'][0]['type']);
        $t->same('compiler', $styledItem['editorialRoles'][1]['type']);
        $t->same(
            '[Editorial Role Packet | Editorial direction by Primary, Pat. Compiled by Compile, Cora; Build, Ben. Curated by Curate, Eli. Reviewed author: Review, Robin. | Compile and Build | Curate | Review]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'editorial-role-packet', 'text' => '[@editorial-role-packet]']),
            ])
        );
        $t->same(
            'Editorial Role Packet :: Editorial direction by Primary, Pat. Compiled by Compile, Cora; Build, Ben. Curated by Curate, Eli. Reviewed author: Review, Robin.',
            $styled->renderBibliographyEntry('editorial-role-packet')
        );

        $document = (new MarkdownReader())->read('Editorial roles @editorial-role-packet remain visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'References'));

        $t->same(['editorial-role-packet'], $handoff['citedKeys']);
        $t->same('compiler', $handoff['bibliography']->children[0]->attr('cslItem')['editorial-roles'][1]['type'] ?? null);
        $t->contains('Editorial roles Primary (2026) remain visible.', $blocks);
        $t->contains('Editorial direction by Primary, Pat. Compiled by Compile, Cora; Build, Ben. Curated by Curate, Eli. Reviewed author: Review, Robin.', $blocks);
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
    'carries biblatex ISO date ranges in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{date-range-packet,
  author      = {Ng, Nia},
  title       = {Date Range Packet},
  date        = {2026-06-01/2026-06-03},
  urldate     = {2026-06-04/2026-06-05},
  eventdate   = {2026-05/2026-06},
  origdate    = {2020/2021},
  reprintdate = {2025-11/},
  url         = {https://example.test/date-range}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['date-range-packet'];

        $t->same([[2026, 6, 1], [2026, 6, 3]], $item['issued']['date-parts']);
        $t->same('2026-06-01/2026-06-03', $item['issued']['raw']);
        $t->same([[2026, 6, 4], [2026, 6, 5]], $item['accessed']['date-parts']);
        $t->same([[2026, 5], [2026, 6]], $item['event-date']['date-parts']);
        $t->same([[2020], [2021]], $item['original-date']['date-parts']);
        $t->same([[2025, 11]], $item['reprint-date']['date-parts']);
        $t->same('end', $item['reprint-date']['open-ended']);
        $t->same('2025-11/', $item['reprint-date']['raw']);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="issued"/>
        <date variable="accessed"/>
        <date variable="event-date"/>
        <date variable="original-date"/>
        <date variable="reprint-date"/>
        <text variable="issued-raw"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="issued"/>
      <date variable="accessed"/>
      <date variable="event-date"/>
      <date variable="original-date"/>
      <date variable="reprint-date"/>
    </layout>
  </bibliography>
</style>
XML);

        $styledItem = $styled->item('date-range-packet');
        $t->same('2026-06-01/2026-06-03', $styledItem['issuedDate']['display'] ?? null);
        $t->same([[2026, 6, 1], [2026, 6, 3]], $styledItem['issuedDate']['rangeParts'] ?? null);
        $t->same('2025-11/', $styledItem['reprintDate']['display'] ?? null);
        $t->same('end', $styledItem['reprintDate']['openEnded'] ?? null);
        $t->same(
            '[Date Range Packet | 2026-06-01/2026-06-03 | 2026-06-04/2026-06-05 | 2026-05/2026-06 | 2020/2021 | 2025-11/ | 2026-06-01/2026-06-03]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'date-range-packet', 'text' => '[@date-range-packet]']),
            ])
        );
        $t->same(
            'Date Range Packet :: 2026-06-01/2026-06-03 :: 2026-06-04/2026-06-05 :: 2026-05/2026-06 :: 2020/2021 :: 2025-11/',
            $styled->renderBibliographyEntry('date-range-packet')
        );

        $document = (new MarkdownReader())->read('Date range review [@date-range-packet] remains style-visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Date range review [Date Range Packet | 2026-06-01/2026-06-03 | 2026-06-04/2026-06-05 | 2026-05/2026-06 | 2020/2021 | 2025-11/ | 2026-06-01/2026-06-03] remains style-visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Date Range Packet :: 2026-06-01/2026-06-03 :: 2026-06-04/2026-06-05 :: 2026-05/2026-06 :: 2020/2021 :: 2025-11/</dd>', $blocks);
    },
    'maps split biblatex URL access dates into CSL accessed dates' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{split-url-date,
  author   = {Ng, Nia},
  title    = {Split URL Date Packet},
  date     = {2026},
  urlyear  = {2026},
  urlmonth = jun,
  urlday   = {17},
  url      = {https://example.test/split-url-date}
}

@online{split-accessed-date,
  author        = {Roe, Pat},
  title         = {Split Accessed Date Packet},
  date          = {2025},
  accessedyear  = {2025},
  accessedmonth = {11},
  accessedday   = {3},
  url           = {https://example.test/split-accessed-date}
}

@online{split-access-date,
  author      = {Lee, Lia},
  title       = {Split Access Date Packet},
  date        = {2024},
  accessyear  = {2024},
  accessmonth = {4},
  accessday   = {9},
  url         = {https://example.test/split-access-date}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);

        $t->same([2026, 6, 17], $items['split-url-date']['accessed']['date-parts'][0]);
        $t->same([2025, 11, 3], $items['split-accessed-date']['accessed']['date-parts'][0]);
        $t->same([2024, 4, 9], $items['split-access-date']['accessed']['date-parts'][0]);
        $t->same('6', $items['split-url-date']['rawBibtex']['fields']['urlmonth']);
        $t->same('2025', $items['split-accessed-date']['rawBibtex']['fields']['accessedyear']);
        $t->same('2024', $items['split-access-date']['rawBibtex']['fields']['accessyear']);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="accessed"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="accessed"/>
    </layout>
  </bibliography>
</style>
XML);

        $t->same(
            '[Split URL Date Packet | 2026-06-17; Split Accessed Date Packet | 2025-11-03; Split Access Date Packet | 2024-04-09]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'split-url-date', 'text' => '[@split-url-date]']),
                new AstNode('citation', ['id' => 'split-accessed-date', 'text' => '[@split-accessed-date]']),
                new AstNode('citation', ['id' => 'split-access-date', 'text' => '[@split-access-date]']),
            ])
        );
        $t->same('Split URL Date Packet :: 2026-06-17', $styled->renderBibliographyEntry('split-url-date'));

        $document = (new MarkdownReader())->read('Split access dates [@split-url-date; @split-accessed-date; @split-access-date] stay style-visible.');
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Split access dates [Split URL Date Packet | 2026-06-17; Split Accessed Date Packet | 2025-11-03; Split Access Date Packet | 2024-04-09] stay style-visible.</p>', $blocks);
        $t->contains('<dt>Ng 2026</dt><dd>Split URL Date Packet :: 2026-06-17</dd>', $blocks);
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
        $t->same('Gia Garcia. Migration Manual. Review Press. 2026. Collection title: Review Sources. Collection title abbreviation: RS. Collection number: 7. Version: 2.1.0. Status: revised. Medium: print-on-demand packet.', $bibliography);
    },
    'carries biblatex literal list metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@book{distributed-source,
  author        = {Curator, Eli},
  title         = {Distributed Source Review},
  date          = {2026},
  publisher     = {{Review Press} and {Archive Desk}},
  location      = {{New York} and {London}},
  language      = {{english} and {french}},
  origpublisher = {{Archivo Press} and {Migration Desk}},
  origlocation  = {{Madrid} and {Barcelona}},
  origlanguage  = {{spanish} and {catalan}},
  url           = {https://example.test/distributed-source}
}

@proceedings{distributed-venue,
  editor     = {Program, Pat},
  title      = {Distributed Venue Proceedings},
  eventtitle = {Migration Review Summit},
  venue      = {{Portland Convention Center} and {Remote Stream}},
  date       = {2025},
  institution = {{Migration Board} and {Source Lab}},
  address     = {{Remote} and {Portland}}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $sourceItem = $items['distributed-source'];
        $venueItem = $items['distributed-venue'];

        $t->same('Review Press; Archive Desk', $sourceItem['publisher']);
        $t->same(['Review Press', 'Archive Desk'], $sourceItem['publisher-list']);
        $t->same('New York; London', $sourceItem['publisher-place']);
        $t->same(['New York', 'London'], $sourceItem['publisher-place-list']);
        $t->same('english; french', $sourceItem['language']);
        $t->same(['english', 'french'], $sourceItem['language-list']);
        $t->same(['Archivo Press', 'Migration Desk'], $sourceItem['original-publisher-list']);
        $t->same(['Madrid', 'Barcelona'], $sourceItem['original-publisher-place-list']);
        $t->same(['spanish', 'catalan'], $sourceItem['original-language-list']);
        $t->same('Review Press and Archive Desk', $sourceItem['rawBibtex']['fields']['publisher']);
        $t->same('Portland Convention Center; Remote Stream', $venueItem['event-place']);
        $t->same(['Portland Convention Center', 'Remote Stream'], $venueItem['event-place-list']);
        $t->same(['Migration Board', 'Source Lab'], $venueItem['publisher-list']);
        $t->same(['Remote', 'Portland'], $venueItem['publisher-place-list']);
        $t->same(
            'Eli Curator. Distributed Source Review. Review Press; Archive Desk. 2026. https://example.test/distributed-source.',
            $processor->renderBibliographyText($sourceItem)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Literal List Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-literal-list-review</id>
    <updated>2026-06-27T10:40:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author editor"/>
        <text variable="publisher-list"/>
        <text variable="publisher-place-list"/>
        <text variable="event-place-list"/>
        <text variable="language-list"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="publisher-list"/>
      <text variable="publisher-place-list"/>
      <text variable="event-place-list"/>
      <text variable="language-list"/>
      <text variable="original-publisher-list"/>
      <text variable="original-publisher-place-list"/>
      <text variable="original-language-list"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $styledSource = $styled->item('distributed-source');
        $styledVenue = $styled->item('distributed-venue');
        $t->same('Bounded Legacy BibLaTeX Literal List Review', $summary['title'] ?? null);
        $t->same(['Review Press', 'Archive Desk'], $styledSource['publisherList'] ?? null);
        $t->same(['Portland Convention Center', 'Remote Stream'], $styledVenue['eventPlaceList'] ?? null);
        $t->same('[Curator | Review Press; Archive Desk | New York; London | english; french; Program | Migration Board; Source Lab | Remote; Portland | Portland Convention Center; Remote Stream]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'distributed-source', 'text' => '[@distributed-source]']),
            new AstNode('citation', ['id' => 'distributed-venue', 'text' => '[@distributed-venue]']),
        ]));
        $t->same(
            'Distributed Source Review :: Review Press; Archive Desk :: New York; London :: english; french :: Archivo Press; Migration Desk :: Madrid; Barcelona :: spanish; catalan',
            $styled->renderBibliographyEntry('distributed-source')
        );
        $t->same('Distributed Venue Proceedings :: Migration Board; Source Lab :: Remote; Portland :: Portland Convention Center; Remote Stream', $styled->renderBibliographyEntry('distributed-venue'));

        $document = (new MarkdownReader())->read('Distributed source [@distributed-source; @distributed-venue] keep literal lists visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['distributed-source', 'distributed-venue'], $handoff['citedKeys']);
        $t->same(['Review Press', 'Archive Desk'], $handoff['items'][0]['publisher-list'] ?? null);
        $t->same(['Portland Convention Center', 'Remote Stream'], $handoff['items'][1]['event-place-list'] ?? null);
        $t->contains('<p>Distributed source [Curator | Review Press; Archive Desk | New York; London | english; french; Program | Migration Board; Source Lab | Remote; Portland | Portland Convention Center; Remote Stream] keep literal lists visible.</p>', $blocks);
        $t->contains('<dt>Curator 2026</dt><dd>Distributed Source Review :: Review Press; Archive Desk :: New York; London :: english; french :: Archivo Press; Migration Desk :: Madrid; Barcelona :: spanish; catalan</dd>', $blocks);
        $t->contains('<dt>Program 2025</dt><dd>Distributed Venue Proceedings :: Migration Board; Source Lab :: Remote; Portland :: Portland Convention Center; Remote Stream</dd>', $blocks);
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
    'carries direct csl title aliases in legacy biblatex handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{direct-csl-title-aliases,
  author                 = {Alias, Avery},
  title                  = {Direct Alias Packet},
  title-short            = {DAP},
  title-addon            = {proof note},
  container-title        = {Journal of Direct Aliases},
  container-subtitle     = {Review Edition},
  container-title-addon  = {container addendum},
  journal-title-short    = {J. Direct Alias.},
  originaltitle          = {Paquete Directo},
  originalsubtitle       = {Archivo},
  original-title-addon   = {facsimile},
  date                   = {2026}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['direct-csl-title-aliases'];

        $t->same('Direct Alias Packet', $item['title']);
        $t->same('DAP', $item['short-title']);
        $t->same('proof note', $item['title-addon']);
        $t->same('Journal of Direct Aliases: Review Edition', $item['container-title']);
        $t->same('container addendum', $item['container-title-addon']);
        $t->same('J. Direct Alias.', $item['container-title-short']);
        $t->same('J. Direct Alias.', $item['journal-abbreviation']);
        $t->same('Paquete Directo: Archivo', $item['original-title']);
        $t->same('facsimile', $item['original-title-addon']);
        $t->same('Journal of Direct Aliases', $item['rawBibtex']['fields']['container-title']);
        $t->same('DAP', $item['rawBibtex']['fields']['title-short']);
        $t->contains('Journal abbreviation: J. Direct Alias', $processor->renderBibliographyText($item));

        $document = (new MarkdownReader())->read('Direct CSL title aliases [@direct-csl-title-aliases] remain visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $t->same(['direct-csl-title-aliases'], $handoff['citedKeys']);
        $t->same('DAP', $handoff['bibliography']->children[0]->attr('cslItem')['short-title'] ?? null);
        $t->same('container addendum', $handoff['items'][0]['container-title-addon'] ?? null);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Direct CSL Title Alias Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-direct-csl-title-alias-review</id>
    <updated>2026-07-01T13:45:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]">
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="title-short"/>
        <text variable="title-addon"/>
        <text variable="container-title"/>
        <text variable="container-title-addon"/>
        <text variable="original-title"/>
        <text variable="container-title-short"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="short-title"/>
      <text variable="title-addon"/>
      <text variable="container-title"/>
      <text variable="container-title-addon"/>
      <text variable="original-title"/>
      <text variable="journal-title-short"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $t->same('Bounded Legacy BibLaTeX Direct CSL Title Alias Review', $summary['title'] ?? null);
        $t->same('title-short', $summary['citationRendering'][0]['children'][1]['variable'] ?? null);
        $t->same('[Alias | DAP | proof note | Journal of Direct Aliases: Review Edition | container addendum | Paquete Directo: Archivo | J. Direct Alias.]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'direct-csl-title-aliases', 'text' => '[@direct-csl-title-aliases]']),
        ]));
        $t->same('Direct Alias Packet :: DAP :: proof note :: Journal of Direct Aliases: Review Edition :: container addendum :: Paquete Directo: Archivo :: J. Direct Alias.', $styled->renderBibliographyEntry('direct-csl-title-aliases'));

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Direct CSL title aliases [Alias | DAP | proof note | Journal of Direct Aliases: Review Edition | container addendum | Paquete Directo: Archivo | J. Direct Alias.] remain visible.</p>', $blocks);
        $t->contains('<dt>Alias 2026</dt><dd>Direct Alias Packet :: DAP :: proof note :: Journal of Direct Aliases: Review Edition :: container addendum :: Paquete Directo: Archivo :: J. Direct Alias.</dd>', $blocks);
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

        $processor = new BibtexCslProcessor();
        $item = $processor->cslItems($source)['extent-handoff'];
        $bibliography = $processor->renderBibliographyText($item);

        $t->same('chapter', $item['type']);
        $t->same('2', $item['volume']);
        $t->same('4', $item['number-of-volumes']);
        $t->same('7', $item['chapter-number']);
        $t->same('101-120', $item['page']);
        $t->same('320', $item['number-of-pages']);
        $t->same('Casey Chapter. Extent Review Chapter. Migration Extent Handbook 2. 2026. 101-120. Number of volumes: 4. Chapter number: 7. Number of pages: 320.', $bibliography);

        $document = (new MarkdownReader())->read('Extent source @extent-handoff keeps compact extent metadata visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['extent-handoff'], $handoff['citedKeys']);
        $t->same('4', $handoff['items'][0]['number-of-volumes']);
        $t->same('7', $handoff['bibliography']->children[0]->attr('cslItem')['chapter-number'] ?? null);
        $t->same('320', $handoff['bibliography']->children[0]->attr('cslItem')['number-of-pages'] ?? null);
        $t->contains('Number of volumes: 4. Chapter number: 7. Number of pages: 320.', $blocks);
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
    'carries biblatex publication detail aliases in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@inbook{legacy-publication-details,
  author         = {Ng, Nia},
  title          = {Publication Detail Packet},
  booktitle      = {Source Review Handbook},
  date           = {2026},
  pages          = {101--118},
  pagination     = {section},
  bookpagination = {chapter},
  part           = {B},
  printingnumber = {3},
  references     = {Legacy Packet A; Legacy Packet B},
  dimensions     = {21 cm},
  division       = {Part II},
  scale          = {1:24000},
  type           = {review catalog},
  entrysubtype   = {migration appendix}
}

@book{legacy-hyphen-publication-details,
  title           = {Hyphenated Publication Detail Packet},
  date            = {2025},
  book-pagination = {folio},
  part-number     = {7},
  printing-number = {2},
  dimension       = {folded map},
  subdivision     = {Appendix}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-publication-details'];
        $hyphen = $items['legacy-hyphen-publication-details'];

        $t->same(['legacy-publication-details', 'legacy-hyphen-publication-details'], array_keys($items));
        $t->same('section', $item['pagination']);
        $t->same('chapter', $item['book-pagination']);
        $t->same('B', $item['part']);
        $t->same('3', $item['printing-number']);
        $t->same('Legacy Packet A; Legacy Packet B', $item['references']);
        $t->same('21 cm', $item['dimensions']);
        $t->same('Part II', $item['division']);
        $t->same('1:24000', $item['scale']);
        $t->same('review catalog', $item['genre']);
        $t->same('migration appendix', $item['entry-subtype']);
        $t->same('chapter', $item['rawBibtex']['fields']['bookpagination']);
        $t->same('3', $item['rawBibtex']['fields']['printingnumber']);
        $t->same('folio', $hyphen['book-pagination']);
        $t->same('7', $hyphen['part']);
        $t->same('2', $hyphen['printing-number']);
        $t->same('folded map', $hyphen['dimensions']);
        $t->same('Appendix', $hyphen['division']);
        $t->same('folio', $hyphen['rawBibtex']['fields']['book-pagination']);
        $t->same('Appendix', $hyphen['rawBibtex']['fields']['subdivision']);
        $t->same(
            'Nia Ng. Publication Detail Packet. Source Review Handbook. 2026. 101-118. Pagination: section. Book pagination: chapter. Part: B. Printing number: 3. References: Legacy Packet A; Legacy Packet B. Dimensions: 21 cm. Division: Part II. Scale: 1:24000. Entry subtype: migration appendix.',
            $processor->renderBibliographyText($item)
        );
        $t->same(
            'Hyphenated Publication Detail Packet. 2025. Book pagination: folio. Part: 7. Printing number: 2. Dimensions: folded map. Division: Appendix.',
            $processor->renderBibliographyText($hyphen)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Publication Detail Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-publication-detail-review</id>
    <updated>2026-06-30T23:30:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="pagination"/>
        <text variable="book-pagination"/>
        <text variable="part"/>
        <text variable="printing-number"/>
        <text variable="references"/>
        <text variable="dimensions"/>
        <text variable="division"/>
        <text variable="scale"/>
        <text variable="entry-subtype"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="pagination"/>
      <text variable="book-pagination"/>
      <text variable="part"/>
      <text variable="printing-number"/>
      <text variable="references"/>
      <text variable="dimensions"/>
      <text variable="division"/>
      <text variable="scale"/>
      <text variable="entry-subtype"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('legacy-publication-details');
        $t->same('Bounded Legacy BibLaTeX Publication Detail Review', $styled->cslStyleSummary()['title'] ?? null);
        $t->same('section', $normalized['pagination'] ?? null);
        $t->same('chapter', $normalized['bookPagination'] ?? null);
        $t->same('B', $normalized['part'] ?? null);
        $t->same('3', $normalized['printingNumber'] ?? null);
        $t->same('Legacy Packet A; Legacy Packet B', $normalized['references'] ?? null);
        $t->same('21 cm', $normalized['dimensions'] ?? null);
        $t->same('Part II', $normalized['division'] ?? null);
        $t->same('1:24000', $normalized['scale'] ?? null);
        $t->same('migration appendix', $normalized['entrySubtype'] ?? null);
        $t->same('[Publication Detail Packet | section | chapter | B | 3 | Legacy Packet A; Legacy Packet B | 21 cm | Part II | 1:24000 | migration appendix; Hyphenated Publication Detail Packet | folio | 7 | 2 | folded map | Appendix]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-publication-details', 'text' => '[@legacy-publication-details]']),
            new AstNode('citation', ['id' => 'legacy-hyphen-publication-details', 'text' => '[@legacy-hyphen-publication-details]']),
        ]));
        $t->same('Publication Detail Packet :: section :: chapter :: B :: 3 :: Legacy Packet A; Legacy Packet B :: 21 cm :: Part II :: 1:24000 :: migration appendix', $styled->renderBibliographyEntry('legacy-publication-details'));
        $t->same('Hyphenated Publication Detail Packet :: folio :: 7 :: 2 :: folded map :: Appendix', $styled->renderBibliographyEntry('legacy-hyphen-publication-details'));

        $document = (new MarkdownReader())->read('Legacy publication details [@legacy-publication-details; @legacy-hyphen-publication-details] stay visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $directBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));
        $styledBlocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));

        $t->same(['legacy-publication-details', 'legacy-hyphen-publication-details'], $handoff['citedKeys']);
        $t->same('chapter', $handoff['bibliography']->children[0]->attr('cslItem')['book-pagination'] ?? null);
        $t->contains('Pagination: section. Book pagination: chapter. Part: B. Printing number: 3.', $directBlocks);
        $t->contains('References: Legacy Packet A; Legacy Packet B. Dimensions: 21 cm. Division: Part II. Scale: 1:24000.', $directBlocks);
        $t->contains('<p>Legacy publication details [Publication Detail Packet | section | chapter | B | 3 | Legacy Packet A; Legacy Packet B | 21 cm | Part II | 1:24000 | migration appendix; Hyphenated Publication Detail Packet | folio | 7 | 2 | folded map | Appendix] stay visible.</p>', $styledBlocks);
        $t->contains('<dd>Publication Detail Packet :: section :: chapter :: B :: 3 :: Legacy Packet A; Legacy Packet B :: 21 cm :: Part II :: 1:24000 :: migration appendix</dd>', $styledBlocks);
        $t->contains('<dd>Hyphenated Publication Detail Packet :: folio :: 7 :: 2 :: folded map :: Appendix</dd>', $styledBlocks);
    },
    'carries biblatex pagination and printing number metadata in legacy csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@inbook{column-printing,
  author          = {Ng, Nia},
  title           = {Column Printing Packet},
  booktitle       = {Review Sourcebook},
  date            = {2026},
  pages           = {12--14},
  page-label      = {column},
  bookpagination  = {section},
  part            = {A},
  printing        = {2},
  supplement-number = {1}
}

@article{folio-range,
  author            = {Roe, Pat},
  title             = {Folio Range Packet},
  journaltitle      = {Migration Review},
  year              = {2025},
  pages             = {4--6},
  pagination        = {folio},
  book-pagination   = {line},
  part-number       = {B},
  printing-number   = {3-4},
  supplementnumber  = {2-3}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $column = $items['column-printing'];
        $folio = $items['folio-range'];

        $t->same('column', $column['pagination']);
        $t->same('section', $column['book-pagination']);
        $t->same('A', $column['part']);
        $t->same('2', $column['printing-number']);
        $t->same('1', $column['supplement-number']);
        $t->same('folio', $folio['pagination']);
        $t->same('line', $folio['book-pagination']);
        $t->same('B', $folio['part']);
        $t->same('3-4', $folio['printing-number']);
        $t->same('2-3', $folio['supplement-number']);
        $t->same('column', $column['rawBibtex']['fields']['page-label']);
        $t->same('section', $column['rawBibtex']['fields']['bookpagination']);
        $t->same('2', $column['rawBibtex']['fields']['printing']);
        $t->same('1', $column['rawBibtex']['fields']['supplement-number']);
        $t->same('line', $folio['rawBibtex']['fields']['book-pagination']);
        $t->same('3-4', $folio['rawBibtex']['fields']['printing-number']);
        $t->same(
            'Nia Ng. Column Printing Packet. Review Sourcebook. 2026. 12-14. Pagination: column. Book pagination: section. Part: A. Printing number: 2. Supplement number: 1.',
            $processor->renderBibliographyText($column)
        );
        $t->same(
            'Pat Roe. Folio Range Packet. Migration Review. 2025. 4-6. Pagination: folio. Book pagination: line. Part: B. Printing number: 3-4. Supplement number: 2-3.',
            $processor->renderBibliographyText($folio)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="en-US">
  <info>
    <title>Bounded Legacy BibLaTeX Pagination Printing Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-pagination-printing-review</id>
    <updated>2026-06-30T23:58:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" ">
        <names variable="author"/>
        <label variable="page" form="long"/>
        <text variable="page"/>
        <text variable="page-label" prefix="[" suffix="]"/>
        <label variable="printing-number" form="short"/>
        <number variable="printing-number" form="ordinal"/>
        <label variable="supplement-number" form="short"/>
        <number variable="supplement-number" form="roman"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="book-pagination"/>
      <text variable="part"/>
      <text variable="printing"/>
      <text variable="supplement-number"/>
    </layout>
  </bibliography>
</style>
XML);

        $summary = $styled->cslStyleSummary();
        $citationChildren = $summary['citationRendering'][0]['children'] ?? [];
        $t->same('Bounded Legacy BibLaTeX Pagination Printing Review', $summary['title'] ?? null);
        $t->same('page-label', $citationChildren[3]['variable'] ?? null);
        $t->same('printing-number', $citationChildren[4]['variable'] ?? null);
        $t->same('ordinal', $citationChildren[5]['form'] ?? null);
        $t->same('supplement-number', $citationChildren[6]['variable'] ?? null);
        $t->same('roman', $citationChildren[7]['form'] ?? null);
        $t->same('[Ng columns 12-14 [column] printing no. 2nd supp. no. i; Roe folios 4-6 [folio] printing nos. 3rd-4th supp. nos. ii-iii]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'column-printing', 'text' => '[@column-printing]']),
            new AstNode('citation', ['id' => 'folio-range', 'text' => '[@folio-range]']),
        ]));
        $t->same('Column Printing Packet :: section :: A :: 2 :: 1', $styled->renderBibliographyEntry('column-printing'));
        $t->same('Folio Range Packet :: line :: B :: 3-4 :: 2-3', $styled->renderBibliographyEntry('folio-range'));

        $document = (new MarkdownReader())->read('Pagination metadata [@column-printing; @folio-range] stays visible.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['column-printing', 'folio-range'], $handoff['citedKeys']);
        $t->same('column', $handoff['items'][0]['pagination']);
        $t->same('3-4', $handoff['bibliography']->children[1]->attr('cslItem')['printing-number'] ?? null);
        $t->same('2-3', $handoff['bibliography']->children[1]->attr('cslItem')['supplement-number'] ?? null);
        $t->contains('Pagination: column', $blocks);
        $t->contains('Book pagination: line', $blocks);
        $t->contains('Printing number: 3-4', $blocks);
        $t->contains('Supplement number: 2-3', $blocks);
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
  sortinit       = {S},
  sortinithash   = {smith-archive},
  labelprefix    = {WP},
  labelalpha     = {Smi26},
  labeltitle     = {legacy label},
  extraalpha     = {b},
  extradate      = {2026b},
  extratitle     = {archive appendix}
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
        $t->same('S', $labels['sort-initial']);
        $t->same('smith-archive', $labels['sort-initial-hash']);
        $t->same('WP', $labels['label-prefix']);
        $t->same('Smi26', $labels['label-alpha']);
        $t->same('legacy label', $labels['label-title']);
        $t->same('b', $labels['extra-alpha']);
        $t->same('2026b', $labels['extra-date']);
        $t->same('archive appendix', $labels['extra-title']);
        $t->same('LLM', $labels['rawBibtex']['fields']['shorthand']);
        $t->same('010 legacy label', $labels['rawBibtex']['fields']['sortshorthand']);
        $t->same('FSH', $fallback['citation-label']);
        $t->same('FSH', $fallback['shorthand-list-sort-key']);
        $t->same(
            'Ada Smith. Legacy Label Manual. 2026. Citation label: LLM. Shorthand intro: cited as Legacy Label Manual. Sort shorthand: 010 legacy label. Presort: aa. Sort key: 900-smith. Sort name: Archive Desk. Sort title: Label Manual Legacy. Sort year: 2025. Sort initial: S. Sort initial hash: smith-archive. Label prefix: WP. Label alpha: Smi26. Label title: legacy label. Extra alpha: b. Extra date: 2026b. Extra title: archive appendix.',
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
        $t->contains('Sort name: Archive Desk', $blocks);
        $t->contains('Label alpha: Smi26', $blocks);
        $t->contains('Extra title: archive appendix', $blocks);
        $t->contains('Fallback Shorthand Manual', $blocks);
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
  related               = {source-appendix, missing-related, source-license},
  relatedtype           = {updated-by},
  relatedstring         = {Updated source},
  relatedoptions        = {dataonly; skipbib},
  crossref              = {source-proceedings}
}

@book{source-appendix,
  author    = {Roe, Pat},
  title     = {Source Appendix Packet},
  date      = {2024},
  publisher = {Appendix Desk}
}

@online{source-license,
  author = {{Archive Desk}},
  title  = {Source License Snapshot},
  date   = {2025},
  url    = {https://example.test/source-license}
}

@proceedings{source-proceedings,
  title     = {Source Proceedings},
  date      = {2023},
  publisher = {Review Conference Press}
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
        $t->same('source-appendix, missing-related, source-license', $item['related']);
        $t->same('updated-by', $item['related-type']);
        $t->same('Updated source', $item['related-string']);
        $t->same('dataonly; skipbib', $item['related-options']);
        $t->same('source-proceedings', $item['xref']);
        $t->same(['source-appendix', 'missing-related', 'source-license'], $item['related-keys']);
        $t->same('Source Appendix Packet', $item['relatedItems'][0]['title'] ?? null);
        $t->same([2024], $item['relatedItems'][0]['issued']['date-parts'][0] ?? null);
        $t->same('Source License Snapshot', $item['relatedItems'][1]['title'] ?? null);
        $t->same(['missing-related'], $item['missing-related-keys']);
        $t->same(['dataonly', 'skipbib'], $item['relatedOptions']);
        $t->same('Updated source (updated-by): Source Appendix Packet (2024); Source License Snapshot (2025); missing: missing-related', $item['relatedSummary']);
        $t->same(['source-proceedings'], $item['xref-keys']);
        $t->same('Source Proceedings', $item['xrefItems'][0]['title'] ?? null);
        $t->same('Xref: Source Proceedings (2023)', $item['xrefSummary']);
        $directBibliography = $processor->renderBibliographyText($item);
        $t->contains('Shorthand list sort key: 005 explicit relation list', $directBibliography);
        $t->contains('Related: source-appendix, missing-related, source-license', $directBibliography);
        $t->contains('Related type: updated-by', $directBibliography);
        $t->contains('Related string: Updated source', $directBibliography);
        $t->contains('Updated source (updated-by): Source Appendix Packet (2024); Source License Snapshot (2025); missing: missing-related', $directBibliography);
        $t->contains('Related options: dataonly; skipbib', $directBibliography);
        $t->contains('Xref: Source Proceedings (2023)', $directBibliography);

        $document = (new MarkdownReader())->read('Relation source [@legacy-relation] keeps relation handoff metadata.');
        $handoff = $processor->citationHandoff($document, $source);

        $t->same(['legacy-relation'], $handoff['citedKeys']);
        $t->same('relation-manual', $handoff['bibliography']->children[0]->attr('cslItem')['id'] ?? null);
        $t->same('005 explicit relation list', $handoff['bibliography']->children[0]->attr('cslItem')['shorthand-list-sort-key'] ?? null);
        $t->same('source-proceedings', $handoff['bibliography']->children[0]->attr('cslItem')['xref'] ?? null);
        $t->same(['missing-related'], $handoff['bibliography']->children[0]->attr('cslItem')['missing-related-keys'] ?? null);
        $directBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));
        $t->contains('Shorthand list sort key: 005 explicit relation list', $directBlocks);
        $t->contains('Related: source-appendix, missing-related, source-license', $directBlocks);
        $t->contains('Xref: Source Proceedings (2023)', $directBlocks);

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <info>
    <title>Bounded Legacy BibLaTeX Related Item Review</title>
    <id>https://example.test/styles/bounded-legacy-biblatex-related-review</id>
    <updated>2026-06-30T23:20:00+00:00</updated>
  </info>
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <text variable="related-summary"/>
        <text variable="related-options"/>
        <text variable="missing-related-keys"/>
        <text variable="xref-summary"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="related-summary"/>
      <text variable="related-options"/>
      <text variable="missing-related-keys"/>
      <text variable="xref-summary"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('relation-manual');
        $t->same(['source-appendix', 'missing-related', 'source-license'], $normalized['relatedKeys'] ?? null);
        $t->same('Source Appendix Packet (2024)', $normalized['relatedItems'][0]['display'] ?? null);
        $t->same('Source License Snapshot (2025)', $normalized['relatedItems'][1]['display'] ?? null);
        $t->same(['missing-related'], $normalized['missingRelatedKeys'] ?? null);
        $t->same(['dataonly', 'skipbib'], $normalized['relatedOptions'] ?? null);
        $t->same('Source Proceedings (2023)', $normalized['xrefItems'][0]['display'] ?? null);
        $t->same('[Relation Review Manual | Updated source (updated-by): Source Appendix Packet (2024); Source License Snapshot (2025); missing: missing-related | dataonly, skipbib | missing-related | Xref: Source Proceedings (2023)]', $styled->renderCitationCluster([
            new AstNode('citation', ['id' => 'legacy-relation', 'text' => '[@legacy-relation]']),
        ]));
        $t->same('Relation Review Manual :: Updated source (updated-by): Source Appendix Packet (2024); Source License Snapshot (2025); missing: missing-related :: dataonly, skipbib :: missing-related :: Xref: Source Proceedings (2023)', $styled->renderBibliographyEntry('relation-manual'));

        $blocks = (new WordPressBlockWriter())->write($styled->appendBibliography($document, 'Works Cited'));
        $t->contains('<p>Relation source [Relation Review Manual | Updated source (updated-by): Source Appendix Packet (2024); Source License Snapshot (2025); missing: missing-related | dataonly, skipbib | missing-related | Xref: Source Proceedings (2023)] keeps relation handoff metadata.</p>', $blocks);
        $t->contains('Relation Review Manual :: Updated source (updated-by): Source Appendix Packet (2024); Source License Snapshot (2025); missing: missing-related :: dataonly, skipbib :: missing-related :: Xref: Source Proceedings (2023)', $blocks);
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
    'carries legacy biblatex availability submitted and label dates in csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@report{legacy-date-handoff,
  author         = {Smith, Ada},
  title          = {Legacy Date Handoff Packet},
  date           = {2026},
  availabledate  = {2026-06-15},
  submittedyear  = {2026},
  submittedmonth = {5},
  submittedday   = {28},
  labeldate      = {2025-12}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-date-handoff'];

        $t->same([2026, 6, 15], $item['available-date']['date-parts'][0]);
        $t->same([2026, 5, 28], $item['submitted']['date-parts'][0]);
        $t->same([2025, 12], $item['label-date']['date-parts'][0]);
        $t->same(
            'Ada Smith. Legacy Date Handoff Packet. 2026. Available date: 2026-06-15. Submitted date: 2026-05-28. Label date: 2025-12.',
            $processor->renderBibliographyText($item)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
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

        $t->same(
            '[Legacy Date Handoff Packet | 2026-06-15 | 2026-05-28 | 2025-12]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'legacy-date-handoff', 'text' => '[@legacy-date-handoff]']),
            ])
        );
        $t->same(
            'Legacy Date Handoff Packet :: 2026-06-15 :: 2026-05-28 :: 2025-12',
            $styled->renderBibliographyEntry('legacy-date-handoff')
        );

        $document = (new MarkdownReader())->read('Legacy date handoff cites @legacy-date-handoff.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-date-handoff'], $handoff['citedKeys']);
        $t->same([2026, 6, 15], $handoff['items'][0]['available-date']['date-parts'][0]);
        $t->contains('Available date: 2026-06-15', $blocks);
        $t->contains('Submitted date: 2026-05-28', $blocks);
        $t->contains('Label date: 2025-12', $blocks);
    },
    'preserves legacy biblatex availability submitted and label date ranges in csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@report{legacy-date-range-handoff,
  author         = {Smith, Ada},
  title          = {Legacy Date Range Handoff Packet},
  date           = {2026},
  availabledate  = {2026-06-15/2026-07-01},
  submitted-date = {2026-05/},
  labeldate      = {/2025-12}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $item = $items['legacy-date-range-handoff'];

        $t->same([[2026, 6, 15], [2026, 7, 1]], $item['available-date']['date-parts']);
        $t->same('2026-06-15/2026-07-01', $item['available-date']['raw']);
        $t->same([[2026, 5]], $item['submitted']['date-parts']);
        $t->same('end', $item['submitted']['open-ended']);
        $t->same('2026-05/', $item['submitted']['raw']);
        $t->same([[2025, 12]], $item['label-date']['date-parts']);
        $t->same('start', $item['label-date']['open-ended']);
        $t->same('/2025-12', $item['label-date']['raw']);
        $t->same(
            'Ada Smith. Legacy Date Range Handoff Packet. 2026. Available date: 2026-06-15/2026-07-01. Submitted date: 2026-05/. Label date: /2025-12.',
            $processor->renderBibliographyText($item)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]">
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

        $t->same(
            '[Legacy Date Range Handoff Packet | 2026-06-15/2026-07-01 | 2026-05/ | /2025-12]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'legacy-date-range-handoff', 'text' => '[@legacy-date-range-handoff]']),
            ])
        );
        $t->same(
            'Legacy Date Range Handoff Packet :: 2026-06-15/2026-07-01 :: 2026-05/ :: /2025-12',
            $styled->renderBibliographyEntry('legacy-date-range-handoff')
        );

        $document = (new MarkdownReader())->read('Legacy date ranges cite @legacy-date-range-handoff.');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['legacy-date-range-handoff'], $handoff['citedKeys']);
        $t->same([[2026, 6, 15], [2026, 7, 1]], $handoff['items'][0]['available-date']['date-parts']);
        $t->contains('Available date: 2026-06-15/2026-07-01', $blocks);
        $t->contains('Submitted date: 2026-05/', $blocks);
        $t->contains('Label date: /2025-12', $blocks);
    },
    'carries legacy biblatex accepted and revised dates in csl handoff' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@article{publication-state-dates,
  author        = {Ng, Nia},
  title         = {Publication State Packet},
  journaltitle  = {Migration Review},
  date          = {2026},
  accepteddate  = {2026-06-12},
  revisedyear   = {2026},
  revisedmonth  = {5},
  revisedday    = {30},
  status        = {accepted}
}

@report{publication-state-aliases,
  author         = {Roe, Pat},
  title          = {Publication State Alias Packet},
  date           = {2025},
  date-accepted  = {2025-03},
  revision-date  = {2025-04/2025-05}
}
BIB;

        $processor = new BibtexCslProcessor();
        $items = $processor->cslItems($source);
        $state = $items['publication-state-dates'];
        $aliases = $items['publication-state-aliases'];

        $t->same([2026, 6, 12], $state['accepted-date']['date-parts'][0]);
        $t->same([2026, 5, 30], $state['revised-date']['date-parts'][0]);
        $t->same([2025, 3], $aliases['accepted-date']['date-parts'][0]);
        $t->same([[2025, 4], [2025, 5]], $aliases['revised-date']['date-parts']);
        $t->same('2026-06-12', $state['rawBibtex']['fields']['accepteddate']);
        $t->same('2026', $state['rawBibtex']['fields']['revisedyear']);
        $t->same('2025-03', $aliases['rawBibtex']['fields']['date-accepted']);
        $t->same('2025-04/2025-05', $aliases['rawBibtex']['fields']['revision-date']);
        $t->same(
            'Nia Ng. Publication State Packet. Migration Review. 2026. Accepted date: 2026-06-12. Revised date: 2026-05-30. Status: accepted.',
            $processor->renderBibliographyText($state)
        );

        $styled = CitationCslProcessor::fromItems(array_values($items))->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <text variable="title"/>
        <date variable="accepted-date"/>
        <date variable="revised-date"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <date variable="date-accepted"/>
      <date variable="revision-date"/>
    </layout>
  </bibliography>
</style>
XML);

        $normalized = $styled->item('publication-state-dates');
        $t->same([2026, 6, 12], $normalized['acceptedDate']['parts'] ?? null);
        $t->same('2026-05-30', $normalized['revisedDate']['display'] ?? null);
        $t->same(
            '[Publication State Packet | 2026-06-12 | 2026-05-30; Publication State Alias Packet | 2025-03 | 2025-04/2025-05]',
            $styled->renderCitationCluster([
                new AstNode('citation', ['id' => 'publication-state-dates', 'text' => '[@publication-state-dates]']),
                new AstNode('citation', ['id' => 'publication-state-aliases', 'text' => '[@publication-state-aliases]']),
            ])
        );
        $t->same(
            'Publication State Alias Packet :: 2025-03 :: 2025-04/2025-05',
            $styled->renderBibliographyEntry('publication-state-aliases')
        );

        $document = (new MarkdownReader())->read('Publication state dates cite @publication-state-dates and [@publication-state-aliases].');
        $handoff = $processor->citationHandoff($document, $source);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$handoff['bibliography']]));

        $t->same(['publication-state-dates', 'publication-state-aliases'], $handoff['citedKeys']);
        $t->same([2026, 6, 12], $handoff['items'][0]['accepted-date']['date-parts'][0]);
        $t->same([[2025, 4], [2025, 5]], $handoff['bibliography']->children[1]->attr('cslItem')['revised-date']['date-parts'] ?? null);
        $t->contains('Accepted date: 2026-06-12', $blocks);
        $t->contains('Revised date: 2025-04/2025-05', $blocks);
    },
];
