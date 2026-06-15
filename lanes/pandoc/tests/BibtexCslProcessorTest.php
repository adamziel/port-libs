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
        $t->same('Gia Garcia. Migration Manual. Review Press. 2026.', $bibliography);
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
