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

The PDF alias entry @pdf-alias-source keeps JabRef-style attachment metadata visible for review.

A BibLaTeX entry set @migration-review-set keeps data-only member summaries available for review.

The related manual @related-manual keeps companion entry metadata attached to the source packet.

The xref chapter @xref-chapter keeps see-also parent metadata visible without crossref inheritance.

A review note source @review-note-source keeps import audit notes and publication medium attached.

Annotated source @annotated-source keeps private reviewer notes distinct from public abstracts.

A translated source @translated-manual preserves original publication metadata for source review.

Original subtitle source @original-subtitle-manual keeps original-title addenda visible for source review.

Original language list source @original-language-list-manual preserves source-language lists for review.

Primary language list source @primary-language-list-manual preserves source language lists for review.

Patent and legal sources @import-patent and @review-act preserve legal review metadata.

Date-range sources @range-manual and @range-rule preserve interval metadata for review.

Open-ended date source @open-ended-window preserves ongoing and predecessor interval metadata for review.

Season-coded source @seasonal-source preserves seasonal publication metadata for review.

Timestamped source @timestamped-source preserves imported date-part time metadata for review.

Approximate date source @circa-manual preserves review date markers.

Title metadata sources @title-review and @chapter-title-review keep reviewer subtitles attached.

Publication detail sources @journal-detail and @book-detail preserve volume, issue, series, and identifier metadata.

Publisher-list source @distributed-review and institutional packet [@institutional-packet] preserve multi-place publication metadata.

Abbreviated journal source @short-journal-detail preserves short journal metadata for review.

First-page metadata for @journal-detail keeps page-range review cues addressable.

Multi-volume source @volume-chapter and dossier [@dossier-set] preserve main-title and volume-family metadata.

Role-rich source @role-review keeps editorial review names attached.

Secondary editor source @secondary-editor-review preserves compiler, editorial director, and reviewer roles.

Secondary review role source @secondary-review-roles and introduction packet [@secondary-introduction-role] preserve commentator, annotator, foreword, introduction, and afterword aliases.

Redactor source @secondary-redactor-review preserves redactor aliases and name annotations.

Annotated name source @name-annotation-review keeps reviewer name annotations attached.

Shorthand source @shorthand-review and short editor source [@short-editor-review] keep compact citation labels visible.

Software source @import-tool and dataset [@source-dataset] preserve version and publication state metadata.

Licensed dataset @licensed-dataset keeps related license metadata visible for review.

Event paper @event-paper and proceedings [@event-proceedings] preserve conference metadata.

Organizer paper @organized-paper and webinar [@organizer-webinar] keep event review owners visible.

Localized event source @localized-event-paper keeps custom CSL event labels visible.

Alias source @legacy-alias-source resolves to one canonical bibliography item.

Subtype source @review-subtype preserves source-kind metadata for review.

Split URL date source @split-url-date preserves component access-date metadata.

URL description source @url-description-source keeps reviewer link labels visible for review.

Truncated author source @truncated-name-list keeps source-authored et-al markers visible.

Sort override sources [@sort-visible-adams; @sort-visible-zed] keep BibLaTeX sorting hints available for review.

Call-number source @archive-call-number preserves archive shelf metadata for review.

Pagination source @pagination-review preserves column page-unit metadata for review.

Special issue source @special-issue-review preserves imported issue title metadata for review.

Article-number source @article-number-review preserves imported electronic article IDs for review.

PubMed source @pubmed-review preserves imported medical database identifiers for review.

Media identifier source @media-identifier-review preserves film, score, and report identifiers for review.

Container-author chapter @container-author-review preserves source volume authors for review.

Reviewed work source @reviewed-work-review preserves reviewed-title, references, dimensions, and scale metadata for review.

Custom field source @custom-field-review preserves BibLaTeX user and verb fields for review.

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

@online{pdf-alias-source,
  author = {Ng, Nia},
  title  = {PDF Alias Source},
  date   = {2026-06-07},
  url    = {https://example.test/pdf-alias-source},
  pdf    = {Reviewer PDF:attachments/pdf-alias.pdf:application/pdf; Remote PDF:https://example.test/pdf-alias.pdf:application/pdf}
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

@collection{xref-dossier,
  options   = {dataonly},
  editor    = {Curator, Eli},
  title     = {Migration Source Dossier},
  date      = {2026},
  publisher = {Review Press}
}

@incollection{xref-chapter,
  author = {Ng, Nia},
  title  = {Xref Chapter Review},
  date   = {2025},
  pages  = {7--9},
  xref   = {xref-dossier, missing-dossier}
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

@online{annotated-source,
  author     = {Roe, Pat},
  title      = {Annotated Source Packet},
  date       = {2026-06-05},
  abstract   = {Public summary for imported source.},
  annotation = {Internal migration reviewer note.},
  url        = {https://example.test/annotated-source}
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

@book{original-subtitle-manual,
  author         = {Garc{\'i}a, Gia},
  translator     = {Curator, Eli},
  title          = {Migration Manual},
  origtitle      = {Manual de Migraci{\'o}n},
  origsubtitle   = {Archivo de Fuentes},
  origtitleaddon = {Edici{\'o}n revisada},
  date           = {2026},
  origdate       = {2020-05},
  publisher      = {Review Press},
  origpublisher  = {Archivo Press},
  origlocation   = {Madrid},
  language       = {english},
  origlanguage   = {spanish}
}

@book{original-language-list-manual,
  author        = {Smith, Ada},
  translator    = {Curator, Eli},
  title         = {Multilingual Source Manual},
  origtitle     = {Manual fuente},
  date          = {2026},
  origdate      = {2020},
  publisher     = {Review Press},
  origpublisher = {Archivo Press},
  origlocation  = {Madrid},
  language      = {english},
  origlanguage  = {spanish and basque and catalan}
}

@book{primary-language-list-manual,
  author    = {Smith, Ada},
  title     = {Primary Language Source},
  date      = {2026},
  publisher = {Review Press},
  language  = {english and french and spanish}
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

@book{open-ended-window,
  author    = {Smith, Ada},
  title     = {Open Source Review Window},
  date      = {2020/},
  origdate  = {/2018},
  publisher = {Review Press},
  url       = {https://example.test/open-ended-window},
  urldate   = {2026-06-01/}
}

@book{seasonal-source,
  author    = {{Season Desk}},
  title     = {Seasonal Source Packet},
  date      = {2026-22},
  origdate  = {1999-23},
  publisher = {Review Press},
  url       = {https://example.test/seasonal-source},
  urldate   = {2026-24}
}

@online{timestamped-source,
  author       = {{Timeline Desk}},
  title        = {Timestamped Source Capture},
  date         = {2026-06-05},
  hour         = {9},
  minute       = {15},
  second       = {30},
  timezone     = {+0200},
  origdate     = {2020},
  orighour     = {8},
  origminute   = {0},
  url          = {https://example.test/timestamped-source},
  urldate      = {2026-06-06},
  urlhour      = {14},
  urlminute    = {5},
  urltimezone  = {Z}
}

@book{circa-manual,
  author    = {Smith, Ada},
  title     = {Approximate Source Date},
  date      = {2026~},
  origdate  = {2020?},
  publisher = {Review Press},
  url       = {https://example.test/circa-manual},
  urldate   = {2026-06-05~}
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

@article{short-journal-detail,
  author       = {Doe, Jane},
  title        = {Abbreviated Field Notes},
  journaltitle = {Journal of Imported Sources},
  shortjournal = {J. Import. Sources},
  date         = {2026},
  pages        = {12--18},
  issn         = {2468-1357},
  url          = {https://example.test/short-journal}
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

@book{distributed-review,
  author        = {Curator, Eli},
  title         = {Distributed Source Review},
  date          = {2026},
  publisher     = {{Review Press} and {Archive Desk}},
  location      = {{New York} and {London}},
  origpublisher = {{Archivo Press} and {Migration Desk}},
  origlocation  = {{Madrid} and {Barcelona}},
  url           = {https://example.test/distributed-review}
}

@report{institutional-packet,
  author      = {Ng, Nia},
  title       = {Institutional Review Packet},
  date        = {2025},
  institution = {{Migration Board} and {Source Lab}},
  address     = {{Remote} and {Portland}}
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

@collection{secondary-review-roles,
  author      = {Smith, Ada},
  editora     = {Roe, Pat and {{Migration Desk}}},
  editoratype = {commentator},
  editorb     = {Ng, Nia},
  editorbtype = {annotator},
  editorc     = {de la Cruz, Ana Maria},
  editorctype = {foreword},
  title       = {Secondary Review Role Dossier},
  date        = {2026},
  publisher   = {Review Press}
}

@book{secondary-introduction-role,
  author      = {Curator, Eli},
  editora     = {M{\"u}ller, Mia},
  editoratype = {introduction},
  editorb     = {Garc{\'i}a, Gia},
  editorbtype = {afterword},
  title       = {Secondary Introduction Packet},
  date        = {2025}
}

@collection{secondary-redactor-review,
  author      = {Smith, Ada},
  editora     = {Roe, Pat and {{Migration Desk}}},
  editora+an  = {1=redacted source notes},
  editoratype = {redactor},
  title       = {Redacted Source Dossier},
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

@book{shorthand-review,
  author         = {Smith, Ada and Curator, Eli},
  shortauthor    = {{WIR Desk}},
  title          = {WordPress Import Review Manual},
  date           = {2026},
  publisher      = {Review Press},
  shorthand      = {WIR},
  shorthandintro = {cited as WordPress Import Review},
  label          = {Manual Label}
}

@collection{short-editor-review,
  editor      = {Roe, Pat and Ng, Nia},
  shorteditor = {{Review Editors}},
  title       = {Editor Label Source},
  date        = {2025},
  publisher   = {Review Press}
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

@misc{cc-by-4,
  options = {dataonly},
  title   = {Creative Commons Attribution 4.0 International},
  date    = {2013},
  url     = {https://creativecommons.org/licenses/by/4.0/}
}

@dataset{licensed-dataset,
  author      = {Ng, Nia},
  title       = {Licensed Source Dataset},
  date        = {2026},
  doi         = {10.5555/license-data},
  related     = {cc-by-4, missing-license},
  relatedtype = {license}
}

@proceedings{event-proceedings,
  editor          = {Curator, Eli},
  title           = {WordPress Import Conference Proceedings},
  eventtitle      = {WordCamp Migration Summit},
  eventtitleaddon = {Reviewer track},
  eventtype       = {conference},
  venue           = {Portland},
  eventdate       = {2026-06-04/2026-06-05},
  date            = {2026},
  publisher       = {Migration Desk}
}

@inproceedings{event-paper,
  author   = {Ng, Nia},
  title    = {Source Packet Event Review},
  pages    = {44--48},
  crossref = {event-proceedings}
}

@proceedings{organized-proceedings,
  editor        = {Curator, Eli},
  title         = {WordPress Import Organizer Proceedings},
  eventtitle    = {WordCamp Migration Summit},
  organization  = {{WordCamp Foundation} and {Migration Desk}},
  venue         = {Portland},
  eventdate     = {2026-06-04/2026-06-05},
  date          = {2026},
  publisher     = {Migration Desk Publications}
}

@inproceedings{organized-paper,
  author   = {Ng, Nia},
  title    = {Source Packet Organizer Review},
  pages    = {52--56},
  crossref = {organized-proceedings}
}

@online{organizer-webinar,
  author         = {Smith, Ada},
  title          = {Remote Review Webinar},
  eventtitle     = {Remote Import Clinic},
  eventorganizer = {{Review Team} and Curator, Eli},
  date           = {2025},
  url            = {https://example.test/organizer-webinar}
}

@inproceedings{localized-event-paper,
  author          = {Ng, Nia},
  title           = {Localized Event Paper},
  booktitle       = {Localized Proceedings},
  eventtitle      = {Source Review Summit},
  eventtitleaddon = {Import track},
  eventtype       = {atelier},
  eventorganizer  = {{Bureau de revue} and Curator, Eli},
  venue           = {Montreal},
  eventdate       = {2026-06-04/2026-06-05},
  date            = {2026},
  publisher       = {Migration Desk},
  pages           = {50--54}
}

@book{canonical-alias-source,
  author    = {{Alias Review Desk}},
  title     = {Canonical Alias Packet},
  date      = {2026},
  publisher = {Review Press},
  ids       = {legacy-alias-source, source-packet-alias}
}

@report{review-subtype,
  author       = {Ng, Nia},
  title        = {Source Audit Report},
  date         = {2026},
  type         = {white paper},
  entrysubtype = {migration source audit},
  institution  = {Migration Desk},
  url          = {https://example.test/subtype-report}
}

@online{split-url-date,
  author   = {Ng, Nia},
  title    = {Split URL Date Source},
  date     = {2026},
  url      = {https://example.test/split-url-date},
  urlyear  = {2026},
  urlmonth = jun,
  urlday   = {5}
}

@online{url-description-source,
  author         = {Ng, Nia},
  title          = {URL Description Source},
  date           = {2026},
  url            = {https://example.test/url-description-source},
  urldescription = {Reviewer mirror copy},
  urldate        = {2026-06-05}
}

@article{truncated-name-list,
  author       = {Smith, Ada and Ng, Nia and others},
  editor       = {Curator, Eli and others},
  title        = {Truncated Source Review},
  journaltitle = {Journal of Imports},
  date         = {2026},
  pages        = {10--12},
  url          = {https://example.test/truncated-name-list}
}

@book{sort-visible-zed,
  author    = {Zed, Zoe},
  sortname  = {Adams, Ari},
  title     = {Visible Zed Manual},
  sorttitle = {Alpha Sort Packet},
  date      = {2026},
  sortyear  = {2019},
  sortkey   = {001-sort-visible-zed}
}

@book{sort-visible-adams,
  author    = {Adams, Ada},
  sortname  = {Zed, Zoe},
  title     = {Visible Adams Manual},
  sorttitle = {Omega Sort Packet},
  date      = {2020},
  sortyear  = {2025},
  sortkey   = {900-sort-visible-adams}
}

@book{archive-call-number,
  author    = {Smith, Ada},
  title     = {Archive Shelf Packet},
  date      = {2026},
  publisher = {Review Press},
  library   = {NYPL Manuscripts Division, MS 42 Box 7 Folder 3}
}

@article{pagination-review,
  author         = {Ng, Nia},
  title          = {Column Pagination Review},
  journaltitle   = {Source Unit Ledger},
  date           = {2026},
  pages          = {12--14},
  pagination     = {column},
  bookpagination = {section}
}

@article{special-issue-review,
  author          = {Doe, Jane},
  title           = {Special Issue Packet},
  journaltitle    = {Journal of Source Imports},
  issuetitle      = {Migration Special Issue},
  issuesubtitle   = {Import Desk Reports},
  issuetitleaddon = {Editorial packet supplement},
  date            = {2026},
  pages           = {30--35}
}

@article{article-number-review,
  author       = {Roe, Pat},
  title        = {Electronic Article Packet},
  journaltitle = {Journal of Source Imports},
  date         = {2026},
  eid          = {e2026-77},
  doi          = {10.5555/eid-review}
}

@article{pubmed-review,
  author       = {Ng, Nia},
  title        = {PubMed Import Packet},
  journaltitle = {Journal of Source Imports},
  date         = {2026},
  pmid         = {12345678},
  pmcid        = {PMC1234567},
  doi          = {10.5555/pubmed-review}
}

@misc{media-identifier-review,
  author = {{Migration Media Desk}},
  title  = {Media Identifier Packet},
  date   = {2026},
  isan   = {0000-0000-D07A-0090-Q-0000-0000-X},
  ismn   = {979-0-060-11561-5},
  isrn   = {NISTIR 8202},
  iswc   = {T-034.524.680-1}
}

@incollection{container-author-review,
  author        = {Ng, Nia},
  bookauthor    = {Smith, Ada and Curator, Eli},
  bookauthor+an = {1=source volume author; 2:family=container family verified},
  title         = {Chapter Review},
  booktitle     = {Migration Sourcebook},
  date          = {2026},
  pages         = {44--49}
}

@review{reviewed-work-review,
  author         = {Roe, Pat},
  title          = {Review of Imported Block Patterns},
  reviewtitle    = {Block Patterns in the Wild},
  reviewsubtitle = {A Migration Source Atlas},
  references     = {Smith 2024, pp. 12-18},
  dimensions     = {24 x 32 cm},
  scale          = {1:50000},
  date           = {2026},
  journaltitle   = {Journal of Source Imports},
  pages          = {70--72}
}

@misc{custom-field-review,
  author = {Curator, Eli},
  title  = {Custom Field Packet},
  date   = {2026},
  usera  = {Migration batch 42},
  userb  = {Needs media review},
  userf  = {\mkbibemph{Reviewer escalation}},
  verba  = {wp:shortcode [gallery ids="1,2"]},
  verbc  = {source checksum sha256:abc123}
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
    $pdfAliasSource = $processor->item('pdf-alias-source');
    if (($pdfAliasSource['sourceFiles'][0]['path'] ?? null) !== 'attachments/pdf-alias.pdf') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve pdf alias attachment metadata');
    }
    if (array_column($pdfAliasSource['sourceFileDiagnostics'] ?? [], 'reason') !== ['remote-uri']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve pdf alias unsafe attachment diagnostics');
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
    if (($relatedManual['relatedKeys'] ?? null) !== ['migration-review-set', 'missing-related']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not normalize related manual relationship keys');
    }
    if (($relatedManual['relatedType'] ?? null) !== 'companion') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not normalize related manual relationship type');
    }
    if (($relatedManual['relatedString'] ?? null) !== 'Companion review set') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not normalize related manual relationship string');
    }
    if (($relatedManual['relatedItems'][0]['title'] ?? null) !== 'Migration Review Set') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize related manual relationship item');
    }
    if (($relatedManual['missingRelatedKeys'] ?? null) !== ['missing-related']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not normalize missing related keys');
    }
    if (($relatedManual['raw']['relatedType'] ?? null) !== 'companion') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve related manual relationship type');
    }
    if (($relatedManual['raw']['missingRelatedKeys'] ?? null) !== ['missing-related']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve missing related keys');
    }
    $xrefChapter = $processor->item('xref-chapter');
    if (($xrefChapter['xrefKeys'] ?? null) !== ['xref-dossier', 'missing-dossier']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not normalize xref chapter keys');
    }
    if (($xrefChapter['xrefItems'][0]['title'] ?? null) !== 'Migration Source Dossier') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize xref parent metadata');
    }
    if (($xrefChapter['missingXrefKeys'] ?? null) !== ['missing-dossier']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve missing xref keys');
    }
    if (($xrefChapter['containerTitle'] ?? null) !== '') {
        throw new RuntimeException('BibTeX CSL handoff self-test incorrectly inherited xref container metadata');
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
    $annotatedSource = $processor->item('annotated-source');
    if (($annotatedSource['abstract'] ?? null) !== 'Public summary for imported source.') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve annotated source abstract metadata');
    }
    if (($annotatedSource['annotation'] ?? null) !== 'Internal migration reviewer note.') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve annotated source reviewer annotation metadata');
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
    $originalSubtitleManual = $processor->item('original-subtitle-manual');
    if (($originalSubtitleManual['originalTitle'] ?? null) !== 'Manual de Migración: Archivo de Fuentes') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not compose original subtitle metadata');
    }
    if (($originalSubtitleManual['originalTitleAddon'] ?? null) !== 'Edición revisada') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve original title addendum metadata');
    }
    $originalLanguageListManual = $processor->item('original-language-list-manual');
    if (($originalLanguageListManual['originalLanguage'] ?? null) !== 'spanish; basque; catalan') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve original language list display metadata');
    }
    if (($originalLanguageListManual['originalLanguageList'] ?? null) !== ['spanish', 'basque', 'catalan']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve original language list metadata');
    }
    $primaryLanguageListManual = $processor->item('primary-language-list-manual');
    if (($primaryLanguageListManual['language'] ?? null) !== 'english; french; spanish') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve primary language list display metadata');
    }
    if (($primaryLanguageListManual['languageList'] ?? null) !== ['english', 'french', 'spanish']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve primary language list metadata');
    }
    if (($primaryLanguageListManual['raw']['language-list'] ?? null) !== ['english', 'french', 'spanish']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose raw primary language list metadata');
    }
    $languageStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="language"/>
        <text variable="language-list"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="language-list"/>
    </layout>
  </bibliography>
</style>
XML);
    $languageBlocks = (new WordPressBlockWriter())->write($languageStyled->appendBibliography(
        (new MarkdownReader())->read('Language list review [@primary-language-list-manual] keeps source languages visible.'),
        'Language Sources'
    ));
    if (!str_contains($languageBlocks, '<p>Language list review Smith | english; french; spanish | english; french; spanish keeps source languages visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render primary language list variables in custom citations');
    }
    if (!str_contains($languageBlocks, '<dt>Smith 2026</dt><dd>Primary Language Source | english; french; spanish</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render primary language list variables in custom bibliography output');
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
    $openEndedWindow = $processor->item('open-ended-window');
    if (($openEndedWindow['issuedDate']['display'] ?? null) !== '2020/' || ($openEndedWindow['issuedDate']['openEnded'] ?? null) !== 'end') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve open-ended issued date metadata');
    }
    if (($openEndedWindow['originalDate']['display'] ?? null) !== '/2018' || ($openEndedWindow['originalDate']['openEnded'] ?? null) !== 'start') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve open-ended original date metadata');
    }
    if (($openEndedWindow['accessedDate']['display'] ?? null) !== '2026-06-01/') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve open-ended access date metadata');
    }
    $seasonalSource = $processor->item('seasonal-source');
    if (($seasonalSource['issuedDate']['display'] ?? null) !== 'Summer 2026') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve seasonal issued date metadata');
    }
    if (($seasonalSource['accessedDate']['display'] ?? null) !== 'Winter 2026') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve seasonal access date metadata');
    }
    if (($seasonalSource['originalDate']['seasonName'] ?? null) !== 'Autumn') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve seasonal original date metadata');
    }
    if (($seasonalSource['dateSeasonSummary'] ?? null) !== 'Date seasons: issued Summer; accessed Winter; original-date Autumn') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize seasonal date metadata');
    }
    $timestampedSource = $processor->item('timestamped-source');
    if (($timestampedSource['issuedDate']['time'] ?? null) !== '09:15:30+02:00') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve issued date time metadata');
    }
    if (($timestampedSource['accessedDate']['time'] ?? null) !== '14:05Z') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve accessed date time metadata');
    }
    if (($timestampedSource['originalDate']['time'] ?? null) !== '08:00') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve original date time metadata');
    }
    if (($timestampedSource['dateTimeSummary'] ?? null) !== 'Date times: issued 09:15:30+02:00; accessed 14:05Z; original-date 08:00') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize date time metadata');
    }
    $circaManual = $processor->item('circa-manual');
    if (($circaManual['issuedDate']['circa'] ?? null) !== true) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve approximate issued date marker');
    }
    if (($circaManual['originalDate']['uncertain'] ?? null) !== true) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve uncertain original date marker');
    }
    if (($circaManual['dateMarkerSummary'] ?? null) !== 'Date markers: issued circa (2026~); accessed circa (2026-06-05~); original-date uncertain (2020?)') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize date markers for review');
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
    if (($journalDetail['pageFirst'] ?? null) !== '20') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve journal first-page metadata');
    }
    $shortJournalDetail = $processor->item('short-journal-detail');
    if (($shortJournalDetail['containerTitleShort'] ?? null) !== 'J. Import. Sources') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve short journal metadata');
    }
    if (($shortJournalDetail['journalAbbreviation'] ?? null) !== 'J. Import. Sources') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose journal abbreviation metadata');
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
    $distributedReview = $processor->item('distributed-review');
    if (($distributedReview['publisherList'] ?? null) !== ['Review Press', 'Archive Desk']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve distributed publisher literal-list metadata');
    }
    if (($distributedReview['publisherPlaceList'] ?? null) !== ['New York', 'London']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve distributed publisher-place literal-list metadata');
    }
    if (($distributedReview['originalPublisherList'] ?? null) !== ['Archivo Press', 'Migration Desk']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve distributed original publisher list metadata');
    }
    if (($distributedReview['originalPublisherPlaceList'] ?? null) !== ['Madrid', 'Barcelona']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve distributed original publisher-place list metadata');
    }
    $institutionalPacket = $processor->item('institutional-packet');
    if (($institutionalPacket['publisherList'] ?? null) !== ['Migration Board', 'Source Lab']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map institutional publisher literal-list metadata');
    }
    if (($institutionalPacket['publisherPlaceList'] ?? null) !== ['Remote', 'Portland']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map institutional publisher-place literal-list metadata');
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
    $secondaryReviewRoles = $processor->item('secondary-review-roles');
    if (($secondaryReviewRoles['commentators'][1]['literal'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary commentator alias metadata');
    }
    if (($secondaryReviewRoles['annotators'][0]['family'] ?? null) !== 'Ng') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary annotator alias metadata');
    }
    if (($secondaryReviewRoles['forewordAuthors'][0]['nonDroppingParticle'] ?? null) !== 'de la') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary foreword alias particles');
    }
    if (($secondaryReviewRoles['editorialRoles'][0]['label'] ?? null) !== 'Commentator') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary commentator role label');
    }
    $secondaryIntroductionRole = $processor->item('secondary-introduction-role');
    if (($secondaryIntroductionRole['introductionAuthors'][0]['family'] ?? null) !== 'Müller') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary introduction alias metadata');
    }
    if (($secondaryIntroductionRole['afterwordAuthors'][0]['family'] ?? null) !== 'García') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary afterword alias metadata');
    }
    $secondaryRedactorReview = $processor->item('secondary-redactor-review');
    if (($secondaryRedactorReview['redactors'][0]['family'] ?? null) !== 'Roe') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary redactor alias metadata');
    }
    if (($secondaryRedactorReview['redactors'][1]['literal'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve literal secondary redactor alias metadata');
    }
    if (($secondaryRedactorReview['redactors'][0]['annotations'][0]['value'] ?? null) !== 'redacted source notes') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary redactor name annotation metadata');
    }
    if (($secondaryRedactorReview['editorialRoles'][0]['label'] ?? null) !== 'Redactor') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve secondary redactor role label');
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
    $shorthandReview = $processor->item('shorthand-review');
    if (($shorthandReview['citationLabel'] ?? null) !== 'WIR') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve shorthand citation label metadata');
    }
    if (($shorthandReview['shorthandIntro'] ?? null) !== 'cited as WordPress Import Review') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve shorthand intro metadata');
    }
    if (($shorthandReview['shortAuthors'][0]['literal'] ?? null) !== 'WIR Desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve short author metadata');
    }
    $shortEditorReview = $processor->item('short-editor-review');
    if (($shortEditorReview['shortEditors'][0]['literal'] ?? null) !== 'Review Editors') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve short editor metadata');
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
    $licensedDataset = $processor->item('licensed-dataset');
    if (($licensedDataset['relatedType'] ?? null) !== 'license') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve license related type metadata');
    }
    if (($licensedDataset['relatedItems'][0]['title'] ?? null) !== 'Creative Commons Attribution 4.0 International') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize related license item metadata');
    }
    if (($licensedDataset['missingRelatedKeys'] ?? null) !== ['missing-license']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve missing license related keys');
    }
    $eventPaper = $processor->item('event-paper');
    if (($eventPaper['eventTitle'] ?? null) !== 'WordCamp Migration Summit') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve event paper event title metadata');
    }
    if (($eventPaper['eventTitleAddon'] ?? null) !== 'Reviewer track') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve event paper event title addendum metadata');
    }
    if (($eventPaper['eventType'] ?? null) !== 'conference') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve event paper event type metadata');
    }
    if (($eventPaper['eventPlace'] ?? null) !== 'Portland') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve event paper event place metadata');
    }
    if (($eventPaper['eventDate']['display'] ?? null) !== '2026-06-04/2026-06-05') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve event paper event date metadata');
    }
    $eventProceedings = $processor->item('event-proceedings');
    if (($eventProceedings['eventTitle'] ?? null) !== 'WordCamp Migration Summit') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve proceedings event title metadata');
    }
    $organizedPaper = $processor->item('organized-paper');
    if (($organizedPaper['eventOrganizers'][0]['literal'] ?? null) !== 'WordCamp Foundation') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not inherit organized-paper event organizer metadata');
    }
    if (($organizedPaper['eventOrganizers'][1]['literal'] ?? null) !== 'Migration Desk') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve organized-paper second event organizer');
    }
    if (($organizedPaper['publisher'] ?? null) !== 'Migration Desk Publications') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve organized-paper publisher metadata');
    }
    $organizerWebinar = $processor->item('organizer-webinar');
    if (($organizerWebinar['eventOrganizers'][0]['literal'] ?? null) !== 'Review Team') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve explicit webinar event organizer metadata');
    }
    if (($organizerWebinar['eventOrganizers'][1]['family'] ?? null) !== 'Curator') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not parse explicit webinar event organizer names');
    }
    $localizedEventPaper = $processor->item('localized-event-paper');
    if (($localizedEventPaper['eventTitle'] ?? null) !== 'Source Review Summit') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve localized-event source event title');
    }
    if (($localizedEventPaper['eventOrganizers'][0]['literal'] ?? null) !== 'Bureau de revue') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve localized-event source event organizer');
    }
    if (($localizedEventPaper['eventDate']['display'] ?? null) !== '2026-06-04/2026-06-05') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve localized-event source event date');
    }
    $localizedProcessor = CitationCslProcessor::fromBibtex($bibtex)->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text" default-locale="fr-FR">
  <info>
    <title>Localized Event Review Style</title>
    <id>https://example.test/styles/localized-event-review</id>
    <updated>2026-06-05T16:39:41+00:00</updated>
  </info>
  <locale xml:lang="fr-FR">
    <terms>
      <term name="event">Événement</term>
      <term name="event-title-addon">Supplément d'événement</term>
      <term name="event-type">Type d'événement</term>
      <term name="event-organizer">Organisateur</term>
      <term name="event-place">Lieu</term>
      <term name="event-date">Dates</term>
    </terms>
  </locale>
  <citation>
    <layout prefix="(" suffix=")" delimiter="; ">
      <group delimiter=" ">
        <names variable="author editor"/>
        <date variable="issued"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout/>
  </bibliography>
</style>
XML);
    $localizedBlocks = (new WordPressBlockWriter())->write($localizedProcessor->appendBibliography(
        (new MarkdownReader())->read('Localized event source @localized-event-paper keeps custom CSL event labels visible.'),
        'Works Cited'
    ));
    if (!str_contains($localizedBlocks, "<dt>Ng 2026</dt><dd>Ng, Nia. Localized Event Paper. Localized Proceedings. Événement: Source Review Summit. Supplément d&#039;événement: Import track. Type d&#039;événement: atelier. Organisateur: Bureau de revue; Curator, Eli. Lieu: Montreal. Dates 2026-06-04/2026-06-05. Migration Desk, 2026. 50-54.</dd>")) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render localized CSL event bibliography labels');
    }
    $canonicalAliasSource = $processor->item('canonical-alias-source');
    if (($canonicalAliasSource['citationAliases'] ?? null) !== ['legacy-alias-source', 'source-packet-alias']) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve canonical alias source ids metadata');
    }
    $legacyAliasSource = $processor->item('legacy-alias-source');
    if (($legacyAliasSource['id'] ?? null) !== 'canonical-alias-source') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not resolve legacy alias source to canonical id');
    }
    if (($legacyAliasSource['citationAlias'] ?? null) !== 'legacy-alias-source') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve legacy alias source key');
    }
    if ($processor->missingCitationIds((new MarkdownReader())->read('Alias [@legacy-alias-source] should resolve.')) !== []) {
        throw new RuntimeException('BibTeX CSL handoff self-test treated legacy alias source as missing');
    }
    $reviewSubtype = $processor->item('review-subtype');
    if (($reviewSubtype['genre'] ?? null) !== 'white paper') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve review subtype genre metadata');
    }
    if (($reviewSubtype['entrySubtype'] ?? null) !== 'migration source audit') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve review subtype entrysubtype metadata');
    }
    $splitUrlDate = $processor->item('split-url-date');
    if (($splitUrlDate['accessedDate']['display'] ?? null) !== '2026-06-05') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve split URL access date metadata');
    }
    if (($splitUrlDate['raw']['accessed']['date-parts'][0] ?? null) !== [2026, 6, 5]) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map split URL access date into raw CSL metadata');
    }
    $urlDescriptionSource = $processor->item('url-description-source');
    if (($urlDescriptionSource['urlLabel'] ?? null) !== 'Reviewer mirror copy') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve URL description label metadata');
    }
    if (($urlDescriptionSource['raw']['URL-label'] ?? null) !== 'Reviewer mirror copy') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose raw URL description label metadata');
    }
    $truncatedNameList = $processor->item('truncated-name-list');
    if (($truncatedNameList['authors'][2]['etAl'] ?? null) !== true) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map author others sentinel into CSL et-al metadata');
    }
    if (($truncatedNameList['editors'][1]['etAl'] ?? null) !== true) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not map editor others sentinel into CSL et-al metadata');
    }
    if ($processor->renderBibliographyEntry('truncated-name-list') !== 'Smith, Ada; Ng, Nia; et al. Truncated Source Review. Journal of Imports. 2026. 10-12. https://example.test/truncated-name-list.') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render source-authored et-al bibliography names');
    }
    $sortVisibleZed = $processor->item('sort-visible-zed');
    if (($sortVisibleZed['sortName'] ?? null) !== 'Adams, Ari') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve sort-visible-zed sort name');
    }
    if (($sortVisibleZed['sortTitle'] ?? null) !== 'Alpha Sort Packet') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve sort-visible-zed sort title');
    }
    if (($sortVisibleZed['sortYear'] ?? null) !== '2019') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve sort-visible-zed sort year');
    }
    if (($sortVisibleZed['sortKey'] ?? null) !== '001-sort-visible-zed') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve sort-visible-zed sort key');
    }
    $sortStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <sort>
      <key variable="author"/>
      <key variable="issued"/>
      <key variable="title"/>
    </sort>
    <layout prefix="[" suffix="]" delimiter="; ">
      <group delimiter=" | ">
        <names variable="author"/>
        <date variable="issued"/>
        <text variable="title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <sort>
      <key variable="author"/>
      <key variable="issued"/>
      <key variable="title"/>
    </sort>
    <layout delimiter=" :: ">
      <text variable="title"/>
      <text variable="sort-name"/>
      <text variable="sort-year"/>
      <text variable="sort-title"/>
      <text variable="sort-key"/>
    </layout>
  </bibliography>
</style>
XML);
    $sortBlocks = (new WordPressBlockWriter())->write($sortStyled->appendBibliography(
        (new MarkdownReader())->read('Sorted review [@sort-visible-adams; @sort-visible-zed] keeps visible metadata unchanged.'),
        'Sorted Sources'
    ));
    if (!str_contains($sortBlocks, '<p>Sorted review [Zed | 2026 | Visible Zed Manual; Adams | 2020 | Visible Adams Manual] keeps visible metadata unchanged.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not apply sort override fields to styled citation order');
    }
    $sortZedPosition = strpos($sortBlocks, '<dt>Zed 2026</dt><dd>Visible Zed Manual :: Adams, Ari :: 2019 :: Alpha Sort Packet :: 001-sort-visible-zed</dd>');
    $sortAdamsPosition = strpos($sortBlocks, '<dt>Adams 2020</dt><dd>Visible Adams Manual :: Zed, Zoe :: 2025 :: Omega Sort Packet :: 900-sort-visible-adams</dd>');
    if (!is_int($sortZedPosition) || !is_int($sortAdamsPosition) || $sortZedPosition > $sortAdamsPosition) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not apply sort override fields to styled bibliography order');
    }
    $archiveCallNumber = $processor->item('archive-call-number');
    if (($archiveCallNumber['callNumber'] ?? null) !== 'NYPL Manuscripts Division, MS 42 Box 7 Folder 3') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve archive call-number metadata');
    }
    if (($archiveCallNumber['raw']['call-number'] ?? null) !== 'NYPL Manuscripts Division, MS 42 Box 7 Folder 3') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose raw CSL call-number metadata');
    }
    $paginationReview = $processor->item('pagination-review');
    if (($paginationReview['pagination'] ?? null) !== 'column') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve pagination-review pagination metadata');
    }
    if (($paginationReview['bookPagination'] ?? null) !== 'section') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve pagination-review book pagination metadata');
    }
    $paginationStyled = $processor->withCslStyle(<<<'XML'
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
    $paginationBlocks = (new WordPressBlockWriter())->write($paginationStyled->appendBibliography(
        (new MarkdownReader())->read('Pagination review [@pagination-review] keeps page unit labels visible.'),
        'Pagination Sources'
    ));
    if (!str_contains($paginationBlocks, '<p>Pagination review columns 12-14 keeps page unit labels visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not apply pagination metadata to CSL page labels');
    }
    if (!str_contains($paginationBlocks, '<dt>Ng 2026</dt><dd>Column Pagination Review | cols. | 12-14 | section</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose pagination metadata in custom bibliography output');
    }
    $specialIssueReview = $processor->item('special-issue-review');
    if (($specialIssueReview['issueTitle'] ?? null) !== 'Migration Special Issue: Import Desk Reports') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve issue title metadata');
    }
    if (($specialIssueReview['issueTitleAddon'] ?? null) !== 'Editorial packet supplement') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve issue title addendum metadata');
    }
    $issueStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="issue-title"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="issue-title"/>
      <text variable="issue-title-addon"/>
    </layout>
  </bibliography>
</style>
XML);
    $issueBlocks = (new WordPressBlockWriter())->write($issueStyled->appendBibliography(
        (new MarkdownReader())->read('Special issue review [@special-issue-review] keeps issue titles visible.'),
        'Issue Sources'
    ));
    if (!str_contains($issueBlocks, '<p>Special issue review Doe | Migration Special Issue: Import Desk Reports keeps issue titles visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render issue-title metadata in custom citations');
    }
    if (!str_contains($issueBlocks, '<dt>Doe 2026</dt><dd>Special Issue Packet | Migration Special Issue: Import Desk Reports | Editorial packet supplement</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render issue-title metadata in custom bibliography output');
    }
    $articleNumberReview = $processor->item('article-number-review');
    if (($articleNumberReview['articleNumber'] ?? null) !== 'e2026-77') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve article-number metadata');
    }
    if (($articleNumberReview['raw']['article-number'] ?? null) !== 'e2026-77') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve raw article-number metadata');
    }
    $articleNumberStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="article-number"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="article-number"/>
      <text variable="doi"/>
    </layout>
  </bibliography>
</style>
XML);
    $articleNumberBlocks = (new WordPressBlockWriter())->write($articleNumberStyled->appendBibliography(
        (new MarkdownReader())->read('Article number review [@article-number-review] keeps article IDs visible.'),
        'Article Number Sources'
    ));
    if (!str_contains($articleNumberBlocks, '<p>Article number review Roe | e2026-77 keeps article IDs visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render article-number metadata in custom citations');
    }
    if (!str_contains($articleNumberBlocks, '<dt>Roe 2026</dt><dd>Electronic Article Packet | e2026-77 | 10.5555/eid-review</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render article-number metadata in custom bibliography output');
    }
    $pubmedReview = $processor->item('pubmed-review');
    if (($pubmedReview['pmid'] ?? null) !== '12345678') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve PMID metadata');
    }
    if (($pubmedReview['pmcid'] ?? null) !== 'PMC1234567') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve PMCID metadata');
    }
    if (($pubmedReview['raw']['PMID'] ?? null) !== '12345678' || ($pubmedReview['raw']['PMCID'] ?? null) !== 'PMC1234567') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose raw PubMed identifier metadata');
    }
    $pubmedStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="PMID"/>
        <text variable="PMCID"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="PMID"/>
      <text variable="PMCID"/>
    </layout>
  </bibliography>
</style>
XML);
    $pubmedBlocks = (new WordPressBlockWriter())->write($pubmedStyled->appendBibliography(
        (new MarkdownReader())->read('PubMed review [@pubmed-review] keeps identifiers visible.'),
        'PubMed Sources'
    ));
    if (!str_contains($pubmedBlocks, '<p>PubMed review Ng | 12345678 | PMC1234567 keeps identifiers visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render PubMed identifiers in custom citations');
    }
    if (!str_contains($pubmedBlocks, '<dt>Ng 2026</dt><dd>PubMed Import Packet | 12345678 | PMC1234567</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render PubMed identifiers in custom bibliography output');
    }
    $mediaIdentifierReview = $processor->item('media-identifier-review');
    if (($mediaIdentifierReview['isan'] ?? null) !== '0000-0000-D07A-0090-Q-0000-0000-X') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve ISAN metadata');
    }
    if (($mediaIdentifierReview['ismn'] ?? null) !== '979-0-060-11561-5') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve ISMN metadata');
    }
    if (($mediaIdentifierReview['isrn'] ?? null) !== 'NISTIR 8202') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve ISRN metadata');
    }
    if (($mediaIdentifierReview['iswc'] ?? null) !== 'T-034.524.680-1') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve ISWC metadata');
    }
    if (($mediaIdentifierReview['raw']['ISAN'] ?? null) !== '0000-0000-D07A-0090-Q-0000-0000-X') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not expose raw media identifier metadata');
    }
    $mediaIdentifierStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="ISAN"/>
        <text variable="ISMN"/>
        <text variable="ISRN"/>
        <text variable="ISWC"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="ISAN"/>
      <text variable="ISMN"/>
      <text variable="ISRN"/>
      <text variable="ISWC"/>
    </layout>
  </bibliography>
</style>
XML);
    $mediaIdentifierBlocks = (new WordPressBlockWriter())->write($mediaIdentifierStyled->appendBibliography(
        (new MarkdownReader())->read('Media identifier review [@media-identifier-review] keeps source IDs visible.'),
        'Media Identifier Sources'
    ));
    if (!str_contains($mediaIdentifierBlocks, '<p>Media identifier review Migration Media Desk | 0000-0000-D07A-0090-Q-0000-0000-X | 979-0-060-11561-5 | NISTIR 8202 | T-034.524.680-1 keeps source IDs visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render media identifiers in custom citations');
    }
    if (!str_contains($mediaIdentifierBlocks, '<dt>Migration Media Desk 2026</dt><dd>Media Identifier Packet | 0000-0000-D07A-0090-Q-0000-0000-X | 979-0-060-11561-5 | NISTIR 8202 | T-034.524.680-1</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render media identifiers in custom bibliography output');
    }
    $containerAuthorReview = $processor->item('container-author-review');
    if (($containerAuthorReview['containerAuthors'][0]['family'] ?? null) !== 'Smith') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve first container author family');
    }
    if (($containerAuthorReview['containerAuthors'][1]['family'] ?? null) !== 'Curator') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve second container author family');
    }
    if (($containerAuthorReview['containerAuthors'][1]['annotations'][0]['value'] ?? null) !== 'container family verified') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve container-author name annotation metadata');
    }
    $reviewedWorkReview = $processor->item('reviewed-work-review');
    if (($reviewedWorkReview['reviewedTitle'] ?? null) !== 'Block Patterns in the Wild: A Migration Source Atlas') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve reviewed-title metadata');
    }
    if (($reviewedWorkReview['references'] ?? null) !== 'Smith 2024, pp. 12-18') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve references metadata');
    }
    if (($reviewedWorkReview['dimensions'] ?? null) !== '24 x 32 cm') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve dimensions metadata');
    }
    if (($reviewedWorkReview['scale'] ?? null) !== '1:50000') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve scale metadata');
    }
    $reviewedMetadataStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
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
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="reviewed-title"/>
      <text variable="references"/>
      <text variable="dimensions"/>
      <text variable="scale"/>
    </layout>
  </bibliography>
</style>
XML);
    $reviewedMetadataBlocks = (new WordPressBlockWriter())->write($reviewedMetadataStyled->appendBibliography(
        (new MarkdownReader())->read('Reviewed metadata [@reviewed-work-review] keeps reviewed work fields visible.'),
        'Reviewed Work Sources'
    ));
    if (!str_contains($reviewedMetadataBlocks, '<p>Reviewed metadata Roe | Block Patterns in the Wild: A Migration Source Atlas | Smith 2024, pp. 12-18 | 24 x 32 cm | 1:50000 keeps reviewed work fields visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render reviewed-work metadata in custom citations');
    }
    if (!str_contains($reviewedMetadataBlocks, '<dt>Roe 2026</dt><dd>Review of Imported Block Patterns | Block Patterns in the Wild: A Migration Source Atlas | Smith 2024, pp. 12-18 | 24 x 32 cm | 1:50000</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render reviewed-work metadata in custom bibliography output');
    }
    $customFieldReview = $processor->item('custom-field-review');
    if (($customFieldReview['biblatexCustomFields']['usera'] ?? null) !== 'Migration batch 42') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve usera custom metadata');
    }
    if (($customFieldReview['biblatexCustomFields']['userf'] ?? null) !== 'Reviewer escalation') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not clean wrapped userf custom metadata');
    }
    if (($customFieldReview['biblatexCustomFields']['verba'] ?? null) !== 'wp:shortcode [gallery ids="1,2"]') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not preserve verba custom metadata');
    }
    if (($customFieldReview['biblatexCustomFieldSummary'] ?? null) !== 'usera: Migration batch 42; userb: Needs media review; userf: Reviewer escalation; verba: wp:shortcode [gallery ids="1,2"]; verbc: source checksum sha256:abc123') {
        throw new RuntimeException('BibTeX CSL handoff self-test did not summarize custom BibLaTeX metadata');
    }
    $customFieldStyled = $processor->withCslStyle(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" version="1.0" class="in-text">
  <citation>
    <layout>
      <group delimiter=" | ">
        <names variable="author"/>
        <text variable="usera"/>
        <text variable="verba"/>
      </group>
    </layout>
  </citation>
  <bibliography>
    <layout delimiter=" | ">
      <text variable="title"/>
      <text variable="biblatex-custom-field-summary"/>
    </layout>
  </bibliography>
</style>
XML);
    $customFieldBlocks = (new WordPressBlockWriter())->write($customFieldStyled->appendBibliography(
        (new MarkdownReader())->read('Custom fields [@custom-field-review] stay visible.'),
        'Custom Field Sources'
    ));
    if (!str_contains($customFieldBlocks, '<p>Custom fields Curator | Migration batch 42 | wp:shortcode [gallery ids=&quot;1,2&quot;] stay visible.</p>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render custom user/verba metadata in custom citations');
    }
    if (!str_contains($customFieldBlocks, '<dt>Curator 2026</dt><dd>Custom Field Packet | usera: Migration batch 42; userb: Needs media review; userf: Reviewer escalation; verba: wp:shortcode [gallery ids=&quot;1,2&quot;]; verbc: source checksum sha256:abc123</dd>')) {
        throw new RuntimeException('BibTeX CSL handoff self-test did not render custom user/verba metadata in custom bibliography output');
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
        '<dt>Ng 2026</dt><dd>Ng, Nia. Import Glossary. Migration Reference. Migration Desk, 2026. Keywords: wordpress; import; reviewer. https://example.test/glossary.</dd>',
        '<dt>Migration Review Set 2026</dt><dd>Migration Review Set. 2026. Entry set: Packet Audit Trails (2026); Archive Site (2026-05-31); missing: missing-source.</dd>',
        '<dt>Curator 2024</dt><dd>Curator, Eli. Migration Manual. 2024. Companion review set (companion): Migration Review Set (2026-06-05); missing: missing-related.</dd>',
        '<p>The xref chapter Ng (2025) keeps see-also parent metadata visible without crossref inheritance.</p>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Xref Chapter Review. 2025. 7-9. Xref: Migration Source Dossier (2026); missing: missing-dossier.</dd>',
        '<p>A review note source Ng (2026) keeps import audit notes and publication medium attached.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Review Packet Snapshot. 2026. Medium: Archived web packet. Note: Needs source-check before migration. Addendum: Queue imported by handoff. https://example.test/review-packet.</dd>',
        '<p>Annotated source Roe (2026) keeps private reviewer notes distinct from public abstracts.</p>',
        '<dt>Roe 2026</dt><dd>Roe, Pat. Annotated Source Packet. 2026. Annotation: Internal migration reviewer note. https://example.test/annotated-source.</dd>',
        '<dt>García 2026</dt><dd>García, Gia. Migration Manual. Review Press, 2026. Translated by Curator, Eli; de la Cruz, Ana Maria. Original title: Manual de Migración. Original work published 2020-05. Original publisher: Archivo Press, Madrid. Original language: spanish.</dd>',
        '<p>Original subtitle source García (2026) keeps original-title addenda visible for source review.</p>',
        '<dt>García 2026</dt><dd>García, Gia. Migration Manual. Review Press, 2026. Translated by Curator, Eli. Original title: Manual de Migración: Archivo de Fuentes. Original title addendum: Edición revisada. Original work published 2020-05. Original publisher: Archivo Press, Madrid. Original language: spanish.</dd>',
        '<p>Original language list source Smith (2026) preserves source-language lists for review.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Multilingual Source Manual. Review Press, 2026. Translated by Curator, Eli. Original title: Manual fuente. Original work published 2020. Original publisher: Archivo Press, Madrid. Original language: spanish; basque; catalan.</dd>',
        '<p>Primary language list source Smith (2026) preserves source language lists for review.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Primary Language Source. Review Press, 2026.</dd>',
        '<dt>Müller 2026</dt><dd>Müller, Mia. Block Import Review Patent. 2026. Patent US-123456. Jurisdiction: US. Holder: WordPress Foundation. Event date 2024-01-15. Status: granted. https://example.test/patents/us-123456.</dd>',
        '<dt>WordPress Import Review Act 2025</dt><dd>WordPress Import Review Act. Oregon Legislature, 2025. Statute HB 42. Authority: Oregon Legislature. Jurisdiction: Oregon. Event date 2025-06-01.</dd>',
        '<dt>de la Cruz 2020/2021</dt><dd>de la Cruz, Ana Maria. Migration Release Window. Review Press, 2020/2021. Original work published 2018/2019. https://example.test/range-manual. Accessed 2026-06-04/2026-06-05.</dd>',
        '<dt>Import Review Rule 2024/2025</dt><dd>Import Review Rule. Migration Board, 2024/2025. Regulation Rule 7. Authority: Migration Board. Event date 2025-01-01/2025-01-31.</dd>',
        '<p>Open-ended date source Smith (2020/) preserves ongoing and predecessor interval metadata for review.</p>',
        '<dt>Smith 2020/</dt><dd>Smith, Ada. Open Source Review Window. Review Press, 2020/. Original work published /2018. https://example.test/open-ended-window. Accessed 2026-06-01/.</dd>',
        '<p>Season-coded source Season Desk (2026) preserves seasonal publication metadata for review.</p>',
        '<dt>Season Desk 2026</dt><dd>Season Desk. Seasonal Source Packet. Review Press, 2026. Date seasons: issued Summer; accessed Winter; original-date Autumn. Original work published Autumn 1999. https://example.test/seasonal-source. Accessed Winter 2026.</dd>',
        '<p>Timestamped source Timeline Desk (2026) preserves imported date-part time metadata for review.</p>',
        '<dt>Timeline Desk 2026</dt><dd>Timeline Desk. Timestamped Source Capture. 2026. Date times: issued 09:15:30+02:00; accessed 14:05Z; original-date 08:00. Original work published 2020. https://example.test/timestamped-source. Accessed 2026-06-06.</dd>',
        '<p>Approximate date source Smith (2026) preserves review date markers.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Approximate Source Date. Review Press, 2026. Date markers: issued circa (2026~); accessed circa (2026-06-05~); original-date uncertain (2020?). Original work published 2020. https://example.test/circa-manual. Accessed 2026-06-05.</dd>',
        '<p>Title metadata sources Curator (2026) and Ng (2025) keep reviewer subtitles attached.</p>',
        '<dt>Curator 2026</dt><dd>Curator, Eli. Migration Manual: Reviewer Packet Guide. Draft source notes. Review Press, 2026.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Checklist: Attachment Review. Migration Handbook: Import Desk Edition. Internal packet supplement. 2025. 7-12.</dd>',
        '<p>Publication detail sources Doe (2026) and Curator (2025) preserve volume, issue, series, and identifier metadata.</p>',
        '<p>First-page metadata for Doe (2026) keeps page-range review cues addressable.</p>',
        '<dt>Doe 2026</dt><dd>Doe, Jane. Detailed Field Notes. Journal of Imports. Vol. 12, no. 3. 2026. 20-30. DOI 10.5555/detail. ISSN 1234-5678. Archive: arXiv cs.DL 2401.01234.</dd>',
        '<p>Publisher-list source Curator (2026) and institutional packet (Ng 2025) preserve multi-place publication metadata.</p>',
        '<dt>Curator 2026</dt><dd>Curator, Eli. Distributed Source Review. Review Press; Archive Desk, 2026. Publisher places: New York; London. Original publisher: Archivo Press; Migration Desk, Madrid; Barcelona. https://example.test/distributed-review.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Institutional Review Packet. Migration Board; Source Lab, 2025. Publisher places: Remote; Portland.</dd>',
        '<p>Abbreviated journal source Doe (2026) preserves short journal metadata for review.</p>',
        '<dt>Doe 2026</dt><dd>Doe, Jane. Abbreviated Field Notes. Journal of Imported Sources. Journal abbreviation: J. Import. Sources. 2026. 12-18. https://example.test/short-journal. ISSN 2468-1357.</dd>',
        '<dt>Curator 2025</dt><dd>Curator, Eli. Review Handbook. 2nd ed. Source Review Series, no. 7. Review Press, 2025. ISBN 978-1-2345-6789-0.</dd>',
        '<p>Multi-volume source Smith (2026) and dossier (Curator 2025) preserve main-title and volume-family metadata.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Review Checklist. Import Handbook: Volume Desk Edition. Main title: Migration Source Dossier: Multi-volume Reviewer Set. Main title addendum: Internal archive packet. Vol. 2 of 4. Part 1. Chap. 7. 320 pp. 2026. 33-39.</dd>',
        '<dt>Curator 2025</dt><dd>Curator, Eli. Migration Source Dossier: Multi-volume Reviewer Set. 4 vols. Review Press, 2025.</dd>',
        '<p>Role-rich source Smith (2026) keeps editorial review names attached.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Annotated Migration Manual. Review Press, 2026. Commentary by Roe, Pat; Migration Desk. Annotated by Ng, Nia. Introduction by de la Cruz, Ana Maria. Foreword by Müller, Mia. Afterword by Curator, Eli. Original author: García, Gia.</dd>',
        '<p>Secondary editor source Smith (2026) preserves compiler, editorial director, and reviewer roles.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Migration Source Dossier. Review Press, 2026. Compiled by Roe, Pat; Migration Desk. Editorial direction by Ng, Nia. Reviewer: de la Cruz, Ana Maria.</dd>',
        '<p>Secondary review role source Smith (2026) and introduction packet (Curator 2025) preserve commentator, annotator, foreword, introduction, and afterword aliases.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Secondary Review Role Dossier. Review Press, 2026. Commentary by Roe, Pat; Migration Desk. Annotated by Ng, Nia. Foreword by de la Cruz, Ana Maria.</dd>',
        '<dt>Curator 2025</dt><dd>Curator, Eli. Secondary Introduction Packet. 2025. Introduction by Müller, Mia. Afterword by García, Gia.</dd>',
        '<p>Redactor source Smith (2026) preserves redactor aliases and name annotations.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Redacted Source Dossier. Review Press, 2026. Name annotations: Redactor 1: redacted source notes. Redacted by Roe, Pat; Migration Desk.</dd>',
        '<p>Annotated name source Smith and Ng (2026) keeps reviewer name annotations attached.</p>',
        '<dt>Smith and Ng 2026</dt><dd>Smith, Ada; Ng, Nia. Annotated Source Names. Review Press, 2026. Name addendum: Imported source names verified by review desk. Name annotations: Author 1: primary source author; Author 2 family: family name verified; Editor 1: review editor.</dd>',
        '<p>Shorthand source WIR and short editor source (Review Editors 2025) keep compact citation labels visible.</p>',
        '<dt>WIR</dt><dd>Smith, Ada; Curator, Eli. WordPress Import Review Manual. Review Press, 2026.</dd>',
        '<dt>Review Editors 2025</dt><dd>Roe, Pat; Ng, Nia. Editor Label Source. Review Press, 2025.</dd>',
        '<p>Software source Migration Desk (2026) and dataset (Ng 2025) preserve version and publication state metadata.</p>',
        '<dt>Migration Desk 2026</dt><dd>Migration Desk. Block Import Verifier. 2026. Version: 2.1.0-beta. Status: preprint. https://example.test/import-verifier.</dd>',
        '<dt>Ng 2025</dt><dd>Ng, Nia. Source Packet Dataset. 2025. Version: 2025.4. Status: revised. DOI 10.5555/dataset.</dd>',
        '<p>Licensed dataset Ng (2026) keeps related license metadata visible for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Licensed Source Dataset. 2026. License: Creative Commons Attribution 4.0 International (2013); missing: missing-license. DOI 10.5555/license-data.</dd>',
        '<p>Event paper Ng (2026) and proceedings (Curator 2026) preserve conference metadata.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Source Packet Event Review. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event addendum: Reviewer track. Event type: conference. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk, 2026. 44-48.</dd>',
        '<dt>Curator 2026</dt><dd>Curator, Eli. WordPress Import Conference Proceedings. Event: WordCamp Migration Summit. Event addendum: Reviewer track. Event type: conference. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk, 2026.</dd>',
        '<p>Organizer paper Ng (2026) and webinar (Smith 2025) keep event review owners visible.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Source Packet Organizer Review. WordPress Import Organizer Proceedings. Event: WordCamp Migration Summit. Event organizer: WordCamp Foundation; Migration Desk. Event place: Portland. Event date 2026-06-04/2026-06-05. Migration Desk Publications, 2026. 52-56.</dd>',
        '<dt>Smith 2025</dt><dd>Smith, Ada. Remote Review Webinar. Event: Remote Import Clinic. Event organizer: Review Team; Curator, Eli. 2025. https://example.test/organizer-webinar.</dd>',
        '<p>Localized event source Ng (2026) keeps custom CSL event labels visible.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Localized Event Paper. Localized Proceedings. Event: Source Review Summit. Event addendum: Import track. Event type: atelier. Event organizer: Bureau de revue; Curator, Eli. Event place: Montreal. Event date 2026-06-04/2026-06-05. Migration Desk, 2026. 50-54.</dd>',
        '<p>Alias source Alias Review Desk (2026) resolves to one canonical bibliography item.</p>',
        '<dt>Alias Review Desk 2026</dt><dd>Alias Review Desk. Canonical Alias Packet. Review Press, 2026. Citation aliases: legacy-alias-source; source-packet-alias.</dd>',
        '<p>Subtype source Ng (2026) preserves source-kind metadata for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Source Audit Report. Migration Desk, 2026. Entry subtype: migration source audit. https://example.test/subtype-report.</dd>',
        '<p>Split URL date source Ng (2026) preserves component access-date metadata.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Split URL Date Source. 2026. https://example.test/split-url-date. Accessed 2026-06-05.</dd>',
        '<p>URL description source Ng (2026) keeps reviewer link labels visible for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. URL Description Source. 2026. URL label: Reviewer mirror copy. https://example.test/url-description-source. Accessed 2026-06-05.</dd>',
        '<p>Truncated author source Smith, Ng, et al. (2026) keeps source-authored et-al markers visible.</p>',
        '<dt>Smith, Ng, et al. 2026</dt><dd>Smith, Ada; Ng, Nia; et al. Truncated Source Review. Journal of Imports. 2026. 10-12. https://example.test/truncated-name-list.</dd>',
        '<p>Sort override sources (Adams 2020; Zed 2026) keep BibLaTeX sorting hints available for review.</p>',
        '<dt>Adams 2020</dt><dd>Adams, Ada. Visible Adams Manual. 2020.</dd>',
        '<dt>Zed 2026</dt><dd>Zed, Zoe. Visible Zed Manual. 2026.</dd>',
        '<p>Call-number source Smith (2026) preserves archive shelf metadata for review.</p>',
        '<dt>Smith 2026</dt><dd>Smith, Ada. Archive Shelf Packet. Review Press, 2026. Call number: NYPL Manuscripts Division, MS 42 Box 7 Folder 3.</dd>',
        '<p>Pagination source Ng (2026) preserves column page-unit metadata for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Column Pagination Review. Source Unit Ledger. 2026. 12-14. Pagination: column. Book pagination: section.</dd>',
        '<p>Special issue source Doe (2026) preserves imported issue title metadata for review.</p>',
        '<dt>Doe 2026</dt><dd>Doe, Jane. Special Issue Packet. Journal of Source Imports. Issue title: Migration Special Issue: Import Desk Reports. Issue title addendum: Editorial packet supplement. 2026. 30-35.</dd>',
        '<p>Article-number source Roe (2026) preserves imported electronic article IDs for review.</p>',
        '<dt>Roe 2026</dt><dd>Roe, Pat. Electronic Article Packet. Journal of Source Imports. 2026. Article number: e2026-77. DOI 10.5555/eid-review.</dd>',
        '<p>PubMed source Ng (2026) preserves imported medical database identifiers for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. PubMed Import Packet. Journal of Source Imports. 2026. DOI 10.5555/pubmed-review. PMID 12345678. PMCID PMC1234567.</dd>',
        '<p>Media identifier source Migration Media Desk (2026) preserves film, score, and report identifiers for review.</p>',
        '<dt>Migration Media Desk 2026</dt><dd>Migration Media Desk. Media Identifier Packet. 2026. ISAN 0000-0000-D07A-0090-Q-0000-0000-X. ISMN 979-0-060-11561-5. ISRN NISTIR 8202. ISWC T-034.524.680-1.</dd>',
        '<p>Container-author chapter Ng (2026) preserves source volume authors for review.</p>',
        '<dt>Ng 2026</dt><dd>Ng, Nia. Chapter Review. Migration Sourcebook. 2026. 44-49. Name annotations: Container author 1: source volume author; Container author 2 family: container family verified. Container author: Smith, Ada; Curator, Eli.</dd>',
        '<p>Reviewed work source Roe (2026) preserves reviewed-title, references, dimensions, and scale metadata for review.</p>',
        '<dt>Roe 2026</dt><dd>Roe, Pat. Review of Imported Block Patterns. Journal of Source Imports. 2026. 70-72. Reviewed title: Block Patterns in the Wild: A Migration Source Atlas. References: Smith 2024, pp. 12-18. Dimensions: 24 x 32 cm. Scale: 1:50000.</dd>',
        '<p>Custom field source Curator (2026) preserves BibLaTeX user and verb fields for review.</p>',
        '<dt>Curator 2026</dt><dd>Curator, Eli. Custom Field Packet. 2026. BibLaTeX custom fields: usera: Migration batch 42; userb: Needs media review; userf: Reviewer escalation; verba: wp:shortcode [gallery ids=&quot;1,2&quot;]; verbc: source checksum sha256:abc123.</dd>',
        '<p>Missing bibliography keys such as [@missing-source] remain visible for follow-up.</p>',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('BibTeX CSL handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    if (substr_count($blocks, '<dt>Alias Review Desk 2026</dt><dd>Alias Review Desk. Canonical Alias Packet. Review Press, 2026. Citation aliases: legacy-alias-source; source-packet-alias.</dd>') !== 1) {
        throw new RuntimeException('BibTeX CSL handoff self-test rendered duplicate alias bibliography entries');
    }

    echo "wordpress-bibtex-csl-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
