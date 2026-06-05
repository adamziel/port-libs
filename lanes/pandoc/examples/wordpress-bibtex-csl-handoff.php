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

A review note source @review-note-source keeps import audit notes and publication medium attached.

A translated source @translated-manual preserves original publication metadata for source review.

Patent and legal sources @import-patent and @review-act preserve legal review metadata.

Date-range sources @range-manual and @range-rule preserve interval metadata for review.

Title metadata sources @title-review and @chapter-title-review keep reviewer subtitles attached.

Publication detail sources @journal-detail and @book-detail preserve volume, issue, series, and identifier metadata.

Multi-volume source @volume-chapter and dossier [@dossier-set] preserve main-title and volume-family metadata.

Role-rich source @role-review keeps editorial review names attached.

Secondary editor source @secondary-editor-review preserves compiler, editorial director, and reviewer roles.

Annotated name source @name-annotation-review keeps reviewer name annotations attached.

Software source @import-tool and dataset [@source-dataset] preserve version and publication state metadata.

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
  file   = {Review PDF:attachments/source-audit.pdf:application/pdf; Source HTML:attachments/source-audit.html:text/html; Reviewer Notes:attachments/reviewer%20notes.html:text/html; Remote PDF:https://example.test/source-audit.pdf:application/pdf; Traversal PDF:../private/source-audit.pdf:application/pdf; Windows PDF:C:\Users\Ada\source-audit.pdf:application/pdf}
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

@online{review-note-source,
  author       = {Ng, Nia},
  title        = {Review Packet Snapshot},
  date         = {2026-06-05},
  howpublished = {Archived web packet},
  note         = {Needs source-check before migration},
  addendum     = {Queue imported by handoff},
  url          = {https://example.test/review-packet}
}

@book{translated-manual,
  author        = {Garc{\'i}a, Gia},
  translator    = {Curator, Eli and de la Cruz, Ana Maria},
  title         = {Migration Manual},
  origtitle     = {Manual de Migraci{\'o}n},
  date          = {2026},
  origdate      = {2020-05},
  publisher     = {Review Press},
  origpublisher = {Archivo Press},
  origlocation  = {Madrid},
  language      = {english},
  origlanguage  = {spanish}
}

@patent{import-patent,
  author    = {M{\"u}ller, Mia},
  holder    = {{WordPress Foundation}},
  title     = {Block Import Review Patent},
  number    = {US-123456},
  type      = {patent},
  location  = {US},
  date      = {2026-06-05},
  eventdate = {2024-01-15},
  status    = {granted},
  url       = {https://example.test/patents/us-123456}
}

@legislation{review-act,
  title        = {WordPress Import Review Act},
  number       = {HB 42},
  type         = {statute},
  organization = {Oregon Legislature},
  location     = {Oregon},
  date         = {2025-05-01},
  eventdate    = {2025-06-01}
}

@book{range-manual,
  author    = {de la Cruz, Ana Maria},
  title     = {Migration Release Window},
  date      = {2020-05/2021-06},
  origdate  = {2018/2019},
  publisher = {Review Press},
  url       = {https://example.test/range-manual},
  urldate   = {2026-06-04/2026-06-05}
}

@legislation{range-rule,
  title        = {Import Review Rule},
  number       = {Rule 7},
  type         = {regulation},
  organization = {Migration Board},
  date         = {2024/2025},
  eventdate    = {2025-01-01/2025-01-31}
}

@book{title-review,
  author     = {Curator, Eli},
  title      = {Migration Manual},
  subtitle   = {Reviewer Packet Guide},
  titleaddon = {Draft source notes},
  shorttitle = {Reviewer Guide},
  date       = {2026},
  publisher  = {Review Press}
}

@incollection{chapter-title-review,
  author         = {Ng, Nia},
  title          = {Checklist},
  subtitle       = {Attachment Review},
  booktitle      = {Migration Handbook},
  booksubtitle   = {Import Desk Edition},
  booktitleaddon = {Internal packet supplement},
  date           = {2025},
  pages          = {7--12}
}

@article{journal-detail,
  author        = {Doe, Jane},
  title         = {Detailed Field Notes},
  journaltitle  = {Journal of Imports},
  date          = {2026},
  volume        = {12},
  number        = {3},
  pages         = {20--30},
  doi           = {10.5555/detail},
  issn          = {1234-5678},
  eprint        = {2401.01234},
  archiveprefix = {arXiv},
  eprintclass   = {cs.DL}
}

@book{book-detail,
  author       = {Curator, Eli},
  title        = {Review Handbook},
  date         = {2025},
  edition      = {2nd},
  series       = {Source Review Series},
  seriesnumber = {7},
  publisher    = {Review Press},
  isbn         = {978-1-2345-6789-0}
}

@inbook{volume-chapter,
  author         = {Smith, Ada},
  title          = {Review Checklist},
  booktitle      = {Import Handbook},
  booksubtitle   = {Volume Desk Edition},
  maintitle      = {Migration Source Dossier},
  mainsubtitle   = {Multi-volume Reviewer Set},
  maintitleaddon = {Internal archive packet},
  date           = {2026},
  volume         = {2},
  volumes        = {4},
  part           = {1},
  chapter        = {7},
  pagetotal      = {320},
  pages          = {33--39}
}

@mvbook{dossier-set,
  editor    = {Curator, Eli},
  title     = {Migration Source Dossier},
  subtitle  = {Multi-volume Reviewer Set},
  volumes   = {4},
  publisher = {Review Press},
  date      = {2025}
}

@book{role-review,
  author       = {Smith, Ada},
  title        = {Annotated Migration Manual},
  date         = {2026},
  publisher    = {Review Press},
  origauthor   = {Garc{\'i}a, Gia},
  commentator  = {Roe, Pat and {{Migration Desk}}},
  annotator    = {Ng, Nia},
  introduction = {de la Cruz, Ana Maria},
  foreword     = {M{\"u}ller, Mia},
  afterword    = {Curator, Eli}
}

@collection{secondary-editor-review,
  editor      = {Smith, Ada},
  editora     = {Roe, Pat and {{Migration Desk}}},
  editoratype = {compiler},
  editorb     = {Ng, Nia},
  editorbtype = {editorialdirector},
  editorc     = {de la Cruz, Ana Maria},
  editorctype = {reviewer},
  title       = {Migration Source Dossier},
  date        = {2026},
  publisher   = {Review Press}
}

@book{name-annotation-review,
  author     = {Smith, Ada and Ng, Nia},
  author+an  = {1=primary source author; 2:family=family name verified},
  editor     = {Curator, Eli},
  editor+an  = {1=review editor},
  title      = {Annotated Source Names},
  date       = {2026},
  publisher  = {Review Press},
  nameaddon  = {Imported source names verified by review desk}
}

@software{import-tool,
  author   = {{Migration Desk}},
  title    = {Block Import Verifier},
  date     = {2026-06-05},
  version  = {2.1.0-beta},
  pubstate = {preprint},
  url      = {https://example.test/import-verifier}
}

@dataset{source-dataset,
  author   = {Ng, Nia},
  title    = {Source Packet Dataset},
  date     = {2025},
  version  = {2025.4},
  pubstate = {revised},
  doi      = {10.5555/dataset}
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
    if (($sourceGlossary['sourceFiles'][2]['path'] ?? null) !== 'attachments/reviewer notes.html') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not normalize source-glossary attachment path metadata');
    }
    if (array_column($sourceGlossary['sourceFileDiagnostics'] ?? [], 'reason') !== ['remote-uri', 'path-traversal', 'windows-drive-path']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve unsafe attachment diagnostics');
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
    $reviewNoteSource = $processor->item('review-note-source');
    if (($reviewNoteSource['medium'] ?? null) !== 'Archived web packet') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve review-note source medium metadata');
    }
    if (($reviewNoteSource['note'] ?? null) !== 'Needs source-check before migration') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve review-note source note metadata');
    }
    if (($reviewNoteSource['addendum'] ?? null) !== 'Queue imported by handoff') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve review-note source addendum metadata');
    }
    $translatedManual = $processor->item('translated-manual');
    if (($translatedManual['originalTitle'] ?? null) !== 'Manual de Migración') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve translated manual original title');
    }
    if (($translatedManual['originalDate']['display'] ?? null) !== '2020-05') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve translated manual original date');
    }
    if (($translatedManual['translators'][1]['nonDroppingParticle'] ?? null) !== 'de la') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve translated manual translator particles');
    }
    $importPatent = $processor->item('import-patent');
    if (($importPatent['number'] ?? null) !== 'US-123456') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve patent number');
    }
    if (($importPatent['holders'][0]['literal'] ?? null) !== 'WordPress Foundation') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve patent holder');
    }
    if (($importPatent['eventDate']['display'] ?? null) !== '2024-01-15') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve patent event date');
    }
    $reviewAct = $processor->item('review-act');
    if (($reviewAct['authority'] ?? null) !== 'Oregon Legislature') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve legislation authority');
    }
    if (($reviewAct['eventDate']['display'] ?? null) !== '2025-06-01') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve legislation event date');
    }
    $rangeManual = $processor->item('range-manual');
    if (($rangeManual['issuedDate']['rangeParts'] ?? null) !== [[2020, 5], [2021, 6]]) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve issued date range metadata');
    }
    if (($rangeManual['originalDate']['display'] ?? null) !== '2018/2019') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve original date range metadata');
    }
    if (($rangeManual['accessedDate']['display'] ?? null) !== '2026-06-04/2026-06-05') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve accessed date range metadata');
    }
    $rangeRule = $processor->item('range-rule');
    if (($rangeRule['eventDate']['display'] ?? null) !== '2025-01-01/2025-01-31') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve legal event date range metadata');
    }
    $titleReview = $processor->item('title-review');
    if (($titleReview['title'] ?? null) !== 'Migration Manual: Reviewer Packet Guide') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not compose title-review subtitle metadata');
    }
    if (($titleReview['shortTitle'] ?? null) !== 'Reviewer Guide') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve title-review short title metadata');
    }
    if (($titleReview['titleAddon'] ?? null) !== 'Draft source notes') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve title-review title addon metadata');
    }
    $chapterTitleReview = $processor->item('chapter-title-review');
    if (($chapterTitleReview['containerTitle'] ?? null) !== 'Migration Handbook: Import Desk Edition') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not compose chapter container subtitle metadata');
    }
    if (($chapterTitleReview['containerTitleAddon'] ?? null) !== 'Internal packet supplement') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve chapter container title addon metadata');
    }
    $journalDetail = $processor->item('journal-detail');
    if (($journalDetail['volume'] ?? null) !== '12' || ($journalDetail['issue'] ?? null) !== '3') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve journal volume/issue metadata');
    }
    if (($journalDetail['issn'] ?? null) !== '1234-5678' || ($journalDetail['archiveLocation'] ?? null) !== '2401.01234') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve journal identifier metadata');
    }
    $bookDetail = $processor->item('book-detail');
    if (($bookDetail['edition'] ?? null) !== '2nd') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve book edition metadata');
    }
    if (($bookDetail['collectionTitle'] ?? null) !== 'Source Review Series' || ($bookDetail['collectionNumber'] ?? null) !== '7') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve book series metadata');
    }
    if (($bookDetail['isbn'] ?? null) !== '978-1-2345-6789-0') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve book ISBN metadata');
    }
    $volumeChapter = $processor->item('volume-chapter');
    if (($volumeChapter['mainTitle'] ?? null) !== 'Migration Source Dossier: Multi-volume Reviewer Set') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve volume-chapter main title metadata');
    }
    if (($volumeChapter['mainTitleAddon'] ?? null) !== 'Internal archive packet') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve volume-chapter main title addon metadata');
    }
    if (($volumeChapter['numberOfVolumes'] ?? null) !== '4' || ($volumeChapter['chapterNumber'] ?? null) !== '7') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve volume/chapter number metadata');
    }
    if (($volumeChapter['part'] ?? null) !== '1' || ($volumeChapter['numberOfPages'] ?? null) !== '320') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve part/page-count metadata');
    }
    $dossierSet = $processor->item('dossier-set');
    if (($dossierSet['numberOfVolumes'] ?? null) !== '4') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve dossier-set volume count metadata');
    }
    $roleReview = $processor->item('role-review');
    if (($roleReview['originalAuthors'][0]['family'] ?? null) !== 'García') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve original author role metadata');
    }
    if (($roleReview['commentators'][1]['literal'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve literal commentator role metadata');
    }
    if (($roleReview['annotators'][0]['family'] ?? null) !== 'Ng') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve annotator role metadata');
    }
    if (($roleReview['introductionAuthors'][0]['nonDroppingParticle'] ?? null) !== 'de la') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve introduction name particle metadata');
    }
    if (($roleReview['forewordAuthors'][0]['family'] ?? null) !== 'Müller') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve foreword role metadata');
    }
    if (($roleReview['afterwordAuthors'][0]['family'] ?? null) !== 'Curator') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve afterword role metadata');
    }
    $secondaryEditorReview = $processor->item('secondary-editor-review');
    if (($secondaryEditorReview['compilers'][0]['family'] ?? null) !== 'Roe') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary compiler role metadata');
    }
    if (($secondaryEditorReview['compilers'][1]['literal'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve literal secondary compiler role metadata');
    }
    if (($secondaryEditorReview['editorialDirectors'][0]['family'] ?? null) !== 'Ng') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary editorial director metadata');
    }
    if (($secondaryEditorReview['editorialRoles'][2]['label'] ?? null) !== 'Reviewer') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve unknown secondary editor role label');
    }
    if (($secondaryEditorReview['editorialRoles'][2]['names'][0]['nonDroppingParticle'] ?? null) !== 'de la') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary reviewer name particles');
    }
    $nameAnnotationReview = $processor->item('name-annotation-review');
    if (($nameAnnotationReview['nameAddon'] ?? null) !== 'Imported source names verified by review desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve name annotation addendum metadata');
    }
    if (($nameAnnotationReview['authors'][0]['annotations'][0]['value'] ?? null) !== 'primary source author') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve first author name annotation metadata');
    }
    if (($nameAnnotationReview['authors'][1]['annotations'][0]['part'] ?? null) !== 'family') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve second author family annotation metadata');
    }
    if (($nameAnnotationReview['editors'][0]['annotations'][0]['value'] ?? null) !== 'review editor') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve editor name annotation metadata');
    }
    $importTool = $processor->item('import-tool');
    if (($importTool['version'] ?? null) !== '2.1.0-beta') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve software version metadata');
    }
    if (($importTool['status'] ?? null) !== 'preprint') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map software pubstate metadata');
    }
    $sourceDataset = $processor->item('source-dataset');
    if (($sourceDataset['version'] ?? null) !== '2025.4') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve dataset version metadata');
    }
    if (($sourceDataset['status'] ?? null) !== 'revised') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map dataset pubstate metadata');
    }

    foreach ([
        '<p>The source packet cites (see Smith 1899; Doe and Roe 2020, pp. 55-60).</p>',
        '<p>The reviewer queue keeps de la Cruz (2026) attached to imported source access notes.</p>',
        '<p>A proceedings child entry inherits Smith (2026) conference metadata for reviewer bibliographies.</p>',
        '<p>Accented .bib names such as Müller et al. (2026) remain readable in bibliography review.</p>',
        '<p>The xdata-backed glossary entry Ng (2026) keeps reviewer packet metadata attached.</p>',
        '<p>A BibLaTeX entry set Migration Review Set (2026) keeps data-only member summaries available for review.</p>',
        '<p>The related manual Curator (2024) keeps companion entry metadata attached to the source packet.</p>',
        '<p>A translated source García (2026) preserves original publication metadata for source review.</p>',
        '<p>Patent and legal sources Müller (2026) and WordPress Import Review Act (2025) preserve legal review metadata.</p>',
        '<p>Date-range sources de la Cruz (2020/2021) and Import Review Rule (2024/2025) preserve interval metadata for review.</p>',
        '<dt>Doe and Roe 2020</dt><dd>Doe, Jane; Roe, Pat. Field Notes. Journal of Imports. 2020. 55-60. https://example.test/field-notes. Accessed 2026-06-04.</dd>',
        '<dt>de la Cruz 2026</dt><dd>de la Cruz, Ana Maria, Jr. Source Packet. 2026. https://example.test/source-packet.</dd>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Packet Audit Trails. Migration Futures Conference. Review Press, 2026. 12-18.</dd>',
        '<dt>Müller et al. 2026</dt><dd>Müller, Mia; García, Gia; Søren Archive Team. Étude of Jalapeño Source Packets. Crème Brûlée Review. Revü Press, 2026. 7-9. https://example.test/accented.</dd>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Import Glossary. Migration Reference. Migration Desk, 2026. https://example.test/glossary.</dd>',
        '<dt>Migration Review Set 2026</dt><dd>Migration Review Set. 2026.</dd>',
        '<dt>Curator 2024</dt><dd>Curator, Eli. Migration Manual. 2024.</dd>',
        '<p>A review note source Ng (2026) keeps import audit notes and publication medium attached.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Review Packet Snapshot. 2026. Medium: Archived web packet. Note: Needs source-check before migration. Addendum: Queue imported by handoff. https://example.test/review-packet.</dd>',
        '<dt>García 2026</dt><dd>García, Gia. Migration Manual. Review Press, 2026. Translated by Curator, Eli; de la Cruz, Ana Maria. Original title: Manual de Migración. Original work published 2020-05. Original publisher: Archivo Press, Madrid. Original language: spanish.</dd>',
        '<dt>Müller 2026</dt><dd>Müller, Mia. Block Import Review Patent. 2026. Patent US-123456. Jurisdiction: US. Holder: WordPress Foundation. Event date 2024-01-15. Status: granted. https://example.test/patents/us-123456.</dd>',
        '<dt>WordPress Import Review Act 2025</dt><dd>WordPress Import Review Act. Oregon Legislature, 2025. Statute HB 42. Authority: Oregon Legislature. Jurisdiction: Oregon. Event date 2025-06-01.</dd>',
        '<dt>de la Cruz 2020/2021</dt><dd>de la Cruz, Ana Maria. Migration Release Window. Review Press, 2020/2021. Original work published 2018/2019. https://example.test/range-manual. Accessed 2026-06-04/2026-06-05.</dd>',
        '<dt>Import Review Rule 2024/2025</dt><dd>Import Review Rule. Migration Board, 2024/2025. Regulation Rule 7. Authority: Migration Board. Event date 2025-01-01/2025-01-31.</dd>',
        '<p>Title metadata sources Curator (2026) and Ng (2025) keep reviewer subtitles attached.</p>',
        '<dt>Curator 2026</dt><dd>Curator, Eli. Migration Manual: Reviewer Packet Guide. Draft source notes. Review Press, 2026.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Checklist: Attachment Review. Migration Handbook: Import Desk Edition. Internal packet supplement. 2025. 7-12.</dd>',
        '<p>Publication detail sources Doe (2026) and Curator (2025) preserve volume, issue, series, and identifier metadata.</p>',
        '<dt>Doe 2026</dt><dd>Doe, Jane. Detailed Field Notes. Journal of Imports. Vol. 12, no. 3. 2026. 20-30. DOI 10.5555/detail. ISSN 1234-5678. Archive: arXiv cs.DL 2401.01234.</dd>',
        '<dt>Curator 2025</dt><dd>Curator, Eli. Review Handbook. 2nd ed. Source Review Series, no. 7. Review Press, 2025. ISBN 978-1-2345-6789-0.</dd>',
        '<p>Multi-volume source Smith (2026) and dossier (Curator 2025) preserve main-title and volume-family metadata.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Review Checklist. Import Handbook: Volume Desk Edition. Main title: Migration Source Dossier: Multi-volume Reviewer Set. Main title addendum: Internal archive packet. Vol. 2 of 4. Part 1. Chap. 7. 320 pp. 2026. 33-39.</dd>',
        '<dt>Curator 2025</dt><dd>Curator, Eli. Migration Source Dossier: Multi-volume Reviewer Set. 4 vols. Review Press, 2025.</dd>',
        '<p>Role-rich source Smith (2026) keeps editorial review names attached.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Annotated Migration Manual. Review Press, 2026. Commentary by Roe, Pat; Migration Desk. Annotated by Ng, Nia. Introduction by de la Cruz, Ana Maria. Foreword by Müller, Mia. Afterword by Curator, Eli. Original author: García, Gia.</dd>',
        '<p>Secondary editor source Smith (2026) preserves compiler, editorial director, and reviewer roles.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Migration Source Dossier. Review Press, 2026. Compiled by Roe, Pat; Migration Desk. Editorial direction by Ng, Nia. Reviewer: de la Cruz, Ana Maria.</dd>',
        '<p>Annotated name source Smith and Ng (2026) keeps reviewer name annotations attached.</p>',
        '<dt>Smith and Ng 2026</dt><dd>Smith, Ada; Ng, Nia. Annotated Source Names. Review Press, 2026. Name addendum: Imported source names verified by review desk. Name annotations: Author 1: primary source author; Author 2 family: family name verified; Editor 1: review editor.</dd>',
        '<p>Software source Migration Desk (2026) and dataset (Ng 2025) preserve version and publication state metadata.</p>',
        '<dt>Migration Desk 2026</dt><dd>Migration Desk. Block Import Verifier. 2026. Version: 2.1.0-beta. Status: preprint. https://example.test/import-verifier.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Source Packet Dataset. 2025. Version: 2025.4. Status: revised. DOI 10.5555/dataset.</dd>',
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
