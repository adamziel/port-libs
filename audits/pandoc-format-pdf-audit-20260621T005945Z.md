# Pandoc Format and PDF Audit - 2026-06-21T00:59:45Z

## Registry Snapshot

- Upstream Pandoc input formats tracked: 51.
- Native PHP upstream input readers registered as partial: 28.
- Upstream input formats still unsupported: 23.
- Project-local non-upstream inputs: `pdf`, `doc`.
- Upstream output formats tracked: 75.
- Native PHP output formats still unsupported: 61.

Unsupported upstream inputs after this slice:

`asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `mediawiki`, `man`, `mdoc`, `muse`, `opml`, `org`, `pod`, `pptx`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `typst`, `vimwiki`, `xlsx`.

New in this slice:

- `xml`: registered `PortLibs\Pandoc\XmlReader` as a bounded XML reader.
- `jats`: registered `PortLibs\Pandoc\XmlReader` for bounded JATS article/book intake.
- `bits`: registered `PortLibs\Pandoc\XmlReader` for bounded BITS/book intake.

Follow-up after this audit:

- `html`: now dispatches through dedicated `PortLibs\Pandoc\HtmlReader`, preserving the current HTML-capable reader bridge while full HTML5 tree construction remains open.
- `docbook`: now dispatches through direct `PortLibs\Pandoc\DocBookReader`.

## Current Missing Work By Supported Format Family

- Markdown/CommonMark/GFM: broad local coverage exists, but the exact Pandoc extension disabling/enabling matrix, every variant edge, and complete upstream command fixture parity remain partial.
- HTML: now routes through dedicated `HtmlReader` dispatch with provenance metadata while delegating to the current HTML-capable bridge for existing DOM/raw-HTML/native-div/table/link/list coverage. A full HTML5 tree-construction reader remains missing.
- XML/JATS/BITS: now has a bounded reader for safe XML, titles, paragraphs, links, lists, JATS/BITS front matter, body sections, and tables. Missing full Pandoc XML/JATS reader parity, complete citation/reference materialization, nested section edge cases, figure/media payload mapping, and DocBook-specific XML semantics.
- DocBook: now has direct bounded DocBook XML reader dispatch for metadata, sections, paragraphs, lists, links/xrefs, CALS tables, media references, bibliography entries, and review packets. Full Pandoc DocBook reader parity remains open.
- DOCX/ODT/EPUB/DOC: package/container readers exist but remain partial on full office/layout fidelity, embedded object handling, full style compatibility, and complete upstream fixture parity.
- Bibliography formats: BibTeX, BibLaTeX, CSL JSON, RIS, and EndNote XML have bounded CSL item readers. Full Pandoc citation processor parity and every source-format edge remain open.
- JSON/native AST: current constructors are covered for the shared AST subset; complete Pandoc constructor parity remains open.
- CSV/TSV: table AST import is present; complete Pandoc option parity remains open.
- RTF: paragraph and inline style import is present; full control-word, destination, table, image, and metadata parity remains open.

## PDF Audit

PDF remains a project-local input, not an upstream Pandoc input token.

Current PHP PDF path:

- Uses markerPDF text extraction through `PdfReader`.
- Handles searchable text, font encodings/CMaps, encrypted content streams, object/xref streams, ActualText, marked-content artifacts, link annotations, structural provenance, simple heading/list/link/table mapping, positioned text table reconstruction, and filled-rectangle cell background propagation.
- Local problematic PDF smoke coverage currently reports 10 detected tables, 10 geometry tables, and 896 filled rectangles; the whitespace regression reproducer verifies spacing generically rather than depending on document-specific wording.

Remaining PDF gaps:

- Multi-page table continuation and repeated header detection.
- More exact table cell grid inference when text boxes and rectangle fills do not line up cleanly.
- Broader cell background propagation for complex clipping paths and non-rectangle paint operations.
- Full tagged-PDF semantic table precedence when tags and geometry disagree.
- Image-only/scanned PDF intake without OCR/model shell-outs. This remains behind a supplied-layout/OCR-result boundary, not a native OCR claim.
- Form/XFA, signatures, attachments beyond current metadata/reporting paths, and exact visual layout fidelity.

## Porting Plan

1. Finish the current XML-family reader slice: register `xml`, `jats`, and `bits`, emit shared AST blocks, and keep the registry marked `partial`.
2. Continue DocBook XML parity after the direct-reader dispatch slice: deepen namespace/version edges, bibliography/citation semantics, richer media-object selection, and exact Pandoc constructor parity.
3. Continue HTML after the dedicated dispatch slice: move more HTML reader internals behind `HtmlReader`, replace the current bridge with a fuller HTML5 tree-construction path where needed, and preserve existing raw/native-div/table/link/list coverage.
4. Continue PDF work in markerPDF/Pandoc PDF handoff slices: prioritize multi-page tables, more robust fill/background inference, and tagged-vs-geometry table reconciliation.
5. After XML/HTML/DocBook, select the next unsupported text-markup family with the highest reuse from existing readers: `rst`/`asciidoc`/wiki-style formats, unless the user re-prioritizes `pptx`, `xlsx`, or `typst`.

## Verification For This Slice

- `php -l lanes/pandoc/src/XmlReader.php`: passed.
- `php tools/run-tests.php lanes/pandoc/tests/XmlReaderTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`: 4 files, 6,441 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PdfReaderTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`: 2 files, 3,051 assertions, 0 failures.
- Exact-string guard for local PDF reproducer terms across `lanes/pandoc`, `lanes/markerpdf`, this audit, and `PANDOC_STATUS.md`: 0 executable-source/test hits.
- Direct local problematic-PDF smoke through `PandocConverter::read(..., 'pdf')`: 10 detected tables, 10 geometry tables, 896 filled rectangles, `geometry` reconstruction mode.
