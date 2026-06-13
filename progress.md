| Project | Focus | State | Progress | PHP Tests | Mapped Upstream | Unmapped | Next Gate | Commit |
| --- | --- | --- | ---: | ---: | --- | ---: | --- | --- |
| [libsqlite](lanes/libsqlite/lane-status.json) | Primary | PHP green, upstream gap | 99.6% | 6,290,284 pass / 0 fail | [1,589 / 1,589 (100.0%)](lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | 16d8081 |
| [LightningCSS](lanes/lightningcss/lane-status.json) | Active | PHP green, upstream gap | 99.8% | 9,280 pass / 0 fail | [2,445 / 3,532 (69.2%)](lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json) | 1,087 | Full upstream runner closure is partial: bounded Rust media test and... | pending isolate... |
| [gitoxide](lanes/gitoxide/lane-status.json) | Active | High coverage | 98.8% | 11,183 pass / 0 fail | [1,821 / 2,886 (63.1%)](lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json) | 1,065 | Cargo workspace blocked by sparse target files | 29e9ab4 |
| [markerPDF](lanes/markerpdf/lane-status.json) | Active | PHP green, upstream gap | 100.0% | 3,621 pass / 0 fail | [763 / 78 (978.2%)](lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json) | 0 | No GPU/model execution will be run for markerPDF under current user d... | pending fast ba... |
| [Readability/content rewrite engine](lanes/readability/lane-status.json) | Backlog | Active port | 85.0% | 154 pass / 0 fail | [1,578 / 1,984 (79.5%)](lanes/readability/UPSTREAM_TEST_MANIFEST.json) | 406 | No local blocker | cd2e8a0 |
| [pandoc](lanes/pandoc/lane-status.json) | Backlog | High coverage | 96.0% | 3,318 pass / 0 fail | [3,277 / 2,276 (144.0%)](lanes/pandoc/UPSTREAM_TEST_MANIFEST.json) | 0 | Continue EPUB3 package reader parity after direct manifest/spine diagnostics | epub-direct-manifest-spine-report-2b00b60b67 |
| [quadrable](lanes/quadrable/lane-status.json) | Backlog | High coverage | 98.0% | 137 pass / 0 fail | [55 / 55 (100.0%)](lanes/quadrable/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | cd2e8a0 |
| [syncthing](lanes/syncthing/lane-status.json) | Backlog | PHP green, upstream gap | 99.0% | 350 pass / 0 fail | [350 / 658 (53.2%)](lanes/syncthing/UPSTREAM_TEST_MANIFEST.json) | 308 | No local blocker | cd2e8a0 |
| [difftastic](lanes/difftastic/lane-status.json) | Backlog | Active port | 80.0% | 279 pass / 0 fail | [272 / 586 (46.4%)](lanes/difftastic/UPSTREAM_TEST_MANIFEST.json) | 314 | Upstream runner parity unavailable | cd2e8a0 |
| [rclone](lanes/rclone/lane-status.json) | Backlog | High coverage | 98.0% | 512 pass / 0 fail | [512 / 2,553 (20.1%)](lanes/rclone/UPSTREAM_TEST_MANIFEST.json) | 2,041 | No local blocker | cd2e8a0 |
| [esbuild](lanes/esbuild/lane-status.json) | Backlog | Needs catch-up | 77.0% | 259 pass / 0 fail | [259 / 2,567 (10.1%)](lanes/esbuild/UPSTREAM_TEST_MANIFEST.json) | 2,308 | Release-extra upstream `make test-all` coverage remains static-only. | cd2e8a0 |
| [dolt](lanes/dolt/lane-status.json) | Parked | Parked | 69.0% | 249 pass / 0 fail | [315 / 613 (51.4%)](lanes/dolt/UPSTREAM_TEST_MANIFEST.json) | 298 | Parked | cd2e8a0 |

## Pandoc Input Format Shipping Matrix

Shipping target: native PHP input/import support. Pandoc output format parity is out of scope for this burn-down.
IPYNB/notebook input is explicitly skipped for this phase and is not counted below.
PDF import is not an upstream Pandoc input token, so it is tracked as an adjacent PDF ingestion target instead of part of the 51-format Pandoc denominator.

Input scope after skipping IPYNB:

| Scope | Count |
| --- | ---: |
| Upstream Pandoc input tokens in scope | 50 |
| Shippable native PHP input support | 1 |
| Partial native PHP input support to finish | 18 |
| Unsupported native PHP input tokens to implement | 31 |

Focused test counts below are evidence counters, not a strict remaining-test burn-down. Percentages above 100% mean the local PHP tests are more granular than the upstream case counter available for that family; they do not claim upstream runner parity.

| Input family | In-scope input tokens | Current input status | Local passing | Upstream denominator | Remaining input work |
| --- | --- | --- | ---: | ---: | --- |
| Markdown/CommonMark/GFM | `commonmark`, `commonmark_x`, `gfm`, `markdown`, `markdown_github`, `markdown_mmd`, `markdown_phpextra`, `markdown_strict` | partial | 444 | 1,096 | Escaped fenced-Div attributes and nested-list fixture round-trip are covered; complete extension and variant parity. |
| HTML/XML/JATS DOM | `html` partial; `xml`, `jats`, `bits` unsupported | mixed | 275 | 29 | JATS/BITS front-matter review packets are covered; finish HTML5 tree construction and implement full XML/JATS/BITS readers. |
| JSON/native AST | `json`, `native` | partial | 50 | 252 | Nullary helper payload validation, task-list checkbox sidecars, note label sidecars, and fixture writer handoff are preserved; complete broader JSON/native AST constructor coverage. |
| DOCX/OpenXML | `docx` | partial | 92 | 35 | Finish remaining direct WordprocessingML/package reader parity; section-property review metadata is covered. |
| EPUB/EPUB3 | `epub` | partial | 60 | 9 | Direct manifest suffix diagnostics and skipped spine-entry reporting are covered; finish broader EPUB package reader parity. |
| ODF/ODT/OpenDocument | `odt` | ship-ready | 50 | 20 | 0 critical gaps for native PHP ODT import; continue only non-critical hardening slices as discovered. |
| Shared ZIP/OPC package | dependency for package readers | partial dependency | 106 | 67 | Finish shared ZIP/OPC package ingestion used by DOCX, EPUB, ODT, PPTX, and XLSX. |
| CSL/BibTeX/BibLaTeX/csljson citations | `bibtex`, `biblatex`, `csljson`, `endnotexml`, `ris` | mixed | 77 | 8 | RIS now has bounded native CSL item parsing; EndNote XML, broader RIS coverage, and full reader-registry parity remain. |
| LaTeX/TeX/math | `latex` | partial | 20 | 14 | Finish LaTeX reader and math conversion parity. |
| DocBook/table geometry | `docbook` | partial | 16 | 16 | Finish DocBook XML reader parity. |
| RTF | `rtf` | partial | 4 | 3 | Finish RTF reader parity. |
| Typst | `typst` | unsupported | 47 | 17 | Implement Typst reader; package dependency conflict policy is covered, but current evidence is boundary/provenance only. |
| PPTX/XLSX | `pptx`, `xlsx` | unsupported | 0 | 2 | Implement native package readers after ZIP/OPC and XML package foundations. |
| Wiki/roff/text markup readers | `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`, `mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `vimwiki` | unsupported | 0 | 20 | Explicit unsupported ship-gate verdict is covered; implement the first native text-format reader. |
| Tabular/data readers | `csv`, `tsv` | partial | 2 | 2 | Finish full CSV/TSV reader parity beyond the bounded headed-table slice. |
| Unsupported input format surfaces | all unsupported input tokens above | unsupported | 0 | 31 | Close the remaining unsupported input registry rows. |

Adjacent import targets outside the Pandoc input denominator:

| Target | Current evidence | Scope note | Remaining input work |
| --- | ---: | --- | --- |
| PDF | 45 / 17 | Pandoc has `pdf` as an output target, not an input format. | Track as separate PDF import/markerPDF ingestion work. |
| Legacy DOC/CFB | 7 / 7 | Not a current upstream Pandoc input token. | Decide and track as separate legacy document import support. |
| IPYNB/notebook | skipped | Upstream Pandoc input token intentionally skipped for this phase. | No work in this burn-down. |

### Closure-Wave Evidence Snapshot (2026-06-13)

Current-main counters are reconciled through `2b00b60b67`: 3,318 PHP passes, 0 failures, and 3,277 mapped upstream cases out of the accepted 2,276-row static upstream inventory. Rows below summarize the recently landed closure-wave evidence; they are factual evidence counters, not global ship-ready claims. This refresh was checked with `jq empty`, `git diff --check`, syntax checks for the touched EPUB files, focused `EpubPackageReaderTest.php` (`1` file, `187` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74472` assertions, `0` failures).

| Surface | Evidence state | Upstream denominator | Local passing numerator | Ship verdict | Remaining critical gaps |
| --- | --- | ---: | ---: | --- | --- |
| CSV/TSV direct readers | Landed | 2 CSV command fixtures | 2 focused reader/registry files, 1,055 assertions | Partial after bounded reader slice | Broader CSV/TSV option behavior, malformed input diagnostics, and table-reader edge cases. |
| EPUB direct package reader | Landed manifest suffix diagnostics and skipped spine-entry reports | 9 EPUB package rows | 60 EPUB evidence cases; latest focused run 187 assertions | Partial | Broader direct EPUB package reader structural/content parity and upstream runner parity. |
| JSON/native table, list, and block handoff | Landed table-caption, note-label, Div Plain, task-list sidecar, and nullary helper payload slices | 252 JSON/native artifacts | 50 JSON/native cases; latest focused run 2,097 assertions | Partial | Broader native/json fixture parity, unsupported constructors, and table/citation/metadata round trips. |
| Markdown block boundaries | Landed nested fenced Div, escaped fenced-Div attributes, and mixed-content nested-list slices | 1,096 Markdown-family rows | 444 Markdown-family cases; latest focused MarkdownReader run 6,635 assertions | Partial | Markdown/CommonMark/GFM extension and variant parity. |
| Media linked-resource handoff | Landed | 1 mapped cross-format resource slice | 1 focused `MediaBag` case, 144 assertions | Covered bounded handoff; not an input-format ship gate | Wider media/resource edge cases outside opt-in linked-resource handoff. |
| Notes/references | Landed footnote label anchors, WordPress backlink metadata, and JSON/native note-label sidecars | 252 JSON/native artifacts plus notes/reference handoff slices | Focused Markdown/DOCX run 11,750 assertions; latest JSON/native run 1,978 assertions | Partial | Broader note/reference placement, endnote grouping, anchor round-trips, and constructor parity. |
| Wiki/roff/text reader ship gate | Landed explicit unsupported verdict for 20 text markup reader input tokens | 20 wiki/roff/man/text markup tokens | Focused registry run 1,106 assertions | Unsupported | Implement native readers after registry-level ship-gate accounting. |
| Table geometry | Landed LaTeX table-foot longtable and body-local head-row handoffs | Table writer geometry slice | Focused LaTeX/table run 1,917 assertions | Covered bounded table-foot and body-head-row slices | Multi-body semantics, rowspan output, and package-specific table internals. |
| PDF/Typst dependency policy | Landed package dependency conflict policy | 17 PDF/Typst boundary rows | 47 PDF/Typst boundary/provenance cases; latest focused run 2,225 assertions | Covered bounded no-engine provenance | Real PDF/Typst output parity remains unsupported without external engines. |

### Markdown Fixture Round-Trip Update (2026-06-13)

Bounded native PHP Markdown/JSON-native fixture coverage advanced by one cross-format round-trip slice. `PandocJsonWriter` and `NativeWriter` now flush mixed inline runs to `Plain` blocks before nested list blocks, preserving the existing `wordpress-import-markdown.md` nested task-list fixture through Pandoc JSON/native writer output, JSON reader import, Markdown writer output, and WordPress block output.

Verification passed `php -l` for `PandocJsonWriter.php`, `NativeWriter.php`, and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6626` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74167` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, office suite, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### CSV/TSV Direct Text Reader Update (2026-06-13)

Verdict: partial, not ship-ready. The native PHP lane now has 2 local passing CSV/TSV reader cases against the 2 CSV command fixture rows recorded in the accepted static upstream inventory (`test/command/01.csv` and `test/command/3533-rst-csv-tables.csv`).

Implemented gap: `DelimitedTextReader` parses bounded headed CSV and TSV input into shared table AST nodes, treats the first row as a header, pads ragged rows, attaches `delimitedText` and `tableGeometry` review packets, and proves Markdown, WordPress table, and Pandoc JSON table export. Registry status for `csv` and `tsv` moved from unsupported to partial.

Verification passed `php -l` for `DelimitedTextReader.php`, `DelimitedTextReaderTest.php`, `PandocFormatRegistry.php`, and `PandocFormatRegistryTest.php`; focused `DelimitedTextReaderTest.php` plus `PandocFormatRegistryTest.php` passed (`2` files, `1055` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74205` assertions, `0` failures). No Pandoc binary, spreadsheet application, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

Remaining critical gap: finish full Pandoc CSV/TSV reader parity, including broader upstream fixture hydration, option behavior, malformed input diagnostics, and table-reader edge cases.

### JSON/Native Note Label Sidecar Update (2026-06-13)

Bounded native PHP notes/references handoff advanced by one JSON/native AST slice after recovering prior `plib-y2ua1` evidence. `PandocJsonReader` and `NativeReader` now hydrate valid `Note` `noteLabel` sidecars into shared note labels, and `PandocJsonWriter` plus `NativeWriter` emit `noteLabel` only for valid labelled notes while unlabelled `Note` constructors stay standard.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `1978` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74215` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### ODT Configuration Package Metadata Update (2026-06-12)

Bounded native PHP ODF/ODT package ingestion advanced by one metadata-only Configurations2 slice. `OpenDocumentPackage::summarize()` now exposes `packageConfigurations` alongside manifest review and package inventory, preserving declared, undeclared, missing, encrypted, invalid-media-type, and directory buckets plus configuration area/kind/path, byte/CRC/compression, byte-exposure, and review-policy provenance while keeping configuration payloads out of document media handoff.

Verification passed `php -l` for `OpenDocumentPackage.php` and `OpenDocumentPackageTest.php`; focused `OpenDocumentPackageTest.php` passed (`1` file, `1154` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74156` assertions, `0` failures). No Pandoc, office suite, zip/unzip, ZipArchive, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### Markdown Nested Fenced Div Update (2026-06-12)

Bounded native PHP block-structure handoff advanced by one Markdown/Native/WordPress slice. MarkdownReader now keeps `:::` fenced Div openings and closings out of definition-list marker parsing after preceding paragraphs, and MarkdownWriter sizes outer Div fences from colon runs in the rendered body so nested Divs round-trip.

Verification passed `php -l` for `MarkdownReader.php`, `MarkdownWriter.php`, and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6615` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74068` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### Markdown Fenced Div Escaped Attribute Update (2026-06-13)

Bounded native PHP block-structure handoff advanced by one Markdown/Native/WordPress slice. `MarkdownReader` now scans fenced Div key-value attributes with quoted escaped delimiters, preserving quote-bearing data attributes through Markdown read/write, native JSON attr tuples, and WordPress HTML attribute handoff.

Verification passed `php -l` for `MarkdownReader.php` and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6635` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74345` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, Typst, TeX/PDF engine, browser renderer, Node tooling, office suite, online service, live provider, or external validator was invoked.

### Markdown/WordPress Footnote Label Update (2026-06-12)

Bounded native PHP notes handoff advanced by one cross-format slice. MarkdownWriter now preserves valid source footnote labels with collision handling, and WordPressBlockWriter uses label-derived `fn`/`fnref` anchors plus `data-pandoc-note-label` provenance for named source notes while generated inline notes stay numeric.

Verification passed `php -l` for `MarkdownWriter.php`, `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and `CitationCslProcessorTest.php`; focused `MarkdownReaderTest.php` and `CitationCslProcessorTest.php` passed (`2` files, `11909` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74054` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### Markdown/WordPress Footnote Backlink Update (2026-06-13)

Bounded native PHP notes handoff advanced by one WordPress writer slice. `WordPressBlockWriter` now emits footnote backlinks with `class="footnote-back"` and `role="doc-backlink"` while preserving label-derived anchors, generated numeric anchors, `data-pandoc-note-label` provenance, and link serialization inside note bodies.

Verification passed `php -l` for `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and `DocxReaderTest.php`; focused `MarkdownReaderTest.php` plus `DocxReaderTest.php` passed (`2` files, `11750` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74349` assertions, `0` failures). No Pandoc, browser renderer, Node tooling, TeX engine, office suite, online service, live provider, or external validator was invoked.

### Text Markup Reader Ship-Gate Update (2026-06-13)

Bounded native PHP registry accounting now records an executable unsupported ship-gate verdict for 20 wiki/roff/man/text markup reader input tokens: `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`, `mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, and `vimwiki`. `PandocFormatRegistry::textMarkupReaderShipGate()` reports upstream denominator `20`, local passing numerator `0`, unsupported count `20`, family buckets `wiki=7`, `roff-manual=2`, `text-markup=11`, and direct reader parity `false`.

Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `1106` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74434` assertions, `0` failures). No Pandoc binary, wiki renderer, roff renderer, Cabal/Haskell runner, browser renderer, external validator, online service, live provider, or live-service provider test was invoked.

### Markdown Adjacent List Boundary Update (2026-06-12)

Bounded native PHP list handoff advanced by one Markdown reader/writer slice. MarkdownWriter now emits Pandoc empty-comment separators only where adjacent list blocks would merge, and MarkdownReader consumes those separators only after a parsed list before another bullet or ordered marker, definition-list term, or indented code block. Ordinary raw HTML comments remain raw HTML.

Verification passed `php -l` for `MarkdownReader.php`, `MarkdownWriter.php`, and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6595` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74048` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### Media/Resource Link Handoff

MediaBag linked-resource closure on 2026-06-12: supplied or preloaded link targets are now opt-in media resources alongside images, so Markdown and native JSON document links can be extracted and rebased with the same provenance used for image media.

| Check | Evidence |
| --- | --- |
| Upstream mapped denominator | `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` moves 3,259 -> 3,260 mapped cases. |
| Local passing numerator | `lanes/pandoc/lane-status.json` moves phpPass 3,299 -> 3,300; phpFail remains 0. |
| Cross-format surface | `MediaBag` now handles link targets from MarkdownReader and PandocJsonReader/PandocJsonWriter, with MarkdownWriter and WordPressBlockWriter preserving `data-pandoc-media-*` provenance. |
| Focused verification | `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php` passed 1 file, 144 assertions, 0 failures. |
| Full verification | `php tools/run-tests.php lanes/pandoc/tests` passed 44 files, 74,034 assertions, 0 failures. |
| External validators | No Pandoc binary, office suite, TeX/Typst engine, browser renderer, Node tooling, online service, live provider, or external validator was invoked. |

### ODF/ODT Ship Readiness

Format-specific closure on 2026-06-12: native PHP ODF/ODT input is shippable
for OpenDocument text packages under the current no-external-validator policy.

| Check | Evidence |
| --- | --- |
| Upstream format-related denominator | 20 ODF/ODT mapped upstream cases, 575 assertion targets in `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`. |
| Local passing numerator | 50 current mapped ODF/ODT cases and 1,546 focused assertions across `lanes/pandoc/lane-status.json` plus manifest-carried ODF/ODT counters. |
| Coverage percent | 250.0% by mapped case slices, 268.9% by focused assertions. Percentages above 100% reflect local PHP slices being more granular than the upstream inventory rows. |
| Local focused test files | `OdfReaderTest.php`, `OdtReaderTest.php`, `OpenDocumentReaderTest.php`, and `OpenDocumentPackageTest.php` cover 219 focused ODF/ODT test cases. |
| Manifest/package ingestion | Root mimetype validation, `META-INF/manifest.xml`, directory declarations, raw ZIP name/order provenance, URI-encoded paths, declared sizes, custom attributes, media-type parameters, missing/encrypted/unsupported bytes, thumbnails, signatures, Configurations2, scripts, RDF, object replacements, and embedded object packages. |
| Styles/content ingestion | Styles, duplicate/missing style diagnostics, list styles, headings, paragraphs, sections, tables, captions, annotations, fields, forms, indexes, metadata fields, package settings, embedded images, MathML objects, charts, OLE placeholders, and Markdown/WordPress writer handoff. |
| Uncovered upstream tests | 0 critical uncovered ODF/ODT upstream rows for the assigned native PHP import scope. |
| Failing or missing critical behavior | None known in local ODF/ODT coverage; `phpFail` remains 0. |
| Verification | `jq empty lanes/pandoc/lane-status.json`, `git diff --check -- progress.md lanes/pandoc/lane-status.json`, focused ODF/ODT suite (`4` files, `5,827` assertions, `0` failures), and full `lanes/pandoc/tests` (`44` files, `73,816` assertions, `0` failures). |
| Ship verdict | Shippable for native PHP ODT package import and downstream writer handoff. Defer non-critical future hardening to newly discovered format-specific beads rather than keeping this format blocked. |

### DOCX/OpenXML Ship-Readiness Update (2026-06-12)

Verdict: not yet shippable as full Pandoc DOCX reader parity; bounded native reader coverage advanced by one section-property slice.

| Area | Evidence |
| --- | --- |
| Upstream input denominator | 35 DOCX/OpenXML rows in the accepted static Pandoc inventory. |
| Local passing evidence | 92 native PHP DOCX/OpenXML-focused cases, 262.9% of the coarse upstream denominator. |
| Newly covered gap | `w:sectPr` section-property review metadata for section type, header/footer references and diagnostics, page size, margins, columns, page numbering, document grid, title-page, and package summary counters. |
| Remaining critical gaps | Full direct WordprocessingML reader parity still needs broader field, content-control, revision, list, table, and package edge-case coverage; writer parity remains out of scope for this input burn-down. |
| External tooling | No Pandoc binary, Word, LibreOffice, office suite, zip/unzip, ZipArchive, browser renderer, online service, live provider test, or external validator was invoked for this slice. |

### EPUB3 Package Closure Update (2026-06-12)

| Surface | Evidence | Verdict |
| --- | --- | --- |
| EPUB3 package reader | Upstream denominator 9; local passing evidence 59; mapped evidence 655.6% of the static denominator. | Partial, not shippable. |
| Latest closed gap | NCX `docTitle`/`docAuthor` entries now preserve text attributes plus local, missing, and remote audio-label provenance in document metadata and import reports. | Covered by one focused native PHP test with 39 assertions. |
| Remaining critical gaps | Direct EPUB package reader parity still needs broader structural/content coverage, and no upstream Pandoc runner, EPUBCheck, browser renderer, or external validator was executed. | Keep EPUB3 in partial status. |

### EPUB Direct Manifest Spine Report Update (2026-06-13)

Bounded native PHP EPUB3 direct package-reader coverage advanced by one manifest/spine diagnostic slice. `EpubPackageReader` now preserves OPF manifest query and fragment suffix diagnostics, external href diagnostics, missing package-part diagnostics, manifest reports, and spine readability/skipped-entry reports while keeping readable local XHTML handoff from aborting on external or missing linear itemrefs.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `187` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74472` assertions, `0` failures). No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider, or live-service provider test was invoked.

### XML/HTML5/JATS DOM Ship-Readiness Update (2026-06-12)

Verdict: not shippable yet. The native PHP lane now has 275 local passing XML/HTML/JATS/DocBook DOM evidence cases against 29 upstream format-related cases (948.3% evidence ratio; local cases are intentionally more granular than the upstream denominator), but `html` remains partial and `xml`, `jats`, and `bits` are still not registered as full direct readers.

| Format surface | Upstream format-related tests | Local passing evidence | Remaining critical gap |
| --- | ---: | ---: | --- |
| HTML5 DOM/parser and writer boundary | covered in the 29-case family | broad DOM, table, escaping, attribute, metadata, and WordPress handoff coverage | Complete HTML5 tree-construction parity and upstream runner comparison. |
| XML safe DOM primitives | covered in the 29-case family | safe XML loading, namespace queries, fragment serialization, declaration/DTD/PI rejection | Implement full Pandoc `xml` input reader mapping into the shared AST. |
| JATS/BITS XML-ish inputs | covered in the 29-case family | new bounded JATS/BITS front-matter review packet | Implement full JATS/BITS body, back matter, tables, figures, references, and citation-reader parity. |
| DocBook XML-ish tables | covered in the 29-case family | DocBook table geometry and WordPress handoff coverage | Finish full DocBook reader parity beyond table geometry. |

Implemented highest-impact gap: `XmlHtmlDom::summarizeJatsFrontMatter()` now emits safe XML review packets for JATS `article` and BITS `book`/`book-part` front matter: root attributes, document type, DTD version, language, titles, identifiers, abstracts, keywords, contributors, publication dates, section summaries, xref targets, references, figures, table-wraps, book-part counts, and an explicit `directReaderParity=false` marker. Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`, focused `XmlHtmlDomTest.php` (`1` file, `1828` assertions, `0` failures), adjacent DOM/table tests (`6` files, `6256` assertions, `0` failures), and full `lanes/pandoc/tests` (`44` files, `73941` assertions, `0` failures). No Pandoc binary, browser renderer, external XML validator, online service, live provider test, or live-service provider test was invoked.

### Citation/Bibliography Data Format Ship-Readiness Update (2026-06-12)

Verdict: not shippable yet. The native PHP lane now has 77 local passing CSL/BibTeX/BibLaTeX/csljson/RIS evidence cases against 8 upstream citation/bibliography format-related rows (962.5% evidence ratio; local cases are intentionally more granular than the upstream denominator).

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| CSL/BibTeX/BibLaTeX/csljson/RIS/EndNote XML | 8 | 77 | 962.5% | Bounded RIS parsing now exists in native PHP, but EndNote XML is still unsupported and RIS needs broader tag coverage plus registry-level reader parity before this family can ship. |

Implemented highest-impact gap: `CitationCslProcessor::risItems()` and `fromRis()` now parse bounded RIS article and report records through normalized CSL items, default citation clusters, bibliography entries, and WordPress review blocks for `TY`, `ID`, `AU`, `TI`, `T2`, `PY`, `VL`, `IS`, `SP`, `EP`, `DO`, `UR`, `KW`, `PB`, `CY`, and `N1` fields. Verification passed `php -l` for `CitationCslProcessor.php` and `CitationCslProcessorTest.php`, focused `CitationCslProcessorTest.php` (`1` file, `5308` assertions, `0` failures), and full `lanes/pandoc/tests` (`44` files, `73964` assertions, `0` failures). No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

### JSON/Native Table Caption Writer Update (2026-06-12)

Bounded native PHP table caption handoff advanced by one cross-writer slice. Markdown table output now renders shared long caption blocks plus short caption blocks/inlines instead of falling back to plain caption text, and LaTeX figure and longtable output now emits short captions as optional `\\caption[...]` arguments. TableGeometry writer diagnostics were updated so LaTeX no longer reports a missing short-caption optional-argument requirement.

Verification passed `php -l` for `MarkdownWriter.php`, `LatexWriter.php`, `TableGeometry.php`, `NativeReaderTest.php`, and `TableGeometryTest.php`; focused `NativeReaderTest.php`, `LatexWriterTest.php`, and `TableGeometryTest.php` passed (`3` files, `2265` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74019` assertions, `0` failures). No external Pandoc, TeX, browser, Node, office, online service, live provider, or external validator was invoked.

### Raw/HTML Boundary Ship-Readiness Update (2026-06-12)

Verdict: not a shippable text-family close by itself. This slice closes one cross-format writer boundary gap while broader Markdown/CommonMark/GFM reader and extension parity remains partial.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| Markdown/WordPress raw HTML writer boundaries | 1 mapped slice | 1 local slice, 8 focused assertions | n/a | Complete broader Markdown/CommonMark/GFM extension and variant parity. |

Implemented highest-impact gap: `MarkdownWriter` now emits typed `raw_html_inline` payloads while keeping generic `format=html` raw disabled, and `WordPressBlockWriter` renders generic `raw_block`/`raw_inline` format `html`/`html4`/`html5`/`xhtml` plus TeX aliases through existing raw HTML/code paths without exposing unsupported raw formats. Verification passed `php -l` for `MarkdownWriter.php`, `WordPressBlockWriter.php`, and `MarkdownReaderTest.php`, focused `MarkdownReaderTest.php` (`1` file, `6581` assertions, `0` failures), focused `PandocJsonNativeAstTest.php` (`1` file, `1968` assertions, `0` failures), and full `lanes/pandoc/tests` (`44` files, `74018` assertions, `0` failures). No Pandoc, browser renderer, Node tooling, external validator, online service, live provider test, or live-service provider test was invoked.

### PDF/Typst Boundary Ship-Readiness Update (2026-06-13)

Verdict: not shippable for real PDF/Typst output parity because native PHP does not execute external TeX/Typst/PDF engines. Graceful no-external-engine boundary diagnostics now have 47 local mapped PDF/Typst boundary/provenance cases against 17 upstream format-related cases (276.5%), with no known critical uncovered graceful-boundary rows.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| PDF/Typst graceful boundary/provenance diagnostics | 17 | 47 | 276.5% | Full output parity still requires external engine execution, which remains unsupported in native PHP. |

Implemented highest-impact gap: `PdfEngineHandoff::fakeRun()` now extends Typst package dependency policy with sidecar package input counts, metadata-only byte exposure, non-executed network policy, package coordinates, namespace counts, and multi-version conflict diagnostics while preserving successful graceful behavior without external engines. Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`, focused `PdfEngineHandoffTest.php` (`1` file, `2225` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74226` assertions, `0` failures). No Pandoc, Typst, TeX/PDF engine, browser, Node, online service, live provider, or external validator was invoked.

### Text Format Ship-Readiness Update (2026-06-12)

Verdict: not shippable yet. The focused denominator-backed text-format gate is 465 / 1,132 (41.1%) across Markdown/CommonMark/GFM, LaTeX/TeX/math, wiki/roff text readers, and CSV/TSV readers. Plain output now has 3 local evidence slices, but reader parity gaps still block the broader text-format family.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| Markdown/CommonMark/GFM variants | 1,096 | 443 | 40.4% | Nested-list fixture round-trip is covered; complete extension and variant parity. |
| LaTeX/TeX/math | 14 | 20 | 142.9% | Local evidence is granular, but native LaTeX reader and math parity remain incomplete. |
| Wiki/roff text readers | 20 | 0 | 0.0% | Implement native text readers or explicitly defer them. |
| CSV/TSV readers | 2 | 2 | 100.0% | Bounded headed-table reader coverage exists; finish full CSV/TSV reader parity. |
| Plain output | output-side evidence | 3 local slices | n/a | Output-side handoff evidence; not an input-reader ship gate. |

Implemented highest-impact gap: `PlainWriter` now renders native table captions and table head/body/foot rows as readable plain rows instead of falling through to unstructured child text. The renderer preserves inline-formatted cell text, multi-block cell text, and existing wrapping diagnostics. Verification passed `php -l` for `PlainWriter.php` and `PlainWriterTest.php`, focused `PlainWriterTest.php` (`1` file, `216` assertions, `0` failures), and full `lanes/pandoc/tests` (`44` files, `73995` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, office suite, TeX/Typst engine, browser renderer, Node tooling, external validator, online service, live provider test, or live-service provider test was invoked.

### JSON/Native AST Ship-Readiness Update (2026-06-13)

Verdict: not shippable yet. The native PHP lane now has 50 local passing JSON/native AST evidence cases against 252 upstream native expected artifacts (19.8% evidence ratio). Broader native/json fixture parity, unsupported constructor surfaces, and table/citation/metadata round-trip edges remain open.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| JSON/native AST constructors and round trips | 252 | 50 | 19.8% | Broader upstream native/json fixture parity plus unsupported constructor/table/citation/metadata surfaces beyond bounded sidecar reuse, Markdown fixture writer handoff, note label sidecars, task-list sidecars, and nullary helper payload validation. |

Implemented highest-impact gap: `PandocJsonWriter` and `NativeWriter` now attach `taskChecked` sidecars to generated list-item block payloads, while `PandocJsonReader` and `NativeReader` restore `taskChecked`/`taskList` metadata so Markdown, WordPress, and LaTeX handoff paths preserve unchecked, checked, and nested task items after JSON/native round trips. Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, `PandocJsonWriter.php`, `NativeWriter.php`, `PandocJsonNativeAstTest.php`, and `MarkdownReaderTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2015` assertions, `0` failures); focused `MarkdownReaderTest.php` (`1` file, `6626` assertions, `0` failures); and full `lanes/pandoc/tests` (`45` files, `74254` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Implemented follow-up gap: `PandocJsonWriter` and `NativeWriter` now reject stale non-empty nullary helper constructor payload reuse for quote, math, citation-mode, list-style/delimiter, table-alignment, and `ColWidthDefault` helpers. Regenerated JSON/native output drops stale `c` payloads while preserving valid helper sidecars and empty-`c` compatibility. Verification passed `php -l` for `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` (`1` file, `2097` assertions, `0` failures); and full `lanes/pandoc/tests` (`45` files, `74336` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

### Table Geometry Writer Update (2026-06-13)

Implemented highest-impact gap: `LatexWriter` now serializes `table_foot` rows as supported `longtable` footer sections instead of flattening them after the body, and `TableGeometry` no longer reports `latex-longtable-footer-required` for plain table-foot sections. The table geometry handoff self-test now also expects the current Markdown caption-block diagnostic token, `inline-caption-markdown`.

Remaining critical table gaps after the footer slice: body-local header-row preservation in LaTeX, multiple body-group semantics in non-HTML writers, true LaTeX rowspan output beyond diagnostics, and package-specific DOCX/ODT writer internals that remain out of scope for this slice. Verification passed `php -l` for the touched Pandoc PHP files, focused `LatexWriterTest.php` plus `TableGeometryTest.php` (`2` files, `1917` assertions, `0` failures), `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`, and full `lanes/pandoc/tests` (`45` files, `74205` assertions, `0` failures). No Pandoc binary, TeX engine, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Implemented follow-up gap: `LatexWriter` now preserves each Pandoc `table_body` `headRows` group in body position instead of promoting those rows into the global table head, while `TableGeometry` no longer reports `latex-body-head-rows-review-required` for the covered LaTeX writer path. Markdown and AsciiDoc body-head diagnostics remain in place. Verification passed `php -l` for `LatexWriter.php`, `TableGeometry.php`, `LatexWriterTest.php`, `TableGeometryTest.php`, and `wordpress-table-geometry-handoff.php`; focused `LatexWriterTest.php` plus `TableGeometryTest.php` (`2` files, `1917` assertions, `0` failures); focused `TableGeometryReaderHandoffTest.php` (`1` file, `1493` assertions, `0` failures); `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`; and full `lanes/pandoc/tests` (`45` files, `74336` assertions, `0` failures). No external Pandoc, TeX, browser, Node, office, online service, live provider, or external validator was invoked.

### JSON/Native Block Boundary Update (2026-06-13)

Implemented highest-impact gap: `WordPressBlockWriter` now wraps unadorned native `Plain` children as paragraphs in multi-block HTML collections, preserving adjacent `Plain` blocks and `Plain` before `RawBlock` boundaries inside native `Div` and blockquote handoff. Single-block `Plain` collections remain compact for existing tight-list behavior.

Remaining critical JSON/native gaps: broader upstream native/json fixture parity, unsupported constructor surfaces, and table/citation/metadata round-trip edges beyond this bounded Div/Plain/RawBlock WordPress boundary slice. Verification passed `php -l` for `WordPressBlockWriter.php` and `PandocJsonNativeAstTest.php`, focused `PandocJsonNativeAstTest.php` (`1` file, `1987` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74214` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, TeX/PDF engine, browser renderer, Node tooling, office suite, external validator, online service, live provider test, or live-service provider test was invoked.

### Pandoc Progress/Status Reconciliation

Dashboard reconciliation on 2026-06-13: `PANDOC_STATUS.md` is now present, the
root dashboard, lane status, upstream manifest, ready/open beads, and landed
commit history agree on the current shipping call after the DOCX section-property
slice, EPUB3 NCX document metadata provenance slice, XML/HTML/JATS front-matter slice, RIS citation parser slice, JSON/native target tuple sidecar slice, plain writer table caption row slice, PDF/Typst package dependency policy slice, Markdown/WordPress raw HTML boundary slice, JSON/native table caption block writer slice, MediaBag linked-resource handoff slice, Markdown adjacent-list separator slice, Markdown/WordPress footnote label anchor slice, Markdown nested fenced Div slice, ODT configuration package metadata slice, Markdown fixture nested-list round-trip slice, CSV/TSV direct text reader slice, JSON/native note label sidecar slice, LaTeX table-foot longtable writer slice, JSON/native Div Plain block-boundary slice, PDF/Typst package dependency conflict policy slice, JSON/native task-list checkbox sidecar slice, JSON/native nullary helper payload validation slice, LaTeX table body-head writer slice, Markdown fenced Div escaped-attribute slice, Markdown/WordPress footnote backlink slice, Pandoc text markup reader unsupported ship-gate slice, and EPUB direct manifest/spine report slice.

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static upstream inventory remains 2,276 Pandoc test/data/benchmark artifacts at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`; input-format scope is 50 tokens after skipping IPYNB for this phase. | Denominator accepted for native PHP progress accounting; not upstream runner parity. |
| Local passing numerator | `lane-status.json` reports 3,318 PHP passes / 0 failures, and `UPSTREAM_TEST_MANIFEST.json` reports 3,277 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,277 / 2,276 = 144.0%; percentages above 100% reflect local PHP slices being more granular than upstream inventory rows. | High coverage, but not global ship-ready. |
| Shippable format gate | ODF/ODT is ship-ready with 50 local mapped cases / 20 upstream ODF/ODT cases, 250.0%, and 0 critical ODF/ODT gaps. | ODF/ODT can ship under the native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. EPUB direct manifest/spine diagnostics are covered, and the 20 wiki/roff/man/text markup reader tokens have explicit unsupported ship-gate accounting. | Full Pandoc input lane remains active. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`; only `plib-qka5o` qualified and was closed as landed. Follow-up main-ancestor orphan count is 0. Branch-only orphan candidates were left open. | Dashboard queue state now reflects landed work without closing live branch work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/EpubPackageReader.php lanes/pandoc/tests/EpubPackageReaderTest.php lanes/pandoc/notes/pandoc-epub-direct-manifest-spine-report-20260613T010615Z.md`, syntax checks for `EpubPackageReader.php` and `EpubPackageReaderTest.php`, focused `EpubPackageReaderTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | 45 test files, 74,472 assertions, 0 failures. |

Methodology: upstream denominators come from `lanes/pandoc/notes/upstream-inventory.md`,
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, and the input-format registry in
`lanes/pandoc/src/PandocFormatRegistry.php`, which records 51 upstream Pandoc
input tokens from the 2026-06-03 manual and upstream source commit
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Local passing counters
merge `mapped*Cases` from `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and current
`lanes/pandoc/lane-status.json`; `phpPass`/`phpFail` come from
`lanes/pandoc/lane-status.json`. Commands used: `jq` over the manifest and lane
status JSON to list case counters, PHP registry inspection for input support
status, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/EpubPackageReader.php lanes/pandoc/tests/EpubPackageReaderTest.php lanes/pandoc/notes/pandoc-epub-direct-manifest-spine-report-20260613T010615Z.md`,
`php -l lanes/pandoc/src/EpubPackageReader.php`,
`php -l lanes/pandoc/tests/EpubPackageReaderTest.php`,
`php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
(`1` file, `187` assertions, `0` failures), and `php tools/run-tests.php lanes/pandoc/tests`
(`45` files, `74472` assertions, `0` failures on current main `2b00b60b67`).
`bd orphans --label lane:pandoc` was used for stale-open cleanup, but only
main-ancestor referenced commits were closed. No Pandoc binary, office suite,
TeX/Typst engine, browser engine, Node tooling, or external validator was invoked.
