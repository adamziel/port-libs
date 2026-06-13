| Project | Focus | State | Progress | PHP Tests | Mapped Upstream | Unmapped | Next Gate | Commit |
| --- | --- | --- | ---: | ---: | --- | ---: | --- | --- |
| [libsqlite](lanes/libsqlite/lane-status.json) | Primary | PHP green, upstream gap | 99.6% | 6,290,284 pass / 0 fail | [1,589 / 1,589 (100.0%)](lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json) | 0 | No local blocker | 16d8081 |
| [LightningCSS](lanes/lightningcss/lane-status.json) | Active | PHP green, upstream gap | 99.8% | 9,280 pass / 0 fail | [2,445 / 3,532 (69.2%)](lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json) | 1,087 | Full upstream runner closure is partial: bounded Rust media test and... | pending isolate... |
| [gitoxide](lanes/gitoxide/lane-status.json) | Active | High coverage | 98.8% | 11,183 pass / 0 fail | [1,821 / 2,886 (63.1%)](lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json) | 1,065 | Cargo workspace blocked by sparse target files | 29e9ab4 |
| [markerPDF](lanes/markerpdf/lane-status.json) | Active | PHP green, upstream gap | 100.0% | 3,621 pass / 0 fail | [763 / 78 (978.2%)](lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json) | 0 | No GPU/model execution will be run for markerPDF under current user d... | pending fast ba... |
| [Readability/content rewrite engine](lanes/readability/lane-status.json) | Backlog | Active port | 85.0% | 154 pass / 0 fail | [1,578 / 1,984 (79.5%)](lanes/readability/UPSTREAM_TEST_MANIFEST.json) | 406 | No local blocker | cd2e8a0 |
| [pandoc](lanes/pandoc/lane-status.json) | Backlog | High coverage | 96.0% | 3,353 pass / 0 fail | [3,313 / 2,276 (145.6%)](lanes/pandoc/UPSTREAM_TEST_MANIFEST.json) | 0 | Finish CSV/TSV row-width, malformed-input, multiline, and broader option parity after input-prefix diagnostics | csv-tsv-input-prefix-diagnostics-6f88e1c |
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
| Markdown/CommonMark/GFM | `commonmark`, `commonmark_x`, `gfm`, `markdown`, `markdown_github`, `markdown_mmd`, `markdown_phpextra`, `markdown_strict` | partial | 452 | 1,096 | YAML metadata alias review summaries, generic raw HTML serialization, HTML list item attributes, math attribute round-trip, escaped fenced-Div attributes, fenced Div section-reference boundaries, nested-list fixture round-trip, definition-list term-group handoff, table-cell note placement, and header section Div mapping are covered; complete extension and variant parity. |
| HTML/XML/JATS DOM | `html` partial; `xml`, `jats`, `bits` unsupported | mixed | 278 | 29 | Object association provenance, JATS/BITS front-matter and body diagnostics review packets, and explicit XML/JATS/BITS direct-reader capability diagnostics are covered; finish HTML5 tree construction and implement full XML/JATS/BITS readers. |
| JSON/native AST | `json`, `native` | partial | 54 | 252 | Table attribute handoff, mixed block-container and table caption/cell flushing, nullary helper payload validation, task-list checkbox sidecars, note label sidecars, definition-list term handoff, and fixture writer handoff are preserved; complete broader JSON/native AST constructor coverage. |
| DOCX/OpenXML | `docx` | partial | 94 | 35 | Finish remaining direct WordprocessingML/package reader parity; section-property review metadata, subdocument diagnostics, and note/comment relationship diagnostics are covered. |
| EPUB/EPUB3 | `epub` | partial | 62 | 9 | Direct manifest suffix diagnostics, skipped spine-entry reporting, XHTML definition-list handoff, and XHTML table-section review metadata are covered; finish broader EPUB package reader parity. |
| ODF/ODT/OpenDocument | `odt` | ship-ready | 51 | 20 | 0 critical gaps for native PHP ODT import; compact manifest custom attribute provenance is covered. Continue only non-critical hardening slices as discovered. |
| Shared ZIP/OPC package | dependency for package readers | partial dependency | 107 | 67 | Selected-entry role bucket handoff is covered; finish shared ZIP/OPC package ingestion used by DOCX, EPUB, ODT, PPTX, and XLSX. |
| CSL/BibTeX/BibLaTeX/csljson citations | `bibtex`, `biblatex`, `csljson`, `endnotexml`, `ris` | mixed | 77 | 8 | RIS now has bounded native CSL item parsing; EndNote XML, broader RIS coverage, and full reader-registry parity remain. |
| LaTeX/TeX/math | `latex` | partial | 20 | 14 | Finish LaTeX reader and math conversion parity. |
| DocBook/table geometry | `docbook` | partial | 17 | 16 | DocBook structural review diagnostics are covered; finish DocBook XML reader parity, body conversion, inline/block/reference/bibliography mapping, generated AST parity, and broader fixture hydration. |
| RTF | `rtf` | partial | 4 | 3 | Finish RTF reader parity. |
| Typst | `typst` | unsupported | 48 | 17 | Implement Typst reader; package dependency conflict and source-class policy provenance are covered, but current evidence is boundary/provenance only. |
| PPTX/XLSX | `pptx`, `xlsx` | unsupported | 0 | 2 | Implement native package readers after ZIP/OPC and XML package foundations. |
| Wiki/roff/text markup readers | `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`, `mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `vimwiki` | unsupported diagnostics | 0 | 20 | Diagnostic packet records 20/0 reader state with reason/capability metadata; implement native readers or explicitly defer them. |
| Tabular/data readers | `csv`, `tsv` | partial | 11 | 2 | Headed and no-header table option handling, auto format inference diagnostics, input-prefix encoding diagnostics, registry option profiles, extension inference, direction buckets, and source provenance are covered; finish broader CSV/TSV reader parity beyond the bounded table slices. |
| Unsupported input format surfaces | all unsupported input tokens above | unsupported | 0 | 31 | Close the remaining unsupported input registry rows. |

Adjacent import targets outside the Pandoc input denominator:

| Target | Current evidence | Scope note | Remaining input work |
| --- | ---: | --- | --- |
| PDF | 45 / 17 | Pandoc has `pdf` as an output target, not an input format. | Track as separate PDF import/markerPDF ingestion work. |
| Legacy DOC/CFB | 7 / 7 | Not a current upstream Pandoc input token. | Decide and track as separate legacy document import support. |
| IPYNB/notebook | skipped | Upstream Pandoc input token intentionally skipped for this phase. | No work in this burn-down. |

### Closure-Wave Evidence Snapshot (2026-06-13)

Current-main counters are reconciled through the CSV/TSV input-prefix diagnostics slice rebased on `6f88e1c`: 3,353 PHP passes, 0 failures, and 3,313 mapped upstream cases out of the accepted 2,276-row static upstream inventory. Rows below summarize the recently landed closure-wave evidence; they are factual evidence counters, not global ship-ready claims. This refresh was checked with `jq empty`, `git diff --check`, syntax checks for `DelimitedTextReader.php` and `DelimitedTextReaderTest.php`, focused `DelimitedTextReaderTest.php` plus `PandocFormatRegistryTest.php` (`2` files, `1538` assertions, `0` failures), and full `lanes/pandoc/tests` (`46` files, `75542` assertions, `0` failures).

| Surface | Evidence state | Upstream denominator | Local passing numerator | Ship verdict | Remaining critical gaps |
| --- | --- | ---: | ---: | --- | --- |
| CSV/TSV direct readers and registry options | Landed headed/no-header option parity, auto CSV/TSV format inference diagnostics, input-prefix encoding diagnostics, tabular registry extension inference, direction buckets, source provenance, and option profiles | 2 CSV command fixtures plus registry/options harness slices | 11 local CSV/TSV/registry cases; latest focused reader/registry run 1,538 assertions | Partial after bounded reader/profile/inference/prefix slices | Broader CSV/TSV row-width behavior, malformed input diagnostics, multiline-cell behavior, and table-reader edge cases. |
| ODF/ODT package reader | Landed compact manifest custom file-entry attributes while preserving ship-ready closure | 20 ODF/ODT rows | 51 ODF/ODT cases; latest focused ODF/ODT gate 5,942 assertions | Ship-ready | 0 critical ODF/ODT gaps for native PHP ODT import. |
| DOCX/OpenXML package reader | Landed subdocument and note/comment relationship diagnostics | 35 DOCX/OpenXML rows | 94 DOCX/OpenXML cases; latest focused run 2,483 assertions | Partial | Broader WordprocessingML/package reader parity, fields, content controls, revisions, lists, tables, and package edge cases. |
| EPUB direct package reader | Landed manifest suffix diagnostics, skipped spine-entry reports, XHTML definition-list handoff, and XHTML table-section review metadata | 9 EPUB package rows | 62 EPUB evidence cases; latest focused run 4,328 assertions | Partial | Broader direct EPUB package reader structural/content parity and upstream runner parity. |
| Shared ZIP/OPC package | Landed selected-entry handoff role buckets | 67 ZIP/OPC dependency rows | 107 ZIP/OPC evidence cases; latest focused run 4,448 assertions | Partial dependency | ZIP64 expansion, encrypted payload decryption, non-deflate extraction, cryptographic signature validation, and remaining DOCX/EPUB/PPTX/XLSX package-reader parity. |
| JSON/native table, list, and block handoff | Landed table-caption, table-attribute, mixed table caption/cell flushing, note-label, Div Plain, mixed block-container, task-list sidecar, definition-list term-group, and nullary helper payload slices | 252 JSON/native artifacts | 54 JSON/native cases; latest focused touched-path run 14,091 assertions | Partial | Broader native/json fixture parity, unsupported constructors, link/raw payloads around block containers, and table/citation/metadata round trips. |
| Markdown block/list/note/section boundaries | Landed YAML metadata alias summaries, generic raw HTML serialization, HTML list item attributes, math attributes, nested fenced Div, escaped fenced-Div attributes, fenced Div section-reference boundaries, mixed-content nested-list, definition-list term-group, table-cell note placement, and header section Div slices | 1,096 Markdown-family rows | 452 Markdown-family cases; latest focused YAML/Markdown run 6,753 assertions | Partial | Markdown/CommonMark/GFM extension, YAML metadata diagnostics, and variant parity. |
| XML/JATS/BITS direct reader capability and body diagnostics | Landed explicit unsupported direct-reader capability diagnostics for `xml`, `jats`, and `bits`, standalone object association provenance, and JATS/BITS body roots, section hierarchy, xref resolution, references, figures, table-wraps, unreferenced buckets, and BITS book-part body metadata | 29 XML/HTML/JATS/DocBook rows | 278 XML/HTML/JATS/DocBook cases; latest focused `XmlHtmlDomTest.php` run 1,970 assertions | Unsupported for full direct readers | Implement full XML/JATS/BITS body/back matter, tables, figures, references, citations, and AST parity. |
| DocBook structural review diagnostics | Landed review-only DocBook 4/5 structure packets with metadata, section/chapter structure, figures, tables, admonitions, bibliography entries, xref/external targets, media/image references, and unsupported direct-reader diagnostics | 16 DocBook/table geometry rows | 17 local DocBook/table geometry cases; latest focused `XmlHtmlDomTest.php` run 1,930 assertions | Partial after bounded review diagnostics | Full DocBook body conversion, inline/block/reference/bibliography mapping, generated AST parity, broader fixture hydration, and figure/media/admonition conversion remain open. |
| IPYNB/notebook reader diagnostics | Landed metadata keys, cell tags, MIME summaries, and blocked resource diagnostics | 1 IPYNB rich-package reader bucket | Focused `IpynbReaderTest.php` run 86 assertions | Covered bounded reader diagnostics | Full Jupyter notebook reader parity, rich output rendering, attachment/media extraction, broader nbformat diagnostics, and native IPYNB writer support remain open. |
| Media linked-resource handoff | Landed linked-resource loading plus MIME inference | 2 mapped cross-format resource slices | 2 focused `MediaBag` cases; latest focused run 168 assertions | Covered bounded handoff; not an input-format ship gate | Wider media/resource edge cases outside opt-in linked-resource handoff. |
| Notes/references | Landed footnote label anchors, WordPress backlink metadata, table-cell note placement, and JSON/native note-label sidecars | 252 JSON/native artifacts plus notes/reference handoff slices | Latest focused MarkdownReader run 6,668 assertions; focused Markdown/DOCX run 11,750 assertions | Partial | Broader note/reference placement, endnote grouping, anchor round-trips, and constructor parity. |
| Inline writer attributes | Landed WordPress semantic/math inline attrs and LaTeX semantic/code/math id anchors | Writer handoff slice | Focused Markdown/LaTeX writer run 6,665 assertions | Covered bounded writer handoff | Broader Markdown/CommonMark/GFM and LaTeX/math reader parity. |
| Wiki/roff/text reader ship gate | Landed explicit unsupported verdict and diagnostic packet for 20 text markup reader input tokens | 20 wiki/roff/man/text markup tokens | Focused registry run 1,349 assertions | Unsupported | Implement native readers after registry-level ship-gate accounting or explicitly defer them. |
| Table geometry | Landed LaTeX table-foot longtable, body-local head-row, and PlainWriter body-group handoffs | Table writer geometry slice | Focused PlainWriter/TableGeometry runs 2,127 assertions | Covered bounded table-foot, body-head-row, and plain-text body-group slices | Markdown/AsciiDoc/LaTeX body-group semantics, rowspan output, and package-specific table internals. |
| PDF/Typst dependency policy | Landed package dependency conflict and source-class policy provenance | 17 PDF/Typst boundary rows | 48 PDF/Typst boundary/provenance cases; latest focused run 2,231 assertions | Covered bounded no-engine provenance | Real PDF/Typst output parity remains unsupported without external engines. |

### XML/JATS/BITS Body Diagnostics Update (2026-06-13)

Bounded native PHP XML/JATS/BITS coverage advanced by one body diagnostics slice after CSV/TSV format inference diagnostics. `XmlHtmlDom::summarizeJatsFrontMatter()` now reports JATS/BITS body roots, section hierarchy/depth/type metadata, direct and descendant section paragraph counts, resolved and unresolved xref targets, reference labels and back-reference counts, figure labels/captions/graphic hrefs, table-wrap labels/captions/row counts, unreferenced figure/table buckets, and BITS book-part body metadata while keeping `directReaderParity=false`.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `1970` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75481` assertions, `0` failures). No Pandoc binary, browser renderer, Node tooling, external XML validator, online service, live provider test, or live-service provider test was invoked.

XML/HTML/JATS/DocBook DOM evidence is now 278 local cases against the 29 accepted static upstream XML/HTML/JATS/DocBook rows. XML/JATS/BITS remains unsupported as full direct readers; full XML input reader mapping, JATS/BITS body/back matter/table/figure/reference/citation parity, HTML5 tree-construction parity, and DocBook XML reader parity remain open.

### CSV/TSV Format Inference Update (2026-06-13)

Bounded native PHP CSV/TSV reader coverage advanced by one format inference diagnostics slice after DocBook structural review diagnostics. `DelimitedTextReader` now supports `readAuto()` and `format=auto`, resolving CSV vs TSV from `extension` / `sourcePath` metadata first and then content row-profile scoring while preserving `formatInference` review packets.

Verification passed `php -l` for `DelimitedTextReader.php`, `DelimitedTextReaderTest.php`, `PandocFormatRegistry.php`, and `PandocFormatRegistryTest.php`; focused `DelimitedTextReaderTest.php` plus `PandocFormatRegistryTest.php` passed (`2` files, `1501` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75441` assertions, `0` failures). No Pandoc binary, spreadsheet application, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

CSV/TSV/registry evidence is now 9 local mapped cases against the 2 accepted static upstream CSV command fixture rows. CSV/TSV remains partial; broader upstream fixture hydration, option behavior, malformed input diagnostics, multiline-cell behavior, and table-reader edge cases remain open.

### DocBook Structural Review Update (2026-06-13)

Bounded native PHP DocBook/XML coverage advanced by one structural review diagnostics slice after IPYNB notebook metadata/resource diagnostics. `XmlHtmlDom::summarizeDocBookStructure()` now emits review-only packets for DocBook 4/5 structural roots with metadata, identifiers, contributors, sections, figures, tables, admonitions, bibliography entries, xref/external targets, media/image counts, and direct-reader unsupported diagnostics.

Verification passed `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `1930` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75426` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, XML validator, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

DocBook evidence is now 17 local cases against the 16 accepted static upstream DocBook/table geometry rows. DocBook remains partial; full body conversion, inline/block/reference/bibliography mapping, generated AST parity, broader fixture hydration, and figure/media/admonition conversion remain open.

### IPYNB Notebook Metadata/Resource Diagnostic Update (2026-06-13)

Bounded native PHP IPYNB reader parity advanced by one metadata/resource diagnostic slice after XML/HTML object association provenance. `IpynbReader` now exposes top-level notebook metadata keys, per-cell metadata keys and sorted tags, attachment/output MIME-type summaries, and a metadata-only resource policy that blocks notebook attachment/output byte exposure while preserving safe `data-ipynb-*` review attributes for WordPress handoff.

Verification passed `php -l` for `IpynbReader.php`, `PandocFormatRegistry.php`, and `IpynbReaderTest.php`; focused `IpynbReaderTest.php` passed (`1` file, `86` assertions, `0` failures); focused IPYNB+registry tests passed (`2` files, `1511` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75370` assertions, `0` failures). No Pandoc binary, Jupyter, Python notebook runner, Node tooling, browser renderer, online service, live provider, or external validator was invoked.

Remaining notebook gaps: full Jupyter notebook reader parity, rich output rendering, attachment/media extraction, broader nbformat diagnostics, and native IPYNB writer support remain partial or unsupported.

### XML/HTML Object Association Update (2026-06-13)

Bounded native PHP HTML object handoff advanced by one standalone object association slice after MediaBag linked-resource MIME inference. `XmlHtmlDom` now summarizes object `form` owner metadata, valid and invalid `usemap` image-map targets, and `typemustmatch` state, while both HTML serializers emit `typemustmatch` as a boolean attribute.

Verification passed `php -l` for `XmlHtmlDom.php`, `Html5DomFragment.php`, and `XmlHtmlDomTest.php`; focused `XmlHtmlDomTest.php` passed (`1` file, `1874` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75327` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, external validator, online service, live provider test, or live-service provider test was invoked.

HTML/XML/JATS evidence is now 277 local cases against the 29 accepted static upstream HTML/XML/JATS/DocBook rows. HTML/XML/JATS remains partial; broader HTML5 tree construction and full XML/JATS/BITS direct readers remain open.

### MediaBag MIME Inference Update (2026-06-13)

Bounded native PHP MediaBag coverage advanced by one linked-resource MIME inference slice after YAML metadata alias review summaries. `MediaBag` now infers common package/resource MIME types from package-local paths beyond images, PDF, and plain text, covering CSS, JavaScript, JSON/XML/HTML, audio/video, fonts, EPUB, Markdown, CSV, and TSV. Data URI and hashed remote or URL-suffixed resources receive MIME-derived hash extensions for the same resource classes.

The focused fixture proves inferred CSS, audio, font, and JSON MIME provenance through extraction attributes, Markdown output, WordPress links, and JSON/native round-trip. Verification passed `php -l` for `MediaBag.php` and `MediaBagTest.php`; focused `MediaBagTest.php` passed (`1` file, `168` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75296` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, office suite, TeX/PDF engine, Node tooling, online service, live provider test, or external validator was invoked.

MediaBag linked-resource evidence is now 2 local mapped resource slices. The broader media/resource surface remains partial for duplicate resource provenance, package-local path normalization, external/missing diagnostics, and writer handoff consistency edges outside this bounded MIME inference slice.

### YAML Metadata Alias Summary Update (2026-06-13)

Bounded native PHP YAML metadata/front-matter coverage advanced by one alias review summary slice after JSON/native table attribute writer handoff. `MarkdownReader` now includes alias provenance in `yamlMetadataReviewSummary`, including alias-only packets, so resolved aliases contribute `aliasCount` to clean review summaries. `YamlMetadataReview` focused coverage indexes resolved aliases by metadata path for merge-key, scalar alias, and map alias handoff.

Verification passed `php -l` for `MarkdownReader.php`, `MarkdownReaderTest.php`, and `YamlMetadataReviewTest.php`; focused `YamlMetadataReviewTest.php` plus `MarkdownReaderTest.php` passed (`2` files, `6753` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75272` assertions, `0` failures). No Pandoc binary, YAML CLI tooling, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

Markdown/YAML evidence is now 452 local Markdown-family cases against the 1,096 accepted static upstream Markdown-family rows. YAML metadata remains partial; broader scalar/list/map diagnostics, unsupported feature verdicts, and JSON/native/Markdown writer round-trip handoff remain open.

### JSON/Native Table Attribute Writer Update (2026-06-13)

Bounded native PHP JSON/native metadata-provenance coverage advanced by one table attribute writer handoff slice after JSON/native mixed table caption/cell flushing. `MarkdownWriter` now emits safe table caption attributes for explicit ids, classes, `data-*`, `aria-*`, language, title, role, and direction metadata while keeping `data-docx-*` review-only layout metadata out of pipe-table Markdown. `LatexWriter` now emits figure/table caption labels from explicit AST identifiers while preserving existing short-caption command output.

The fixture proves the same table id, class, and `data-source` provenance through NativeReader/NativeWriter, Markdown table captions, WordPress table attributes, and LaTeX caption labels. Verification passed `php -l` for `MarkdownWriter.php`, `LatexWriter.php`, and `NativeReaderTest.php`; focused `DocxReaderTest.php`/`NativeReaderTest.php`/`LatexWriterTest.php`/`TableGeometryTest.php`/`MarkdownReaderTest.php` passed (`5` files, `14091` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75253` assertions, `0` failures). No Pandoc binary, office suite, TeX engine, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

JSON/native evidence is now 54 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; broader native/json fixture parity, unsupported constructors, link/raw payloads around block containers, citation/metadata round trips, and WordPress invalid JSON/native block encodings remain open.

### JSON/Native Mixed Table Caption/Cell Update (2026-06-13)

Bounded native PHP JSON/native writer coverage advanced by one mixed-content fixture slice after tabular data registry option profiles. `PandocJsonWriter` and `NativeWriter` now flush long table caption blocks through the same mixed child-block path used by table cells, lists, and figures, so leading/trailing inline runs around nested block children become `Plain` blocks instead of invalid mixed block payloads. The fixture covers mixed caption blocks around a nested `bullet_list` and mixed table cell content around a nested `blockquote`, then round-trips through Pandoc JSON and native readers.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, `PandocJsonWriter.php`, `NativeWriter.php`, `PandocJsonNativeAstTest.php`, and `MarkdownReaderTest.php`; focused `PandocJsonNativeAstTest.php` plus `MarkdownReaderTest.php` passed (`2` files, `8875` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75250` assertions, `0` failures). No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderer, Node tooling, TeX/office tools, online service, live provider test, or external validator was invoked.

JSON/native evidence is now 53 local cases against the 252 accepted static upstream JSON/native artifacts. JSON/native remains partial, not ship-ready; unsupported constructors, link/raw payloads around block containers, citation/metadata round trips, and WordPress invalid JSON/native block encodings remain open.

### Tabular Data Registry Options Update (2026-06-13)

Bounded native PHP format-registry coverage advanced by three CSV/TSV option-profile cases after ODF compact manifest custom attributes. `PandocFormatRegistry` now exposes tabular extension inference, input-only direction buckets, upstream `readCSV`/`readTSV` source provenance, delimiter/quote/escape/header option profiles, and review packets while keeping native CSV/TSV input support partial and avoiding CSV/TSV output-writer claims.

Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `1425` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75224` assertions, `0` failures). No Pandoc binary, office suite, TeX/Typst/PDF engine, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

CSV/TSV evidence is now 7 local mapped reader/registry cases against the 2 accepted static upstream CSV command fixture rows. CSV/TSV remains partial, not ship-ready; broader option behavior, malformed input diagnostics, multiline-cell behavior, and table-reader edge cases remain open.

### ODF Compact Manifest Custom Attributes Update (2026-06-13)

Bounded native PHP ODF/ODT package-reader coverage advanced by one compact manifest provenance slice after DOCX note/comment relationship diagnostics. `OpenDocumentPackage` now preserves custom `manifest:file-entry` attributes in parity with the richer ODF reader path, including namespaced vendor attributes, `xml:*` attributes, empty custom attribute values, structural/custom classification, deterministic custom attribute maps, and aggregate manifest custom-attribute buckets in package review summaries.

Verification passed `php -l` for `OpenDocumentPackage.php` and `OpenDocumentPackageTest.php`; focused `OpenDocumentPackageTest.php` passed (`1` file, `1181` assertions, `0` failures); focused ODF/ODT gate passed (`4` files, `5942` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75148` assertions, `0` failures). No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, Node tooling, online validator, live provider, or external validator was invoked.

ODF/ODT evidence is now 51 local mapped cases against the 20 accepted static upstream ODF/ODT rows, with 0 remaining critical ODF/ODT gaps. The native PHP ODF/ODT input ship-ready verdict remains unchanged.

### DOCX Note/Comment Relationship Diagnostics Update (2026-06-13)

Bounded native PHP DOCX/OpenXML reader coverage advanced by one relationship diagnostic slice after Markdown generic raw HTML serialization. `DocxOpenXmlReader` now carries footnote, endnote, and comment part-local `.rels` diagnostics into the DOCX review summaries, including relationship part names, IDs, internal/external counts, target part and external target summaries, missing target/content-type issue codes, target query/fragment suffixes, and per-item referenced relationship IDs.

Verification passed `php -l` for `DocxOpenXmlReader.php` and `DocxOpenXmlReaderTest.php`; focused `DocxOpenXmlReaderTest.php` passed (`1` file, `2483` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75121` assertions, `0` failures). No Pandoc binary, Word, LibreOffice, office suite, zip/unzip, ZipArchive, browser renderer, online service, live provider test, or external validator was invoked.

DOCX/OpenXML evidence is now 94 local cases against the 35 accepted static upstream DOCX/OpenXML rows. DOCX/OpenXML remains partial, not ship-ready; broader direct WordprocessingML reader parity for fields, content controls, revision markup, list/table behavior, and package edge cases remains open.

### CSV/TSV Header Option Update (2026-06-13)

Bounded native PHP CSV/TSV reader coverage advanced by one header option parity slice after text markup unsupported diagnostics. `DelimitedTextReader` now accepts `header => false` for CSV and TSV imports, keeps every source row in the table body, emits an empty table head for downstream Pandoc JSON compatibility, generates stable column labels, records a no-header diagnostic in the `delimitedText` review packet, and rejects non-boolean `header` options.

Verification passed `php -l` for `DelimitedTextReader.php`, `DelimitedTextReaderTest.php`, `PandocFormatRegistry.php`, and `PandocFormatRegistryTest.php`; focused `DelimitedTextReaderTest.php` plus `PandocFormatRegistryTest.php` passed (`2` files, `1410` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75039` assertions, `0` failures). No Pandoc binary, spreadsheet application, browser renderer, Node tooling, online validator, online service, live provider test, or external validator was invoked.

Current CSV/TSV evidence is 4 local cases against the 2 accepted static upstream CSV command fixture rows. CSV/TSV remains partial, not ship-ready; broader delimiter/quote option variants, malformed input diagnostics, multiline-cell policy, table caption/metadata handoff, and additional upstream fixture hydration remain open.

### PlainWriter Table Body-Group Update (2026-06-13)

Bounded native PHP table writer coverage advanced by one non-HTML body-group slice after Markdown HTML list item attributes. `PlainWriter` now separates consecutive native Pandoc `table_body` groups with a blank line in plain text while preserving body-local `headRows` in each group.

Verification passed `php -l` for `PlainWriter.php` and `PlainWriterTest.php`; focused `PlainWriterTest.php` passed (`1` file, `223` assertions, `0` failures); focused `TableGeometryTest.php` passed (`1` file, `1904` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74766` assertions, `0` failures). No Pandoc, TeX, Typst, browser renderer, Node tooling, office suite, online service, live provider test, or external validator was invoked.

### Markdown HTML List Item Attribute Update (2026-06-13)

Bounded native PHP list-item attribute coverage advanced by one Markdown/HTML-to-WordPress handoff slice after Markdown math attribute round-trip. `MarkdownReader` now carries safe HTML `li` `id`, `class`, `data-*`, and `title` attributes into shared `list_item` attrs, and the existing WordPress list item writer output preserves those safe attrs while continuing to filter unsafe event and style attributes.

Verification passed `php -l` for `MarkdownReader.php` and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6716` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74759` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, Node tooling, TeX/PDF/Typst engine, office suite, online service, live provider test, or external validator was invoked.

### Markdown Math Attribute Round-Trip Update (2026-06-13)

Bounded native PHP inline attribute coverage advanced by one Markdown math reader/writer slice after JSON/native block-container mixed-content flushing. `MarkdownReader` now attaches immediate `{#id .class key="value"}` tuples to inline and display math nodes, and `MarkdownWriter` emits those tuples for math nodes, preserving class and key-value attrs for `Math` alongside existing `Code` and `Span` attr output.

Verification passed `php -l` for `MarkdownReader.php`, `MarkdownWriter.php`, and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6704` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74747` assertions, `0` failures). No Pandoc, TeX engine, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

### JSON/Native Block-Container Mixed Content Update (2026-06-13)

Bounded native PHP JSON/native fixture coverage advanced by one mixed block-container round-trip slice after XML/JATS/BITS direct reader capability diagnostics. `PandocJsonWriter` and `NativeWriter` now flush mixed inline runs to `Plain` blocks inside `BlockQuote`, `Div`, `Note`, and shared child-block payloads while preserving nested `CodeBlock` and list payloads plus valid `noteLabel` sidecars.

Verification passed `php -l` for `PandocJsonReader.php`, `NativeReader.php`, `PandocJsonWriter.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`; focused `PandocJsonNativeAstTest.php` passed (`1` file, `2133` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74728` assertions, `0` failures). No Pandoc, JSON filters, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

### XML/JATS/BITS Direct Reader Capability Update (2026-06-13)

Bounded native PHP registry and DOM coverage advanced by one XML/JATS/BITS capability slice after Markdown header section Div mapping. `PandocFormatRegistry` now exposes `xml`, `jats`, and `bits` as explicit unsupported direct-reader inputs with bounded diagnostic surfaces, and `XmlHtmlDom::summarizeJatsFrontMatter()` emits direct-reader diagnostics for unsupported full-reader gaps while preserving `directReaderParity=false`.

Verification passed `php -l` for `PandocFormatRegistry.php`, `XmlHtmlDom.php`, `PandocFormatRegistryTest.php`, and `XmlHtmlDomTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `1168` assertions, `0` failures); focused `XmlHtmlDomTest.php` passed (`1` file, `1843` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74698` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, Node tooling, external XML validator, online service, live provider test, or live-service provider test was invoked.

### Markdown Header Section Div Update (2026-06-13)

Bounded native PHP Markdown block-structure coverage advanced by one opt-in header-to-section grouping slice after Markdown table-cell note placement. `MarkdownReader` now accepts `sectionDivs => true` and groups parsed headings into nested `div` sections with `section` and `levelN` classes, moving heading identifiers, classes, and key-value attributes to the section wrapper so Markdown and WordPress handoff keep stable section anchors without duplicate heading identifiers.

Verification passed `php -l` for `MarkdownReader.php` and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6685` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74621` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, Node tooling, TeX/PDF engine, office suite, online service, live provider, or external validator was invoked.

### Markdown Table Cell Note Placement Update (2026-06-13)

Bounded native PHP notes/reference placement coverage advanced by one Markdown table-cell slice after DOCX/OpenXML subdocument diagnostics. `MarkdownReaderTest` now verifies a labelled note reference inside a pipe-table cell remains in the table cell while the linked note body and escaped pipe round-trip into Markdown note definitions and WordPress doc-endnotes outside the table boundary, preserving the already-landed `footnote-back`/`doc-backlink` backlink metadata.

Verification passed `php -l` for `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6668` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74604` assertions, `0` failures). No Pandoc, browser renderer, Node tooling, TeX engine, office suite, online service, live provider, or external validator was invoked.

### DOCX/OpenXML Subdocument Diagnostic Update (2026-06-13)

Bounded native PHP DOCX/OpenXML package-reader coverage advanced by one unsupported subdocument relationship diagnostic slice after definition-list term-group coverage. `DocxOpenXmlReader` now emits metadata-only `w:subDoc`/subDocument diagnostics for referenced and unreferenced relationships, external URI targets, internal package targets, missing targets, wrong relationship types, unknown relationship IDs, missing relationship IDs, query/fragment suffixes, content-type/hash metadata, `directReaderParity=false`, and subdocument package inventory roles.

Recovered predecessor evidence: closed bead `plib-achtq` only records MR `plib-wisp-mu2` and no issue notes; recoverable evidence is the prior `progress.md` DOCX/OpenXML ship-readiness update plus commit `f4d0e410e4`, which recorded DOCX at 92 local cases / 35 upstream rows and not ship-ready.

Verification passed `php -l` for `DocxOpenXmlReader.php` and `DocxOpenXmlReaderTest.php`; focused `DocxOpenXmlReaderTest.php` passed (`1` file, `2412` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74595` assertions, `0` failures). No Pandoc, Word, LibreOffice, office suite, zip/unzip, ZipArchive, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

### Definition List Term Group Update (2026-06-13)

Bounded native PHP list handoff advanced by one definition-list term parity slice after EPUB XHTML definition-list handoff. `MarkdownReader` now groups stacked Markdown definition terms as `LineBreak`-separated term inlines, `MarkdownWriter` emits those groups as separate term lines, and `WordPressBlockWriter` renders Pandoc JSON/native `definition_term` nodes instead of dropping native term text.

Verification passed `php -l` for `MarkdownReader.php`, `MarkdownWriter.php`, `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and `PandocJsonNativeAstTest.php`; focused `MarkdownReaderTest.php` plus `PandocJsonNativeAstTest.php` passed (`2` files, `8762` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74525` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, TeX engine, Node tooling, office suite, online service, live provider, or external validator was invoked.

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

### Markdown Fenced Div Section Reference Update (2026-06-13)

Bounded native PHP Markdown writer coverage advanced by one fenced Div section-reference slice. `MarkdownWriter` now flushes pending footnote and reference definitions before nested headings and before a fenced Div closing fence when `referenceLocation=end_of_section`, keeping section-local notes and reference links inside their Div boundaries instead of leaking to the document tail.

Verification passed `php -l` for `MarkdownWriter.php` and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` passed (`1` file, `6653` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74487` assertions, `0` failures). No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, TeX or office tooling, online service, live provider, or external validator was invoked.

### Markdown/WordPress Footnote Label Update (2026-06-12)

Bounded native PHP notes handoff advanced by one cross-format slice. MarkdownWriter now preserves valid source footnote labels with collision handling, and WordPressBlockWriter uses label-derived `fn`/`fnref` anchors plus `data-pandoc-note-label` provenance for named source notes while generated inline notes stay numeric.

Verification passed `php -l` for `MarkdownWriter.php`, `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and `CitationCslProcessorTest.php`; focused `MarkdownReaderTest.php` and `CitationCslProcessorTest.php` passed (`2` files, `11909` assertions, `0` failures); full `lanes/pandoc/tests` passed (`44` files, `74054` assertions, `0` failures). No Pandoc, Cabal/Haskell runner, browser renderer, TeX engine, Node tooling, online service, live provider, or external validator was invoked.

### Markdown/WordPress Footnote Backlink Update (2026-06-13)

Bounded native PHP notes handoff advanced by one WordPress writer slice. `WordPressBlockWriter` now emits footnote backlinks with `class="footnote-back"` and `role="doc-backlink"` while preserving label-derived anchors, generated numeric anchors, `data-pandoc-note-label` provenance, and link serialization inside note bodies.

Verification passed `php -l` for `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and `DocxReaderTest.php`; focused `MarkdownReaderTest.php` plus `DocxReaderTest.php` passed (`2` files, `11750` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74349` assertions, `0` failures). No Pandoc, browser renderer, Node tooling, TeX engine, office suite, online service, live provider, or external validator was invoked.

### Text Markup Reader Ship-Gate Update (2026-06-13)

Bounded native PHP registry accounting now records an executable unsupported ship-gate verdict for 20 wiki/roff/man/text markup reader input tokens: `asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`, `mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, and `vimwiki`. `PandocFormatRegistry::textMarkupReaderShipGate()` reports upstream denominator `20`, local passing numerator `0`, unsupported count `20`, family buckets `wiki=7`, `roff-manual=2`, `text-markup=11`, and direct reader parity `false`.

Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `1106` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74434` assertions, `0` failures). No Pandoc binary, wiki renderer, roff renderer, Cabal/Haskell runner, browser renderer, external validator, online service, live provider, or live-service provider test was invoked.

### Inline Attribute Writer Handoff Update (2026-06-13)

Bounded native PHP inline writer coverage advanced by one cross-format handoff slice. `WordPressBlockWriter` now emits safe inline attributes for semantic inline nodes and math spans, preserving `id`, classes, `data-*`, `aria-*`, language, role, title, and translate metadata while filtering unsafe style and event handlers. `LatexWriter` now wraps semantic inline commands, code spans, and math spans that carry ids in `\protect\hypertarget{...}{...}` anchors.

Verification passed `php -l` for `LatexWriter.php`, `WordPressBlockWriter.php`, `LatexWriterTest.php`, and `MarkdownReaderTest.php`; focused `MarkdownReaderTest.php` plus `LatexWriterTest.php` passed (`2` files, `6665` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74485` assertions, `0` failures). No Pandoc, TeX, browser renderer, JSON filter, spreadsheet tool, external validator, online service, live provider, or live-service provider test was invoked.

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

### EPUB XHTML Definition-List Handoff Update (2026-06-13)

Bounded native PHP EPUB3 direct package-reader coverage advanced by one XHTML spine structural handoff slice. `EpubPackageReader` now maps linear spine XHTML `<dl>`, `<dt>`, and `<dd>` content into shared `definition_list` AST nodes while preserving source id/class attributes, term text, inline strong/link children, package-local links, loose multi-block definitions, nested list bodies, and WordPress `<dl>` output.

Verification passed `php -l` for `EpubPackageReader.php` and `EpubPackageReaderTest.php`; focused `EpubPackageReaderTest.php` passed (`1` file, `213` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `74513` assertions, `0` failures). No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider, or live-service provider test was invoked.

### EPUB XHTML Table Semantics Update (2026-06-13)

Bounded native PHP EPUB3 content-reader coverage advanced by one XHTML table semantics slice after PlainWriter table body-group boundaries. `EpubReader` now preserves table caption text and ids, colgroup counts, direct section order, `thead`/`tbody`/`tfoot` and implicit-body row counts, header-cell `scope` and `headers` associations, row/column spans, nested-table counts, diagnostics, aggregate XHTML resource report counters, import-report propagation, and `raw_html` node attributes.

Remaining critical EPUB gaps: direct EPUB package reader parity still needs broader structural/content coverage beyond this bounded table-semantics slice, including full XHTML-to-AST conversion policy, section/header mapping, nav/NCX edge provenance, metadata propagation, package structural diagnostics, and media/resource handling. EPUB denominator/local numerator is now 9 upstream rows / 62 local mapped EPUB evidence cases (688.9%). Verification passed `php -l` for `EpubReader.php` and `EpubReaderTest.php`, focused `EpubReaderTest.php` (`1` file, `4328` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74818` assertions, `0` failures). No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node tooling, online service, live provider test, or external validator was invoked.

### Shared ZIP/OPC Selected Handoff Role Bucket Update (2026-06-13)

Bounded native PHP shared ZIP/OPC package coverage advanced by one selected-entry role bucket slice after EPUB XHTML table semantics. `ZipPackage::entryHandoffPreflight()` role summaries now expose `handoffUniqueEntryCount`, `handoffCompressedBytes`, `handoffUncompressedBytes`, `handoffEntryNames`, and `issueCounts`, so DOCX/EPUB/ODT package readers can review readable bytes, duplicate requests, oversized sidecars, and missing required package parts by semantic role before exposing selected package payloads.

Remaining critical ZIP/OPC gaps: full package parity is still partial because ZIP64 expansion, encrypted payload decryption, non-deflate extraction, cryptographic signature validation, DOCX/EPUB package readers, and PPTX/XLSX package readers are not globally shippable. Shared ZIP/OPC denominator/local numerator is now 67 upstream dependency rows / 107 local mapped evidence cases (159.7%). Verification passed `php -l` for `ZipPackage.php` and `ZipPackageTest.php`, focused `ZipPackageTest.php` (`1` file, `4448` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74831` assertions, `0` failures). No Pandoc, office suite, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

### Text Markup Unsupported Diagnostics Update (2026-06-13)

Verdict: explicit unsupported state, not ship-ready. The native PHP lane still has 0 native reader passes across the 20 accepted wiki/roff/manual/lightweight text input tokens, but the registry now records a focused diagnostic packet for the full 20-token denominator.

Implemented gap: `PandocFormatRegistry` now exposes `textMarkupUnsupportedFormatReviewPacket()` and per-format diagnostics with family buckets, unsupported reason codes, input/output status, unsupported directions, reader/writer capability flags, implementation names, and native-only policy evidence. The diagnostic groups the 20 unsupported input tokens into 11 lightweight-markup formats, 7 wiki formats, and 2 roff/manual formats without registering a parser or writer.

Verification passed `php -l` for `PandocFormatRegistry.php` and `PandocFormatRegistryTest.php`; focused `PandocFormatRegistryTest.php` passed (`1` file, `1349` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75012` assertions, `0` failures). No Pandoc binary, wiki renderer, roff tool, Cabal/Haskell runner, browser renderer, online validator, online service, live provider test, or external validator was invoked.

Remaining critical gap: implement native readers for the text markup formats or make an explicit product deferral; this slice only improves diagnostic/capability metadata.

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

Follow-up generic raw HTML serialization gap: `MarkdownWriter` now preserves typed `raw_html_inline` payloads plus generic `raw_inline`/`raw_block` payloads whose raw format is `html`, `html4`, `html5`, or `xhtml`, while unsupported raw formats stay disabled. NativeReader to MarkdownWriter round trips now preserve raw HTML inline/block payloads and WordPress still handles raw HTML blocks through the existing HTML path. Verification passed `php -l` for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and `NativeReaderTest.php`; focused `NativeReaderTest.php` plus `MarkdownReaderTest.php` passed (`2` files, `7059` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75050` assertions, `0` failures). No Pandoc binary, browser renderer, Node tooling, external validator, online service, live provider test, or live-service provider test was invoked.

### PDF/Typst Boundary Ship-Readiness Update (2026-06-13)

Verdict: not shippable for real PDF/Typst output parity because native PHP does not execute external TeX/Typst/PDF engines. Graceful no-external-engine boundary diagnostics now have 47 local mapped PDF/Typst boundary/provenance cases against 17 upstream format-related cases (276.5%), with no known critical uncovered graceful-boundary rows.

| Format focus | Upstream denominator | Local passing evidence | Evidence percent | Remaining critical gap |
| --- | ---: | ---: | ---: | --- |
| PDF/Typst graceful boundary/provenance diagnostics | 17 | 47 | 276.5% | Full output parity still requires external engine execution, which remains unsupported in native PHP. |

Implemented highest-impact gap: `PdfEngineHandoff::fakeRun()` now extends Typst package dependency policy with sidecar package input counts, metadata-only byte exposure, non-executed network policy, package coordinates, namespace counts, and multi-version conflict diagnostics while preserving successful graceful behavior without external engines. Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`, focused `PdfEngineHandoffTest.php` (`1` file, `2225` assertions, `0` failures), and full `lanes/pandoc/tests` (`45` files, `74226` assertions, `0` failures). No Pandoc, Typst, TeX/PDF engine, browser, Node, online service, live provider, or external validator was invoked.

Follow-up provenance gap: `PdfEngineHandoff::fakeRun()` now classifies Typst package dependencies by source bucket while preserving the existing package dependency policy fields. Structured dependency rows include `sourceClass`, policy packets expose deterministic `sourceClasses` and `sourceClassCounts`, and diagnostics report `typst-package-dependency-source` counts for `custom-namespace`, `preview-registry`, and `typst-registry` dependencies. Verification passed `php -l` for `PdfEngineHandoff.php` and `PdfEngineHandoffTest.php`; focused `PdfEngineHandoffTest.php` passed (`1` file, `2231` assertions, `0` failures); full `lanes/pandoc/tests` passed (`45` files, `75045` assertions, `0` failures). No Pandoc, Typst, TeX/PDF engine, browser renderer, Node tooling, online service, live provider, or external validator was invoked.

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
commit history agree on the current shipping call after the JATS/BITS body
diagnostics slice, CSV/TSV format inference diagnostics slice, DocBook structural review diagnostics slice, IPYNB
metadata/resource diagnostics slice, XML/HTML object association slice, MediaBag linked-resource
MIME inference slice, YAML metadata alias summary slice, JSON/native table
attribute writer handoff slice,
JSON/native mixed table caption/cell flushing
slice, DOCX section-property slice, tabular data
registry option profiles slice, ODF compact manifest custom attributes slice,
DOCX/OpenXML note/comment relationship diagnostics slice, EPUB3 NCX document
metadata provenance slice, XML/HTML/JATS front-matter slice, RIS citation parser
slice, JSON/native target tuple sidecar slice, plain writer table caption row
slice, PDF/Typst package dependency policy slice, PDF/Typst package source-class
policy slice, Markdown/WordPress raw HTML boundary slice, Markdown generic raw
HTML serialization slice, JSON/native table caption block writer slice, MediaBag
linked-resource handoff slice, Markdown adjacent-list separator slice,
Markdown/WordPress footnote label anchor slice, Markdown nested fenced Div slice,
ODT configuration package metadata slice, Markdown fixture nested-list round-trip
slice, CSV/TSV direct text reader slice, CSV/TSV header option parity slice,
JSON/native note label sidecar slice, LaTeX table-foot longtable writer slice,
JSON/native Div Plain block-boundary slice, PDF/Typst package dependency conflict
policy slice, JSON/native task-list checkbox sidecar slice, JSON/native nullary
helper payload validation slice, LaTeX table body-head writer slice, Markdown
fenced Div escaped-attribute slice, Markdown fenced Div section-reference boundary
slice, Markdown/WordPress footnote backlink slice, Pandoc text markup reader
unsupported ship-gate slice, text markup unsupported diagnostics slice, EPUB
direct manifest/spine report slice, EPUB XHTML definition-list handoff slice,
EPUB XHTML table semantics slice, shared ZIP selected-entry role bucket slice,
definition-list term-group handoff slice, DOCX/OpenXML subdocument diagnostic
slice, Markdown table-cell note placement slice, Markdown header section Div
slice, XML/JATS/BITS direct reader capability diagnostics, JSON/native
block-container mixed-content flushing, Markdown math attribute round-trip,
Markdown HTML list item attribute handoff, PlainWriter table body-group
boundaries, and Pandoc inline attribute writer handoff slice.

| Check | Evidence | Verdict |
| --- | --- | --- |
| Upstream denominator | Static upstream inventory remains 2,276 Pandoc test/data/benchmark artifacts at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`; input-format scope is 50 tokens after skipping IPYNB for this phase. | Denominator accepted for native PHP progress accounting; not upstream runner parity. |
| Local passing numerator | `lane-status.json` reports 3,353 PHP passes / 0 failures, and `UPSTREAM_TEST_MANIFEST.json` reports 3,313 mapped upstream cases. | PHP lane remains green. |
| Percent | 3,313 / 2,276 = 145.6%; percentages above 100% reflect local PHP slices being more granular than upstream inventory rows. | High coverage, but not global ship-ready. |
| Shippable format gate | ODF/ODT is ship-ready with 51 local mapped cases / 20 upstream ODF/ODT cases, 255.0%, and 0 critical ODF/ODT gaps. | ODF/ODT can ship under the native PHP/no-external-validator policy. |
| Remaining critical gaps | 18 input tokens remain partial and 31 remain unsupported across DOCX/OpenXML, EPUB3, shared ZIP/OPC dependencies, JSON/native AST, CSV/TSV, CSL/BibTeX/BibLaTeX/csljson, HTML/XML/JATS DOM, LaTeX/TeX/math, Typst, PPTX/XLSX, and wiki/roff/text readers. JATS/BITS body diagnostics, CSV/TSV format inference diagnostics, DocBook structural review diagnostics, IPYNB notebook metadata/resource diagnostics, XML/HTML object association provenance, MediaBag linked-resource MIME inference, YAML metadata alias review summaries, JSON/native table attribute writer handoff, JSON/native mixed table caption/cell flushing, tabular data registry option profiles, ODF compact manifest custom attributes, DOCX note/comment relationship diagnostics, Markdown generic raw HTML serialization, Typst package source-class policy provenance, CSV/TSV header option parity, text markup unsupported diagnostics, shared ZIP selected-entry role buckets, EPUB XHTML table semantics, PlainWriter table body-group boundaries, Markdown HTML list item attribute handoff, Markdown math attribute round-trip, JSON/native block-container mixed-content flushing, XML/JATS/BITS direct reader capability diagnostics, Markdown header section Div mapping, Markdown table-cell note placement, DOCX/OpenXML subdocument diagnostics, definition-list term-group handoff, Markdown fenced Div section-reference boundaries, inline writer attributes, EPUB direct manifest/spine diagnostics, EPUB XHTML definition-list handoff, and text markup unsupported ship-gate accounting are covered. | Full Pandoc input lane remains active. |
| Stale assigned-open cleanup | `bd orphans --label lane:pandoc` was filtered to commits that are ancestors of `origin/main`; only `plib-qka5o` qualified and was closed as landed. Follow-up main-ancestor orphan count is 0. Branch-only orphan candidates were left open. | Dashboard queue state now reflects landed work without closing live branch work. |
| Verification | `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `git diff --check`, syntax checks for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`, focused `XmlHtmlDomTest.php`, and `php tools/run-tests.php lanes/pandoc/tests` passed. | Focused: 1 file, 1,970 assertions, 0 failures. Full: 45 files, 75,481 assertions, 0 failures. |

Methodology: upstream denominators come from `lanes/pandoc/notes/upstream-inventory.md`,
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, and the input-format registry in
`lanes/pandoc/src/PandocFormatRegistry.php`, which records 51 upstream Pandoc
input tokens from the 2026-06-03 manual and upstream source commit
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Local passing counters
merge `mapped*Cases` from `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and current
`lanes/pandoc/lane-status.json`; `phpPass`/`phpFail` come from
`lanes/pandoc/lane-status.json`. Commands used: `jq` over the manifest and lane
status JSON to list case counters, PHP registry inspection for input support
status, `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php`,
`php -l lanes/pandoc/src/XmlHtmlDom.php`,
`php -l lanes/pandoc/tests/XmlHtmlDomTest.php`,
`php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
(`1` file, `1970` assertions, `0` failures), and `php tools/run-tests.php lanes/pandoc/tests`
(`45` files, `75481` assertions, `0` failures after final rebase onto base `dc8677bb`).
`bd orphans --label lane:pandoc` was used for stale-open cleanup, but only
main-ancestor referenced commits were closed. No Pandoc binary, office suite,
TeX/Typst engine, browser engine, Node tooling, or external validator was invoked.
